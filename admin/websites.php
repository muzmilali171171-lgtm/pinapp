<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'websites';
$pageTitle = 'All Websites';

$tabs = ['all' => 'All', 'wordpress' => 'WordPress', 'shopify' => 'Shopify', 'wix' => 'Wix', 'custom' => 'Custom', 'unconnected' => 'Unconnected'];

// Counts for the filter tabs.
$counts = array_fill_keys(array_keys($tabs), 0);
$rows = $pdo->query("SELECT platform, status, COUNT(*) c FROM websites GROUP BY platform, status")->fetchAll();
foreach ($rows as $r) {
    $counts['all'] += (int)$r['c'];
    if (isset($counts[$r['platform']])) $counts[$r['platform']] += (int)$r['c'];
    if ($r['platform'] === 'none' || $r['status'] !== 'connected') $counts['unconnected'] += (int)$r['c'];
}

$filter = $_GET['platform'] ?? 'all';
if (!isset($tabs[$filter])) $filter = 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($filter === 'unconnected') {
    $where[] = "(w.platform = 'none' OR w.status <> 'connected')";
} elseif ($filter !== 'all') {
    $where[] = "w.platform = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $where[] = "(w.site_url LIKE ? OR w.site_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM websites w LEFT JOIN users u ON u.id = w.user_id $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare("SELECT w.id, w.site_url, w.site_name, w.platform, w.status, w.connected_at, u.name AS user_name, u.email AS user_email
    FROM websites w LEFT JOIN users u ON u.id = w.user_id $whereSql
    ORDER BY w.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$stmt->execute($params);
$sites = $stmt->fetchAll();

$qs = function (array $over = []) use ($filter, $search, $page) {
    return '?' . http_build_query(array_filter(array_merge(['platform' => $filter, 'q' => $search, 'page' => $page], $over), fn($v) => $v !== '' && $v !== null));
};

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>All Websites <span class="muted">(<?= (int)$counts['all'] ?>)</span></h1></div>

<div class="card">
    <div class="site-filter">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e($qs(['platform' => $key, 'page' => 1])) ?>" class="btn-secondary btn-small <?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?> (<?= (int)$counts[$key] ?>)</a>
        <?php endforeach; ?>
    </div>

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px;">
        <input type="hidden" name="platform" value="<?= e($filter) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search URL, site name or user…" style="max-width:340px;">
        <button type="submit" class="btn-secondary btn-small">Search</button>
        <?php if ($search !== ''): ?><a href="<?= e($qs(['q' => '', 'page' => 1])) ?>" class="btn-secondary btn-small">Clear</a><?php endif; ?>
    </form>

    <?php if (empty($sites)): ?>
        <div class="empty-state">No websites found.</div>
    <?php else: ?>
    <table>
        <tr><th>ID</th><th>Website URL</th><th>Platform</th><th>Status</th><th>User</th><th>Added</th></tr>
        <?php foreach ($sites as $s):
            $state = website_connection_state($s);
        ?>
        <tr>
            <td class="muted">#<?= (int)$s['id'] ?></td>
            <td>
                <a href="<?= e($s['site_url']) ?>" target="_blank" rel="noopener"><?= e($s['site_url']) ?></a>
                <?php if (!empty($s['site_name']) && $s['site_name'] !== $s['site_url']): ?><div class="muted" style="font-size:12px;"><?= e($s['site_name']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-platform-<?= e($s['platform']) ?>"><?= e($s['platform'] === 'none' ? 'Not linked' : platform_label($s['platform'])) ?></span></td>
            <td><span class="badge badge-<?= $state === 'connected' ? 'connected' : 'error' ?>"><?= $state === 'connected' ? 'Connected' : 'Unconnected' ?></span></td>
            <td>
                <?= e($s['user_name'] ?: '—') ?>
                <?php if (!empty($s['user_email'])): ?><div class="muted" style="font-size:12px;"><?= e($s['user_email']) ?></div><?php endif; ?>
            </td>
            <td class="muted"><?= format_datetime($s['connected_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($pages > 1): ?>
    <div style="display:flex; gap:8px; align-items:center; margin-top:16px;">
        <?php if ($page > 1): ?><a href="<?= e($qs(['page' => $page - 1])) ?>" class="btn-secondary btn-small">← Previous</a><?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= e($qs(['page' => $page + 1])) ?>" class="btn-secondary btn-small">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
