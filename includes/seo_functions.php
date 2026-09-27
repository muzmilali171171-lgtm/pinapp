<?php
/**
 * SEO module — site-wide meta tags, favicon/logo, indexing control and
 * JSON-LD schema markup (SoftwareApplication/WebApplication + Review),
 * all editable from Admin → SEO Setting.
 *
 * Everything reads from three tables (seo_settings, seo_offers, seo_reviews)
 * and falls back to sensible defaults if the SEO tables have not been created
 * yet — so an old install that hasn't run migrate.php still renders fine.
 */

/** Cached single-row settings fetch. */
function get_seo_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $defaults = [
        'meta_title' => defined('APP_NAME') ? APP_NAME : 'Pin Scheduler',
        'meta_description' => '',
        'meta_keywords' => '',
        'favicon_path' => '',
        'logo_path' => '',
        'og_image_path' => '',
        'canonical_url' => '',
        'robots_index' => 0,          // default OFF, as requested
        'robots_follow' => 1,
        'robots_txt' => '',
        'extra_head_code' => '',
        'schema_enabled' => 1,
        'app_type' => 'WebApplication',
        'app_name' => defined('APP_NAME') ? APP_NAME : '',
        'app_url' => defined('APP_URL') ? rtrim(APP_URL, '/') . '/' : '',
        'app_category' => 'BusinessApplication',
        'app_operating_system' => 'Any',
        'app_browser_requirements' => 'Requires HTML5 support and JavaScript',
        'app_description' => '',
        'app_screenshot' => '',
        'rating_enabled' => 1,
        'rating_value' => '4.6',
        'rating_count' => 0,
        'rating_best' => '5',
        'rating_worst' => '1',
        'publisher_enabled' => 1,
        'publisher_name' => '',
        'publisher_url' => '',
        'publisher_logo_url' => '',
        'publisher_logo_width' => 512,
        'publisher_logo_height' => 512,
        'offers_enabled' => 1,
        'offers_currency' => 'USD',
        'reviews_enabled' => 1,
    ];

    try {
        $row = $pdo->query("SELECT * FROM seo_settings ORDER BY id ASC LIMIT 1")->fetch();
    } catch (Throwable $e) {
        $row = null;
    }

    $cache = $row ? array_merge($defaults, array_filter($row, static fn($v) => $v !== null)) : $defaults;
    return $cache;
}

function seo_offers(PDO $pdo, bool $activeOnly = true): array
{
    try {
        $sql = "SELECT * FROM seo_offers" . ($activeOnly ? " WHERE status = 'active'" : "") . " ORDER BY sort_order ASC, id ASC";
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function seo_reviews(PDO $pdo, bool $activeOnly = true): array
{
    try {
        $sql = "SELECT * FROM seo_reviews" . ($activeOnly ? " WHERE status = 'active'" : "") . " ORDER BY sort_order ASC, id ASC";
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Turn a stored path ("uploads/seo/x.png") into a full URL; leaves absolute URLs alone. */
function seo_asset_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    return $base . '/' . ltrim($path, '/');
}

/**
 * Handle a favicon/logo/og-image upload from the admin form.
 * Returns the stored relative path, or null when nothing was uploaded.
 */
function seo_handle_upload(string $field, array &$error = null): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error[] = "Upload failed for $field.";
        return null;
    }

    $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        $error[] = "Unsupported file type for $field (use PNG, JPG, WEBP, GIF, SVG or ICO).";
        return null;
    }
    if ($_FILES[$field]['size'] > 3 * 1024 * 1024) {
        $error[] = "File for $field is too large (max 3MB).";
        return null;
    }

    $dir = __DIR__ . '/../uploads/seo';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $name = $field . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        $error[] = "Could not save the uploaded file for $field.";
        return null;
    }
    return 'uploads/seo/' . $name;
}

/**
 * Build the SoftwareApplication / WebApplication JSON-LD node.
 * AggregateOffer low/high price and offerCount are computed from the offer rows.
 */
