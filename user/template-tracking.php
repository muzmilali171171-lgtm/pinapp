<?php
/**
 * User → Analytics → Template Tracking.
 * How each template (Image Styles & Templates, Classic Wizard templates, custom designs) performs on
 * Pinterest, and inside a template, which main colours perform best. Data: user/ajax-template-tracking.php.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/pinterest_analytics_functions.php';
require_once __DIR__ . '/../includes/template_tracking_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'template-tracking';
$pageTitle = 'Template Tracking';

$accounts = pa_user_accounts($pdo, (int)$user['id']);
$connected = array_values(array_filter($accounts, fn($a) => $a['status'] === 'connected'));
$accountId = (int)($_GET['account'] ?? ($_SESSION['pa_account_id'] ?? 0));
$account = null;
foreach ($connected as $a) if ((int)$a['id'] === $accountId) $account = $a;
if (!$account && $connected) $account = $connected[0];
if ($account) $_SESSION['pa_account_id'] = (int)$account['id'];
tt_ensure_schema($pdo);

include __DIR__ . '/includes/user-header.php';
?>
<link rel="stylesheet" href="../assets/css/pinterest-analytics.css?v=<?= @filemtime(__DIR__ . '/../assets/css/pinterest-analytics.css') ?: time() ?>">
<style>
.tt-filters { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:14px; }
.tt-filters label { display:block; font-size:12px; font-weight:600; color:#4b5563; margin-bottom:4px; }
.tt-filters select, .tt-filters input[type=date] { padding:8px 10px; border:1px solid #d1d5db; border-radius:9px; font:inherit; background:#fff; }
.tt-ranges { display:flex; gap:4px; flex-wrap:wrap; }
.tt-ranges button { border:1px solid #d1d5db; background:#fff; border-radius:999px; padding:7px 14px; cursor:pointer; font-weight:600; font-size:13px; }
.tt-ranges button.on { background:#7c3aed; color:#fff; border-color:#7c3aed; }
.tt-summary { font-size:14px; color:#374151; margin:4px 0 12px; }
.tt-summary b { color:#111827; }
.tt-table-wrap { overflow-x:auto; }
.tt-table { width:100%; border-collapse:collapse; font-size:13.5px; min-width:900px; }
.tt-table th { text-align:left; font-size:12px; color:#6b7280; font-weight:700; padding:10px 8px; border-bottom:1px solid #e5e7eb; white-space:nowrap; cursor:pointer; user-select:none; }
.tt-table th.nosort { cursor:default; }
.tt-table th .arr { color:#9ca3af; margin-left:3px; }
.tt-table th.sorted .arr { color:#7c3aed; }
.tt-table td { padding:10px 8px; border-bottom:1px solid #f1f2f4; vertical-align:middle; }
.tt-table tr:hover td { background:#faf9ff; }
.tt-name { font-weight:600; color:#111827; }
.tt-src { display:inline-block; font-size:11px; padding:1px 8px; border-radius:999px; background:#f3f4f6; color:#4b5563; margin-top:3px; }
.tt-src.style { background:#ede9fe; color:#5b21b6; } .tt-src.cw { background:#dbeafe; color:#1e40af; } .tt-src.design { background:#fce7f3; color:#9d174d; }
.tt-dots { display:inline-flex; gap:3px; margin-left:6px; vertical-align:middle; }
.tt-dot { width:12px; height:12px; border-radius:50%; border:1px solid rgba(0,0,0,.15); display:inline-block; }
.tt-sw { width:22px; height:22px; border-radius:6px; border:1px solid rgba(0,0,0,.15); display:inline-block; vertical-align:middle; margin-right:8px; }
.tt-link { border:1px solid #d1d5db; background:#fff; border-radius:8px; padding:5px 10px; cursor:pointer; font-size:12.5px; font-weight:600; white-space:nowrap; }
.tt-link:hover { border-color:#7c3aed; color:#6d28d9; }
.tt-crumb { display:flex; align-items:center; gap:8px; margin-bottom:10px; font-size:14px; }
.tt-crumb a { color:#6d28d9; cursor:pointer; font-weight:600; }
.tt-progress { background:#f5f3ff; border:1px solid #ddd6fe; color:#5b21b6; border-radius:10px; padding:10px 14px; margin-bottom:12px; font-size:13.5px; }
.tt-modal { position:fixed; inset:0; background:rgba(17,24,39,.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
.tt-modal[hidden] { display:none; }
.tt-modal-box { background:#fff; border-radius:16px; width:100%; max-width:980px; max-height:88vh; display:flex; flex-direction:column; }
.tt-modal-head { display:flex; justify-content:space-between; align-items:center; padding:16px 18px; border-bottom:1px solid #eee; }
.tt-modal-body { overflow:auto; padding:16px 18px; }
.tt-pins { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; }
.tt-pin { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; font-size:12px; background:#fff; }
.tt-pin img { width:100%; aspect-ratio:2/3; object-fit:cover; display:block; background:#f3f4f6; }
.tt-pin div { padding:8px; }
.tt-pin .t { font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:4px; }
.tt-pin .m { color:#6b7280; }
.tt-empty { text-align:center; padding:30px 10px; color:#6b7280; }
</style>

<div class="pa-head">
    <div>
        <h1>Template Tracking</h1>
        <p>Which templates — and which colours — get the most views, clicks and saves on Pinterest.</p>
    </div>
    <?php if ($connected): ?>
    <form method="GET" class="pa-account-picker">
        <label for="ttAccount">Pinterest account</label>
        <select name="account" id="ttAccount" onchange="this.form.submit()">
            <?php foreach ($connected as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $account && (int)$a['id'] === (int)$account['id'] ? 'selected' : '' ?>><?= e($a['pinterest_username'] ? '@' . $a['pinterest_username'] : 'Account #' . $a['id']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if (!$connected): ?>
<div class="card"><div class="empty-state">
    <p style="font-size:16px; margin-top:0;">Connect a Pinterest account to track your templates.</p>
    <a href="connect-pinterest" class="btn-primary">Go to Pinterest Accounts</a>
</div></div>
<?php else: ?>
<div class="card">
    <div class="tt-filters">
        <div>
            <label>Date range</label>
            <div class="tt-ranges" id="ttRanges">
                <button type="button" data-range="life" class="on">Lifetime</button>
                <button type="button" data-range="30">Last 30 days</button>
                <button type="button" data-range="90">Last 90 days</button>
                <button type="button" data-range="custom">📅 Custom</button>
            </div>
        </div>
        <div id="ttCustom" hidden>
            <label>From – to</label>
            <input type="date" id="ttFrom" value="<?= e(date('Y-m-d', strtotime('-30 days'))) ?>"> <input type="date" id="ttTo" value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div>
            <label for="ttMin">Min. pins</label>
            <select id="ttMin"><option value="1">1+</option><option value="2">2+</option><option value="3">3+</option><option value="5">5+</option><option value="10">10+</option><option value="20">20+</option><option value="50">50+</option></select>
        </div>
        <div>
            <label for="ttSource">Templates from</label>
            <select id="ttSource">
                <option value="">All</option>
                <?php foreach (TT_SOURCES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px;">
            <button type="button" class="btn-secondary" id="ttAllColors">🎨 Colour stats (all pins)</button>
            <button type="button" class="btn-secondary" id="ttRefresh">↻ Refresh data</button>
        </div>
    </div>
    <div class="tt-progress" id="ttProgress" hidden></div>

    <div id="ttTemplatesView">
        <div class="tt-summary" id="ttSummary">Loading…</div>
        <div class="tt-table-wrap"><table class="tt-table" id="ttTable"></table></div>
    </div>
    <div id="ttColorsView" hidden>
        <div class="tt-crumb"><a id="ttBack">← All templates</a><span>/</span><b id="ttColorTitle"></b></div>
        <div class="tt-summary" id="ttColorSummary"></div>
        <div class="tt-table-wrap"><table class="tt-table" id="ttColorTable"></table></div>
    </div>
    <p class="muted" style="margin:14px 0 0; font-size:12.5px;">Views = impressions · Clicks = link clicks to your site · CTR = clicks ÷ views · Save % = saves ÷ views.
    Lifetime and 90 days come from your Pinterest pin stats; last 30 days and custom ranges use daily stats (Pinterest keeps 90 days of those).
    Pins are tracked from the template that made their image — Image Styles &amp; Templates and custom designs from now on, Classic Wizard pins including older ones.</p>
</div>

<div class="tt-modal" id="ttModal" hidden>
    <div class="tt-modal-box">
        <div class="tt-modal-head"><b id="ttModalTitle">Pins</b><button type="button" class="tt-link" id="ttModalClose">✕ Close</button></div>
        <div class="tt-modal-body"><div class="tt-pins" id="ttPins"></div></div>
    </div>
</div>

<script>
(function () {
    const CFG = { accountId: <?= (int)$account['id'] ?>, csrf: <?= json_encode(csrf_token()) ?> };
    const SRC = <?= json_encode(TT_SOURCES) ?>;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = (n, d) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: d == null ? 0 : d, minimumFractionDigits: 0 });
    const st = { range: 'life', sort: 'avg_views', dir: -1, csort: 'avg_views', cdir: -1, rows: [], crows: [], colorKey: null, colorTitle: '' };

    function params(extra) {
        const p = Object.assign({ account_id: CFG.accountId, range: st.range, min_pins: $('ttMin').value, source: $('ttSource').value }, extra || {});
        if (st.range === 'custom') { p.from = $('ttFrom').value; p.to = $('ttTo').value; }
        return p;
    }
    function get(action, extra) {
        const q = new URLSearchParams(Object.assign({ action }, params(extra)));
        return fetch('ajax-template-tracking?' + q, { credentials: 'same-origin' }).then((r) => r.json()).catch(() => ({ ok: false, error: 'Network error.' }));
    }
    function post(url, data) {
        const fd = new FormData();
        Object.entries(Object.assign({ account_id: CFG.accountId, csrf_token: CFG.csrf }, data)).forEach(([k, v]) => fd.append(k, v));
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.json()).catch(() => ({ ok: false, error: 'Network error.' }));
    }
    function progress(msg) { $('ttProgress').hidden = !msg; $('ttProgress').textContent = msg || ''; }

    /* ---------- data preparation: pins list → template links → daily stats ---------- */
    async function syncPins() {
        let page = 1, bookmark = '';
        for (;;) {
            progress('Syncing your pins from Pinterest… page ' + page);
            const r = await post('ajax-pa-sync', { step: 'pins', page, bookmark });
            if (!r.ok) { progress(''); alert(r.error || 'Pinterest sync failed.'); return false; }
            if (!r.bookmark) break;
            bookmark = r.bookmark; page++;
            if (page > 100) break;
        }
        await post('ajax-pa-sync', { step: 'finish' });
        return true;
    }
    async function resolvePins() {
        for (let i = 0; i < 200; i++) {
            const r = await post('ajax-template-tracking', { action: 'resolve' });
            if (!r.ok) { progress(''); alert(r.error || 'Could not read your pins.'); return; }
            if (!r.left) break;
            progress('Matching pins to their templates… ' + r.left + ' left');
        }
    }
    async function syncDaily() {
        let offset = 0;
        for (let i = 0; i < 500; i++) {
            const r = await post('ajax-template-tracking', { action: 'daily_sync', offset });
            if (!r.ok) { progress(''); alert(r.error || 'Could not load daily stats.'); return; }
            if (r.next == null) break;
            offset = r.next;
            progress('Loading daily stats… ' + Math.min(offset, r.total) + ' / ' + r.total + ' pins');
        }
    }
    async function load(force) {
        progress('Matching pins to their templates…');
        await resolvePins();
        let r = await get('stats');
        if (r.ok && (r.pins_need_sync || force)) { if (await syncPins()) r = await get('stats'); }
        if (r.ok && (r.daily_needs_sync || (force && (st.range === '30' || st.range === 'custom')))) { await syncDaily(); r = await get('stats'); }
        progress('');
        if (!r.ok) { $('ttSummary').textContent = r.error || 'Could not load stats.'; $('ttTable').innerHTML = ''; return; }
        st.rows = r.rows;
        $('ttSummary').innerHTML = `Showing stats for <b>${fmt(r.total_pins)}</b> pins across <b>${fmt(r.templates)}</b> template${r.templates === 1 ? '' : 's'}` + (r.all_pins > r.total_pins ? ` <span class="muted">(${fmt(r.all_pins - r.total_pins)} pins hidden by “Min. pins”)</span>` : '');
        renderTable();
        if (!$('ttColorsView').hidden && st.colorKey) openColors(st.colorKey, st.colorTitle, true);
    }

    /* ---------- tables ---------- */
    const COLS = [['avg_views', 'Avg. Views'], ['clicks', 'Total Clicks'], ['avg_clicks', 'Avg. Clicks'], ['ctr', 'Avg. CTR'], ['save_pct', 'Avg. Save %']];
    function head(first, sort, dir, extra) {
        const th = (k, label) => `<th data-sort="${k}" class="${sort === k ? 'sorted' : ''}">${label}<span class="arr">${sort === k ? (dir < 0 ? '↓' : '↑') : '⇅'}</span></th>`;
        return `<tr>${th('name', first)}${th('pins', 'Total Pins')}${th('views', 'Total Views')}${COLS.map(([k, l]) => th(k, l)).join('')}<th class="nosort">Link</th>${extra || ''}</tr>`;
    }
    function sortRows(rows, key, dir) {
        return rows.slice().sort((a, b) => (key === 'name' ? String(a.name).localeCompare(String(b.name)) : (a[key] - b[key])) * dir);
    }
    function metricCells(r) {
        return `<td>${fmt(r.pins)}</td><td>${fmt(r.views)}</td><td>${fmt(r.avg_views, 1)}</td><td>${fmt(r.clicks)}</td><td>${fmt(r.avg_clicks, 2)}</td><td>${fmt(r.ctr, 2)}%</td><td>${fmt(r.save_pct, 2)}%</td>`;
    }
    function renderTable() {
        const rows = sortRows(st.rows, st.sort, st.dir);
        $('ttTable').innerHTML = head('Template name', st.sort, st.dir, '<th class="nosort">Colours</th>') + (rows.length ? rows.map((r) => `<tr>
            <td><div class="tt-name">${esc(r.name)}${r.top_colors.length ? `<span class="tt-dots">${r.top_colors.map((c) => `<span class="tt-dot" title="${esc(c.name)}" style="background:${c.hex}"></span>`).join('')}</span>` : ''}</div>
                <span class="tt-src ${esc(r.source)}">${esc(SRC[r.source] || r.source)}</span></td>
            ${metricCells(r)}
            <td><button type="button" class="tt-link" data-pins="${esc(r.key)}" data-title="${esc(r.name)}">View pins</button></td>
            <td><button type="button" class="tt-link" data-colors="${esc(r.key)}" data-title="${esc(r.name)}">🎨 Colour stats</button></td>
        </tr>`).join('') : `<tr><td colspan="11" class="tt-empty">No tracked pins yet for this account and filter. Publish pins made with a template (Image Styles &amp; Templates, Classic Wizard or a custom design) and they'll appear here.</td></tr>`);
    }
    async function openColors(key, title, silent) {
        st.colorKey = key; st.colorTitle = title;
        $('ttTemplatesView').hidden = true; $('ttColorsView').hidden = false;
        $('ttColorTitle').textContent = title + ' — colours';
        if (!silent) $('ttColorSummary').textContent = 'Loading…';
        const r = await get('colors', { key });
        if (!r.ok) { $('ttColorSummary').textContent = r.error || 'Could not load.'; return; }
        st.crows = r.rows;
        $('ttColorSummary').innerHTML = `Showing stats for <b>${fmt(r.total_pins)}</b> pins across <b>${fmt(r.rows.length)}</b> main colour${r.rows.length === 1 ? '' : 's'}`;
        renderColors();
    }
    function renderColors() {
        const rows = sortRows(st.crows, st.csort, st.cdir);
        $('ttColorTable').innerHTML = head('Main color', st.csort, st.cdir) + (rows.length ? rows.map((r) => `<tr>
            <td><span class="tt-sw" style="background:${r.color_hex}"></span><span class="tt-name">${esc(r.name)}</span></td>
            ${metricCells(r)}
            <td><button type="button" class="tt-link" data-pins="${esc(st.colorKey)}" data-color="${esc(r.name)}" data-title="${esc(st.colorTitle + ' · ' + r.name)}">View pins</button></td>
        </tr>`).join('') : '<tr><td colspan="9" class="tt-empty">No pins.</td></tr>');
    }
    async function openPins(key, color, title) {
        $('ttModalTitle').textContent = title;
        $('ttPins').innerHTML = '<p class="muted">Loading…</p>';
        $('ttModal').hidden = false;
        const r = await get('pins', { key, color: color || '' });
        if (!r.ok) { $('ttPins').innerHTML = `<p class="muted">${esc(r.error || 'Could not load.')}</p>`; return; }
        $('ttPins').innerHTML = r.pins.length ? r.pins.map((p) => `<div class="tt-pin">
            <a href="${esc(p.url)}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(p.image)}" alt=""></a>
            <div><div class="t" title="${esc(p.title)}">${esc(p.title || 'Pin ' + p.pin_id)}</div>
            <div class="m">👁 ${fmt(p.views)} · 🔗 ${fmt(p.clicks)} · 📌 ${fmt(p.saves)}</div>
            <div class="m">CTR ${fmt(p.ctr, 2)}% ${p.color_hex ? `<span class="tt-dot" style="background:${p.color_hex};vertical-align:middle" title="${esc(p.color)}"></span>` : ''}</div>
            <div class="m"><a href="${esc(p.url)}" target="_blank" rel="noopener">Open on Pinterest ↗</a></div></div>
        </div>`).join('') + (r.total > r.pins.length ? `<p class="muted" style="grid-column:1/-1">Showing the top ${r.pins.length} of ${r.total} pins.</p>` : '') : '<p class="muted">No pins.</p>';
    }

    /* ---------- events ---------- */
    $('ttRanges').addEventListener('click', (e) => {
        const b = e.target.closest('[data-range]'); if (!b) return;
        st.range = b.dataset.range;
        document.querySelectorAll('#ttRanges button').forEach((x) => x.classList.toggle('on', x === b));
        $('ttCustom').hidden = st.range !== 'custom';
        load();
    });
    ['ttFrom', 'ttTo', 'ttMin', 'ttSource'].forEach((id) => $(id).addEventListener('change', () => load()));
    $('ttRefresh').addEventListener('click', () => load(true));
    $('ttAllColors').addEventListener('click', () => openColors('all', 'All tracked pins'));
    $('ttBack').addEventListener('click', () => { $('ttColorsView').hidden = true; $('ttTemplatesView').hidden = false; st.colorKey = null; });
    $('ttTable').addEventListener('click', (e) => {
        const h = e.target.closest('th[data-sort]');
        if (h) { const k = h.dataset.sort; st.dir = st.sort === k ? -st.dir : (k === 'name' ? 1 : -1); st.sort = k; return renderTable(); }
        const c = e.target.closest('[data-colors]'); if (c) return openColors(c.dataset.colors, c.dataset.title);
        const p = e.target.closest('[data-pins]'); if (p) return openPins(p.dataset.pins, '', p.dataset.title);
    });
    $('ttColorTable').addEventListener('click', (e) => {
        const h = e.target.closest('th[data-sort]');
        if (h) { const k = h.dataset.sort; st.cdir = st.csort === k ? -st.cdir : (k === 'name' ? 1 : -1); st.csort = k; return renderColors(); }
        const p = e.target.closest('[data-pins]'); if (p) return openPins(p.dataset.pins, p.dataset.color, p.dataset.title);
    });
    $('ttModalClose').addEventListener('click', () => { $('ttModal').hidden = true; });
    $('ttModal').addEventListener('click', (e) => { if (e.target === $('ttModal')) $('ttModal').hidden = true; });
    load();
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
