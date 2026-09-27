<?php
/**
 * Admin → Canva → Add Elements.
 * Choose the element type (Shapes / Graphics / Emoji / 3D / Frames = the tabs users see in the editor's
 * Elements panel), choose or type a sub category, then upload one or many files (SVG, PNG, JPG, WEBP, GIF).
 * Uploaded elements appear for every user on top of the built-in ones.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'design-elements';
$pageTitle = 'Canva — Add Elements';
$msg = null; $err = null;
$dir = __DIR__ . '/../uploads/design-elements/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$types = DESIGN_ELEMENT_TYPES;
$cleanType = fn($t) => isset($types[$t]) ? $t : 'graphics';
$cleanSub = fn($s) => mb_substr(trim((string)$s), 0, 100) ?: 'General';

try {
    design_ensure_element_type($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = $_POST['act'] ?? '';
        if ($act === 'upload') {
            $etype = $cleanType($_POST['element_type'] ?? '');
            $sub = ($_POST['sub_category'] ?? '') === '__new__' ? ($_POST['new_sub_category'] ?? '') : ($_POST['sub_category'] ?? '');
            $sub = $cleanSub($sub);
            $files = $_FILES['files'] ?? null;
            $n = ($files && is_array($files['name'])) ? count($files['name']) : 0;
            if (!$n || ($n === 1 && $files['error'][0] === UPLOAD_ERR_NO_FILE)) {
                $err = 'Please choose at least one file.';
            } else {
                $added = 0; $bad = [];
                for ($i = 0; $i < $n; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > 8 * 1024 * 1024) { $bad[] = $files['name'][$i]; continue; }
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    $base = trim(preg_replace('/[^A-Za-z0-9 _-]+/', ' ', pathinfo($files['name'][$i], PATHINFO_FILENAME)));
                    $name = mb_substr(($n === 1 ? trim($_POST['name'] ?? '') : '') ?: ucwords(str_replace(['_', '-'], ' ', $base)) ?: 'Element', 0, 255);
                    $fname = 'el_' . bin2hex(random_bytes(8));
                    if ($ext === 'svg') {
                        $svg = design_sanitize_svg((string)file_get_contents($files['tmp_name'][$i]));
                        if (!$svg) { $bad[] = $files['name'][$i]; continue; }
                        file_put_contents($dir . $fname . '.svg', $svg);
                        $type = 'svg'; $fname .= '.svg';
                    } else {
                        $info = @getimagesize($files['tmp_name'][$i]);
                        $map = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
                        if (!$info || !isset($map[$info[2]])) { $bad[] = $files['name'][$i]; continue; }
                        $type = $map[$info[2]]; $fname .= '.' . $type;
                        move_uploaded_file($files['tmp_name'][$i], $dir . $fname);
                        @chmod($dir . $fname, 0644);
                    }
                    $pdo->prepare("INSERT INTO design_elements (name, element_type, category, file_path, file_type) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$name, $etype, $sub, 'uploads/design-elements/' . $fname, $type]);
                    $added++;
                }
                $msg = "$added element(s) added to {$types[$etype]['label']} → $sub." . ($bad ? ' Skipped: ' . implode(', ', $bad) . ' (unsupported or larger than 8 MB).' : '');
            }
        } elseif ($act === 'toggle') {
            $pdo->prepare("UPDATE design_elements SET status = IF(status = 'active', 'hidden', 'active') WHERE id = ?")->execute([(int)$_POST['id']]);
        } elseif ($act === 'rename') {
            $pdo->prepare("UPDATE design_elements SET name = ?, element_type = ?, category = ? WHERE id = ?")
                ->execute([mb_substr(trim($_POST['name'] ?? ''), 0, 255) ?: 'Element', $cleanType($_POST['element_type'] ?? ''), $cleanSub($_POST['category'] ?? ''), (int)$_POST['id']]);
            $msg = 'Element updated.';
        } elseif ($act === 'delete') {
            $st = $pdo->prepare("SELECT file_path FROM design_elements WHERE id = ?");
            $st->execute([(int)$_POST['id']]);
            if ($p = $st->fetchColumn()) {
                if (strpos($p, 'uploads/design-elements/') === 0 && is_file(__DIR__ . '/../' . $p)) @unlink(__DIR__ . '/../' . $p);
                $pdo->prepare("DELETE FROM design_elements WHERE id = ?")->execute([(int)$_POST['id']]);
            }
            $msg = 'Element deleted.';
        }
    }

    $fType = isset($types[$_GET['type'] ?? '']) ? $_GET['type'] : '';
    $fSub = trim($_GET['sub'] ?? '');
    // sub categories: suggested + everything already used, per type
    $subsByType = [];
    foreach ($types as $k => $t) $subsByType[$k] = $t['subs'];
    foreach ($pdo->query("SELECT element_type, category, COUNT(*) c FROM design_elements GROUP BY element_type, category ORDER BY category")->fetchAll() as $r) {
        $k = isset($types[$r['element_type']]) ? $r['element_type'] : 'graphics';
        if (!in_array($r['category'], $subsByType[$k], true)) $subsByType[$k][] = $r['category'];
    }
    $typeCounts = [];
    foreach ($pdo->query("SELECT element_type, COUNT(*) c FROM design_elements GROUP BY element_type")->fetchAll() as $r) $typeCounts[$r['element_type']] = (int)$r['c'];
    $subCounts = [];
    if ($fType) {
        $st = $pdo->prepare("SELECT category, COUNT(*) c FROM design_elements WHERE element_type = ? GROUP BY category ORDER BY category");
        $st->execute([$fType]);
        $subCounts = $st->fetchAll();
    }
    $where = []; $args = [];
    if ($fType) { $where[] = 'element_type = ?'; $args[] = $fType; }
    if ($fType && $fSub !== '') { $where[] = 'category = ?'; $args[] = $fSub; }
    $st = $pdo->prepare("SELECT * FROM design_elements" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT 1000");
    $st->execute($args);
    $els = $st->fetchAll();
} catch (PDOException $e) {
    $err = 'The design tables are missing — run migrate.php once.';
    $els = []; $subsByType = array_map(fn($t) => $t['subs'], $types); $typeCounts = []; $subCounts = []; $fType = ''; $fSub = '';
}

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.el-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:12px; }
.el-card { border:1px solid #e5e7eb; border-radius:12px; padding:10px; background:#fff; }
.el-card.hidden-el { opacity:.5; }
.el-img { height:110px; display:flex; align-items:center; justify-content:center; background:repeating-conic-gradient(#f3f4f6 0 25%, #fff 0 50%) 0 0/16px 16px; border-radius:8px; margin-bottom:8px; }
.el-img img { max-width:100%; max-height:100%; }
.el-card input, .el-card select { width:100%; font-size:12px; padding:4px 6px; margin-bottom:4px; }
.el-card .el-actions { display:flex; gap:4px; flex-wrap:wrap; }
.el-card button { font-size:12px; padding:4px 8px; }
.el-tabs, .el-cats { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
.el-tabs a, .el-cats a { padding:5px 12px; border:1px solid #e5e7eb; border-radius:999px; text-decoration:none; font-size:13px; color:#374151; }
.el-tabs a.on, .el-cats a.on { background:#7c3aed; color:#fff; border-color:#7c3aed; }
.el-typebadge { display:inline-block; font-size:11px; padding:1px 7px; border-radius:999px; background:#ede9fe; color:#5b21b6; margin-bottom:4px; }
.el-steps { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; }
@media (max-width: 900px) { .el-steps { grid-template-columns:1fr; } }
.el-steps .step-num { display:inline-flex; width:22px; height:22px; border-radius:50%; background:#7c3aed; color:#fff; font-size:12px; align-items:center; justify-content:center; margin-right:6px; }
</style>
<div class="page-header"><h1>Canva — Add Elements</h1></div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;">Add elements</h3>
    <p class="muted">Users see these in the design editor under <strong>Elements</strong>, in the tab and sub category you choose — on top of the built-in shapes, graphics, emoji, 3D and frames.
    SVG, PNG (transparent works best), JPG, WEBP or GIF, up to 8 MB each. SVGs are cleaned of scripts automatically.</p>
    <form method="post" enctype="multipart/form-data" id="elUploadForm">
        <input type="hidden" name="act" value="upload">
        <div class="el-steps">
            <div class="form-row">
                <label><span class="step-num">1</span>Select element type</label>
                <select name="element_type" id="elType" required>
                    <?php foreach ($types as $k => $t): ?><option value="<?= e($k) ?>" <?= $fType === $k ? 'selected' : '' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label><span class="step-num">2</span>Select sub category</label>
                <select name="sub_category" id="elSub" required></select>
                <input type="text" name="new_sub_category" id="elNewSub" placeholder="New sub category name" maxlength="100" style="margin-top:6px; display:none;">
            </div>
            <div class="form-row">
                <label><span class="step-num">3</span>Add file(s)</label>
                <input type="file" name="files[]" multiple accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp,image/gif" required>
                <input type="text" name="name" placeholder="Name (optional, single file — default: file name)" maxlength="255" style="margin-top:6px;">
            </div>
        </div>
        <button type="submit" class="btn-primary">⬆ Upload Element</button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;">Uploaded elements (<?= count($els) ?>)</h3>
    <div class="el-tabs">
        <a href="design-elements" class="<?= $fType === '' ? 'on' : '' ?>">All</a>
        <?php foreach ($types as $k => $t): ?><a href="design-elements?type=<?= urlencode($k) ?>" class="<?= $fType === $k ? 'on' : '' ?>"><?= e($t['label']) ?> (<?= (int)($typeCounts[$k] ?? 0) ?>)</a><?php endforeach; ?>
    </div>
    <?php if ($fType && $subCounts): ?>
    <div class="el-cats">
        <a href="design-elements?type=<?= urlencode($fType) ?>" class="<?= $fSub === '' ? 'on' : '' ?>">All <?= e($types[$fType]['label']) ?></a>
        <?php foreach ($subCounts as $c): ?><a href="design-elements?type=<?= urlencode($fType) ?>&amp;sub=<?= urlencode($c['category']) ?>" class="<?= $fSub === $c['category'] ? 'on' : '' ?>"><?= e($c['category']) ?> (<?= (int)$c['c'] ?>)</a><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!$els): ?><p class="muted">No uploaded elements here yet. (Built-in elements are always available in the editor.)</p><?php endif; ?>
    <div class="el-grid">
        <?php foreach ($els as $el): $et = isset($types[$el['element_type'] ?? '']) ? $el['element_type'] : 'graphics'; ?>
        <div class="el-card <?= $el['status'] === 'hidden' ? 'hidden-el' : '' ?>">
            <div class="el-img"><img loading="lazy" src="../<?= e($el['file_path']) ?>" alt=""></div>
            <span class="el-typebadge"><?= e($types[$et]['label']) ?> · <?= e($el['category']) ?></span>
            <form method="post">
                <input type="hidden" name="id" value="<?= (int)$el['id'] ?>">
                <input type="text" name="name" value="<?= e($el['name']) ?>" title="Name">
                <select name="element_type" title="Element type">
                    <?php foreach ($types as $k => $t): ?><option value="<?= e($k) ?>" <?= $et === $k ? 'selected' : '' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
                </select>
                <input type="text" name="category" value="<?= e($el['category']) ?>" title="Sub category" list="elSubs_<?= e($et) ?>">
                <div class="el-actions">
                    <button type="submit" name="act" value="rename" class="btn-secondary">Save</button>
                    <button type="submit" name="act" value="toggle" class="btn-secondary"><?= $el['status'] === 'active' ? 'Hide' : 'Show' ?></button>
                    <button type="submit" name="act" value="delete" class="btn-secondary" onclick="return confirm('Delete this element?')">Delete</button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="card">
    <h3 style="margin-top:0;">Built-in elements <span class="muted" style="font-weight:400;">— included with the editor, always available to users</span></h3>
    <div class="el-tabs" id="biTabs">
        <?php foreach ($types as $k => $t): ?><a href="#" data-bitype="<?= e($k) ?>"><?= e($t['label']) ?> <span class="bi-count" data-bicount="<?= e($k) ?>"></span></a><?php endforeach; ?>
    </div>
    <div class="el-cats" id="biCats"></div>
    <div class="el-grid bi-grid" id="biGrid"></div>
    <p class="muted" id="biNote" style="margin-bottom:0;"></p>
</div>
<style>
.bi-grid { grid-template-columns:repeat(auto-fill, minmax(96px, 1fr)); }
.bi-cell { border:1px solid #e5e7eb; border-radius:10px; background:#fff; padding:6px; text-align:center; font-size:11px; color:#6b7280; overflow:hidden; }
.bi-cell .bi-img { height:70px; display:flex; align-items:center; justify-content:center; }
.bi-cell img, .bi-cell svg { max-width:100%; max-height:70px; }
.bi-cell .bi-emoji { font-size:40px; line-height:1; }
.bi-cell div.bi-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:4px; }
</style>
<script charset="utf-8" src="../assets/js/design-elements-data.js?v=<?= @filemtime(__DIR__ . '/../assets/js/design-elements-data.js') ?: 1 ?>"></script>
<script>
(function () {
    const LIB = window.DE_LIB || {};
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const seg = window.Intl && Intl.Segmenter ? new Intl.Segmenter('en', { granularity: 'grapheme' }) : null;
    const split = (s) => seg ? [...seg.segment(s)].map((x) => x.segment) : Array.from(s);
    const FRAMES = ['Square', 'Portrait 4:5', 'Portrait 2:3', 'Landscape 3:2', 'Wide 16:9', 'Strip', 'Rounded square', 'Rounded portrait', 'Circle', 'Oval', 'Arch', 'Wide arch',
        'Triangle', 'Diamond', 'Pentagon', 'Hexagon', 'Octagon', 'Star', 'Heart', 'Blob', 'Cloud', 'Ticket', 'Scalloped', 'Leaf'];
    const data = {
        shapes: (LIB.shapes || []).flatMap((g) => g.items.map((it) => ({ cat: g.cat, name: it.n, shape: it }))),
        graphics: (LIB.graphics || []).map((g) => ({ cat: g.cat, name: g.name, path: g.path })),
        emoji: (LIB.emoji || []).flatMap(([cat, s]) => split(s).map((e) => ({ cat, name: e, ch: e }))),
        '3d': (LIB['3d'] || []).map((g) => ({ cat: g.cat, name: g.name, path: g.path })),
        frames: FRAMES.map((n, i) => ({ cat: i < 8 ? 'Photo frames' : 'Shape frames', name: n + ' frame' })),
    };
    let type = <?= json_encode($fType ?: 'graphics') ?>, cat = '';
    Object.keys(data).forEach((k) => { const el = document.querySelector(`[data-bicount="${k}"]`); if (el) el.textContent = '(' + data[k].length + ')'; });
    function cell(it) {
        let inner;
        if (it.ch) inner = `<span class="bi-emoji">${it.ch}</span>`;
        else if (it.path) inner = `<img loading="lazy" src="../${esc(it.path)}" alt="">`;
        else if (it.shape) { const s = it.shape; inner = `<svg data-auto viewBox="0 0 300 300"><path d="${esc(s.d)}" fill="${s.f || 'none'}" ${s.s ? `stroke="${s.s}" stroke-width="${s.sw || 6}"` : ''} fill-rule="${s.rule || 'nonzero'}"/></svg>`; }
        else inner = '<span style="font-size:30px">▢</span>';
        return `<div class="bi-cell" title="${esc(it.name)}"><div class="bi-img">${inner}</div><div class="bi-name">${esc(it.name)}</div></div>`;
    }
    function render() {
        document.querySelectorAll('[data-bitype]').forEach((a) => a.classList.toggle('on', a.dataset.bitype === type));
        const list = data[type] || [];
        const cats = [...new Set(list.map((x) => x.cat))];
        if (cat && !cats.includes(cat)) cat = '';
        document.getElementById('biCats').innerHTML = `<a href="#" data-bicat="" class="${cat === '' ? 'on' : ''}">All</a>` + cats.map((c) => `<a href="#" data-bicat="${esc(c)}" class="${cat === c ? 'on' : ''}">${esc(c)} (${list.filter((x) => x.cat === c).length})</a>`).join('');
        const shown = list.filter((x) => !cat || x.cat === cat);
        const grid = document.getElementById('biGrid');
        grid.innerHTML = shown.map(cell).join('');
        grid.querySelectorAll('svg[data-auto]').forEach((svg) => { try { const b = svg.firstElementChild.getBBox(); svg.setAttribute('viewBox', `${b.x - 12} ${b.y - 12} ${b.width + 24} ${b.height + 24}`); } catch (e) {} });
        document.getElementById('biNote').textContent = shown.length + ' built-in ' + (type === '3d' ? '3D' : type) + ' element(s). Your uploads for this type appear next to these in the editor.';
    }
    document.getElementById('biTabs').addEventListener('click', (e) => { const a = e.target.closest('[data-bitype]'); if (!a) return; e.preventDefault(); type = a.dataset.bitype; cat = ''; render(); });
    document.getElementById('biCats').addEventListener('click', (e) => { const a = e.target.closest('[data-bicat]'); if (!a) return; e.preventDefault(); cat = a.dataset.bicat; render(); });
    render();
})();
</script>

<?php foreach ($subsByType as $k => $subs): ?>
<datalist id="elSubs_<?= e($k) ?>"><?php foreach ($subs as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
<?php endforeach; ?>
<script>
(function () {
    const SUBS = <?= json_encode($subsByType, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    const PRESET_SUB = <?= json_encode($fSub) ?>;
    const typeSel = document.getElementById('elType'), subSel = document.getElementById('elSub'), newSub = document.getElementById('elNewSub');
    function fill() {
        const list = SUBS[typeSel.value] || [];
        subSel.innerHTML = list.map((s) => `<option>${s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))}</option>`).join('')
            + '<option value="__new__">➕ New sub category…</option>';
        if (PRESET_SUB && list.includes(PRESET_SUB)) subSel.value = PRESET_SUB;
        toggleNew();
    }
    function toggleNew() {
        const on = subSel.value === '__new__';
        newSub.style.display = on ? 'block' : 'none';
        newSub.required = on;
        if (on) newSub.focus();
    }
    typeSel.addEventListener('change', fill);
    subSel.addEventListener('change', toggleNew);
    fill();
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
