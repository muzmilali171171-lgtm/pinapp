<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php'; // starts the session; current_user() works for logged-in visitors too
require_once __DIR__ . '/../../includes/free_tool_functions.php';
require_once __DIR__ . '/../../includes/cw_functions.php';

$user = current_user($pdo); // may be null — this page works with no login
$settings = free_tool_get_settings($pdo);

/* ===================== AJAX ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    @set_time_limit(120);
    $out = function (array $d) { echo json_encode($d); exit; };
    $action = (string)$_POST['action'];

    // Light guard against using the preview/scan endpoints as a free image proxy.
    $_SESSION['pm_calls'] = ($_SESSION['pm_calls'] ?? 0) + 1;
    if ($_SESSION['pm_calls'] > 300) $out(['ok' => false, 'error' => 'Too many requests from this browser. Sign up free to keep creating pins.', 'limit_reached' => true]);

    switch ($action) {
        case 'sample':
            $url = cw_sample_image();
            $out(['ok' => (bool)$url, 'url' => $url]);

        case 'scan': {
            // Pages from the site's sitemap; nothing is stored for visitors.
            $url = trim((string)($_POST['url'] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL)) $out(['ok' => false, 'error' => 'That link is not a valid URL.']);
            $pages = pm_read_sitemap($url, 500);
            $out(['ok' => true, 'pages' => array_map(fn($u) => ['id' => null, 'url' => $u, 'title' => ''], $pages)]);
        }

        case 'page_images': {
            $url = trim((string)($_POST['url'] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL)) $out(['ok' => false, 'error' => 'That link is not a valid page URL.']);
            $page = cw_fetch_page($url);
            if (!$page['ok']) $out(['ok' => false, 'error' => $page['error']]);
            $imgs = cw_download_usable_images($page['images'], 4, 10);
            $out(['ok' => true, 'title' => $page['title'], 'images' => $imgs['images'], 'skipped' => $imgs['skipped']]);
        }

        case 'start': {
            // One click on "Generate pins" = one free generation (up to 5 pages).
            if (pm_attempts_remaining($pdo) <= 0) {
                $out(['ok' => false, 'limit_reached' => true, 'error' => 'You’ve used all your free generations. Create a free account to keep making pins — and schedule them automatically.']);
            }
            $urls = array_values(array_unique(array_filter((array)json_decode((string)($_POST['pages'] ?? '[]'), true), fn($u) => is_string($u) && filter_var($u, FILTER_VALIDATE_URL))));
            if (!$urls) $out(['ok' => false, 'error' => 'Select at least one page.']);
            $urls = array_slice($urls, 0, PM_MAX_PAGES_PER_RUN);
            free_tool_record_attempt($pdo, 'pin_maker');
            $token = bin2hex(random_bytes(12));
            $_SESSION['pm_run'] = ['token' => $token, 'urls' => $urls, 'done' => [], 'expires' => time() + 3600];
            $out(['ok' => true, 'token' => $token, 'pages' => $urls, 'remaining' => pm_attempts_remaining($pdo)]);
        }

        case 'prepare': {
            // Images + AI text for one page of the current generation.
            $run = $_SESSION['pm_run'] ?? null;
            $url = trim((string)($_POST['url'] ?? ''));
            if (!$run || $run['token'] !== ($_POST['token'] ?? '') || $run['expires'] < time() || !in_array($url, $run['urls'], true)) {
                $out(['ok' => false, 'error' => 'This generation has expired. Click “Generate pins” again.']);
            }
            if (in_array($url, $run['done'], true)) $out(['ok' => false, 'error' => 'This page was already generated.']);
            $_SESSION['pm_run']['done'][] = $url;

            $fetched = cw_fetch_page($url);
            if (!$fetched['ok']) $out(['ok' => false, 'skip' => true, 'error' => $fetched['error']]);
            $imgs = cw_download_usable_images($fetched['images'], 6, 14);
            if (!$imgs['images']) {
                $out(['ok' => false, 'skip' => true, 'error' => $imgs['skipped']
                    ? "All {$imgs['skipped']} image(s) on this page are too small or too wide for a pin."
                    : 'No images were found on this page.']);
            }
            $pageInfo = ['url' => $url, 'title' => $fetched['title'] ?: $url, 'description' => $fetched['description'], 'excerpt' => $fetched['excerpt']];
            $count = max(1, min(PM_MAX_PINS_PER_PAGE, (int)($_POST['count'] ?? 3)));
            $content = cw_ai_page_content($pdo, 0, $pageInfo, $count, [], 'fixed', false);
            if (!$content['ok']) $content = cw_plain_page_content($pageInfo, $count);
            $out(['ok' => true, 'title' => $pageInfo['title'], 'images' => $imgs['images'], 'items' => $content['items'], 'category' => $content['category']]);
        }

        case 'keep': {
            // "Schedule these pins": keep the design so the Classic Wizard opens with it after signup/login.
            $config = json_decode((string)($_POST['config'] ?? ''), true);
            if (!is_array($config)) $out(['ok' => false, 'error' => 'Invalid settings.']);
            $_SESSION[FREE_TOOL_SESSION_KEY] = ['wizard' => $config, 'generated_at' => date('c')];
            $out(['ok' => true, 'next' => $user ? '../../user/classic-wizard?from_freetool=1' : '../../auth/register?from=freetool']);
        }
    }
    $out(['ok' => false, 'error' => 'Unknown action.']);
}

/* ===================== Page ===================== */
$prefillUrl = trim($_GET['url'] ?? '');
$remaining = pm_attempts_remaining($pdo);
$maxAttempts = pm_max_attempts($pdo);
$boot = [
    'remaining' => $remaining, 'max' => $maxAttempts, 'loggedIn' => (bool)$user,
    'maxPages' => PM_MAX_PAGES_PER_RUN, 'maxPins' => PM_MAX_PINS_PER_PAGE,
    'prefillUrl' => $prefillUrl, 'auto' => $prefillUrl !== '' && !empty($_GET['auto']),
];
$v = fn($f) => @filemtime(__DIR__ . '/../../' . $f) ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Free AI Pinterest Pin Maker: Unlimited Pins | ' . SITE_BRAND,
    'description' => 'Create Pinterest pins from any page free. Paste a link, pick from unlimited templates, colours and fonts, and let AI write the titles and descriptions.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/pinterest-pin-maker/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Pinterest Pin Maker', 'free-tools/pinterest-pin-maker/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= $v('assets/css/style.css') ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= $v('assets/css/free-tool.css') ?>">
