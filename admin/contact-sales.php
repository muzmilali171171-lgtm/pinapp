<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Contact Sales';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Contact Sales</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_contact_sales') {
    platform_setting_set($pdo, 'pricing_contact_sales_enabled', isset($_POST['enabled']) ? '1' : '0');
    redirect('contact-sales?saved=1');
}

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null;
if ($viewId) {
    $pdo->prepare("UPDATE contact_sales_submissions SET viewed = 1 WHERE id = ?")->execute([$viewId]);
}
$stmt = $pdo->prepare("SELECT * FROM contact_sales_submissions WHERE id = ?");
$viewRow = null;
if ($viewId) {
    $stmt->execute([$viewId]);
    $viewRow = $stmt->fetch();
}

$submissions = get_contact_sales_submissions($pdo);
$pricing = credit_pricing_get($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Contact Sales</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="tab-row">
    <a href="plans">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways">Payment Gateway Integration</a>
    <a href="coupons">Coupons</a>
    <a href="contact-sales" class="active">Contact Sales</a>
    <a href="plan-users">Users</a>
    <a href="plan-settings">Setting</a>
</div>

<div class="card">
    <div class="settings-toggle-row" style="border:none; padding-top:0;">
        <div>
            <div class="settings-toggle-label">Contact Sales on the Upgrade page</div>
            <div class="settings-toggle-desc">Shows an attractive "Need custom limits?" call-to-action + button on the pricing page, letting a visitor request custom limits/AI credits at a budget they specify.</div>
        </div>
        <form method="POST" onchange="this.submit();">
            <input type="hidden" name="action" value="toggle_contact_sales">
            <label class="toggle-pill"><input type="checkbox" name="enabled" value="1" <?= $pricing['contact_sales_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </form>
    </div>
</div>

<?php if ($viewRow): ?>
<div class="card">
    <h2>Submission #<?= (int)$viewRow['id'] ?></h2>
    <p><strong>Name:</strong> <?= e($viewRow['name']) ?></p>
    <p><strong>WhatsApp:</strong> <?= e($viewRow['whatsapp'] ?: '—') ?></p>
    <p><strong>Email:</strong> <?= e($viewRow['email']) ?></p>
    <p><strong>Budget:</strong> <?= e($viewRow['budget'] ?: '—') ?></p>
    <p><strong>Message:</strong></p>
    <p style="white-space:pre-wrap;"><?= e($viewRow['message']) ?></p>
    <p class="muted">Submitted <?= e(format_datetime($viewRow['created_at'])) ?></p>
    <a href="contact-sales" class="btn-secondary">← Back to list</a>
</div>
<?php endif; ?>

<div class="card">
    <h2>All Submissions</h2>
    <?php if (empty($submissions)): ?>
        <p class="muted">No contact-sales requests yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $s): ?>
            <tr>
                <td>#<?= (int)$s['id'] ?></td>
                <td><?= e($s['name']) ?> <?= !$s['viewed'] ? '<span class="badge badge-error">New</span>' : '' ?></td>
                <td><?= e($s['email']) ?></td>
                <td><?= e(format_datetime($s['created_at'])) ?></td>
                <td><a href="?view=<?= (int)$s['id'] ?>" class="btn-secondary btn-small">View Detail</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
