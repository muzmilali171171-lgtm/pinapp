<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/page_crawler_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'website-pages';
$pageTitle = 'Website Pages';

$connectedWebsites = $pdo->prepare("SELECT id, site_name, site_url FROM websites WHERE user_id = ? AND status = 'connected'");
$connectedWebsites->execute([$user['id']]);
$connectedWebsites = $connectedWebsites->fetchAll();

$crawlSites = get_user_crawl_sites($pdo, $user['id']);

$siteId = (int)($_GET['site_id'] ?? ($crawlSites[0]['id'] ?? 0));
$currentSite = null;
foreach ($crawlSites as $cs) {
    if ((int)$cs['id'] === $siteId) { $currentSite = $cs; break; }
}

$pages = [];
$sitemaps = [];
if ($currentSite) {
    $stmt = $pdo->prepare("SELECT * FROM crawl_pages WHERE crawl_site_id = ? ORDER BY url ASC");
    $stmt->execute([$currentSite['id']]);
    $pages = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM crawl_sitemaps WHERE crawl_site_id = ? ORDER BY sitemap_url ASC");
    $stmt->execute([$currentSite['id']]);
    $sitemaps = $stmt->fetchAll();
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Website Pages</h1>
</div>

<?= flash_render() ?>
<div id="alertBox"></div>

<div class="card">
    <h2>Scan a Website</h2>
    <div class="two-col">
        <div class="form-row">
            <label>Website URL</label>
            <input type="text" id="scanUrl" placeholder="https://example.com">
        </div>
        <div class="form-row">
            <label>Or select a connected website</label>
            <select id="scanConnectedSite">
                <option value="">-- Enter a URL instead --</option>
                <?php foreach ($connectedWebsites as $w): ?>
                    <option value="<?= (int)$w['id'] ?>" data-url="<?= e($w['site_url']) ?>"><?= e($w['site_name'] ?: $w['site_url']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="button" class="btn-primary" id="scanBtn">🔍 Scan</button>
    <p class="muted">Finds the site's sitemap(s) automatically (via robots.txt or common paths) and imports every
    page listed. If no sitemap exists, it crawls the site's pages instead.</p>
</div>

<div class="card">
    <h2>Or Upload URLs From a CSV</h2>
    <p class="muted">No sitemap, or you only want specific pages? Upload a CSV of URLs instead and save them as a
    named <strong>archive</strong> you can build schedules from — exactly like a scanned site. Only the
    <code>url</code> column is required; any title, description, alt and keywords you include are stored with each
    page and can be reused instead of letting AI write them.</p>

    <div class="two-col">
        <div class="form-row">
            <label>Archive Name</label>
            <input type="text" id="archiveName" placeholder="e.g. Recipe Posts — Batch 1">
        </div>
        <div class="form-row">
            <label>Save Into</label>
            <select id="csvTargetArchive">
                <option value="">Create a new archive</option>
                <?php foreach ($crawlSites as $cs): ?>
                    <option value="<?= (int)$cs['id'] ?>">Add to: <?= e($cs['site_name']) ?> (<?= (int)$cs['total_pages'] ?> pages)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label>CSV File <span class="muted">(columns: url, title, description, alt, keywords, image_url, category, priority)</span></label>
        <input type="file" id="urlCsvInput" accept=".csv,.txt">
    </div>

    <label class="checkbox-row"><input type="checkbox" id="csvActivate" checked> Select every uploaded URL for pinning straight away</label>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; align-items:center;">
        <button type="button" class="btn-primary" id="uploadCsvBtn">⬆ Upload CSV</button>
        <a href="../assets/downloads/website-urls-sample.csv" class="btn-secondary" download>⬇ Download Sample CSV</a>
        <span class="muted" id="csvUploadStatus"></span>
    </div>
    <p class="muted" style="margin-bottom:0;">Up to 2,000 URLs per file (5MB max). Re-uploading a URL that's already
    in the archive updates it instead of creating a duplicate.</p>
</div>

<?php if (!empty($crawlSites)): ?>
<div class="card">
    <h2>Your Sites &amp; Archives</h2>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach ($crawlSites as $cs): ?>
            <a href="?site_id=<?= (int)$cs['id'] ?>" class="btn-secondary btn-small <?= $currentSite && $currentSite['id'] == $cs['id'] ? 'active' : '' ?>">
                <?= e($cs['site_name']) ?><?= (($cs['source'] ?? '') === 'csv') ? ' 📄' : '' ?> (<?= (int)$cs['total_pages'] ?> pages, <?= (int)$cs['active_pages'] ?> selected)
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($currentSite): ?>
<div class="card">
    <div class="page-header" style="margin-bottom:14px;">
        <h2 style="margin:0;">Pages To Use For Pins <span class="muted">— <?= e($currentSite['site_name']) ?></span></h2>
        <button type="button" class="btn-primary" id="automateBtn">Automate Daily Pin</button>
    </div>

    <?php if (empty($pages)): ?>
        <div class="empty-state">No pages here yet — scan the site above, or upload a CSV of URLs.</div>
    <?php else: ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <span class="muted"><span id="selectedCount">0</span> of <?= count($pages) ?> pages selected</span>
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn-secondary btn-small" id="selectAllBtn">Select All</button>
            <button type="button" class="btn-secondary btn-small" id="clearAllBtn">Clear</button>
            <button type="button" class="btn-primary btn-small" id="saveSelectionBtn">Save Changes</button>
        </div>
    </div>

    <table>
        <tr><th style="width:36px;"></th><th>URL</th><th>Last Modified</th><th>Category/Tags</th><th>Priority</th></tr>
        <?php foreach ($pages as $p): ?>
        <tr>
            <td><input type="checkbox" class="page-checkbox" value="<?= (int)$p['id'] ?>" <?= $p['active'] ? 'checked' : '' ?>></td>
            <td><a href="<?= e($p['url']) ?>" target="_blank"><?= e($p['url']) ?></a></td>
            <td class="muted"><?= $p['last_modified'] ? date('d M Y', strtotime($p['last_modified'])) : '—' ?></td>
            <td class="muted"><?= e($p['category_tags'] ?: '—') ?></td>
            <td><span class="badge badge-pending"><?= e(ucfirst($p['priority'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('scanConnectedSite').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (opt.dataset.url) document.getElementById('scanUrl').value = opt.dataset.url;
});

document.getElementById('scanBtn').addEventListener('click', async () => {
    const url = document.getElementById('scanUrl').value.trim();
    const websiteId = document.getElementById('scanConnectedSite').value;
    if (!url) { showAlert('Please enter a website URL or select a connected website.', 'error'); return; }
    const btn = document.getElementById('scanBtn');
    btn.disabled = true;
    btn.textContent = 'Scanning…';
    const fd = new FormData();
    fd.append('url', url);
    fd.append('website_id', websiteId);
    try {
        const res = await fetch('ajax-scan-website', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            showAlert(`Found ${result.pages_added} new page(s)${result.sitemaps_found ? ' across ' + result.sitemaps_found + ' sitemap(s)' : ' via crawler'}.`, 'success');
            window.location.href = '?site_id=' + result.crawl_site_id;
        } else {
            showAlert('Scan failed: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error while scanning.', 'error');
    }
    btn.disabled = false;
    btn.textContent = '🔍 Scan';
});

function updateSelectedCount() {
    const count = document.querySelectorAll('.page-checkbox:checked').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = count;
}
document.querySelectorAll('.page-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));
updateSelectedCount();

const selectAllBtn = document.getElementById('selectAllBtn');
if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
    document.querySelectorAll('.page-checkbox').forEach(cb => cb.checked = true);
    updateSelectedCount();
});
const clearAllBtn = document.getElementById('clearAllBtn');
if (clearAllBtn) clearAllBtn.addEventListener('click', () => {
    document.querySelectorAll('.page-checkbox').forEach(cb => cb.checked = false);
    updateSelectedCount();
});

const saveBtn = document.getElementById('saveSelectionBtn');
if (saveBtn) saveBtn.addEventListener('click', async () => {
    const activeIds = Array.from(document.querySelectorAll('.page-checkbox:checked')).map(cb => cb.value);
    const inactiveIds = Array.from(document.querySelectorAll('.page-checkbox:not(:checked)')).map(cb => cb.value);
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    const fd = new FormData();
    fd.append('active_ids', JSON.stringify(activeIds));
    fd.append('inactive_ids', JSON.stringify(inactiveIds));
    try {
        const res = await fetch('ajax-toggle-crawl-pages', { method: 'POST', body: fd });
        const result = await res.json();
        showAlert(result.ok ? 'Selection saved.' : ('Could not save: ' + result.error), result.ok ? 'success' : 'error');
    } catch (e) {
        showAlert('Network error while saving.', 'error');
    }
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';
});

const uploadCsvBtn = document.getElementById('uploadCsvBtn');
uploadCsvBtn.addEventListener('click', async () => {
    const fileInput = document.getElementById('urlCsvInput');
    const targetArchive = document.getElementById('csvTargetArchive').value;
    const archiveName = document.getElementById('archiveName').value.trim();

    if (!fileInput.files.length) { showAlert('Please choose a CSV file first.', 'error'); return; }
    if (!targetArchive && !archiveName) { showAlert('Please give the new archive a name.', 'error'); return; }

    const fd = new FormData();
    fd.append('csv_file', fileInput.files[0]);
    fd.append('archive_name', archiveName);
    fd.append('existing_site_id', targetArchive);
    fd.append('activate', document.getElementById('csvActivate').checked ? '1' : '0');

    uploadCsvBtn.disabled = true;
    uploadCsvBtn.textContent = 'Uploading…';
    document.getElementById('csvUploadStatus').textContent = '';
    try {
        const res = await fetch('ajax-upload-url-csv', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            let msg = `Imported ${result.pages_added} new URL(s) into "${result.site_name}"`;
            if (result.pages_updated) msg += `, updated ${result.pages_updated} existing`;
            if (result.skipped) msg += `, skipped ${result.skipped} invalid row(s)`;
            showAlert(msg + '.', 'success');
            window.location.href = '?site_id=' + result.crawl_site_id;
        } else {
            showAlert('Upload failed: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error while uploading the CSV.', 'error');
    }
    uploadCsvBtn.disabled = false;
    uploadCsvBtn.textContent = '\u2b06 Upload CSV';
});

const automateBtn = document.getElementById('automateBtn');
if (automateBtn) automateBtn.addEventListener('click', async () => {
    const activeIds = Array.from(document.querySelectorAll('.page-checkbox:checked')).map(cb => cb.value);
    if (!activeIds.length) { showAlert('Select at least one page first.', 'error'); return; }
    const inactiveIds = Array.from(document.querySelectorAll('.page-checkbox:not(:checked)')).map(cb => cb.value);
    automateBtn.disabled = true;
    const fd = new FormData();
    fd.append('active_ids', JSON.stringify(activeIds));
    fd.append('inactive_ids', JSON.stringify(inactiveIds));
    try {
        await fetch('ajax-toggle-crawl-pages', { method: 'POST', body: fd });
    } catch (e) { /* proceed regardless — the create page re-reads active pages either way */ }
    window.location.href = 'auto-website-create?site_id=<?= (int)($currentSite['id'] ?? 0) ?>';
});

<?php if (!empty($_GET['autoscan']) && $currentSite && empty($pages)): ?>
// Arrived from an "Automate Pin" button on a site that has not been scanned yet — scan it right away.
document.getElementById('scanUrl').value = <?= json_encode($currentSite['site_url'], JSON_HEX_TAG) ?>;
<?php if (!empty($currentSite['website_id'])): ?>
document.getElementById('scanConnectedSite').value = '<?= (int)$currentSite['website_id'] ?>';
<?php endif; ?>
document.getElementById('scanBtn').click();
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
