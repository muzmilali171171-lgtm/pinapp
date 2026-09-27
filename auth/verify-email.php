<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';

$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE token = ? AND type = 'email_verify'");
$stmt->execute([$token]);
$tokenRow = $stmt->fetch();
$valid = $tokenRow && !$tokenRow['used_at'] && strtotime($tokenRow['expires_at']) > time();

if ($valid) {
    $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([$tokenRow['user_id']]);
    $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE id = ?")->execute([$tokenRow['id']]);
    log_event($pdo, 'system', 'Email address verified', (int)$tokenRow['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php seo_render_head($pdo, ['title' => 'Verify email — ' . SITE_BRAND, 'description' => '', 'noindex' => true, 'schema' => false]); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Email verification</h1>
    <?php if ($valid): ?>
        <div class="alert alert-success">Your email address has been verified. Thanks!</div>
    <?php else: ?>
        <div class="alert alert-error">This verification link is invalid or has expired.</div>
    <?php endif; ?>
    <div class="switch"><a href="<?= current_user($pdo) ? '../user/dashboard' : 'login' ?>">Continue</a></div>
</div>
</body>
</html>