<link rel="stylesheet" href="../../assets/css/classic-wizard.css?v=<?= $v('assets/css/classic-wizard.css') ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page pm-page">
    <div class="ft-hero">
        <h1>Free AI Pinterest Pin Maker</h1>
        <p class="ft-sub">Paste a link, choose templates, colours and fonts, and get ready-to-post pins with AI-written titles and descriptions.</p>
    </div>

    <div class="pm-attempts" id="pmAttempts" role="status"></div>
    <div id="cwAlert" class="cw-alert" role="status" aria-live="polite"></div>

    <ol class="cw-stepper pm-stepper" aria-label="Steps">
        <li class="is-active" data-step="1"><button type="button" data-goto="1"><span class="cw-dot">1</span><span><b>Design</b><small>Pages, size, templates, colours, fonts</small></span></button></li>
        <li data-step="2"><button type="button" data-goto="2"><span class="cw-dot">2</span><span><b>Your pins</b><small>Edit, download or schedule</small></span></button></li>
    </ol>

    <!-- =============================== STEP 1: DESIGN =============================== -->
    <section class="cw-step-panel" data-panel="1">
        <div class="cw-design">
            <div class="cw-design-main">

                <div class="cw-card">
                    <h2 class="cw-card-title">Website or page</h2>
                    <p class="cw-help">Paste a page link to make pins for it, or your homepage to pick pages from your sitemap. Up to <?= PM_MAX_PAGES_PER_RUN ?> pages per generation.</p>
                    <div class="cw-scan-row">
                        <input type="url" id="cwSiteUrl" placeholder="https://yourwebsite.com/a-post" value="<?= e($prefillUrl) ?>" autocomplete="url">
                        <button type="button" id="cwScanBtn" class="btn-primary">Scan</button>
                    </div>
                    <div id="cwScanStatus" class="cw-help"></div>
                    <div id="cwPagesBox" class="cw-pages" hidden>
                        <div class="cw-pages-tools">
                            <input type="search" id="cwPageSearch" placeholder="Search pages by URL">
                            <button type="button" class="btn-secondary btn-small" id="cwSelectNone">Clear</button>
                            <span class="cw-pages-count"><b id="cwSelCount">0</b> of <?= PM_MAX_PAGES_PER_RUN ?> selected · <span id="cwPageTotal">0</span> pages</span>
                        </div>
                        <div id="cwPagesList" class="cw-pages-list" role="list"></div>
                    </div>
                    <div class="form-row cw-inline pm-ppp"><label for="pmPinsPerPage">Pins per page</label>
                        <select id="pmPinsPerPage"><?php for ($i = 1; $i <= PM_MAX_PINS_PER_PAGE; $i++): ?><option value="<?= $i ?>" <?= $i === PM_MAX_PINS_PER_PAGE ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
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
                    <p class="cw-help">Select as many as you like. With AI on, each page gets the selected templates that suit its topic.</p>
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
                                <b>Use your own design (SVG)</b>
                                <p class="cw-help">In Canva: Share → Download → SVG. Type <code>{{title}}</code> where the headline goes (optional <code>{{kicker}}</code>, <code>{{cta}}</code>, <code>{{website}}</code>). Name a rectangle <code>photo</code> to place the page image there. Designs stay in this browser only — create an account to keep them.</p>
                            </div>
                            <div class="cw-canva-form">
                                <input type="text" id="cwCanvaName" placeholder="Design name" maxlength="120">
                                <select id="cwCanvaTextPos" aria-label="Headline position">
                                    <option value="none">Headline: from {{title}} only</option>
                                    <option value="top">Add headline at top</option>
                                    <option value="center">Add headline in centre</option>
                                    <option value="bottom">Add headline at bottom</option>
                                </select>
                                <label class="btn-primary cw-file-btn">Choose SVG<input type="file" id="cwCanvaFile" accept=".svg,image/svg+xml" hidden></label>
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
                    <button type="button" class="btn-primary" id="pmGenerate">Generate pins</button>
                </div>
            </div>

            <aside class="cw-design-preview" aria-label="Live preview">
                <div class="cw-preview-sticky">
                    <div class="cw-preview-head">
                        <div>
                            <b>Live preview</b>
                            <div class="cw-help cw-ellipsis" id="cwPreviewPage">Sample image — paste a link to preview your own page</div>
                        </div>
                        <button type="button" class="btn-secondary btn-small" id="cwShuffle" disabled title="Preview another selected page">Another page</button>
                    </div>
                    <div id="cwPreviewGrid" class="cw-preview-grid"></div>
                    <p class="cw-help" id="cwPreviewNote"></p>
                </div>
            </aside>
        </div>
    </section>

    <!-- =============================== STEP 2: RESULTS =============================== -->
    <section class="cw-step-panel" data-panel="2" hidden>
        <div class="cw-genbar">
            <div class="cw-genbar-info">
                <div class="cw-progress" aria-hidden="true"><span id="cwProgBar"></span></div>
                <div><b id="cwProgText">Getting ready…</b> <span class="cw-help" id="cwProgSub"></span></div>
            </div>
            <div class="cw-genbar-actions">
                <button type="button" class="btn-secondary btn-small" data-goto="1">Back to design</button>
                <button type="button" class="btn-secondary" id="pmDownloadAll" disabled>Download all</button>
                <button type="button" class="btn-primary" id="pmKeep">Schedule these pins</button>
            </div>
        </div>
        <p class="cw-help cw-draft-note">Click a pin to edit its text, photos or template. Pins made here are not saved — download them, or create a free account to schedule pins automatically.</p>
        <div class="cw-review pm-review">
            <div class="cw-review-pages" id="cwReviewPages"></div>
        </div>
    </section>

    <!-- ===================== Marketing CTA ===================== -->
    <div class="ft-marketing">
        <?php if (!empty($settings['coupon_code'])): ?>
            <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code <?= e($settings['coupon_code']) ?> for <?= (int)$settings['discount_percent'] ?>% off today!</div>
        <?php endif; ?>
        <h2><?= e($settings['marketing_heading']) ?></h2>
        <p><?= e($settings['marketing_body']) ?></p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
        <?php if (!empty($settings['coupon_code'])): ?>
            <div class="ft-marketing-note">Enter code <strong><?= e($settings['coupon_code']) ?></strong> at checkout</div>
        <?php endif; ?>
    </div>

    <!-- ===================== FAQ ===================== -->
    <div class="ft-faq">
        <h2>Pinterest Pin Maker FAQ</h2>
        <details><summary>Is this Pinterest Pin Maker really free?</summary><p>Yes — every visitor gets <?= (int)$maxAttempts ?> free generations with no account. Each generation makes up to <?= PM_MAX_PINS_PER_PAGE ?> pins for up to <?= PM_MAX_PAGES_PER_RUN ?> pages. Sign up free for more pins and automatic scheduling.</p></details>
        <details><summary>Where do the pin images come from?</summary><p>From the page you paste — its featured image and the photos in the post. Images that are too small or too wide for a pin are skipped. You can also upload your own photo for any pin.</p></details>
        <details><summary>Can I use my own Canva design?</summary><p>Yes. Export it from Canva as SVG, open “Import from Canva” in the Templates section, and add <code>{{title}}</code> where the headline should appear.</p></details>
        <details><summary>Can I download my pins?</summary><p>Yes — each pin on its own, or all of them as one ZIP file. Titles and descriptions can be copied with one click.</p></details>
        <details><summary>What happens when I click “Schedule these pins”?</summary><p>You create a free account and the Classic Wizard opens with your website, templates, colours and fonts already set, ready to schedule pins to Pinterest.</p></details>
    </div>
</div>

<!-- =============================== PIN EDITOR =============================== -->
<div class="cw-modal" id="cwModal" hidden role="dialog" aria-modal="true" aria-labelledby="cwModalTitle">
    <div class="cw-modal-box">
        <div class="cw-modal-head">
            <h3 id="cwModalTitle">Edit pin</h3>
            <button type="button" class="cw-x" id="cwModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="cw-modal-body">
            <div class="cw-modal-preview"><canvas id="cwModalCanvas"></canvas></div>
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
                    <div class="form-row"><label for="cwMKeywords">Keywords</label><input type="text" id="cwMKeywords" maxlength="500"></div>
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

<?php render_site_footer($pdo); ?>
<script>window.PM_BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
<script src="../../assets/js/cw-engine.js?v=<?= $v('assets/js/cw-engine.js') ?>"></script>
<script src="../../assets/js/cw-pinmaker.js?v=<?= $v('assets/js/cw-pinmaker.js') ?>"></script>
</body>
</html>
