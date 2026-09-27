<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/cw_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'classic-wizard';
$pageTitle = 'Classic Wizard';

$accountsStmt = $pdo->prepare("SELECT id, pinterest_username FROM pinterest_accounts WHERE user_id = ? AND status = 'connected'");
$accountsStmt->execute([$user['id']]);
$accounts = $accountsStmt->fetchAll();

$draftId = (int)($_GET['draft'] ?? 0);
if ($draftId) {
    $draft = cw_get_project($pdo, $draftId, (int)$user['id']);
    if (!$draft) redirect('classic-wizard-drafts');
    if ($draft['status'] === 'scheduled' && $draft['pin_batch_id']) redirect('batch-view?batch_id=' . urlencode($draft['pin_batch_id']));
}

// Design carried over from the public Pin Maker ("Schedule these pins"), used once.
$prefill = null;
if (!$draftId && !empty($_SESSION['free_tool_pending']['wizard']) && is_array($_SESSION['free_tool_pending']['wizard'])) {
    $prefill = $_SESSION['free_tool_pending']['wizard'];
    unset($_SESSION['free_tool_pending']);
}

$boot = [
    'prefill' => $prefill,
    'accounts' => array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['pinterest_username'] ?: 'Account #' . $a['id']], $accounts),
    'textCredits' => round(get_user_text_credits($pdo, (int)$user['id']), 1),
    'draftId' => $draftId ?: null,
    'today' => date('Y-m-d'),
    'tomorrow' => date('Y-m-d', strtotime('+1 day')),
];

include __DIR__ . '/includes/user-header.php';
$v = fn($f) => @filemtime(__DIR__ . '/../' . $f) ?: time();
?>
<link rel="stylesheet" href="../assets/css/classic-wizard.css?v=<?= $v('assets/css/classic-wizard.css') ?>">

<div class="page-header cw-head">
    <h1>Classic Wizard</h1>
    <div class="cw-head-links">
        <a href="classic-wizard-drafts" class="btn-secondary btn-small">Drafts<?php $dc = cw_count_drafts($pdo, (int)$user['id']); if ($dc): ?> <span class="cw-count"><?= $dc ?></span><?php endif; ?></a>
        <a href="classic-wizard-batches" class="btn-secondary btn-small">Scheduled pins</a>
    </div>
</div>

<?php if (empty($accounts)): ?>
    <div class="alert alert-info">Connect a Pinterest account first — <a href="connect-pinterest">connect Pinterest</a>. Your pins are scheduled to that account.</div>
<?php else: ?>

<div id="cwAlert" class="cw-alert" role="status" aria-live="polite"></div>

<ol class="cw-stepper" aria-label="Wizard steps">
    <li class="is-active" data-step="1"><button type="button" data-goto="1"><span class="cw-dot">1</span><span><b>Design</b><small>Pages, size, templates, colours, fonts</small></span></button></li>
    <li data-step="2"><button type="button" data-goto="2"><span class="cw-dot">2</span><span><b>Schedule</b><small>Pace, per-page gap, boards</small></span></button></li>
    <li data-step="3"><button type="button" data-goto="3"><span class="cw-dot">3</span><span><b>Generate &amp; review</b><small>Edit, then approve</small></span></button></li>
</ol>

