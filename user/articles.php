<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'articles';
$pageTitle = 'My Articles';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM articles WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
    redirect('articles');
}

$articles = $pdo->prepare("SELECT a.*, w.site_name FROM articles a LEFT JOIN websites w ON w.id = a.website_id
    WHERE a.user_id = ? ORDER BY a.updated_at DESC");
$articles->execute([$user['id']]);
$articles = $articles->fetchAll();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>My Articles</h1>
    <a href="write-article" class="btn-primary">+ Write Article</a>
</div>

<?php if (isset($_GET['published'])): ?><div class="alert alert-success">Article published!</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Draft saved.</div><?php endif; ?>

<div class="card">
    <?php if (empty($articles)): ?>
        <div class="empty-state">No articles yet. <a href="write-article">Write your first one</a>.</div>
    <?php else: ?>
    <table>
        <tr><th>Title</th><th>Website</th><th>Status</th><th>Updated</th><th></th></tr>
        <?php foreach ($articles as $a): ?>
        <tr>
            <td><?= e($a['title'] ?: '(untitled)') ?></td>
            <td><?= e($a['site_name'] ?: '-') ?></td>
            <td>
                <span class="badge badge-<?= e($a['status'] === 'published' ? 'published' : ($a['status'] === 'failed' ? 'failed' : 'pending')) ?>"><?= e(ucfirst($a['status'])) ?></span>
                <?php if ($a['status'] === 'published' && $a['wp_post_url']): ?>
                    — <a href="<?= e($a['wp_post_url']) ?>" target="_blank" rel="noopener">View</a>
                <?php endif; ?>
            </td>
            <td class="muted"><?= format_datetime($a['updated_at']) ?></td>
            <td>
                <?php if ($a['status'] !== 'published'): ?>
                    <a href="write-article?id=<?= (int)$a['id'] ?>&step=<?= $a['content'] ? 'edit' : 'outline' ?>" class="btn-secondary btn-small">Continue</a>
                <?php endif; ?>
                <a href="?delete=<?= (int)$a['id'] ?>" class="btn-secondary btn-small" onclick="return confirm('Delete this article?');">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
