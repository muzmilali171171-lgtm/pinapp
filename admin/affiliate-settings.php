<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'affiliate';
$pageTitle = 'Affiliate — Settings';

if (!affiliate_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Affiliate — Settings</h1></div>
    <div class="card">
        <div class="alert alert-error">The Affiliate Program's database tables/columns aren't set up yet.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    affiliate_settings_save($pdo, $_POST);
    $saved = true;
}

$settings = affiliate_settings_get($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Affiliate — Settings</h1></div>

<div class="tab-row">
    <a href="affiliate-dashboard">Dashboard</a>
    <a href="affiliate-settings" class="active">Settings</a>
    <a href="affiliate-payouts">Payouts</a>
</div>

<?php if ($saved): ?><div class="alert alert-success">Affiliate settings saved.</div><?php endif; ?>

<form method="POST">
    <div class="card">
        <h2>Program</h2>
        <label class="checkbox-row"><input type="checkbox" name="program_enabled" <?= $settings['program_enabled'] ? 'checked' : '' ?>> Affiliate program is active (users can see their affiliate link and earn commissions)</label>
    </div>

    <div class="card">
        <h2>Commission</h2>
        <div class="form-row">
            <label>Commission percentage</label>
            <input type="number" step="0.01" min="0" max="100" name="commission_percent" value="<?= e((string)$settings['commission_percent']) ?>" style="max-width:160px;">
        </div>
        <div class="form-row">
            <label>Commission duration</label>
            <select name="duration_type" id="durationType" onchange="document.getElementById('durationMonthsRow').style.display = this.value === 'custom' ? 'block' : 'none';">
                <option value="lifetime" <?= $settings['duration_type'] === 'lifetime' ? 'selected' : '' ?>>Lifetime — every future payment earns commission</option>
                <option value="custom" <?= $settings['duration_type'] === 'custom' ? 'selected' : '' ?>>Custom — only payments within a set number of months from signup</option>
            </select>
        </div>
        <div class="form-row" id="durationMonthsRow" style="display:<?= $settings['duration_type'] === 'custom' ? 'block' : 'none' ?>;">
            <label>Duration in months</label>
            <input type="number" min="1" name="duration_months" value="<?= e((string)($settings['duration_months'] ?? 1)) ?>" style="max-width:160px;">
            <p class="muted">e.g. 1 for one month, 12 for one year, 24 for two years, 36 for three years — applies to renewal
            payments too, counted from the referred user's own signup date.</p>
        </div>
    </div>

    <div class="card">
        <h2>Payouts</h2>
        <div class="form-row">
            <label>Minimum payout threshold ($)</label>
            <input type="number" step="0.01" min="0" name="min_payout_threshold" value="<?= e((string)$settings['min_payout_threshold']) ?>" style="max-width:160px;">
        </div>
        <div class="form-row">
            <label>Referral cookie window (days)</label>
            <input type="number" min="1" name="cookie_days" value="<?= e((string)$settings['cookie_days']) ?>" style="max-width:160px;">
            <p class="muted">How long a visitor's affiliate link "remembers" them before they sign up.</p>
        </div>
        <h3>Payout methods offered to affiliates</h3>
        <label class="checkbox-row"><input type="checkbox" name="paypal_enabled" <?= $settings['paypal_enabled'] ? 'checked' : '' ?>> PayPal</label>
        <label class="checkbox-row"><input type="checkbox" name="crypto_enabled" <?= $settings['crypto_enabled'] ? 'checked' : '' ?>> Crypto Wallet (USDT · TRC20)</label>
        <label class="checkbox-row"><input type="checkbox" name="binance_enabled" <?= $settings['binance_enabled'] ? 'checked' : '' ?>> Binance Pay</label>
    </div>

    <button type="submit" class="btn-primary">Save Settings</button>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
