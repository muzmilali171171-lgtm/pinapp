<?php
/**
 * Multi-platform website support: WordPress (companion plugin), Shopify (Admin API),
 * Wix (REST API key) and Custom websites (signed webhook).
 *
 * Everything that differs per platform lives here behind three entry points, so the
 * rest of the app never has to care which platform a website is on:
 *
 *   website_recheck()            - verify a connection and refresh the cached site info
 *   website_publish_dispatch()   - publish an article (same result shape as website_publish_post())
 *   website_get_crawl_site()     - the "Automate Pin" hook: gives the website a crawl site
 *
 * NOTE: the Shopify, Wix and webhook code talks to third-party APIs. It is written from
 * their published docs but could not be run against live accounts while building — test
 * each platform with a real site once after uploading.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/website_functions.php';
require_once __DIR__ . '/page_crawler_functions.php';

/* ===================== Labels / small helpers ===================== */

const PLATFORM_LABELS = [
    'none' => 'Unconnected',
    'wordpress' => 'WordPress',
    'shopify' => 'Shopify',
    'wix' => 'Wix',
    'custom' => 'Custom',
];

function platform_label(?string $platform): string
{
    return PLATFORM_LABELS[$platform ?? 'none'] ?? 'Unconnected';
}

/** 'connected' or 'unconnected' — what the user sees. A site with no platform, or a failed check, is unconnected. */
function website_connection_state(array $w): string
{
    $platform = $w['platform'] ?? 'wordpress';
    return ($platform !== 'none' && ($w['status'] ?? 'error') === 'connected') ? 'connected' : 'unconnected';
}

function website_meta(array $w): array
{
    $m = json_decode($w['platform_meta'] ?? '', true);
    return is_array($m) ? $m : [];
}

function normalize_site_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    return rtrim($url, '/');
}

function public_upload_url(string $relativePath): string
{
    // External storage copy (R2 / S3 / B2) when available, else the hosting URL.
    if (function_exists('media_url')) return media_url($relativePath);
    return rtrim(APP_URL, '/') . '/' . ltrim($relativePath, '/');
}

/** Blocks webhook targets that point at this server's own network (localhost, 10.x, 192.168.x, metadata IPs...). */
function platform_url_is_safe(string $url): bool
{
    $p = parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return false;
    $host = $p['host'];
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false; // did not resolve
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/* ===================== CSRF + flash messages ===================== */

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''));
}

function flash_set(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/** Returns (and clears) queued flash messages as ready-to-print alert HTML. Messages are plain text and escaped here. */
function flash_render(): string
{
    if (empty($_SESSION['flash_messages'])) return '';
    $html = '';
    foreach ($_SESSION['flash_messages'] as $m) {
        $html .= '<div class="alert alert-' . e($m['type']) . '">' . e($m['message']) . '</div>';
    }
    unset($_SESSION['flash_messages']);
    return $html;
}

/* ===================== Admin-editable platform settings ===================== */

function platform_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return ($v === false || $v === null || $v === '') ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default; // table not created yet (migrate.php not run)
    }
}

function platform_setting_set(PDO $pdo, string $key, ?string $value): void
{
    $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value]);
}

function platform_default_guide(string $platform): string
{
    if ($platform === 'shopify') {
        return '<p>Connect your Shopify store so you can view its products and blog posts, turn them into daily Pinterest pins, and publish AI articles straight to your store blog.</p>'
            . '<h4>Option A — Connect with Shopify (recommended)</h4>'
            . '<ol><li>Enter your store address (for example <code>my-store.myshopify.com</code>) and click <strong>Connect with Shopify</strong>.</li>'
            . '<li>Approve the permissions on Shopify (read products, read and write blog content).</li>'
            . '<li>You are sent back here and the store shows as <strong>Connected</strong>.</li></ol>'
            . '<h4>Option B — Enter credentials manually</h4>'
            . '<ol><li>In your <strong>Shopify Dev Dashboard</strong> create an app and give it the scopes <code>read_products</code>, <code>read_content</code> and <code>write_content</code>, then install it on your store.</li>'
            . '<li>Copy the app\'s <strong>Client ID</strong> and <strong>Client secret</strong> into the manual form below (access tokens are fetched and renewed automatically).</li>'
            . '<li>Older stores that already have a legacy custom app can paste its <strong>Admin API access token</strong> (starts with <code>shpat_</code>) instead.</li></ol>'
            . '<p>Only your <code>*.myshopify.com</code> address works here, not your custom domain.</p>';
    }
    if ($platform === 'wix') {
        return '<p>Connect a Wix site to publish AI articles to its Wix Blog and to turn its pages into daily Pinterest pins.</p>'
            . '<ol><li>Make sure the <strong>Wix Blog</strong> app is added to your site.</li>'
            . '<li>Open <a href="https://manage.wix.com/account/api-keys" target="_blank" rel="noopener">Wix API Keys</a>, click <strong>Generate API Key</strong>, and give it the <strong>Manage Blog</strong> permission (add <strong>Manage Ricos Document</strong> and <strong>Read Members</strong> if they are listed).</li>'
            . '<li>Copy the API key. It is shown only once.</li>'
            . '<li>Open your site\'s dashboard. The <strong>Site ID</strong> is the ID in the browser address bar after <code>/dashboard/</code>.</li>'
            . '<li>Paste the site URL, Site ID and API key into the form below and click <strong>Connect Wix Site</strong>.</li></ol>'
            . '<p>Repeat for every Wix site you want to connect.</p>';
    }
    return '';
}

