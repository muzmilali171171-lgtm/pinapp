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
$activePage = 'bulk-schedule';
$pageTitle = 'Bulk Pin Scheduler';

$featureCheck = require_plan_feature($pdo, (int)$user['id'], 'bulk_scheduling');
if (!$featureCheck['allowed']) {
    render_user_feature_locked($pageTitle, $activePage, $user, $featureCheck['message']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? AND status = 'connected'");
$stmt->execute([$user['id']]);
$accounts = $stmt->fetchAll();

$boardsByAccount = [];
foreach ($accounts as $acc) {
    $rows = get_boards_for_account($pdo, $acc);
    $boardsByAccount[$acc['id']] = array_map(function ($b) {
        return [
            'value' => 'row:' . $b['id'],
            'name' => $b['board_name'],
            'description' => $b['board_description'] ?: '',
            'status' => $b['status'],
        ];
    }, $rows);
}

$articleSettings = get_article_settings($pdo);
$hasPinAi = $articleSettings && (!empty($articleSettings['pin_text_provider']) || !empty($articleSettings['text_provider']));
$imageProvider = $articleSettings['pin_image_provider'] ?? 'deepinfra';
$imageIsCloudflare = $imageProvider === 'cloudflare';
$myCredits = get_user_credits($pdo, $user['id']);
$myImageCredits = get_user_image_credits($pdo, $user['id']);
$myTextCredits = get_user_text_credits($pdo, $user['id']);
$qualityCosts = credit_pricing_get($pdo);

// Pages sent from the Design editor ("Use This Design") — each page becomes a pin row.
$designSet = null;
$designToken = preg_replace('/[^a-f0-9]/', '', (string)($_GET['designs'] ?? ''));
if ($designToken !== '' && !empty($_SESSION['design_use'][$designToken])) {
    $ds = $_SESSION['design_use'][$designToken];
    $designItems = [];
    foreach ((array)($ds['items'] ?? []) as $it) {
        $f = basename((string)($it['file'] ?? ''));
        if (preg_match('/^design_[a-f0-9]{16}\.jpg$/', $f) && is_file(__DIR__ . '/../uploads/pins/' . $f)) {
            $designItems[] = ['path' => 'uploads/pins/' . $f, 'file' => $f, 'name' => (string)($it['name'] ?? '')];
        }
    }
    if ($designItems) $designSet = ['items' => $designItems, 'title' => (string)($ds['title'] ?? '')];
}

// Resume an autosaved draft (?draft=<batch_id>), if it belongs to this user and is still a draft.
$initialDraft = null;
$draftBatchId = trim($_GET['draft'] ?? '');
if ($draftBatchId !== '') {
    $draftBatch = get_batch($pdo, $draftBatchId, $user['id']);
    if ($draftBatch && $draftBatch['status'] === 'draft') {
        $initialDraft = [
            'batch_id' => $draftBatch['batch_id'],
            'name' => $draftBatch['name'],
            'state' => $draftBatch['draft_json'] ? json_decode($draftBatch['draft_json'], true) : null,
        ];
    }
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Bulk Pin Scheduler</h1>
    <div style="display:flex; gap:10px;">
        <span class="muted" id="draftStatusLine" style="align-self:center;"></span>
        <a href="batches" class="btn-secondary">Batches</a>
        <a href="schedule-list" class="btn-secondary">View Scheduled Pins</a>
    </div>
</div>

<div id="alertBox"></div>

<?php if (empty($accounts)): ?>
    <div class="alert alert-info">You need to <a href="connect-pinterest">connect a Pinterest account</a> before bulk-scheduling pins.</div>
<?php else: ?>

<div class="bulk-scheduler-shell">

    <!-- LEFT PANEL: collapsible settings accordion -->
    <div class="bs-panel bs-settings-panel">

        <div class="bs-panel-title bs-batch-name-title"><span class="step-num">•</span> Batch Name</div>
        <div class="form-row">
            <input type="text" id="bsBatchName" placeholder="e.g. October Home Decor Push" maxlength="255">
        </div>

        <!-- 1. ACCOUNT, BOARD & SCHEDULING -->
        <div class="accordion-item">
            <button type="button" class="accordion-header open" data-accordion="acctBoard">
                <span class="step-num">1</span> Account, Board &amp; Scheduling <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body open" id="acc-acctBoard">

                <div class="form-row">
                    <label>Pinterest Account</label>
                    <select id="bsAccount">
                        <option value="">-- Select account --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int)$acc['id'] ?>"><?= e($acc['pinterest_username'] ?: 'Account #' . $acc['id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="board-mode-toggle" id="boardModeToggle" style="display:none;">
                    <button type="button" class="board-mode-btn active" data-board-mode="select">Select Board</button>
                    <button type="button" class="board-mode-btn" data-board-mode="create">Create Board</button>
                </div>

                <!-- SELECT BOARD -->
                <div id="bsSelectBoardBox" style="display:none;">
                    <div class="board-picker-toolbar">
                        <input type="text" id="bsBoardSearch" placeholder="Search boards…">
                        <div class="view-toggle">
                            <button type="button" class="view-toggle-btn active" data-board-view="grid" title="Grid view">▦</button>
                            <button type="button" class="view-toggle-btn" data-board-view="list" title="List view">☰</button>
                        </div>
                    </div>
                    <div id="bsBoardPicker" class="board-picker board-picker-grid"></div>
                    <input type="hidden" id="bsBoard" value="">
                    <button type="button" class="btn-secondary btn-small" id="bsInsertSelectedBoardBtn" style="margin-top:8px;">✓ Insert Board Into All Pins</button>
                </div>

                <!-- CREATE BOARD -->
                <div id="bsCreateBoardBox" style="display:none;">
                    <div class="board-mode-toggle board-mode-toggle-sub">
                        <button type="button" class="board-mode-btn active" data-create-mode="manual">Enter Manually</button>
                        <button type="button" class="board-mode-btn" data-create-mode="ai">Create with AI</button>
                    </div>
                    <div id="bsCreateManual">
                        <div class="form-row">
                            <label>New Board Name</label>
                            <input type="text" id="bsNewBoardName" placeholder="e.g. Home Decor Ideas">
                        </div>
                        <div class="form-row">
                            <label>New Board Description</label>
                            <textarea id="bsNewBoardDescription" placeholder="What this board is about..."></textarea>
                        </div>
                        <button type="button" class="btn-secondary btn-small" id="bsInsertManualBoardBtn">✓ Insert Board Into All Pins</button>
                    </div>
                    <div id="bsCreateAi" style="display:none;">
                        <div class="board-mode-toggle board-mode-toggle-sub">
                            <button type="button" class="board-mode-btn active" data-board-ai-mode="single">Single Board</button>
                            <button type="button" class="board-mode-btn" data-board-ai-mode="multiple">Multiple Boards</button>
                        </div>

                        <div id="bsBoardAiSingle">
                            <div class="form-row">
                                <label>Board Keyword / Topic</label>
                                <input type="text" id="bsBoardAiKeyword" placeholder="e.g. cozy farmhouse kitchen ideas">
                            </div>
                            <button type="button" class="btn-secondary btn-small" id="bsBoardAiBtn">✨ Generate Board Name &amp; Description</button>
                            <p class="muted" id="bsBoardAiResult" style="margin-top:8px;"></p>
                        </div>

                        <div id="bsBoardAiMultiple" style="display:none;">
                            <p class="muted" style="margin-top:0;">AI checks each pin's title against your existing boards on this account first. If a good match exists, that pin is assigned to it. If not, AI writes a new board name + description for that pin (shown under its board selector below). Pins are processed 10 at a time.</p>
                            <button type="button" class="btn-secondary btn-small" id="bsBoardAiMultiBtn">🪄 Assign Boards With AI</button>
                            <div id="bsBoardAiMultiStatus" class="ai-progress" style="margin-top:8px; max-height:180px; overflow-y:auto;"></div>
                        </div>
                    </div>
                    <p class="muted bs-panel-hint">New boards are created automatically on Pinterest about 5 minutes before their first pin is due to publish.</p>
                </div>

                <hr class="bs-divider">

                <div class="schedule-mode-toggle">
                    <button type="button" class="board-mode-btn active" data-sched-mode="interval">Fixed Interval</button>
                    <button type="button" class="board-mode-btn" data-sched-mode="perday">Pins Per Day</button>
                </div>
                <div id="bsIntervalMode">
                    <div class="two-col">
                        <div class="form-row">
                            <label>First Pin Publish Time</label>
                            <input type="datetime-local" id="bsStartTime">
                        </div>
                        <div class="form-row">
                            <label>Interval Between Pins (min)</label>
                            <input type="number" id="bsInterval" value="60" min="1">
                        </div>
                    </div>
                    <button type="button" class="btn-secondary btn-small" id="bsApplySchedule">Apply times to all rows</button>
                </div>
                <div id="bsPerDayMode" style="display:none;">
                    <div class="two-col">
                        <div class="form-row">
                            <label>Pins Per Day</label>
                            <input type="number" id="bsPinsPerDay" value="3" min="1">
                        </div>
                        <div class="form-row">
                            <label>Gap Between Pins (hours)</label>
                            <input type="number" id="bsGapHours" value="8" min="0.5" step="0.5">
                        </div>
                    </div>
                    <div class="two-col">
                        <div class="form-row">
                            <label>Start Date</label>
                            <input type="date" id="bsStartDate">
                        </div>
                        <div class="form-row">
                            <label>First Pin Time of Day</label>
                            <input type="time" id="bsDayStartTime" value="09:00">
                        </div>
                    </div>
                    <button type="button" class="btn-secondary btn-small" id="bsApplyPerDaySchedule">Apply times to all rows</button>
                </div>
            </div>
        </div>

        <!-- 2. BULK INSERT -->
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion="bulkInsert">
                <span class="step-num">2</span> Bulk Insert <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body" id="acc-bulkInsert">
                <p class="muted" style="margin-top:0;">One value per line — fills the rows top to bottom.</p>
                <div class="bulk-insert-grid">
                    <div>
                        <label>Titles</label>
                        <textarea id="biTitles" placeholder="One title per line"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-insert="title">Insert Titles</button>
                    </div>
                    <div>
                        <label>Descriptions</label>
                        <textarea id="biDescriptions" placeholder="One description per line"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-insert="description">Insert Descriptions</button>
                    </div>
                    <div>
                        <label>Links</label>
                        <textarea id="biLinks" placeholder="One link per line"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-insert="link">Insert Links</button>
                    </div>
                    <div>
                        <label>Image Alt Text</label>
                        <textarea id="biAlt" placeholder="One alt text per line"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-insert="alt">Insert Alt Text</button>
                    </div>
                </div>

                <div class="form-row" style="margin-top:14px;">
                    <label>Tags <span class="muted" style="font-weight:400;">(default for every pin — override per pin below)</span></label>
                    <div class="tag-input-box" id="tagInputBox">
                        <div class="tag-pills" id="tagPills"></div>
                        <input type="text" id="tagInputField" placeholder="Type a tag and press Enter">
                    </div>
                </div>
                <div class="form-row">
                    <label>Keywords <span class="muted" style="font-weight:400;">(comma separated — default for every pin)</span></label>
                    <input type="text" id="bsKeywords" placeholder="e.g. home decor, cozy living room, small space ideas">
                </div>
            </div>
        </div>

        <!-- 3. CREATE WITH AI -->
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion="createAi">
                <span class="step-num">3</span> Create with AI <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body" id="acc-createAi">
                <div class="bs-ai-box">
                    <span class="ai-badge">✨ AI Writer</span>
                    <div class="credits-note">Your Text AI balance: <strong><?= number_format($myTextCredits, 1) ?></strong> credits <?php if ($myTextCredits <= 0): ?><span class="credits-low-warn">— low! <a href="upgrade">Upgrade your plan</a></span><?php endif; ?></div>
                    <?php if (!$hasPinAi): ?>
                        <div class="alert alert-info">AI writing is not available right now. Please check back soon.</div>
                    <?php endif; ?>
                    <div class="form-row">
                        <label>Main Keywords <span class="muted">(one per line — one pin per keyword)</span></label>
                        <textarea id="aiKeywords" placeholder="e.g.&#10;cozy living room ideas&#10;small kitchen storage hacks"></textarea>
                    </div>
                    <div class="form-row">
                        <label>Links <span class="muted">(optional — one per line; their page titles are fetched and used as keywords)</span></label>
                        <textarea id="aiLinks" placeholder="https://example.com/article-1&#10;https://example.com/article-2"></textarea>
                    </div>
                    <div class="form-row" style="margin-bottom:10px;">
                        <label>Description Style</label>
                        <select id="aiTagStyle">
                            <option value="without_tags">Without hashtags</option>
                            <option value="with_tags">With hashtags</option>
                        </select>
                    </div>
                    <div class="form-row" style="margin-bottom:10px;">
                        <label>Custom Instructions <span class="muted">(optional)</span></label>
                        <textarea id="aiCustomPrompt" placeholder="e.g. Write in a playful tone and mention it's budget-friendly"></textarea>
                    </div>
                    <button type="button" class="btn-primary" id="aiGenerateBtn" style="width:100%;">Generate &amp; Insert with AI</button>
                    <div id="aiProgress" class="ai-progress"></div>
                    <p class="muted" style="margin-bottom:0;">Generates a title, description, alt text and keywords for each pin, 10 at a time. Descriptions end with a call-to-action to your Global Link. Titles keep any number found in the keyword (e.g. "20 ...").</p>
                </div>
            </div>
        </div>

        <!-- 4. GLOBAL DEFAULTS -->
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion="globalDefaults">
                <span class="step-num">4</span> Global Defaults <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body" id="acc-globalDefaults">
                <p class="muted" style="margin-top:0;">Used whenever a row is left blank.</p>
                <div class="two-col">
                    <div class="form-row">
                        <label>Global Title</label>
                        <input type="text" id="bsGlobalTitle" maxlength="100">
                    </div>
                    <div class="form-row">
                        <label>Global Link</label>
                        <input type="url" id="bsGlobalLink" placeholder="https://...">
                    </div>
                </div>
                <div class="form-row">
                    <label>Global Description</label>
                    <textarea id="bsGlobalDescription" maxlength="500"></textarea>
                </div>
            </div>
        </div>

        <!-- 5. TAG PRODUCT -->
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion="tagProduct">
                <span class="step-num">5</span> Tag Product <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body" id="acc-tagProduct">
                <p class="muted" style="margin-top:0;">Bulk-apply product links to every pin at once, or use each pin's own <strong>Tag Product</strong> button on the right for one-off tagging.</p>
                <div class="form-row">
                    <label>Product Links <span class="muted">(one per line)</span></label>
                    <textarea id="productLinks" placeholder="https://shop.example.com/product-1&#10;https://shop.example.com/product-2"></textarea>
                </div>
                <label class="checkbox-row">
                    <input type="checkbox" id="productApplyToAll">
                    Use only the first link and apply it to every pin
                </label>
                <button type="button" class="btn-secondary btn-small" id="applyProductTagsBtn" style="margin-top:8px;">Apply Product Tags to Pins</button>
                <p class="muted">A pin's product link becomes its outbound destination link when no other link is set.</p>
            </div>
        </div>

        <!-- 6. CREATE PIN IMAGE WITH AI -->
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion="createPinImage">
                <span class="step-num">6</span> Create Pin Image with AI <span class="accordion-caret">▾</span>
            </button>
            <div class="accordion-body" id="acc-createPinImage">
                <div class="bs-ai-box">
                    <span class="ai-badge">🎨 AI Pin Image Generator</span>
                    <div class="credits-note">Your Image AI balance: <strong id="creditsBalance"><?= number_format($myImageCredits, 1) ?></strong> credits <?php if ($myImageCredits <= 0): ?><span class="credits-low-warn">— low! <a href="upgrade">Upgrade your plan</a></span><?php endif; ?></div>

                    <div class="form-row">
                        <label>Titles or Links <span class="muted">(one per line — a link's page title is fetched automatically, and long titles are shortened by AI)</span></label>
                        <textarea id="piInputs" placeholder="e.g.&#10;10 Cozy Living Room Ideas For Fall&#10;https://example.com/blog/small-kitchen-storage"></textarea>
                    </div>

                    <div class="two-col">
                        <div class="form-row">
                            <label>Size</label>
                            <select id="piSize">
                                <option value="2:3">1000 × 1500 px (2:3)</option>
                                <option value="9:16">1080 × 1920 px (9:16)</option>
                                <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                                <option value="1:1">1000 × 1000 px (1:1)</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Website <span class="muted">(optional, shown at the bottom)</span></label>
                            <input type="text" id="piWebsite" placeholder="example.com">
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="form-row">
                            <label>CTA</label>
                            <select id="piCtaMode">
                                <option value="auto">Auto (added automatically based on the title)</option>
                                <option value="custom">Custom text</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div class="form-row" id="piCtaCustomWrap" style="display:none;">
                            <label>CTA Text</label>
                            <input type="text" id="piCtaText" list="piCtaExamples" placeholder="e.g. Explore All Ideas">
                            <datalist id="piCtaExamples">
                                <option value="Explore All Ideas"><option value="Visit Site"><option value="Explore Now">
                                <option value="See How"><option value="Get the Recipe"><option value="Learn More">
                            </datalist>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Select Category <span class="muted" style="font-weight:400;">(the AI image matches this niche)</span></label>
                        <input type="hidden" id="piImageCategory" data-catpick>
                    </div>

                    <div class="form-row">
                        <label>Pin Templates &amp; Styles</label>
                        <input type="hidden" id="piImageStyle" value="auto" data-tplpick>
                    </div>

                    <div class="form-row">
                        <label class="checkbox-row"><input type="checkbox" id="piPaletteEnabled"> Use my brand color palette <span class="muted">(optional — leave off for the AI's own automatic colors)</span></label>
                    </div>
                    <div id="piPaletteWrap" style="display:none; margin-top:8px;">
                        <div class="form-row">
                            <label>Number of Colors</label>
                            <select id="piPaletteCount">
                                <option value="3" selected>3 Colors</option>
                                <option value="4">4 Colors</option>
                            </select>
                        </div>
                        <div class="two-col">
                            <div class="form-row"><label>Color 1</label><input type="color" id="piColor1" value="#E91E63"></div>
                            <div class="form-row"><label>Color 2</label><input type="color" id="piColor2" value="#FFEB3B"></div>
                        </div>
                        <div class="two-col">
                            <div class="form-row"><label>Color 3</label><input type="color" id="piColor3" value="#212121"></div>
                            <div class="form-row" id="piColor4Wrap" style="display:none;"><label>Color 4</label><input type="color" id="piColor4" value="#FFFFFF"></div>
                        </div>
                        <p class="muted">These colors are used for the pin's headline text (cycled line by line).</p>

                        <div class="two-col">
                            <div class="form-row"><label>Website Text Color</label><input type="color" id="piWebsiteTextColor" value="#FFFFFF"></div>
                            <div class="form-row"><label>Website Background Color</label><input type="color" id="piWebsiteBgColor" value="#E91E63"></div>
                        </div>
                        <div class="two-col">
                            <div class="form-row"><label>CTA Text Color</label><input type="color" id="piCtaTextColor" value="#FFFFFF"></div>
                            <div class="form-row"><label>CTA Background Color</label><input type="color" id="piCtaBgColor" value="#E91E63"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Custom Prompt <span class="muted">(optional — leave blank for an automatically attractive design)</span></label>
                        <textarea id="piCustomPrompt" placeholder="Describe the exact background/style you want, or leave blank"></textarea>
                    </div>

                    <input type="hidden" id="piImageType" value="auto"><input type="hidden" id="piCollageCount" value="4">
<p class="muted" style="margin:-4px 0 12px; font-size:12.5px;">Single photo or collage is chosen automatically from the template: single-photo templates get one image, collage templates get several different images (credits are per photo — the photo count is shown on each template).</p>

                    <div class="form-row">
                        <label>Quality</label>
                        <select id="piQuality">
                            <option value="budget">Budget — <?= number_format($qualityCosts['image_quality_low'], 2) ?> credits/photo</option>
                            <option value="high">High Quality — <?= number_format($qualityCosts['image_quality_medium'], 2) ?> credits/photo</option>
                            <option value="ultra">Ultra Quality — <?= number_format($qualityCosts['image_quality_high'], 2) ?> credits/photo</option>
                        </select>
                    </div>

                    <button type="button" class="btn-primary" id="piGenerateBtn" style="width:100%;">🎨 Generate Pin Images</button>
                    <p class="muted" style="margin-bottom:0;">Images are added to the Pins list on the right as each one finishes. A failed image (after 3 tries) still adds an empty row so you don't lose your place — check the status list below.</p>

                    <div id="piStatusList" class="pi-status-list"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: pins, shown one by one -->
    <div class="bs-panel bs-pins-panel">
        <div class="bs-pins-header">
            <h2>📌 Pins <span class="badge badge-pending" id="pinCountBadge">0</span></h2>
            <div class="actions">
                <input type="file" id="bulkImageInput" accept="image/*" multiple style="display:none;">
                <button type="button" class="btn-primary" id="uploadBulkBtn">⬆ Upload Images</button>
                <button type="button" class="btn-secondary" id="openCsvModalBtn"><span style="margin-right:4px;">🗒</span>Upload CSV</button>
                <button type="button" class="btn-secondary" id="downloadCsvBtn"><span style="margin-right:4px;">⬇</span>Download CSV</button>
                <button type="button" class="btn-secondary" id="addRowBtn">+ Add Row</button>
            </div>
        </div>

        <div class="bs-rows-scroll">
            <div id="rowsContainer"></div>
            <div class="empty-state" id="emptyState">
                <span class="icon">🖼️</span>
                No pins yet — upload images, upload a CSV, or add a row to get started.
            </div>
        </div>

        <div class="bs-pins-footer">
            <span class="muted" id="summaryLine">0 pins</span>
            <button type="button" class="btn-primary" id="scheduleAllBtn">🕐 Schedule All Pins</button>
        </div>
    </div>

</div>

<!-- CSV UPLOAD MODAL -->
<div class="modal-overlay" id="csvModalOverlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h2>🗒️ Upload CSV File</h2>
            <button type="button" class="modal-close" id="csvModalClose">✕</button>
        </div>
        <p class="muted">Upload a CSV file containing pin details. Only an image link column is required, other fields are optional. For instance, if you don't pass in the title — we'll generate it for you.</p>
        <div style="text-align:right; margin-bottom:14px;">
            <a href="../assets/downloads/bulk-pins-sample.csv" class="btn-secondary btn-small" download>⬇ Sample CSV</a>
        </div>
        <div class="csv-dropzone" id="csvDropzone">
            <input type="file" id="csvFileInput" accept=".csv" style="display:none;">
            <div class="csv-dropzone-icon">🗒️</div>
            <div>Drag and drop CSV or click to select</div>
            <div class="csv-fields-box">
                <div><strong style="color:var(--red);">imageUrl</strong>, title, description, outboundURL, altText, baseTitle, baseDescription, boardName, scheduleDate</div>
                <ul>
                    <li>Only the image link column is required — column names are matched flexibly (e.g. "Image URL", "Image Link", "img" all work)</li>
                    <li>baseTitle/baseDescription are hints for AI generation</li>
                    <li>If title/description are empty, they'll be AI-generated</li>
                </ul>
            </div>
        </div>
        <div id="csvImportSummary" class="muted" style="margin-top:10px;"></div>
        <div id="csvImportStatus" class="ai-progress" style="margin-top:6px; max-height:220px; overflow-y:auto;"></div>
    </div>
</div>

<!-- PER-PIN PRODUCT TAG MODAL -->
<div class="modal-overlay" id="productModalOverlay" style="display:none;">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h2>🏷️ Product Tags</h2>
            <button type="button" class="modal-close" id="productModalClose">✕</button>
        </div>
        <div class="board-mode-toggle">
            <button type="button" class="board-mode-btn" data-product-tab="search">Search Pins</button>
            <button type="button" class="board-mode-btn active" data-product-tab="link">Use a Link</button>
        </div>
        <div id="productSearchTab" style="display:none;">
            <div class="form-row"><input type="text" placeholder="Search your pins..." disabled></div>
            <p class="muted">Searching your existing pins for products isn't available yet — use "Use a Link" to tag a product by URL instead.</p>
        </div>
        <div id="productLinkTab">
            <div class="form-row"><input type="url" id="productLinkInput" placeholder="Add a product link"></div>
            <p class="muted">Enter a link to a product page on a retailer site. It's tagged as this pin's outbound link.</p>
            <button type="button" class="btn-primary btn-small" id="productLinkSaveBtn">Add</button>
            <button type="button" class="btn-secondary btn-small" id="productLinkRemoveBtn">Remove Tag</button>
        </div>
    </div>
</div>

<script>
const BOARDS_BY_ACCOUNT = <?= json_encode($boardsByAccount, JSON_HEX_TAG) ?>;
const APP_BASE_URL = <?= json_encode(rtrim(APP_URL, '/'), JSON_HEX_TAG) ?>;
const UPLOAD_URL = 'ajax-bulk-upload';
const FETCH_IMAGE_URL = 'ajax-bulk-fetch-image';
const AI_URL = 'ajax-bulk-ai';
const BOARD_AI_URL = 'ajax-board-ai';
const SAVE_URL = 'ajax-bulk-save';
const DRAFT_SAVE_URL = 'ajax-bulk-draft-save';
const INITIAL_DRAFT = <?= $initialDraft ? json_encode($initialDraft, JSON_HEX_TAG) : 'null' ?>;

const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ---------------- Accordion ---------------- */
document.querySelectorAll('.accordion-header').forEach(h => {
    h.addEventListener('click', () => {
        const body = document.getElementById('acc-' + h.dataset.accordion);
        const willOpen = !body.classList.contains('open');
        body.classList.toggle('open', willOpen);
        h.classList.toggle('open', willOpen);
    });
});

let rows = []; // { id, image_path, filename, uploading, title, description, link, alt, keywords, product_link, board_choice, publish_at }
let rowSeq = 0;
let draftBatchId = INITIAL_DRAFT ? INITIAL_DRAFT.batch_id : null;
let tagsList = [];

const accountSelect = document.getElementById('bsAccount');
const boardModeToggle = document.getElementById('boardModeToggle');
const selectBoardBox = document.getElementById('bsSelectBoardBox');
const createBoardBox = document.getElementById('bsCreateBoardBox');
const boardHidden = document.getElementById('bsBoard');
const boardPicker = document.getElementById('bsBoardPicker');
const boardSearch = document.getElementById('bsBoardSearch');
const newBoardName = document.getElementById('bsNewBoardName');
const newBoardDescription = document.getElementById('bsNewBoardDescription');
let boardMode = 'select'; // 'select' | 'create'
let createMode = 'manual'; // 'manual' | 'ai'
let boardView = 'grid';

/* ---------------- Account & Board ---------------- */
function currentBoards() {
    return BOARDS_BY_ACCOUNT[accountSelect.value] || [];
}
function renderBoardPicker() {
    const q = (boardSearch.value || '').toLowerCase();
    const list = currentBoards().filter(b => b.name.toLowerCase().includes(q));
    boardPicker.className = 'board-picker board-picker-' + boardView;
    if (!list.length) {
        boardPicker.innerHTML = '<div class="muted" style="padding:14px;">No boards found.</div>';
        return;
    }
    boardPicker.innerHTML = list.map(b => `
        <div class="board-card ${boardHidden.value === b.value ? 'selected' : ''}" data-board-value="${escapeHtml(b.value)}">
            <div class="board-card-icon">📌</div>
            <div class="board-card-info">
                <div class="board-card-name">${escapeHtml(b.name)}</div>
                ${b.status === 'pending_creation' ? '<span class="muted">Will be created</span>' : (b.status === 'create_failed' ? '<span class="muted">Creation failed</span>' : '')}
            </div>
        </div>
    `).join('');
    boardPicker.querySelectorAll('[data-board-value]').forEach(el => {
        el.addEventListener('click', () => {
            boardHidden.value = el.dataset.boardValue;
            renderBoardPicker();
        });
    });
}
boardSearch.addEventListener('input', renderBoardPicker);
document.querySelectorAll('[data-board-view]').forEach(btn => {
    btn.addEventListener('click', () => {
        boardView = btn.dataset.boardView;
        document.querySelectorAll('[data-board-view]').forEach(b => b.classList.toggle('active', b === btn));
        renderBoardPicker();
    });
});

function setBoardMode(mode) {
    boardMode = mode;
    document.querySelectorAll('[data-board-mode]').forEach(b => b.classList.toggle('active', b.dataset.boardMode === mode));
    selectBoardBox.style.display = mode === 'select' ? 'block' : 'none';
    createBoardBox.style.display = mode === 'create' ? 'block' : 'none';
    if (mode === 'create') {
        boardHidden.value = '__new__';
    } else if (boardHidden.value === '__new__') {
        boardHidden.value = '';
    }
}
document.querySelectorAll('[data-board-mode]').forEach(btn => {
    btn.addEventListener('click', () => setBoardMode(btn.dataset.boardMode));
});

/* ---------------- Insert board into all pins ---------------- */
document.getElementById('bsInsertSelectedBoardBtn').addEventListener('click', () => {
    if (!boardHidden.value || boardHidden.value === '__new__') { showAlert('Please select a board first.', 'error'); return; }
    if (!rows.length) { showAlert('No pins to apply the board to yet.', 'error'); return; }
    rows.forEach(r => { r.board_choice = boardHidden.value; r.new_board_name = ''; r.new_board_description = ''; });
    renderRows();
    showAlert('Board applied to all ' + rows.length + ' pin(s).', 'success');
});
document.getElementById('bsInsertManualBoardBtn').addEventListener('click', () => {
    if (!newBoardName.value.trim()) { showAlert('Please enter a board name first.', 'error'); return; }
    if (!rows.length) { showAlert('No pins to apply the board to yet.', 'error'); return; }
    const mName = newBoardName.value.trim(), mDesc = newBoardDescription.value.trim();
    const mExisting = currentBoards().find(b => boardKey(b.name) === boardKey(mName));
    rows.forEach(r => {
        if (mExisting) { r.board_choice = mExisting.value; r.new_board_name = ''; r.new_board_description = ''; }
        else { r.board_choice = '__pin_new__'; r.new_board_name = mName; r.new_board_description = mDesc; }
    });
    renderRows();
    showAlert('"' + newBoardName.value.trim() + '" will be applied to all ' + rows.length + ' pin(s).', 'success');
});

function setCreateMode(mode) {
    createMode = mode;
    document.querySelectorAll('[data-create-mode]').forEach(b => b.classList.toggle('active', b.dataset.createMode === mode));
    document.getElementById('bsCreateManual').style.display = mode === 'manual' ? 'block' : 'none';
    document.getElementById('bsCreateAi').style.display = mode === 'ai' ? 'block' : 'none';
}
document.querySelectorAll('[data-create-mode]').forEach(btn => {
    btn.addEventListener('click', () => setCreateMode(btn.dataset.createMode));
});
document.getElementById('bsBoardAiBtn').addEventListener('click', async () => {
    const keyword = document.getElementById('bsBoardAiKeyword').value.trim();
    if (!keyword) { showAlert('Please enter a board keyword/topic first.', 'error'); return; }
    const btn = document.getElementById('bsBoardAiBtn');
    btn.disabled = true; btn.textContent = 'Generating…';
    const fd = new FormData();
    fd.append('keyword', keyword);
    try {
        const res = await fetch(BOARD_AI_URL, { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            newBoardName.value = result.name;
            newBoardDescription.value = result.description;
            const dup = currentBoards().find(b => boardKey(b.name) === boardKey(result.name));
            document.getElementById('bsBoardAiResult').textContent = dup
                ? 'A board named "' + dup.name + '" already exists on this account — pins will go to that board (no duplicate is created).'
                : 'Suggestion inserted — feel free to edit it below.';
            setCreateMode('manual');
        } else {
            showAlert('AI board suggestion failed: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error contacting the AI writer.', 'error');
    }
    btn.disabled = false; btn.textContent = '✨ Generate Board Name & Description';
});

// Same normalization as board_name_key() in PHP: case/punctuation-insensitive board name comparison.
function boardKey(name) {
    return String(name || '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
}

/* ---------------- Create with AI: Single vs Multiple boards ---------------- */
let boardAiMode = 'single';
document.querySelectorAll('[data-board-ai-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        boardAiMode = btn.dataset.boardAiMode;
        document.querySelectorAll('[data-board-ai-mode]').forEach(b => b.classList.toggle('active', b === btn));
        document.getElementById('bsBoardAiSingle').style.display = boardAiMode === 'single' ? 'block' : 'none';
        document.getElementById('bsBoardAiMultiple').style.display = boardAiMode === 'multiple' ? 'block' : 'none';
    });
});

// "Multiple Boards": for each pin already in the list, ask AI whether its title fits one of
// this account's existing boards or needs a brand-new one — 10 pins per call/notification, same
// chunking pattern as the pin-content generator, so a big batch can't overrun the AI response
// and the user sees live progress instead of a long silent wait.
document.getElementById('bsBoardAiMultiBtn').addEventListener('click', async () => {
    if (!rows.length) { showAlert('Add or generate some pins first.', 'error'); return; }
    if (!accountSelect.value) { showAlert('Please select a Pinterest account first.', 'error'); return; }

    const statusEl = document.getElementById('bsBoardAiMultiStatus');
    statusEl.innerHTML = '';
    const btn = document.getElementById('bsBoardAiMultiBtn');
    btn.disabled = true; btn.textContent = 'Assigning…';

    const existingBoardNames = currentBoards().map(b => b.name);
    // New boards drafted in earlier chunks, by normalized name -> {name, description}. They are sent
    // along as "existing" boards so later pins on the same topic join them instead of getting a
    // slightly different duplicate name.
    const draftedBoards = {};
    const targets = rows.filter(r => r.title || r.description); // needs something to judge intent from

    for (let i = 0; i < targets.length; i += AI_CHUNK_SIZE) {
        const batch = targets.slice(i, i + AI_CHUNK_SIZE);
        const from = i + 1, to = Math.min(i + AI_CHUNK_SIZE, targets.length);
        progressLine('bsBoardAiMultiStatus', `Assigning boards for pins ${from}–${to} of ${targets.length}…`, 'pending');
        try {
            const fd = new FormData();
            fd.append('mode', 'batch_assign');
            fd.append('titles', JSON.stringify(batch.map(r => r.title || r.description)));
            fd.append('existing_boards', JSON.stringify(existingBoardNames.concat(Object.values(draftedBoards).map(d => d.name))));
            const res = await fetch(BOARD_AI_URL, { method: 'POST', body: fd });
            const result = await res.json();
            if (result.ok) {
                batch.forEach((row, j) => {
                    const item = result.items[j];
                    if (!item) return;
                    if (item.match) {
                        const board = currentBoards().find(b => boardKey(b.name) === boardKey(item.match));
                        if (board) {
                            row.board_choice = board.value;
                            row.new_board_name = ''; row.new_board_description = '';
                            return;
                        }
                        const drafted = draftedBoards[boardKey(item.match)];
                        if (drafted) {
                            row.board_choice = '__pin_new__';
                            row.new_board_name = drafted.name;
                            row.new_board_description = drafted.description;
                            return;
                        }
                    }
                    if (item.new_name) {
                        const key = boardKey(item.new_name);
                        const existing = currentBoards().find(b => boardKey(b.name) === key);
                        if (existing) {
                            row.board_choice = existing.value;
                            row.new_board_name = ''; row.new_board_description = '';
                            return;
                        }
                        if (!draftedBoards[key]) draftedBoards[key] = { name: item.new_name, description: item.new_description || '' };
                        row.board_choice = '__pin_new__';
                        row.new_board_name = draftedBoards[key].name;
                        row.new_board_description = draftedBoards[key].description;
                    }
                });
                progressLine('bsBoardAiMultiStatus', `✓ Pins ${from}–${to}: boards selected/created.`, 'success');
            } else {
                progressLine('bsBoardAiMultiStatus', `✗ Pins ${from}–${to}: ${result.error || 'failed'}.`, 'error');
            }
        } catch (e) {
            progressLine('bsBoardAiMultiStatus', `✗ Pins ${from}–${to}: network error.`, 'error');
        }
        renderRows();
    }

    btn.disabled = false; btn.textContent = '🪄 Assign Boards With AI';
    const newCount = Object.keys(draftedBoards).length;
    progressLine('bsBoardAiMultiStatus', `Done — processed ${targets.length} pin(s)` + (newCount ? `, ${newCount} new board(s) will be created.` : '.'), 'success');
});

function populateBoards() {
    const accId = accountSelect.value;
    if (!accId) {
        boardModeToggle.style.display = 'none';
        selectBoardBox.style.display = 'none';
        createBoardBox.style.display = 'none';
        boardHidden.value = '';
        renderRows(); // refresh each row's per-pin board dropdown (now empty)
        return;
    }
    boardModeToggle.style.display = 'flex';
    setBoardMode('select');
    boardHidden.value = '';
    renderBoardPicker();
    renderRows(); // refresh each row's per-pin board dropdown for the new account's boards
}
accountSelect.addEventListener('change', populateBoards);

/* ---------------- Scheduling mode ---------------- */
document.querySelectorAll('[data-sched-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-sched-mode]').forEach(b => b.classList.toggle('active', b === btn));
        const mode = btn.dataset.schedMode;
        document.getElementById('bsIntervalMode').style.display = mode === 'interval' ? 'block' : 'none';
        document.getElementById('bsPerDayMode').style.display = mode === 'perday' ? 'block' : 'none';
    });
});
document.getElementById('bsPinsPerDay').addEventListener('change', function () {
    const perDay = parseInt(this.value, 10) || 1;
    document.getElementById('bsGapHours').value = Math.max(0.5, Math.round((24 / perDay) * 10) / 10);
});

