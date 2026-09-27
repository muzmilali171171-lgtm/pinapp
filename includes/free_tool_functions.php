<?php
$GLOBALS['TT_SKIP'] = true; // free-tool images for visitors aren't tracked pins
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_functions.php';
require_once __DIR__ . '/storage_functions.php';

/**
 * Free Tools -> Pinterest Pin Maker (public, no-login tool).
 *
 * Lets an anonymous visitor paste a URL, scans that single page for images
 * (reuses scan_page_for_images() from Storage), writes AI titles/descriptions
 * for it, and composites a small gallery of pin designs from those images
 * using the SAME rendering engine as Bulk Pin Scheduler / Auto Website to
 * Daily Pin (compose_pin_image() and its pin_render_* styles). Those render
 * styles are what's presented here as "templates" — this reuses the real
 * pin-design engine rather than a separate one.
 *
 * Anonymous usage is capped (admin-configurable, default 10 successful
 * generations) per browser session + IP, tracked in free_tool_usage.
 * Whatever a visitor last generated is kept in $_SESSION so that if they
 * click "Start Free Now" and sign up, the pins they already made can be
 * carried into their account (see FREE_TOOL_SESSION_KEY below; the redirect
 * target that consumes it is the Classic Wizard, a separate build).
 */

const FREE_TOOL_SESSION_KEY = 'free_tool_pending';

/** The template styles a visitor can choose from — each maps to a real compose_pin_image() style. */
function free_tool_template_options(): array
{
    return [
        'high_attractive_multi' => 'Bold Headline',
        'simple' => 'Minimal Caption',
        'simple2' => 'Banner Badge',
        'unique_multi' => 'Photo Collage',
        'fashion_outfits' => 'Editorial Stack',
        'fashion_outfits2' => 'Centered Overlay',
        'fashion_outfits3' => 'Grid Collage',
        'home_decor' => 'Soft Frame',
        'home_decor2' => 'Full Bleed',
        'recipe_food' => 'Recipe Card',
        'recipe_food2' => 'Recipe Card (Light Band)',
        'pet_recipe' => 'Two-Tone Split',
    ] + pin_extra_style_labels() + array_map(fn($t) => $t['name'], array_filter(pin_template_registry(), fn($t) => strpos($t['key'], 'tpl_') === 0));
}

/** Admin-configurable settings for this tool, with sane defaults for a fresh install. */
function free_tool_get_settings(PDO $pdo): array
{
    $row = get_article_settings($pdo) ?: [];
    return [
        'text_provider' => $row['freetool_text_provider'] ?? ($row['wpin_text_provider'] ?? ($row['pin_text_provider'] ?? ($row['text_provider'] ?? null))),
        'text_model' => $row['freetool_text_model'] ?? ($row['wpin_text_model'] ?? ($row['pin_text_model'] ?? ($row['text_model'] ?? null))),
        'max_attempts' => isset($row['freetool_max_attempts']) && $row['freetool_max_attempts'] !== null ? (int)$row['freetool_max_attempts'] : 10,
        'ai_design' => isset($row['freetool_ai_design']) ? (bool)$row['freetool_ai_design'] : true,
        'template_set_count' => isset($row['freetool_template_count']) && $row['freetool_template_count'] !== null ? (int)$row['freetool_template_count'] : 4,
        'coupon_code' => $row['freetool_coupon_code'] ?? 'PIN20',
        'discount_percent' => isset($row['freetool_discount_percent']) && $row['freetool_discount_percent'] !== null ? (int)$row['freetool_discount_percent'] : 20,
        'marketing_heading' => $row['freetool_marketing_heading'] ?? 'Want to generate Pins on autopilot?',
        'marketing_body' => $row['freetool_marketing_body'] ?? 'Create hundreds of Pinterest Pins for your website in minutes. Choose from multiple templates, customize fonts and colors, and schedule 30 days in advance.',
    ];
}

/* ===================== Anonymous rate limiting ===================== */

function free_tool_session_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['free_tool_token'])) {
        $_SESSION['free_tool_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['free_tool_token'];
}

function free_tool_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** How many generations this visitor has left for $tool (never negative). $perDay=true resets at midnight; otherwise cumulative across all days. */
function free_tool_attempts_remaining(PDO $pdo, int $maxAttempts, string $tool = 'pin_maker', bool $perDay = false): int
{
    $token = free_tool_session_token();
    if ($perDay) {
        $stmt = $pdo->prepare("SELECT attempts FROM free_tool_usage WHERE session_token = ? AND tool = ? AND usage_date = CURDATE()");
        $stmt->execute([$token, $tool]);
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(attempts), 0) FROM free_tool_usage WHERE session_token = ? AND tool = ?");
        $stmt->execute([$token, $tool]);
    }
    $used = (int)($stmt->fetchColumn() ?: 0);
    return max(0, $maxAttempts - $used);
}

