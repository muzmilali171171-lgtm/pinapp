<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tutorial_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'tutorials';
$pageTitle = 'Tutorials';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_tutorial') {
    $id = !empty($_POST['tutorial_id']) ? (int)$_POST['tutorial_id'] : null;

    if (trim($_POST['title'] ?? '') === '') $errors[] = 'Title is required.';
    if (trim($_POST['video_url'] ?? '') === '') $errors[] = 'Video link is required.';

    $thumbnailPath = tutorials_handle_thumbnail_upload('thumbnail', $errors);

    if (!$errors) {
        save_tutorial($pdo, $id, $_POST, $thumbnailPath);
        log_event($pdo, 'system', 'Admin saved a tutorial video');
        redirect('tutorials?saved=1');
    }
}

if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("SELECT status FROM tutorials WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    $status = $stmt->fetchColumn();
    if ($status !== false) {
        $pdo->prepare("UPDATE tutorials SET status = ? WHERE id = ?")
            ->execute([$status === 'active' ? 'inactive' : 'active', (int)$_GET['toggle']]);
    }
    redirect('tutorials');
}
if (isset($_GET['feature'])) {
    $pdo->exec("UPDATE tutorials SET is_featured = 0");
    $pdo->prepare("UPDATE tutorials SET is_featured = 1 WHERE id = ?")->execute([(int)$_GET['feature']]);
    redirect('tutorials');
}
if (isset($_GET['unfeature'])) {
    $pdo->prepare("UPDATE tutorials SET is_featured = 0 WHERE id = ?")->execute([(int)$_GET['unfeature']]);
    redirect('tutorials');
}
if (isset($_GET['delete'])) {
    delete_tutorial($pdo, (int)$_GET['delete']);
    redirect('tutorials');
}

$tutorials = get_all_tutorials($pdo);
foreach ($tutorials as &$t) {
    $t['thumb_url'] = tutorials_thumbnail_url($t);
}
unset($t);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Tutorials</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Tutorial saved.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
    <h2 id="tutorial-form-title">Add Tutorial</h2>
    <p class="muted">Paste any YouTube link (watch, youtu.be or shorts) — the video ID and default thumbnail are
    picked up automatically. Upload a custom thumbnail below only if you want to override it.</p>
    <form method="POST" enctype="multipart/form-data" id="tutorial-form">
        <input type="hidden" name="action" value="save_tutorial">
        <input type="hidden" name="tutorial_id" id="tutorial_id" value="">

        <div class="two-col">
            <div class="form-row">
                <label>Title</label>
                <input type="text" name="title" id="tutorial_title" maxlength="255" placeholder="BlogToPin Tutorial. Introduction." required>
            </div>
            <div class="form-row">
                <label>Video Link (YouTube)</label>
                <input type="url" name="video_url" id="tutorial_video_url" maxlength="500" placeholder="https://www.youtube.com/watch?v=..." required>
            </div>
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>Video Length <span class="muted">(shown on the right, e.g. 8:36)</span></label>
                <input type="text" name="duration" id="tutorial_duration" maxlength="20" placeholder="8:36">
            </div>
            <div class="form-row">
                <label>Priority / Order <span class="muted">(lower shows first)</span></label>
                <input type="number" name="sort_order" id="tutorial_sort_order" value="0">
            </div>
        </div>

        <div class="form-row">
            <label>Thumbnail <span class="muted">(optional — leave blank to auto-use the YouTube thumbnail)</span></label>
            <input type="file" name="thumbnail" id="tutorial_thumbnail" accept="image/*">
            <div id="tutorial_thumb_preview" style="margin-top:8px;"></div>
        </div>

        <div class="form-row">
            <label>Description <span class="muted">(optional, internal notes)</span></label>
            <textarea name="description" id="tutorial_description" rows="2"></textarea>
        </div>

        <label class="checkbox-row">
            <input type="checkbox" name="is_featured" id="tutorial_is_featured" value="1">
            Set as the main / featured video (shown as the big "Quick Start" card at the top of the Tutorials page — only one video can be featured at a time)
        </label>

        <div class="form-row" style="max-width:220px;margin-top:16px;">
            <label>Status</label>
            <select name="status" id="tutorial_status">
                <option value="active">Active (visible)</option>
                <option value="inactive">Inactive (hidden)</option>
            </select>
        </div>

        <button type="submit" class="btn-primary">Save Tutorial</button>
        <button type="button" class="btn-secondary" onclick="resetTutorialForm();">+ Add Another Instead</button>
    </form>
</div>

<div class="card">
    <h2>All Tutorials <span class="muted">(<?= count($tutorials) ?>)</span></h2>
    <p class="muted">Live at <a href="../tutorials" target="_blank">/tutorials</a> — also linked from the user
    panel's topbar "Tutorials" button and sidebar menu.</p>
    <?php if (empty($tutorials)): ?>
        <p class="muted">No tutorials added yet. Use the form above to add your first video.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Thumbnail</th><th>Title</th><th>Length</th><th>Priority</th><th>Featured</th><th>Status</th><th>Views</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tutorials as $t): ?>
            <tr>
                <td>
                    <?php if ($t['thumb_url']): ?>
                        <img src="<?= e($t['thumb_url']) ?>" alt="" style="width:80px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                    <?php else: ?>
                        <span class="muted">No image</span>
                    <?php endif; ?>
                </td>
                <td><?= e($t['title']) ?></td>
                <td><?= e($t['duration'] ?: '-') ?></td>
                <td><?= (int)$t['sort_order'] ?></td>
                <td>
                    <?php if ($t['is_featured']): ?>
                        <span class="badge badge-connected">★ Main video</span>
                    <?php else: ?>
                        <a href="?feature=<?= (int)$t['id'] ?>" class="btn-secondary btn-small">Set as main</a>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $t['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                <td><?= (int)$t['view_count'] ?></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn-secondary btn-small" onclick='editTutorial(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>Edit</button>
                    <a href="?toggle=<?= (int)$t['id'] ?>" class="btn-secondary btn-small"><?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?></a>
                    <a href="?delete=<?= (int)$t['id'] ?>" class="btn-danger btn-small" onclick="return confirm('Delete this tutorial?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function editTutorial(t) {
    document.getElementById('tutorial-form-title').textContent = 'Edit Tutorial';
    document.getElementById('tutorial_id').value = t.id;
    document.getElementById('tutorial_title').value = t.title;
    document.getElementById('tutorial_video_url').value = t.video_url;
    document.getElementById('tutorial_duration').value = t.duration || '';
    document.getElementById('tutorial_sort_order').value = t.sort_order;
    document.getElementById('tutorial_description').value = t.description || '';
    document.getElementById('tutorial_is_featured').checked = t.is_featured == 1;
    document.getElementById('tutorial_status').value = t.status;
    var preview = document.getElementById('tutorial_thumb_preview');
    preview.innerHTML = t.thumb_url ? '<img src="' + t.thumb_url + '" style="width:120px;border-radius:6px;border:1px solid var(--border);">' : '';
    document.getElementById('tutorial-form').scrollIntoView({behavior:'smooth'});
}
function resetTutorialForm() {
    document.getElementById('tutorial-form-title').textContent = 'Add Tutorial';
    document.getElementById('tutorial-form').reset();
    document.getElementById('tutorial_id').value = '';
    document.getElementById('tutorial_thumb_preview').innerHTML = '';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