<!-- =============================== STEP 1: DESIGN =============================== -->
<section class="cw-step-panel" data-panel="1">
    <div class="cw-design">
        <div class="cw-design-main">

            <div class="cw-card">
                <h2 class="cw-card-title">Website</h2>
                <p class="cw-help">Paste your website or any page link. Scanning lists every page from the sitemap.</p>
                <div class="cw-scan-row">
                    <input type="url" id="cwSiteUrl" placeholder="https://yourwebsite.com or https://yourwebsite.com/a-post" autocomplete="url">
                    <button type="button" id="cwScanBtn" class="btn-primary">Scan</button>
                </div>
                <div id="cwScanStatus" class="cw-help"></div>
                <div id="cwPagesBox" class="cw-pages" hidden>
                    <div class="cw-pages-tools">
                        <input type="search" id="cwPageSearch" placeholder="Search pages by title or URL">
                        <button type="button" class="btn-secondary btn-small" id="cwSelectAll">Select all</button>
                        <button type="button" class="btn-secondary btn-small" id="cwSelectNone">Clear</button>
                        <span class="cw-pages-count"><b id="cwSelCount">0</b> of <span id="cwPageTotal">0</span> selected</span>
                    </div>
                    <div id="cwPagesList" class="cw-pages-list" role="list"></div>
                </div>
            </div>

            <div class="cw-card">
                <h2 class="cw-card-title">Pin size</h2>
                <div class="cw-sizes" id="cwSizes" role="radiogroup" aria-label="Pin size"></div>
            </div>

            <div class="cw-card">
                <h2 class="cw-card-title">Image layout</h2>
                <div class="cw-seg" id="cwLayout" role="radiogroup" aria-label="Image layout">
                    <label><input type="radio" name="cwLayout" value="single" checked><span><b>Single image</b><small>One photo per pin</small></span></label>
                    <label><input type="radio" name="cwLayout" value="collage"><span><b>Collage</b><small>2–4 photos per pin</small></span></label>
                    <label><input type="radio" name="cwLayout" value="mix"><span><b>Half &amp; half</b><small>50% single, 50% collage</small></span></label>
                    <label><input type="radio" name="cwLayout" value="custom"><span><b>Custom mix</b><small>Set your own split</small></span></label>
                </div>
                <div id="cwCustomMix" class="cw-mix" hidden>
                    <label for="cwMixRange">Single image <b id="cwMixSingle">70%</b> · Collage <b id="cwMixCollage">30%</b></label>
                    <input type="range" id="cwMixRange" min="0" max="100" step="5" value="70">
                </div>
            </div>

            <div class="cw-card">
                <div class="cw-card-head">
                    <h2 class="cw-card-title">Templates</h2>
                    <label class="cw-switch"><input type="checkbox" id="cwAiTemplates" checked><span></span>AI picks the best template for each page</label>
                </div>
                <p class="cw-help" id="cwTplHelp">Select as many templates as you like. With AI on, each page gets the selected templates that suit its topic; with AI off, pins rotate through your selection.</p>
                <div class="cw-tabs" role="tablist">
                    <button type="button" role="tab" class="is-active" data-tab="all">All templates <span id="cwTplAllCount">70</span></button>
                    <button type="button" role="tab" data-tab="selected">Your selected <span id="cwTplSelCount">0</span></button>
                    <button type="button" role="tab" data-tab="canva">Import from Canva</button>
                </div>
                <div id="cwTplFilters" class="cw-chips"></div>
                <div id="cwTplGrid" class="cw-tpl-grid"></div>
                <div id="cwCanvaBox" class="cw-canva" hidden>
                    <div class="cw-canva-upload">
                        <div>
                            <b>Upload your design (SVG)</b>
                            <p class="cw-help">In Canva: Share → Download → SVG. Type <code>{{title}}</code> where the pin headline goes (optional: <code>{{kicker}}</code>, <code>{{cta}}</code>, <code>{{website}}</code>). Name a rectangle <code>photo</code> to place the page image there — otherwise the image fills the pin behind your design.</p>
                        </div>
                        <div class="cw-canva-form">
                            <input type="text" id="cwCanvaName" placeholder="Design name" maxlength="120">
                            <select id="cwCanvaTextPos" aria-label="Headline position">
                                <option value="none">Headline: from {{title}} only</option>
                                <option value="top">Add headline at top</option>
                                <option value="center">Add headline in centre</option>
                                <option value="bottom">Add headline at bottom</option>
                            </select>
                            <label class="btn-primary cw-file-btn">Upload SVG<input type="file" id="cwCanvaFile" accept=".svg,image/svg+xml" hidden></label>
                        </div>
                    </div>
                    <div id="cwCanvaGrid" class="cw-tpl-grid"></div>
                </div>
            </div>

            <div class="cw-card">
                <h2 class="cw-card-title">Colour palette</h2>
                <div id="cwPalettes" class="cw-palettes" role="radiogroup" aria-label="Colour palette"></div>
                <div id="cwCustomPalette" class="cw-custom-pal" hidden>
                    <label>Background<input type="color" data-role="bg" value="#ffffff"></label>
                    <label>Primary<input type="color" data-role="primary" value="#e60023"></label>
                    <label>Secondary<input type="color" data-role="secondary" value="#fde8ec"></label>
                    <label>Accent<input type="color" data-role="accent" value="#ffb000"></label>
                    <label>Text<input type="color" data-role="dark" value="#111111"></label>
                </div>
            </div>

            <div class="cw-card">
                <h2 class="cw-card-title">Fonts</h2>
                <div id="cwCombos" class="cw-combos" role="radiogroup" aria-label="Font combination"></div>
                <label class="cw-switch cw-custom-font-toggle"><input type="checkbox" id="cwCustomFonts"><span></span>Or use custom font settings</label>
                <div id="cwFontPickers" class="cw-font-pickers" hidden>
                    <div class="cw-fontpick" data-role="main"><label>Main font <small>headline</small></label></div>
                    <div class="cw-fontpick" data-role="secondary"><label>Secondary font <small>website, buttons</small></label></div>
                    <div class="cw-fontpick" data-role="accent"><label>Accent font <small>small tag line</small></label></div>
                </div>
            </div>

            <div class="cw-nav">
                <span class="cw-help" id="cwStep1Summary"></span>
                <button type="button" class="btn-primary" data-next="2">Next: schedule</button>
            </div>
        </div>

        <aside class="cw-design-preview" aria-label="Live preview">
            <div class="cw-preview-sticky">
                <div class="cw-preview-head">
                    <div>
                        <b>Live preview</b>
                        <div class="cw-help cw-ellipsis" id="cwPreviewPage">Sample image — scan a website to preview your own pages</div>
                    </div>
                    <button type="button" class="btn-secondary btn-small" id="cwShuffle" disabled title="Preview another random page">Another page</button>
                </div>
                <div id="cwPreviewGrid" class="cw-preview-grid"></div>
                <p class="cw-help" id="cwPreviewNote"></p>
            </div>
        </aside>
    </div>
