<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'affiliate';
$pageTitle = 'Affiliate — Payouts';

if (!affiliate_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Affiliate — Payouts</h1></div>
    <div class="card">
        <div class="alert alert-error">The Affiliate Program's database tables/columns aren't set up yet.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decide_payout') {
    $payoutId = (int)($_POST['payout_id'] ?? 0);
    $decision = in_array($_POST['decision'] ?? '', ['approve', 'reject', 'paid'], true) ? $_POST['decision'] : 'reject';
    affiliate_admin_decide_payout($pdo, $payoutId, $decision, trim($_POST['admin_note'] ?? ''));
    log_event($pdo, 'system', "Admin marked affiliate payout #$payoutId as $decision");
    redirect('affiliate-payouts');
}

$pendingPayouts = affiliate_admin_get_payout_requests($pdo, 'pending');
$approvedPayouts = affiliate_admin_get_payout_requests($pdo, 'approved');
$allPayouts = affiliate_admin_get_payout_requests($pdo, null);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Affiliate — Payouts</h1></div>

<div class="tab-row">
    <a href="affiliate-dashboard">Dashboard</a>
    <a href="affiliate-settings">Settings</a>
    <a href="affiliate-payouts" class="active">Payouts</a>
</div>

<?php if (!empty($pendingPayouts)): ?>
<div class="card">
    <h2>Pending Requests (<?= count($pendingPayouts) ?>)</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Amount</th><th>Payment Method</th><th>Decision</th></tr></thead>
        <tbody>
        <?php foreach ($pendingPayouts as $p): ?>
            <tr>
                <td>#<?= (int)$p['id'] ?></td>
                <td><?= e($p['user_name']) ?><br><span class="muted"><?= e($p['user_email']) ?></span>
                    <br><a href="affiliate-view?id=<?= (int)$p['user_id'] ?>" class="muted">View user</a></td>
                <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= e(AFFILIATE_METHOD_LABELS[$p['method']] ?? $p['method']) ?><br><span class="muted"><?= e($p['payment_details']) ?></span></td>
                <td>
                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="decide_payout">
                        <input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>">
                        <input type="text" name="admin_note" placeholder="Optional note" style="width:140px;">
                        <button type="submit" name="decision" value="approve" class="btn-primary btn-small">Approve</button>
                        <button type="submit" name="decision" value="reject" class="btn-danger btn-small">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($approvedPayouts)): ?>
<div class="card">
    <h2>Approved — Awaiting Payment (<?= count($approvedPayouts) ?>)</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Amount</th><th>Payment Method</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($approvedPayouts as $p): ?>
            <tr>
                <td>#<?= (int)$p['id'] ?></td>
                <td><?= e($p['user_name']) ?><br><span class="muted"><?= e($p['user_email']) ?></span></td>
                <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= e(AFFILIATE_METHOD_LABELS[$p['method']] ?? $p['method']) ?><br><span class="muted"><?= e($p['payment_details']) ?></span></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="action" value="decide_payout">
                        <input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" name="decision" value="paid" class="btn-primary btn-small">Mark Paid</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h2>All Payout Requests</h2>
    <?php if (empty($allPayouts)): ?>
        <p class="muted">No payout requests yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Amount Paid</th></tr></thead>
        <tbody>
        <?php foreach ($allPayouts as $p): ?>
            <tr>
                <td>#<?= (int)$p['id'] ?></td>
                <td><?= e($p['user_name']) ?></td>
                <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= e(AFFILIATE_METHOD_LABELS[$p['method']] ?? $p['method']) ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'paid' ? 'active' : ($p['status'] === 'rejected' ? 'error' : 'pending') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td><?= e(format_datetime($p['requested_at'])) ?></td>
                <td><?= $p['status'] === 'paid' ? '$' . number_format((float)$p['amount'], 2) . ' — ' . e(format_datetime($p['paid_at'])) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
