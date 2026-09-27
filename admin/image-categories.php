<?php
/**
 * Admin → Image Categories. Main categories and their subcategories, which users pick in every
 * AI pin-image form ("Select category") so the generated photo matches the niche.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/image_category_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'image-categories';
$pageTitle = 'Image Categories';
$error = '';
$notice = $_GET['notice'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Your session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $msg = '';
        if ($action === 'add_main') {
            $r = image_category_add($pdo, (string)($_POST['name'] ?? ''), null);
            $r['ok'] ? $msg = 'Main category created.' : $error = $r['error'];
        } elseif ($action === 'add_sub') {
            $r = image_category_add($pdo, (string)($_POST['name'] ?? ''), (int)($_POST['parent_id'] ?? 0));
            $r['ok'] ? $msg = 'Subcategory created.' : $error = $r['error'];
        } elseif ($action === 'rename') {
            $r = image_category_rename($pdo, (int)($_POST['id'] ?? 0), (string)($_POST['name'] ?? ''));
            $r['ok'] ? $msg = 'Renamed.' : $error = $r['error'];
        } elseif ($action === 'delete') {
            image_category_delete($pdo, (int)($_POST['id'] ?? 0));
            $msg = 'Deleted.';
        } elseif ($action === 'seed') {
            $n = image_categories_seed_defaults($pdo);
            $msg = $n ? "$n default categories/subcategories added." : 'All default categories are already there.';
        }
        if (!$error) {
            log_event($pdo, 'system', 'Admin updated image categories: ' . $action);
            redirect('image-categories?notice=' . urlencode($msg) . (isset($_POST['keep_open']) ? '#cat-' . (int)$_POST['keep_open'] : ''));
        }
    }
}

$tree = image_categories_tree($pdo);
$subTotal = array_sum(array_map(fn($m) => count($m['subs']), $tree));
$csrf = csrf_token();

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.ic-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ic-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
.ic-form .form-row { flex: 1 1 200px; margin: 0; }
.ic-toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.ic-toolbar input { flex: 1 1 260px; padding: 10px 12px; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; }
.ic-main { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; }
.ic-main-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ic-main-head h3 { margin: 0; font-size: 16px; }
.ic-main-head .muted { font-weight: 400; font-size: 13px; }
.ic-actions { display: flex; gap: 6px; flex: none; }
.ic-actions button, .ic-chip button { border: 1px solid var(--border, #e5e7eb); background: #fff; border-radius: 8px; cursor: pointer; font-size: 13px; padding: 4px 9px; }
.ic-actions button.del, .ic-chip button.del { color: #b91c1c; }
.ic-subs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.ic-chip { display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--border, #e5e7eb); border-radius: 999px; padding: 3px 4px 3px 11px; font-size: 13px; background: #fafafa; }
.ic-chip button { border: none; background: none; padding: 2px 5px; }
.ic-hidden { display: none !important; }
@media (max-width: 800px) { .ic-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header"><h1>Image Categories</h1></div>
<?php if ($notice && !$error): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<p class="muted">Users pick one of these in every AI pin-image form (Bulk &amp; Single Pin Scheduler, Auto Website to Daily Pin,
Auto Article, Regenerate, Keyword Research, the homepage widget and AI Pin Create). The chosen category guides the AI
photo toward that niche's search intent. Leaving it on <em>Auto</em> lets the AI infer the category from the title.</p>

<div class="ic-grid">
    <div class="card">
        <h2 style="margin-top:0;">Create main category</h2>
        <form method="POST" class="ic-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add_main">
            <div class="form-row"><label for="icMainName">Name</label><input type="text" id="icMainName" name="name" maxlength="120" required placeholder="e.g. Home Decor"></div>
            <button type="submit" class="btn-primary">Create Now</button>
        </form>
    </div>
    <div class="card">
        <h2 style="margin-top:0;">Create sub category</h2>
        <form method="POST" class="ic-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add_sub">
            <div class="form-row"><label for="icSubName">Name</label><input type="text" id="icSubName" name="name" maxlength="120" required placeholder="e.g. Cozy Decor"></div>
            <div class="form-row"><label for="icSubParent">Main category</label>
                <select id="icSubParent" name="parent_id" required>
                    <option value="">— Select main category —</option>
                    <?php foreach ($tree as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Create Now</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="ic-toolbar">
        <h2 style="margin:0;">All categories <span class="muted" style="font-weight:400;">(<?= count($tree) ?> main · <?= $subTotal ?> sub)</span></h2>
        <input type="search" id="icSearch" placeholder="Search categories…" aria-label="Search categories">
        <form method="POST" style="margin:0;" onsubmit="return confirm('Add any default category or subcategory that is missing? Existing ones are not changed.');">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn-secondary btn-small">Add missing default categories</button>
        </form>
    </div>

    <?php if (!$tree): ?>
        <div class="empty-state">No categories yet. Create one above, or add the default categories.</div>
    <?php endif; ?>

    <div id="icList">
    <?php foreach ($tree as $m): ?>
        <div class="ic-main" id="cat-<?= (int)$m['id'] ?>" data-id="<?= (int)$m['id'] ?>">
            <div class="ic-main-head">
                <h3><span class="ic-n"><?= e($m['name']) ?></span> <span class="muted">(<?= count($m['subs']) ?> sub)</span></h3>
                <div class="ic-actions">
                    <button type="button" data-a="r">Rename</button>
                    <button type="button" class="del" data-a="d">Delete</button>
                </div>
            </div>
            <?php if ($m['subs']): ?>
            <div class="ic-subs">
                <?php foreach ($m['subs'] as $s): ?>
                    <span class="ic-chip" data-id="<?= (int)$s['id'] ?>"><span class="ic-n"><?= e($s['name']) ?></span><button type="button" data-a="r" title="Rename">✎</button><button type="button" class="del" data-a="d" title="Delete">✕</button></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<form method="POST" id="icActionForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action">
    <input type="hidden" name="id">
    <input type="hidden" name="name">
    <input type="hidden" name="keep_open">
</form>

<script>
(function () {
    var form = document.getElementById('icActionForm');
    function send(action, id, name, parent) {
        var f = function (n) { return form.querySelector('[name="' + n + '"]'); };
        f('action').value = action; f('id').value = id; f('name').value = name || ''; f('keep_open').value = parent || '';
        HTMLFormElement.prototype.submit.call(form);
    }
    // One delegated handler for every Rename / Delete button (keeps the page light with ~2,000 chips).
    document.getElementById('icList').addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]');
        if (!b) return;
        var chip = b.closest('.ic-chip'), main = b.closest('.ic-main');
        var item = chip || main, id = item.getAttribute('data-id');
        var name = item.querySelector('.ic-n').textContent;
        var keep = main.getAttribute('data-id');
        if (b.getAttribute('data-a') === 'r') {
            var n = prompt('New name:', name);
            if (n && n.trim() && n.trim() !== name) send('rename', id, n.trim(), keep);
        } else {
            var label = chip ? name : name + ' and all its subcategories';
            if (confirm('Delete "' + label + '"?')) send('delete', id, '', chip ? keep : '');
        }
    });
    var search = document.getElementById('icSearch');
    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        document.querySelectorAll('.ic-main').forEach(function (m) {
            var mainHit = !q || m.querySelector('h3 .ic-n').textContent.toLowerCase().indexOf(q) !== -1;
            var anySub = false;
            m.querySelectorAll('.ic-chip').forEach(function (c) {
                var hit = mainHit || c.textContent.toLowerCase().indexOf(q) !== -1;
                c.classList.toggle('ic-hidden', !hit);
                if (hit) anySub = true;
            });
            m.classList.toggle('ic-hidden', !(mainHit || anySub));
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
