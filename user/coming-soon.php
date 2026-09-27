<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = $_GET['active'] ?? '';
$feature = trim($_GET['feature'] ?? 'This feature');
$pageTitle = $feature;

include __DIR__ . '/includes/user-header.php';
?>
<div class="coming-soon-wrap">
    <div class="coming-soon-icon">🚧</div>
    <h1><?= e($feature) ?></h1>
    <div class="badge badge-pending" style="font-size:13px; padding:6px 16px;">Under Development</div>
    <p class="muted" style="max-width:420px; margin:14px auto 26px;">
        We're still building this one — it isn't available yet, but it's on the way.
    </p>
    <a href="dashboard" class="btn-primary">← Back to Dashboard</a>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
