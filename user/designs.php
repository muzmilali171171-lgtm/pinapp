<?php
/** Custom Design → Your Designs (and published templates to start from). */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'designs';
$pageTitle = 'Your Designs';
$tab = ($_GET['tab'] ?? 'mine') === 'templates' ? 'templates' : 'mine';

$designs = $templates = [];
$tablesMissing = false;
try {
    $st = $pdo->prepare("SELECT id, title, width, height, thumb_path, is_published, updated_at FROM user_designs WHERE user_id = ? ORDER BY updated_at DESC");
    $st->execute([$user['id']]);
    $designs = $st->fetchAll();
    $templates = $pdo->query("SELECT id, title, width, height, thumb_path, template_category FROM user_designs WHERE is_published = 1 ORDER BY published_at DESC")->fetchAll();
} catch (PDOException $e) {
    $tablesMissing = true;
}
$presets = [['Pinterest Pin', 1000, 1500], ['Pinterest Long', 1000, 2100], ['Story / Reel', 1080, 1920], ['Square', 1080, 1080], ['Instagram Portrait', 1080, 1350], ['YouTube Thumbnail', 1280, 720]];

include __DIR__ . '/includes/user-header.php';
?>
<style>
.dz-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.dz-new { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:10px; margin-bottom:22px; }
.dz-new a { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:16px 10px; border:1px dashed #c4b5fd; border-radius:14px; background:#faf5ff; color:#5b21b6; text-decoration:none; font-weight:600; font-size:14px; text-align:center; }
.dz-new a small { color:#7c3aed; font-weight:400; font-size:12px; }
.dz-new a:hover { background:#f3e8ff; }
.dz-new .dz-shape { background:#ddd6fe; border-radius:4px; }
.dz-custom { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.dz-custom input { width:90px; }
.dz-tabs { display:flex; gap:8px; margin-bottom:14px; }
.dz-tabs a { padding:8px 16px; border-radius:999px; border:1px solid #e5e7eb; text-decoration:none; color:#374151; font-weight:600; font-size:14px; }
.dz-tabs a.on { background:#7c3aed; border-color:#7c3aed; color:#fff; }
.dz-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:14px; }
.dz-card { border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; background:#fff; display:flex; flex-direction:column; }
.dz-thumb { display:block; background:#f3f4f6; aspect-ratio:2/3; overflow:hidden; }
.dz-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.dz-thumb span { display:flex; height:100%; align-items:center; justify-content:center; color:#9ca3af; font-size:13px; }
.dz-body { padding:10px 12px; display:flex; flex-direction:column; gap:6px; }
.dz-title { font-weight:600; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dz-meta { color:#6b7280; font-size:12px; }
.dz-actions { display:flex; gap:6px; flex-wrap:wrap; }
.dz-actions a, .dz-actions button { font-size:12.5px; padding:5px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; text-decoration:none; color:#374151; }
.dz-actions a.dz-primary { background:#7c3aed; border-color:#7c3aed; color:#fff; }
.dz-actions .dz-del:hover { background:#fee2e2; border-color:#fecaca; }
.dz-badge { display:inline-block; font-size:11px; background:#dcfce7; color:#166534; border-radius:999px; padding:1px 8px; margin-left:4px; }
</style>

<div class="page-header dz-head">
    <h1>🎨 Custom Design</h1>
    <a class="btn-primary" href="design-editor">+ Create a blank design</a>
</div>

<?php if ($tablesMissing): ?>
    <div class="alert alert-error">The design tables are missing — ask the admin to run <code>migrate.php</code> once.</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;">Start a new design</h3>
    <div class="dz-new">
        <?php foreach ($presets as [$n, $w, $h]): $sw = 34 * $w / max($w, $h); $sh = 34 * $h / max($w, $h); ?>
            <a href="design-editor?w=<?= $w ?>&h=<?= $h ?>">
                <span class="dz-shape" style="width:<?= round($sw) ?>px;height:<?= round($sh) ?>px;"></span>
                <?= e($n) ?><small><?= $w ?> × <?= $h ?> px</small>
            </a>
        <?php endforeach; ?>
    </div>
    <form class="dz-custom" action="design-editor" method="get">
        <strong>Custom size:</strong>
        <input type="number" name="w" min="50" max="8000" value="1000" required> ×
        <input type="number" name="h" min="50" max="8000" value="1500" required> px
        <button type="submit" class="btn-secondary">Create</button>
    </form>
</div>

<div class="dz-tabs">
    <a href="designs?tab=mine" class="<?= $tab === 'mine' ? 'on' : '' ?>">Your Designs (<?= count($designs) ?>)</a>
    <a href="designs?tab=templates" class="<?= $tab === 'templates' ? 'on' : '' ?>">Templates (<?= count($templates) ?>)</a>
</div>

<?php if ($tab === 'mine'): ?>
    <?php if (!$designs): ?>
        <div class="card"><p class="muted" style="margin:0;">You haven't saved any designs yet. Create one above, or start from a template.</p></div>
    <?php else: ?>
    <div class="dz-grid">
        <?php foreach ($designs as $d): ?>
        <div class="dz-card" data-id="<?= (int)$d['id'] ?>">
            <a class="dz-thumb" href="design-editor?id=<?= (int)$d['id'] ?>" style="aspect-ratio:<?= (int)$d['width'] ?>/<?= (int)$d['height'] ?>;">
                <?php if ($d['thumb_path']): ?><img loading="lazy" src="../<?= e($d['thumb_path']) ?>?v=<?= strtotime($d['updated_at']) ?>" alt=""><?php else: ?><span>No preview</span><?php endif; ?>
            </a>
            <div class="dz-body">
                <div class="dz-title" title="<?= e($d['title']) ?>"><?= e($d['title']) ?><?php if ($d['is_published']): ?><span class="dz-badge">Published</span><?php endif; ?></div>
                <div class="dz-meta"><?= (int)$d['width'] ?> × <?= (int)$d['height'] ?> · <?= e(date('M j, Y', strtotime($d['updated_at']))) ?></div>
                <div class="dz-actions">
                    <a class="dz-primary" href="design-editor?id=<?= (int)$d['id'] ?>">Edit</a>
                    <button type="button" data-act="rename">Rename</button>
                    <button type="button" data-act="duplicate">Duplicate</button>
                    <?php if ($d['thumb_path']): ?><a href="../<?= e($d['thumb_path']) ?>" download>Preview</a><?php endif; ?>
                    <button type="button" class="dz-del" data-act="delete">Delete</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$templates): ?>
        <div class="card"><p class="muted" style="margin:0;">No templates have been published yet.</p></div>
    <?php else: ?>
    <div class="dz-grid">
        <?php foreach ($templates as $t): ?>
        <div class="dz-card">
            <a class="dz-thumb" href="design-editor?template=<?= (int)$t['id'] ?>" style="aspect-ratio:<?= (int)$t['width'] ?>/<?= (int)$t['height'] ?>;">
                <?php if ($t['thumb_path']): ?><img loading="lazy" src="../<?= e($t['thumb_path']) ?>" alt=""><?php else: ?><span>No preview</span><?php endif; ?>
            </a>
            <div class="dz-body">
                <div class="dz-title" title="<?= e($t['title']) ?>"><?= e($t['title']) ?></div>
                <div class="dz-meta"><?= (int)$t['width'] ?> × <?= (int)$t['height'] ?><?= $t['template_category'] ? ' · ' . e($t['template_category']) : '' ?></div>
                <div class="dz-actions"><a class="dz-primary" href="design-editor?template=<?= (int)$t['id'] ?>">Use this template</a></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
document.querySelectorAll('.dz-card [data-act]').forEach(function (b) {
    b.addEventListener('click', async function () {
        var card = b.closest('.dz-card'), id = card.dataset.id, act = b.dataset.act;
        var fd = new FormData(); fd.append('action', act); fd.append('id', id);
        if (act === 'delete' && !confirm('Delete this design? This cannot be undone.')) return;
        if (act === 'rename') {
            var t = prompt('New name for this design:', card.querySelector('.dz-title').childNodes[0].textContent.trim());
            if (!t) return; fd.append('title', t);
        }
        var r = await fetch('ajax-design', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.json(); }).catch(function () { return { ok: false }; });
        if (!r.ok) { alert(r.error || 'Something went wrong.'); return; }
        location.reload();
    });
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
