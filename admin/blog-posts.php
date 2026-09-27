<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'blog-posts';
$pageTitle = 'All Blog Posts';

if (isset($_GET['delete'])) {
    blog_delete_post($pdo, (int)$_GET['delete']);
    redirect('blog-posts?deleted=1');
}
if (isset($_GET['toggle'])) {
    $p = blog_get_post($pdo, (int)$_GET['toggle']);
    if ($p) {
        $newStatus = $p['status'] === 'published' ? 'draft' : 'published';
        $publishedAt = $p['published_at'] ?: ($newStatus === 'published' ? date('Y-m-d H:i:s') : null);
        $pdo->prepare("UPDATE blog_posts SET status = ?, published_at = ? WHERE id = ?")
            ->execute([$newStatus, $publishedAt, $p['id']]);
    }
    redirect('blog-posts');
}

$search = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$result = blog_admin_list_posts($pdo, $search, $categoryId, $status, $page, 20);
$posts = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / 20));
$categories = blog_get_categories($pdo);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>All Blog Posts</h1></div>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Post deleted.</div><?php endif; ?>

<div class="card">
    <form method="GET" class="two-col" style="align-items:end;">
        <div class="form-row">
            <label>Search by title</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search posts...">
        </div>
        <div class="form-row">
            <label>Category</label>
            <select name="category">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="form-row" style="max-width:160px;">
            <button type="submit" class="btn-primary">Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Posts <span class="muted">(<?= $total ?>)</span></h2>
    <p class="muted"><a href="blog-post-create" class="btn-primary btn-small">+ Create New Post</a></p>
    <?php if (empty($posts)): ?>
        <p class="muted">No posts found.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['category_name']) ?></td>
                <td><?= format_datetime($p['published_at'] ?: $p['created_at']) ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'published' ? 'connected' : 'error' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td style="white-space:nowrap;">
                    <a href="blog-post-create?id=<?= (int)$p['id'] ?>" class="btn-secondary btn-small">Edit</a>
                    <a href="../<?= e($p['category_slug']) ?>/<?= e($p['slug']) ?>" target="_blank" class="btn-secondary btn-small">View</a>
                    <a href="?toggle=<?= (int)$p['id'] ?>" class="btn-secondary btn-small"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></a>
                    <a href="?delete=<?= (int)$p['id'] ?>" class="btn-danger btn-small" onclick="return confirm('Delete this post?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
        <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="btn-secondary btn-small <?= $i === $page ? 'active' : '' ?>"
               href="?q=<?= urlencode($search) ?>&category=<?= $categoryId ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