</section>

<!-- =============================== STEP 2: SCHEDULE =============================== -->
<section class="cw-step-panel" data-panel="2" hidden>
    <div class="cw-schedule">
        <div class="cw-card">
            <h2 class="cw-card-title">Account</h2>
            <div class="cw-two">
                <div class="form-row"><label for="cwAccount">Pinterest account</label>
                    <select id="cwAccount"></select>
                </div>
                <div class="form-row"><label for="cwName">Schedule name <span class="muted">(optional)</span></label>
                    <input type="text" id="cwName" placeholder="e.g. Recipes — autumn" maxlength="120">
                </div>
            </div>
        </div>

        <div class="cw-card">
            <h2 class="cw-card-title">Publishing pace</h2>
            <div class="cw-seg cw-seg-2" role="radiogroup" aria-label="Pace">
                <label><input type="radio" name="cwPace" value="fixed" checked><span><b>Pins per day</b><small>Same number every day</small></span></label>
                <label><input type="radio" name="cwPace" value="ramp"><span><b>New account warm-up</b><small>Grow the pace month by month</small></span></label>
            </div>
            <div id="cwPaceFixed" class="cw-pace">
                <div class="form-row cw-inline"><label for="cwPerDay">Pins per day</label><input type="number" id="cwPerDay" min="1" max="100" value="6"></div>
                <p class="cw-gap-note" id="cwGapNote"></p>
            </div>
            <div id="cwPaceRamp" class="cw-pace" hidden>
                <table class="cw-ramp">
                    <thead><tr><th>Month</th><th>Pins per day</th><th>Recommended</th></tr></thead>
                    <tbody>
                        <tr><td>Month 1</td><td><input type="number" class="cw-ramp-in" min="1" max="100" value="1"></td><td>1 a day</td></tr>
                        <tr><td>Month 2</td><td><input type="number" class="cw-ramp-in" min="1" max="100" value="3"></td><td>3 a day</td></tr>
                        <tr><td>Month 3</td><td><input type="number" class="cw-ramp-in" min="1" max="100" value="5"></td><td>5 a day</td></tr>
                        <tr><td>Month 4</td><td><input type="number" class="cw-ramp-in" min="1" max="100" value="12"></td><td>10–15 a day</td></tr>
                        <tr><td>Month 5 onward</td><td><input type="number" class="cw-ramp-in" min="1" max="100" value="20"></td><td>20 a day</td></tr>
                    </tbody>
                </table>
                <p class="cw-help">The time between pins adjusts automatically to each month's pace.</p>
            </div>
            <div class="cw-two">
                <div class="form-row"><label for="cwStartDate">First publish day</label><input type="date" id="cwStartDate"></div>
                <div class="form-row"><label for="cwStartTime">First pin of the day at</label><select id="cwStartTime"><?php for ($h = 0; $h < 24; $h++): $hourVal = sprintf('%02d:00', $h); ?><option value="<?= $hourVal ?>" <?= $hourVal === '08:00' ? 'selected' : '' ?>><?= date('g:00 A', mktime($h, 0)) ?> (<?= $hourVal ?>)</option><?php endfor; ?></select></div>
            </div>
            <label class="checkbox-row"><input type="checkbox" id="cwJitter" checked> Vary times by a few minutes so posting looks natural</label>
        </div>

        <div class="cw-card">
            <h2 class="cw-card-title">Per page</h2>
            <div class="cw-two">
                <div class="form-row"><label for="cwPinsPerPage">Pins per page</label><input type="number" id="cwPinsPerPage" min="1" max="10" value="3"></div>
                <div class="form-row"><label for="cwPageGap">Gap between pins of the same page</label>
                    <div style="display:flex; gap:8px;">
                        <input type="number" id="cwPageGap" min="1" max="365" value="30" style="flex:1;">
                        <select id="cwPageGapUnit" style="width:120px;">
                            <option value="minutes">Minutes</option>
                            <option value="days" selected>Days</option>
                        </select>
                    </div>
                    <p class="cw-help" id="cwPageGapHelp">Recommended: 30 days — after a page's first pin publishes, its next pin waits a month.</p>
                </div>
            </div>
            <label class="checkbox-row"><input type="checkbox" id="cwNoLink"> Publish without a link <span class="muted">(useful for brand-new accounts)</span></label>
        </div>

        <div class="cw-card">
            <h2 class="cw-card-title">Boards</h2>
            <div class="cw-seg cw-seg-2" role="radiogroup" aria-label="Board choice">
                <label><input type="radio" name="cwBoardMode" value="selected" checked><span><b>Choose boards</b><small>AI picks the best of your chosen boards per page</small></span></label>
                <label><input type="radio" name="cwBoardMode" value="ai"><span><b>Let AI choose</b><small>From all boards on the account</small></span></label>
            </div>
            <label class="checkbox-row" id="cwAiCreateRow" hidden><input type="checkbox" id="cwAiCreate" checked> Create a new board automatically when none fits</label>
            <div id="cwBoardsWrap">
                <div id="cwBoards" class="cw-boards"><p class="cw-help">Loading boards…</p></div>
                <div class="cw-new-board">
                    <input type="text" id="cwNewBoard" placeholder="New board name" maxlength="50">
                    <button type="button" class="btn-secondary btn-small" id="cwNewBoardBtn">Create board</button>
                </div>
            </div>
        </div>

        <div class="cw-card cw-summary" id="cwSummary"></div>

        <div class="cw-nav">
            <button type="button" class="btn-secondary" data-next="1">Back</button>
            <button type="button" class="btn-primary" id="cwStartGen">Generate pins</button>
        </div>
    </div>
