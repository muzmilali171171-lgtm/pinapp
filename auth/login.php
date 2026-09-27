<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/oauth_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';
require_once __DIR__ . '/../includes/auth_layout.php';

if (current_user($pdo)) {
    redirect(rtrim(APP_URL, '/') . '/user/dashboard');
}

$authSettings = auth_settings_get($pdo);
$errors = [];

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['oauth_error'])) {
    $errors[] = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } elseif ($user['status'] === 'suspended') {
        $errors[] = 'This account has been suspended.';
    } else {
        $_SESSION['user_id'] = $user['id'];
        redirect(rtrim(APP_URL, '/') . '/user/dashboard');
    }
}
auth_layout_start($pdo, 'Log in — ' . SITE_BRAND, 'login');
?>
<h1>Welcome <span>back</span> 👋</h1>
<p class="au-lead">Log in to see your scheduled pins, drafts and traffic.</p>
<?php foreach ($errors as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

<?php auth_social_buttons($pdo, $authSettings); ?>
<?php if ($authSettings['google_enabled'] || $authSettings['facebook_enabled'] || $authSettings['microsoft_enabled'] || $authSettings['pinterest_login_enabled']): ?><div class="au-or">or log in with email</div><?php endif; ?>

<form method="POST" class="au-form">
    <div class="au-field">
        <label for="au-email">Email</label>
        <div class="au-input"><span class="au-ic" aria-hidden="true">✉️</span><input type="email" id="au-email" name="email" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" placeholder="you@example.com" required autofocus></div>
    </div>
    <div class="au-field">
        <label for="au-password">Password <a href="forgot-password">Forgot password?</a></label>
        <div class="au-input"><span class="au-ic" aria-hidden="true">🔒</span><input type="password" id="au-password" name="password" autocomplete="current-password" placeholder="Your password" required><button type="button" class="au-pw-toggle" data-toggle-pw="au-password" aria-pressed="false">Show</button></div>
    </div>
    <button type="submit" class="au-submit">Log in</button>
</form>
<div class="au-switch">New to <?= e(SITE_BRAND) ?>? <a href="register">Create a free account →</a></div>
<?php auth_layout_end('login'); ?>
