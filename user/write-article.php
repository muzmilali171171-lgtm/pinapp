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
$activePage = 'write-article';
$pageTitle = 'Write Article';

$featureCheck = require_plan_feature($pdo, (int)$user['id'], 'single_article_writer');
if (!$featureCheck['allowed']) {
    render_user_feature_locked($pageTitle, $activePage, $user, $featureCheck['message']);
    exit;
}

$articleSettings = get_article_settings($pdo);
$hasTextModel = $articleSettings && !empty($articleSettings['text_provider']);

$websites = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND status = 'connected' AND platform <> 'none' ORDER BY platform, site_name");
$websites->execute([$user['id']]);
$websites = $websites->fetchAll();
$websitesJson = json_encode(array_map(function ($w) {
    return [
        'id' => (int)$w['id'],
        'name' => ($w['site_name'] ?: $w['site_url']) . ' (' . platform_label($w['platform']) . ')',
        'platform' => $w['platform'],
        'categories' => json_decode($w['categories_cache'] ?: '[]', true) ?: [],
    ];
}, $websites));

$initialId = (int)($_GET['id'] ?? 0);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Write Article</h1></div>

<div id="alertBox"></div>
<div id="app"></div>

<?php if (!$hasTextModel): ?>
<div class="alert alert-info">AI article writing is not available right now. Please check back soon.</div>
<?php endif; ?>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
const WEBSITES = <?= $websitesJson ?>;
const HAS_TEXT_MODEL = <?= $hasTextModel ? 'true' : 'false' ?>;
const INITIAL_ID = <?= $initialId ?>;
const AJAX_URL = 'ajax-write-article';

const app = document.getElementById('app');
const alertBox = document.getElementById('alertBox');
let currentArticle = null;
let ckEditorInstance = null;

function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function clearAlert() { alertBox.innerHTML = ''; }
function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function callApi(formData) {
    try {
        const res = await fetch(AJAX_URL, { method: 'POST', body: formData });
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (parseErr) {
            console.error('Non-JSON response:', text);
            return { ok: false, error: 'Unexpected server response (HTTP ' + res.status + '). Please try again, or use fewer AI images.' };
        }
    } catch (networkErr) {
        console.error(networkErr);
        return { ok: false, error: 'Network error — the request may have timed out. Please try again.' };
    }
}

/* ---------------- Step 1: new article form ---------------- */
function renderNewForm() {
    app.innerHTML = `
      <div class="card">
        <form id="outlineForm">
          <div class="form-row">
            <label>Source</label>
            <select name="source_type" id="sourceType">
              <option value="keyword">Keyword / Title</option>
              <option value="competitor_url">Competitor URL</option>
            </select>
          </div>
          <div class="form-row">
            <label id="sourceLabel">Keyword or Title</label>
            <input type="text" name="source_value" placeholder="e.g. 18 Short Bob Hairstyles for Dark Thin Fine Hair to Make Hair Look Fuller" required>
          </div>
          <button type="submit" class="btn-primary" id="outlineBtn" ${HAS_TEXT_MODEL ? '' : 'disabled'}>Generate Outline</button>
        </form>
      </div>`;

    document.getElementById('sourceType').addEventListener('change', function () {
        document.getElementById('sourceLabel').textContent = this.value === 'competitor_url' ? 'Competitor URL' : 'Keyword or Title';
    });

    document.getElementById('outlineForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        clearAlert();
        const btn = document.getElementById('outlineBtn');
        btn.disabled = true;
        btn.textContent = 'Generating outline...';

        const fd = new FormData(this);
        fd.append('action', 'generate_outline');
        const data = await callApi(fd);

        btn.disabled = false;
        btn.textContent = 'Generate Outline';

        if (!data.ok) { showAlert(escapeHtml(data.error), 'error'); return; }
        currentArticle = data.article;
        history.replaceState(null, '', '?id=' + currentArticle.id + '&step=outline');
        renderOutline();
    });
}

/* ---------------- Step 2: outline review ---------------- */
function renderOutline() {
    const rows = currentArticle.sections.map((s) => `
      <div class="form-row" style="border-bottom:1px solid var(--border); padding-bottom:14px;">
        <label>${escapeHtml(s.heading)}</label>
        <p class="muted" style="margin:2px 0 0;">${escapeHtml(s.brief)}</p>
      </div>`).join('');

    app.innerHTML = `
      <div class="card">
        <h2>${escapeHtml(currentArticle.title)}</h2>
        <p class="muted">Review the outline, then generate the full article.</p>
        <form id="contentForm">
          ${rows}
          <button type="submit" class="btn-primary" id="contentBtn">Generate Full Article</button>
        </form>
      </div>`;

    document.getElementById('contentForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        clearAlert();
        const btn = document.getElementById('contentBtn');
        btn.disabled = true;
        btn.textContent = 'Writing article...';

        const fd = new FormData();
        fd.append('action', 'generate_content');
        fd.append('id', currentArticle.id);
        const data = await callApi(fd);

        btn.disabled = false;
        btn.textContent = 'Generate Full Article';

        if (!data.ok) { showAlert(escapeHtml(data.error), 'error'); return; }
        currentArticle = data.article;
        history.replaceState(null, '', '?id=' + currentArticle.id + '&step=edit');
        renderEditor();
    });
}

