<?php
/**
 * AJAX backend for the Auto Article wizard's final "Create Batch" step —
 * validates the wizard's settings and titles, then creates the batch and
 * queues one `articles` row per title (create_article_batch() spreads them
 * across days at the chosen daily pace). The cron worker then picks them up.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$payload = json_decode($_POST['payload'] ?? '', true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$titles = $payload['titles'] ?? [];
if (!is_array($titles) || empty($titles)) {
    echo json_encode(['ok' => false, 'error' => 'Please add at least one title.']);
    exit;
}
if (count($titles) > 60) {
    echo json_encode(['ok' => false, 'error' => 'Please keep a batch to 60 titles or fewer.']);
    exit;
}

$articleType = ($payload['article_type'] ?? 'ideas') === 'recipe' ? 'recipe' : 'ideas';
$websiteId = (int)($payload['website_id'] ?? 0);

// Confirm the website belongs to this user and is connected.
$stmt = $pdo->prepare("SELECT id, platform FROM websites WHERE id = ? AND user_id = ? AND status = 'connected' AND platform <> 'none'");
$stmt->execute([$websiteId, $user['id']]);
$websiteRow = $stmt->fetch();
if (!$websiteRow) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid connected website.']);
    exit;
}

$publishMode = ($payload['publish_mode'] ?? 'now') === 'pin_auto' ? 'pin_auto' : 'now';
$wpAuthorId = (int)($payload['wp_author_id'] ?? 0);
// Only WordPress sites have a selectable author; Shopify / Wix / custom sites use their own default.
if ($websiteRow['platform'] === 'wordpress' && $wpAuthorId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Please select an author.']);
    exit;
}
$pinSettingsJson = null;
if ($publishMode === 'pin_auto') {
    $pinSettings = $payload['pin_settings'] ?? [];
    if (empty($pinSettings['pinterest_account_id'])) {
        echo json_encode(['ok' => false, 'error' => 'Please choose a Pinterest account for Pin Auto.']);
        exit;
    }
    $pinSettings['article_pin_gap_unit'] = ($pinSettings['article_pin_gap_unit'] ?? 'days') === 'minutes' ? 'minutes' : 'days';
    $pinSettings['article_pin_gap_minutes'] = $pinSettings['article_pin_gap_unit'] === 'minutes'
        ? max(1, min(525600, (int)($pinSettings['article_pin_gap_minutes'] ?? 60))) : null;
    $pinSettings['article_pin_gap_days'] = max(1, min(365, (int)($pinSettings['article_pin_gap_days'] ?? 30)));
    $pinSettings['pin_image_category_id'] = max(0, (int)($pinSettings['pin_image_category_id'] ?? 0));
    $pinSettingsJson = json_encode($pinSettings);
}

$batch = [
    'name' => trim($payload['name'] ?? ''),
    'article_type' => $articleType,
    'website_id' => $websiteId,
    'category' => trim($payload['category'] ?? ''),
    'wp_author_id' => $wpAuthorId,
    'tags_enabled' => !empty($payload['tags_enabled']),
    'daily_count' => max(1, (int)($payload['daily_count'] ?? 1)),
    'length_mode' => in_array($payload['length_mode'] ?? 'auto', ['auto', 'min', 'random'], true) ? $payload['length_mode'] : 'auto',
    'min_words' => ($payload['length_mode'] ?? '') === 'min' ? max(300, min(8000, (int)($payload['min_words'] ?? 1500))) : null,
    'feature_image_w' => max(200, (int)($payload['feature_image_w'] ?? 1200)),
    'feature_image_h' => max(200, (int)($payload['feature_image_h'] ?? 630)),
    'ideas_image_size' => trim($payload['ideas_image_size'] ?? '3:4'),
    'recipe_image_count' => max(1, min(10, (int)($payload['recipe_image_count'] ?? 3))),
    // Only 3 credit types: budget / high / ultra (the old "cloudflare 0.2" option is gone).
    'image_quality' => in_array(($payload['image_quality'] ?? ''), ['budget', 'high', 'ultra'], true) ? $payload['image_quality'] : 'budget',
    'image_category_id' => max(0, (int)($payload['image_category_id'] ?? 0)),
    'publish_mode' => $publishMode,
    'pin_settings_json' => $pinSettingsJson,
];

$titleRows = [];
foreach ($titles as $t) {
    if (!is_array($t) || trim($t['title'] ?? '') === '') continue;
    $titleRows[] = [
        'title' => trim($t['title']),
        'source_type' => in_array($t['source_type'] ?? '', ['competitor_url'], true) ? 'competitor_url' : 'keyword',
        'source_value' => $t['source_value'] ?? null,
    ];
}
if (empty($titleRows)) {
    echo json_encode(['ok' => false, 'error' => 'Please add at least one valid title.']);
    exit;
}

$result = create_article_batch($pdo, $user['id'], $batch, $titleRows);
log_event($pdo, 'system', "Auto Article batch created: {$result['count']} articles queued", $user['id']);

// Respond right away, then detach from this connection and start processing
// immediately (rather than waiting for the next "tick" or a manual click) —
// this survives the browser navigating to the batch view page or closing.
ignore_user_abort(true);
@set_time_limit(90);
if (function_exists('fastcgi_finish_request')) {
    echo json_encode($result);
    fastcgi_finish_request();
    run_due_article_steps($pdo, 6);
} else {
    echo json_encode($result);
}
