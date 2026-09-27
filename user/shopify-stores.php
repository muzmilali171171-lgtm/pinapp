<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'shopify-stores';
$pageTitle = 'Shopify Stores';
$returnTo = 'shopify-stores';

$connectId = (int)($_GET['connect_id'] ?? $_POST['connect_id'] ?? 0);
$target = $connectId ? get_user_website($pdo, $connectId, (int)$user['id']) : null;
if (!$target) $connectId = 0;

$errors = [];
$oauthReady = shopify_oauth_configured($pdo);

// Manual connection: store address + (Client ID & secret  OR  Admin API access token).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manual_connect') {
    $shop = shopify_normalize_shop((string)($_POST['shop'] ?? ''));
    $mode = ($_POST['auth_mode'] ?? '') === 'token' ? 'token' : 'client_credentials';

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$shop) {
        $errors[] = 'Enter your store address as my-store.myshopify.com (the address ending in .myshopify.com, not your custom domain).';
    }

    $token = null;
    $meta = ['auth_mode' => $mode, 'shop_domain' => $shop];
    if (empty($errors)) {
        @set_time_limit(90);
        if ($mode === 'client_credentials') {
            $clientId = trim($_POST['client_id'] ?? '');
            $clientSecret = trim($_POST['client_secret'] ?? '');
            if ($clientId === '' || $clientSecret === '') {
                $errors[] = 'Enter both the Client ID and the Client secret.';
            } else {
                $t = shopify_client_credentials_token($shop, $clientId, $clientSecret);
                if (!$t['ok']) {
                    $errors[] = 'Shopify did not accept those credentials: ' . e((string)$t['error']) . ' — the app must be installed on this store and created by the same organization that owns it.';
                } else {
                    $token = $t['token'];
                    $meta += ['client_id' => $clientId, 'client_secret' => $clientSecret, 'token_expires_at' => $t['expires_at'], 'scope' => $t['scope']];
                }
            }
        } else {
            $token = trim($_POST['access_token'] ?? '');
            if ($token === '') $errors[] = 'Enter the Admin API access token.';
        }
    }

    if (empty($errors)) {
        $id = website_save_connection($pdo, (int)$user['id'], $connectId ?: null, 'shopify', [
            'site_url' => 'https://' . $shop, 'site_name' => $shop, 'external_id' => $shop, 'access_token' => $token, 'platform_meta' => $meta,
        ]);
        $site = get_user_website($pdo, $id, (int)$user['id']);
        $r = website_recheck($pdo, $site);
        if ($r['ok']) {
            flash_set('success', 'Shopify store connected: ' . ($r['site_name'] ?: $shop));
            if (!empty($r['warning'])) flash_set('info', $r['warning']);
            redirect('shopify-stores');
        }
        $errors[] = 'Saved, but Shopify rejected the connection test: ' . e((string)$r['error']);
    }
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND platform = 'shopify' ORDER BY connected_at DESC, id DESC");
$stmt->execute([$user['id']]);
$siteRows = $stmt->fetchAll();

$connectShopValue = ($target && $target['platform'] === 'shopify') ? $target['external_id'] : '';
$guide = platform_guide_html($pdo, 'shopify');

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Shopify Stores</h1><a href="websites" class="btn-secondary">← All Websites</a></div>

<?= flash_render() ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= $err ?></div><?php endforeach; ?>

<div class="card">
    <h2>Setup guide</h2>
    <div class="guide-box"><?= $guide ?></div>
</div>

<div class="card">
    <h2>Connect a Shopify store<?= $target ? ' — ' . e($target['site_url']) : '' ?></h2>

    <h3 style="font-size:15px;">Connect with Shopify</h3>
    <?php if ($oauthReady): ?>
    <form method="POST" action="shopify-oauth-start" style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="connect_id" value="<?= (int)$connectId ?>">
        <div class="form-row" style="margin:0; flex:1; min-width:260px;">
            <label>Store address</label>
            <input type="text" name="shop" placeholder="my-store.myshopify.com" value="<?= e($connectShopValue) ?>" required>
        </div>
        <button type="submit" class="btn-primary">Connect with Shopify</button>
    </form>
    <?php else: ?>
        <p class="muted">One-click connect is not switched on yet (the site admin has not added the Shopify app under Admin → All Websites → Settings).
        You can still connect with your own credentials below.</p>
    <?php endif; ?>

    <details style="margin-top:20px;" <?= (!$oauthReady || $errors) ? 'open' : '' ?>>
        <summary style="cursor:pointer; font-weight:600;">Or connect with your own credentials</summary>
        <form method="POST" style="margin-top:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="manual_connect">
            <input type="hidden" name="connect_id" value="<?= (int)$connectId ?>">
            <div class="form-row">
                <label>Store address</label>
                <input type="text" name="shop" placeholder="my-store.myshopify.com" value="<?= e($_POST['shop'] ?? $connectShopValue) ?>" required>
            </div>
            <div class="form-row">
                <label>How do you want to authenticate?</label>
                <select name="auth_mode" id="shopAuthMode">
                    <option value="client_credentials" <?= ($_POST['auth_mode'] ?? '') !== 'token' ? 'selected' : '' ?>>Client ID + Client secret (Dev Dashboard app)</option>
                    <option value="token" <?= ($_POST['auth_mode'] ?? '') === 'token' ? 'selected' : '' ?>>Admin API access token (existing custom app)</option>
                </select>
            </div>
            <div id="shopCcFields" class="two-col">
                <div class="form-row"><label>Client ID</label><input type="text" name="client_id" autocomplete="off"></div>
                <div class="form-row"><label>Client secret</label><input type="password" name="client_secret" autocomplete="off"></div>
            </div>
            <div id="shopTokenFields" class="form-row" style="display:none;">
                <label>Admin API access token</label>
                <input type="password" name="access_token" placeholder="shpat_..." autocomplete="off">
            </div>
            <button type="submit" class="btn-primary">Connect Store</button>
        </form>
    </details>
</div>

<div class="card">
    <h2>Your Shopify stores</h2>
    <?php $emptyText = 'No Shopify stores connected yet.'; include __DIR__ . '/includes/website-table.php'; ?>
</div>

<script>
(function () {
    var sel = document.getElementById('shopAuthMode');
    function sync() {
        var token = sel.value === 'token';
        document.getElementById('shopCcFields').style.display = token ? 'none' : 'grid';
        document.getElementById('shopTokenFields').style.display = token ? 'block' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
