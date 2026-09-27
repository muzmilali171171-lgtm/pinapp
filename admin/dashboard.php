<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'dashboard';
$pageTitle = 'Dashboard';

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM pinterest_accounts WHERE status = 'connected'")->fetchColumn();
$totalScheduled = $pdo->query("SELECT COUNT(*) FROM scheduled_pins")->fetchColumn();
$totalPublished = $pdo->query("SELECT COUNT(*) FROM scheduled_pins WHERE status = 'published'")->fetchColumn();
$totalFailed = $pdo->query("SELECT COUNT(*) FROM scheduled_pins WHERE status = 'failed'")->fetchColumn();
$totalPending = $pdo->query("SELECT COUNT(*) FROM scheduled_pins WHERE status = 'pending'")->fetchColumn();
$artScheduled = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status IN ('queued','drafting','drafted','imaging','ready','publishing','draft')")->fetchColumn();
$artPublished = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
$artFailed = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'failed'")->fetchColumn();
$websitesConnected = (int)$pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'connected'")->fetchColumn();

$recentUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Dashboard</h1></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= $totalUsers ?></div><div class="label">Total users</div></div>
    <div class="stat-card"><div class="num"><?= $totalAccounts ?></div><div class="label">Connected Pinterest accounts</div></div>
    <div class="stat-card"><div class="num"><?= $totalScheduled ?></div><div class="label">Scheduled pins</div></div>
    <div class="stat-card"><div class="num"><?= $totalPending ?></div><div class="label">Pending</div></div>
    <div class="stat-card"><div class="num"><?= $totalPublished ?></div><div class="label">Published</div></div>
    <div class="stat-card"><div class="num"><?= $totalFailed ?></div><div class="label">Failed</div></div>
    <a class="stat-card" href="articles?status=scheduled" style="text-decoration:none;"><div class="num"><?= $artScheduled ?></div><div class="label">Articles scheduled</div></a>
    <a class="stat-card" href="articles?status=published" style="text-decoration:none;"><div class="num"><?= $artPublished ?></div><div class="label">Articles published</div></a>
    <a class="stat-card" href="articles?status=failed" style="text-decoration:none;"><div class="num"><?= $artFailed ?></div><div class="label">Articles failed</div></a>
    <a class="stat-card" href="websites" style="text-decoration:none;"><div class="num"><?= $websitesConnected ?></div><div class="label">Websites connected</div></a>
</div>

<div class="card">
    <h2>Recently joined users</h2>
    <?php if (empty($recentUsers)): ?>
        <div class="empty-state">No users yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th></tr>
        <?php foreach ($recentUsers as $u): ?>
        <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
            <td><?= format_datetime($u['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
