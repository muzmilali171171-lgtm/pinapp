<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'affiliate';
$pageTitle = 'Affiliate — Dashboard';

if (!affiliate_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Affiliate — Dashboard</h1></div>
    <div class="card">
        <div class="alert alert-error">The Affiliate Program's database tables/columns aren't set up yet.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

$overview = affiliate_admin_overview($pdo);
$affiliates = affiliate_admin_list($pdo);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Affiliate — Dashboard</h1></div>

<div class="tab-row">
    <a href="affiliate-dashboard" class="active">Dashboard</a>
    <a href="affiliate-settings">Settings</a>
    <a href="affiliate-payouts">Payouts</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= (int)$overview['total_affiliates'] ?></div><div class="label">Total Affiliate Users</div></div>
    <div class="stat-card"><div class="num"><?= (int)$overview['total_sales'] ?></div><div class="label">Total Sell</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($overview['total_commission'], 2) ?></div><div class="label">Total Commission</div></div>
    <div class="stat-card"><div class="num"><?= (int)$overview['total_customers'] ?></div><div class="label">Total Customers</div></div>
    <div class="stat-card"><div class="num"><?= (int)$overview['total_clicks'] ?></div><div class="label">Total Clicks</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($overview['pending_payouts'], 2) ?></div><div class="label">Pending Payouts</div></div>
</div>

<div class="card">
    <h2>All Affiliates</h2>
    <?php if (empty($affiliates)): ?>
        <p class="muted">No one has generated an affiliate link yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>User</th><th>Link Code</th><th>Clicks</th><th>Referrals</th><th>Customers</th><th>Sales</th><th>Earning</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($affiliates as $a): ?>
            <tr>
                <td><?= e($a['name']) ?><br><span class="muted"><?= e($a['email']) ?></span></td>
                <td><code><?= e($a['affiliate_code']) ?></code></td>
                <td><?= (int)$a['total_clicks'] ?></td>
                <td><?= (int)$a['total_referrals'] ?></td>
                <td><?= (int)$a['total_customers'] ?></td>
                <td><?= (int)$a['total_sales'] ?></td>
                <td>$<?= number_format((float)$a['total_earning'], 2) ?></td>
                <td><?= e(format_datetime($a['joined_at'])) ?></td>
                <td><a href="affiliate-view?id=<?= (int)$a['id'] ?>" class="btn-secondary btn-small">View Analytics</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
