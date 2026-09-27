<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'All Plans';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>All Plans</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

if (isset($_GET['delete'])) {
    delete_plan($pdo, (int)$_GET['delete']);
    redirect('plans');
}
if (isset($_GET['toggle'])) {
    $p = get_plan($pdo, (int)$_GET['toggle']);
    if ($p) {
        $pdo->prepare("UPDATE pricing_plans SET status = ? WHERE id = ?")->execute([$p['status'] === 'active' ? 'inactive' : 'active', $p['id']]);
    }
    redirect('plans');
}

$plans = get_all_plans($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>All Plans</h1><a href="plan-create" class="btn-primary">+ Create Plan</a></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Plan saved.</div><?php endif; ?>

<div class="tab-row">
    <a href="plans" class="active">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways">Payment Gateway Integration</a>
    <a href="coupons">Coupons</a>
    <a href="contact-sales">Contact Sales</a>
    <a href="plan-users">Users</a>
    <a href="plan-settings">Setting</a>
</div>

<div class="card">
    <?php if (empty($plans)): ?>
        <p class="muted">No plans yet — create your first one.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Plan Name</th><th>Price / mo</th><th>Total Users</th><th>Tag</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
            <tr>
                <td><?= e($p['name']) ?> <?= $p['is_free'] ? '<span class="badge badge-connected">Free</span>' : '' ?></td>
                <td>$<?= number_format((float)$p['price_monthly'], 2) ?></td>
                <td><?= plan_total_users($pdo, (int)$p['id']) ?></td>
                <td><?= $p['tag'] ? e(PLAN_TAG_OPTIONS[$p['tag']] ?? $p['tag']) : '—' ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td style="white-space:nowrap;">
                    <a href="plan-create?id=<?= (int)$p['id'] ?>" class="btn-secondary btn-small">Edit</a>
                    <a href="?toggle=<?= (int)$p['id'] ?>" class="btn-secondary btn-small"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></a>
                    <a href="?delete=<?= (int)$p['id'] ?>" class="btn-danger btn-small" onclick="return confirm('Delete this plan? Users on it will be moved to no plan.');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
