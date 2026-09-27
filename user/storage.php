<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'storage';
$pageTitle = 'Storage';

$usedBytes = get_user_storage_used($pdo, $user['id']);
$usedMb = $usedBytes / 1048576;
$quotaBytes = get_user_storage_quota_bytes($pdo, (int)$user['id']);
$quotaMb = $quotaBytes / 1048576;
$usedPct = min(100, round(($usedBytes / max(1, $quotaBytes)) * 100, 1));

$stmt = $pdo->prepare("SELECT * FROM storage_images WHERE user_id = ? ORDER BY created_at DESC LIMIT 300");
$stmt->execute([$user['id']]);
$images = $stmt->fetchAll();

// Read-only view of images already generated elsewhere in the app (pins + article images) —
// these are managed by their own features and are never deletable from here.
$pinImages = $pdo->prepare("SELECT image_path, title, status FROM scheduled_pins WHERE user_id = ? AND image_path IS NOT NULL ORDER BY created_at DESC LIMIT 100");
$pinImages->execute([$user['id']]);
$pinImages = $pinImages->fetchAll();

$articleImages = $pdo->prepare("SELECT featured_image_path AS image_path, title FROM articles WHERE user_id = ? AND featured_image_path IS NOT NULL ORDER BY created_at DESC LIMIT 100");
$articleImages->execute([$user['id']]);
$articleImages = $articleImages->fetchAll();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Storage</h1></div>
<div id="alertBox"></div>

<div class="board-mode-toggle" id="mainTabToggle" style="max-width:420px;">
    <button type="button" class="board-mode-btn active" data-main-tab="storage">📦 Storage</button>
    <button type="button" class="board-mode-btn" data-main-tab="website-images">🖼️ Website Images</button>
</div>

