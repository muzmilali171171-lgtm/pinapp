<?php
/**
 * Admin login. Served at /admin/login by default, or only at the custom address set in
 * Admin → Account Settings (then /admin/login shows the 404 page). The custom address is
 * routed here from blog.php, which defines ADMIN_LOGIN_VIA_SLUG before including this file.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_security.php';
require_once __DIR__ . '/includes/admin-auth.php';

if (admin_login_slug($pdo) !== '' && !defined('ADMIN_LOGIN_VIA_SLUG')) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}
if (current_admin($pdo)) {
    redirect(rtrim(APP_URL, '/') . '/admin/dashboard');
}

$errors = [];
$step = !empty($_SESSION['admin_2fa_pending']) ? '2fa' : 'password';
$root = rtrim(APP_URL, '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($wait = admin_login_blocked()) > 0) {
        $errors[] = 'Too many attempts. Try again in ' . ceil($wait / 60) . ' minute(s).';
    } elseif (($_POST['step'] ?? '') === 'cancel') {
        unset($_SESSION['admin_2fa_pending']);
        $step = 'password';
    } elseif ($step === '2fa') {
        $pending = $_SESSION['admin_2fa_pending'];
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt->execute([(int)$pending['id']]);
        $admin = $stmt->fetch();
        if (!$admin || time() - $pending['t'] > 300) {
            unset($_SESSION['admin_2fa_pending']);
            $step = 'password';
            $errors[] = 'That sign-in expired. Please log in again.';
        } elseif (!totp_verify((string)$admin['totp_secret'], (string)($_POST['code'] ?? ''))) {
            admin_login_failed();
            $errors[] = 'That code is not correct. Use the current 6-digit code from your authenticator app.';
        } else {
            unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_login_fail']);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            redirect($root . '/admin/dashboard');
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            admin_login_failed();
            $errors[] = 'Invalid username or password.';
        } elseif (!empty($admin['totp_enabled']) && !empty($admin['totp_secret'])) {
            $_SESSION['admin_2fa_pending'] = ['id' => (int)$admin['id'], 't' => time()];
            $step = '2fa';
        } else {
            unset($_SESSION['admin_login_fail']);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            redirect($root . '/admin/dashboard');
        }
    }
}

// Only show the default-credentials hint while the default password is still in use.
$showDefaultHint = false;
try {
    $row = $pdo->query("SELECT password_hash FROM admin_users WHERE username = 'admin' LIMIT 1")->fetch();
    $showDefaultHint = $row && password_verify('1234', $row['password_hash']);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login — <?= e(APP_NAME) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= $root ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<style>
.adm-login { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: radial-gradient(circle at 20% 10%, #ffe1e6 0, transparent 40%), radial-gradient(circle at 90% 90%, #dcfce7 0, transparent 40%), #f7f7f8; }
.adm-card { width: 100%; max-width: 400px; background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 32px; box-shadow: 0 24px 60px rgba(17,24,39,.12); }
.adm-card h1 { margin: 0 0 6px; font-size: 24px; }
.adm-card p.sub { margin: 0 0 20px; color: var(--gray); font-size: 14px; }
.adm-card label { display: block; font-weight: 600; font-size: 13.5px; margin: 12px 0 6px; }
.adm-card input { width: 100%; box-sizing: border-box; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 15px; }
.adm-card .btn-primary { width: 100%; margin-top: 18px; padding: 12px; }
.adm-code { letter-spacing: .5em; text-align: center; font-size: 22px !important; font-weight: 700; }
.adm-lock { width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; background: #fff0f1; border: 2px solid var(--red); margin-bottom: 16px; }
.adm-link { background: none; border: 0; color: var(--gray); text-decoration: underline; cursor: pointer; margin-top: 12px; font-size: 13px; }
</style>
</head>
<body>
<div class="adm-login">
    <div class="adm-card">
        <div class="adm-lock"><?= $step === '2fa' ? '🔐' : '🛡️' ?></div>
        <?php if ($step === '2fa'): ?>
            <h1>Two-factor check</h1>
            <p class="sub">Enter the 6-digit code from your authenticator app.</p>
        <?php else: ?>
            <h1>Admin login</h1>
            <p class="sub"><?= e(APP_NAME) ?> admin panel</p>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

        <?php if ($step === '2fa'): ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="step" value="2fa">
                <label for="code">Authentication code</label>
                <input type="text" id="code" name="code" class="adm-code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
                <button type="submit" class="btn-primary">Verify &amp; log in</button>
            </form>
            <form method="POST"><input type="hidden" name="step" value="cancel"><button type="submit" class="adm-link">Use a different account</button></form>
        <?php else: ?>
            <form method="POST">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
                <button type="submit" class="btn-primary">Log in</button>
            </form>
            <?php if ($showDefaultHint): ?><p class="muted" style="margin-top:16px;font-size:13px;">Default login: admin / 1234 — change it in Account Settings after logging in.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