/** Records one generation against this visitor's session+IP for $tool, today. */
function free_tool_record_attempt(PDO $pdo, string $tool = 'pin_maker'): void
{
    $token = free_tool_session_token();
    $ip = free_tool_client_ip();
    $pdo->prepare("INSERT INTO free_tool_usage (session_token, ip_address, tool, usage_date, attempts, created_at, updated_at)
        VALUES (?, ?, ?, CURDATE(), 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, ip_address = VALUES(ip_address), updated_at = NOW()")
        ->execute([$token, $ip, $tool]);
}

/* ===================== Generation ===================== */

/**
 * Scans $pageUrl for images, writes one AI title/description for the page,
 * then composites a small gallery (one pin per requested style) using those
 * images. Returns ['ok','error','page_title','pins' => [['style','image_url','title','description'],...]].
 */
function free_tool_generate_pins(PDO $pdo, string $pageUrl, array $settings, array $styles): array
{
    $pageUrl = trim($pageUrl);
    if ($pageUrl === '' || !filter_var($pageUrl, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'Please enter a valid page URL.', 'pins' => []];
    }

    $scan = scan_page_for_images($pageUrl);
    if (!$scan['ok'] || empty($scan['images'])) {
        return ['ok' => false, 'error' => $scan['error'] ?? 'No images found on that page.', 'pins' => []];
    }

    // Download up to 4 candidate images — the pin renderers pick what they need from this list.
    $imageBytesList = [];
    foreach (array_slice($scan['images'], 0, 4) as $imgUrl) {
        $ch = curl_init($imgUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data !== false && $httpCode < 300 && $contentType && stripos($contentType, 'image/') === 0) {
            $imageBytesList[] = $data;
        }
    }
    if (empty($imageBytesList)) {
        return ['ok' => false, 'error' => 'Could not download any images from that page.', 'pins' => []];
    }

    $pageTitle = extract_title_from_url($pageUrl) ?: parse_url($pageUrl, PHP_URL_PATH);
    $website = parse_url($pageUrl, PHP_URL_HOST) ?: '';

    // One AI-written title/description for the page, reused across every template in the gallery
    // (each template renders it differently — that's the point of previewing several at once).
    $content = free_tool_ai_content($pdo, $pageTitle, $settings);
    $title = $content['title'] ?? $pageTitle;
    $ctaText = auto_pick_cta($title);

    $destDir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $pins = [];
    foreach ($styles as $styleKey) {
        $composited = compose_pin_image($imageBytesList, $title, $website, $ctaText, '2:3', $styleKey);
        if (!$composited) continue;
        $filename = 'freetool_' . bin2hex(random_bytes(8)) . '.jpg';
        file_put_contents($destDir . $filename, $composited);
        $pins[] = [
            'style' => $styleKey,
            'style_label' => free_tool_template_options()[$styleKey] ?? $styleKey,
            'image_path' => 'uploads/pins/' . $filename,
            'title' => $title,
            'description' => $content['description'] ?? '',
            'alt_text' => $content['alt_text'] ?? $title,
        ];
    }

    if (empty($pins)) {
        return ['ok' => false, 'error' => 'Could not generate any pins from that page — try a different URL.', 'pins' => []];
    }

    return ['ok' => true, 'error' => null, 'page_title' => $title, 'source_url' => $pageUrl, 'pins' => $pins];
}

/** Admin-configurable settings for AI Pinterest Pin Create (/free-tools/ai-pinterest-pin-create). */
function free_tool_pincreate_settings(PDO $pdo): array
{
    $row = get_article_settings($pdo) ?: [];
    $provider = $row['pincreate_image_provider'] ?? ($row['wpin_image_provider'] ?? ($row['image_provider'] ?? 'deepinfra'));
    return [
        'image_provider' => $provider,
        'image_model' => $row['pincreate_image_model'] ?? ($row['wpin_image_model'] ?? 'black-forest-labs/FLUX-1-schnell'),
        'max_pins' => isset($row['pincreate_max_pins']) && $row['pincreate_max_pins'] !== null ? (int)$row['pincreate_max_pins'] : 20,
        'watermark_text' => $row['pincreate_watermark_text'] ?? APP_NAME,
        'watermark_logo_path' => $row['pincreate_watermark_logo_path'] ?? null,
    ];
}

/** Admin-configurable settings for AI Image Creator (/free-tools/ai-image-creater). */
function free_tool_imagecreator_settings(PDO $pdo): array
{
    $row = get_article_settings($pdo) ?: [];
    $provider = $row['imagecreator_image_provider'] ?? ($row['wpin_image_provider'] ?? ($row['image_provider'] ?? 'deepinfra'));
    return [
        'image_provider' => $provider,
        'image_model' => $row['imagecreator_image_model'] ?? ($row['wpin_image_model'] ?? 'black-forest-labs/FLUX-1-schnell'),
        'daily_limit' => isset($row['imagecreator_daily_limit']) && $row['imagecreator_daily_limit'] !== null ? (int)$row['imagecreator_daily_limit'] : 10,
    ];
}

/**
 * AI Pinterest Pin Create: generates ONE pin from a title/keyword (+ optional website,
 * CTA, custom prompt) — an AI background image (DeepInfra or Cloudflare, admin-configured)
 * composited with the SAME engine as Bulk Pin Scheduler, then watermarked. No AI image
 * credits are spent (this is the site's own admin-configured provider, not a user's plan).
 */
function free_tool_generate_single_pin(PDO $pdo, string $title, string $websiteUrl, string $ctaText, string $sizeKey, string $customPrompt, int $categoryId = 0): array
{
    $title = trim($title);
    if ($title === '') {
        return ['ok' => false, 'error' => 'Please enter a blog title or keyword.'];
    }
    $settings = free_tool_pincreate_settings($pdo);

    // Short, unique headline + category-aware photo scene (same step as the Bulk Pin Scheduler).
    $brief = pin_prepare_image_brief($pdo, mb_substr($title, 0, 150), image_category_path($pdo, $categoryId), $customPrompt);
    $title = $brief['headline'];
    // Free tool = one photo, so AI Auto picks among single-photo templates only.
    $singles = array_keys(array_filter(pin_template_registry(), fn($t) => $t['layout'] === 'single'));
    $style = pin_pick_auto_style($title, $singles);
    $promptPair = build_pin_image_prompt($title, $customPrompt, $brief, pin_generation_dims($sizeKey));
    $gen = ai_generate_pin_image_with_retry($pdo, $settings['image_provider'], $settings['image_model'], $promptPair, 3);
    if (!$gen['ok']) {
        return ['ok' => false, 'error' => $gen['error'] ?: 'Could not generate an image right now — please try again.'];
    }

    $website = parse_url(trim($websiteUrl), PHP_URL_HOST) ?: trim($websiteUrl);
    $composited = compose_pin_image([$gen['image_data']], $title, $website, $ctaText, $sizeKey, $style);
    if (!$composited) {
        return ['ok' => false, 'error' => 'Could not compose the pin image.'];
    }

    $destDir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $cleanName = 'pincreate_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $cleanName, $composited);

    $watermarked = free_tool_apply_watermark($composited, $settings);
    $wmName = 'pincreate_wm_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $wmName, $watermarked ?: $composited);

    return [
        'ok' => true, 'error' => null, 'title' => $title,
        'clean_path' => 'uploads/pins/' . $cleanName,
        'watermarked_path' => 'uploads/pins/' . $wmName,
    ];
}

/**
 * Homepage "Create a Pin" hero widget (public, no-login). Same admin-configured
 * provider, attempt limit and watermark as free_tool_generate_single_pin() /
 * AI Pinterest Pin Create above, but exposes the fuller Bulk Pin Scheduler
 * option set: image style, CTA mode, an on/off website line, single vs.
 * collage, and an optional brand color palette. Shares its usage counter
 * with the 'pin_create' tool so a visitor's free pins are counted once
 * across both entry points.
 */
function free_tool_generate_homepage_pin(
    PDO $pdo,
    string $title,
    string $websiteUrl,
    bool $showWebsite,
    string $ctaMode,
    string $ctaText,
    string $sizeKey,
    string $imageStyle,
    string $imageType,
    int $collageCount,
    $colorPalette,
    string $customPrompt = '',
    int $categoryId = 0
): array {
    $title = trim($title);
    if ($title === '') {
        return ['ok' => false, 'error' => 'Please enter a title for the pin.'];
    }
    $settings = free_tool_pincreate_settings($pdo);

    // Short, unique headline + category-aware photo scene (same step as the Bulk Pin Scheduler).
    $brief = pin_prepare_image_brief($pdo, mb_substr($title, 0, 150), image_category_path($pdo, $categoryId), $customPrompt);
    $title = $brief['headline'];

    if ($ctaMode === 'none') {
        $ctaText = '';
    } elseif ($ctaMode === 'auto' || trim($ctaText) === '') {
        $ctaText = auto_pick_cta($title);
    }
    $website = $showWebsite ? (parse_url(trim($websiteUrl), PHP_URL_HOST) ?: trim($websiteUrl)) : '';

    // Single vs collage comes from the template ($imageType/$collageCount are kept only for old callers).
    $imageStyle = pin_resolve_style(pin_sanitize_style_value($imageStyle), $title);
    $gen = pin_generate_template_images($pdo, $imageStyle, $title, $customPrompt, $brief, $sizeKey, (string)$settings['image_provider'], (string)$settings['image_model'], 3);
    if (!$gen['ok']) {
        return ['ok' => false, 'error' => $gen['error'] ?: 'Could not generate an image right now — please try again.'];
    }
    $imageBytesList = $gen['images'];

    $composited = compose_pin_image($imageBytesList, $title, $website, $ctaText, $sizeKey, $imageStyle, $colorPalette);
    if (!$composited) {
        return ['ok' => false, 'error' => 'Could not compose the pin image.'];
    }

    $destDir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $cleanName = 'hpwidget_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $cleanName, $composited);

    $watermarked = free_tool_apply_watermark($composited, $settings);
    $wmName = 'hpwidget_wm_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $wmName, $watermarked ?: $composited);

    return [
        'ok' => true, 'error' => null, 'title' => $title,
        'clean_path' => 'uploads/pins/' . $cleanName,
        'watermarked_path' => 'uploads/pins/' . $wmName,
    ];
}

/** Stamps ONE small watermark (logo if the admin uploaded one, else a text pill) in the top-right corner of a JPEG — kept clear of the title/CTA/website bar, which all live in the bottom half of a pin. */
function free_tool_apply_watermark(string $jpegData, array $settings): ?string
{
    $img = @imagecreatefromstring($jpegData);
    if (!$img) return null;
    $w = imagesx($img);
    $h = imagesy($img);
    $pad = max(10, (int)($w * 0.03));

    if (!empty($settings['watermark_logo_path'])) {
        $logoPath = __DIR__ . '/../' . ltrim($settings['watermark_logo_path'], '/');
        $logo = @imagecreatefromstring((string)@file_get_contents($logoPath));
        if ($logo) {
            // One small logo mark, top-right corner only — not centered, not tiled.
            $logoW = (int)($w * 0.14);
            $logoH = (int)(imagesy($logo) * ($logoW / imagesx($logo)));
            imagealphablending($img, true);
            imagecopyresampled($img, $logo, $w - $logoW - $pad, $pad, 0, 0, $logoW, $logoH, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
    } else {
        $text = $settings['watermark_text'] ?: 'PREVIEW';
        $font = __DIR__ . '/../assets/fonts/Poppins-Bold.ttf';
        if (!is_file($font)) $font = __DIR__ . '/../assets/fonts/Roboto-Bold.ttf'; // fall back to whatever ships
        $size = max(11, (int)($w / 40)); // small, corner-sized — not a full-image banner

        if (is_file($font)) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $textW = $bbox[2] - $bbox[0];
            $textH = $bbox[1] - $bbox[7];
            $padX = 14; $padY = 9;
            $badgeW = $textW + $padX * 2;
            $badgeH = $textH + $padY * 2;
            $bx = $w - $badgeW - $pad;
            $by = $pad; // top-right, clear of the title/CTA/website bar at the bottom
            imagealphablending($img, true);
            $badgeBg = imagecolorallocatealpha($img, 0, 0, 0, 45); // one soft translucent pill, corner only
            imagefilledrectangle($img, $bx, $by, $bx + $badgeW, $by + $badgeH, $badgeBg);
            $white = imagecolorallocatealpha($img, 255, 255, 255, 10);
            imagettftext($img, $size, 0, $bx + $padX, $by + $badgeH - $padY, $white, $font, $text);
        } else {
            $white = imagecolorallocatealpha($img, 255, 255, 255, 10);
            imagestring($img, 4, max(0, $w - 130), $pad, $text, $white);
        }
    }

    ob_start();
    imagejpeg($img, null, 88);
    $out = ob_get_clean();
    imagedestroy($img);
    return $out ?: null;
}

/**
 * AI Image Creator: a plain AI image generation (no pin compositing, no watermark) —
 * admin-configured provider/model (DeepInfra or Cloudflare).
 */
function free_tool_generate_plain_image(PDO $pdo, string $prompt, string $sizeKey): array
{
    $prompt = trim($prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'Please enter a prompt.'];
    }
    $settings = free_tool_imagecreator_settings($pdo);
    $promptPair = ['positive' => $prompt, 'negative' => 'text, watermark, logo, low quality, blurry, distorted'];
    $gen = ai_generate_pin_image_with_retry($pdo, $settings['image_provider'], $settings['image_model'], $promptPair, 3);
    if (!$gen['ok']) {
        return ['ok' => false, 'error' => $gen['error'] ?: 'Could not generate an image right now — please try again.'];
    }

    [$targetW, $targetH] = pin_image_size_dims($sizeKey);
    $src = @imagecreatefromstring($gen['image_data']);
    if (!$src) {
        return ['ok' => false, 'error' => 'Could not process the generated image.'];
    }
    $dst = imagecreatetruecolor($targetW, $targetH);
    pin_image_cover_resize($src, $dst, $targetW, $targetH);
    imagedestroy($src);
    ob_start();
    imagejpeg($dst, null, 90);
    $data = ob_get_clean();
    imagedestroy($dst);

    $destDir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = 'imgcreate_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $filename, $data);

    return ['ok' => true, 'error' => null, 'image_path' => 'uploads/pins/' . $filename];
}

/** Admin-configurable settings shared by the three text-only free tools (hashtag, title/description, bio generators). */
function free_tool_text_settings(PDO $pdo): array
{
    $row = get_article_settings($pdo) ?: [];
    return [
        'text_provider' => $row['freetext_provider'] ?? ($row['freetool_text_provider'] ?? ($row['text_provider'] ?? null)),
        'text_model' => $row['freetext_model'] ?? ($row['freetool_text_model'] ?? ($row['text_model'] ?? null)),
        'max_attempts' => isset($row['freetext_max_attempts']) && $row['freetext_max_attempts'] !== null ? (int)$row['freetext_max_attempts'] : 30,
    ];
}

/** Best-effort fetch of a page's title + meta description, for the "Extract from URL" mode on the hashtag and title/description tools. */
function free_tool_fetch_url_context(string $url): array
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'title' => '', 'description' => '', 'error' => 'Please enter a valid URL.'];
    }
    $html = http_get_text($url, 15);
    if (!$html) {
        return ['ok' => false, 'title' => '', 'description' => '', 'error' => 'Could not fetch that page — it may be blocking automated requests.'];
    }
    $title = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES);
    } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES);
    }
    $description = '';
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $description = html_entity_decode(trim($m[1]), ENT_QUOTES);
    } elseif (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $description = html_entity_decode(trim($m[1]), ENT_QUOTES);
    }
    if ($title === '') {
        return ['ok' => false, 'title' => '', 'description' => '', 'error' => 'Could not find a title on that page.'];
    }
    return ['ok' => true, 'title' => mb_substr($title, 0, 200), 'description' => mb_substr($description, 0, 400), 'error' => null];
}