function seo_software_schema(PDO $pdo): ?array
{
    $s = get_seo_settings($pdo);
    if (empty($s['schema_enabled']) || trim((string)$s['app_name']) === '') return null;

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $s['app_type'] ?: 'WebApplication',
        'name' => $s['app_name'],
    ];
    if (!empty($s['app_url'])) $schema['url'] = $s['app_url'];
    if (!empty($s['app_category'])) $schema['applicationCategory'] = $s['app_category'];
    if (!empty($s['app_operating_system'])) $schema['operatingSystem'] = $s['app_operating_system'];
    if (!empty($s['app_browser_requirements'])) $schema['browserRequirements'] = $s['app_browser_requirements'];
    if (!empty($s['app_description'])) $schema['description'] = $s['app_description'];

    if (!empty($s['rating_enabled']) && (float)$s['rating_value'] > 0 && (int)$s['rating_count'] > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (float)$s['rating_value'],
            'ratingCount' => (int)$s['rating_count'],
            'bestRating' => (float)$s['rating_best'],
            'worstRating' => (float)$s['rating_worst'],
        ];
    }

    if (!empty($s['app_screenshot'])) {
        $schema['screenshot'] = seo_asset_url($s['app_screenshot']);
    }

    if (!empty($s['publisher_enabled']) && trim((string)$s['publisher_name']) !== '') {
        $publisher = ['@type' => 'Organization', 'name' => $s['publisher_name']];
        if (!empty($s['publisher_url'])) $publisher['url'] = $s['publisher_url'];
        if (!empty($s['publisher_logo_url'])) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => seo_asset_url($s['publisher_logo_url']),
                'width' => (int)$s['publisher_logo_width'],
                'height' => (int)$s['publisher_logo_height'],
            ];
        }
        $schema['publisher'] = $publisher;
    }

    if (!empty($s['offers_enabled'])) {
        $offers = seo_offers($pdo);
        if ($offers) {
            $prices = array_map(static fn($o) => (float)$o['price'], $offers);
            $list = [];
            foreach ($offers as $o) {
                $offer = [
                    '@type' => 'Offer',
                    'availability' => $o['availability'] ?: 'https://schema.org/InStock',
                ];
                if (!empty($o['price_valid_until'])) $offer['priceValidUntil'] = $o['price_valid_until'];
                $spec = [
                    '@type' => 'UnitPriceSpecification',
                    'price' => (float)$o['price'],
                    'priceCurrency' => $o['price_currency'] ?: $s['offers_currency'],
                    'name' => $o['name'],
                ];
                if (!empty($o['url'])) $spec['url'] = $o['url'];
                if ((int)$o['quantity_value'] > 0) {
                    $spec['referenceQuantity'] = [
                        '@type' => 'QuantitativeValue',
                        'value' => (int)$o['quantity_value'],
                        'unitCode' => $o['unit_code'] ?: 'MON',
                    ];
                }
                $offer['priceSpecification'] = $spec;
                if (!empty($o['description'])) $offer['description'] = $o['description'];
                $list[] = $offer;
            }
            $schema['offers'] = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => $s['offers_currency'] ?: 'USD',
                'highPrice' => max($prices),
                'lowPrice' => min($prices),
                'offerCount' => count($list),
                'offers' => $list,
            ];
        }
    }

    return $schema;
}

/** Build one JSON-LD Review node per active review row. */
function seo_review_schemas(PDO $pdo): array
{
    $s = get_seo_settings($pdo);
    if (empty($s['schema_enabled']) || empty($s['reviews_enabled'])) return [];

    $out = [];
    foreach (seo_reviews($pdo) as $r) {
        if (trim((string)$r['author_name']) === '' || trim((string)$r['review_body']) === '') continue;
        $item = [
            '@type' => $s['app_type'] ?: 'WebApplication',
            'name' => $r['item_name'] ?: $s['app_name'],
        ];
        $url = $r['item_url'] ?: $s['app_url'];
        if ($url) $item['url'] = $url;
        if (!empty($r['item_operating_system'])) $item['operatingSystem'] = $r['item_operating_system'];
        if (!empty($r['item_application_category'])) $item['applicationCategory'] = $r['item_application_category'];

        $out[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'itemReviewed' => $item,
            'author' => ['@type' => $r['author_type'] ?: 'Person', 'name' => $r['author_name']],
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => (float)$r['rating_value'],
                'bestRating' => (float)$r['best_rating'],
                'worstRating' => (float)$r['worst_rating'],
            ],
            'reviewBody' => $r['review_body'],
        ];
    }
    return $out;
}

