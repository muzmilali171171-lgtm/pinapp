<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Plan Pricing — Users';

// If migrate.php hasn't been (re)run yet on this database, the queries below will fail —
// show a clear notice instead of a blank 500 error page.
if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Users</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

// Approve / reject a pending custom-payment-method proof.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decide_payment') {
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';
    $message = trim($_POST['admin_message'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM plan_payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if ($payment) {
        $pdo->prepare("UPDATE plan_payments SET status = ?, admin_message = ?, decided_at = NOW() WHERE id = ?")
            ->execute([$decision, $message ?: null, $paymentId]);
        if ($decision === 'approved') {
            activate_plan_for_user($pdo, (int)$payment['user_id'], (int)$payment['plan_id'], $payment['billing_cycle']);
            if ($payment['coupon_id']) {
                $pdo->prepare("INSERT INTO coupon_redemptions (coupon_id, user_id, plan_id) VALUES (?,?,?)")->execute([$payment['coupon_id'], $payment['user_id'], $payment['plan_id']]);
            }
            // Credits the referring affiliate (if any) — covers a first purchase and any
            // later renewal payment the same way, subject to the commission window.
            affiliate_record_commission_for_payment($pdo, $payment);
            create_notification($pdo, (int)$payment['user_id'], 'plan', 'Payment approved', 'Your payment was approved and your plan is now active.', '/user/upgrade');
        } else {
            create_notification($pdo, (int)$payment['user_id'], 'plan', 'Payment could not be verified', $message ?: 'Please contact support or try again.', '/user/upgrade');
        }
        log_event($pdo, 'system', "Admin $decision payment #$paymentId");
    }
    redirect('plan-users');
}

$filterPlan = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$where = $filterPlan ? "WHERE u.plan_id = ?" : "WHERE u.plan_id IS NOT NULL AND p.is_free = 0";
$params = $filterPlan ? [$filterPlan] : [];

$stmt = $pdo->prepare("SELECT u.*, p.name AS plan_name, p.price_monthly, p.is_free
    FROM users u JOIN pricing_plans p ON p.id = u.plan_id
    $where ORDER BY u.plan_started_at DESC");
$stmt->execute($params);
$planUsers = $stmt->fetchAll();

$revenueStmt = $pdo->query("SELECT p.name, COALESCE(SUM(pp.amount),0) AS revenue, COUNT(*) AS payments
    FROM plan_payments pp JOIN pricing_plans p ON p.id = pp.plan_id
    WHERE pp.status IN ('approved','completed') GROUP BY p.id, p.name ORDER BY revenue DESC");
$revenueByPlan = $revenueStmt->fetchAll();
$totalRevenue = array_sum(array_column($revenueByPlan, 'revenue'));

$pendingStmt = $pdo->query("SELECT pp.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name, cpm.name AS custom_method_name
    FROM plan_payments pp
    JOIN users u ON u.id = pp.user_id JOIN pricing_plans p ON p.id = pp.plan_id
    LEFT JOIN custom_payment_methods cpm ON cpm.id = pp.custom_payment_method_id
    WHERE pp.status = 'pending' ORDER BY pp.created_at ASC");
$pendingPayments = $pendingStmt->fetchAll();

$allPlans = get_all_plans($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Users</h1></div>

<div class="tab-row">
    <a href="plans">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways">Payment Gateway Integration</a>
    <a href="coupons">Coupons</a>
    <a href="contact-sales">Contact Sales</a>
    <a href="plan-users" class="active">Users</a>
    <a href="plan-settings">Setting</a>
</div>

<div class="two-col">
    <div class="card"><h2>Total Revenue</h2><div class="stat-card"><div class="num">$<?= number_format($totalRevenue, 2) ?></div></div></div>
    <div class="card">
        <h2>Revenue by Plan</h2>
        <?php if (empty($revenueByPlan)): ?><p class="muted">No approved payments yet.</p><?php else: ?>
        <table><thead><tr><th>Plan</th><th>Payments</th><th>Revenue</th></tr></thead><tbody>
        <?php foreach ($revenueByPlan as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td><?= (int)$r['payments'] ?></td><td>$<?= number_format((float)$r['revenue'], 2) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pendingPayments)): ?>
<div class="card">
    <h2>Pending Payment Approvals (<?= count($pendingPayments) ?>)</h2>
    <table>
        <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Method</th><th>Proof</th><th>Decision</th></tr></thead>
        <tbody>
        <?php foreach ($pendingPayments as $pp): ?>
            <tr>
                <td><?= e($pp['user_name']) ?><br><span class="muted"><?= e($pp['user_email']) ?></span></td>
                <td><?= e($pp['plan_name']) ?> (<?= e($pp['billing_cycle']) ?>)</td>
                <td>$<?= number_format((float)$pp['amount'], 2) ?></td>
                <td><?= e($pp['custom_method_name'] ?: $pp['payment_method']) ?></td>
                <td><?= $pp['proof_screenshot_path'] ? '<a href="../' . e($pp['proof_screenshot_path']) . '" target="_blank">View screenshot</a>' : '—' ?></td>
                <td>
                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="decide_payment">
                        <input type="hidden" name="payment_id" value="<?= (int)$pp['id'] ?>">
                        <input type="text" name="admin_message" placeholder="Optional message" style="width:140px;">
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

<div class="card">
    <h2>Plan Subscribers</h2>
    <form method="GET" style="margin-bottom:12px;">
        <select name="plan" onchange="this.form.submit();">
            <option value="">All paid plans</option>
            <?php foreach ($allPlans as $p): if ($p['is_free']) continue; ?>
                <option value="<?= (int)$p['id'] ?>" <?= $filterPlan === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (empty($planUsers)): ?>
        <p class="muted">No paid plan subscribers yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Plan</th><th>End Date</th><th>Auto Renew</th></tr></thead>
        <tbody>
        <?php foreach ($planUsers as $u): ?>
            <tr>
                <td>#<?= (int)$u['id'] ?></td>
                <td><?= e($u['name']) ?><br><span class="muted"><?= e($u['email']) ?></span></td>
                <td><?= e($u['plan_name']) ?></td>
                <td><?= $u['plan_end_date'] ? e(format_datetime($u['plan_end_date'])) : '—' ?></td>
                <td><span class="badge badge-<?= $u['plan_auto_renew'] ? 'connected' : 'error' ?>"><?= $u['plan_auto_renew'] ? 'On' : 'Off' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