<!-- ===================== STORAGE TAB ===================== -->
<div id="tab-storage">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <strong>Storage used: <?= number_format($usedMb, 1) ?> MB / <?= number_format($quotaMb, 0) ?> MB</strong>
            <button type="button" class="btn-danger btn-small" id="clearSpaceBtn">🧹 Clear Space</button>
        </div>
        <div style="background:var(--light); border-radius:8px; height:10px; overflow:hidden;">
            <div style="background:<?= $usedPct > 90 ? 'var(--red)' : 'var(--green)' ?>; height:100%; width:<?= $usedPct ?>%;"></div>
        </div>
    </div>

    <div class="board-mode-toggle" id="subTabToggle">
        <button type="button" class="board-mode-btn active" data-sub-tab="manage">Manage Images</button>
        <button type="button" class="board-mode-btn" data-sub-tab="upload">Manual Upload</button>
        <button type="button" class="board-mode-btn" data-sub-tab="stock">Stock Images</button>
        <button type="button" class="board-mode-btn" data-sub-tab="ai">AI Generation</button>
    </div>

    <!-- Manage Images -->
    <div id="sub-manage">
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <input type="text" id="tagFilter" placeholder="Filter by tag…" style="max-width:260px;">
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn-secondary btn-small" id="selectAllImgBtn">Select All</button>
                    <button type="button" class="btn-secondary btn-small" id="bulkDownloadBtn">⬇ Download Selected</button>
                    <button type="button" class="btn-danger btn-small" id="bulkDeleteBtn">🗑 Delete Selected</button>
                </div>
            </div>
            <?php if (empty($images)): ?>
                <div class="empty-state">No images yet — try Manual Upload, Stock Images, or AI Generation.</div>
            <?php else: ?>
            <div class="storage-grid" id="storageGrid">
                <?php foreach ($images as $img): ?>
                <div class="storage-item" data-tags="<?= e(strtolower($img['tags'] ?? '')) ?>" data-id="<?= (int)$img['id'] ?>">
                    <label class="storage-item-check"><input type="checkbox" class="img-checkbox" value="<?= (int)$img['id'] ?>"></label>
                    <img src="<?= e(storage_image_url($img)) ?>" loading="lazy" alt="">
                    <div class="storage-item-meta">
                        <span class="muted"><?= e(ucfirst($img['source'])) ?></span>
                        <div class="storage-item-tags"><?= e($img['tags'] ?: '—') ?></div>
                        <div style="display:flex; gap:6px; margin-top:6px;">
                            <button type="button" class="btn-secondary btn-small tag-btn" data-id="<?= (int)$img['id'] ?>">🏷 Tag</button>
                            <a href="<?= e(storage_image_url($img)) ?>" download class="btn-secondary btn-small">⬇</a>
                            <button type="button" class="btn-danger btn-small del-btn" data-id="<?= (int)$img['id'] ?>">🗑</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($pinImages) || !empty($articleImages)): ?>
        <div class="card">
            <h2>Pins &amp; Article Images <span class="muted" style="font-weight:400;">(view only — managed by their own features; pins still scheduled can't be removed here)</span></h2>
            <div class="storage-grid">
                <?php foreach (array_slice($pinImages, 0, 60) as $img): ?>
                    <div class="storage-item storage-item-readonly">
                        <img src="<?= e(media_url((string)$img['image_path'], false)) ?>" loading="lazy" alt="">
                        <div class="storage-item-meta"><span class="muted">Pin<?= $img['status'] === 'pending' ? ' · scheduled' : '' ?></span></div>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($articleImages, 0, 60) as $img): ?>
                    <div class="storage-item storage-item-readonly">
                        <img src="<?= e(media_url((string)$img['image_path'], false)) ?>" loading="lazy" alt="">
                        <div class="storage-item-meta"><span class="muted">Article</span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Manual Upload -->
    <div id="sub-upload" style="display:none;">
        <div class="card">
            <h2>Upload Files</h2>
            <input type="file" id="uploadFileInput" accept="image/*" multiple>
            <div class="form-row" style="margin-top:14px;">
                <label>Or add from an image URL</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="uploadUrlInput" placeholder="https://example.com/image.jpg" style="flex:1;">
                    <button type="button" class="btn-primary" id="addFromUrlBtn">Add</button>
                </div>
            </div>
            <div id="uploadStatus" class="muted" style="margin-top:10px;"></div>
        </div>
    </div>

    <!-- Stock Images -->
    <div id="sub-stock" style="display:none;">
        <div class="card">
            <h2>Search Pexels</h2>
            <div style="display:flex; gap:8px;">
                <input type="text" id="pexelsQuery" placeholder="Enter search terms…" style="flex:1;">
                <button type="button" class="btn-primary" id="pexelsSearchBtn">Search</button>
            </div>
            <p class="muted">Photos provided by Pexels.</p>
            <div class="storage-grid" id="pexelsResults" style="margin-top:14px;"></div>
            <button type="button" class="btn-secondary" id="pexelsLoadMoreBtn" style="display:none; margin-top:14px;">Load More</button>
        </div>
    </div>

    <!-- AI Generation -->
    <div id="sub-ai" style="display:none;">
        <div class="card">
            <h2>AI Generation</h2>
            <div class="form-row"><label>Prompt</label><textarea id="aiPrompt" rows="3" placeholder="Describe the image you want…"></textarea></div>
            <div class="two-col">
                <div class="form-row">
                    <label>Size</label>
                    <select id="aiSize">
                        <option value="2:3">2:3 (portrait)</option>
                        <option value="1:1">1:1 (square)</option>
                        <option value="9:16">9:16</option>
                        <option value="1:2.1">1:2.1</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Quality</label>
                    <select id="aiQuality">
                        <option value="budget">Budget — 0.2 credits</option>
                        <option value="high">High — 0.7 credits</option>
                        <option value="ultra">Ultra — 1 credit</option>
                    </select>
                </div>
            </div>
            <button type="button" class="btn-primary" id="aiGenerateBtn">✨ Create</button>
            <div id="aiStatus" class="muted" style="margin-top:10px;"></div>
        </div>
    </div>
</div>

<!-- ===================== WEBSITE IMAGES TAB ===================== -->
<div id="tab-website-images" style="display:none;">
    <div class="card">
        <h2>Scan a Page for Images</h2>
        <div style="display:flex; gap:8px;">
            <input type="text" id="scanPageUrl" placeholder="https://example.com/some-page/" style="flex:1;">
            <button type="button" class="btn-primary" id="scanPageBtn">Scan</button>
        </div>
        <div id="scanStatus" class="muted" style="margin-top:10px;"></div>
    </div>
    <div class="card">
        <h2>Website Images</h2>
        <div id="websiteImagesList"></div>
    </div>
</div>