/** Echo every JSON-LD block for the page. */
function seo_render_schema(PDO $pdo): void
{
    $nodes = [];
    $app = seo_software_schema($pdo);
    if ($app) $nodes[] = $app;
    foreach (seo_review_schemas($pdo) as $review) $nodes[] = $review;

    foreach ($nodes as $node) {
        echo "\n<script type=\"application/ld+json\">"
            . json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "</script>";
    }
}

/**
 * Echo the full <head> SEO block: title, description, keywords, robots,
 * canonical, favicon, Open Graph / Twitter cards and (optionally) schema.
 *
 * $opts keys: title, description, keywords, canonical, noindex (bool),
 *             image, schema (bool — default true).
 */
function seo_render_head(PDO $pdo, array $opts = []): void
{
    $s = get_seo_settings($pdo);

    $title = trim((string)($opts['title'] ?? $s['meta_title'])) ?: (defined('APP_NAME') ? APP_NAME : '');
    $desc = trim((string)($opts['description'] ?? $s['meta_description']));
    $keywords = trim((string)($opts['keywords'] ?? $s['meta_keywords']));
    $canonical = trim((string)($opts['canonical'] ?? $s['canonical_url']));
    $image = seo_asset_url($opts['image'] ?? ($s['og_image_path'] ?: $s['logo_path']));

    // Indexing: page-level noindex always wins; otherwise the admin toggle decides.
    $noindex = !empty($opts['noindex']) || empty($s['robots_index']);
    $robots = ($noindex ? 'noindex' : 'index') . ',' . (empty($s['robots_follow']) ? 'nofollow' : 'follow');

    echo '<title>' . e($title) . "</title>\n";
    if ($desc !== '')     echo '<meta name="description" content="' . e($desc) . "\">\n";
    if ($keywords !== '') echo '<meta name="keywords" content="' . e($keywords) . "\">\n";
    echo '<meta name="robots" content="' . e($robots) . "\">\n";
    echo '<meta name="googlebot" content="' . e($robots) . "\">\n";
    if ($canonical !== '') echo '<link rel="canonical" href="' . e($canonical) . "\">\n";

    $favicon = seo_asset_url($s['favicon_path']);
    if ($favicon !== '') {
        $ext = strtolower(pathinfo(parse_url($favicon, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $type = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml',
                 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/png';
        echo '<link rel="icon" type="' . e($type) . '" href="' . e($favicon) . "\">\n";
        echo '<link rel="apple-touch-icon" href="' . e($favicon) . "\">\n";
    }

    // Open Graph / Twitter
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . e($title) . "\">\n";
    if ($desc !== '') echo '<meta property="og:description" content="' . e($desc) . "\">\n";
    if ($canonical !== '') echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    if ($image !== '') echo '<meta property="og:image" content="' . e($image) . "\">\n";
    echo '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
    echo '<meta name="twitter:title" content="' . e($title) . "\">\n";
    if ($desc !== '') echo '<meta name="twitter:description" content="' . e($desc) . "\">\n";
    if ($image !== '') echo '<meta name="twitter:image" content="' . e($image) . "\">\n";

    if (($opts['schema'] ?? true) && !$noindex) {
        seo_render_schema($pdo);
    }

    if (!empty($s['extra_head_code'])) {
        echo "\n" . $s['extra_head_code'] . "\n";
    }
}

/** The body of robots.txt, served by /robots.php (and written to /robots.txt on save). */
function seo_robots_txt(PDO $pdo): string
{
    $s = get_seo_settings($pdo);
    if (trim((string)$s['robots_txt']) !== '') {
        return trim((string)$s['robots_txt']) . "\n";
    }

    if (empty($s['robots_index'])) {
        return "User-agent: *\nDisallow: /\n";
    }

    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $txt  = "User-agent: *\n";
    $txt .= "Disallow: /admin/\n";
    $txt .= "Disallow: /user/\n";
    $txt .= "Disallow: /auth/\n";
    $txt .= "Disallow: /config/\n";
    $txt .= "Disallow: /oauth/\n";
    $txt .= "Disallow: /cron/\n";
    $txt .= "Allow: /\n";
    if ($base !== '') $txt .= "\nSitemap: $base/sitemap.xml\n";
    return $txt;
}

/** Write the current robots.txt to disk so search engines get a static file. */
function seo_write_robots_file(PDO $pdo): bool
{
    $path = __DIR__ . '/../robots.txt';
    return @file_put_contents($path, seo_robots_txt($pdo)) !== false;
}
