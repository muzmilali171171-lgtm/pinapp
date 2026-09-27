<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'websites';
$pageTitle = 'All Websites';
$returnTo = 'websites';

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? ORDER BY connected_at DESC, id DESC");
$stmt->execute([$user['id']]);
$allSites = $stmt->fetchAll();

// Filter tabs: All / one per platform / Unconnected.
$counts = ['all' => count($allSites), 'wordpress' => 0, 'shopify' => 0, 'wix' => 0, 'custom' => 0, 'unconnected' => 0];
foreach ($allSites as $s) {
    if (isset($counts[$s['platform']])) $counts[$s['platform']]++;
    if (website_connection_state($s) === 'unconnected') $counts['unconnected']++;
}
$filter = $_GET['platform'] ?? 'all';
if (!isset($counts[$filter])) $filter = 'all';

$siteRows = array_values(array_filter($allSites, function ($s) use ($filter) {
    if ($filter === 'all') return true;
    if ($filter === 'unconnected') return website_connection_state($s) === 'unconnected';
    return $s['platform'] === $filter;
}));

$tabs = ['all' => 'All', 'wordpress' => 'WordPress', 'shopify' => 'Shopify', 'wix' => 'Wix', 'custom' => 'Custom', 'unconnected' => 'Unconnected'];

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>All Websites</h1></div>

<?= flash_render() ?>

<div class="card">
    <h2>Add a website</h2>
    <p class="muted">Add any website by its URL. You can run <strong>Automate Pin</strong> on it straight away, and connect it to
    WordPress, Shopify, Wix or a custom webhook whenever you want to publish articles to it.</p>
    <form method="POST" action="website-action" class="two-col" style="align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="return" value="websites">
        <div class="form-row" style="margin:0;">
            <label>Website URL</label>
            <input type="text" name="site_url" placeholder="https://yourblog.com" required>
        </div>
        <div class="form-row" style="margin:0;">
            <label>Name <span class="muted">(optional)</span></label>
            <div style="display:flex; gap:8px;">
                <input type="text" name="site_name" placeholder="My Blog">
                <button type="submit" class="btn-primary" style="white-space:nowrap;">Add Website</button>
            </div>
        </div>
    </form>

    <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
        <strong style="font-size:14px;">Connect a platform:</strong>
        <div class="site-actions" style="margin-top:8px;">
            <a href="website-wordpress" class="btn-secondary btn-small">WordPress</a>
            <a href="shopify-stores" class="btn-secondary btn-small">Shopify</a>
            <a href="wix-sites" class="btn-secondary btn-small">Wix</a>
            <a href="custom-websites" class="btn-secondary btn-small">Custom (webhook)</a>
        </div>
    </div>
</div>

<div class="card">
    <h2>Your websites <span class="muted">(<?= (int)$counts['all'] ?>)</span></h2>
    <div class="site-filter">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?platform=<?= e($key) ?>" class="btn-secondary btn-small <?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?> (<?= (int)$counts[$key] ?>)</a>
        <?php endforeach; ?>
    </div>
    <?php
    $emptyText = $filter === 'all' ? 'No websites yet — add one above or connect a platform.' : 'No websites in this filter.';
    include __DIR__ . '/includes/website-table.php';
    ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
