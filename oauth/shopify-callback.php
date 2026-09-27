<?php
/**
 * Shopify redirects here after the store owner approves the app.
 * Checks Shopify's HMAC signature and our own state value, swaps the temporary code for an access token,
 * saves the store and sends the user back to the Shopify Stores page.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$back = rtrim(APP_URL, '/') . '/user/shopify-stores';

function shopify_fail(PDO $pdo, int $userId, string $back, string $message): void
{
    log_event($pdo, 'oauth', $message, $userId);
    flash_set('error', $message);
    redirect($back);
}

$pending = $_SESSION['shopify_oauth'] ?? null;
unset($_SESSION['shopify_oauth']);

$shop = shopify_normalize_shop((string)($_GET['shop'] ?? ''));
$code = (string)($_GET['code'] ?? '');
$secret = shopify_app_credentials($pdo)['client_secret'];

if (!$pending || !$shop || $code === '' || $secret === '') {
    shopify_fail($pdo, $user['id'], $back, 'Shopify connection could not be completed. Please start again.');
}
if (!hash_equals((string)$pending['state'], (string)($_GET['state'] ?? '')) || $pending['shop'] !== $shop) {
    shopify_fail($pdo, $user['id'], $back, 'Invalid or expired Shopify connection request. Please start again.');
}
if (!shopify_verify_hmac($_GET, $secret)) {
    shopify_fail($pdo, $user['id'], $back, 'Shopify signature check failed. Please start again.');
}

$ex = shopify_exchange_code($pdo, $shop, $code);
if (!$ex['ok']) {
    shopify_fail($pdo, $user['id'], $back, 'Shopify token exchange failed: ' . $ex['error']);
}

$id = website_save_connection($pdo, (int)$user['id'], (int)$pending['connect_id'] ?: null, 'shopify', [
    'site_url' => 'https://' . $shop, 'site_name' => $shop, 'external_id' => $shop, 'access_token' => $ex['token'],
    'platform_meta' => ['auth_mode' => 'oauth', 'shop_domain' => $shop, 'scope' => $ex['scope']],
]);
$site = get_user_website($pdo, $id, (int)$user['id']);
$r = website_recheck($pdo, $site);

log_event($pdo, 'oauth', 'Shopify store connected (' . $shop . ')', (int)$user['id']);
if ($r['ok']) {
    flash_set('success', 'Shopify store connected: ' . ($r['site_name'] ?: $shop));
    if (!empty($r['warning'])) flash_set('info', $r['warning']);
} else {
    flash_set('error', 'The store was saved, but the connection test failed: ' . $r['error']);
}
redirect($back);
