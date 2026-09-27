<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'email-settings';
$pageTitle = 'Email Setting';

$saved = false;
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        platform_setting_set($pdo, 'email_smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
        platform_setting_set($pdo, 'email_smtp_host', trim($_POST['smtp_host'] ?? ''));
        platform_setting_set($pdo, 'email_smtp_port', (string)max(1, (int)($_POST['smtp_port'] ?? 587)));
        platform_setting_set($pdo, 'email_smtp_username', trim($_POST['smtp_username'] ?? ''));
        $pass = (string)($_POST['smtp_password'] ?? '');
        if ($pass !== '') platform_setting_set($pdo, 'email_smtp_password', $pass);
        platform_setting_set($pdo, 'email_smtp_encryption', in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls');
        platform_setting_set($pdo, 'email_from_name', trim($_POST['from_name'] ?? ''));
        platform_setting_set($pdo, 'email_from_email', trim($_POST['from_email'] ?? ''));
        platform_setting_set($pdo, 'email_mailchimp_api_key', trim($_POST['mailchimp_api_key'] ?? ''));
        platform_setting_set($pdo, 'email_mailchimp_audience_id', trim($_POST['mailchimp_audience_id'] ?? ''));
        platform_setting_set($pdo, 'email_password_reset_enabled', isset($_POST['password_reset_enabled']) ? '1' : '0');
        platform_setting_set($pdo, 'email_verification_enabled', isset($_POST['verification_enabled']) ? '1' : '0');
        platform_setting_set($pdo, 'email_change_notify_enabled', isset($_POST['email_change_notify_enabled']) ? '1' : '0');
        log_event($pdo, 'system', 'Admin updated Email Setting');
        $saved = true;
    }

    if ($action === 'send_test') {
        $admin = current_admin($pdo);
        $to = trim($_POST['test_email'] ?? ($admin['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $testResult = ['ok' => false, 'error' => 'Enter a valid email address to send the test to.'];
        } else {
            $testResult = send_app_email($pdo, $to, 'Test email from ' . APP_NAME,
                '<p>This is a test email from your ' . e(APP_NAME) . ' Email Setting page. If you received this, your SMTP settings are working.</p>');
            $testResult['to'] = $to;
        }
    }
}

$s = email_settings_get($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Email Setting</h1></div>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($testResult): ?>
    <div class="alert alert-<?= $testResult['ok'] ? 'success' : 'error' ?>">
        <?= $testResult['ok'] ? 'Test email sent to ' . e($testResult['to']) . '.' : 'Failed to send: ' . e($testResult['error']) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="save">
    <div class="card">
        <h2>Outgoing Email (SMTP)</h2>
        <p class="muted">Used for password reset, email verification and email-change notices. If SMTP is turned off
        below, the server's built-in PHP <code>mail()</code> is used instead (works on most shared hosting, but is
        more likely to land in spam without SMTP + SPF/DKIM configured).</p>

        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Use SMTP</div>
                <div class="settings-toggle-desc">Turn on to use the server details below instead of PHP mail().</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="smtp_enabled" value="1" <?= $s['smtp_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e($s['smtp_host']) ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-row">
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= (int)$s['smtp_port'] ?>">
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>SMTP Username</label>
                <input type="text" name="smtp_username" value="<?= e($s['smtp_username']) ?>" autocomplete="off">
            </div>
            <div class="form-row">
                <label>SMTP Password</label>
                <input type="password" name="smtp_password" placeholder="<?= $s['smtp_password'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>" autocomplete="off">
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Encryption</label>
                <select name="smtp_encryption">
                    <option value="tls" <?= $s['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS (port 587)</option>
                    <option value="ssl" <?= $s['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
                    <option value="none" <?= $s['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                </select>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>From Name</label>
                <input type="text" name="from_name" value="<?= e($s['from_name']) ?>" placeholder="<?= e(APP_NAME) ?>">
            </div>
            <div class="form-row">
                <label>From Email</label>
                <input type="email" name="from_email" value="<?= e($s['from_email']) ?>" placeholder="no-reply@yourdomain.com">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Which System Emails Are Sent</h2>
        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Password reset email</div>
                <div class="settings-toggle-desc">Users can request a reset link from the Log In page.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="password_reset_enabled" value="1" <?= $s['password_reset_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Account creation email verification</div>
                <div class="settings-toggle-desc">New signups get a "verify your email" link. Off = accounts are considered verified immediately.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="verification_enabled" value="1" <?= $s['verification_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Email-change notice</div>
                <div class="settings-toggle-desc">Notifies a user's OLD email address when they change it in Settings, as a security precaution.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="email_change_notify_enabled" value="1" <?= $s['email_change_notify_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
    </div>

    <div class="card">
        <h2>Mailchimp</h2>
        <p class="muted">Reference fields for future newsletter-signup integrations on your sites (not used for the
        transactional emails above).</p>
        <div class="two-col">
            <div class="form-row">
                <label>Mailchimp API Key</label>
                <input type="text" name="mailchimp_api_key" value="<?= e($s['mailchimp_api_key']) ?>">
            </div>
            <div class="form-row">
                <label>Audience ID</label>
                <input type="text" name="mailchimp_audience_id" value="<?= e($s['mailchimp_audience_id']) ?>">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-primary">Save Email Setting</button>
</form>

<div class="card">
    <h2>Send a Test Email</h2>
    <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="action" value="send_test">
        <div class="form-row" style="flex:1; min-width:220px; margin-bottom:0;">
            <label>Send to</label>
            <input type="email" name="test_email" placeholder="you@example.com">
        </div>
        <button type="submit" class="btn-secondary">Send Test</button>
    </form>
</div>

<div class="tab-row">
    <a href="?guide=hostinger" class="<?= ($_GET['guide'] ?? 'hostinger') === 'hostinger' ? 'active' : '' ?>">Hostinger Email Guide</a>
    <a href="?guide=gmail" class="<?= ($_GET['guide'] ?? '') === 'gmail' ? 'active' : '' ?>">Gmail SMTP Guide</a>
    <a href="?guide=mailchimp" class="<?= ($_GET['guide'] ?? '') === 'mailchimp' ? 'active' : '' ?>">Mailchimp Guide</a>
</div>
<div class="card guide-box">
    <?= email_setup_guide($_GET['guide'] ?? 'hostinger') ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
