<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_security.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'account-settings';
$pageTitle = 'Account Settings';
$admin = current_admin($pdo);
$ok = [];
$err = [];

if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentOk = password_verify((string)($_POST['current_password'] ?? ''), $admin['password_hash']);
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $err[] = 'Your session expired. Please try again.';
    } elseif ($action !== 'totp_start' && !$currentOk) {
        $err[] = 'Your current password is not correct.';
    } elseif ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) $err[] = 'Username: 3–50 letters, numbers, dots, dashes or underscores.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid login email.';
        if (!$err) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE (username = ? OR email = ?) AND id <> ?");
            $s->execute([$username, $email, $admin['id']]);
            if ((int)$s->fetchColumn() > 0) $err[] = 'Another admin already uses that username or email.';
        }
        if (!$err) {
            $pdo->prepare("UPDATE admin_users SET name = ?, username = ?, email = ? WHERE id = ?")->execute([mb_substr($name, 0, 150) ?: null, $username, $email, $admin['id']]);
            $ok[] = 'Profile saved. Use your new username or email next time you log in.';
        }
    } elseif ($action === 'password') {
        $new = (string)($_POST['new_password'] ?? '');
        if (strlen($new) < 8) $err[] = 'The new password must be at least 8 characters.';
        elseif ($new !== (string)($_POST['confirm_password'] ?? '')) $err[] = 'The two new passwords don’t match.';
        elseif ($new === '1234') $err[] = 'Choose a password other than the default.';
        else {
            $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            $ok[] = 'Password changed.';
        }
    } elseif ($action === 'totp_enable') {
        $secret = $_SESSION['admin_totp_setup'] ?? '';
        if (!$secret) $err[] = 'Start the setup again.';
        elseif (!totp_verify($secret, (string)($_POST['code'] ?? ''))) $err[] = 'That code is not correct. Check the time on your phone and try the newest code.';
        else {
            $pdo->prepare("UPDATE admin_users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?")->execute([$secret, $admin['id']]);
            unset($_SESSION['admin_totp_setup']);
            $ok[] = 'Two-factor authentication is on. You’ll be asked for a code at every login.';
        }
    } elseif ($action === 'totp_disable') {
        if (!totp_verify((string)$admin['totp_secret'], (string)($_POST['code'] ?? ''))) $err[] = 'Enter a current code from your authenticator app to turn two-factor off.';
        else {
            $pdo->prepare("UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")->execute([$admin['id']]);
            $ok[] = 'Two-factor authentication is off.';
        }
    } elseif ($action === 'slug') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? ''), " /"));
        if (!empty($_POST['reset'])) $slug = '';
        if ($problem = admin_login_slug_problem($pdo, $slug)) $err[] = $problem;
        else {
            site_setting_set($pdo, 'admin_login_slug', $slug !== '' ? $slug : null);
            $ok[] = $slug !== '' ? 'Admin login address changed. Bookmark it now — /admin/login no longer works.' : 'Admin login address reset to /admin/login.';
        }
    }
    $admin = current_admin($pdo);
}

