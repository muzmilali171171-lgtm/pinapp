<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'auto-website-batches';
$pageTitle = 'Your Scheduled Websites';

if (isset($_GET['stop'])) {
    $pdo->prepare("UPDATE website_pin_batches SET status = 'stopped' WHERE batch_id = ? AND user_id = ?")->execute([$_GET['stop'], $user['id']]);
    redirect('auto-website-batches');
}
if (isset($_GET['resume'])) {
    $pdo->prepare("UPDATE website_pin_batches SET status = 'active' WHERE batch_id = ? AND user_id = ?")->execute([$_GET['resume'], $user['id']]);
    redirect('auto-website-batches');
}

$batches = get_user_website_pin_batches($pdo, $user['id']);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Your Scheduled Websites</h1>
    <a href="auto-website-create" class="btn-primary">+ Create New Schedule</a>
</div>

<div class="card">
    <?php if (empty($batches)): ?>
        <div class="empty-state">
            <span class="icon">🌐</span>
            No scheduled websites yet. <a href="auto-website-create">Create your first schedule</a>.
        </div>
    <?php else: ?>
    <table>
        <tr>
            <th>ID</th><th>Batch Name</th><th>Website</th><th>First Pin</th><th>Last Pin</th>
            <th>Total Pages</th><th>Status</th><th></th>
        </tr>
        <?php foreach ($batches as $b): ?>
        <tr>
            <td>#<?= (int)$b['id'] ?></td>
            <td><strong><?= e($b['name'] ?: 'Untitled schedule') ?></strong>
                <div class="muted"><?= (int)$b['completed_pages'] ?>/<?= (int)$b['total_pages'] ?> pages done<?= $b['failed_pages'] > 0 ? ' · ' . (int)$b['failed_pages'] . ' failed' : '' ?></div>
            </td>
            <td><?= e($b['site_name'] ?: '—') ?></td>
            <td><?= format_datetime($b['first_publish_at']) ?></td>
            <td><?= format_datetime($b['last_publish_at']) ?></td>
            <td><?= (int)$b['total_pages'] ?></td>
            <td><span class="badge badge-<?= $b['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="auto-website-batch-view?batch_id=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">View More</a>
                <?php if ($b['status'] === 'active'): ?>
                    <a href="?stop=<?= e($b['batch_id']) ?>" class="btn-danger btn-small" onclick="return confirm('Stop this schedule? Pages not yet processed will not be generated.')">Stop</a>
                <?php else: ?>
                    <a href="?resume=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">Resume</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