<script>
const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('[data-main-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-main-tab]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('tab-storage').style.display = btn.dataset.mainTab === 'storage' ? 'block' : 'none';
        document.getElementById('tab-website-images').style.display = btn.dataset.mainTab === 'website-images' ? 'block' : 'none';
        if (btn.dataset.mainTab === 'website-images') loadWebsiteImages();
    });
});
document.querySelectorAll('[data-sub-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-sub-tab]').forEach(b => b.classList.toggle('active', b === btn));
        ['manage', 'upload', 'stock', 'ai'].forEach(t => document.getElementById('sub-' + t).style.display = t === btn.dataset.subTab ? 'block' : 'none');
    });
});

/* ---------------- Manage Images ---------------- */
document.getElementById('tagFilter').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.storage-item[data-tags]').forEach(el => {
        el.style.display = !q || el.dataset.tags.includes(q) ? '' : 'none';
    });
});
document.getElementById('selectAllImgBtn').addEventListener('click', () => {
    document.querySelectorAll('.img-checkbox').forEach(cb => cb.checked = true);
});

async function deleteImages(ids) {
    const fd = new FormData();
    fd.append('ids', JSON.stringify(ids));
    const res = await fetch('ajax-storage-delete', { method: 'POST', body: fd });
    return res.json();
}
document.querySelectorAll('.del-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this image?')) return;
        const result = await deleteImages([btn.dataset.id]);
        if (result.ok) document.querySelector(`.storage-item[data-id="${btn.dataset.id}"]`).remove();
        else showAlert(result.error, 'error');
    });
});
document.getElementById('bulkDeleteBtn').addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('.img-checkbox:checked')).map(cb => cb.value);
    if (!ids.length) { showAlert('Select at least one image first.', 'error'); return; }
    if (!confirm(`Delete ${ids.length} image(s)?`)) return;
    const result = await deleteImages(ids);
    if (result.ok) { ids.forEach(id => document.querySelector(`.storage-item[data-id="${id}"]`)?.remove()); showAlert('Deleted.', 'success'); }
    else showAlert(result.error, 'error');
});
document.getElementById('bulkDownloadBtn').addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.img-checkbox:checked')).map(cb => cb.value);
    if (!ids.length) { showAlert('Select at least one image first.', 'error'); return; }
    window.location.href = 'ajax-storage-download?ids=' + ids.join(',');
});
document.getElementById('clearSpaceBtn').addEventListener('click', () => {
    document.querySelector('[data-sub-tab="manage"]').click();
    showAlert('Select the images you no longer need below and use Delete Selected to free up space.', 'info');
});
document.querySelectorAll('.tag-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const tags = prompt('Tags (comma-separated):');
        if (tags === null) return;
        const fd = new FormData();
        fd.append('id', btn.dataset.id);
        fd.append('tags', tags);
        const res = await fetch('ajax-storage-tag', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            const item = document.querySelector(`.storage-item[data-id="${btn.dataset.id}"]`);
            item.dataset.tags = tags.toLowerCase();
            item.querySelector('.storage-item-tags').textContent = tags || '—';
        } else showAlert(result.error, 'error');
    });
});

/* ---------------- Manual Upload ---------------- */
document.getElementById('uploadFileInput').addEventListener('change', async function () {
    const files = Array.from(this.files);
    if (!files.length) return;
    const status = document.getElementById('uploadStatus');
    for (const file of files) {
        status.textContent = `Uploading ${file.name}…`;
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch('ajax-storage-upload', { method: 'POST', body: fd });
            const result = await res.json();
            status.textContent = result.ok ? `Uploaded ${file.name}.` : `Failed: ${result.error}`;
        } catch (e) { status.textContent = 'Network error.'; }
    }
    setTimeout(() => window.location.reload(), 800);
});
document.getElementById('addFromUrlBtn').addEventListener('click', async () => {
    const url = document.getElementById('uploadUrlInput').value.trim();
    if (!url) return;
    const status = document.getElementById('uploadStatus');
    status.textContent = 'Adding…';
    const fd = new FormData();
    fd.append('url', url);
    try {
        const res = await fetch('ajax-storage-add-url', { method: 'POST', body: fd });
        const result = await res.json();
        status.textContent = result.ok ? 'Added.' : ('Failed: ' + result.error);
        if (result.ok) setTimeout(() => window.location.reload(), 800);
    } catch (e) { status.textContent = 'Network error.'; }
});

