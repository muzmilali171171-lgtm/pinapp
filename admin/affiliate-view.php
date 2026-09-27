<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'affiliate';
$pageTitle = 'Affiliate — Analytics';

if (!affiliate_tables_ready($pdo)) {
    redirect('affiliate-dashboard');
}

$affiliateUserId = (int)($_GET['id'] ?? 0);
$affiliate = affiliate_admin_get_affiliate($pdo, $affiliateUserId);
if (!$affiliate) {
    redirect('affiliate-dashboard');
}

$stats = affiliate_get_stats($pdo, $affiliateUserId);
$referrals = affiliate_get_recent_referrals($pdo, $affiliateUserId, 100);
$sales = affiliate_get_sales($pdo, $affiliateUserId, 100);
$payouts = affiliate_get_user_payouts($pdo, $affiliateUserId);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header">
    <h1>Affiliate — <?= e($affiliate['name']) ?></h1>
    <a href="affiliate-dashboard" class="btn-secondary">← Back to Affiliates</a>
</div>

<div class="card">
    <p><strong><?= e($affiliate['name']) ?></strong> — <?= e($affiliate['email']) ?><br>
    Affiliate link: <code><?= e(affiliate_link_url($affiliate['affiliate_code'])) ?></code><br>
    Joined: <?= e(format_datetime($affiliate['created_at'])) ?></p>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="num">$<?= number_format($stats['total_earning'], 2) ?></div><div class="label">Total Earning</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($stats['total_paid'], 2) ?></div><div class="label">Total Paid</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($stats['to_pay'], 2) ?></div><div class="label">Balance</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_clicks'] ?></div><div class="label">Total Clicks</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_referrals'] ?></div><div class="label">Total Referrals</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_customers'] ?></div><div class="label">Total Customers</div></div>
</div>

<div class="card">
    <h2>Referrals</h2>
    <?php if (empty($referrals)): ?>
        <p class="muted">No referrals yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($referrals as $r): ?>
            <tr>
                <td><?= e(format_datetime($r['created_at'])) ?></td>
                <td><?= e($r['name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
                <td><span class="badge badge-<?= $r['status'] === 'customer' ? 'active' : 'pending' ?>"><?= $r['status'] === 'customer' ? 'Customer' : 'Signed up' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Sales / Commissions</h2>
    <?php if (empty($sales)): ?>
        <p class="muted">No commissioned sales yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Plan</th><th>Revenue</th><th>Earn %</th><th>Earnings</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <tr>
                <td><?= e(format_datetime($s['created_at'])) ?></td>
                <td><?= e($s['name']) ?><br><span class="muted"><?= e($s['email']) ?></span></td>
                <td><?= e($s['plan_name'] ?? '—') ?></td>
                <td>$<?= number_format((float)$s['revenue_amount'], 2) ?></td>
                <td><?= number_format((float)$s['commission_percent'], 2) ?>%</td>
                <td>$<?= number_format((float)$s['commission_amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Payout Requests</h2>
    <?php if (empty($payouts)): ?>
        <p class="muted">No payout requests yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payouts as $p): ?>
            <tr>
                <td><?= e(format_datetime($p['requested_at'])) ?></td>
                <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= e(AFFILIATE_METHOD_LABELS[$p['method']] ?? $p['method']) ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'paid' ? 'active' : ($p['status'] === 'rejected' ? 'error' : 'pending') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
