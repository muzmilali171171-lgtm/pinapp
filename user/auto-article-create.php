<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'auto-article-create';
$pageTitle = 'Create New Batch';

$featureCheck = require_plan_feature($pdo, (int)$user['id'], 'auto_article');
if (!$featureCheck['allowed']) {
    render_user_feature_locked($pageTitle, $activePage, $user, $featureCheck['message']);
    exit;
}

$websitesStmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND status = 'connected' AND platform <> 'none' ORDER BY platform, site_name");
$websitesStmt->execute([$user['id']]);
$websites = $websitesStmt->fetchAll();
$websitesJson = json_encode(array_map(function ($w) {
    return [
        'id' => (int)$w['id'],
        'name' => $w['site_name'] ?: $w['site_url'],
        'platform' => $w['platform'],
        'categories' => json_decode($w['categories_cache'] ?: '[]', true) ?: [],
        'authors' => json_decode($w['authors_cache'] ?: '[]', true) ?: [],
    ];
}, $websites));

$accountsStmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? AND status = 'connected'");
$accountsStmt->execute([$user['id']]);
$accounts = $accountsStmt->fetchAll();

$articleSettings = get_article_settings($pdo);
$hasTextModel = $articleSettings && !empty($articleSettings['text_provider']);
$myCredits = get_user_credits($pdo, $user['id']);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Create New Batch</h1>
    <a href="auto-article-batches" class="btn-secondary">← Your Batches</a>
</div>

<div id="alertBox"></div>
<p class="muted">Your balance: <strong id="creditsBalance"><?= number_format($myCredits, 1) ?></strong> credits</p>

<?php if (empty($websites)): ?>
    <div class="alert alert-info">You need to <a href="websites">connect a website</a> first — WordPress, Shopify, Wix or a custom website — under <strong>Add Websites</strong>.</div>
<?php elseif (!$hasTextModel): ?>
    <div class="alert alert-info">AI article writing is not available right now. Please check back soon.</div>
<?php else: ?>

<div class="wizard-steps">
    <div class="wizard-step-dot active" data-step-dot="1">1<span>Type &amp; Website</span></div>
    <div class="wizard-step-dot" data-step-dot="2">2<span>Titles</span></div>
    <div class="wizard-step-dot" data-step-dot="3">3<span>Images</span></div>
    <div class="wizard-step-dot" data-step-dot="4">4<span>Publish</span></div>
</div>

