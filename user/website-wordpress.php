<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'website-wordpress';
$pageTitle = 'WordPress Websites';
$returnTo = 'website-wordpress';

// Coming from an "Unconnected" row's Connect / Reconnect button: prefill its URL and update that row.
$connectId = (int)($_GET['connect_id'] ?? $_POST['connect_id'] ?? 0);
$target = $connectId ? get_user_website($pdo, $connectId, (int)$user['id']) : null;
if (!$target) $connectId = 0;

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteUrl = normalize_site_url((string)($_POST['site_url'] ?? ''));
    $siteKey = trim($_POST['site_key'] ?? '');

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($siteUrl === '' || $siteKey === '') {
        $errors[] = 'Please enter both the site URL and the site key.';
    } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid site URL (including https://).';
    }

    if (empty($errors)) {
        $id = website_save_connection($pdo, (int)$user['id'], $connectId ?: null, 'wordpress', [
            'site_url' => $siteUrl, 'site_name' => '', 'site_key' => $siteKey,
        ]);
        $site = get_user_website($pdo, $id, (int)$user['id']);
        $r = website_recheck($pdo, $site);
        if ($r['ok']) {
            $success = true;
            $connectId = 0;
            $target = null;
        } else {
            $errors[] = 'Site saved, but the connection test failed. Double-check the site URL and key, then click "Re-check" below. (' . e((string)$r['error']) . ')';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND platform = 'wordpress' ORDER BY connected_at DESC, id DESC");
$stmt->execute([$user['id']]);
$siteRows = $stmt->fetchAll();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>WordPress Websites</h1><a href="websites" class="btn-secondary">← All Websites</a></div>

<?= flash_render() ?>
<?php if ($success): ?><div class="alert alert-success">Website connected successfully!</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= $err ?></div><?php endforeach; ?>

<div class="card">
    <h2>1. Install the plugin on your WordPress site</h2>
    <p>Download and install this small plugin on the WordPress site you want to publish articles to. It lets this
    app publish posts securely — no WordPress password is ever shared.</p>
    <a href="../assets/downloads/pinscheduler-publisher.zip" class="btn-primary">Download WP Plugin (.zip)</a>

    <h3 style="margin-top:24px;">Setup guide</h3>
    <ol>
        <li>In your WordPress admin, go to <strong>Plugins → Add New → Upload Plugin</strong>, choose the downloaded zip, and click <strong>Install Now</strong>, then <strong>Activate</strong>.</li>
        <li>Go to <strong>Settings → PinScheduler Publisher</strong>.</li>
        <li>Copy the <strong>Site URL</strong> and <strong>Site Key</strong> shown there.</li>
        <li>Paste both into the form below and click <strong>Connect Website</strong>.</li>
    </ol>
</div>

<div class="card">
    <h2>2. Connect a website<?= $target ? ' — ' . e($target['site_url']) : '' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="connect_id" value="<?= (int)$connectId ?>">
        <div class="two-col">
            <div class="form-row">
                <label>Site URL</label>
                <input type="text" name="site_url" placeholder="https://yourblog.com" value="<?= e($target['site_url'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <label>Site Key</label>
                <input type="text" name="site_key" placeholder="paste the key from the plugin settings page" required>
            </div>
        </div>
        <button type="submit" class="btn-primary">Connect Website</button>
    </form>
</div>

<div class="card">
    <h2>Your WordPress websites</h2>
    <?php $emptyText = 'No WordPress websites connected yet.'; include __DIR__ . '/includes/website-table.php'; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
