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
    seo_go_live_once($pdo);

    $defaults = [
        'meta_title' => defined('SITE_BRAND') ? SITE_BRAND : 'Pin Scheduler',
        'meta_description' => '',
        'meta_keywords' => '',
        'favicon_path' => '',
        'logo_path' => '',
        'og_image_path' => '',
        'canonical_url' => '',
        'robots_index' => 1,          // the site is live: search engines may index it (Admin → SEO Setting can switch it off)
        'robots_follow' => 1,
        'robots_txt' => '',
        'extra_head_code' => '',
        'schema_enabled' => 1,
        'app_type' => 'WebApplication',
        'app_name' => defined('SITE_BRAND') ? SITE_BRAND : '',
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

/* ===================== Site-wide SEO defaults ===================== */

const SEO_HOME_TITLE = 'AI Pinterest Automation & Pin Scheduler | AutomatedPin';
const SEO_HOME_DESCRIPTION = 'Automate Pinterest with AI: create pins for hundreds of pages in 1 click, schedule them, auto-write blog posts and design free with unlimited templates.';
const SEO_HOME_KEYWORDS = 'Pinterest automation tool, AI Pinterest pin scheduler, auto pin to Pinterest, Pinterest pin maker, Canva alternative for Pinterest, free Pinterest pin templates, AI auto blog, bulk pin scheduler, Pinterest analytics';
const SEO_DEFAULT_IMAGE = 'https://media.webtopin.com/cdn/uploads/acc058d92f65e7730cdf1dbfd1973c5f.webp';

/**
 * One-time "go live" for the new domain (flag file): switches indexing on, sets the optimized home
 * meta, clears a site-wide canonical / custom robots.txt that could hide pages, renames the old
 * brand in blog posts, and rewrites the static robots.txt. Admin → SEO Setting still controls it after.
 */
function seo_go_live_once(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.seo_live_v1';
    if (is_file($flag)) return;
    try {
        $id = $pdo->query("SELECT id FROM seo_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE seo_settings SET robots_index = 1, robots_follow = 1, robots_txt = NULL, canonical_url = NULL,
                meta_title = ?, meta_description = ?, meta_keywords = ?, app_name = ?, app_url = ? WHERE id = ?")
                ->execute([SEO_HOME_TITLE, SEO_HOME_DESCRIPTION, SEO_HOME_KEYWORDS, SITE_BRAND, rtrim(APP_URL, '/') . '/', $id]);
        }
        // Old brand name saved in the database (footer text, email sender name, SEO fields, blog posts).
        // Only the site's own address changes — media.webtopin.com image links (the CDN) stay as they are.
        $brandPairs = [['WebToPin', SITE_BRAND], ['Webtopin', SITE_BRAND], ['Web To Pin', SITE_BRAND]];
        $domainPairs = [['://www.webtopin.com', '://automatedpin.io'], ['://webtopin.com', '://automatedpin.io'], [' webtopin.com', ' automatedpin.io'], ['>webtopin.com', '>automatedpin.io']];
        $targets = [
            ['footer_settings', ['logo_text', 'description', 'footer_text'], true],
            ['seo_settings', ['publisher_name', 'app_description'], true],
            ['seo_reviews', ['item_name', 'review_body'], true],
            ['platform_settings', ['setting_value'], false],
            ['blog_posts', ['title', 'subtitle', 'meta_title', 'meta_description', 'content', 'author'], true],
        ];
        foreach ($targets as [$table, $cols, $withDomain]) {
            foreach ($cols as $col) {
                // settings table: only the plain brand name (e.g. email sender) — never keys, buckets or URLs
                foreach ($withDomain ? array_merge($brandPairs, $domainPairs) : [['Web To Pin', SITE_BRAND]] as [$old, $new]) {
                    try {
                        $pdo->prepare("UPDATE $table SET $col = REPLACE($col, ?, ?) WHERE $col LIKE BINARY ?")
                            ->execute([$old, $new, '%' . $old . '%']);
                    } catch (Throwable $e) { /* table / column missing */ }
                }
            }
        }
    } catch (Throwable $e) { /* tables missing — defaults apply */ }
    if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
    @file_put_contents($flag, date('c'));
    seo_write_robots_file($pdo);
}

/** Absolute, clean URL of the page being shown (no query string, no ".php", no "index.php"). */
function seo_current_url(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = preg_replace('#/index\.php$#', '/', $path);
    $path = preg_replace('#\.php$#', '', $path);
    $path = preg_replace('#/{2,}#', '/', $path);
    return rtrim(defined('APP_URL') ? APP_URL : '', '/') . $path;
}

/** Keeps a title within ~60 characters: drops the " | Brand" / " — Brand" suffix when it would not fit. */
function seo_fit_title(string $title): string
{
    $title = trim(preg_replace('/\s+/', ' ', $title));
    if (mb_strlen($title) <= 60) return $title;
    $b = preg_quote(SITE_BRAND, '/');
    $short = preg_replace('/\s*[|—–-]\s*(Blog\s*[|—–-]\s*)?' . $b . '$/u', '', $title);
    return $short !== '' ? $short : $title;
}

/** Keeps a meta description within 160 characters, cut at a word. */
function seo_fit_description(string $d): string
{
    $d = trim(preg_replace('/\s+/', ' ', $d));
    if (mb_strlen($d) <= 160) return $d;
    $cut = mb_substr($d, 0, 157);
    $cut = preg_replace('/\s+\S*$/u', '', $cut);
    return rtrim($cut, " ,;:—–-") . '…';
}

/** Organization + WebSite (+ BreadcrumbList) JSON-LD for every public page. */
function seo_site_schema(PDO $pdo, array $breadcrumbs = []): array
{
    $s = get_seo_settings($pdo);
    $home = rtrim(APP_URL, '/') . '/';
    $logo = seo_asset_url($s['logo_path'] ?: $s['favicon_path']);
    $org = ['@type' => 'Organization', '@id' => $home . '#organization', 'name' => SITE_BRAND, 'url' => $home];
    if ($logo !== '') $org['logo'] = $logo;
    $graph = [$org, ['@type' => 'WebSite', '@id' => $home . '#website', 'name' => SITE_BRAND, 'url' => $home, 'publisher' => ['@id' => $home . '#organization']]];
    if ($breadcrumbs) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home]];
        foreach (array_values($breadcrumbs) as $i => [$name, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => preg_match('#^https?://#', $url) ? $url : rtrim(APP_URL, '/') . '/' . ltrim($url, '/')];
        }
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
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
    seo_go_live_once($pdo);
    $s = get_seo_settings($pdo);

    $title = seo_fit_title(trim((string)($opts['title'] ?? $s['meta_title'])) ?: SITE_BRAND);
    $desc = seo_fit_description(trim((string)($opts['description'] ?? $s['meta_description'])));
    $keywords = trim((string)($opts['keywords'] ?? $s['meta_keywords']));
    // Every page is its own canonical (clean URL, no query string) unless the page says otherwise.
    $canonical = trim((string)($opts['canonical'] ?? '')) ?: seo_current_url();
    $image = seo_asset_url($opts['image'] ?? ($s['og_image_path'] ?: SEO_DEFAULT_IMAGE));
    $imageAlt = trim((string)($opts['image_alt'] ?? $title));

    // Indexing: page-level noindex always wins; otherwise the admin toggle decides.
    $noindex = !empty($opts['noindex']) || empty($s['robots_index']);
    $robots = ($noindex ? 'noindex' : 'index') . ',' . (empty($s['robots_follow']) ? 'nofollow' : 'follow')
        . ($noindex ? '' : ',max-image-preview:large,max-snippet:-1');

    echo '<title>' . e($title) . "</title>\n";
    if ($desc !== '')     echo '<meta name="description" content="' . e($desc) . "\">\n";
    if ($keywords !== '') echo '<meta name="keywords" content="' . e($keywords) . "\">\n";
    echo '<meta name="robots" content="' . e($robots) . "\">\n";
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";

    $favicon = seo_asset_url($s['favicon_path']);
    if ($favicon !== '') {
        $ext = strtolower(pathinfo(parse_url($favicon, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $type = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml',
                 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/png';
        echo '<link rel="icon" type="' . e($type) . '" href="' . e($favicon) . "\">\n";
        echo '<link rel="apple-touch-icon" href="' . e($favicon) . "\">\n";
    }

    // Open Graph / Twitter
    echo '<meta property="og:type" content="' . e($opts['og_type'] ?? 'website') . "\">\n";
    echo '<meta property="og:site_name" content="' . e(SITE_BRAND) . "\">\n";
    echo '<meta property="og:title" content="' . e($title) . "\">\n";
    if ($desc !== '') echo '<meta property="og:description" content="' . e($desc) . "\">\n";
    echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    if ($image !== '') {
        echo '<meta property="og:image" content="' . e($image) . "\">\n";
        echo '<meta property="og:image:alt" content="' . e($imageAlt) . "\">\n";
    }
    echo '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
    echo '<meta name="twitter:title" content="' . e($title) . "\">\n";
    if ($desc !== '') echo '<meta name="twitter:description" content="' . e($desc) . "\">\n";
    if ($image !== '') echo '<meta name="twitter:image" content="' . e($image) . "\">\n";

    if (!$noindex && ($opts['site_schema'] ?? true)) {
        echo '<script type="application/ld+json">' . json_encode(seo_site_schema($pdo, $opts['breadcrumbs'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
    // The app / rating / offers markup describes the whole product: home page only (or where a page asks for it).
    $isHome = rtrim($canonical, '/') === rtrim(defined('APP_URL') ? APP_URL : '', '/');
    if (($opts['schema'] ?? $isHome) && !$noindex) {
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
    $txt .= "Allow: /\n";
    foreach (['/admin/', '/user/', '/auth/', '/config/', '/oauth/', '/cron/', '/webhooks/', '/includes/', '/database/', '/wp-plugin/', '/uploads/cw-src/', '/uploads/cw-pt/'] as $p) $txt .= "Disallow: $p\n";
    foreach (['/install.php', '/migrate.php', '/update-pricing-plans.php', '/robots.php', '/media.php', '/pin-template-preview', '/pin-templates', '/image-categories', '/sitemap.php'] as $p) $txt .= "Disallow: $p\n";
    if ($base !== '') $txt .= "\nSitemap: $base/sitemap.xml\n";
    return $txt;
}

/** Write the current robots.txt to disk so search engines get a static file. */
function seo_write_robots_file(PDO $pdo): bool
{
    $path = __DIR__ . '/../robots.txt';
    return @file_put_contents($path, seo_robots_txt($pdo)) !== false;
}
