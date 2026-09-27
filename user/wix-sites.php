<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'wix-sites';
$pageTitle = 'Wix Sites';
$returnTo = 'wix-sites';

$connectId = (int)($_GET['connect_id'] ?? $_POST['connect_id'] ?? 0);
$target = $connectId ? get_user_website($pdo, $connectId, (int)$user['id']) : null;
if (!$target) $connectId = 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteUrl = normalize_site_url((string)($_POST['site_url'] ?? ''));
    $siteId = trim($_POST['site_id'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $memberId = trim($_POST['member_id'] ?? '');

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($siteUrl === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter your Wix site URL, for example https://yourname.wixsite.com/blog or your own domain.';
    } elseif ($siteId === '' || $apiKey === '') {
        $errors[] = 'Both the Site ID and the API key are required.';
    } elseif (!preg_match('/^[0-9a-fA-F\-]{20,}$/', $siteId)) {
        $errors[] = 'That does not look like a Wix Site ID. It is a long ID like 1a2b3c4d-1234-5678-90ab-cdef01234567 — see step 4 of the guide.';
    }

    if (empty($errors)) {
        @set_time_limit(90);
        $id = website_save_connection($pdo, (int)$user['id'], $connectId ?: null, 'wix', [
            'site_url' => $siteUrl, 'site_name' => '', 'external_id' => $siteId, 'access_token' => $apiKey,
            'platform_meta' => $memberId !== '' ? ['member_id' => $memberId] : [],
        ]);
        $site = get_user_website($pdo, $id, (int)$user['id']);
        $r = website_recheck($pdo, $site);
        if ($r['ok']) {
            flash_set('success', 'Wix site connected: ' . ($r['site_name'] ?: $siteUrl));
            if (!empty($r['warning'])) flash_set('info', $r['warning']);
            redirect('wix-sites');
        }
        $errors[] = 'Saved, but Wix rejected the connection test: ' . e((string)$r['error']);
    }
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND platform = 'wix' ORDER BY connected_at DESC, id DESC");
$stmt->execute([$user['id']]);
$siteRows = $stmt->fetchAll();

$guide = platform_guide_html($pdo, 'wix');

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Wix Sites</h1><a href="websites" class="btn-secondary">← All Websites</a></div>

<?= flash_render() ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= $err ?></div><?php endforeach; ?>

<div class="card">
    <h2>Setup guide</h2>
    <div class="guide-box"><?= $guide ?></div>
</div>

<div class="card">
    <h2>Connect a Wix site<?= $target ? ' — ' . e($target['site_url']) : '' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="connect_id" value="<?= (int)$connectId ?>">
        <div class="two-col">
            <div class="form-row">
                <label>Site URL</label>
                <input type="text" name="site_url" placeholder="https://yourdomain.com" value="<?= e($_POST['site_url'] ?? ($target['site_url'] ?? '')) ?>" required>
            </div>
            <div class="form-row">
                <label>Site ID</label>
                <input type="text" name="site_id" placeholder="1a2b3c4d-1234-5678-90ab-cdef01234567" value="<?= e($_POST['site_id'] ?? (($target && $target['platform'] === 'wix') ? $target['external_id'] : '')) ?>" required>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>API key</label>
                <input type="password" name="api_key" placeholder="paste the key you generated in Wix" autocomplete="off" required>
            </div>
            <div class="form-row">
                <label>Member ID <span class="muted">(optional)</span></label>
                <input type="text" name="member_id" placeholder="author of the posts — found automatically if left empty" autocomplete="off">
            </div>
        </div>
        <button type="submit" class="btn-primary">Connect Wix Site</button>
    </form>
</div>

<div class="card">
    <h2>Your Wix sites</h2>
    <?php $emptyText = 'No Wix sites connected yet.'; include __DIR__ . '/includes/website-table.php'; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