/**
 * Pinterest Hashtag Generator: returns a set of relevant hashtags for a title/topic —
 * a mix of broad, niche and long-tail tags, capped at Pinterest's realistic 2-20 range.
 */
function free_tool_generate_hashtags(PDO $pdo, string $topic, string $tone, int $count, array $settings): array
{
    $topic = trim($topic);
    if ($topic === '') {
        return ['ok' => false, 'error' => 'Please enter a title, topic, or URL.', 'hashtags' => []];
    }
    $count = max(2, min(20, $count ?: 10));

    if (empty($settings['text_provider'])) {
        // No AI configured — fall back to simple keyword-derived tags so the tool still works.
        $words = preg_split('/[^a-z0-9]+/i', strtolower($topic));
        $words = array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) > 2)));
        $tags = array_map(fn($w) => '#' . $w, array_slice($words, 0, $count));
        return ['ok' => true, 'error' => null, 'hashtags' => $tags];
    }

    $systemPrompt = 'You are a Pinterest SEO expert. Respond with ONLY a JSON array of strings, no markdown, no commentary. '
        . "Generate exactly $count Pinterest hashtags (each starting with #, no spaces, lowercase, no punctuation besides letters/numbers/underscore) "
        . "for the given topic, in a $tone tone. Mix broad/evergreen tags, niche-specific tags, and a couple of long-tail tags. "
        . 'Keep the combined set realistic for Pinterest (short, search-like terms, not sentences).';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Topic: $topic", 500);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'hashtags' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'hashtags' => []];
    }
    $tags = array_values(array_filter(array_map(function ($t) {
        $t = trim((string)$t);
        if ($t === '') return null;
        if ($t[0] !== '#') $t = '#' . $t;
        return preg_replace('/[^#a-z0-9_]/i', '', $t);
    }, $json)));

    return ['ok' => true, 'error' => null, 'hashtags' => array_slice($tags, 0, $count)];
}