</section>

<!-- =============================== STEP 3: GENERATE & REVIEW =============================== -->
<section class="cw-step-panel" data-panel="3" hidden>
    <div class="cw-genbar">
        <div class="cw-genbar-info">
            <div class="cw-progress" aria-hidden="true"><span id="cwProgBar"></span></div>
            <div><b id="cwProgText">Getting ready…</b> <span class="cw-help" id="cwProgSub"></span></div>
        </div>
        <div class="cw-genbar-actions">
            <span class="cw-help">Text AI credits: <b id="cwCredits"></b></span>
            <button type="button" class="btn-secondary btn-small" id="cwPauseBtn">Pause</button>
            <a href="classic-wizard-drafts" class="btn-secondary btn-small" id="cwLaterBtn">Finish later</a>
            <button type="button" class="btn-primary" id="cwApproveBtn" disabled>Approve &amp; schedule now</button>
        </div>
    </div>
    <p class="cw-help cw-draft-note">Everything here is saved as a draft while you work. Nothing is published until you approve — if you leave, find this run under <a href="classic-wizard-drafts">Drafts</a>.</p>
    <div id="cwSchedPreview" class="cw-help"></div>

    <div class="cw-review">
        <div class="cw-review-pages" id="cwReviewPages"></div>
        <aside class="cw-review-side">
            <div class="cw-preview-sticky" id="cwPageEditor">
                <p class="cw-help">Select a page on the left to restyle all of its pins at once.</p>
            </div>
        </aside>
    </div>