/* ---------------- Global tags (batch default) ---------------- */
function renderTagPills() {
    const box = document.getElementById('tagPills');
    box.innerHTML = tagsList.map((t, i) => `<span class="tag-pill">${escapeHtml(t)}<span class="tag-pill-x" data-tag-idx="${i}">×</span></span>`).join('');
    box.querySelectorAll('[data-tag-idx]').forEach(el => {
        el.addEventListener('click', () => { tagsList.splice(parseInt(el.dataset.tagIdx, 10), 1); renderTagPills(); });
    });
}
document.getElementById('tagInputField').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = e.target.value.trim().replace(/,$/, '');
        if (val && !tagsList.includes(val)) { tagsList.push(val); renderTagPills(); }
        e.target.value = '';
    }
});

/* ---------------- Row rendering ---------------- */
function fmtDateForInput(d) {
    const pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}
function fmtDateDisplay(iso) {
    if (!iso) return 'Not set';
    const d = new Date(iso);
    if (isNaN(d)) return 'Not set';
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function addRow(partial) {
    rowSeq++;
    rows.push(Object.assign({
        id: rowSeq, image_path: null, filename: '', uploading: false,
        title: '', description: '', link: '', alt: '', keywords: '', product_link: '', board_choice: '', publish_at: '',
        // Set when the "Multiple Boards" AI assignment couldn't match this pin to an existing
        // board and wrote a brand-new one just for this pin (board_choice becomes '__pin_new__').
        new_board_name: '', new_board_description: '',
    }, partial || {}));
    renderRows();
    return rows[rows.length - 1];
}
function removeRow(id) {
    rows = rows.filter(r => r.id !== id);
    renderRows();
}
function getRow(id) { return rows.find(r => r.id === id); }

/* ---------------- Per-pin board popup: search, pick, or create (manual / AI) ---------------- */
function rowBoardLabel(r) {
    if (r.board_choice === '__pin_new__') return '✨ New: ' + (r.new_board_name || 'Untitled board');
    if (!r.board_choice) return 'Use batch default board';
    const b = currentBoards().find(x => x.value === r.board_choice);
    return b ? '📌 ' + b.name : 'Use batch default board';
}

let rbmRowId = null;
function ensureRowBoardModal() {
    if (document.getElementById('rbmOverlay')) return;
    const css = document.createElement('style');
    css.textContent = `
.row-board-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 6px; border: none; background: transparent; padding: 0; font: inherit; font-size: 12.5px; color: var(--dark, #111827); cursor: pointer; text-align: left; }
.row-board-btn-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rbm-overlay { position: fixed; inset: 0; z-index: 100000; background: rgba(17,24,39,.55); display: none; align-items: flex-start; justify-content: center; padding: 5vh 12px; }
.rbm-overlay.open { display: flex; }
.rbm-modal { width: 100%; max-width: 560px; max-height: 90vh; display: flex; flex-direction: column; background: #fff; color: #111827; border-radius: 16px; box-shadow: 0 24px 60px rgba(0,0,0,.25); overflow: hidden; font-size: 14px; }
.rbm-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 18px 6px; }
.rbm-head h3 { margin: 0; font-size: 18px; }
.rbm-pin { padding: 0 18px 10px; color: #6b7280; font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rbm-close { border: none; background: none; font-size: 20px; cursor: pointer; color: #6b7280; }
.rbm-search { margin: 0 18px 10px; padding: 10px 14px; border: 1px solid #d9dbe0; border-radius: 10px; font: inherit; }
.rbm-list { overflow-y: auto; padding: 0 18px; flex: 1; min-height: 120px; }
.rbm-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; margin-bottom: 6px; border: 1px solid #eceef2; border-radius: 10px; background: #fff; font: inherit; text-align: left; cursor: pointer; }
.rbm-item:hover { border-color: #f3b3bf; }
.rbm-item.sel { border-color: #e60023; background: #fff5f7; font-weight: 600; }
.rbm-item small { color: #6b7280; font-weight: 400; }
.rbm-create { border-top: 1px solid #eef0f3; padding: 12px 18px 16px; }
.rbm-tabs { display: flex; gap: 6px; margin-bottom: 10px; }
.rbm-tabs button { border: 1px solid #d9dbe0; background: #fff; border-radius: 999px; padding: 6px 12px; font: inherit; font-size: 13px; cursor: pointer; }
.rbm-tabs button.on { background: #111827; color: #fff; border-color: #111827; }
.rbm-create input, .rbm-create textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #d9dbe0; border-radius: 10px; font: inherit; margin-bottom: 8px; }
.rbm-create textarea { min-height: 64px; resize: vertical; }
.rbm-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.rbm-note { font-size: 12.5px; color: #6b7280; margin: 4px 0 8px; }
[data-theme="dark"] .rbm-modal, [data-theme="dark"] .rbm-item, [data-theme="dark"] .rbm-search, [data-theme="dark"] .rbm-create input, [data-theme="dark"] .rbm-create textarea, [data-theme="dark"] .rbm-tabs button { background: #1e2025; border-color: #33353c; color: #e5e7eb; }
[data-theme="dark"] .rbm-item.sel { background: #2a1a1e; border-color: #e60023; }`;
    document.head.appendChild(css);
    const ov = document.createElement('div');
    ov.id = 'rbmOverlay';
    ov.className = 'rbm-overlay';
    ov.innerHTML = `
    <div class="rbm-modal" role="dialog" aria-modal="true" aria-label="Board for this pin">
        <div class="rbm-head"><h3>Board for this pin</h3><button type="button" class="rbm-close" aria-label="Close">✕</button></div>
        <div class="rbm-pin" id="rbmPinTitle"></div>
        <input type="search" class="rbm-search" id="rbmSearch" placeholder="Search boards…">
        <div class="rbm-list" id="rbmList"></div>
        <div class="rbm-create">
            <div class="rbm-tabs"><strong style="align-self:center; margin-right:6px;">+ Create new board</strong>
                <button type="button" data-rbm-tab="manual" class="on">Manual</button>
                <button type="button" data-rbm-tab="ai">✨ With AI</button>
            </div>
            <div id="rbmAiRow" style="display:none;">
                <p class="rbm-note">AI writes a board name (main keyword only, no numbers) and a description from this pin's title. You can edit both before using it.</p>
                <button type="button" class="btn-secondary" id="rbmAiBtn">✨ Write Name &amp; Description</button>
                <div style="height:8px;"></div>
            </div>
            <input type="text" id="rbmNewName" maxlength="50" placeholder="Board name">
            <textarea id="rbmNewDesc" maxlength="500" placeholder="Board description (optional)"></textarea>
            <div class="rbm-actions"><button type="button" class="btn-primary" id="rbmUseNew">Use This New Board</button><span class="rbm-note" id="rbmNewNote"></span></div>
        </div>
    </div>`;
    document.body.appendChild(ov);
    ov.addEventListener('click', e => { if (e.target === ov) closeRowBoardModal(); });
    ov.querySelector('.rbm-close').addEventListener('click', closeRowBoardModal);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && ov.classList.contains('open')) closeRowBoardModal(); });
    document.getElementById('rbmSearch').addEventListener('input', renderRowBoardList);
    ov.querySelectorAll('[data-rbm-tab]').forEach(b => b.addEventListener('click', () => {
        ov.querySelectorAll('[data-rbm-tab]').forEach(x => x.classList.toggle('on', x === b));
        document.getElementById('rbmAiRow').style.display = b.dataset.rbmTab === 'ai' ? 'block' : 'none';
    }));
    document.getElementById('rbmAiBtn').addEventListener('click', async () => {
        const row = getRow(rbmRowId);
        const keyword = row ? (row.title || row.description || '').trim() : '';
        if (!keyword) { document.getElementById('rbmNewNote').textContent = 'This pin has no title yet — add one first.'; return; }
        const btn = document.getElementById('rbmAiBtn');
        btn.disabled = true; btn.textContent = 'Writing…';
        const fd = new FormData();
        fd.append('keyword', keyword);
        try {
            const res = await fetch(BOARD_AI_URL, { method: 'POST', body: fd });
            const result = await res.json();
            if (result.ok) {
                document.getElementById('rbmNewName').value = result.name || '';
                document.getElementById('rbmNewDesc').value = result.description || '';
                document.getElementById('rbmNewNote').textContent = 'Written by AI — edit if you like, then click “Use This New Board”.';
            } else {
                document.getElementById('rbmNewNote').textContent = 'AI failed: ' + (result.error || 'please try again');
            }
        } catch (e) {
            document.getElementById('rbmNewNote').textContent = 'Network error — please try again.';
        }
        btn.disabled = false; btn.textContent = '✨ Write Name & Description';
    });
    document.getElementById('rbmUseNew').addEventListener('click', () => {
        const row = getRow(rbmRowId);
        const name = document.getElementById('rbmNewName').value.trim();
        if (!row) return;
        if (!name) { document.getElementById('rbmNewNote').textContent = 'Please enter a board name.'; return; }
        // Board names are unique on Pinterest: reuse an existing board with the same name.
        const existing = currentBoards().find(b => boardKey(b.name) === boardKey(name));
        if (existing) { row.board_choice = existing.value; row.new_board_name = ''; row.new_board_description = ''; }
        else { row.board_choice = '__pin_new__'; row.new_board_name = name; row.new_board_description = document.getElementById('rbmNewDesc').value.trim(); }
        closeRowBoardModal(); renderRows(); scheduleAutosave();
    });
}