/**
 * Pinterest Title & Description Generator: one catchy title (<=100 chars) and one
 * keyword-rich description (<=500 chars, ~150-250 char sweet spot), from a topic or URL.
 */
function free_tool_generate_title_description(PDO $pdo, string $topic, string $tone, string $audience, array $settings): array
{
    $topic = trim($topic);
    if ($topic === '') {
        return ['ok' => false, 'error' => 'Please enter a topic or URL.', 'title' => '', 'description' => ''];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'title' => '', 'description' => ''];
    }

    $audienceLine = trim($audience) !== '' ? "Target audience: $audience." : '';
    $systemPrompt = 'You are a Pinterest SEO copywriter. Respond with ONLY a JSON object, no markdown, no commentary. '
        . 'Shape: {"title": "...", "description": "..."}. '
        . "Title: STRICTLY under 100 characters, front-load the main keyword in the first 40 characters (Pinterest truncates titles there), $tone tone. "
        . 'Description: STRICTLY under 500 characters, ideally 150-250 characters, natural sentences (not a keyword list), '
        . "include relevant keywords, end with a short call-to-action, $tone tone. $audienceLine";
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Topic: $topic", 600);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'title' => '', 'description' => ''];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json) || empty($json['title'])) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'title' => '', 'description' => ''];
    }
    return [
        'ok' => true, 'error' => null,
        'title' => mb_substr((string)$json['title'], 0, 100),
        'description' => mb_substr((string)($json['description'] ?? ''), 0, 500),
    ];
}