/* ---------------- Step 3: edit & publish (no images) ---------------- */
function renderEditor() {
    const websiteOptions = WEBSITES.map(w =>
        `<option value="${w.id}" ${currentArticle.website_id == w.id ? 'selected' : ''}>${escapeHtml(w.name)}</option>`
    ).join('');

    app.innerHTML = `
      <div class="card">
        <form id="publishForm">
          <div class="form-row">
            <label>Title</label>
            <input type="text" name="title" id="titleInput" value="${escapeHtml(currentArticle.title)}" required>
          </div>
          <div class="form-row">
            <label>Content</label>
            <textarea name="content" id="editor">${currentArticle.content || ''}</textarea>
          </div>
          <div class="two-col">
            <div class="form-row">
              <label>Website</label>
              <select name="website_id" id="websiteSelect">
                <option value="">-- Select website --</option>
                ${websiteOptions}
              </select>
              ${WEBSITES.length === 0 ? '<p class="muted">No connected websites yet. <a href="websites">Add one</a>.</p>' : ''}
            </div>
            <div class="form-row">
              <label>Category</label>
              <input type="text" name="category" id="categoryInput" list="categoryList" value="${escapeHtml(currentArticle.category || '')}" placeholder="type or pick a category">
              <datalist id="categoryList"></datalist>
            </div>
          </div>
          <div class="form-row">
            <label>Tags (comma-separated, optional)</label>
            <input type="text" name="tags" value="${escapeHtml(currentArticle.tags || '')}" placeholder="tag one, tag two">
          </div>
          <button type="submit" class="btn-secondary" id="saveDraftBtn">Save Draft</button>
          <button type="submit" class="btn-primary" id="publishBtn" data-publish="1">Publish Now</button>
        </form>
      </div>`;

    if (ckEditorInstance) { ckEditorInstance.destroy().catch(() => {}); ckEditorInstance = null; }
    ClassicEditor.create(document.querySelector('#editor')).then(editor => { ckEditorInstance = editor; }).catch(console.error);

    function refreshCategoryList() {
        const websiteId = document.getElementById('websiteSelect').value;
        const site = WEBSITES.find(w => String(w.id) === String(websiteId));
        const list = document.getElementById('categoryList');
        list.innerHTML = '';
        (site ? site.categories : []).forEach(c => {
            const o = document.createElement('option');
            o.value = c.name;
            list.appendChild(o);
        });
    }
    document.getElementById('websiteSelect').addEventListener('change', refreshCategoryList);
    refreshCategoryList();

    document.getElementById('publishForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        clearAlert();
        const clickedBtn = e.submitter;
        const isPublish = clickedBtn && clickedBtn.id === 'publishBtn';

        if (ckEditorInstance) {
            document.querySelector('#editor').value = ckEditorInstance.getData();
        }

        const saveBtn = document.getElementById('saveDraftBtn');
        const pubBtn = document.getElementById('publishBtn');
        saveBtn.disabled = true; pubBtn.disabled = true;
        clickedBtn.textContent = isPublish ? 'Publishing...' : 'Saving...';

        const fd = new FormData(this);
        fd.append('action', 'save');
        fd.append('id', currentArticle.id);
        if (isPublish) fd.append('publish_now', '1');

        const data = await callApi(fd);

        saveBtn.disabled = false; pubBtn.disabled = false;
        saveBtn.textContent = 'Save Draft';
        pubBtn.textContent = 'Publish Now';

        if (!data.ok) { showAlert(escapeHtml(data.error), 'error'); return; }

        currentArticle = data.article;
        if (data.published) {
            showAlert('Published! <a href="' + escapeHtml(currentArticle.wp_post_url || '#') + '" target="_blank" rel="noopener">View post</a> &middot; <a href="articles">Back to My Articles</a>', 'success');
        } else {
            showAlert('Draft saved. <a href="articles">Back to My Articles</a>', 'success');
        }
    });
}

/* ---------------- Boot ---------------- */
async function boot() {
    if (INITIAL_ID) {
        const fd = new FormData();
        fd.append('action', 'get_state');
        fd.append('id', INITIAL_ID);
        const data = await callApi(fd);
        if (data.ok) {
            currentArticle = data.article;
            if (currentArticle.content) {
                renderEditor();
            } else {
                renderOutline();
            }
            return;
        }
    }
    renderNewForm();
}
boot();
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