// Starting 2FA setup: create a secret held in the session until a code confirms it.
if (($_POST['action'] ?? '') === 'totp_start' || (!empty($_SESSION['admin_totp_setup']) && empty($admin['totp_enabled']))) {
    if (empty($_SESSION['admin_totp_setup'])) $_SESSION['admin_totp_setup'] = totp_new_secret();
}
$setupSecret = empty($admin['totp_enabled']) ? ($_SESSION['admin_totp_setup'] ?? '') : '';
$loginUrl = admin_login_url($pdo);
$slug = admin_login_slug($pdo);

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.as-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
.as-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 22px; }
.as-card h2 { font-size: 18px; margin: 0 0 4px; }
.as-card .muted { margin-top: 0; }
.as-card .form-row { margin-bottom: 12px; }
.as-status { display: inline-block; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; }
.as-on { background: #dcfce7; color: #15803d; }
.as-off { background: #fee2e2; color: #b91c1c; }
.as-url { display: flex; align-items: center; gap: 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.as-url span { background: var(--light); padding: 10px 12px; font-size: 13px; color: var(--gray); white-space: nowrap; }
.as-url input { border: 0 !important; border-radius: 0 !important; }
.as-qr { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; margin: 12px 0; }
.as-qr #qrBox { background: #fff; padding: 8px; border: 1px solid var(--border); border-radius: 10px; }
.as-key { font-family: monospace; font-size: 15px; letter-spacing: .08em; background: var(--light); padding: 8px 10px; border-radius: 8px; word-break: break-all; }
.as-code { letter-spacing: .4em; font-size: 20px; text-align: center; font-weight: 700; max-width: 200px; }
@media (max-width: 900px) { .as-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header"><h1>Account Settings</h1></div>
<?php foreach ($ok as $m): ?><div class="alert alert-success"><?= e($m) ?></div><?php endforeach; ?>
<?php foreach ($err as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>

<div class="as-grid">
    <div class="as-card">
        <h2>Profile &amp; login</h2>
        <p class="muted">Your name, the username and the email you log in with.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="profile">
            <div class="form-row"><label>Name</label><input type="text" name="name" value="<?= e($admin['name'] ?? '') ?>" maxlength="150" placeholder="Your name"></div>
            <div class="form-row"><label>Username</label><input type="text" name="username" value="<?= e($admin['username']) ?>" required></div>
            <div class="form-row"><label>Admin login email</label><input type="email" name="email" value="<?= e($admin['email']) ?>" required></div>
            <div class="form-row"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
            <button class="btn-primary">Save profile</button>
        </form>
    </div>

    <div class="as-card">
        <h2>New password</h2>
        <p class="muted">At least 8 characters.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="password">
            <div class="form-row"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="form-row"><label>New password</label><input type="password" name="new_password" minlength="8" autocomplete="new-password" required></div>
            <div class="form-row"><label>Confirm new password</label><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
            <button class="btn-primary">Change password</button>
        </form>
    </div>

    <div class="as-card">
        <h2>Two-factor authentication <span class="as-status <?= !empty($admin['totp_enabled']) ? 'as-on' : 'as-off' ?>"><?= !empty($admin['totp_enabled']) ? 'ON' : 'OFF' ?></span></h2>
        <p class="muted">Ask for a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…) after your password.</p>
        <?php if (!empty($admin['totp_enabled'])): ?>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="totp_disable">
                <div class="form-row"><label>Current password</label><input type="password" name="current_password" required></div>
                <div class="form-row"><label>Code from your app</label><input type="text" name="code" class="as-code" inputmode="numeric" maxlength="7" required></div>
                <button class="btn-danger">Turn off two-factor</button>
            </form>
        <?php elseif ($setupSecret): ?>
            <p><b>1.</b> Scan this QR code with your authenticator app, or type the key.</p>
            <div class="as-qr">
                <div id="qrBox" aria-label="QR code for your authenticator app"></div>
                <div><div class="muted" style="font-size:12px;">Setup key</div><div class="as-key"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></div></div>
            </div>
            <p><b>2.</b> Enter the 6-digit code the app shows.</p>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="totp_enable">
                <div class="form-row"><label>Current password</label><input type="password" name="current_password" required></div>
                <div class="form-row"><label>Code from your app</label><input type="text" name="code" class="as-code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required></div>
                <button class="btn-primary">Turn on two-factor</button>
            </form>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>new QRCode(document.getElementById('qrBox'), { text: <?= json_encode(totp_uri($setupSecret, $admin['email'] ?: $admin['username'])) ?>, width: 168, height: 168 });</script>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="totp_start">
                <button class="btn-primary">Set up two-factor</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="as-card">
        <h2>Admin login address</h2>
        <p class="muted">Move the login page away from the default <code>/admin/login</code>. Once changed, the old address shows a 404 page.</p>
        <p>Current login page: <a href="<?= e($loginUrl) ?>"><?= e($loginUrl) ?></a></p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="slug">
            <div class="form-row"><label>New address</label>
                <div class="as-url"><span><?= e(rtrim(APP_URL, '/')) ?>/</span><input type="text" name="slug" value="<?= e($slug) ?>" placeholder="e.g. team-portal-7k2" pattern="[a-z0-9-]{4,40}"></div>
                <p class="muted" style="font-size:12px;margin:6px 0 0;">4–40 lowercase letters, numbers and dashes. Pick something hard to guess.</p>
            </div>
            <div class="form-row"><label>Current password</label><input type="password" name="current_password" required></div>
            <button class="btn-primary">Save login address</button>
            <?php if ($slug !== ''): ?><button class="btn-secondary" name="reset" value="1" style="margin-left:8px;">Reset to /admin/login</button><?php endif; ?>
        </form>
        <p class="muted" style="font-size:12px;margin-top:14px;">Forgot your custom address? Remove the <code>admin_login_slug</code> row from the <code>site_settings</code> table in your database, and <code>/admin/login</code> works again.</p>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