/** Pinterest Bio Generator: one profile bio, strictly under Pinterest's 160-character "About" limit. */
function free_tool_generate_bio(PDO $pdo, string $niche, string $tone, string $language, array $settings): array
{
    $niche = trim($niche);
    if ($niche === '') {
        return ['ok' => false, 'error' => 'Please enter your account topic or niche.', 'bio' => ''];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'bio' => ''];
    }

    $systemPrompt = 'You are a Pinterest branding copywriter. Respond with ONLY a JSON object, no markdown, no commentary. '
        . 'Shape: {"bio": "..."}. '
        . "Write ONE Pinterest profile bio in $language, STRICTLY under 160 characters (Pinterest's hard limit for the About field), "
        . "in a $tone tone. Include 1-2 natural keywords for the niche, say who you help and what you post about, "
        . 'optionally end with a light call-to-action. No hashtags, no line breaks, no quotation marks.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Niche: $niche", 300);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'bio' => ''];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json) || empty($json['bio'])) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'bio' => ''];
    }
    return ['ok' => true, 'error' => null, 'bio' => mb_substr(trim((string)$json['bio']), 0, 160)];
}

/**
 * Pinterest Board Name Generator: a short list of catchy, on-brand board name ideas
 * for a given topic (Pinterest board names are capped at 50 characters).
 */
function free_tool_generate_board_names(PDO $pdo, string $topic, string $tone, string $language, array $settings): array
{
    $topic = trim($topic);
    if ($topic === '') {
        return ['ok' => false, 'error' => 'Please enter a board topic or theme.', 'names' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'names' => []];
    }
    $systemPrompt = 'You are a Pinterest branding expert. Respond with ONLY a JSON array of strings, no markdown, no commentary. '
        . "Generate exactly 8 catchy Pinterest board names for the given topic, in $language, in a $tone tone. "
        . 'Each name STRICTLY under 50 characters (Pinterest\'s board name limit). Make them varied: some punchy and short, '
        . 'some descriptive, some keyword-forward for search. No quotation marks, no numbering, no hashtags.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Board topic: $topic", 500);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'names' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'names' => []];
    }
    $names = array_values(array_filter(array_map(fn($n) => mb_substr(trim((string)$n), 0, 50), $json)));
    return ['ok' => true, 'error' => null, 'names' => array_slice($names, 0, 8)];
}

/**
 * Pinterest Username Generator: unique, SEO-friendly username ideas (Pinterest usernames
 * are 3-15 characters, letters/numbers/underscores only).
 */
function free_tool_generate_usernames(PDO $pdo, string $niche, string $style, array $settings): array
{
    $niche = trim($niche);
    if ($niche === '') {
        return ['ok' => false, 'error' => 'Please enter your account topic or niche.', 'usernames' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'usernames' => []];
    }
    $systemPrompt = 'You are a branding expert. Respond with ONLY a JSON array of strings, no markdown, no commentary. '
        . "Generate exactly 10 Pinterest username ideas for the given niche, in a $style style. "
        . 'Each username STRICTLY 3-15 characters (Pinterest\'s hard limit), using ONLY lowercase letters, numbers, and underscores '
        . '— no spaces, no other symbols. Make them unique and memorable, not generic.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Niche: $niche", 400);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'usernames' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'usernames' => []];
    }
    $names = array_values(array_filter(array_map(function ($n) {
        $n = preg_replace('/[^a-z0-9_]/i', '', strtolower(trim((string)$n)));
        return ($n !== '' && mb_strlen($n) >= 3 && mb_strlen($n) <= 15) ? $n : null;
    }, $json)));
    return ['ok' => true, 'error' => null, 'usernames' => array_slice($names, 0, 10)];
}

/**
 * Calls a vision-capable chat model with an image + prompt. Mirrors ai_generate_text_raw()'s
 * provider handling, extended with each provider's own image-content format. The admin-chosen
 * text model for the Free Tools text generators must itself support image input (e.g. gpt-4o,
 * a vision-capable Claude or Gemini model) for this to succeed — a non-vision model will error.
 */
