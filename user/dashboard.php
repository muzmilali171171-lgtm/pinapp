<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'dashboard';
$pageTitle = 'Dashboard';

$accounts = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ?");
$accounts->execute([$user['id']]);
$accounts = $accounts->fetchAll();

$stmt = $pdo->prepare("SELECT status, COUNT(*) as c FROM scheduled_pins WHERE user_id = ? GROUP BY status");
$stmt->execute([$user['id']]);
$statusCounts = ['pending' => 0, 'processing' => 0, 'published' => 0, 'failed' => 0];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['c'];
}

// Pins grouped by board
$stmt = $pdo->prepare("SELECT board_name, board_id, COUNT(*) as total,
        SUM(status='published') as published, SUM(status='pending') as pending
    FROM scheduled_pins WHERE user_id = ? GROUP BY board_id, board_name ORDER BY total DESC");
$stmt->execute([$user['id']]);
$byBoard = $stmt->fetchAll();

$totalScheduled = array_sum($statusCounts);

include __DIR__ . '/includes/user-header.php';
?>
<?php
$freeToolPinPending = $_SESSION['free_tool_pincreate_pending'] ?? null;
unset($_SESSION['free_tool_pincreate_pending']);
?>
<div class="page-header">
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <a href="schedule-create" class="btn-primary">+ New Schedule</a>
</div>

<?php if ($freeToolPinPending && !empty($freeToolPinPending['clean_path'])): ?>
<div class="alert alert-success">
    Your free pin "<?= e($freeToolPinPending['title'] ?? '') ?>" is ready —
    <a href="../<?= e($freeToolPinPending['clean_path']) ?>" download>download it without the watermark</a>.
</div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= count($accounts) ?></div><div class="label">Connected Pinterest accounts</div></div>
    <div class="stat-card"><div class="num"><?= $totalScheduled ?></div><div class="label">Total scheduled pins</div></div>
    <div class="stat-card"><div class="num"><?= $statusCounts['pending'] ?></div><div class="label">Pending</div></div>
    <div class="stat-card"><div class="num"><?= $statusCounts['published'] ?></div><div class="label">Published</div></div>
    <div class="stat-card"><div class="num"><?= $statusCounts['failed'] ?></div><div class="label">Failed</div></div>
</div>

<?php if (empty($accounts)): ?>
<div class="card">
    <h2>Get started</h2>
    <p>You haven't connected a Pinterest account yet.</p>
    <a href="connect-pinterest" class="btn-primary">Connect Pinterest</a>
</div>
<?php endif; ?>

<div class="card">
    <h2>Scheduled pins by board</h2>
    <?php if (empty($byBoard)): ?>
        <div class="empty-state">No pins scheduled yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Board</th><th>Total</th><th>Published</th><th>Pending</th></tr>
        <?php foreach ($byBoard as $b): ?>
        <tr>
            <td><?= e($b['board_name'] ?: $b['board_id']) ?></td>
            <td><?= $b['total'] ?></td>
            <td><?= $b['published'] ?></td>
            <td><?= $b['pending'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