<div class="card wizard-card">

    <!-- STEP 1 -->
    <div class="wizard-step" data-step="1">
        <div class="bs-panel-title" style="margin-top:0;">Article Type</div>
        <div class="board-mode-toggle">
            <button type="button" class="board-mode-btn active" data-article-type="ideas">Ideas Base Article</button>
            <button type="button" class="board-mode-btn" data-article-type="recipe">Recipe Food Article</button>
        </div>

        <div class="bs-panel-title">Website</div>
        <div class="form-row">
            <select id="websiteSelect">
                <option value="">-- Select website --</option>
                <?php foreach ($websites as $w): ?>
                    <option value="<?= (int)$w['id'] ?>"><?= e($w['site_name'] ?: $w['site_url']) ?> (<?= e(platform_label($w['platform'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <p class="muted">Don't see your site? <a href="websites">Connect it under Add Websites</a> — WordPress, Shopify, Wix and custom (webhook) websites all work.</p>
        </div>

        <div class="bs-panel-title" id="categoryTitle">Category</div>
        <div class="form-row">
            <select id="categorySelect"><option value="">-- Select a website first --</option></select>
        </div>

        <div id="authorBox">
        <div class="bs-panel-title">Author <span class="muted" style="font-weight:400;">(required)</span></div>
        <div class="form-row">
            <select id="authorSelect" required><option value="">-- Select an author --</option></select>
            <p class="muted" id="noAuthorsHint" style="display:none; color:var(--red);">No authors found for this site — make sure you've installed the latest PinScheduler Publisher plugin (v1.2.1+) and clicked "Re-check" on the <a href="websites">Add Websites</a> page.</p>
        </div>
        </div>

        <div class="bs-panel-title">Batch Name <span class="muted" style="font-weight:400;">(optional)</span></div>
        <div class="form-row"><input type="text" id="batchName" placeholder="e.g. October Recipe Push"></div>

        <div class="wizard-nav"><span></span><button type="button" class="btn-primary" data-next="2">Next →</button></div>
    </div>

    <!-- STEP 2 -->
    <div class="wizard-step" data-step="2" style="display:none;">
        <div class="bs-panel-title" style="margin-top:0;">Titles</div>
        <p class="muted" id="titlesHint">Enter one article title per line — e.g. "25 Birthday Outfit Ideas to Feel Your Best on Your Big Day".</p>
        <div class="form-row">
            <textarea id="titlesInput" rows="10" placeholder="One title (or competitor link) per line"></textarea>
        </div>
        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <button type="button" class="btn-secondary btn-small" id="resolveTitlesBtn">Resolve Links → Titles</button>
            <button type="button" class="btn-secondary btn-small" id="rewriteTitlesBtn">✨ Rewrite to Your Original Title (AI)</button>
        </div>
        <p class="muted">Pasting competitor article links (one per line) instead of titles? Click <strong>Resolve Links → Titles</strong> first to pull each page's title, then <strong>Rewrite to Your Original Title</strong> so it's not a copy.</p>

        <label class="checkbox-row" style="margin-top:14px;">
            <input type="checkbox" id="tagsEnabled">
            Add tags to each article (2-3 AI-suggested tags, where the platform supports them)
        </label>

        <div class="wizard-nav">
            <button type="button" class="btn-secondary" data-prev="1">← Back</button>
            <button type="button" class="btn-primary" data-next="3">Next →</button>
        </div>
    </div>

    <!-- STEP 3 -->
    <div class="wizard-step" data-step="3" style="display:none;">
        <div class="bs-panel-title" style="margin-top:0;">Daily Publishing Pace</div>
        <div class="form-row">
            <label>Articles Published Per Day</label>
            <input type="number" id="dailyCount" value="1" min="1" max="20">
        </div>

        <div class="bs-panel-title">Article Length</div>
        <div class="two-col">
            <div class="form-row">
                <label>Length</label>
                <select id="lengthMode">
                    <option value="auto">Auto — the AI picks the right length for the topic</option>
                    <option value="min">Minimum words (I set the number)</option>
                    <option value="random">Random long-form (1,800 – 3,500 words)</option>
                </select>
            </div>
            <div class="form-row" id="minWordsRow" style="display:none;">
                <label>Minimum words per article</label>
                <input type="number" id="minWords" value="1500" min="300" max="8000" step="100">
            </div>
        </div>
        <p class="muted" style="margin-top:-4px;">Longer articles use more of the AI model's output. Recipes also get a nutrition section (per-serving estimate).</p>

        <div class="bs-panel-title">Feature Image</div>
        <div class="two-col">
            <div class="form-row"><label>Width</label><input type="number" id="featW" value="1200" min="200"></div>
            <div class="form-row"><label>Height</label><input type="number" id="featH" value="630" min="200"></div>
        </div>

        <div id="ideasImageBox">
            <div class="bs-panel-title">Idea Images</div>
            <div class="form-row">
                <label>Size Per Idea</label>
                <select id="ideasImageSize">
                    <option value="3:4" selected>3:4 (portrait)</option>
                    <option value="1:1">1:1 (square)</option>
                    <option value="2:3">2:3</option>
                    <option value="9:16">9:16</option>
                </select>
            </div>
        </div>
        <div id="recipeImageBox" style="display:none;">
            <div class="bs-panel-title">Recipe Images</div>
            <div class="form-row">
                <label>Number of Images Per Article</label>
                <input type="number" id="recipeImageCount" value="3" min="1" max="10">
            </div>
        </div>

        <div class="bs-panel-title">Image Quality <span class="muted" style="font-weight:400;">(1 article = 1 credit, plus each image's own credits)</span></div>
        <div class="form-row">
            <select id="imageQuality">
                <option value="budget">Budget — 0.2 credits/image</option>
                <option value="high">High Quality — 0.7 credits/image</option>
                <option value="ultra">Ultra Quality — 1 credit/image</option>
            </select>
        </div>

        <div class="bs-panel-title">Select Category <span class="muted" style="font-weight:400;">(used for the feature image, article images and pin images)</span></div>
        <div class="form-row">
            <input type="hidden" id="imageCategory" data-catpick>
        </div>

        <div class="wizard-nav">
            <button type="button" class="btn-secondary" data-prev="2">← Back</button>
            <button type="button" class="btn-primary" data-next="4">Next →</button>
        </div>
    </div>

    <!-- STEP 4 -->
    <div class="wizard-step" data-step="4" style="display:none;">
        <div class="bs-panel-title" style="margin-top:0;">Publish Mode</div>
        <div class="board-mode-toggle">
            <button type="button" class="board-mode-btn active" data-publish-mode="now">Publish Now</button>
            <button type="button" class="board-mode-btn" data-publish-mode="pin_auto">Pin Auto</button>
        </div>

        <div id="pinAutoBox" style="display:none;">
            <div class="bs-panel-title">Pinterest Account</div>
            <div class="form-row">
                <select id="pinAccount">
                    <option value="">-- Select account --</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['pinterest_username'] ?: 'Account #' . $acc['id']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($accounts)): ?><p class="muted">No connected Pinterest accounts — <a href="connect-pinterest">connect one</a> first.</p><?php endif; ?>
            </div>

            <div class="two-col">
                <div class="form-row"><label>Pins Published Per Day</label><input type="number" id="dailyPinCount" value="2" min="1"></div>
                <div class="form-row"><label>Monthly Increase</label><input type="number" id="dailyPinRamp" value="0" min="0"></div>
            </div>
            <div class="two-col">
                <div class="form-row"><label>Pins Per Article</label><input type="number" id="pinsPerArticle" value="3" min="1" max="20"></div>
                <div class="form-row"><label>Gap Between That Article's Pins</label>
                    <div style="display:flex; gap:8px;">
                        <input type="number" id="articlePinGapDays" value="30" min="1" style="flex:1;">
                        <select id="articlePinGapUnit" style="width:120px;">
                            <option value="minutes">Minutes</option>
                            <option value="days" selected>Days</option>
                        </select>
                    </div>
                    <p class="muted" id="articlePinGapHint" style="margin:4px 0 0;">Days between this article's pins (recommended: 30).</p>
                </div>
            </div>
            <p class="muted">Same-day pins are spaced automatically (24h ÷ pins/day) — e.g. 4 pins/day auto-spaces to every 6 hours.</p>

            <div class="bs-panel-title">Board Setting</div>
            <div class="form-row">
                <select id="boardMode">
                    <option value="auto">AI Auto (match or create a board per topic)</option>
                    <option value="separate_per_article">Separate board for each article</option>
                </select>
            </div>

            <div class="bs-panel-title">Pin Size</div>
            <div class="form-row">
                <select id="pinSize">
                    <option value="2:3">1000 × 1500 px (2:3)</option>
                    <option value="9:16">1080 × 1920 px (9:16)</option>
                    <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                    <option value="1:1">1000 × 1000 px (1:1)</option>
                </select>
            </div>

            <div class="two-col">
                <div class="form-row"><label>Website Shown On Pin</label><input type="text" id="pinWebsite" placeholder="example.com"></div>
                <div class="form-row">
                    <label>CTA</label>
                    <select id="pinCtaMode">
                        <option value="auto">Auto</option>
                        <option value="custom">Custom text</option>
                        <option value="none">None</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="pinCtaCustomWrap" style="display:none;">
                <label>CTA Text</label>
                <input type="text" id="pinCtaText" placeholder="e.g. Get the Recipe">
            </div>

            <div class="bs-panel-title">Select Category <span class="muted" style="font-weight:400;">(the AI images match this niche)</span></div>
            <div class="form-row">
                <input type="hidden" id="pinImageCategory" data-catpick>
                <p class="muted" style="margin:4px 0 0;">For the pin images. Leave on Auto to use the article's category from step 3.</p>
            </div>

            <div class="bs-panel-title">Pin Templates &amp; Styles</div>
            <div class="form-row">
                <input type="hidden" id="pinImageStyle" value="auto" data-tplpick>
            </div>

            <label class="checkbox-row"><input type="checkbox" id="pinPaletteEnabled"> Use my brand color palette <span class="muted">(optional — leave off for the AI's own automatic colors)</span></label>
            <div id="pinPaletteWrap" style="display:none; margin-top:8px;">
                <div class="form-row">
                    <label>Number of Colors</label>
                    <select id="pinPaletteCount">
                        <option value="3" selected>3 Colors</option>
                        <option value="4">4 Colors</option>
                    </select>
                </div>
                <div class="two-col">
                    <div class="form-row"><label>Color 1</label><input type="color" id="pinPaletteColor1" value="#E91E63"></div>
                    <div class="form-row"><label>Color 2</label><input type="color" id="pinPaletteColor2" value="#FFEB3B"></div>
                </div>
                <div class="two-col">
                    <div class="form-row"><label>Color 3</label><input type="color" id="pinPaletteColor3" value="#212121"></div>
                    <div class="form-row" id="pinPaletteColor4Wrap" style="display:none;"><label>Color 4</label><input type="color" id="pinPaletteColor4" value="#FFFFFF"></div>
                </div>
                <p class="muted">These colors are used for the pin's headline text (cycled line by line).</p>

                <div class="two-col">
                    <div class="form-row"><label>Website Text Color</label><input type="color" id="pinPaletteWebsiteTextColor" value="#FFFFFF"></div>
                    <div class="form-row"><label>Website Background Color</label><input type="color" id="pinPaletteWebsiteBgColor" value="#E91E63"></div>
                </div>
                <div class="two-col">
                    <div class="form-row"><label>CTA Text Color</label><input type="color" id="pinPaletteCtaTextColor" value="#FFFFFF"></div>
                    <div class="form-row"><label>CTA Background Color</label><input type="color" id="pinPaletteCtaBgColor" value="#E91E63"></div>
                </div>
            </div>

            <input type="checkbox" id="collageEnabled" hidden><input type="hidden" id="collageCount" value="auto">
        </div>

        <div class="wizard-nav">
            <button type="button" class="btn-secondary" data-prev="3">← Back</button>
            <button type="button" class="btn-primary" id="createBatchBtn">Create Batch</button>
        </div>
    </div>

</div>
<?php endif; ?>

<script>
const WEBSITES = <?= $websitesJson ?: '[]' ?>;
let resolvedTitles = []; // [{title, source_type, source_value}]
let articleType = 'ideas';
let publishMode = 'now';

const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ---------------- Step navigation ---------------- */
function goToStep(n) {
    document.querySelectorAll('.wizard-step').forEach(el => el.style.display = el.dataset.step == n ? 'block' : 'none');
    document.querySelectorAll('.wizard-step-dot').forEach(el => el.classList.toggle('active', el.dataset.stepDot <= n));
}
document.querySelectorAll('[data-next]').forEach(btn => btn.addEventListener('click', () => goToStep(btn.dataset.next)));
document.querySelectorAll('[data-prev]').forEach(btn => btn.addEventListener('click', () => goToStep(btn.dataset.prev)));

/* ---------------- Step 1 ---------------- */
document.querySelectorAll('[data-article-type]').forEach(btn => {
    btn.addEventListener('click', () => {
        articleType = btn.dataset.articleType;
        document.querySelectorAll('[data-article-type]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('ideasImageBox').style.display = articleType === 'ideas' ? 'block' : 'none';
        document.getElementById('recipeImageBox').style.display = articleType === 'recipe' ? 'block' : 'none';
        document.getElementById('titlesHint').textContent = articleType === 'recipe'
            ? 'Enter one recipe title per line — e.g. "Creamy Garlic Parmesan Chicken – Easy 30-Minute Dinner".'
            : 'Enter one article title per line — e.g. "25 Birthday Outfit Ideas to Feel Your Best on Your Big Day".';
    });
});
document.getElementById('websiteSelect').addEventListener('change', function () {
    const site = WEBSITES.find(w => w.id == this.value);
    const catSelect = document.getElementById('categorySelect');
    const authorSelect = document.getElementById('authorSelect');
    const noAuthorsHint = document.getElementById('noAuthorsHint');
    const authorBox = document.getElementById('authorBox');
    const categoryTitle = document.getElementById('categoryTitle');
    if (!site) {
        catSelect.innerHTML = '<option value="">-- Select a website first --</option>';
        authorSelect.innerHTML = '<option value="">-- Select an author --</option>';
        noAuthorsHint.style.display = 'none';
        authorBox.style.display = 'block';
        categoryTitle.textContent = 'Category';
        return;
    }
    // Only WordPress sites have a selectable author. Shopify / Wix / custom use their own default.
    authorBox.style.display = site.platform === 'wordpress' ? 'block' : 'none';
    categoryTitle.textContent = site.platform === 'shopify' ? 'Blog' : 'Category';
    if (site.platform !== 'wordpress') {
        catSelect.innerHTML = (site.categories && site.categories.length)
            ? site.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('')
            : '<option value="">-- Default (none) --</option>';
        return;
    }
    catSelect.innerHTML = (site.categories && site.categories.length)
        ? site.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('')
        : '<option value="">-- No categories found --</option>';
    if (site.authors && site.authors.length) {
        authorSelect.innerHTML = '<option value="">-- Select an author --</option>' +
            site.authors.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
        noAuthorsHint.style.display = 'none';
    } else {
        authorSelect.innerHTML = '<option value="">-- No authors found --</option>';
        noAuthorsHint.style.display = 'block';
    }
});

/* ---------------- Step 2 ---------------- */
document.getElementById('resolveTitlesBtn').addEventListener('click', async () => {
    const lines = document.getElementById('titlesInput').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    if (!lines.length) { showAlert('Please enter at least one title or link.', 'error'); return; }
    const btn = document.getElementById('resolveTitlesBtn');
    btn.disabled = true; btn.textContent = 'Resolving…';
    // 20 lines per request so 100+ links never hit a server timeout; every line keeps its place.
    try {
        const all = [];
        for (let i = 0; i < lines.length; i += 20) {
            const chunk = lines.slice(i, i + 20);
            btn.textContent = 'Resolving ' + (i + 1) + '–' + (i + chunk.length) + ' of ' + lines.length + '…';
            const fd = new FormData();
            fd.append('lines', JSON.stringify(chunk));
            let result = null;
            for (let attempt = 0; attempt < 2 && !(result && result.ok); attempt++) {
                try { const res = await fetch('ajax-resolve-titles', { method: 'POST', body: fd }); result = await res.json(); }
                catch (e) { result = { ok: false, error: 'Network error.' }; }
            }
            if (result && result.ok && result.items.length === chunk.length) {
                all.push(...result.items);
            } else {
                chunk.forEach(l => all.push({ title: l, source_type: /^https?:\/\//i.test(l) ? 'competitor_url' : 'keyword', source_value: /^https?:\/\//i.test(l) ? l : null }));
            }
        }
        resolvedTitles = all;
        document.getElementById('titlesInput').value = resolvedTitles.map(i => i.title).join('\n');
        showAlert(all.length + ' title(s) resolved.', 'success');
    } catch (e) {
        showAlert('Network error.', 'error');
    }
    btn.disabled = false; btn.textContent = 'Resolve Links → Titles';
});
document.getElementById('rewriteTitlesBtn').addEventListener('click', async () => {
    const lines = document.getElementById('titlesInput').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    if (!lines.length) { showAlert('Please enter at least one title first.', 'error'); return; }
    const btn = document.getElementById('rewriteTitlesBtn');
    btn.disabled = true; btn.textContent = 'Rewriting…';
    const fd = new FormData();
    fd.append('titles', JSON.stringify(lines));
    try {
        const res = await fetch('ajax-rewrite-titles', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            document.getElementById('titlesInput').value = result.titles.join('\n');
            showAlert('Titles rewritten to originals.', 'success');
        } else {
            showAlert('Could not rewrite titles: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error.', 'error');
    }
    btn.disabled = false; btn.textContent = '✨ Rewrite to Your Original Title (AI)';
});

/* ---------------- Step 4 ---------------- */
document.querySelectorAll('[data-publish-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        publishMode = btn.dataset.publishMode;
        document.querySelectorAll('[data-publish-mode]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('pinAutoBox').style.display = publishMode === 'pin_auto' ? 'block' : 'none';
    });
});
document.getElementById('pinPaletteEnabled').addEventListener('change', function () {
    document.getElementById('pinPaletteWrap').style.display = this.checked ? 'block' : 'none';
});
document.getElementById('pinPaletteCount').addEventListener('change', function () {
    document.getElementById('pinPaletteColor4Wrap').style.display = this.value === '4' ? 'block' : 'none';
});
function buildPinColorPalette() {
    if (!document.getElementById('pinPaletteEnabled').checked) return null;
    const count = parseInt(document.getElementById('pinPaletteCount').value, 10);
    const colors = [
        document.getElementById('pinPaletteColor1').value,
        document.getElementById('pinPaletteColor2').value,
        document.getElementById('pinPaletteColor3').value,
    ];
    if (count === 4) colors.push(document.getElementById('pinPaletteColor4').value);
    return {
        enabled: true,
        colors: colors,
        website_text_color: document.getElementById('pinPaletteWebsiteTextColor').value,
        website_bg_color: document.getElementById('pinPaletteWebsiteBgColor').value,
        cta_text_color: document.getElementById('pinPaletteCtaTextColor').value,
        cta_bg_color: document.getElementById('pinPaletteCtaBgColor').value,
    };
}
document.getElementById('lengthMode').addEventListener('change', function () {
    document.getElementById('minWordsRow').style.display = this.value === 'min' ? '' : 'none';
});
document.getElementById('articlePinGapUnit').addEventListener('change', function () {
    const inp = document.getElementById('articlePinGapDays');
    if (this.value === 'minutes') {
        inp.max = 525600;
        document.getElementById('articlePinGapHint').textContent = 'Minutes between this article\'s pins (60 = 1 hour, 1440 = 1 day).';
    } else {
        inp.max = 365;
        if (+inp.value > 365) inp.value = 30;
        document.getElementById('articlePinGapHint').textContent = 'Days between this article\'s pins (recommended: 30).';
    }
});
document.getElementById('pinCtaMode').addEventListener('change', function () {
    document.getElementById('pinCtaCustomWrap').style.display = this.value === 'custom' ? 'block' : 'none';
});

/* ---------------- Create batch ---------------- */
document.getElementById('createBatchBtn').addEventListener('click', async () => {
    const lines = document.getElementById('titlesInput').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    if (!lines.length) { showAlert('Please add at least one title.', 'error'); goToStep(2); return; }
    if (!document.getElementById('websiteSelect').value) { showAlert('Please select a website.', 'error'); goToStep(1); return; }
    const chosenSite = WEBSITES.find(w => w.id == document.getElementById('websiteSelect').value);
    if (chosenSite && chosenSite.platform === 'wordpress' && !document.getElementById('authorSelect').value) { showAlert('Please select an author.', 'error'); goToStep(1); return; }

    // Preserve source metadata for lines that were resolved from links; anything typed fresh is a plain keyword title.
    const titles = lines.map(line => {
        const match = resolvedTitles.find(r => r.title === line);
        return match ? match : { title: line, source_type: 'keyword', source_value: null };
    });

    const payload = {
        name: document.getElementById('batchName').value.trim(),
        article_type: articleType,
        website_id: document.getElementById('websiteSelect').value,
        category: document.getElementById('categorySelect').value,
        wp_author_id: (chosenSite && chosenSite.platform === 'wordpress') ? document.getElementById('authorSelect').value : 0,
        tags_enabled: document.getElementById('tagsEnabled').checked,
        daily_count: document.getElementById('dailyCount').value,
        length_mode: document.getElementById('lengthMode').value,
        min_words: document.getElementById('minWords').value,
        feature_image_w: document.getElementById('featW').value,
        feature_image_h: document.getElementById('featH').value,
        ideas_image_size: document.getElementById('ideasImageSize').value,
        recipe_image_count: document.getElementById('recipeImageCount').value,
        image_quality: document.getElementById('imageQuality').value,
        image_category_id: document.getElementById('imageCategory').value,
        publish_mode: publishMode,
        titles: titles,
    };

    if (publishMode === 'pin_auto') {
        if (!document.getElementById('pinAccount').value) { showAlert('Please select a Pinterest account.', 'error'); goToStep(4); return; }
        payload.pin_settings = {
            pinterest_account_id: document.getElementById('pinAccount').value,
            daily_pin_count: document.getElementById('dailyPinCount').value,
            daily_pin_ramp: document.getElementById('dailyPinRamp').value,
            pins_per_article: document.getElementById('pinsPerArticle').value,
            article_pin_gap_unit: document.getElementById('articlePinGapUnit').value,
            article_pin_gap_minutes: document.getElementById('articlePinGapUnit').value === 'minutes' ? document.getElementById('articlePinGapDays').value : null,
            article_pin_gap_days: document.getElementById('articlePinGapUnit').value === 'minutes' ? 1 : document.getElementById('articlePinGapDays').value,
            pin_image_category_id: document.getElementById('pinImageCategory').value,
            board_mode: document.getElementById('boardMode').value,
            pin_size: document.getElementById('pinSize').value,
            website: document.getElementById('pinWebsite').value.trim(),
            cta_mode: document.getElementById('pinCtaMode').value,
            cta_text: document.getElementById('pinCtaText').value.trim(),
            image_style: document.getElementById('pinImageStyle').value,
            image_category_id: document.getElementById('imageCategory').value,
            color_palette: buildPinColorPalette(),
            collage_enabled: document.getElementById('collageEnabled').checked,
            collage_count: document.getElementById('collageCount').value,
        };
    }

    const btn = document.getElementById('createBatchBtn');
    btn.disabled = true; btn.textContent = 'Creating…';
    const fd = new FormData();
    fd.append('payload', JSON.stringify(payload));
    try {
        const res = await fetch('ajax-create-article-batch', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            window.location.href = 'auto-article-batch-view?batch_id=' + encodeURIComponent(result.batch_id);
        } else {
            showAlert('Could not create batch: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error while creating the batch.', 'error');
    }
    btn.disabled = false; btn.textContent = 'Create Batch';
});
</script>

<script src="../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/category-picker.js') ?: time() ?>"></script>
<script src="../assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/template-picker.js') ?: time() ?>"></script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