function ai_generate_vision_text(PDO $pdo, string $provider, string $model, string $systemPrompt, string $userPrompt, string $imageBase64, string $mimeType, int $maxTokens = 400): array
{
    $row = get_ai_provider_row($pdo, $provider, 'text');
    if (!$row || empty($row['api_key'])) {
        return ['ok' => false, 'text' => '', 'error' => "No API key configured for text provider '$provider'."];
    }
    $apiKey = $row['api_key'];

    switch ($provider) {
        case 'chatgpt':
        case 'openrouter':
        case 'deepinfra':
            $url = [
                'chatgpt' => 'https://api.openai.com/v1/chat/completions',
                'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
                'deepinfra' => 'https://api.deepinfra.com/v1/openai/chat/completions',
            ][$provider];
            $headers = ["Authorization: Bearer $apiKey"];
            if ($provider === 'openrouter') { $headers[] = 'HTTP-Referer: ' . APP_URL; $headers[] = 'X-Title: ' . APP_NAME; }
            $result = ai_http_post($url, $headers, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $userPrompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageBase64"]],
                    ]],
                ],
                'max_tokens' => $maxTokens,
            ]);
            if ($result['ok']) {
                return ['ok' => true, 'text' => $result['data']['choices'][0]['message']['content'] ?? '', 'error' => null];
            }
            break;

        case 'claude':
            $result = ai_http_post('https://api.anthropic.com/v1/messages',
                ["x-api-key: $apiKey", "anthropic-version: 2023-06-01"],
                ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $systemPrompt, 'messages' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $imageBase64]],
                        ['type' => 'text', 'text' => $userPrompt],
                    ]],
                ]]);
            if ($result['ok']) {
                $text = '';
                foreach (($result['data']['content'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'text') $text .= $block['text'];
                }
                return ['ok' => true, 'text' => $text, 'error' => null];
            }
            break;

        case 'google':
            $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$apiKey";
            $result = ai_http_post($url, [], [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['parts' => [
                    ['text' => $userPrompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
                ]]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
            ]);
            if ($result['ok']) {
                $text = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
                return ['ok' => true, 'text' => $text, 'error' => null];
            }
            break;

        default:
            return ['ok' => false, 'text' => '', 'error' => "Unknown text provider '$provider'."];
    }

    return ['ok' => false, 'text' => '', 'error' => is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'Unknown error')];
}

/** Pinterest Alt Text Generator: SEO-friendly, descriptive alt text for an uploaded image. */
function free_tool_generate_alt_text(PDO $pdo, string $imageBinary, string $mimeType, array $settings): array
{
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs a vision-capable AI model configured by the site admin. Please try again later.', 'alt_text' => ''];
    }
    $base64 = base64_encode($imageBinary);
    $systemPrompt = 'You write SEO-optimized, accessible alt text for Pinterest images. Respond with ONLY the alt text itself, '
        . 'no quotation marks, no "Alt text:" prefix, no commentary. Describe what is actually visible in the image — subject, '
        . 'setting, action, notable colors or style — in one natural sentence, STRICTLY under 125 characters (the accessibility '
        . 'best-practice length for alt text), including a relevant keyword where it fits naturally without keyword-stuffing.';
    $result = ai_generate_vision_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, 'Describe this image for Pinterest alt text.', $base64, $mimeType, 200);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'alt_text' => ''];
    }
    $alt = trim($result['text'], " \t\n\r\0\x0B\"'");
    if ($alt === '') {
        return ['ok' => false, 'error' => 'Could not generate alt text for that image.', 'alt_text' => ''];
    }
    return ['ok' => true, 'error' => null, 'alt_text' => mb_substr($alt, 0, 125)];
}

/**
 * Pinterest Keyword Research Tool: AI-suggested related keywords with a qualitative
 * relevance/interest estimate. Pinterest doesn't expose a public search-volume API, so
 * this returns relative, AI-estimated signals for content planning — not exact traffic
 * numbers — and the UI labels them that way rather than presenting invented statistics as fact.
 */
function free_tool_generate_keywords(PDO $pdo, string $topic, array $settings): array
{
    $topic = trim($topic);
    if ($topic === '') {
        return ['ok' => false, 'error' => 'Please enter a keyword or URL.', 'keywords' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'keywords' => []];
    }
    $systemPrompt = 'You are a Pinterest SEO strategist. Respond with ONLY a JSON array, no markdown, no commentary. '
        . 'Shape: [{"keyword": "...", "interest": "High"|"Medium"|"Low", "trend": "Rising"|"Steady"|"Seasonal"}, ...] — '
        . 'exactly 15 objects. Generate related, realistic Pinterest search phrases for the given topic (a mix of broad, '
        . 'niche, and long-tail phrasing, the way real Pinterest users search — not single keywords, actual search-like phrases). '
        . '"interest" and "trend" are your best qualitative estimate based on general knowledge of the niche, not exact data.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Topic: $topic", 1200);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'keywords' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'keywords' => []];
    }
    $keywords = [];
    foreach ($json as $row) {
        if (empty($row['keyword'])) continue;
        $keywords[] = [
            'keyword' => mb_substr(trim((string)$row['keyword']), 0, 100),
            'interest' => in_array($row['interest'] ?? '', ['High', 'Medium', 'Low'], true) ? $row['interest'] : 'Medium',
            'trend' => in_array($row['trend'] ?? '', ['Rising', 'Steady', 'Seasonal'], true) ? $row['trend'] : 'Steady',
        ];
    }
    return ['ok' => true, 'error' => null, 'keywords' => array_slice($keywords, 0, 15)];
}