function renderRowBoardList() {
    const row = getRow(rbmRowId);
    if (!row) return;
    const q = (document.getElementById('rbmSearch').value || '').toLowerCase();
    const items = [{ value: '', name: 'Use batch default board', note: 'the board chosen above for the whole batch' }];
    currentBoards().forEach(b => items.push({ value: b.value, name: b.name }));
    // New boards already drafted on other pins in this batch, so pins on the same topic can share one.
    const drafted = {};
    rows.forEach(x => { if (x.board_choice === '__pin_new__' && x.new_board_name && !drafted[boardKey(x.new_board_name)]) drafted[boardKey(x.new_board_name)] = x; });
    Object.values(drafted).forEach(x => items.push({ value: '__pin_new__', name: x.new_board_name, desc: x.new_board_description, note: 'new — will be created' }));
    const list = items.filter(it => !q || it.name.toLowerCase().includes(q));
    const isSel = it => it.value === '__pin_new__' ? (row.board_choice === '__pin_new__' && boardKey(row.new_board_name) === boardKey(it.name)) : row.board_choice === it.value;
    document.getElementById('rbmList').innerHTML = list.length ? list.map((it, i) => `
        <button type="button" class="rbm-item ${isSel(it) ? 'sel' : ''}" data-i="${items.indexOf(it)}">
            <span>${it.value === '' ? '↩' : (it.value === '__pin_new__' ? '✨' : '📌')}</span>
            <span>${escapeHtml(it.name)}${it.note ? ` <small>— ${escapeHtml(it.note)}</small>` : ''}</span>
        </button>`).join('') : '<p class="muted" style="padding:12px 0;">No boards match — create one below.</p>';
    document.querySelectorAll('#rbmList .rbm-item').forEach(el => el.addEventListener('click', () => {
        const it = items[parseInt(el.dataset.i, 10)];
        if (it.value === '__pin_new__') { row.board_choice = '__pin_new__'; row.new_board_name = it.name; row.new_board_description = it.desc || ''; }
        else { row.board_choice = it.value; row.new_board_name = ''; row.new_board_description = ''; }
        closeRowBoardModal(); renderRows(); scheduleAutosave();
    }));
}

