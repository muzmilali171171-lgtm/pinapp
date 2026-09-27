<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'website-pages';
$pageTitle = 'Create New Schedule';

$featureCheck = require_plan_feature($pdo, (int)$user['id'], 'auto_website_daily_pin');
if (!$featureCheck['allowed']) {
    render_user_feature_locked($pageTitle, $activePage, $user, $featureCheck['message']);
    exit;
}

$siteId = (int)($_GET['site_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE id = ? AND user_id = ?");
$stmt->execute([$siteId, $user['id']]);
$site = $stmt->fetch();
if (!$site) redirect('website-pages');

$stmt = $pdo->prepare("SELECT * FROM crawl_pages WHERE crawl_site_id = ? AND active = 1 ORDER BY url ASC");
$stmt->execute([$siteId]);
$activePages = $stmt->fetchAll();

if (empty($activePages)) {
    redirect('website-pages?site_id=' . $siteId);
}

$accountsStmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? AND status = 'connected'");
$accountsStmt->execute([$user['id']]);
$accounts = $accountsStmt->fetchAll();

$boardsByAccount = [];
foreach ($accounts as $acc) {
    $rows = get_boards_for_account($pdo, $acc);
    $boardsByAccount[$acc['id']] = array_map(fn($b) => ['value' => $b['id'], 'name' => $b['board_name']], $rows);
}

$myCredits = get_user_credits($pdo, $user['id']);

// Pages imported from a URL CSV already carry a title/description/keywords. When
// enough of them do, offer that as a content source so AI only makes the images.
$pagesWithCsvData = 0;
foreach ($activePages as $p) {
    if (trim((string)($p['meta_title'] ?? '')) !== '' || trim((string)($p['meta_description'] ?? '')) !== '') {
        $pagesWithCsvData++;
    }
}
$hasArchiveData = $pagesWithCsvData > 0;

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Create New Schedule</h1>
    <a href="website-pages?site_id=<?= $siteId ?>" class="btn-secondary">← Back to Pages</a>
</div>

<div id="alertBox"></div>
<p class="muted">Your balance: <strong id="creditsBalance"><?= number_format($myCredits, 1) ?></strong> credits ·
<strong><?= count($activePages) ?></strong> pages selected from <strong><?= e($site['site_name']) ?></strong></p>

<?php if (empty($accounts)): ?>
    <div class="alert alert-info">You need to <a href="connect-pinterest">connect a Pinterest account</a> first.</div>
<?php else: ?>

<div class="card wizard-card">
    <div class="bs-panel-title" style="margin-top:0;">Batch Name</div>
    <div class="form-row"><input type="text" id="batchName" placeholder="e.g. CelebriHub Daily Pins"></div>

    <div class="bs-panel-title">Pinterest Account</div>
    <div class="form-row">
        <select id="pinAccount">
            <option value="">-- Select account --</option>
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= (int)$acc['id'] ?>"><?= e($acc['pinterest_username'] ?: 'Account #' . $acc['id']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bs-panel-title">Board Setting</div>
    <div class="board-mode-toggle" id="boardModeToggle">
        <button type="button" class="board-mode-btn active" data-board-mode="ai_separate">AI — Separate Board Per Page</button>
        <button type="button" class="board-mode-btn" data-board-mode="existing">One Existing Board</button>
    </div>
    <div class="form-row" id="existingBoardBox" style="display:none;">
        <label>Board</label>
        <select id="existingBoardSelect"><option value="">-- Select account first --</option></select>
    </div>
    <p class="muted" id="aiBoardHint">AI names and creates a new board for each page, based on that page's title/intent.</p>

    <div class="bs-panel-title">Pins Per Page</div>
    <div class="two-col">
        <div class="form-row"><label>Number of Pins Per Page</label><input type="number" id="pinsPerPage" value="3" min="1" max="20"></div>
        <div class="form-row"><label>Gap Between That Page's Pins</label>
            <div style="display:flex; gap:8px;">
                <input type="number" id="pageGapValue" value="30" min="1" style="flex:1;">
                <select id="pageGapUnit" style="width:130px;">
                    <option value="minutes">Minutes</option>
                    <option value="days" selected>Days</option>
                </select>
            </div>
            <p class="muted" id="pageGapHint" style="margin-bottom:0;">Recommended: 30 days — each next pin of the same page waits this long.</p>
        </div>
    </div>
    <div class="two-col">
        <div class="form-row"><label>First Publish Day</label><input type="date" id="startDate" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>"></div>
        <div class="form-row"><label>First Pin of the Day At</label><select id="startTime"><?php for ($h = 0; $h < 24; $h++): $hourVal = sprintf('%02d:00', $h); ?><option value="<?= $hourVal ?>" <?= $hourVal === '09:00' ? 'selected' : '' ?>><?= date('g:00 A', mktime($h, 0)) ?> (<?= $hourVal ?>)</option><?php endfor; ?></select></div>
    </div>
    <p class="muted" style="margin-top:-6px;">If the first pin's time has already passed today, it is published right away instead of waiting.</p>
    <div class="form-row">
        <label>Pins Published Per Day</label>
        <input type="number" id="dailyPinCount" value="6" min="1">
        <p class="muted" style="margin-bottom:0;">Same-day pins are spaced automatically — e.g. 6 pins/day auto-spaces to every 4 hours.</p>
    </div>

    <div class="bs-panel-title">Content Source</div>
    <div class="board-mode-toggle">
        <button type="button" class="board-mode-btn active" data-content-source="ai">AI Writes Everything</button>
        <button type="button" class="board-mode-btn" data-content-source="csv">I'll Provide My Own (CSV)</button>
        <?php if ($hasArchiveData): ?>
        <button type="button" class="board-mode-btn" data-content-source="archive">Use My Uploaded Archive Data</button>
        <?php endif; ?>
    </div>
    <?php if ($hasArchiveData): ?>
    <p class="muted" id="archiveContentHint" style="display:none;"><strong><?= (int)$pagesWithCsvData ?></strong>
    of your <?= count($activePages) ?> selected page(s) already have a title/description from the CSV you uploaded
    — those are used as-is, and AI fills in anything that's missing. No extra upload needed.</p>
    <?php endif; ?>
    <div class="form-row" id="csvUploadBox" style="display:none;">
        <label>Upload CSV <span class="muted">(columns: url, title, description, alt, keywords)</span></label>
        <input type="file" id="csvFileInput" accept=".csv">
        <p class="muted" id="csvStatus"></p>
    </div>
    <p class="muted" id="aiContentHint">AI extracts each page's title from its URL and writes unique, non-duplicate
    pin titles/descriptions/alt text/keywords for every pin — even when a page has several pins, each one is
    reworded differently while staying on the same topic. When using your own CSV, AI only creates the images;
    your title/description/alt/keywords are used as-is for every pin on that page.</p>

    <div class="bs-panel-title">Image Quality <span class="muted" style="font-weight:400;">(recommended: Ultra)</span></div>
    <div class="form-row">
        <select id="imageQuality">
            <option value="budget">Budget — 0.2 credits/image</option>
            <option value="high">High Quality — 0.7 credits/image</option>
            <option value="ultra" selected>Ultra Quality — 1 credit/image (recommended)</option>
        </select>
    </div>

    <div class="bs-panel-title">Pin Size</div>
    <div class="form-row">
        <select id="pinSize">
            <option value="2:3" selected>1000 × 1500 px (2:3)</option>
            <option value="9:16">1080 × 1920 px (9:16)</option>
            <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
            <option value="1:1">1000 × 1000 px (1:1)</option>
        </select>
    </div>

    <div class="bs-panel-title">Select Category <span class="muted" style="font-weight:400;">(the AI images match this niche)</span></div>
    <div class="form-row">
        <input type="hidden" id="imageCategory" data-catpick>
    </div>

    <div class="bs-panel-title">Pin Templates &amp; Styles</div>
    <div class="form-row">
        <input type="hidden" id="imageStyle" value="auto" data-tplpick>
    </div>

    <label class="checkbox-row"><input type="checkbox" id="paletteEnabled"> Use my brand color palette <span class="muted">(optional — leave off for the AI's own automatic colors)</span></label>
    <div id="paletteWrap" style="display:none; margin-top:8px;">
        <div class="form-row">
            <label>Number of Colors</label>
            <select id="paletteCount">
                <option value="3" selected>3 Colors</option>
                <option value="4">4 Colors</option>
            </select>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Color 1</label><input type="color" id="paletteColor1" value="#E91E63"></div>
            <div class="form-row"><label>Color 2</label><input type="color" id="paletteColor2" value="#FFEB3B"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Color 3</label><input type="color" id="paletteColor3" value="#212121"></div>
            <div class="form-row" id="paletteColor4Wrap" style="display:none;"><label>Color 4</label><input type="color" id="paletteColor4" value="#FFFFFF"></div>
        </div>
        <p class="muted">These colors are used for the pin's headline text (cycled line by line).</p>

        <div class="two-col">
            <div class="form-row"><label>Website Text Color</label><input type="color" id="paletteWebsiteTextColor" value="#FFFFFF"></div>
            <div class="form-row"><label>Website Background Color</label><input type="color" id="paletteWebsiteBgColor" value="#E91E63"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>CTA Text Color</label><input type="color" id="paletteCtaTextColor" value="#FFFFFF"></div>
            <div class="form-row"><label>CTA Background Color</label><input type="color" id="paletteCtaBgColor" value="#E91E63"></div>
        </div>
    </div>

    <div class="bs-panel-title">CTA</div>
    <div class="two-col">
        <div class="form-row">
            <label>Mode</label>
            <select id="ctaMode">
                <option value="auto">Auto</option>
                <option value="custom">Custom text</option>
                <option value="none">None</option>
            </select>
        </div>
        <div class="form-row" id="ctaCustomWrap" style="display:none;">
            <label>CTA Text</label>
            <input type="text" id="ctaText" placeholder="e.g. Read More">
        </div>
    </div>

    <div class="bs-panel-title">Website Text On Image</div>
    <label class="checkbox-row"><input type="checkbox" id="websiteTextEnabled" checked> Show website text on the pin image</label>
    <div class="form-row" id="websiteTextWrap" style="margin-top:8px;">
        <input type="text" id="websiteTextValue" value="<?= e(parse_url($site['site_url'], PHP_URL_HOST) ?: '') ?>" placeholder="example.com">
    </div>

    <div class="wizard-nav">
        <span class="muted">Auto-saves as a draft while you fill this in.</span>
        <button type="button" class="btn-primary" id="createNowBtn">Create Now</button>
    </div>
</div>
<?php endif; ?>

<script>
const BOARDS_BY_ACCOUNT = <?= json_encode($boardsByAccount, JSON_HEX_TAG) ?>;
const ACTIVE_PAGES = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'],
    'url' => $p['url'],
    'archive_title' => $p['meta_title'] ?? '',
    'archive_description' => $p['meta_description'] ?? '',
    'archive_keywords' => $p['keywords'] ?? '',
], $activePages), JSON_HEX_TAG) ?>;
const SITE_ID = <?= $siteId ?>;