</section>

<div id="cwDone" class="cw-card cw-done" hidden></div>

<!-- =============================== PIN EDITOR MODAL =============================== -->
<div class="cw-modal" id="cwModal" hidden role="dialog" aria-modal="true" aria-labelledby="cwModalTitle">
    <div class="cw-modal-box">
        <div class="cw-modal-head">
            <h3 id="cwModalTitle">Edit pin</h3>
            <button type="button" class="cw-x" id="cwModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="cw-modal-body">
            <div class="cw-modal-preview">
                <canvas id="cwModalCanvas"></canvas>
            </div>
            <div class="cw-modal-form">
                <div class="cw-modal-section">
                    <b>Photos</b> <span class="cw-help">Pick one for a single image, or several for a collage.</span>
                    <div id="cwModalImgs" class="cw-modal-imgs"></div>
                    <label class="btn-secondary btn-small cw-file-btn">Upload image<input type="file" id="cwModalUpload" accept="image/jpeg,image/png,image/webp" hidden></label>
                </div>
                <div class="cw-modal-section">
                    <b>Text on the pin</b>
                    <div class="form-row"><label for="cwMHeadline">Headline</label><input type="text" id="cwMHeadline" maxlength="60"></div>
                    <div class="cw-two">
                        <div class="form-row"><label for="cwMKicker">Small tag line</label><input type="text" id="cwMKicker" maxlength="24"></div>
                        <div class="form-row"><label for="cwMCta">Button text</label><input type="text" id="cwMCta" maxlength="22"></div>
                    </div>
                </div>
                <div class="cw-modal-section">
                    <b>Pin details</b>
                    <div class="form-row"><label for="cwMTitle">Title <span class="cw-counter" data-for="cwMTitle" data-max="100"></span></label><input type="text" id="cwMTitle" maxlength="100"></div>
                    <div class="form-row"><label for="cwMDesc">Description <span class="cw-counter" data-for="cwMDesc" data-max="500"></span></label><textarea id="cwMDesc" maxlength="500" rows="4"></textarea></div>
                    <div class="form-row"><label for="cwMAlt">Alt text <span class="cw-counter" data-for="cwMAlt" data-max="500"></span></label><input type="text" id="cwMAlt" maxlength="500"></div>
                    <div class="form-row"><label for="cwMKeywords">Keywords</label><input type="text" id="cwMKeywords" maxlength="500" placeholder="comma, separated, keywords"></div>
                    <div class="form-row"><label for="cwMBoard">Board</label><select id="cwMBoard"></select></div>
                </div>
                <div class="cw-modal-section">
                    <b>Template</b>
                    <div id="cwModalTpls" class="cw-tpl-grid cw-tpl-grid-sm"></div>
                </div>
            </div>
        </div>
        <div class="cw-modal-foot">
            <button type="button" class="btn-danger btn-small" id="cwModalRemove">Remove pin</button>
            <span class="cw-grow"></span>
            <button type="button" class="btn-secondary" id="cwModalCancel">Cancel</button>
            <button type="button" class="btn-primary" id="cwModalSave">Save pin</button>
        </div>
    </div>
</div>

<script>window.CW_BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/cw-engine.js?v=<?= $v('assets/js/cw-engine.js') ?>"></script>
<script src="../assets/js/cw-wizard.js?v=<?= $v('assets/js/cw-wizard.js') ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
