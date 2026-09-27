<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'settings';
$pageTitle = 'Pinterest Settings';

$settings = get_pinterest_settings($pdo);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $redirectUri = trim($_POST['redirect_uri'] ?? '');

    if ($settings) {
        $stmt = $pdo->prepare("UPDATE pinterest_settings SET client_id = ?, client_secret = ?, redirect_uri = ? WHERE id = ?");
        $stmt->execute([$clientId, $clientSecret, $redirectUri, $settings['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pinterest_settings (client_id, client_secret, redirect_uri) VALUES (?, ?, ?)");
        $stmt->execute([$clientId, $clientSecret, $redirectUri]);
    }
    log_event($pdo, 'system', 'Pinterest app settings updated by admin');
    $settings = get_pinterest_settings($pdo);
    $success = true;
}

$suggestedRedirect = rtrim(APP_URL, '/') . '/oauth/pinterest-callback.php';

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Pinterest Settings</h1></div>

<?php if ($success): ?>
    <div class="alert alert-success">Settings saved.</div>
<?php endif; ?>

<div class="card">
    <p class="muted">These are the credentials from your Pinterest Developer App. Users never see or enter these
    — they only click "Connect Pinterest" and authorize on Pinterest's own site.</p>

    <form method="POST">
        <div class="form-row">
            <label>Client ID</label>
            <input type="text" name="client_id" value="<?= e($settings['client_id'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Client Secret</label>
            <input type="text" name="client_secret" value="<?= e($settings['client_secret'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Redirect URL</label>
            <input type="text" name="redirect_uri" value="<?= e($settings['redirect_uri'] ?? $suggestedRedirect) ?>">
            <p class="muted">This exact URL must also be added to "Redirect URIs" in your Pinterest app settings on developers.pinterest.com.
            Suggested value: <code><?= e($suggestedRedirect) ?></code></p>
        </div>
        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