function openRowBoardModal(rowId) {
    ensureRowBoardModal();
    rbmRowId = rowId;
    const row = getRow(rowId);
    document.getElementById('rbmPinTitle').textContent = row && row.title ? 'Pin: ' + row.title : '';
    document.getElementById('rbmSearch').value = '';
    document.getElementById('rbmNewName').value = row && row.board_choice === '__pin_new__' ? row.new_board_name : '';
    document.getElementById('rbmNewDesc').value = row && row.board_choice === '__pin_new__' ? (row.new_board_description || '') : '';
    document.getElementById('rbmNewNote').textContent = '';
    renderRowBoardList();
    document.getElementById('rbmOverlay').classList.add('open');
    setTimeout(() => document.getElementById('rbmSearch').focus(), 30);
}
function closeRowBoardModal() {
    const ov = document.getElementById('rbmOverlay');
    if (ov) ov.classList.remove('open');
    rbmRowId = null;
}

function renderRows() {
    const container = document.getElementById('rowsContainer');
    document.getElementById('emptyState').style.display = rows.length ? 'none' : 'block';
    document.getElementById('pinCountBadge').textContent = rows.length;
    const last = rows.length ? rows[rows.length - 1].publish_at : null;
    document.getElementById('summaryLine').textContent = rows.length + ' pins' + (last ? ' · Last pin: ' + fmtDateDisplay(last) : '');

    container.innerHTML = rows.map((r, idx) => {
        return `
        <div class="bulk-row" draggable="true" data-id="${r.id}">
            <div class="bulk-row-handle" title="Drag to reorder">☰</div>

            <div class="bulk-row-media">
                <div class="bulk-row-image" data-id="${r.id}" title="Click to upload/replace image">
                    <span class="bulk-row-index">#${idx + 1}</span>
                    ${r.uploading ? '<span class="muted">Uploading…</span>' : (r.image_path ? `<img src="../${r.image_path}">` : '<span class="muted">+ Image</span>')}
                </div>
                <div class="bulk-row-media-meta">
                    <input type="text" class="bulk-filename" value="${escapeHtml(r.filename)}" placeholder="filename.jpg" readonly>
                    <input type="text" data-field="alt" data-id="${r.id}" value="${escapeHtml(r.alt)}" placeholder="Alt text">
                </div>
            </div>

            <div class="bulk-row-fields">
                <input type="text" data-field="title" data-id="${r.id}" value="${escapeHtml(r.title)}" placeholder="Title (optional, uses global)">
                <textarea data-field="description" data-id="${r.id}" placeholder="Description (optional, uses global)">${escapeHtml(r.description)}</textarea>
                <input type="url" data-field="link" data-id="${r.id}" value="${escapeHtml(r.link)}" placeholder="Link (optional, uses global)">

                <div class="bulk-row-time-wrap">
                    <label class="bulk-row-keywords-pill" title="Keywords for this pin">🔑
                        <input type="text" data-field="keywords" data-id="${r.id}" value="${escapeHtml(r.keywords)}" placeholder="keywords">
                    </label>
                    <label class="bulk-row-time" title="Publish time">🕐
                        <input type="datetime-local" data-field="publish_at" data-id="${r.id}" value="${r.publish_at}">
                    </label>
                </div>

                <div class="row-tools">
                    <div class="row-tool-board">
                        <div class="row-tag-label">Board for this pin</div>
                        <button type="button" class="row-board-btn" data-boardrow-id="${r.id}" title="Choose or create a board for this pin">
                            <span class="row-board-btn-label">${escapeHtml(rowBoardLabel(r))}</span><span class="caret">▾</span>
                        </button>
                        ${r.board_choice === '__pin_new__' ? `
                        <div class="muted row-new-board-note" style="margin-top:4px; font-size:12px;">
                            <strong>${escapeHtml(r.new_board_name)}</strong>${r.new_board_description ? ' — ' + escapeHtml(r.new_board_description) : ''}
                        </div>` : ''}
                    </div>
                </div>

                <button type="button" class="row-product-btn ${r.product_link ? 'has-product' : ''}" data-product-id="${r.id}" title="${escapeHtml(r.product_link)}">
                    ${r.product_link ? '✓ Product Tagged' : 'Tag Product'} <span class="caret">▾</span>
                </button>
            </div>

            <button type="button" class="bulk-row-delete" data-id="${r.id}" title="Delete row">🗑</button>
        </div>
    `;
    }).join('');

    container.querySelectorAll('[data-field]').forEach(el => {
        el.addEventListener('input', () => {
            const row = getRow(parseInt(el.dataset.id, 10));
            if (row) { row[el.dataset.field] = el.value; if (el.dataset.field === 'publish_at') renderSummaryOnly(); }
        });
    });
    container.querySelectorAll('.bulk-row-delete').forEach(el => {
        el.addEventListener('click', () => removeRow(parseInt(el.dataset.id, 10)));
    });
    container.querySelectorAll('.bulk-row-image').forEach(el => {
        el.addEventListener('click', () => uploadImageForRow(parseInt(el.dataset.id, 10)));
    });
    container.querySelectorAll('.row-board-btn').forEach(el => {
        el.addEventListener('click', () => openRowBoardModal(parseInt(el.dataset.boardrowId, 10)));
    });
    container.querySelectorAll('.row-product-btn').forEach(el => {
        el.addEventListener('click', () => openProductModal(parseInt(el.dataset.productId, 10)));
    });
    attachDragHandlers(container);
    scheduleAutosave();
}
function renderSummaryOnly() {
    const last = rows.length ? rows[rows.length - 1].publish_at : null;
    document.getElementById('summaryLine').textContent = rows.length + ' pins' + (last ? ' · Last pin: ' + fmtDateDisplay(last) : '');
    scheduleAutosave();
}

