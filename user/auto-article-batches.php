<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'auto-article-batches';
$pageTitle = 'Your Batch';

if (isset($_GET['stop'])) {
    $stmt = $pdo->prepare("UPDATE article_batches SET status = 'stopped' WHERE batch_id = ? AND user_id = ?");
    $stmt->execute([$_GET['stop'], $user['id']]);
    redirect('auto-article-batches');
}
if (isset($_GET['resume'])) {
    $stmt = $pdo->prepare("UPDATE article_batches SET status = 'active' WHERE batch_id = ? AND user_id = ?");
    $stmt->execute([$_GET['resume'], $user['id']]);
    redirect('auto-article-batches');
}

$batches = get_user_article_batches($pdo, $user['id']);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Auto Article — Your Batches</h1>
    <a href="auto-article-create" class="btn-primary">+ Create New Batch</a>
</div>

<div class="card">
    <?php if (empty($batches)): ?>
        <div class="empty-state">
            <span class="icon">📝</span>
            No Auto Article batches yet. <a href="auto-article-create">Create your first batch</a>.
        </div>
    <?php else: ?>
    <table>
        <tr>
            <th>Name</th><th>Website</th><th>Total Articles</th><th>Category</th>
            <th>Pin Auto</th><th>Created</th><th>Status</th><th></th>
        </tr>
        <?php foreach ($batches as $b): ?>
        <tr>
            <td><strong><?= e($b['name'] ?: 'Untitled batch') ?></strong>
                <div class="muted"><?= (int)$b['published_articles'] ?>/<?= (int)$b['total_articles'] ?> published<?= $b['failed_articles'] > 0 ? ' · ' . (int)$b['failed_articles'] . ' failed' : '' ?></div>
            </td>
            <td><?= e($b['site_name'] ?: '—') ?></td>
            <td><?= (int)$b['total_articles'] ?></td>
            <td><?= e($b['category'] ?: '—') ?></td>
            <td><span class="badge badge-<?= $b['publish_mode'] === 'pin_auto' ? 'connected' : 'pending' ?>"><?= $b['publish_mode'] === 'pin_auto' ? 'On' : 'Off' ?></span></td>
            <td><?= format_datetime($b['created_at']) ?></td>
            <td><span class="badge badge-<?= $b['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="auto-article-batch-view?batch_id=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">View</a>
                <?php if ($b['status'] === 'active'): ?>
                    <a href="?stop=<?= e($b['batch_id']) ?>" class="btn-danger btn-small" onclick="return confirm('Stop this batch? Remaining queued articles will not be generated.')">Stop</a>
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