/* ---------------- Stock Images (Pexels) ---------------- */
let pexelsPage = 1;
let pexelsQuery = '';
async function runPexelsSearch(append) {
    pexelsQuery = document.getElementById('pexelsQuery').value.trim();
    if (!pexelsQuery) return;
    if (!append) { pexelsPage = 1; document.getElementById('pexelsResults').innerHTML = ''; }
    const fd = new FormData();
    fd.append('query', pexelsQuery);
    fd.append('page', pexelsPage);
    const res = await fetch('ajax-pexels-search', { method: 'POST', body: fd });
    const result = await res.json();
    if (!result.ok) { showAlert(result.error, 'error'); return; }
    const grid = document.getElementById('pexelsResults');
    result.results.forEach(p => {
        const div = document.createElement('div');
        div.className = 'storage-item';
        div.innerHTML = `<img src="${p.thumb}" loading="lazy" alt=""><div class="storage-item-meta"><span class="muted">${p.photographer}</span><button type="button" class="btn-primary btn-small pexels-add-btn" style="margin-top:6px; width:100%;">+ Add to Storage</button></div>`;
        div.querySelector('.pexels-add-btn').addEventListener('click', async (e) => {
            e.target.disabled = true; e.target.textContent = 'Adding…';
            const fd2 = new FormData();
            fd2.append('image_url', p.full);
            fd2.append('tags', pexelsQuery);
            const r = await fetch('ajax-storage-add-url', { method: 'POST', body: fd2 });
            const rr = await r.json();
            e.target.textContent = rr.ok ? '✓ Added' : 'Failed';
        });
        grid.appendChild(div);
    });
    document.getElementById('pexelsLoadMoreBtn').style.display = result.results.length > 0 ? 'inline-block' : 'none';
}
document.getElementById('pexelsSearchBtn').addEventListener('click', () => runPexelsSearch(false));
document.getElementById('pexelsLoadMoreBtn').addEventListener('click', () => { pexelsPage++; runPexelsSearch(true); });

/* ---------------- AI Generation ---------------- */
document.getElementById('aiGenerateBtn').addEventListener('click', async () => {
    const prompt = document.getElementById('aiPrompt').value.trim();
    if (!prompt) { showAlert('Please enter a prompt.', 'error'); return; }
    const btn = document.getElementById('aiGenerateBtn');
    const status = document.getElementById('aiStatus');
    btn.disabled = true;
    status.textContent = 'Generating…';
    const fd = new FormData();
    fd.append('prompt', prompt);
    fd.append('size', document.getElementById('aiSize').value);
    fd.append('quality', document.getElementById('aiQuality').value);
    try {
        const res = await fetch('ajax-storage-ai-generate', { method: 'POST', body: fd });
        const result = await res.json();
        status.textContent = result.ok ? 'Created and added to storage.' : ('Failed: ' + result.error);
        if (result.ok) setTimeout(() => window.location.reload(), 900);
        else if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(result.error);
    } catch (e) { status.textContent = 'Network error.'; }
    btn.disabled = false;
});

/* ---------------- Website Images ---------------- */
document.getElementById('scanPageBtn').addEventListener('click', async () => {
    const url = document.getElementById('scanPageUrl').value.trim();
    if (!url) return;
    const status = document.getElementById('scanStatus');
    status.textContent = 'Scanning…';
    const fd = new FormData();
    fd.append('page_url', url);
    try {
        const res = await fetch('ajax-storage-scan-page', { method: 'POST', body: fd });
        const result = await res.json();
        status.textContent = result.ok ? `Added ${result.added} image(s).` : ('Failed: ' + result.error);
        if (result.ok) loadWebsiteImages();
    } catch (e) { status.textContent = 'Network error.'; }
});
async function loadWebsiteImages() {
    const res = await fetch('ajax-storage-website-images');
    const result = await res.json();
    const list = document.getElementById('websiteImagesList');
    if (!result.ok || !result.groups.length) { list.innerHTML = '<div class="empty-state">No website images scanned yet.</div>'; return; }
    list.innerHTML = result.groups.map(g => `
        <div style="margin-bottom:20px;">
            <strong>${g.page_url}</strong> <span class="muted">(${g.images.length} images)</span>
            <div class="storage-grid" style="margin-top:8px;">
                ${g.images.map(img => `<div class="storage-item" data-id="${img.id}"><img src="${img.url}" loading="lazy" alt=""><div class="storage-item-meta"><button type="button" class="btn-danger btn-small del-website-img" data-id="${img.id}">🗑</button></div></div>`).join('')}
            </div>
        </div>
    `).join('');
    document.querySelectorAll('.del-website-img').forEach(btn => {
        btn.addEventListener('click', async () => {
            await deleteImages([btn.dataset.id]);
            loadWebsiteImages();
        });
    });
}
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