/* ---------------- Per-pin product tag modal ---------------- */
const productModalOverlay = document.getElementById('productModalOverlay');
let currentProductRowId = null;
function openProductModal(rowId) {
    currentProductRowId = rowId;
    const row = getRow(rowId);
    document.getElementById('productLinkInput').value = row ? (row.product_link || '') : '';
    setProductTab('link');
    productModalOverlay.style.display = 'flex';
}
function setProductTab(tab) {
    document.querySelectorAll('[data-product-tab]').forEach(b => b.classList.toggle('active', b.dataset.productTab === tab));
    document.getElementById('productSearchTab').style.display = tab === 'search' ? 'block' : 'none';
    document.getElementById('productLinkTab').style.display = tab === 'link' ? 'block' : 'none';
}
document.querySelectorAll('[data-product-tab]').forEach(btn => {
    btn.addEventListener('click', () => setProductTab(btn.dataset.productTab));
});
document.getElementById('productModalClose').addEventListener('click', () => productModalOverlay.style.display = 'none');
productModalOverlay.addEventListener('click', (e) => { if (e.target === productModalOverlay) productModalOverlay.style.display = 'none'; });
document.getElementById('productLinkSaveBtn').addEventListener('click', () => {
    const row = getRow(currentProductRowId);
    if (!row) return;
    row.product_link = document.getElementById('productLinkInput').value.trim();
    productModalOverlay.style.display = 'none';
    renderRows();
});
document.getElementById('productLinkRemoveBtn').addEventListener('click', () => {
    const row = getRow(currentProductRowId);
    if (!row) return;
    row.product_link = '';
    productModalOverlay.style.display = 'none';
    renderRows();
});

/* ---------------- Drag to reorder ---------------- */
function attachDragHandlers(container) {
    let dragId = null;
    container.querySelectorAll('.bulk-row').forEach(el => {
        el.addEventListener('dragstart', () => { dragId = parseInt(el.dataset.id, 10); el.classList.add('dragging'); });
        el.addEventListener('dragend', () => el.classList.remove('dragging'));
        el.addEventListener('dragover', e => e.preventDefault());
        el.addEventListener('drop', () => {
            const dropId = parseInt(el.dataset.id, 10);
            if (dragId === null || dragId === dropId) return;
            const fromIdx = rows.findIndex(r => r.id === dragId);
            const toIdx = rows.findIndex(r => r.id === dropId);
            const [moved] = rows.splice(fromIdx, 1);
            rows.splice(toIdx, 0, moved);
            renderRows();
        });
    });
}

/* ---------------- Image upload ---------------- */
async function uploadImageFile(file) {
    const fd = new FormData();
    fd.append('image', file);
    try {
        const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
        return await res.json();
    } catch (e) {
        return { ok: false, error: 'Network error uploading image.' };
    }
}
async function fetchRemoteImage(url) {
    const fd = new FormData();
    fd.append('image_url', url);
    try {
        const res = await fetch(FETCH_IMAGE_URL, { method: 'POST', body: fd });
        return await res.json();
    } catch (e) {
        return { ok: false, error: 'Network error fetching image.' };
    }
}
document.getElementById('uploadBulkBtn').addEventListener('click', () => document.getElementById('bulkImageInput').click());
document.getElementById('bulkImageInput').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    for (const file of files) {
        const row = addRow({ uploading: true, filename: file.name });
        const result = await uploadImageFile(file);
        if (result.ok) {
            row.image_path = result.path;
            row.filename = result.filename;
        } else {
            showAlert('Image upload failed: ' + (result.error || 'Unknown error'), 'error');
        }
        row.uploading = false;
        renderRows();
    }
    applyScheduleIfSet();
});
function uploadImageForRow(rowId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const row = getRow(rowId);
        row.uploading = true;
        renderRows();
        const result = await uploadImageFile(file);
        if (result.ok) {
            row.image_path = result.path;
            row.filename = result.filename;
        } else {
            showAlert('Image upload failed: ' + (result.error || 'Unknown error'), 'error');
        }
        row.uploading = false;
        renderRows();
    };
    input.click();
}
document.getElementById('addRowBtn').addEventListener('click', () => { addRow({}); applyScheduleIfSet(); });

