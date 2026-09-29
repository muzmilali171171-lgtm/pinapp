<?php
/**
 * Admin → Canva → All Image Style Templates: every "Pin Templates & Styles" template — the built-in ones
 * and the ones made in Admin → Canva → Create New Template — with a live preview and an on/off switch.
 * Switched-off templates disappear from the users' picker, the Classic Wizard and AI Auto.
 * Admin templates can be edited (opens the editor) or deleted; drafts are listed at the top.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pin_template_registry.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();
at_ensure_schema($pdo);

$activePage = 'pin-templates';
$pageTitle = 'All Image Style Templates';
$reg = pin_template_registry();
$ver = pin_template_preview_version();
$drafts = $pdo->query("SELECT id, name, thumb_path, updated_at FROM admin_pin_templates WHERE status = 'draft' ORDER BY updated_at DESC")->fetchAll();
$cats = [];
foreach ($reg as $t) $cats[$t['category']] = ($cats[$t['category']] ?? 0) + 1;
ksort($cats, SORT_NATURAL | SORT_FLAG_CASE);
$nActive = count(array_filter($reg, fn($t) => !empty($t['active'])));
$nAdmin = count(array_filter($reg, fn($t) => !empty($t['admin'])));

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.apt-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.apt-tools input[type=search], .apt-tools select { padding: 8px 10px; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; font: inherit; }
.apt-tools input[type=search] { min-width: 220px; flex: 1; }
.apt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; margin-top: 14px; }
.apt-card { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.apt-card.off { opacity: .55; }
.apt-card.off .apt-img img { filter: grayscale(1); }
.apt-img { position: relative; aspect-ratio: 2 / 3; background: #f3f4f6; }
.apt-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.apt-badges { position: absolute; left: 6px; top: 6px; display: flex; flex-wrap: wrap; gap: 4px; }
.apt-badge { background: rgba(17,24,39,.8); color: #fff; font-size: 10.5px; font-weight: 700; border-radius: 999px; padding: 2px 8px; }
.apt-badge.admin { background: #7c3aed; }
.apt-badge.tag { background: #e60023; }
.apt-badge.num { background: #0891b2; }
.apt-body { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
.apt-name { font-weight: 700; font-size: 13px; line-height: 1.3; }
.apt-meta { font-size: 11.5px; color: var(--gray, #6b7280); }
.apt-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; padding-top: 6px; }
.apt-switch { position: relative; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.apt-switch input { appearance: none; width: 34px; height: 20px; border-radius: 999px; background: #d1d5db; position: relative; cursor: pointer; transition: background .15s; margin: 0; }
.apt-switch input::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: left .15s; }
.apt-switch input:checked { background: #16a34a; }
.apt-switch input:checked::after { left: 16px; }
.apt-acts { display: flex; gap: 4px; }
.apt-acts a, .apt-acts button { font-size: 11.5px; padding: 4px 8px; }
.apt-drafts { display: flex; flex-wrap: wrap; gap: 10px; }
.apt-draft { display: flex; gap: 10px; align-items: center; border: 1px dashed var(--border, #e5e7eb); border-radius: 12px; padding: 8px 10px; }
.apt-draft img { width: 40px; height: 60px; object-fit: cover; border-radius: 6px; background: #f3f4f6; }
.apt-count { font-size: 13px; color: var(--gray, #6b7280); }
[data-theme="dark"] .apt-card { background: #1e2025; border-color: #33353c; }
</style>

<div class="page-header">
    <h1>All Image Style Templates</h1>
    <a href="pin-template-editor" class="btn-primary">+ Create New Template</a>
</div>
<div id="aptAlert"></div>

<?php if ($drafts): ?>
<div class="card">
    <h2 style="margin-top:0;">Drafts <span class="muted" style="font-weight:400;">(not published yet — users can't see them)</span></h2>
    <div class="apt-drafts">
        <?php foreach ($drafts as $d): ?>
        <div class="apt-draft" data-draft="<?= (int)$d['id'] ?>">
            <?php if ($d['thumb_path']): ?><img src="../<?= e($d['thumb_path']) ?>?t=<?= strtotime((string)$d['updated_at']) ?>" alt=""><?php endif; ?>
            <div><strong><?= e($d['name']) ?></strong><div class="muted" style="font-size:12px;"><?= e(format_datetime($d['updated_at'])) ?></div></div>
            <a class="btn-secondary btn-small" href="pin-template-editor?id=-<?= (int)$d['id'] ?>">Edit</a>
            <button type="button" class="btn-danger btn-small" data-del="<?= (int)$d['id'] ?>">Delete</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <p class="muted" style="margin-top:0;">Switched-off templates are hidden from users' <strong>Pin Templates &amp; Styles</strong>, the
        <strong>Classic Wizard</strong> and <strong>AI Auto</strong>. Pins already scheduled keep their image.
        Templates made by admins can be edited any time; a template with an <em>Only number</em> text is used only for titles that contain a number.</p>
    <div class="apt-tools">
        <input type="search" id="aptQ" placeholder="Search templates…">
        <select id="aptSource"><option value="">All sources</option><option value="default">Default templates</option><option value="admin">Made by admin (<?= $nAdmin ?>)</option></select>
        <select id="aptStatus"><option value="">Active &amp; inactive</option><option value="on">Active</option><option value="off">Inactive</option></select>
        <select id="aptCat"><option value="">All categories</option><?php foreach ($cats as $c => $n): ?><option value="<?= e($c) ?>"><?= e($c) ?> (<?= $n ?>)</option><?php endforeach; ?></select>
        <select id="aptLayout"><option value="">Single &amp; collage</option><option value="single">Single photo</option><option value="collage">Collage</option></select>
        <button type="button" class="btn-secondary btn-small" id="aptAllOn">Activate shown</button>
        <button type="button" class="btn-secondary btn-small" id="aptAllOff">Deactivate shown</button>
    </div>
    <div class="apt-count" id="aptCount" style="margin-top:10px;"><?= count($reg) ?> templates · <?= $nActive ?> active</div>
    <div class="apt-grid" id="aptGrid">
        <?php foreach ($reg as $k => $t):
            $isAdmin = !empty($t['admin']);
            $on = !empty($t['active']); ?>
        <div class="apt-card <?= $on ? '' : 'off' ?>" data-key="<?= e($k) ?>" data-src="<?= $isAdmin ? 'admin' : 'default' ?>" data-on="<?= $on ? 1 : 0 ?>"
             data-cat="<?= e($t['category']) ?>" data-layout="<?= e($t['layout']) ?>" data-q="<?= e(strtolower($t['name'] . ' ' . $t['category'] . ' ' . implode(' ', $t['tags'] ?? []) . ' ' . $k)) ?>">
            <div class="apt-img">
                <img loading="lazy" src="../pin-template-preview?s=<?= rawurlencode($k) ?>&v=<?= e($t['ver'] ?? $ver) ?>" alt="">
                <div class="apt-badges">
                    <?php if ($isAdmin): ?><span class="apt-badge admin">Admin</span><?php endif; ?>
                    <?php if (!empty($t['numbered'])): ?><span class="apt-badge num">🔢 Numbered</span><?php endif; ?>
                    <?php foreach (array_slice($t['tags'] ?? [], 0, 3) as $tag): ?><span class="apt-badge tag"><?= e($tag) ?></span><?php endforeach; ?>
                </div>
            </div>
            <div class="apt-body">
                <div class="apt-name"><?= e($t['name']) ?></div>
                <div class="apt-meta"><?= e(!empty($t['any_category']) ? 'Any category' : $t['category']) ?> · <?= $t['layout'] === 'collage' ? 'Collage · ' . (int)$t['photos'] . ' photos' : 'Single photo' ?><?= $isAdmin ? ' · Priority ' . (int)$t['priority'] : '' ?></div>
                <div class="apt-row">
                    <label class="apt-switch"><input type="checkbox" data-toggle="<?= e($k) ?>" <?= $on ? 'checked' : '' ?>> <span><?= $on ? 'Active' : 'Inactive' ?></span></label>
                    <?php if ($isAdmin): $id = (int)substr($k, 3); ?>
                    <div class="apt-acts">
                        <a class="btn-secondary btn-small" href="pin-template-editor?id=-<?= $id ?>">Edit</a>
                        <button type="button" class="btn-danger btn-small" data-del="<?= $id ?>" title="Delete">🗑</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const cards = [...document.querySelectorAll('.apt-card')];
    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('ajax-pin-templates', { method: 'POST', body: fd, credentials: 'same-origin' })).json(); }
        catch (e) { return { ok: false, error: 'Network error.' }; }
    }
    function alertMsg(m, type) { $('aptAlert').innerHTML = '<div class="alert alert-' + type + '">' + m + '</div>'; }
    function setCard(card, on) {
        card.dataset.on = on ? 1 : 0;
        card.classList.toggle('off', !on);
        const cb = card.querySelector('[data-toggle]');
        cb.checked = on;
        cb.nextElementSibling.textContent = on ? 'Active' : 'Inactive';
    }
    function shown() { return cards.filter((c) => c.style.display !== 'none'); }
    function filter() {
        const q = $('aptQ').value.trim().toLowerCase(), src = $('aptSource').value, st = $('aptStatus').value, cat = $('aptCat').value, lay = $('aptLayout').value;
        cards.forEach((c) => {
            const ok = (!q || c.dataset.q.includes(q)) && (!src || c.dataset.src === src) && (!st || (st === 'on') === (c.dataset.on === '1'))
                && (!cat || c.dataset.cat === cat) && (!lay || c.dataset.layout === lay);
            c.style.display = ok ? '' : 'none';
        });
        const s = shown();
        $('aptCount').textContent = s.length + ' shown · ' + cards.filter((c) => c.dataset.on === '1').length + ' of ' + cards.length + ' active';
    }
    ['aptQ', 'aptSource', 'aptStatus', 'aptCat', 'aptLayout'].forEach((id) => $(id).addEventListener(id === 'aptQ' ? 'input' : 'change', filter));
    document.addEventListener('change', async (e) => {
        const cb = e.target.closest('[data-toggle]');
        if (!cb) return;
        const card = cb.closest('.apt-card');
        const r = await post({ action: 'toggle', key: cb.dataset.toggle, active: cb.checked ? 1 : '' });
        if (r.ok) setCard(card, cb.checked); else { cb.checked = !cb.checked; alertMsg(r.error || 'Could not change it.', 'error'); }
        filter();
    });
    async function bulk(on) {
        const list = shown();
        if (!list.length || !confirm((on ? 'Activate ' : 'Deactivate ') + list.length + ' template(s)?')) return;
        const r = await post({ action: 'bulk_toggle', active: on ? 1 : '', keys: JSON.stringify(list.map((c) => c.dataset.key)) });
        if (r.ok) { list.forEach((c) => setCard(c, on)); alertMsg(r.count + ' template(s) ' + (on ? 'activated' : 'deactivated') + '.', 'success'); filter(); }
        else alertMsg(r.error || 'Could not update them.', 'error');
    }
    $('aptAllOn').addEventListener('click', () => bulk(true));
    $('aptAllOff').addEventListener('click', () => bulk(false));
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-del]');
        if (!b || !confirm('Delete this template for good? Pins already made with it keep their image.')) return;
        const r = await post({ action: 'delete', id: b.dataset.del });
        if (r.ok) { const el = b.closest('.apt-card') || b.closest('.apt-draft'); const i = cards.indexOf(el); if (i !== -1) cards.splice(i, 1); el.remove(); filter(); } else alertMsg(r.error || 'Could not delete it.', 'error');
    });
    filter();
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