const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

let boardMode = 'ai_separate';
document.querySelectorAll('[data-board-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        boardMode = btn.dataset.boardMode;
        document.querySelectorAll('[data-board-mode]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('existingBoardBox').style.display = boardMode === 'existing' ? 'block' : 'none';
        document.getElementById('aiBoardHint').style.display = boardMode === 'existing' ? 'none' : 'block';
    });
});
document.getElementById('pinAccount').addEventListener('change', function () {
    const boards = BOARDS_BY_ACCOUNT[this.value] || [];
    const sel = document.getElementById('existingBoardSelect');
    sel.innerHTML = boards.length
        ? '<option value="">-- Select board --</option>' + boards.map(b => `<option value="${b.value}">${b.name}</option>`).join('')
        : '<option value="">-- No boards found --</option>';
});

let contentSource = 'ai';
let csvRows = null; // { urlLower: {title, description, alt, keywords} }
document.querySelectorAll('[data-content-source]').forEach(btn => {
    btn.addEventListener('click', () => {
        contentSource = btn.dataset.contentSource;
        document.querySelectorAll('[data-content-source]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('csvUploadBox').style.display = contentSource === 'csv' ? 'block' : 'none';
        document.getElementById('aiContentHint').style.display = contentSource === 'ai' ? 'block' : 'none';
        const archiveHint = document.getElementById('archiveContentHint');
        if (archiveHint) archiveHint.style.display = contentSource === 'archive' ? 'block' : 'none';
    });
});
function syncGapUnit() {
    const unit = document.getElementById('pageGapUnit').value;
    const inp = document.getElementById('pageGapValue');
    if (unit === 'minutes') {
        inp.max = 43200;
        document.getElementById('pageGapHint').textContent = 'Minutes between pins of the same page (e.g. 60 = 1 hour, 1440 = 1 day).';
    } else {
        inp.max = 365;
        if (+inp.value > 365) inp.value = 30;
        document.getElementById('pageGapHint').textContent = 'Recommended: 30 days — each next pin of the same page waits this long.';
    }
    document.getElementById('pageGapHint').dataset.unit = unit;
}
document.getElementById('pageGapUnit').addEventListener('change', syncGapUnit);
document.getElementById('pageGapHint').dataset.unit = 'days';
document.getElementById('ctaMode').addEventListener('change', function () {
    document.getElementById('ctaCustomWrap').style.display = this.value === 'custom' ? 'block' : 'none';
});
document.getElementById('websiteTextEnabled').addEventListener('change', function () {
    document.getElementById('websiteTextWrap').style.display = this.checked ? 'block' : 'none';
});
document.getElementById('paletteEnabled').addEventListener('change', function () {
    document.getElementById('paletteWrap').style.display = this.checked ? 'block' : 'none';
});
document.getElementById('paletteCount').addEventListener('change', function () {
    document.getElementById('paletteColor4Wrap').style.display = this.value === '4' ? 'block' : 'none';
});
function buildColorPalette() {
    if (!document.getElementById('paletteEnabled').checked) return null;
    const count = parseInt(document.getElementById('paletteCount').value, 10);
    const colors = [
        document.getElementById('paletteColor1').value,
        document.getElementById('paletteColor2').value,
        document.getElementById('paletteColor3').value,
    ];
    if (count === 4) colors.push(document.getElementById('paletteColor4').value);
    return {
        enabled: true,
        colors: colors,
        website_text_color: document.getElementById('paletteWebsiteTextColor').value,
        website_bg_color: document.getElementById('paletteWebsiteBgColor').value,
        cta_text_color: document.getElementById('paletteCtaTextColor').value,
        cta_bg_color: document.getElementById('paletteCtaBgColor').value,
    };
}

function parseCsv(text) {
    const rowsOut = [];
    let field = '', row = [], inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQuotes) {
            if (c === '"') { if (text[i + 1] === '"') { field += '"'; i++; } else { inQuotes = false; } }
            else field += c;
        } else if (c === '"') inQuotes = true;
        else if (c === ',') { row.push(field); field = ''; }
        else if (c === '\n' || c === '\r') {
            if (c === '\r' && text[i + 1] === '\n') i++;
            row.push(field); field = '';
            if (row.some(f => f.trim() !== '')) rowsOut.push(row);
            row = [];
        } else field += c;
    }
    if (field !== '' || row.length) { row.push(field); if (row.some(f => f.trim() !== '')) rowsOut.push(row); }
    if (!rowsOut.length) return [];
    const headers = rowsOut[0].map(h => h.trim().toLowerCase());
    return rowsOut.slice(1).map(r => { const o = {}; headers.forEach((h, i) => o[h] = (r[i] || '').trim()); return o; });
}
document.getElementById('csvFileInput').addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;
    const text = await file.text();
    const rows = parseCsv(text);
    csvRows = {};
    rows.forEach(r => { if (r.url) csvRows[r.url.toLowerCase().trim()] = r; });
    document.getElementById('csvStatus').textContent = `Loaded ${Object.keys(csvRows).length} row(s) — matched by URL.`;
});

