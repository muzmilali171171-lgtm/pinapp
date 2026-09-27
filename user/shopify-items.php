<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'shopify-items';

$site = get_user_website($pdo, (int)($_GET['website_id'] ?? 0), (int)$user['id']);
if (!$site || $site['platform'] !== 'shopify') {
    flash_set('error', 'Shopify store not found.');
    redirect('shopify-stores');
}
if (website_connection_state($site) !== 'connected') {
    flash_set('error', 'This store is unconnected. Reconnect it first.');
    redirect('shopify-stores?connect_id=' . (int)$site['id']);
}

$isBlogs = ($_GET['type'] ?? 'products') === 'blogs';
$itemType = $isBlogs ? 'blog' : 'product';
$pageTitle = ($isBlogs ? 'Blogs' : 'Products') . ' — ' . ($site['site_name'] ?: $site['site_url']);

$crawlSite = website_get_crawl_site($pdo, $site);

$stmt = $pdo->prepare("SELECT * FROM crawl_pages WHERE crawl_site_id = ? AND item_type = ? ORDER BY meta_title ASC, url ASC");
$stmt->execute([$crawlSite['id'], $itemType]);
$items = $stmt->fetchAll();

// Everything ticked across the whole store (products + blogs + any scanned pages) is what Automate Pin will use.
$stmt = $pdo->prepare("SELECT item_type, COUNT(*) c FROM crawl_pages WHERE crawl_site_id = ? AND active = 1 GROUP BY item_type");
$stmt->execute([$crawlSite['id']]);
$selectedByType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$selectedElsewhere = array_sum($selectedByType) - (int)($selectedByType[$itemType] ?? 0);

$base = 'shopify-items?website_id=' . (int)$site['id'];

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1><?= $isBlogs ? 'Blogs' : 'Products' ?> <span class="muted">— <?= e($site['site_name'] ?: $site['site_url']) ?></span></h1>
    <a href="shopify-stores" class="btn-secondary">← Shopify Stores</a>
</div>

<?= flash_render() ?>
<div id="alertBox"></div>

<div class="tab-row">
    <a href="<?= e($base) ?>&type=products" class="btn-secondary btn-small <?= !$isBlogs ? 'active' : '' ?>">Products</a>
    <a href="<?= e($base) ?>&type=blogs" class="btn-secondary btn-small <?= $isBlogs ? 'active' : '' ?>">Blogs</a>
</div>

<div class="card">
    <div class="page-header" style="margin-bottom:14px;">
        <h2 style="margin:0;"><?= $isBlogs ? 'Blog posts' : 'Products' ?> <span class="muted">(<?= count($items) ?>)</span></h2>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="btn-secondary" id="syncBtn">↻ Sync from Shopify</button>
            <button type="button" class="btn-primary" id="automateBtn">Automate Pin</button>
        </div>
    </div>

    <p class="muted" style="margin-top:0;">Tick the <?= $isBlogs ? 'blog posts' : 'products' ?> you want pinned. <strong>Automate Pin</strong> then lets you set the boards, daily pace and
    pin style — every selected item gets its own set of pins, published daily.</p>

    <?php if (empty($items)): ?>
        <div class="empty-state" id="emptyState">No <?= $isBlogs ? 'blog posts' : 'products' ?> loaded yet — <span id="emptyHint">fetching from Shopify…</span></div>
    <?php else: ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <span class="muted"><span id="selectedCount">0</span> of <?= count($items) ?> selected here<?php if ($selectedElsewhere > 0): ?> · <strong><?= (int)$selectedElsewhere ?></strong> more selected in other lists<?php endif; ?></span>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <input type="text" id="filterInput" placeholder="Search…" style="width:200px;">
            <button type="button" class="btn-secondary btn-small" id="selectAllBtn">Select All</button>
            <button type="button" class="btn-secondary btn-small" id="clearAllBtn">Clear</button>
            <button type="button" class="btn-primary btn-small" id="saveBtn">Save Selection</button>
        </div>
    </div>

    <table id="itemsTable">
        <tr><th style="width:36px;"></th><th style="width:60px;"></th><th><?= $isBlogs ? 'Title' : 'Product' ?></th><th>URL</th></tr>
        <?php foreach ($items as $it): ?>
        <tr data-search="<?= e(mb_strtolower(($it['meta_title'] ?? '') . ' ' . $it['url'])) ?>">
            <td><input type="checkbox" class="item-checkbox" value="<?= (int)$it['id'] ?>" <?= $it['active'] ? 'checked' : '' ?>></td>
            <td><?php if (!empty($it['image_url'])): ?><img class="item-thumb" src="<?= e($it['image_url']) ?>" alt="" loading="lazy"><?php else: ?><div class="item-thumb-empty"></div><?php endif; ?></td>
            <td><?= e($it['meta_title'] ?: '—') ?></td>
            <td><a href="<?= e($it['url']) ?>" target="_blank" rel="noopener"><?= e($it['url']) ?></a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<script>
