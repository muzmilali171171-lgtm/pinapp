<?php
/** Starts the "Connect with Shopify" OAuth flow: validates the store address and sends the user to Shopify to approve access. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect('shopify-stores');
}
if (!shopify_oauth_configured($pdo)) {
    flash_set('error', 'One-click connect is not set up yet. Use your own credentials instead.');
    redirect('shopify-stores');
}

$shop = shopify_normalize_shop((string)($_POST['shop'] ?? ''));
if (!$shop) {
    flash_set('error', 'Enter your store address as my-store.myshopify.com.');
    redirect('shopify-stores');
}

$state = bin2hex(random_bytes(16));
$_SESSION['shopify_oauth'] = ['state' => $state, 'shop' => $shop, 'connect_id' => (int)($_POST['connect_id'] ?? 0)];

redirect(shopify_build_authorize_url($pdo, $shop, $state));
