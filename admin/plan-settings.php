<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Plan Pricing Setting';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Plan Pricing Setting</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_setting_set($pdo, 'pricing_image_quality_low_multiplier', (string)max(0, (float)($_POST['image_quality_low'] ?? 0.2)));
    platform_setting_set($pdo, 'pricing_image_quality_medium_multiplier', (string)max(0, (float)($_POST['image_quality_medium'] ?? 0.7)));
    platform_setting_set($pdo, 'pricing_image_quality_high_multiplier', (string)max(0, (float)($_POST['image_quality_high'] ?? 1)));
    platform_setting_set($pdo, 'pricing_text_credit_per_call', (string)max(0, (float)($_POST['text_credit_per_call'] ?? 1)));
    platform_setting_set($pdo, 'pricing_renewal_reminder_days_before', (string)max(1, (int)($_POST['renewal_reminder_days_before'] ?? 7)));
    log_event($pdo, 'system', 'Admin updated Plan Pricing credit settings');
    $saved = true;
}

$s = credit_pricing_get($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Plan Pricing Setting</h1></div>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="tab-row">
    <a href="plans">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways">Payment Gateway Integration</a>
    <a href="coupons">Coupons</a>
    <a href="contact-sales">Contact Sales</a>
    <a href="plan-users">Users</a>
    <a href="plan-settings" class="active">Setting</a>
</div>

<form method="POST">
    <div class="card">
        <h2>Image AI Credit Cost (by quality)</h2>
        <p class="muted">How many credits one generated image costs, by the quality tier the user picks. E.g. a
        "Low" quality image at 0.2 costs 1/5th of a credit; "High" at 1 costs a full credit.</p>
        <div class="two-col">
            <div class="form-row"><label>Low quality multiplier</label><input type="number" step="0.01" name="image_quality_low" value="<?= e($s['image_quality_low']) ?>"></div>
            <div class="form-row"><label>Medium quality multiplier</label><input type="number" step="0.01" name="image_quality_medium" value="<?= e($s['image_quality_medium']) ?>"></div>
        </div>
        <div class="form-row"><label>High quality multiplier</label><input type="number" step="0.01" name="image_quality_high" value="<?= e($s['image_quality_high']) ?>"></div>
    </div>

    <div class="card">
        <h2>Text AI Credit Cost</h2>
        <div class="form-row"><label>Credits per text generation call</label><input type="number" step="0.01" name="text_credit_per_call" value="<?= e($s['text_credit_per_call']) ?>"></div>
    </div>

    <div class="card">
        <h2>Renewal Reminders</h2>
        <p class="muted">A daily reminder notification is sent automatically to users whose plan is about to expire.</p>
        <div class="form-row"><label>Days before end date to start reminding</label><input type="number" min="1" name="renewal_reminder_days_before" value="<?= (int)$s['renewal_reminder_days_before'] ?>"></div>
    </div>

    <button type="submit" class="btn-primary">Save Setting</button>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