const WEBSITE_ID = <?= (int)$site['id'] ?>;
const ITEM_TYPE = <?= json_encode($itemType) ?>;
const CSRF = <?= json_encode(csrf_token()) ?>;
const HAS_ITEMS = <?= empty($items) ? 'false' : 'true' ?>;

const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '"></div>';
    alertBox.firstChild.textContent = message;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
async function post(fields) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('website_id', WEBSITE_ID);
    Object.keys(fields).forEach(k => fd.append(k, fields[k]));
    const res = await fetch('ajax-shopify-items', { method: 'POST', body: fd });
    return res.json();
}

async function syncItems(auto) {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true; btn.textContent = 'Syncing…';
    try {
        const r = await post({ action: 'sync', type: ITEM_TYPE });
        if (r.ok) {
            let msg = 'Synced ' + r.total + ' item(s), ' + r.added + ' new.';
            if (r.skipped) msg += ' ' + r.skipped + ' product(s) skipped because they are not published to the Online Store.';
            sessionStorage.setItem('shopifySyncMsg', msg);
            window.location.reload();
            return;
        }
        showAlert('Sync failed: ' + r.error, 'error');
        const hint = document.getElementById('emptyHint');
        if (hint) hint.textContent = 'sync failed — see the message above.';
    } catch (e) {
        showAlert('Network error while syncing.', 'error');
    }
    btn.disabled = false; btn.textContent = '↻ Sync from Shopify';
}

function checkedIds(state) {
    return Array.from(document.querySelectorAll('.item-checkbox' + (state ? ':checked' : ':not(:checked)'))).map(cb => cb.value);
}
async function saveSelection() {
    return post({ action: 'select', active_ids: JSON.stringify(checkedIds(true)), inactive_ids: JSON.stringify(checkedIds(false)) });
}
function updateCount() {
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = document.querySelectorAll('.item-checkbox:checked').length;
}

document.getElementById('syncBtn').addEventListener('click', () => syncItems(false));

document.querySelectorAll('.item-checkbox').forEach(cb => cb.addEventListener('change', updateCount));
updateCount();

const selAll = document.getElementById('selectAllBtn');
if (selAll) selAll.addEventListener('click', () => {
    document.querySelectorAll('#itemsTable tr:not([style*="display: none"]) .item-checkbox').forEach(cb => cb.checked = true);
    updateCount();
});
const clrAll = document.getElementById('clearAllBtn');
if (clrAll) clrAll.addEventListener('click', () => { document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false); updateCount(); });

const filterInput = document.getElementById('filterInput');
if (filterInput) filterInput.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#itemsTable tr[data-search]').forEach(tr => {
        tr.style.display = (!q || tr.dataset.search.indexOf(q) !== -1) ? '' : 'none';
    });
});

const saveBtn = document.getElementById('saveBtn');
if (saveBtn) saveBtn.addEventListener('click', async () => {
    saveBtn.disabled = true; saveBtn.textContent = 'Saving…';
    try {
        const r = await saveSelection();
        showAlert(r.ok ? 'Selection saved.' : ('Could not save: ' + r.error), r.ok ? 'success' : 'error');
    } catch (e) { showAlert('Network error while saving.', 'error'); }
    saveBtn.disabled = false; saveBtn.textContent = 'Save Selection';
});

document.getElementById('automateBtn').addEventListener('click', async function () {
    if (HAS_ITEMS) {
        this.disabled = true;
        try {
            const r = await saveSelection();
            if (!r.ok) { showAlert('Could not save the selection: ' + r.error, 'error'); this.disabled = false; return; }
            const total = Object.values(r.selected || {}).reduce((a, b) => a + parseInt(b, 10), 0);
            if (!total) { showAlert('Tick at least one item first.', 'error'); this.disabled = false; return; }
            window.location.href = 'auto-website-create?site_id=' + r.crawl_site_id;
        } catch (e) { showAlert('Network error.', 'error'); this.disabled = false; }
    } else {
        showAlert('Nothing to automate yet — sync from Shopify first.', 'error');
    }
});

// Show the result of the last sync (the page reloads after syncing), and auto-sync a store that has nothing loaded yet.
(function () {
    const msg = sessionStorage.getItem('shopifySyncMsg');
    const flagKey = 'shopifyAutoSynced_' + ITEM_TYPE + WEBSITE_ID;
    const hint = document.getElementById('emptyHint');
    if (msg) {
        sessionStorage.removeItem('shopifySyncMsg');
        showAlert(msg, 'success');
        if (hint) hint.textContent = 'nothing was found in the store. Publish some in Shopify, then click Sync.';
    } else if (!HAS_ITEMS && !sessionStorage.getItem(flagKey)) {
        sessionStorage.setItem(flagKey, '1');
        syncItems(true);
    } else if (!HAS_ITEMS && hint) {
        hint.textContent = 'click Sync from Shopify to load them.';
    }
})();
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