/** Admin-configurable settings shared by the Etsy free tools. */
function free_tool_etsy_settings(PDO $pdo): array
{
    $row = get_article_settings($pdo) ?: [];
    return [
        'text_provider' => $row['etsy_text_provider'] ?? ($row['freetext_provider'] ?? null),
        'text_model' => $row['etsy_text_model'] ?? ($row['freetext_model'] ?? null),
        'max_attempts' => isset($row['etsy_max_attempts']) && $row['etsy_max_attempts'] !== null ? (int)$row['etsy_max_attempts'] : 20,
    ];
}

/**
 * Etsy Keyword Tool: related keyword phrases with a qualitative search-volume,
 * competition, and opportunity estimate. Etsy doesn't expose a public search-volume
 * API, so — same as the Pinterest keyword tool — these are AI-estimated relative
 * signals for planning, not measured Etsy data, and are labeled that way.
 */
function free_tool_generate_etsy_keywords(PDO $pdo, string $product, string $audience, string $season, array $settings): array
{
    $product = trim($product);
    if ($product === '') {
        return ['ok' => false, 'error' => 'Please describe your Etsy product.', 'keywords' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'keywords' => []];
    }
    $context = "Product: $product.";
    if (trim($audience) !== '') $context .= " Ideal customer: $audience.";
    if (trim($season) !== '') $context .= " Season/occasion: $season.";

    $systemPrompt = 'You are an Etsy SEO strategist. Respond with ONLY a JSON array, no markdown, no commentary. '
        . 'Shape: [{"keyword": "...", "volume": "High"|"Medium"|"Low", "competition": "High"|"Medium"|"Low", "opportunity": 0-100}, ...] — '
        . 'exactly 15 objects. Generate realistic Etsy buyer search phrases (the way real Etsy shoppers search — specific, '
        . 'intent-driven phrases, not single generic words) for the given product. "volume", "competition" and "opportunity" '
        . 'are your best qualitative estimate based on general knowledge of the niche, not exact data. Opportunity balances '
        . 'volume against competition (higher = more favorable).';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, $context, 1200);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'keywords' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'keywords' => []];
    }
    $keywords = [];
    foreach ($json as $row) {
        if (empty($row['keyword'])) continue;
        $keywords[] = [
            'keyword' => mb_substr(trim((string)$row['keyword']), 0, 100),
            'volume' => in_array($row['volume'] ?? '', ['High', 'Medium', 'Low'], true) ? $row['volume'] : 'Medium',
            'competition' => in_array($row['competition'] ?? '', ['High', 'Medium', 'Low'], true) ? $row['competition'] : 'Medium',
            'opportunity' => max(0, min(100, (int)($row['opportunity'] ?? 50))),
        ];
    }
    return ['ok' => true, 'error' => null, 'keywords' => array_slice($keywords, 0, 15)];
}

/** Etsy Shop Bio Generator: a 2-3 paragraph shop bio from a free-text shop description + tone. */
function free_tool_generate_etsy_bio(PDO $pdo, string $shopInfo, string $tone, array $settings): array
{
    $shopInfo = trim($shopInfo);
    if ($shopInfo === '') {
        return ['ok' => false, 'error' => 'Please tell us about your shop.', 'bio' => ''];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'bio' => ''];
    }
    $systemPrompt = 'You are an Etsy branding copywriter. Respond with ONLY a JSON object, no markdown, no commentary. '
        . 'Shape: {"bio": "..."}. '
        . "Write an Etsy shop \"About\" bio in a $tone tone, 2-3 short paragraphs (roughly 400-800 characters total). "
        . 'Tell the shop\'s story, what makes it unique, the creative process or materials if mentioned, and the shop\'s '
        . 'values — using ONLY details actually given, never inventing specifics (materials, awards, years in business) '
        . 'that were not provided. Natural, human, not keyword-stuffed. No markdown formatting, no headers.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Shop info: $shopInfo", 900);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'bio' => ''];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json) || empty($json['bio'])) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'bio' => ''];
    }
    return ['ok' => true, 'error' => null, 'bio' => trim((string)$json['bio'])];
}

/** Etsy Shop Announcement Generator: 3 tone variants of a shop announcement, each under Etsy's 500-char limit. */
function free_tool_generate_etsy_announcement(PDO $pdo, string $shopName, string $whatYouSell, string $announcement, array $settings): array
{
    $shopName = trim($shopName);
    $whatYouSell = trim($whatYouSell);
    $announcement = trim($announcement);
    if ($shopName === '' || $whatYouSell === '' || $announcement === '') {
        return ['ok' => false, 'error' => 'Please fill in your shop name, what you sell, and what you\'re announcing.', 'variants' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'variants' => []];
    }
    $systemPrompt = 'You are an Etsy shop copywriter. Respond with ONLY a JSON array of 3 strings, no markdown, no commentary. '
        . 'Each string is a shop announcement banner (the text at the top of an Etsy shop page), STRICTLY under 500 characters '
        . '(Etsy\'s hard limit). Write 3 DIFFERENT tone variants: one friendly/warm, one excited/energetic, one professional/polished. '
        . 'Use only the details given — never invent products, dates, or promotions not mentioned. Natural sentences, no hashtags.';
    $userPrompt = "Shop name: $shopName\nWhat they sell: $whatYouSell\nAnnouncing: $announcement";
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, $userPrompt, 900);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'variants' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'variants' => []];
    }
    $variants = array_values(array_filter(array_map(fn($v) => mb_substr(trim((string)$v), 0, 500), $json)));
    return ['ok' => true, 'error' => null, 'variants' => array_slice($variants, 0, 3)];
}

