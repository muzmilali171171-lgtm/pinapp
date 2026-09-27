<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'blog-post-create';
$errors = [];

$editId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
$post = $editId ? blog_get_post($pdo, $editId) : null;
if ($editId && !$post) redirect('blog-posts');
$pageTitle = $post ? 'Edit Blog Post' : 'Create Blog Post';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_post') {
    $id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null;

    if (trim($_POST['title'] ?? '') === '') $errors[] = 'Title is required.';
    if ((int)($_POST['category_id'] ?? 0) <= 0) $errors[] = 'Please select a category.';
    if (trim($_POST['content'] ?? '') === '') $errors[] = 'Content is required.';

    $featureImagePath = blog_handle_feature_image_upload('feature_image', $errors);

    if (!$errors) {
        $newId = blog_save_post($pdo, $id, $_POST, $featureImagePath);
        log_event($pdo, 'system', 'Admin saved a blog post');
        redirect('blog-posts?saved=1');
        exit;
    }
    // Re-hydrate $post from submitted data so the form redisplays with the errors.
    $post = array_merge($post ?? [], $_POST, ['id' => $id]);
}

$categories = blog_get_categories($pdo);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1><?= $post['id'] ?? null ? 'Edit Blog Post' : 'Create Blog Post' ?></h1></div>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data" id="post-form">
        <input type="hidden" name="action" value="save_post">
        <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">

        <div class="form-row">
            <label>Title</label>
            <input type="text" name="title" id="post_title" maxlength="255" required
                   value="<?= e($post['title'] ?? '') ?>" placeholder="How to Go Viral on Pinterest in 2026">
        </div>

        <div class="form-row">
            <label>Sub Title</label>
            <input type="text" name="subtitle" maxlength="500" value="<?= e($post['subtitle'] ?? '') ?>"
                   placeholder="One or two sentences shown under the title">
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>Meta Title</label>
                <input type="text" name="meta_title" maxlength="255" value="<?= e($post['meta_title'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label>Slug <span class="muted">(leave blank to auto-generate)</span></label>
                <input type="text" name="slug" id="post_slug" maxlength="255" value="<?= e($post['slug'] ?? '') ?>" placeholder="pinterest-growth">
            </div>
        </div>

        <div class="form-row">
            <label>Meta Description</label>
            <textarea name="meta_description" rows="2" maxlength="500"><?= e($post['meta_description'] ?? '') ?></textarea>
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>Category <span class="muted">(this is also the URL prefix — /category/slug)</span></label>
                <select name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)($post['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (/<?= e($c['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Author</label>
                <input type="text" name="author" maxlength="150" value="<?= e($post['author'] ?? '') ?>" placeholder="Optional byline">
            </div>
        </div>

        <div class="form-row">
            <label>Feature Image</label>
            <input type="file" name="feature_image" accept="image/*">
            <?php if (!empty($post['feature_image'])): ?>
                <div style="margin-top:8px;"><img src="../<?= e($post['feature_image']) ?>" style="width:160px;border-radius:8px;border:1px solid var(--border);"></div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <label>Content</label>
            <div style="margin-bottom:8px;">
                <label class="checkbox-row" style="display:inline-flex;margin-right:18px;">
                    <input type="radio" name="editor_mode" value="ckeditor" id="mode_ckeditor" <?= (($post['editor_mode'] ?? 'ckeditor') !== 'html') ? 'checked' : '' ?>>
                    Edit with CKEditor
                </label>
                <label class="checkbox-row" style="display:inline-flex;">
                    <input type="radio" name="editor_mode" value="html" id="mode_html" <?= (($post['editor_mode'] ?? '') === 'html') ? 'checked' : '' ?>>
                    Edit raw HTML
                </label>
            </div>
            <textarea name="content" id="post_content" rows="16"><?= e($post['content'] ?? '') ?></textarea>
            <p class="muted">Tip: use H2/H3 headings — the single post page auto-builds its table of contents from them.</p>
        </div>

        <div class="form-row" style="max-width:220px;">
            <label>Status</label>
            <select name="status">
                <option value="draft" <?= (($post['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= (($post['status'] ?? '') === 'published') ? 'selected' : '' ?>>Publish</option>
            </select>
        </div>

        <button type="submit" class="btn-primary">Save Post</button>
        <a href="blog-posts" class="btn-secondary">Cancel</a>
    </form>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
var ckEditorInstance = null;
var contentTextarea = document.getElementById('post_content');

function startCkEditor() {
    if (ckEditorInstance) return;
    ClassicEditor.create(contentTextarea).then(function (editor) {
        ckEditorInstance = editor;
        editor.model.document.on('change:data', function () {
            contentTextarea.value = editor.getData();
        });
    }).catch(console.error);
}
function stopCkEditor() {
    if (!ckEditorInstance) return;
    contentTextarea.value = ckEditorInstance.getData();
    ckEditorInstance.destroy();
    ckEditorInstance = null;
    contentTextarea.style.display = '';
}

document.getElementById('mode_ckeditor').addEventListener('change', function () {
    if (this.checked) startCkEditor();
});
document.getElementById('mode_html').addEventListener('change', function () {
    if (this.checked) stopCkEditor();
});
if (document.getElementById('mode_ckeditor').checked) startCkEditor();

document.getElementById('post-form').addEventListener('submit', function () {
    if (ckEditorInstance) contentTextarea.value = ckEditorInstance.getData();
});

// Live slug preview only — the server still auto-generates/dedupes on save.
document.getElementById('post_title').addEventListener('blur', function () {
    var slugField = document.getElementById('post_slug');
    if (slugField.value.trim() !== '') return;
    slugField.value = this.value.toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
