<?php
/**
 * AJAX backend for user/bulk-schedule.php's "Create Pin Image with AI" panel.
 * Called once per title/link line from the client (sequential loop), so the
 * UI can append each finished pin to Panel 2 as soon as it's ready.
 *
 * Flow: resolve title (fetching a link's page title if needed) -> build a
 * background-image prompt -> generate 1 (single) or N (collage) background
 * images with the admin-configured provider, retrying each up to 3 times
 * (immediate, then +3s, then +4s) -> composite title/CTA/website text onto
 * it with GD -> save into uploads/pins/ -> deduct credits on success only.
 */
@set_time_limit(180); // a collage of up to 6 images, each retried up to 3x, can legitimately take a while
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
if (!function_exists('imagecreatetruecolor')) {
    echo json_encode(['ok' => false, 'error' => 'The PHP GD extension is required for AI pin-image generation and is not enabled on this server.']);
    exit;
}

$user = current_user($pdo);

$input = trim($_POST['input'] ?? '');            // a title OR a link, one line from the user's list
$sizeKey = trim($_POST['size'] ?? '2:3');
$website = trim($_POST['website'] ?? '');
$ctaMode = trim($_POST['cta_mode'] ?? 'auto');    // 'auto' | 'none' | 'custom'
$ctaText = trim($_POST['cta_text'] ?? '');
$customPrompt = trim($_POST['custom_prompt'] ?? '');
// Single vs collage is decided by the chosen template (see pin_template_registry()), not by the user.
$quality = trim($_POST['quality'] ?? 'budget');   // 'budget' | 'high' | 'ultra' (ignored when admin picked Cloudflare)
$imageStyle = pin_sanitize_style_value((string)($_POST['image_style'] ?? 'auto'));
$colorPalette = trim($_POST['color_palette'] ?? ''); // optional brand color palette JSON (see pin_normalize_color_palette())
$categoryId = (int)($_POST['image_category_id'] ?? 0);   // "Select category" (0 = let the AI infer from the title)

if ($input === '') {
    echo json_encode(['ok' => false, 'error' => 'Empty input.']);
    exit;
}

// Resolve title: if this line looks like a URL, fetch its page title first.
$resolvedTitle = $input;
$sourceWasLink = false;
if (preg_match('#^https?://#i', $input)) {
    $sourceWasLink = true;
    $fetchedTitle = extract_title_from_url($input);
    $resolvedTitle = $fetchedTitle ?: $input;
}
$resolvedTitle = mb_substr($resolvedTitle, 0, 120);

// One text-AI step prepares the pin: a short, unique headline for the overlay (long or repetitive
// titles are what cause cramped or duplicated text) and a photo scene that fits the title and the
// chosen category, described so the image model has nothing to write on.
$categoryPath = image_category_path($pdo, $categoryId);
$originalResolvedTitle = $resolvedTitle;
$brief = pin_prepare_image_brief($pdo, $resolvedTitle, $categoryPath, $customPrompt);
$resolvedTitle = $brief['headline'];
$titleWasShortened = $resolvedTitle !== $originalResolvedTitle;

// Model per quality tier: Admin → Bulk Pin Scheduler → Image settings. Cost: Plan Pricing → Setting.
$imgModel = image_model_for($pdo, 'bulk_pin', $quality);
$provider = $imgModel['provider'];
$model = $imgModel['model'];
$iterations = $imgModel['iterations'];

$cost = $imgModel['cost'];
// Pick this pin's template now (AI Auto / one of the multi-selected), so we know how many photos it needs.
$imageStyle = pin_resolve_style($imageStyle, $resolvedTitle);
$imagesNeeded = pin_template_image_count($imageStyle);
$imageType = $imagesNeeded > 1 ? 'collage' : 'single';
$totalCost = round($cost * $imagesNeeded, 2);

$balance = get_user_image_credits($pdo, $user['id']);
if ($balance < $totalCost) {
    echo json_encode(['ok' => false, 'error' => "Your image AI credits are low ({$balance} left, need {$totalCost}) — upgrade your plan to get more.", 'title' => $resolvedTitle]);
    exit;
}

// CTA resolution.
if ($ctaMode === 'none') {
    $ctaText = '';
} elseif ($ctaMode === 'auto' || $ctaText === '') {
    $ctaText = auto_pick_cta($resolvedTitle);
}

// Single photos use the pin's own shape; collage photos are portrait and each one is a different variation.
$gen = pin_generate_template_images($pdo, $imageStyle, $resolvedTitle, $customPrompt, $brief, $sizeKey, $provider, $model, $iterations);
$imageBytesList = $gen['images'];
$genError = $gen['error'];

if (empty($imageBytesList) || count($imageBytesList) < $imagesNeeded) {
    echo json_encode([
        'ok' => false,
        'title' => $resolvedTitle,
        'resolved_title' => ($sourceWasLink || $titleWasShortened) ? $resolvedTitle : null,
        'error' => $genError ?: 'Image generation failed after 3 attempts.',
    ]);
    exit;
}

$composited = compose_pin_image($imageBytesList, $resolvedTitle, $website, $ctaText, $sizeKey, $imageStyle, $colorPalette);
if (!$composited) {
    echo json_encode(['ok' => false, 'title' => $resolvedTitle, 'error' => 'Could not compose the final pin image.']);
    exit;
}

$destDir = __DIR__ . '/../uploads/pins/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);
$filename = 'aipin_' . bin2hex(random_bytes(8)) . '.jpg';
file_put_contents($destDir . $filename, $composited);

deduct_image_credits($pdo, $user['id'], $totalCost);
log_event($pdo, 'ai', "Generated an AI pin image ({$imageType}, {$quality}/{$provider}) for '{$resolvedTitle}' — {$totalCost} credits", $user['id']);

echo json_encode([
    'ok' => true,
    'path' => 'uploads/pins/' . $filename,
    'filename' => $filename,
    'title' => $resolvedTitle,
    'resolved_title' => ($sourceWasLink || $titleWasShortened) ? $resolvedTitle : null,
    'cta' => $ctaText,
    'cost' => $totalCost,
    'remaining_credits' => get_user_image_credits($pdo, $user['id']),
]);
