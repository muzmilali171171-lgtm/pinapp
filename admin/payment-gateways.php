<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Payment Gateway Integration';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Payment Gateway Integration</h1></div>
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
    $action = $_POST['action'] ?? 'save_gateways';

    if ($action === 'save_gateways') {
        platform_setting_set($pdo, 'pg_stripe_enabled', isset($_POST['stripe_enabled']) ? '1' : '0');
        platform_setting_set($pdo, 'pg_stripe_publishable_key', trim($_POST['stripe_publishable_key'] ?? ''));
        if (trim($_POST['stripe_secret_key'] ?? '') !== '') platform_setting_set($pdo, 'pg_stripe_secret_key', trim($_POST['stripe_secret_key']));
        if (trim($_POST['stripe_webhook_secret'] ?? '') !== '') platform_setting_set($pdo, 'pg_stripe_webhook_secret', trim($_POST['stripe_webhook_secret']));

        platform_setting_set($pdo, 'pg_paypal_enabled', isset($_POST['paypal_enabled']) ? '1' : '0');
        platform_setting_set($pdo, 'pg_paypal_client_id', trim($_POST['paypal_client_id'] ?? ''));
        if (trim($_POST['paypal_secret'] ?? '') !== '') platform_setting_set($pdo, 'pg_paypal_secret', trim($_POST['paypal_secret']));
        platform_setting_set($pdo, 'pg_paypal_mode', ($_POST['paypal_mode'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live');

        platform_setting_set($pdo, 'pg_nowpayments_enabled', isset($_POST['nowpayments_enabled']) ? '1' : '0');
        if (trim($_POST['nowpayments_api_key'] ?? '') !== '') platform_setting_set($pdo, 'pg_nowpayments_api_key', trim($_POST['nowpayments_api_key']));
        if (trim($_POST['nowpayments_ipn_secret'] ?? '') !== '') platform_setting_set($pdo, 'pg_nowpayments_ipn_secret', trim($_POST['nowpayments_ipn_secret']));

        platform_setting_set($pdo, 'pg_binance_enabled', isset($_POST['binance_enabled']) ? '1' : '0');
        if (trim($_POST['binance_api_key'] ?? '') !== '') platform_setting_set($pdo, 'pg_binance_api_key', trim($_POST['binance_api_key']));
        if (trim($_POST['binance_secret_key'] ?? '') !== '') platform_setting_set($pdo, 'pg_binance_secret_key', trim($_POST['binance_secret_key']));

        log_event($pdo, 'system', 'Admin updated Payment Gateway settings');
        $saved = true;
    }

    if ($action === 'save_custom_method') {
        $id = !empty($_POST['method_id']) ? (int)$_POST['method_id'] : null;
        save_custom_payment_method($pdo, $id, trim($_POST['method_name'] ?? ''), $_POST['method_details'] ?? '', !empty($_POST['method_enabled']));
        redirect('payment-gateways?saved=1');
    }

    if ($action === 'delete_custom_method') {
        $pdo->prepare("DELETE FROM custom_payment_methods WHERE id = ?")->execute([(int)($_POST['method_id'] ?? 0)]);
        redirect('payment-gateways?saved=1');
    }
}

$s = payment_gateway_settings_get($pdo);
$customMethods = get_custom_payment_methods($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Payment Gateway Integration</h1></div>
<?php if ($saved || isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="tab-row">
    <a href="plans">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways" class="active">Payment Gateway Integration</a>
    <a href="coupons">Coupons</a>
    <a href="contact-sales">Contact Sales</a>
    <a href="plan-users">Users</a>
    <a href="plan-settings">Setting</a>
</div>

<form method="POST">
    <input type="hidden" name="action" value="save_gateways">

    <div class="card">
        <div class="settings-toggle-row" style="border:none; padding-top:0;">
            <h2 style="margin:0;">Stripe</h2>
            <label class="toggle-pill"><input type="checkbox" name="stripe_enabled" value="1" <?= $s['stripe_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="guide-box"><?= payment_gateway_guide('stripe') ?></div>
        <div class="two-col">
            <div class="form-row"><label>Publishable key</label><input type="text" name="stripe_publishable_key" value="<?= e($s['stripe_publishable_key']) ?>" placeholder="pk_live_..."></div>
            <div class="form-row"><label>Secret key</label><input type="password" name="stripe_secret_key" placeholder="<?= $s['stripe_secret_key'] !== '' ? '•••••••• saved — leave blank to keep' : 'sk_live_...' ?>"></div>
        </div>
        <div class="form-row"><label>Webhook signing secret</label><input type="password" name="stripe_webhook_secret" placeholder="<?= $s['stripe_webhook_secret'] !== '' ? '•••••••• saved — leave blank to keep' : 'whsec_...' ?>"></div>
    </div>

    <div class="card">
        <div class="settings-toggle-row" style="border:none; padding-top:0;">
            <h2 style="margin:0;">PayPal</h2>
            <label class="toggle-pill"><input type="checkbox" name="paypal_enabled" value="1" <?= $s['paypal_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="guide-box"><?= payment_gateway_guide('paypal') ?></div>
        <div class="two-col">
            <div class="form-row"><label>Client ID</label><input type="text" name="paypal_client_id" value="<?= e($s['paypal_client_id']) ?>"></div>
            <div class="form-row"><label>Secret</label><input type="password" name="paypal_secret" placeholder="<?= $s['paypal_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>"></div>
        </div>
        <div class="form-row">
            <label>Mode</label>
            <select name="paypal_mode">
                <option value="live" <?= $s['paypal_mode'] === 'live' ? 'selected' : '' ?>>Live</option>
                <option value="sandbox" <?= $s['paypal_mode'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (testing)</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="settings-toggle-row" style="border:none; padding-top:0;">
            <h2 style="margin:0;">NOWPayments</h2>
            <label class="toggle-pill"><input type="checkbox" name="nowpayments_enabled" value="1" <?= $s['nowpayments_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="guide-box"><?= payment_gateway_guide('nowpayments') ?></div>
        <div class="two-col">
            <div class="form-row"><label>API key</label><input type="password" name="nowpayments_api_key" placeholder="<?= $s['nowpayments_api_key'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>"></div>
            <div class="form-row"><label>IPN secret</label><input type="password" name="nowpayments_ipn_secret" placeholder="<?= $s['nowpayments_ipn_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>"></div>
        </div>
    </div>

    <div class="card">
        <div class="settings-toggle-row" style="border:none; padding-top:0;">
            <h2 style="margin:0;">Binance Pay</h2>
            <label class="toggle-pill"><input type="checkbox" name="binance_enabled" value="1" <?= $s['binance_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="guide-box"><?= payment_gateway_guide('binance') ?></div>
        <div class="two-col">
            <div class="form-row"><label>API key</label><input type="password" name="binance_api_key" placeholder="<?= $s['binance_api_key'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>"></div>
            <div class="form-row"><label>Secret key</label><input type="password" name="binance_secret_key" placeholder="<?= $s['binance_secret_key'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>"></div>
        </div>
    </div>

    <button type="submit" class="btn-primary">Save Payment Gateways</button>
</form>

<div class="card">
    <h2>Custom Payment Methods</h2>
    <p class="muted">Bank transfer, Easypaisa, JazzCash, or anything else you accept manually. A user picks one at
    checkout, follows the details you write here, and uploads a payment screenshot — you approve or reject it from
    Plan Pricing → Users.</p>

    <?php foreach ($customMethods as $m): ?>
        <div class="team-member-row">
            <div>
                <div class="team-member-name"><?= e($m['name']) ?></div>
                <div class="team-member-email"><?= $m['enabled'] ? '<span class="badge badge-connected">On</span>' : '<span class="badge badge-error">Off</span>' ?></div>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn-secondary btn-small" onclick="editMethod(<?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete this payment method?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete_custom_method">
                    <input type="hidden" name="method_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <h3 style="margin-top:20px;" id="method-form-title">Add a Custom Payment Method</h3>
    <form method="POST" id="custom-method-form">
        <input type="hidden" name="action" value="save_custom_method">
        <input type="hidden" name="method_id" id="method_id" value="">
        <div class="form-row">
            <label>Method name</label>
            <input type="text" name="method_name" id="method_name" placeholder="e.g. Bank Transfer, Easypaisa" required>
        </div>
        <div class="form-row">
            <label>Payment details (shown to the user at checkout)</label>
            <div class="rte-toolbar" style="margin-bottom:4px;">
                <button type="button" onclick="document.execCommand('bold')"><b>B</b></button>
                <button type="button" onclick="document.execCommand('italic')"><i>I</i></button>
                <button type="button" onclick="document.execCommand('underline')"><u>U</u></button>
                <button type="button" onclick="document.execCommand('insertUnorderedList')">• List</button>
                <button type="button" onclick="var u=prompt('Link URL:'); if(u) document.execCommand('createLink', false, u);">Link</button>
            </div>
            <div id="method_details_editable" contenteditable="true" class="rte-editable" oninput="document.getElementById('method_details').value=this.innerHTML;"></div>
            <textarea name="method_details" id="method_details" style="display:none;"></textarea>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="method_enabled" id="method_enabled" value="1" checked> Enabled</label>
        <button type="submit" class="btn-primary" style="margin-top:10px;">Save Payment Method</button>
        <button type="button" class="btn-secondary" onclick="resetMethodForm();">+ New Method Instead</button>
    </form>
</div>

<style>
.rte-toolbar button { border: 1px solid var(--border); background: #fff; border-radius: 6px; padding: 4px 10px; margin-right:4px; cursor:pointer; font-size:13px; }
.rte-editable { border: 1px solid var(--border); border-radius: 8px; min-height: 100px; padding: 10px 12px; font-size: 14px; }
[data-theme="dark"] .rte-editable { background:#17181c; border-color:#33353c; }
</style>
<script>
function editMethod(id, data) {
    document.getElementById('method-form-title').textContent = 'Edit Payment Method';
    document.getElementById('method_id').value = id;
    document.getElementById('method_name').value = data.name;
    document.getElementById('method_details_editable').innerHTML = data.details_html || '';
    document.getElementById('method_details').value = data.details_html || '';
    document.getElementById('method_enabled').checked = data.enabled == 1;
    document.getElementById('custom-method-form').scrollIntoView({behavior:'smooth'});
}
function resetMethodForm() {
    document.getElementById('method-form-title').textContent = 'Add a Custom Payment Method';
    document.getElementById('method_id').value = '';
    document.getElementById('method_name').value = '';
    document.getElementById('method_details_editable').innerHTML = '';
    document.getElementById('method_details').value = '';
    document.getElementById('method_enabled').checked = true;
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
