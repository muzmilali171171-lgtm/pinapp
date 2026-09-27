<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'blog-categories';
$pageTitle = 'Blog Categories';
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_category') {
    $id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    [$savedId, $err] = blog_save_category($pdo, $id, $_POST['name'] ?? '', $_POST['slug'] ?? '');
    if ($err) {
        $errors[] = $err;
    } else {
        log_event($pdo, 'system', 'Admin saved a blog category');
        redirect('blog-categories?saved=1');
    }
}

if (isset($_GET['delete'])) {
    $err = blog_delete_category($pdo, (int)$_GET['delete']);
    if ($err) {
        $notice = $err;
    } else {
        redirect('blog-categories?deleted=1');
    }
}

$categories = blog_get_categories($pdo, true);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Blog Categories</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Category saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Category deleted.</div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-error"><?= e($notice) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
    <h2 id="category-form-title">Add New Category</h2>
    <p class="muted">A category's slug is the URL segment its posts live under — e.g. slug <strong>growth-guide</strong>
    means posts publish at <code>/growth-guide/post-slug</code> and the archive lives at <code>/growth-guide</code>.</p>
    <form method="POST" id="category-form">
        <input type="hidden" name="action" value="save_category">
        <input type="hidden" name="category_id" id="category_id" value="">
        <div class="two-col">
            <div class="form-row">
                <label>Name</label>
                <input type="text" name="name" id="category_name" maxlength="150" placeholder="Growth Guide" required>
            </div>
            <div class="form-row">
                <label>Slug <span class="muted">(leave blank to auto-generate from the name)</span></label>
                <input type="text" name="slug" id="category_slug" maxlength="150" placeholder="growth-guide">
            </div>
        </div>
        <button type="submit" class="btn-primary">Save Category</button>
        <button type="button" class="btn-secondary" onclick="resetCategoryForm();">+ Add Another Instead</button>
    </form>
</div>

<div class="card">
    <h2>All Categories <span class="muted">(<?= count($categories) ?>)</span></h2>
    <?php if (empty($categories)): ?>
        <p class="muted">No categories yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Published Posts</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= e($c['name']) ?></td>
                <td><code>/<?= e($c['slug']) ?></code></td>
                <td><?= (int)$c['post_count'] ?></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn-secondary btn-small" onclick='editCategory(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>Edit</button>
                    <a href="?delete=<?= (int)$c['id'] ?>" class="btn-danger btn-small" onclick="return confirm('Delete this category?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function editCategory(c) {
    document.getElementById('category-form-title').textContent = 'Edit Category';
    document.getElementById('category_id').value = c.id;
    document.getElementById('category_name').value = c.name;
    document.getElementById('category_slug').value = c.slug;
    document.getElementById('category-form').scrollIntoView({behavior:'smooth'});
}
function resetCategoryForm() {
    document.getElementById('category-form-title').textContent = 'Add New Category';
    document.getElementById('category-form').reset();
    document.getElementById('category_id').value = '';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