/* ---------------- Download CSV (Pinterest's own bulk-upload format) ---------------- */
function resolveBoardNameForCsv(choice, row) {
    if (!choice) return '';
    if (choice === '__new__') return newBoardName.value.trim();
    if (choice === '__pin_new__') return (row && row.new_board_name) ? row.new_board_name.trim() : '';
    const board = currentBoards().find(b => b.value === choice);
    return board ? board.name : '';
}
function csvEscape(value) {
    const s = String(value == null ? '' : value);
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}
document.getElementById('downloadCsvBtn').addEventListener('click', () => {
    if (!rows.length) { showAlert('Add at least one pin first.', 'error'); return; }
    const globalTitle = document.getElementById('bsGlobalTitle').value.trim();
    const globalDescription = document.getElementById('bsGlobalDescription').value.trim();
    const globalLink = document.getElementById('bsGlobalLink').value.trim();
    const globalKeywords = document.getElementById('bsKeywords').value.trim();
    const defaultBoardName = resolveBoardNameForCsv(boardHidden.value);

    const header = ['Title', 'Media URL', 'Pinterest board', 'Thumbnail', 'Description', 'Link', 'Publish date', 'Keywords'];
    const lines = [header.map(csvEscape).join(',')];
    rows.forEach(r => {
        const mediaUrl = r.image_path ? (APP_BASE_URL + '/' + r.image_path) : '';
        const boardName = resolveBoardNameForCsv(r.board_choice, r) || defaultBoardName;
        const publishDate = r.publish_at ? (r.publish_at.length === 16 ? r.publish_at + ':00' : r.publish_at) : '';
        const row = [
            (r.title || globalTitle || '').slice(0, 100),
            mediaUrl,
            boardName,
            '', // thumbnail — image pins only
            (r.description || globalDescription || '').slice(0, 500),
            r.link || globalLink || '',
            publishDate,
            (r.keywords || globalKeywords || '').slice(0, 500),
        ];
        lines.push(row.map(csvEscape).join(','));
    });

    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'pins-' + (document.getElementById('bsBatchName').value.trim().replace(/[^a-z0-9]+/gi, '-') || 'draft') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

/* ---------------- Scheduling ---------------- */
function applyScheduleIfSet() {
    const start = document.getElementById('bsStartTime').value;
    if (!start) return;
    applySchedule();
}
function applySchedule() {
    const start = document.getElementById('bsStartTime').value;
    const interval = parseInt(document.getElementById('bsInterval').value, 10) || 60;
    if (!start) { showAlert('Please set a first pin publish time.', 'error'); return; }
    let t = new Date(start);
    rows.forEach(r => {
        r.publish_at = fmtDateForInput(t);
        t = new Date(t.getTime() + interval * 60000);
    });
    renderRows();
}
document.getElementById('bsApplySchedule').addEventListener('click', applySchedule);

function applyPerDaySchedule() {
    const perDay = parseInt(document.getElementById('bsPinsPerDay').value, 10) || 1;
    const startDate = document.getElementById('bsStartDate').value;
    const startTime = document.getElementById('bsDayStartTime').value || '09:00';
    const gapHours = parseFloat(document.getElementById('bsGapHours').value) || (24 / perDay);
    if (!startDate) { showAlert('Please set a start date.', 'error'); return; }
    const base = new Date(startDate + 'T' + startTime);
    if (isNaN(base)) { showAlert('Invalid start date/time.', 'error'); return; }
    rows.forEach((r, i) => {
        const dayIndex = Math.floor(i / perDay);
        const slotIndex = i % perDay;
        const t = new Date(base.getTime() + dayIndex * 24 * 3600000 + slotIndex * gapHours * 3600000);
        r.publish_at = fmtDateForInput(t);
    });
    renderRows();
    showAlert(`Scheduled ${rows.length} pins at ${perDay} pin(s)/day, ${gapHours}h apart.`, 'success');
}
document.getElementById('bsApplyPerDaySchedule').addEventListener('click', applyPerDaySchedule);

/* ---------------- Bulk insert (one value per line) ---------------- */
document.querySelectorAll('[data-insert]').forEach(btn => {
    btn.addEventListener('click', () => {
        const field = btn.dataset.insert;
        const textareaId = { title: 'biTitles', description: 'biDescriptions', link: 'biLinks', alt: 'biAlt' }[field];
        const lines = document.getElementById(textareaId).value.split('\n').map(s => s.trim()).filter(s => s !== '');
        if (!lines.length) return;
        while (rows.length < lines.length) addRow({});
        lines.forEach((val, i) => { rows[i][field] = val; });
        renderRows();
    });
});

/* ---------------- Product tags (bulk) ---------------- */
document.getElementById('applyProductTagsBtn').addEventListener('click', () => {
    const lines = document.getElementById('productLinks').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    if (!lines.length) { showAlert('Please enter at least one product link.', 'error'); return; }
    if (!rows.length) { showAlert('Add pins first before applying product tags.', 'error'); return; }
    const applyToAll = document.getElementById('productApplyToAll').checked;
    if (applyToAll) {
        rows.forEach(r => { r.product_link = lines[0]; });
    } else {
        rows.forEach((r, i) => { if (lines[i]) r.product_link = lines[i]; });
    }
    renderRows();
    showAlert('Product tags applied.', 'success');
});

/* ---------------- Create with AI ---------------- */
const AI_CHUNK_SIZE = 10;
const RESOLVE_TITLES_URL = 'ajax-resolve-titles';

function progressLine(containerId, text, type) {
    const box = document.getElementById(containerId);
    const line = document.createElement('div');
    line.className = 'ai-progress-line ' + type;
    line.textContent = text;
    box.appendChild(line);
    line.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    return line;
}
function aiProgressLine(text, type) {
    return progressLine('aiProgress', text, type);
}

const LINK_CHUNK_SIZE = 20;

// Readable title from a link's slug — used only if the server can't be reached for that chunk,
// so a link is never silently dropped from the list.
function titleFromSlug(url) {
    try {
        const u = new URL(url);
        let seg = u.pathname.replace(/\/+$/, '').split('/').pop() || u.hostname;
        seg = decodeURIComponent(seg).replace(/\.(html?|php|aspx?)$/i, '').replace(/[-_+]+/g, ' ').replace(/^\d{3,}\s+/, '').trim();
        return seg ? seg.replace(/\b\w/g, c => c.toUpperCase()) : url;
    } catch (e) { return url; }
}

// POST that never throws on a bad/non-JSON response (e.g. a gateway timeout page).
async function postJson(url, fd) {
    try {
        const res = await fetch(url, { method: 'POST', body: fd });
        const text = await res.text();
        try { return JSON.parse(text); }
        catch (e) { return { ok: false, error: 'Server returned an invalid response (HTTP ' + res.status + ').' }; }
    } catch (e) {
        return { ok: false, error: 'Network error.' };
    }
}

// Fetches every link's page title, LINK_CHUNK_SIZE links per request, with live progress.
// Always returns exactly one title per link, in the same order.
async function resolveAiLinkTitles(links) {
    const titles = [];
    let fromSlug = 0;
    for (let i = 0; i < links.length; i += LINK_CHUNK_SIZE) {
        const chunk = links.slice(i, i + LINK_CHUNK_SIZE);
        const label = (i + 1) + '–' + (i + chunk.length) + ' of ' + links.length;
        const pending = aiProgressLine('Fetching titles ' + label + '…', 'pending');
        const fd = new FormData();
        fd.append('lines', JSON.stringify(chunk));
        let result = await postJson(RESOLVE_TITLES_URL, fd);
        if (!result.ok) result = await postJson(RESOLVE_TITLES_URL, fd); // one retry
        pending.remove();
        if (result.ok && Array.isArray(result.items) && result.items.length === chunk.length) {
            result.items.forEach((it, k) => {
                const t = (it.title || '').trim();
                if (!it.fetched) fromSlug++;
                titles.push(t || titleFromSlug(chunk[k]));
            });
            aiProgressLine('✓ Titles ' + label + ' fetched.', 'success');
        } else {
            chunk.forEach(l => { titles.push(/^https?:\/\//i.test(l) ? titleFromSlug(l) : l); fromSlug++; });
            aiProgressLine('⚠ Titles ' + label + ': ' + (result.error || 'fetch failed') + ' — used titles from the link URLs instead.', 'error');
        }
    }
    if (fromSlug) aiProgressLine('ℹ ' + fromSlug + ' page(s) could not be read, so their title was built from the link URL.', 'pending');
    return titles;
}

async function generatePinChunk(keywords, withTags, destLink, customPrompt) {
    const fd = new FormData();
    fd.append('keywords', JSON.stringify(keywords));
    fd.append('with_tags', withTags ? '1' : '0');
    fd.append('dest_link', destLink);
    fd.append('custom_prompt', customPrompt);
    return postJson(AI_URL, fd);
}

document.getElementById('aiGenerateBtn').addEventListener('click', async () => {
    const progressBox = document.getElementById('aiProgress');
    progressBox.innerHTML = '';

    let keywords = document.getElementById('aiKeywords').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    const linkLines = document.getElementById('aiLinks').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    const withTags = document.getElementById('aiTagStyle').value === 'with_tags';
    const destLink = document.getElementById('bsGlobalLink').value.trim();
    const customPrompt = document.getElementById('aiCustomPrompt').value.trim();

    if (!keywords.length && !linkLines.length) {
        showAlert('Please enter at least one keyword or link.', 'error');
        return;
    }

    const btn = document.getElementById('aiGenerateBtn');
    btn.disabled = true;
    btn.textContent = 'Generating…';

    try {
        // Links get resolved to their page titles first, those titles replace the raw
        // links in the box, and generation then continues automatically using them.
        if (linkLines.length) {
            aiProgressLine('Fetching titles for ' + linkLines.length + ' link(s)…', 'success');
            const resolvedTitles = await resolveAiLinkTitles(linkLines);
            document.getElementById('aiLinks').value = resolvedTitles.join('\n');
            keywords = keywords.concat(resolvedTitles);
            aiProgressLine('✓ Replaced links with ' + resolvedTitles.length + ' page title(s).', 'success');
        }

        if (!keywords.length) {
            aiProgressLine('✗ No usable keywords or link titles to generate from.', 'error');
            btn.disabled = false;
            btn.textContent = 'Generate & Insert with AI';
            return;
        }

        // Write and insert 10 pins at a time so large lists never hit the AI's output
        // limit in one go, and so progress is visible as each batch lands.
        const chunks = [];
        for (let i = 0; i < keywords.length; i += AI_CHUNK_SIZE) chunks.push(keywords.slice(i, i + AI_CHUNK_SIZE));

        // Every keyword owns a fixed row (offset + its index), so a failed chunk never shifts
        // later pins into the wrong rows, and one failure no longer stops the whole run.
        const offset = 0;
        let written = 0;
        const failed = [];
        for (let c = 0; c < chunks.length; c++) {
            const chunk = chunks[c];
            const start = offset + c * AI_CHUNK_SIZE;
            const rangeLabel = (start + 1) + '–' + (start + chunk.length) + ' of ' + keywords.length;
            const pending = aiProgressLine('Writing pins ' + rangeLabel + '…', 'pending');

            let result = await generatePinChunk(chunk, withTags, destLink, customPrompt);
            for (let attempt = 1; attempt <= 2 && (!result.ok || !Array.isArray(result.items) || !result.items.length); attempt++) {
                await new Promise(r => setTimeout(r, 1500 * attempt));
                result = await generatePinChunk(chunk, withTags, destLink, customPrompt);
            }
            pending.remove();

            while (rows.length < start + chunk.length) addRow({});
            const items = (result && Array.isArray(result.items)) ? result.items : [];
            chunk.forEach((kw, i) => {
                const row = rows[start + i];
                const item = items[i];
                if (item && item.title) {
                    row.title = item.title;
                    row.description = item.description || row.description;
                    if (item.alt_text) row.alt = item.alt_text;
                    if (item.keywords) row.keywords = item.keywords;
                    written++;
                } else {
                    if (!row.title) row.title = kw.slice(0, 100);
                    failed.push(kw);
                }
            });
            renderRows();
            applyScheduleIfSet();

            if (items.length >= chunk.length) {
                aiProgressLine('✓ Wrote pins ' + rangeLabel + '.', 'success');
            } else {
                aiProgressLine('✗ Pins ' + rangeLabel + ': ' + (result.error || 'AI did not return content') + ' — continuing with the next batch.', 'error');
            }
        }

        if (!failed.length) {
            aiProgressLine('All ' + written + ' pin(s) generated and inserted.', 'success');
        } else {
            aiProgressLine(written + ' of ' + keywords.length + ' pin(s) written. ' + failed.length + ' could not be written after 3 tries — those rows kept their title only, so you can fill them in or edit them.', 'error');
            showAlert(failed.length + ' pin(s) could not be written by AI (the rest were inserted).', 'error');
        }
    } catch (e) {
        aiProgressLine('✗ ' + (e.message || 'Network error contacting the AI writer.'), 'error');
        showAlert('AI generation failed: ' + (e.message || 'Network error contacting the AI writer.'), 'error');
    }

    btn.disabled = false;
    btn.textContent = 'Generate & Insert with AI';
});

/* ---------------- CSV import ---------------- */
const csvOverlay = document.getElementById('csvModalOverlay');
document.getElementById('openCsvModalBtn').addEventListener('click', () => csvOverlay.style.display = 'flex');
document.getElementById('csvModalClose').addEventListener('click', () => csvOverlay.style.display = 'none');
csvOverlay.addEventListener('click', (e) => { if (e.target === csvOverlay) csvOverlay.style.display = 'none'; });

const csvDropzone = document.getElementById('csvDropzone');
const csvFileInput = document.getElementById('csvFileInput');
csvDropzone.addEventListener('click', () => csvFileInput.click());
csvDropzone.addEventListener('dragover', (e) => { e.preventDefault(); csvDropzone.classList.add('dragover'); });
csvDropzone.addEventListener('dragleave', () => csvDropzone.classList.remove('dragover'));
csvDropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    csvDropzone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleCsvFile(e.dataTransfer.files[0]);
});
csvFileInput.addEventListener('change', () => {
    if (csvFileInput.files.length) handleCsvFile(csvFileInput.files[0]);
    csvFileInput.value = '';
});

// CSV column names in the wild vary a lot ("Image URL", "Image Link", "img", "Media URL", ...).
// Matching only the exact sample header names meant a real-world CSV with a differently-named
// image column matched nothing, so every single row looked like it had no image and got
// silently skipped — which is also why no AI content ever got generated (that step only runs
// for rows that were actually imported). Normalizing headers and matching against a list of
// common aliases per field fixes that.
function normalizeCsvHeaderKey(h) {
    return (h || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}
const CSV_FIELD_ALIASES = {
    imageUrl: ['imageurl', 'imageurL', 'image', 'imagelink', 'img', 'imgurl', 'imgsrc', 'imagesrc', 'photo', 'photourl', 'photolink', 'picture', 'pictureurl', 'media', 'mediaurl', 'medialink', 'src', 'thumbnail', 'thumbnailurl'],
    title: ['title', 'pintitle', 'headline', 'name'],
    description: ['description', 'desc', 'pindescription', 'body', 'text', 'caption'],
    outboundURL: ['outboundurl', 'outbound', 'link', 'url', 'destinationurl', 'destination', 'targeturl', 'clickurl', 'websiteurl', 'weblink', 'pinlink'],
    altText: ['alttext', 'alt', 'imagealt', 'altdescription', 'imagealttext'],
    // "keywords" is the pin's own actual keywords tag (goes straight onto the row, and into
    // the Keywords column on CSV export) — it used to be lumped in with baseTitle below, which
    // meant a CSV's Keywords column only ever got used as an AI title-writing hint and never
    // actually reached the row, so it silently never imported.
    keywords: ['keywords', 'keyword', 'tags'],
    baseTitle: ['basetitle', 'topic', 'idea'],
    baseDescription: ['basedescription'],
    boardName: ['boardname', 'board'],
    scheduleDate: ['scheduledate', 'date', 'publishdate', 'publishat', 'schedule', 'scheduletime', 'time', 'datetime'],
};
// Turns one CSV row (keyed by whatever the file's own header text was) into the canonical
// field names the importer expects, by matching normalized headers against the alias lists.
function normalizeCsvRow(rawRow) {
    const lookup = {};
    Object.keys(rawRow).forEach(h => { lookup[normalizeCsvHeaderKey(h)] = rawRow[h]; });
    const out = {};
    Object.keys(CSV_FIELD_ALIASES).forEach(field => {
        out[field] = '';
        for (const alias of CSV_FIELD_ALIASES[field]) {
            if (lookup[alias]) { out[field] = lookup[alias]; break; }
        }
    });
    return out;
}

function parseCsv(text) {
    const rowsOut = [];
    let field = '', row = [], inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQuotes) {
            if (c === '"') {
                if (text[i + 1] === '"') { field += '"'; i++; } else { inQuotes = false; }
            } else { field += c; }
        } else if (c === '"') {
            inQuotes = true;
        } else if (c === ',') {
            row.push(field); field = '';
        } else if (c === '\n' || c === '\r') {
            if (c === '\r' && text[i + 1] === '\n') i++;
            row.push(field); field = '';
            if (row.some(f => f.trim() !== '')) rowsOut.push(row);
            row = [];
        } else {
            field += c;
        }
    }
    if (field !== '' || row.length) { row.push(field); if (row.some(f => f.trim() !== '')) rowsOut.push(row); }
    if (!rowsOut.length) return [];
    const headers = rowsOut[0].map(h => h.trim());
    return rowsOut.slice(1).map(r => {
        const obj = {};
        headers.forEach((h, i) => { obj[h] = (r[i] || '').trim(); });
        return obj;
    });
}

async function handleCsvFile(file) {
    const summaryEl = document.getElementById('csvImportSummary');
    const statusEl = document.getElementById('csvImportStatus');
    statusEl.innerHTML = '';
    summaryEl.textContent = 'Reading CSV…';

    const text = await file.text();
    const rawRows = parseCsv(text);
    if (!rawRows.length) { summaryEl.textContent = 'No rows found in that CSV.'; return; }

    const needAiIdx = [];
    let imported = 0, skipped = 0;

    for (let i = 0; i < rawRows.length; i++) {
        const csvRow = normalizeCsvRow(rawRows[i]);
        const rowNum = i + 1;
        const imageUrl = csvRow.imageUrl;

        if (!imageUrl) {
            skipped++;
            progressLine('csvImportStatus', `✗ Row ${rowNum}: no image link found — check that column's header name.`, 'error');
            continue;
        }

        summaryEl.textContent = `Importing pin ${imported + skipped + 1} of ${rawRows.length}…`;
        const fetched = await fetchRemoteImage(imageUrl);
        if (!fetched.ok) {
            skipped++;
            progressLine('csvImportStatus', `✗ Row ${rowNum}: ${fetched.error || 'could not download that image.'}`, 'error');
            continue;
        }

        const title = csvRow.title;
        const description = csvRow.description;
        const scheduleDate = csvRow.scheduleDate;
        let publishAt = '';
        if (scheduleDate) {
            const d = new Date(scheduleDate);
            if (!isNaN(d)) publishAt = fmtDateForInput(d);
        }

        const row = addRow({
            image_path: fetched.path,
            filename: fetched.filename,
            title, description,
            link: csvRow.outboundURL,
            alt: csvRow.altText,
            keywords: csvRow.keywords,
            publish_at: publishAt,
        });

        if (!title || !description) {
            needAiIdx.push({ row, hint: csvRow.baseTitle || csvRow.keywords || csvRow.baseDescription || csvRow.altText || title || 'pin idea' });
        }
        imported++;
        progressLine('csvImportStatus', `✓ Row ${rowNum}: image imported.`, 'success');
    }

    if (needAiIdx.length) {
        summaryEl.textContent = `Generating titles/descriptions for ${needAiIdx.length} pin(s) with AI…`;
        // Same reasoning as the "Create with AI" panel: ask in batches of 10 rather than all
        // at once, so a big CSV can't blow the AI response past its parseable size either.
        for (let i = 0; i < needAiIdx.length; i += AI_CHUNK_SIZE) {
            const batch = needAiIdx.slice(i, i + AI_CHUNK_SIZE);
            try {
                const result = await generatePinChunk(
                    batch.map(x => x.hint),
                    document.getElementById('aiTagStyle').value === 'with_tags',
                    document.getElementById('bsGlobalLink').value.trim(),
                    ''
                );
                if (result.ok) {
                    batch.forEach((x, j) => {
                        const item = result.items[j];
                        if (!item) return;
                        if (!x.row.title) x.row.title = item.title;
                        if (!x.row.description) x.row.description = item.description;
                        if (!x.row.alt && item.alt_text) x.row.alt = item.alt_text;
                        if (!x.row.keywords && item.keywords) x.row.keywords = item.keywords;
                    });
                    progressLine('csvImportStatus', `✓ Wrote content for ${batch.length} pin(s).`, 'success');
                } else {
                    progressLine('csvImportStatus', `✗ AI content generation failed for ${batch.length} pin(s): ${result.error}`, 'error');
                }
            } catch (e) {
                progressLine('csvImportStatus', '✗ Network error generating AI content for some pins.', 'error');
            }
        }
    }

    renderRows();
    summaryEl.textContent = `Done — imported ${imported} pin(s)${skipped ? `, skipped ${skipped} (see details below)` : ''}.`;
    if (imported && !skipped) {
        setTimeout(() => { csvOverlay.style.display = 'none'; }, 1200);
    }
}

/* ---------------- Create Pin Image with AI ---------------- */
const PIN_IMAGE_URL = 'ajax-generate-pin-image';

document.getElementById('piCtaMode').addEventListener('change', function () {
    document.getElementById('piCtaCustomWrap').style.display = this.value === 'custom' ? 'block' : 'none';
});
document.getElementById('piPaletteEnabled').addEventListener('change', function () {
    document.getElementById('piPaletteWrap').style.display = this.checked ? 'block' : 'none';
});
document.getElementById('piPaletteCount').addEventListener('change', function () {
    document.getElementById('piColor4Wrap').style.display = this.value === '4' ? 'block' : 'none';
});
function piBuildColorPalette() {
    if (!document.getElementById('piPaletteEnabled').checked) return null;
    const count = parseInt(document.getElementById('piPaletteCount').value, 10);
    const colors = [
        document.getElementById('piColor1').value,
        document.getElementById('piColor2').value,
        document.getElementById('piColor3').value,
    ];
    if (count === 4) colors.push(document.getElementById('piColor4').value);
    return {
        enabled: true,
        colors: colors,
        website_text_color: document.getElementById('piWebsiteTextColor').value,
        website_bg_color: document.getElementById('piWebsiteBgColor').value,
        cta_text_color: document.getElementById('piCtaTextColor').value,
        cta_bg_color: document.getElementById('piCtaBgColor').value,
    };
}

function piSetLine(lineIndex, newText) {
    const ta = document.getElementById('piInputs');
    const lines = ta.value.split('\n');
    if (lines[lineIndex] !== undefined) {
        lines[lineIndex] = newText;
        ta.value = lines.join('\n');
    }
}
function piStatusRow(id, text, status) {
    // status: 'pending' | 'ok' | 'fail'
    return `<div class="pi-status-row pi-status-${status}" id="${id}" data-input-text="${escapeHtml(text)}">
        <span class="pi-status-dot"></span>
        <span class="pi-status-text">${escapeHtml(text)}</span>
    </div>`;
}
function piBuildFormData(inputText) {
    const fd = new FormData();
    fd.append('input', inputText);
    fd.append('size', document.getElementById('piSize').value);
    fd.append('website', document.getElementById('piWebsite').value.trim());
    fd.append('cta_mode', document.getElementById('piCtaMode').value);
    fd.append('cta_text', document.getElementById('piCtaText') ? document.getElementById('piCtaText').value.trim() : '');
    fd.append('custom_prompt', document.getElementById('piCustomPrompt').value.trim());
    fd.append('image_type', document.getElementById('piImageType').value);
    fd.append('collage_count', document.getElementById('piCollageCount').value);
    fd.append('quality', document.getElementById('piQuality').value);
    fd.append('image_style', document.getElementById('piImageStyle').value);
    fd.append('image_category_id', document.getElementById('piImageCategory') ? document.getElementById('piImageCategory').value : '');
    fd.append('color_palette', JSON.stringify(piBuildColorPalette() || {}));
    return fd;
}
document.getElementById('piStatusList').addEventListener('click', async (e) => {
    const btn = e.target.closest('.pi-retry-btn');
    if (!btn) return;
    const row = btn.closest('.pi-status-row');
    const text = row.dataset.inputText;
    btn.disabled = true;
    btn.textContent = 'Retrying…';
    let result;
    try {
        const res = await fetch(PIN_IMAGE_URL, { method: 'POST', body: piBuildFormData(text) });
        result = await res.json();
    } catch (err) {
        result = { ok: false, error: 'Network error.' };
    }
    if (result.ok) {
        addRow({ image_path: result.path, filename: result.filename, title: result.title, alt: result.title });
        applyScheduleIfSet();
        row.className = 'pi-status-row pi-status-ok';
        row.querySelector('.pi-status-text').textContent = '✓ ' + result.title;
        btn.remove();
        if (result.remaining_credits !== undefined) {
            document.getElementById('creditsBalance').textContent = Number(result.remaining_credits).toFixed(1);
        }
    } else {
        row.querySelector('.pi-status-text').textContent = '✗ ' + text + ' — ' + (result.error || 'failed');
        btn.disabled = false;
        btn.textContent = 'Retry';
        if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(result.error);
    }
});

document.getElementById('piGenerateBtn').addEventListener('click', async () => {
    const lines = document.getElementById('piInputs').value.split('\n').map(s => s.trim()).filter(s => s !== '');
    if (!lines.length) { showAlert('Please enter at least one title or link.', 'error'); return; }

    const size = document.getElementById('piSize').value;
    const website = document.getElementById('piWebsite').value.trim();
    const ctaMode = document.getElementById('piCtaMode').value;
    const ctaText = document.getElementById('piCtaText') ? document.getElementById('piCtaText').value.trim() : '';
    const customPrompt = document.getElementById('piCustomPrompt').value.trim();
    const imageType = document.getElementById('piImageType').value;
    const collageCount = document.getElementById('piCollageCount').value;
    const quality = document.getElementById('piQuality').value;
    const imageStyle = document.getElementById('piImageStyle').value;
    const colorPalette = JSON.stringify(piBuildColorPalette() || {});

    const statusList = document.getElementById('piStatusList');
    statusList.innerHTML = '';
    const btn = document.getElementById('piGenerateBtn');
    btn.disabled = true;

    for (let i = 0; i < lines.length; i++) {
        const rowId = 'piStatus' + i;
        statusList.insertAdjacentHTML('beforeend', piStatusRow(rowId, lines[i], 'pending'));
        btn.textContent = `Generating ${i + 1} of ${lines.length}…`;

        const fd = new FormData();
        fd.append('input', lines[i]);
        fd.append('size', size);
        fd.append('website', website);
        fd.append('cta_mode', ctaMode);
        fd.append('cta_text', ctaText);
        fd.append('custom_prompt', customPrompt);
        fd.append('image_type', imageType);
        fd.append('collage_count', collageCount);
        fd.append('quality', quality);
        fd.append('image_style', imageStyle);
        fd.append('image_category_id', document.getElementById('piImageCategory') ? document.getElementById('piImageCategory').value : '');
        fd.append('color_palette', colorPalette);

        let result;
        try {
            const res = await fetch(PIN_IMAGE_URL, { method: 'POST', body: fd });
            result = await res.json();
        } catch (e) {
            result = { ok: false, error: 'Network error.' };
        }

        const statusEl = document.getElementById(rowId);
        if (result.title && result.title !== lines[i]) {
            piSetLine(i, result.title); // link resolved to a title — reflect it in the textarea
        }

        if (result.ok) {
            addRow({ image_path: result.path, filename: result.filename, title: result.title, alt: result.title });
            applyScheduleIfSet();
            if (statusEl) {
                statusEl.className = 'pi-status-row pi-status-ok';
                statusEl.querySelector('.pi-status-text').textContent = '✓ ' + result.title;
            }
            if (result.remaining_credits !== undefined) {
                document.getElementById('creditsBalance').textContent = Number(result.remaining_credits).toFixed(1);
            }
        } else {
            // Still add an empty row so the pin count / order isn't lost, per spec.
            addRow({ title: result.title || lines[i] });
            if (statusEl) {
                statusEl.className = 'pi-status-row pi-status-fail';
                statusEl.querySelector('.pi-status-text').textContent = '✗ ' + (result.title || lines[i]) + ' — ' + (result.error || 'failed');
                statusEl.insertAdjacentHTML('beforeend', '<button type="button" class="btn-secondary btn-small pi-retry-btn">Retry</button>');
            }
            // Low/zero credits will fail every remaining line the same way — show the
            // upgrade popup once and stop hammering the rest instead of looping to failure.
            if (window.maybeShowUpgradePopup && window.maybeShowUpgradePopup(result.error)) {
                break;
            }
        }
    }

    btn.disabled = false;
    btn.textContent = '🎨 Generate Pin Images';
});

/* ---------------- Draft autosave ---------------- */
let autosaveTimer = null;
function scheduleAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(saveDraftNow, 4000);
}
function collectState() {
    return {
        account_id: accountSelect.value,
        board_choice: boardHidden.value,
        board_mode: boardMode,
        new_board_name: newBoardName.value,
        new_board_description: newBoardDescription.value,
        global_title: document.getElementById('bsGlobalTitle').value,
        global_description: document.getElementById('bsGlobalDescription').value,
        global_link: document.getElementById('bsGlobalLink').value,
        tags: tagsList,
        keywords: document.getElementById('bsKeywords').value,
        product_links: document.getElementById('productLinks').value,
        rows: rows.map(r => ({ ...r, uploading: false })),
    };
}
async function saveDraftNow() {
    const name = document.getElementById('bsBatchName').value.trim();
    const state = collectState();
    const fd = new FormData();
    fd.append('batch_id', draftBatchId || '');
    fd.append('name', name);
    fd.append('state', JSON.stringify(state));
    try {
        const res = await fetch(DRAFT_SAVE_URL, { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok && result.batch_id) {
            draftBatchId = result.batch_id;
            if (!result.skipped) {
                document.getElementById('draftStatusLine').textContent = 'Draft saved ' + new Date().toLocaleTimeString();
                history.replaceState(null, '', 'bulk-schedule?draft=' + draftBatchId);
            }
        }
    } catch (e) { /* silent — autosave is best-effort */ }
}
setInterval(saveDraftNow, 25000);
['bsBatchName', 'bsGlobalTitle', 'bsGlobalDescription', 'bsGlobalLink', 'bsKeywords', 'productLinks'].forEach(id => {
    document.getElementById(id).addEventListener('input', scheduleAutosave);
});

function restoreDraft() {
    if (!INITIAL_DRAFT) return;
    document.getElementById('bsBatchName').value = INITIAL_DRAFT.name || '';
    const s = INITIAL_DRAFT.state;
    if (!s) return;
    if (s.account_id) { accountSelect.value = s.account_id; populateBoards(); }
    if (s.board_mode === 'create') { setBoardMode('create'); }
    if (s.board_choice) { boardHidden.value = s.board_choice; renderBoardPicker(); }
    newBoardName.value = s.new_board_name || '';
    newBoardDescription.value = s.new_board_description || '';
    document.getElementById('bsGlobalTitle').value = s.global_title || '';
    document.getElementById('bsGlobalDescription').value = s.global_description || '';
    document.getElementById('bsGlobalLink').value = s.global_link || '';
    tagsList = Array.isArray(s.tags) ? s.tags : [];
    renderTagPills();
    document.getElementById('bsKeywords').value = s.keywords || '';
    document.getElementById('productLinks').value = s.product_links || '';
    if (Array.isArray(s.rows) && s.rows.length) {
        rows = s.rows.map(r => { rowSeq = Math.max(rowSeq, r.id || 0); return r; });
    }
    renderRows();
    if (INITIAL_DRAFT.batch_id) {
        document.getElementById('draftStatusLine').textContent = 'Resumed draft';
    }
}

/* ---------------- Schedule All Pins ---------------- */
document.getElementById('scheduleAllBtn').addEventListener('click', async () => {
    if (!rows.length) { showAlert('Add at least one pin first.', 'error'); return; }
    if (!accountSelect.value) { showAlert('Please select a Pinterest account.', 'error'); return; }
    // A pin has its own board when "Board for this pin" / Multiple Boards (AI) set one for it.
    const rowHasOwnBoard = r => (r.board_choice === '__pin_new__' && (r.new_board_name || '').trim() !== '')
        || (typeof r.board_choice === 'string' && r.board_choice.indexOf('row:') === 0);
    const batchBoardOk = boardHidden.value && !(boardHidden.value === '__new__' && !newBoardName.value.trim());
    const rowsWithoutBoard = rows.map((r, i) => rowHasOwnBoard(r) ? null : i + 1).filter(Boolean);
    if (!batchBoardOk && rowsWithoutBoard.length) {
        if (boardHidden.value === '__new__') {
            showAlert('Please name the new board (or set "Board for this pin" on pin' + (rowsWithoutBoard.length > 1 ? 's #' : ' #') + rowsWithoutBoard.slice(0, 10).join(', #') + ').', 'error');
        } else {
            showAlert('Please select or create a board (pin' + (rowsWithoutBoard.length > 1 ? 's #' : ' #') + rowsWithoutBoard.slice(0, 10).join(', #') + ' have no board of their own).', 'error');
        }
        return;
    }

    const missingImage = rows.findIndex(r => !r.image_path);
    if (missingImage !== -1) { showAlert('Row ' + (missingImage + 1) + ' is missing an image.', 'error'); return; }
    const missingTime = rows.findIndex(r => !r.publish_at);
    if (missingTime !== -1) { showAlert('Row ' + (missingTime + 1) + ' is missing a publish time.', 'error'); return; }

    const globalTitle = document.getElementById('bsGlobalTitle').value.trim();
    const globalDescription = document.getElementById('bsGlobalDescription').value.trim();
    const globalLink = document.getElementById('bsGlobalLink').value.trim();
    const tagsCsv = tagsList.join(', ');
    const keywordsCsv = document.getElementById('bsKeywords').value.trim();

    const payload = {
        batch_id: draftBatchId || '',
        batch_name: document.getElementById('bsBatchName').value.trim(),
        pinterest_account_id: accountSelect.value,
        board_choice: batchBoardOk ? boardHidden.value : '',
        new_board_name: batchBoardOk ? newBoardName.value.trim() : '',
        new_board_description: batchBoardOk ? newBoardDescription.value.trim() : '',
        global_title: globalTitle,
        global_description: globalDescription,
        global_link: globalLink,
        tags: tagsCsv,
        keywords: keywordsCsv,
        pins: rows.map(r => ({
            image_path: r.image_path,
            title: r.title || globalTitle,
            description: r.description || globalDescription,
            link: r.link || globalLink,
            alt: r.alt,
            keywords: r.keywords || '',
            board_choice: r.board_choice || '',
            new_board_name: r.new_board_name || '',
            new_board_description: r.new_board_description || '',
            product_link: r.product_link || '',
            publish_at: r.publish_at,
        })),
    };

    const btn = document.getElementById('scheduleAllBtn');
    btn.disabled = true;
    btn.textContent = 'Scheduling…';

    const fd = new FormData();
    fd.append('payload', JSON.stringify(payload));

    try {
        const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
        const result = await res.json();
        if (result.ok) {
            let msg = 'Scheduled ' + result.count + ' pins successfully!';
            if (result.published_now) msg += ' ' + result.published_now + ' overdue pin(s) were published right away.';
            if (result.failed_now) msg += ' ' + result.failed_now + ' overdue pin(s) failed to publish — see Batches.';
            if (result.overdue_queued) msg += ' ' + result.overdue_queued + ' overdue pin(s) will publish within a minute.';
            showAlert(msg + ' <a href="batches">View in Batches</a>.', 'success');
            rows = [];
            draftBatchId = null;
            renderRows();
            history.replaceState(null, '', 'bulk-schedule');
        } else {
            if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(result.error);
            showAlert('Failed to schedule pins: ' + result.error, 'error');
        }
    } catch (e) {
        showAlert('Network error while scheduling pins.', 'error');
    }
    btn.disabled = false;
    btn.textContent = '🕐 Schedule All Pins';
});

restoreDraft();
renderRows();

/* ---------------- Pages sent from the Design editor ---------------- */
const DESIGN_SET = <?= $designSet ? json_encode($designSet, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) : 'null' ?>;
if (DESIGN_SET && !INITIAL_DRAFT) {
    DESIGN_SET.items.forEach((d, i) => addRow({
        image_path: d.path, filename: d.file,
        title: d.name || '', alt: d.name || (DESIGN_SET.title ? DESIGN_SET.title + ' ' + (i + 1) : ''),
    }));
    const bn = document.getElementById('bsBatchName');
    if (bn && !bn.value && DESIGN_SET.title) bn.value = DESIGN_SET.title;
    showAlert(DESIGN_SET.items.length + ' design page(s) added as pins. Add titles, descriptions, links, boards and times — then schedule them all.', 'success');
    history.replaceState(null, '', 'bulk-schedule');
    if (typeof scheduleAutosave === 'function') scheduleAutosave();
}
</script>

<?php endif; ?>
<script src="../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/category-picker.js') ?: time() ?>"></script>
<script src="../assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/template-picker.js') ?: time() ?>"></script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