/** Etsy Store Name Generator: 8 shop name ideas for a product category (kept short — Etsy shop names read best under ~20 characters). */
function free_tool_generate_etsy_shop_names(PDO $pdo, string $category, array $settings): array
{
    $category = trim($category);
    if ($category === '') {
        return ['ok' => false, 'error' => 'Please tell us what your store will sell.', 'names' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'names' => []];
    }
    $systemPrompt = 'You are a branding expert for Etsy sellers. Respond with ONLY a JSON array of strings, no markdown, no commentary. '
        . 'Generate exactly 8 Etsy shop name ideas for the given product category. Each name should ideally be under 20 characters '
        . '(short names read best on Etsy), use only letters and numbers (Etsy shop names cannot contain spaces or most symbols — '
        . 'write them as a single run-together or lightly-capitalized word, e.g. "FernAndClay" not "Fern And Clay"), memorable, '
        . 'and relevant to the category. No quotation marks, no numbering.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Product category: $category", 500);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'names' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'names' => []];
    }
    $names = array_values(array_filter(array_map(function ($n) {
        $n = preg_replace('/[^a-zA-Z0-9]/', '', trim((string)$n));
        return $n !== '' ? mb_substr($n, 0, 30) : null;
    }, $json)));
    return ['ok' => true, 'error' => null, 'names' => array_slice($names, 0, 8)];
}

/** Etsy Tags Generator: 30 candidate tags (Etsy allows 13 per listing, each up to 20 characters). */
function free_tool_generate_etsy_tags(PDO $pdo, string $product, array $settings): array
{
    $product = trim($product);
    if ($product === '') {
        return ['ok' => false, 'error' => 'Please describe your product.', 'tags' => []];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'tags' => []];
    }
    $systemPrompt = 'You are an Etsy SEO expert. Respond with ONLY a JSON array of strings, no markdown, no commentary. '
        . 'Generate exactly 30 Etsy listing tags for the given product. Each tag STRICTLY under 20 characters (Etsy\'s hard '
        . 'limit per tag). Mix single words and multi-word phrases, broad and long-tail, matching how real Etsy buyers search. '
        . 'No hashtags, no duplicate tags.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Product: $product", 700);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'tags' => []];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'tags' => []];
    }
    $tags = array_values(array_unique(array_filter(array_map(fn($t) => mb_substr(trim((string)$t), 0, 20), $json))));
    return ['ok' => true, 'error' => null, 'tags' => array_slice($tags, 0, 30)];
}

/** Etsy Title & Description Generator: a title (<=140 chars) and a description, from product info + target keywords. */
function free_tool_generate_etsy_title_description(PDO $pdo, string $product, string $keywords, string $details, array $settings): array
{
    $product = trim($product);
    if ($product === '') {
        return ['ok' => false, 'error' => 'Please describe what you\'re selling.', 'title' => '', 'description' => ''];
    }
    if (empty($settings['text_provider'])) {
        return ['ok' => false, 'error' => 'This tool needs an AI model configured by the site admin. Please try again later.', 'title' => '', 'description' => ''];
    }
    $context = "Product: $product.";
    if (trim($keywords) !== '') $context .= " Target keywords: $keywords.";
    if (trim($details) !== '') $context .= " Details (materials/size/colors): $details.";

    $systemPrompt = 'You are an Etsy listing copywriter. Respond with ONLY a JSON object, no markdown, no commentary. '
        . 'Shape: {"title": "...", "description": "..."}. '
        . 'Title: STRICTLY under 140 characters (Etsy\'s hard limit), front-load the main keyword, natural — not a comma-stuffed '
        . 'keyword list. Description: 3-4 short paragraphs, keyword in the first sentence, include materials/size/details if '
        . 'given, end with a light call-to-action. Use only details actually provided — never invent specifics.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, $context, 1000);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'title' => '', 'description' => ''];
    }
    $json = extract_json_from_text($result['text']);
    if (!is_array($json) || empty($json['title'])) {
        return ['ok' => false, 'error' => 'Could not parse the AI response.', 'title' => '', 'description' => ''];
    }
    return [
        'ok' => true, 'error' => null,
        'title' => mb_substr((string)$json['title'], 0, 140),
        'description' => trim((string)($json['description'] ?? '')),
    ];
}

function free_tool_ai_content(PDO $pdo, string $pageTitle, array $settings): array
{
    if (empty($settings['text_provider'])) {
        return ['title' => $pageTitle, 'description' => '', 'alt_text' => $pageTitle];
    }
    $systemPrompt = 'You are an expert Pinterest marketer. Respond with ONLY a JSON object, no markdown fences, no commentary. '
        . 'Shape: {"title": "...", "description": "...", "alt_text": "..."}. '
        . 'Title: catchy, keyword-rich, STRICTLY under 100 characters. Description: 2-3 sentences, STRICTLY under 500 characters. '
        . 'alt_text: descriptive, STRICTLY under 500 characters.';
    $result = ai_generate_text($pdo, $settings['text_provider'], $settings['text_model'] ?? '', $systemPrompt, "Page title/topic: $pageTitle", 800);
    if (!$result['ok']) {
        return ['title' => $pageTitle, 'description' => '', 'alt_text' => $pageTitle];
    }
    $json = extract_json_from_text($result['text']);
    if (!$json || !is_array($json) || empty($json['title'])) {
        return ['title' => $pageTitle, 'description' => '', 'alt_text' => $pageTitle];
    }
    return [
        'title' => mb_substr((string)$json['title'], 0, 100),
        'description' => mb_substr((string)($json['description'] ?? ''), 0, 500),
        'alt_text' => mb_substr((string)($json['alt_text'] ?? $json['title']), 0, 500),
    ];
}