/** Guide HTML for a platform, admin-edited or default, reduced to a small safe tag set before output. */
function platform_guide_html(PDO $pdo, string $platform): string
{
    $html = platform_setting($pdo, 'guide_' . $platform, null);
    if ($html === null) $html = platform_default_guide($platform);
    return sanitize_guide_html($html);
}

function sanitize_guide_html(string $html): string
{
    $html = strip_tags($html, '<p><br><ol><ul><li><strong><b><em><i><a><code><pre><h3><h4>');
    // Drop every attribute from allowed tags, except a safe href on links.
    $html = preg_replace('/<(p|br|ol|ul|li|strong|b|em|i|code|pre|h3|h4)\b[^>]*>/i', '<$1>', $html);
    $html = preg_replace_callback('/<a\b([^>]*)>/i', function ($m) {
        if (preg_match('/href\s*=\s*("|\')(.*?)\1/i', $m[1], $h) && preg_match('#^(https?://|mailto:)#i', trim($h[2]))) {
            return '<a href="' . e(trim($h[2])) . '" target="_blank" rel="noopener">';
        }
        return '<a>';
    }, $html);
    return $html;
}

/* ===================== Generic HTTP ===================== */

function platform_http(string $method, string $url, array $headers = [], $body = null, int $timeout = 60, bool $follow = false): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'PinScheduler/1.0',
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body) : $body;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) return ['ok' => false, 'code' => 0, 'data' => null, 'raw' => '', 'error' => $err ?: 'Connection failed.'];
    $data = json_decode($raw, true);
    $ok = $code >= 200 && $code < 300;
    return ['ok' => $ok, 'code' => $code, 'data' => is_array($data) ? $data : null, 'raw' => $raw,
        'error' => $ok ? null : ('HTTP ' . $code . ': ' . mb_substr(trim(strip_tags($raw)), 0, 300))];
}

/* ===================== Saving a connection ===================== */

/**
 * Inserts or updates a website row and returns its id. If $targetId is one of the user's own
 * rows it is updated in place (that is how an "Unconnected" site gets connected). Otherwise an
 * existing row with the same URL is reused, so connecting a site never creates a duplicate.
 * $f keys: site_url, site_name, site_key, external_id, access_token, platform_meta (array), webhook_url.
 */
