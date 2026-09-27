<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$done = false;

$stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE token = ? AND type = 'password_reset'");
$stmt->execute([$token]);
$tokenRow = $stmt->fetch();
$valid = $tokenRow && !$tokenRow['used_at'] && strtotime($tokenRow['expires_at']) > time();

if (!$token || !$valid) {
    $errors[] = 'This password-reset link is invalid or has expired. Please request a new one.';
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($newPass) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $tokenRow['user_id']]);
        $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE id = ?")->execute([$tokenRow['id']]);
        log_event($pdo, 'system', 'Password reset via emailed link', (int)$tokenRow['user_id']);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php seo_render_head($pdo, ['title' => 'Reset password — ' . SITE_BRAND, 'description' => '', 'noindex' => true, 'schema' => false]); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Reset your password</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($done): ?>
        <div class="alert alert-success">Your password has been reset.</div>
        <div class="switch"><a href="login">Log in</a></div>
    <?php elseif ($valid): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>New password</label>
            <input type="password" name="new_password" required minlength="6">
            <label>Confirm new password</label>
            <input type="password" name="confirm_password" required minlength="6">
            <button type="submit" class="btn-primary">Set New Password</button>
        </form>
    <?php else: ?>
        <div class="switch"><a href="forgot-password">Request a new link</a></div>
    <?php endif; ?>
</div>
</body>
</html>
