<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $settings = email_settings_get($pdo);

    if (!$settings['password_reset_enabled']) {
        $errors[] = 'Password reset by email is not available right now. Please contact support to regain access.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO user_tokens (user_id, type, token, expires_at) VALUES (?, 'password_reset', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                ->execute([$user['id'], $token]);
            $link = rtrim(APP_URL, '/') . '/auth/reset-password?token=' . $token;
            send_app_email($pdo, $email, 'Reset your password',
                "<p>Hi " . e($user['name']) . ",</p><p>Click the link below to reset your password. This link expires in 1 hour.</p>"
                . "<p><a href=\"" . e($link) . "\">Reset your password</a></p><p>If you didn't request this, you can ignore this email.</p>");
        }
        // Always show the same success message whether or not the email exists, to avoid leaking which emails are registered.
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php seo_render_head($pdo, ['title' => 'Forgot password — ' . SITE_BRAND, 'description' => '', 'noindex' => true, 'schema' => false]); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Forgot your password?</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($sent): ?>
        <div class="alert alert-success">If an account exists for that email, a password-reset link has been sent.</div>
    <?php else: ?>
        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            <button type="submit" class="btn-primary">Send Reset Link</button>
        </form>
    <?php endif; ?>
    <div class="switch"><a href="login">Back to log in</a></div>
</div>
</body>
</html>