function website_save_connection(PDO $pdo, int $userId, ?int $targetId, string $platform, array $f): int
{
    $f['site_url'] = normalize_site_url($f['site_url']);
    $meta = json_encode($f['platform_meta'] ?? []);

    if (!$targetId) {
        $existing = false;
        // Shopify / Wix sites are identified by their store / site id — a store's URL changes to its primary domain once connected.
        if (in_array($platform, ['shopify', 'wix'], true) && !empty($f['external_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND platform = ? AND external_id = ? LIMIT 1");
            $stmt->execute([$userId, $platform, $f['external_id']]);
            $existing = $stmt->fetchColumn();
        }
        if (!$existing) {
            $stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND site_url = ? ORDER BY (platform = 'none') DESC, id ASC LIMIT 1");
            $stmt->execute([$userId, $f['site_url']]);
            $existing = $stmt->fetchColumn();
        }
        if ($existing) $targetId = (int)$existing;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$targetId, $userId]);
        if (!$stmt->fetchColumn()) $targetId = null;
    }

    if ($targetId) {
        $pdo->prepare("UPDATE websites SET platform = ?, site_name = COALESCE(NULLIF(?, ''), site_name), site_url = ?, site_key = ?,
            external_id = ?, access_token = ?, platform_meta = ?, webhook_url = ?, status = 'error' WHERE id = ? AND user_id = ?")
            ->execute([$platform, $f['site_name'] ?? '', $f['site_url'], $f['site_key'] ?? '', $f['external_id'] ?? null,
                $f['access_token'] ?? null, $meta, $f['webhook_url'] ?? null, $targetId, $userId]);
        return $targetId;
    }

    $pdo->prepare("INSERT INTO websites (user_id, platform, site_name, site_url, site_key, external_id, access_token, platform_meta, webhook_url, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'error')")
        ->execute([$userId, $platform, ($f['site_name'] ?? '') ?: null, $f['site_url'], $f['site_key'] ?? '', $f['external_id'] ?? null,
            $f['access_token'] ?? null, $meta, $f['webhook_url'] ?? null]);
    return (int)$pdo->lastInsertId();
}

function get_user_website(PDO $pdo, int $id, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    return $stmt->fetch() ?: null;
}

/* ===================== Verify + refresh cached info (all platforms) ===================== */

/**
 * Verifies a website's connection and returns normalized info. Never writes to the database —
 * see website_recheck() for that.
 * Returns: ok, error, warning, site_name, site_url, categories[], tags[], authors[], meta[] (merged into platform_meta).
 */
function website_verify(PDO $pdo, array $w): array
{
    $platform = $w['platform'] ?? 'wordpress';
    $blank = ['ok' => false, 'error' => null, 'warning' => null, 'site_name' => null, 'site_url' => null, 'categories' => [], 'tags' => [], 'authors' => [], 'meta' => []];

    switch ($platform) {
        case 'wordpress':
            $ping = website_ping($w['site_url'], $w['site_key']);
            if (!$ping['ok']) {
                $blank['error'] = is_array($ping['data']) ? json_encode($ping['data']) : ($ping['error'] ?: 'Could not reach the site.');
                return $blank;
            }
            return array_merge($blank, [
                'ok' => true,
                'site_name' => $ping['data']['site_name'] ?? $w['site_url'],
                'categories' => $ping['data']['categories'] ?? [],
                'tags' => $ping['data']['tags'] ?? [],
                'authors' => $ping['data']['authors'] ?? [],
            ]);
        case 'shopify':
            return array_merge($blank, shopify_verify($pdo, $w));
        case 'wix':
            return array_merge($blank, wix_verify($w));
        case 'custom':
            return array_merge($blank, custom_verify($w));
    }
    $blank['error'] = 'This website is not linked to a platform yet.';
    return $blank;
}

/** Verifies the connection and stores the result (status, site name, categories, authors, platform meta). */
function website_recheck(PDO $pdo, array $w): array
{
    $r = website_verify($pdo, $w);
    ensure_db_connection($pdo);

    if ($r['ok']) {
        $meta = array_merge(website_meta($w), $r['meta']);
        if ($r['warning']) $meta['warning'] = $r['warning']; else unset($meta['warning']);
        $pdo->prepare("UPDATE websites SET status = 'connected', site_name = ?, site_url = ?, categories_cache = ?, tags_cache = ?,
            authors_cache = ?, platform_meta = ?, last_checked_at = NOW() WHERE id = ?")
            ->execute([$r['site_name'] ?: $w['site_url'], $r['site_url'] ?: $w['site_url'], json_encode($r['categories']),
                json_encode($r['tags']), json_encode($r['authors']), json_encode($meta), $w['id']]);
    } else {
        $pdo->prepare("UPDATE websites SET status = 'error', last_checked_at = NOW() WHERE id = ?")->execute([$w['id']]);
    }
    return $r;
}

/* ===================== Publishing (all platforms) ===================== */

/**
 * Publishes an article to whichever platform the website is on. $payload is the same array
 * website_publish_post() takes (title, content, category, tags, status, slug, meta_description,
 * author_id) plus, for non-WordPress platforms, featured_image_url. Result shape matches
 * website_publish_post(): ['ok'=>bool, 'data'=>['ok','id','url']|..., 'error'=>?string].
 */
function website_publish_dispatch(PDO $pdo, array $w, array $payload): array
{
    switch ($w['platform'] ?? 'wordpress') {
        case 'wordpress':
            return website_publish_post($w['site_url'], $w['site_key'], $payload);
        case 'shopify':
            return shopify_publish_article($pdo, $w, $payload);
        case 'wix':
            return wix_publish_post($w, $payload);
        case 'custom':
            return custom_publish_article($w, $payload);
    }
    return ['ok' => false, 'data' => null, 'error' => 'This website is not connected to a platform.'];
}

/* ===================== Automate Pin hook ===================== */

/** Gives a website its crawl site (the Auto Website to Daily Pin source) and links the two. */
function website_get_crawl_site(PDO $pdo, array $w): array
{
    $site = get_or_create_crawl_site($pdo, (int)$w['user_id'], $w['site_url'], (int)$w['id']);
    if (empty($site['website_id'])) {
        $pdo->prepare("UPDATE crawl_sites SET website_id = ? WHERE id = ?")->execute([$w['id'], $site['id']]);
        $site['website_id'] = $w['id'];
    }
    return $site;
}

/* =====================================================================
 *  SHOPIFY
 * ===================================================================== */

function shopify_normalize_shop(string $input): ?string
{
    $s = strtolower(trim($input));
    if ($s === '') return null;
    $s = preg_replace('#^https?://#', '', $s);
    if (preg_match('#^admin\.shopify\.com/store/([a-z0-9][a-z0-9\-]*)#', $s, $m)) return $m[1] . '.myshopify.com';
    $s = preg_replace('#[/?\#].*$#', '', $s);
    if (strpos($s, '.') === false) $s .= '.myshopify.com';
    return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $s) ? $s : null;
}

function shopify_api_version(PDO $pdo): string
{
    $v = trim((string)platform_setting($pdo, 'shopify_api_version', ''));
    return preg_match('/^\d{4}-\d{2}$/', $v) ? $v : '2026-04';
}

function shopify_scopes(PDO $pdo): string
{
    return preg_replace('/\s+/', '', (string)platform_setting($pdo, 'shopify_scopes', 'read_products,read_content,write_content'));
}

function shopify_app_credentials(PDO $pdo): array
{
    return [
        'client_id' => trim((string)platform_setting($pdo, 'shopify_client_id', '')),
        'client_secret' => trim((string)platform_setting($pdo, 'shopify_client_secret', '')),
    ];
}

function shopify_oauth_configured(PDO $pdo): bool
{
    $c = shopify_app_credentials($pdo);
    return $c['client_id'] !== '' && $c['client_secret'] !== '';
}

function shopify_redirect_uri(): string
{
    return rtrim(APP_URL, '/') . '/oauth/shopify-callback.php';
}

function shopify_build_authorize_url(PDO $pdo, string $shop, string $state): ?string
{
    $c = shopify_app_credentials($pdo);
    if ($c['client_id'] === '') return null;
    return 'https://' . $shop . '/admin/oauth/authorize?' . http_build_query([
        'client_id' => $c['client_id'],
        'scope' => shopify_scopes($pdo),
        'redirect_uri' => shopify_redirect_uri(),
        'state' => $state,
    ]);
}

/** Validates the HMAC Shopify puts on the OAuth callback query string. */
function shopify_verify_hmac(array $query, string $secret): bool
{
    $hmac = (string)($query['hmac'] ?? '');
    unset($query['hmac'], $query['signature']);
    ksort($query);
    $pairs = [];
    foreach ($query as $k => $v) $pairs[] = str_replace(['%', '&', '='], ['%25', '%26', '%3D'], (string)$k) . '=' . str_replace(['%', '&'], ['%25', '%26'], (string)$v);
    return $hmac !== '' && hash_equals(hash_hmac('sha256', implode('&', $pairs), $secret), $hmac);
}

function shopify_exchange_code(PDO $pdo, string $shop, string $code): array
{
    $c = shopify_app_credentials($pdo);
    $r = platform_http('POST', 'https://' . $shop . '/admin/oauth/access_token', ['Content-Type: application/json'],
        ['client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'code' => $code], 30);
    if ($r['ok'] && !empty($r['data']['access_token'])) return ['ok' => true, 'token' => $r['data']['access_token'], 'scope' => $r['data']['scope'] ?? '', 'error' => null];
    return ['ok' => false, 'token' => null, 'scope' => '', 'error' => $r['error'] ?: 'Shopify did not return an access token.'];
}

/** Dev Dashboard apps: client credentials grant. Tokens last ~24h and are renewed automatically by shopify_get_token(). */
function shopify_client_credentials_token(string $shop, string $clientId, string $clientSecret): array
{
    $r = platform_http('POST', 'https://' . $shop . '/admin/oauth/access_token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $clientSecret]), 30);
    if ($r['ok'] && !empty($r['data']['access_token'])) {
        return ['ok' => true, 'token' => $r['data']['access_token'], 'expires_at' => time() + (int)($r['data']['expires_in'] ?? 86399), 'scope' => $r['data']['scope'] ?? '', 'error' => null];
    }
    return ['ok' => false, 'token' => null, 'expires_at' => 0, 'scope' => '', 'error' => $r['error'] ?: 'Shopify rejected the Client ID / Client secret.'];
}

/** Current access token for a store, renewing client-credentials tokens that are about to expire. */
function shopify_get_token(PDO $pdo, array &$w): ?string
{
    $meta = website_meta($w);
    if (($meta['auth_mode'] ?? '') === 'client_credentials' && !empty($meta['client_id']) && !empty($meta['client_secret'])) {
        if (empty($w['access_token']) || (int)($meta['token_expires_at'] ?? 0) < time() + 300) {
            $t = shopify_client_credentials_token($w['external_id'], $meta['client_id'], $meta['client_secret']);
            if (!$t['ok']) return null;
            $meta['token_expires_at'] = $t['expires_at'];
            $w['access_token'] = $t['token'];
            $w['platform_meta'] = json_encode($meta);
            ensure_db_connection($pdo);
            $pdo->prepare("UPDATE websites SET access_token = ?, platform_meta = ? WHERE id = ?")->execute([$t['token'], $w['platform_meta'], $w['id']]);
        }
    }
    return $w['access_token'] ?: null;
}

/** Runs a GraphQL Admin API call. Returns ['ok', 'data', 'error']. Retries when Shopify throttles. */
function shopify_graphql(PDO $pdo, array &$w, string $query, array $variables = []): array
{
    $token = shopify_get_token($pdo, $w);
    if (!$token) return ['ok' => false, 'data' => null, 'error' => 'No valid Shopify access token. Reconnect the store.'];
    $url = 'https://' . $w['external_id'] . '/admin/api/' . shopify_api_version($pdo) . '/graphql.json';
    $body = ['query' => $query];
    if ($variables) $body['variables'] = $variables;

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $r = platform_http('POST', $url, ['Content-Type: application/json', 'X-Shopify-Access-Token: ' . $token], $body, 60);
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['ok' => false, 'data' => null, 'error' => 'Shopify rejected the access token (HTTP ' . $r['code'] . '). The app may have been uninstalled or is missing a scope — reconnect the store.'];
        }
        if (!$r['ok'] || !is_array($r['data'])) return ['ok' => false, 'data' => null, 'error' => $r['error'] ?: 'Unexpected response from Shopify.'];

        if (!empty($r['data']['errors'])) {
            $throttled = strpos(json_encode($r['data']['errors']), 'THROTTLED') !== false;
            if ($throttled && $attempt < 2) { sleep(2); continue; }
            $msg = is_array($r['data']['errors']) ? implode('; ', array_map(fn($x) => is_array($x) ? ($x['message'] ?? json_encode($x)) : (string)$x, $r['data']['errors'])) : (string)$r['data']['errors'];
            return ['ok' => false, 'data' => $r['data']['data'] ?? null, 'error' => $msg];
        }
        return ['ok' => true, 'data' => $r['data']['data'] ?? [], 'error' => null];
    }
    return ['ok' => false, 'data' => null, 'error' => 'Shopify is rate limiting this store. Try again in a minute.'];
}

function shopify_gid_number(string $gid): string
{
    return preg_match('#/(\d+)(\?.*)?$#', $gid, $m) ? $m[1] : $gid;
}

function shopify_verify(PDO $pdo, array $w): array
{
    if (empty($w['external_id'])) return ['ok' => false, 'error' => 'Missing the store address (my-store.myshopify.com).'];

    $shop = shopify_graphql($pdo, $w, '{ shop { name myshopifyDomain primaryDomain { url } } }');
    if (!$shop['ok']) return ['ok' => false, 'error' => $shop['error']];
    $info = $shop['data']['shop'] ?? [];
    $primary = normalize_site_url($info['primaryDomain']['url'] ?? ('https://' . $w['external_id']));

    $warning = null;
    $categories = [];
    $blogs = shopify_graphql($pdo, $w, '{ blogs(first: 50) { nodes { id title handle } } }');
    if ($blogs['ok']) {
        foreach ($blogs['data']['blogs']['nodes'] ?? [] as $b) $categories[] = ['id' => $b['id'], 'name' => $b['title'], 'handle' => $b['handle']];
        if (empty($categories)) $warning = 'This store has no blog yet. Create one in Shopify (Online Store → Blog posts) before publishing articles.';
    } else {
        $warning = 'Connected, but blogs could not be read (' . $blogs['error'] . '). Make sure the app has the read_content and write_content scopes.';
    }

    return [
        'ok' => true,
        'site_name' => $info['name'] ?? $w['external_id'],
        'site_url' => $primary,
        'categories' => $categories,
        'warning' => $warning,
        'meta' => ['shop_domain' => $w['external_id'], 'shop_name' => $info['name'] ?? '', 'primary_url' => $primary],
    ];
}

/** Published products of the store: [['url','title','image']] — products not on the Online Store are skipped and counted. */
function shopify_fetch_products(PDO $pdo, array &$w, int $max = 5000): array
{
    $items = [];
    $skipped = 0;
    $cursor = null;
    $q = 'query($cursor: String) { products(first: 100, after: $cursor, query: "status:active") { pageInfo { hasNextPage endCursor } nodes { title handle onlineStoreUrl featuredImage { url } } } }';
    do {
        $r = shopify_graphql($pdo, $w, $q, ['cursor' => $cursor]);
        if (!$r['ok']) return ['ok' => false, 'items' => [], 'skipped' => 0, 'error' => $r['error']];
        $conn = $r['data']['products'] ?? [];
        foreach ($conn['nodes'] ?? [] as $p) {
            if (empty($p['onlineStoreUrl'])) { $skipped++; continue; }
            $items[] = ['url' => strtok($p['onlineStoreUrl'], '?'), 'title' => $p['title'], 'image' => $p['featuredImage']['url'] ?? null];
        }
        $cursor = $conn['pageInfo']['endCursor'] ?? null;
        $more = !empty($conn['pageInfo']['hasNextPage']) && count($items) < $max;
    } while ($more);
    return ['ok' => true, 'items' => $items, 'skipped' => $skipped, 'error' => null];
}

/** Published blog posts of the store: [['url','title','image']]. */
function shopify_fetch_articles(PDO $pdo, array &$w, int $max = 5000): array
{
    $meta = website_meta($w);
    $base = rtrim($meta['primary_url'] ?? $w['site_url'], '/');
    $items = [];
    $cursor = null;
    $q = 'query($cursor: String) { articles(first: 100, after: $cursor) { pageInfo { hasNextPage endCursor } nodes { title handle isPublished image { url } blog { handle } } } }';
    do {
        $r = shopify_graphql($pdo, $w, $q, ['cursor' => $cursor]);
        if (!$r['ok']) {
            if ($cursor === null) return shopify_fetch_articles_via_blogs($pdo, $w, $base);
            return ['ok' => false, 'items' => [], 'error' => $r['error']];
        }
        $conn = $r['data']['articles'] ?? [];
        foreach ($conn['nodes'] ?? [] as $a) {
            if (isset($a['isPublished']) && !$a['isPublished']) continue;
            $items[] = ['url' => $base . '/blogs/' . ($a['blog']['handle'] ?? 'news') . '/' . $a['handle'], 'title' => $a['title'], 'image' => $a['image']['url'] ?? null];
        }
        $cursor = $conn['pageInfo']['endCursor'] ?? null;
        $more = !empty($conn['pageInfo']['hasNextPage']) && count($items) < $max;
    } while ($more);
    return ['ok' => true, 'items' => $items, 'error' => null];
}

/** Fallback for API versions without the root `articles` query: read each blog's first 100 posts. */
function shopify_fetch_articles_via_blogs(PDO $pdo, array &$w, string $base): array
{
    $r = shopify_graphql($pdo, $w, '{ blogs(first: 50) { nodes { handle articles(first: 100) { nodes { title handle isPublished image { url } } } } } }');
    if (!$r['ok']) return ['ok' => false, 'items' => [], 'error' => $r['error']];
    $items = [];
    foreach ($r['data']['blogs']['nodes'] ?? [] as $b) {
        foreach ($b['articles']['nodes'] ?? [] as $a) {
            if (isset($a['isPublished']) && !$a['isPublished']) continue;
            $items[] = ['url' => $base . '/blogs/' . $b['handle'] . '/' . $a['handle'], 'title' => $a['title'], 'image' => $a['image']['url'] ?? null];
        }
    }
    return ['ok' => true, 'items' => $items, 'error' => null];
}

/**
 * Pulls the store's products or blog posts into crawl_pages (item_type 'product' / 'blog') so they can be
 * ticked and sent to Auto Website to Daily Pin like any other page. Selection (active) is preserved on re-sync.
 */
function shopify_sync_items(PDO $pdo, array $w, string $type): array
{
    $type = $type === 'blog' ? 'blog' : 'product';
    $fetched = $type === 'blog' ? shopify_fetch_articles($pdo, $w) : shopify_fetch_products($pdo, $w);
    if (!$fetched['ok']) return ['ok' => false, 'error' => $fetched['error'], 'added' => 0, 'total' => 0, 'skipped' => 0];

    $site = website_get_crawl_site($pdo, $w);
    ensure_db_connection($pdo);

    $stmt = $pdo->prepare("INSERT INTO crawl_pages (crawl_site_id, url, url_hash, source, item_type, image_url, meta_title, category_tags)
        VALUES (?, ?, ?, 'api', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE item_type = VALUES(item_type), image_url = VALUES(image_url), meta_title = VALUES(meta_title), source = 'api'");
    $added = 0;
    $hashes = [];
    foreach ($fetched['items'] as $it) {
        $hash = md5($it['url']);
        $hashes[] = $hash;
        $stmt->execute([$site['id'], $it['url'], $hash, $type, $it['image'], mb_substr((string)$it['title'], 0, 255), $type]);
        if ($stmt->rowCount() === 1) $added++;
    }

    // Remove items that no longer exist in the store (only ones this sync type previously imported).
    if (!empty($hashes)) {
        $ph = implode(',', array_fill(0, count($hashes), '?'));
        $pdo->prepare("DELETE FROM crawl_pages WHERE crawl_site_id = ? AND item_type = ? AND source = 'api' AND url_hash NOT IN ($ph)")
            ->execute(array_merge([$site['id'], $type], $hashes));
    }
    $pdo->prepare("UPDATE crawl_sites SET last_scanned_at = NOW() WHERE id = ?")->execute([$site['id']]);

    return ['ok' => true, 'error' => null, 'added' => $added, 'total' => count($fetched['items']), 'skipped' => $fetched['skipped'] ?? 0, 'crawl_site_id' => (int)$site['id']];
}

function shopify_publish_article(PDO $pdo, array $w, array $payload): array
{
    $fail = fn(string $m) => ['ok' => false, 'data' => null, 'error' => $m];
    $blogs = json_decode($w['categories_cache'] ?? '', true) ?: [];
    if (empty($blogs)) {
        $live = shopify_graphql($pdo, $w, '{ blogs(first: 50) { nodes { id title handle } } }');
        if ($live['ok']) foreach ($live['data']['blogs']['nodes'] ?? [] as $b) $blogs[] = ['id' => $b['id'], 'name' => $b['title'], 'handle' => $b['handle']];
    }
    if (empty($blogs)) return $fail('This Shopify store has no blog. Create one under Online Store → Blog posts, then click Re-check.');

    $blog = $blogs[0];
    $wanted = trim((string)($payload['category'] ?? ''));
    if ($wanted !== '') foreach ($blogs as $b) if (strcasecmp($b['name'], $wanted) === 0) { $blog = $b; break; }

    $meta = website_meta($w);
    $article = [
        'blogId' => $blog['id'],
        'title' => $payload['title'],
        'body' => $payload['content'],
        'author' => ['name' => $meta['shop_name'] ?? ($w['site_name'] ?: 'Editor')],
        'isPublished' => ($payload['status'] ?? 'publish') === 'publish',
    ];
    $tags = array_values(array_filter(array_map('trim', explode(',', (string)($payload['tags'] ?? '')))));
    if ($tags) $article['tags'] = $tags;
    if (!empty($payload['meta_description'])) $article['summary'] = '<p>' . e($payload['meta_description']) . '</p>';
    if (!empty($payload['featured_image_url'])) $article['image'] = ['url' => $payload['featured_image_url'], 'altText' => mb_substr((string)$payload['title'], 0, 120)];

    $mutation = 'mutation($article: ArticleCreateInput!) { articleCreate(article: $article) { article { id handle blog { handle } } userErrors { field message code } } }';
    $r = shopify_graphql($pdo, $w, $mutation, ['article' => $article]);
    if (!$r['ok']) return $fail($r['error']);

    $res = $r['data']['articleCreate'] ?? [];
    if (!empty($res['userErrors'])) return $fail('Shopify: ' . implode('; ', array_map(fn($x) => $x['message'] ?? 'error', $res['userErrors'])));
    if (empty($res['article']['id'])) return $fail('Shopify did not return the new article.');

    $base = rtrim($meta['primary_url'] ?? $w['site_url'], '/');
    $url = $base . '/blogs/' . ($res['article']['blog']['handle'] ?? ($blog['handle'] ?? 'news')) . '/' . $res['article']['handle'];
    return ['ok' => true, 'data' => ['ok' => true, 'id' => shopify_gid_number($res['article']['id']), 'url' => $url], 'error' => null];
}

/* =====================================================================
 *  WIX
 * ===================================================================== */

const WIX_API_BASE = 'https://www.wixapis.com';

function wix_request(array $w, string $method, string $path, ?array $body = null): array
{
    return platform_http($method, WIX_API_BASE . $path, [
        'Authorization: ' . $w['access_token'],
        'wix-site-id: ' . $w['external_id'],
        'Content-Type: application/json',
    ], $body === [] ? '{}' : $body, 60); // Wix wants an object, not [], for empty bodies
}

function wix_error_text(array $r): string
{
    $msg = $r['data']['message'] ?? $r['data']['details']['applicationError']['description'] ?? null;
    return $msg ? ('Wix: ' . $msg . ' (HTTP ' . $r['code'] . ')') : ($r['error'] ?: 'Wix request failed.');
}

function wix_verify(array $w): array
{
    if (empty($w['access_token']) || empty($w['external_id'])) return ['ok' => false, 'error' => 'Both the Wix API key and the Site ID are required.'];

    // The blog categories call proves the key, the site ID and the Blog permission all at once.
    $cats = wix_request($w, 'GET', '/blog/v3/categories?paging.limit=100');
    if (!$cats['ok']) {
        $hint = in_array($cats['code'], [401, 403], true) ? ' Check the API key, the Site ID and that the key has the Manage Blog permission (and that the Wix Blog app is installed on the site).' : '';
        return ['ok' => false, 'error' => wix_error_text($cats) . $hint];
    }
    $categories = [];
    foreach ($cats['data']['categories'] ?? [] as $c) $categories[] = ['id' => $c['id'], 'name' => $c['label'] ?? ($c['title'] ?? $c['id'])];

    $meta = website_meta($w);
    $warning = null;
    if (empty($meta['member_id'])) {
        $m = wix_request($w, 'GET', '/members/v1/members?fieldsets=PUBLIC&paging.limit=1');
        $memberId = $m['ok'] ? ($m['data']['members'][0]['id'] ?? null) : null;
        if ($memberId) $meta['member_id'] = $memberId;
        else $warning = 'Connected, but no author (member) could be found automatically. Publishing needs one — add the Member ID when connecting, or give the API key the Read Members permission and click Re-check.';
    }

    $name = null;
    $props = wix_request($w, 'GET', '/site-properties/v4/properties');
    if ($props['ok']) $name = $props['data']['properties']['siteDisplayName'] ?? null;

    return ['ok' => true, 'site_name' => $name ?: (parse_url($w['site_url'], PHP_URL_HOST) ?: $w['site_url']), 'categories' => $categories, 'warning' => $warning,
        'meta' => ['member_id' => $meta['member_id'] ?? null]];
}

function wix_publish_post(array $w, array $payload): array
{
    $fail = fn(string $m) => ['ok' => false, 'data' => null, 'error' => $m];
    $meta = website_meta($w);
    if (empty($meta['member_id'])) return $fail('No Wix author (member ID) is set for this site. Reconnect it with a Member ID, or give the API key the Read Members permission and click Re-check.');

    $html = (string)$payload['content'];

    // Wix Blog stores rich content as a "Ricos" document — Wix converts the HTML for us.
    $conv = wix_request($w, 'POST', '/ricos/v1/ricos-document/convert/to-ricos', [
        'html' => $html,
        'options' => ['plugins' => ['IMAGE', 'LINK', 'HEADING', 'DIVIDER', 'TABLE', 'CODE_BLOCK']],
    ]);
    if (!$conv['ok'] || empty($conv['data']['document'])) return $fail('Could not convert the article for Wix: ' . wix_error_text($conv));

    $categoryIds = [];
    $wanted = trim((string)($payload['category'] ?? ''));
    if ($wanted !== '') foreach (json_decode($w['categories_cache'] ?? '', true) ?: [] as $c) if (strcasecmp($c['name'], $wanted) === 0) $categoryIds[] = $c['id'];

    $draft = [
        'title' => $payload['title'],
        'memberId' => $meta['member_id'],
        'richContent' => $conv['data']['document'],
        'commentingEnabled' => true,
    ];
    if ($categoryIds) $draft['categoryIds'] = $categoryIds;
    if (!empty($payload['meta_description'])) $draft['excerpt'] = mb_substr((string)$payload['meta_description'], 0, 500);

    $withImage = $draft;
    if (!empty($payload['featured_image_url'])) {
        $imp = wix_request($w, 'POST', '/site-media/v1/files/import', ['url' => $payload['featured_image_url'], 'displayName' => 'article-' . substr(md5($payload['featured_image_url']), 0, 8)]);
        if ($imp['ok'] && !empty($imp['data']['file']['id'])) {
            $withImage['heroImage'] = ['id' => $imp['data']['file']['id'], 'altText' => mb_substr((string)$payload['title'], 0, 120)];
        }
    }

    $create = wix_request($w, 'POST', '/blog/v3/draft-posts', ['draftPost' => $withImage]);
    if (!$create['ok'] && $withImage !== $draft) {
        $create = wix_request($w, 'POST', '/blog/v3/draft-posts', ['draftPost' => $draft]); // retry without the hero image
    }
    $draftId = $create['data']['draftPost']['id'] ?? null;
    if (!$create['ok'] || !$draftId) return $fail('Wix could not create the draft post: ' . wix_error_text($create));

    $pub = wix_request($w, 'POST', '/blog/v3/draft-posts/' . rawurlencode($draftId) . '/publish', []);
    if (!$pub['ok']) return $fail('The draft was created in Wix but publishing failed: ' . wix_error_text($pub));

    $postId = $pub['data']['postId'] ?? $draftId;
    $url = rtrim($w['site_url'], '/');
    $post = wix_request($w, 'GET', '/blog/v3/posts/' . rawurlencode($postId) . '?fieldsets=URL');
    if ($post['ok'] && !empty($post['data']['post']['url'])) {
        $u = $post['data']['post']['url'];
        $url = rtrim($u['base'] ?? $url, '/') . '/' . ltrim($u['path'] ?? '', '/');
    }
    return ['ok' => true, 'data' => ['ok' => true, 'id' => $postId, 'url' => $url], 'error' => null];
}

/* =====================================================================
 *  CUSTOM WEBSITE (signed webhook)
 * ===================================================================== */

/**
 * POSTs a JSON event to the user's webhook URL. The secret (site_key) signs every request:
 *   X-PinScheduler-Signature: sha256=HMAC_SHA256(secret, timestamp + "." + raw_body)
 * so the receiving site can prove the request came from this app.
 */
function custom_webhook_send(array $w, string $event, array $data): array
{
    $url = (string)$w['webhook_url'];
    if (!platform_url_is_safe($url)) return ['ok' => false, 'code' => 0, 'data' => null, 'raw' => '', 'error' => 'The webhook URL must be a public http(s) address.'];

    $body = json_encode(['event' => $event, 'site_url' => $w['site_url'], 'sent_at' => gmdate('c')] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ts = (string)time();
    return platform_http('POST', $url, [
        'Content-Type: application/json',
        'X-PinScheduler-Event: ' . $event,
        'X-PinScheduler-Timestamp: ' . $ts,
        'X-PinScheduler-Signature: sha256=' . hash_hmac('sha256', $ts . '.' . $body, (string)$w['site_key']),
    ], $body, 60);
}

function custom_verify(array $w): array
{
    if (empty($w['webhook_url'])) return ['ok' => false, 'error' => 'No webhook URL is set for this website.'];
    $r = custom_webhook_send($w, 'ping', []);
    if (!$r['ok']) return ['ok' => false, 'error' => 'The webhook did not answer with a 2xx status. ' . ($r['error'] ?: '')];

    $d = $r['data'] ?? [];
    return [
        'ok' => true,
        'site_name' => !empty($d['site_name']) ? (string)$d['site_name'] : (parse_url($w['site_url'], PHP_URL_HOST) ?: $w['site_url']),
        'categories' => is_array($d['categories'] ?? null) ? array_map(fn($c) => is_array($c) ? ['id' => $c['id'] ?? ($c['name'] ?? ''), 'name' => $c['name'] ?? ''] : ['id' => $c, 'name' => (string)$c], $d['categories']) : [],
        'authors' => is_array($d['authors'] ?? null) ? $d['authors'] : [],
    ];
}

function custom_publish_article(array $w, array $payload): array
{
    $r = custom_webhook_send($w, 'article.publish', ['article' => [
        'title' => $payload['title'],
        'content_html' => $payload['content'],
        'category' => $payload['category'] ?? '',
        'tags' => $payload['tags'] ?? '',
        'status' => $payload['status'] ?? 'publish',
        'slug' => $payload['slug'] ?? '',
        'meta_description' => $payload['meta_description'] ?? '',
        'featured_image_url' => $payload['featured_image_url'] ?? '',
    ]]);
    if (!$r['ok']) return ['ok' => false, 'data' => null, 'error' => 'Webhook publish failed. ' . ($r['error'] ?: '')];

    $d = $r['data'] ?? [];
    $id = isset($d['id']) && $d['id'] !== '' ? (string)$d['id'] : 'wh_' . substr(md5($payload['title'] . microtime()), 0, 12);
    return ['ok' => true, 'data' => ['ok' => true, 'id' => $id, 'url' => (string)($d['url'] ?? '')], 'error' => null];
}