document.getElementById('createNowBtn').addEventListener('click', async () => {
    if (!document.getElementById('pinAccount').value) { showAlert('Please select a Pinterest account.', 'error'); return; }
    if (boardMode === 'existing' && !document.getElementById('existingBoardSelect').value) { showAlert('Please select a board.', 'error'); return; }
    if (!(+document.getElementById('pageGapValue').value >= 1)) { showAlert('Please enter a gap of at least 1.', 'error'); return; }
    if (contentSource === 'csv' && !csvRows) { showAlert('Please upload a CSV first, or switch to AI Writes Everything.', 'error'); return; }

    const pages = ACTIVE_PAGES.map(p => {
        if (contentSource === 'archive') {
            return {
                crawl_page_id: p.id,
                url: p.url,
                title: p.archive_title || null,
                description: p.archive_description || null,
                alt: p.archive_description || null,
                keywords: p.archive_keywords || null,
            };
        }
        const row = csvRows ? csvRows[p.url.toLowerCase().trim()] : null;
        return {
            crawl_page_id: p.id,
            url: p.url,
            title: row ? row.title : null,
            description: row ? row.description : null,
            alt: row ? row.alt : null,
            keywords: row ? row.keywords : null,
        };
    });

    const payload = {
        name: document.getElementById('batchName').value.trim(),
        crawl_site_id: SITE_ID,
        pinterest_account_id: document.getElementById('pinAccount').value,
        pins_per_page: document.getElementById('pinsPerPage').value,
        board_mode: boardMode,
        board_row_id: document.getElementById('existingBoardSelect').value,
        page_gap_value: document.getElementById('pageGapValue').value,
        page_gap_unit: document.getElementById('pageGapUnit').value,
        page_gap_days: document.getElementById('pageGapUnit').value === 'days' ? document.getElementById('pageGapValue').value : 1,
        start_date: document.getElementById('startDate').value,
        start_time: document.getElementById('startTime').value || '09:00',
        daily_pin_count: document.getElementById('dailyPinCount').value,
        image_quality: document.getElementById('imageQuality').value,
        pin_size: document.getElementById('pinSize').value,
        image_style: document.getElementById('imageStyle').value,
        image_category_id: document.getElementById('imageCategory').value,
        color_palette: buildColorPalette(),
        cta_mode: document.getElementById('ctaMode').value,
        cta_text: document.getElementById('ctaText').value.trim(),
        website_text: document.getElementById('websiteTextEnabled').checked ? document.getElementById('websiteTextValue').value.trim() : '',
        content_source: contentSource === 'archive' ? 'csv' : contentSource,
        pages: pages,
    };

    const btn = document.getElementById('createNowBtn');
    btn.disabled = true;
    btn.textContent = 'Creating…';
    const fd = new FormData();
    fd.append('payload', JSON.stringify(payload));
    try {
        const res = await fetch('ajax-create-website-pin-batch', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            window.location.href = 'auto-website-batch-view?batch_id=' + encodeURIComponent(result.batch_id);
        } else {
            showAlert('Could not create schedule: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error while creating the schedule.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Create Now';
});
</script>

<script src="../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/category-picker.js') ?: time() ?>"></script>
<script src="../assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/template-picker.js') ?: time() ?>"></script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
