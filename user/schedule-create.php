<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/pinterest_analytics_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'create';
$pageTitle = 'Create Schedule';

$stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? AND status = 'connected'");
$stmt->execute([$user['id']]);
$accounts = $stmt->fetchAll();

// Refresh each account's board list (real + locally-drafted pending boards) so the dropdown is current.
$boardsByAccount = [];
foreach ($accounts as $acc) {
    $rows = get_boards_for_account($pdo, $acc);
    $boardsByAccount[$acc['id']] = array_map(function ($b) {
        return [
            'value' => 'row:' . $b['id'],
            'name' => $b['board_name'] . ($b['status'] === 'pending_creation' ? ' (will be created)' : ($b['status'] === 'create_failed' ? ' (creation failed)' : '')),
        ];
    }, $rows);
}

// AI credits/settings — same source the Bulk Pin Scheduler's "Create with AI" and
// "Create Pin Image with AI" panels use, so balances and pricing stay in sync everywhere.
$articleSettings = get_article_settings($pdo);
$hasPinAi = $articleSettings && (!empty($articleSettings['pin_text_provider']) || !empty($articleSettings['text_provider']));
$myImageCredits = get_user_image_credits($pdo, $user['id']);
$myTextCredits = get_user_text_credits($pdo, $user['id']);
$qualityCosts = credit_pricing_get($pdo);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = (int)($_POST['pinterest_account_id'] ?? 0);
    $boardChoice = trim($_POST['board_choice'] ?? '');
    $newBoardName = trim($_POST['new_board_name'] ?? '');
    $newBoardDescription = trim($_POST['new_board_description'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['dest_link'] ?? '');
    $altText = trim($_POST['alt_text'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $publishAt = trim($_POST['publish_at'] ?? '');
    $aiImagePath = trim($_POST['ai_image_path'] ?? '');
    // "Schedule Pin" queues it for publish_at; "Publish Now" sends it to Pinterest immediately.
    $action = ($_POST['action'] ?? 'schedule') === 'publish' ? 'publish' : 'schedule';

    $ownAccount = false;
    foreach ($accounts as $acc) {
        if ((int)$acc['id'] === $accountId) $ownAccount = true;
    }
    if (!$ownAccount) $errors[] = 'Please choose a valid connected Pinterest account.';
    if ($action === 'publish') {
        $publishAt = date('Y-m-d H:i:s');
    } elseif ($publishAt === '') {
        $errors[] = 'Please choose a publish date/time.';
    }

    $boardResolved = null;
    if ($ownAccount) {
        $boardResolved = resolve_board_selection($pdo, $accountId, $boardChoice, $newBoardName, $newBoardDescription);
        if (!$boardResolved['ok']) $errors[] = $boardResolved['error'];
    }

    $imagePath = null;

    // If an AI pin image was created via the popup below, ajax-generate-pin-image has
    // already saved it under uploads/pins/ and deducted image credits — just reuse that path.
    // Validate strictly (filename only, must actually exist) so this can't be used to smuggle
    // an arbitrary path in.
    if ($aiImagePath !== '') {
        $base = basename($aiImagePath);
        if (preg_match('/^[A-Za-z0-9_\-.]+\.(jpg|jpeg|png|webp|gif)$/i', $base) && is_file(__DIR__ . '/../uploads/pins/' . $base)) {
            $imagePath = 'uploads/pins/' . $base;
        } else {
            $errors[] = 'The AI-generated image could not be found. Please create it again.';
        }
    }

    if ($imagePath === null) {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload an image or create one with AI.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (!isset($allowed[$mime])) {
                $errors[] = 'Unsupported image type. Please upload JPG, PNG, WEBP or GIF.';
            } else {
                $ext = $allowed[$mime];
                $filename = 'pin_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destDir = __DIR__ . '/../uploads/pins/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destDir . $filename)) {
                    $imagePath = 'uploads/pins/' . $filename;
                } else {
                    $errors[] = 'Failed to save the uploaded image.';
                }
            }
        }
    }

    if (empty($errors)) {
        $limitCheck = check_pin_scheduling_limit($pdo, (int)$user['id']);
        if (!$limitCheck['allowed']) {
            $errors[] = $limitCheck['message'];
        }
    }
    if (empty($errors)) {
        $plan = get_user_plan($pdo, (int)$user['id']);
        $uploadLimit = plan_limit_value($plan, 'upload_pins_limit');
        if ($uploadLimit !== null) {
            $ownerId = team_effective_owner_id($pdo, (int)$user['id']);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND source = 'manual'");
            $stmt->execute([$ownerId]);
            if ((int)$stmt->fetchColumn() >= $uploadLimit) {
                $errors[] = "Your plan allows up to $uploadLimit uploaded pin(s). Upgrade your plan to upload more.";
            }
        }
    }

    $publishResultError = null;
    $publishedNow = false;
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO scheduled_pins
            (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, publish_at, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual')");
        $stmt->execute([
            $user['id'], $accountId,
            $boardResolved['board_id'], $boardResolved['board_name'], $boardResolved['board_row_id'],
            $imagePath, $title, $description, $link, $altText, $keywords, $publishAt,
        ]);
        $success = true;

        // "Publish Now" — try to send it to Pinterest right away. If it fails (rate limit,
        // board not ready yet, etc.) the pin stays queued with status 'pending' and
        // cron/scheduler.php will retry it automatically, same as a normal scheduled pin.
        // Also: a scheduled time that has ALREADY passed (overdue) publishes right away instead of waiting.
        $newPinIdForOverdue = (int)$pdo->lastInsertId();
        if ($action !== 'publish' && strtotime($publishAt) !== false && strtotime($publishAt) <= time()) {
            $action = 'publish';
        }
        if ($action === 'publish') {
            $newPinId = $newPinIdForOverdue;
            $publishResult = pa_publish_pin_now($pdo, $newPinId);
            $publishedNow = $publishResult['ok'];
            $publishResultError = $publishResult['error'] ?? null;
        }
    }
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Create Schedule</h1>
    <a href="bulk-schedule" class="btn-secondary">Bulk Scheduler →</a>
</div>

<?php if (empty($accounts)): ?>
    <div class="alert alert-info">You need to <a href="connect-pinterest">connect a Pinterest account</a> before scheduling pins.</div>
<?php else: ?>

<?php if ($success && $action === 'publish'): ?>
    <?php if ($publishedNow): ?>
        <div class="alert alert-success">Pin published to Pinterest now! <a href="schedule-list">View your pins</a>.</div>
    <?php else: ?>
        <div class="alert alert-info">Pin saved — publishing now didn't go through yet (<?= e($publishResultError ?: 'will retry automatically') ?>). It stays queued and will publish automatically. <a href="schedule-list">View your pins</a>.</div>
    <?php endif; ?>
<?php elseif ($success): ?>
    <div class="alert alert-success">Pin scheduled successfully! <a href="schedule-list">View your scheduled pins</a>.</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>
<?php if (!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var msgs = <?= json_encode(array_values($errors)) ?>;
    for (var i = 0; i < msgs.length; i++) {
        if (window.maybeShowUpgradePopup && window.maybeShowUpgradePopup(msgs[i])) break;
    }
});
</script>
<?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data" id="singlePinForm">
        <div class="form-row">
            <label>Pinterest Account</label>
            <select name="pinterest_account_id" id="accountSelect" required>
                <option value="">-- Select account --</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int)$acc['id'] ?>"><?= e($acc['pinterest_username'] ?: 'Account #' . $acc['id']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Board</label>
            <select name="board_choice" id="boardSelect" required>
                <option value="">-- Select an account first --</option>
            </select>
        </div>

        <div class="form-row" id="newBoardFields" style="display:none;">
            <label>New Board Name</label>
            <input type="text" name="new_board_name" id="newBoardName" placeholder="e.g. Home Decor Ideas">
            <label style="margin-top:10px;">New Board Description</label>
            <textarea name="new_board_description" id="newBoardDescription" placeholder="What this board is about..."></textarea>
            <p class="muted">This board will be created automatically on Pinterest about 5 minutes before its first pin is due to publish.</p>
        </div>

        <!-- IMAGE: upload OR create with AI -->
        <div class="form-row">
            <label>Pin Image</label>
            <div class="board-mode-toggle">
                <button type="button" class="board-mode-btn active" data-image-tab="upload">⬆ Upload Image</button>
                <button type="button" class="board-mode-btn" data-image-tab="ai">🎨 Create with AI</button>
            </div>

            <div id="imageTabUpload">
                <input type="file" name="image" id="imageFileInput" accept="image/*">
            </div>

            <div id="imageTabAi" style="display:none;">
                <div id="aiImagePreviewWrap" style="display:none; margin-bottom:10px;">
                    <img id="aiImagePreview" src="" alt="AI generated pin image" style="max-width:220px; display:block; border-radius:10px; border:1px solid #e5e7eb; margin-bottom:8px;">
                    <button type="button" class="btn-secondary btn-small" id="aiImageChangeBtn">✕ Remove &amp; Create Another</button>
                </div>
                <button type="button" class="btn-primary" id="openAiImageModalBtn">🎨 Open AI Image Creator</button>
                <p class="muted" id="aiImageHint" style="margin-bottom:0;">No AI image created yet — click above to open the AI image creator.</p>
            </div>

            <input type="hidden" name="ai_image_path" id="aiImagePathField" value="">
        </div>

        <!-- WRITE WITH AI: title, description, keywords, alt text -->
        <div class="bs-ai-box" style="margin-bottom:18px;">
            <span class="ai-badge">✨ Write with AI</span>
            <div class="credits-note">Text AI balance: <strong id="textCreditsBalance"><?= number_format($myTextCredits, 1) ?></strong> credits <?php if ($myTextCredits <= 0): ?><span class="credits-low-warn">— low! <a href="upgrade">Upgrade your plan</a></span><?php endif; ?></div>
            <?php if (!$hasPinAi): ?>
                <div class="alert alert-info">No AI text model is configured yet — ask the site admin to set one up under Bulk Pin Scheduler settings.</div>
            <?php endif; ?>
            <div class="form-row">
                <label>Topic, Keyword or Link <span class="muted">(what this pin is about — a link's page title is fetched automatically)</span></label>
                <input type="text" id="waTopic" placeholder="e.g. 10 Cozy Living Room Ideas For Fall, or https://example.com/article">
            </div>
            <div class="two-col">
                <div class="form-row" style="margin-bottom:0;">
                    <label>Description Style</label>
                    <select id="waTagStyle">
                        <option value="without_tags">Without hashtags</option>
                        <option value="with_tags">With hashtags</option>
                    </select>
                </div>
                <div class="form-row" style="margin-bottom:0;">
                    <label>Custom Instructions <span class="muted">(optional)</span></label>
                    <input type="text" id="waCustomPrompt" placeholder="e.g. Write in a playful tone">
                </div>
            </div>
            <button type="button" class="btn-primary" id="waGenerateBtn" style="width:100%; margin-top:12px;">✨ Write Title, Description, Keywords &amp; Alt Text</button>
            <div id="waProgress" class="ai-progress"></div>
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>Title</label>
                <input type="text" name="title" id="fieldTitle" maxlength="100">
            </div>
            <div class="form-row">
                <label>Destination Link (optional)</label>
                <input type="url" name="dest_link" id="fieldLink" placeholder="https://...">
            </div>
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" id="fieldDescription" maxlength="500"></textarea>
        </div>

        <div class="two-col">
            <div class="form-row">
                <label>Image Alt Text (optional)</label>
                <input type="text" name="alt_text" id="fieldAltText" maxlength="500">
            </div>
            <div class="form-row">
                <label>Keywords <span class="muted">(comma separated, optional)</span></label>
                <input type="text" name="keywords" id="fieldKeywords" placeholder="e.g. home decor, cozy living room, small space ideas">
            </div>
        </div>

        <div class="form-row" id="publishAtRow">
            <label>Publish Date &amp; Time</label>
            <input type="datetime-local" name="publish_at" id="fieldPublishAt" required>
        </div>

        <input type="hidden" name="action" id="fieldAction" value="schedule">
        <div class="form-row" style="display:flex; gap:10px; margin-top:4px;">
            <button type="submit" class="btn-primary" id="scheduleSubmitBtn">Schedule Pin</button>
            <button type="submit" class="btn-secondary" id="publishNowBtn">🚀 Publish Now</button>
        </div>
    </form>
</div>

<!-- AI IMAGE CREATOR MODAL (same options as the Bulk Pin Scheduler's "Create Pin Image with AI") -->
<div class="modal-overlay" id="aiImageModalOverlay" style="display:none;">
    <div class="modal-box" style="max-width:640px; max-height:88vh; overflow-y:auto;">
        <div class="modal-header">
            <h2>🎨 Create Pin Image with AI</h2>
            <button type="button" class="modal-close" id="aiImageModalClose">✕</button>
        </div>

        <div class="credits-note">Image AI balance: <strong id="imageCreditsBalance"><?= number_format($myImageCredits, 1) ?></strong> credits <?php if ($myImageCredits <= 0): ?><span class="credits-low-warn">— low! <a href="upgrade">Upgrade your plan</a></span><?php endif; ?></div>

        <div class="form-row">
            <label>Title or Link <span class="muted">(a link's page title is fetched automatically, and long titles are shortened by AI)</span></label>
            <input type="text" id="piInput" placeholder="e.g. 10 Cozy Living Room Ideas For Fall, or https://example.com/blog/small-kitchen-storage">
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
                <option value="budget">Budget — <?= number_format($qualityCosts['image_quality_low'], 2) ?> credits</option>
                <option value="high">High Quality — <?= number_format($qualityCosts['image_quality_medium'], 2) ?> credits</option>
                <option value="ultra">Ultra Quality — <?= number_format($qualityCosts['image_quality_high'], 2) ?> credits</option>
            </select>
        </div>

        <button type="button" class="btn-primary" id="piGenerateBtn" style="width:100%;">🎨 Generate Pin Image</button>
        <div id="piStatusList" class="pi-status-list"></div>

        <div id="piResultWrap" style="display:none; margin-top:16px; text-align:center;">
            <img id="piResultImage" src="" alt="Generated pin preview" style="max-width:100%; max-height:360px; border-radius:12px; border:1px solid #e5e7eb;">
            <div style="margin-top:12px; display:flex; gap:10px; justify-content:center;">
                <button type="button" class="btn-primary" id="piUseImageBtn">✓ Use This Image</button>
                <button type="button" class="btn-secondary" id="piRegenerateBtn">↻ Regenerate</button>
            </div>
        </div>
    </div>
</div>

<script>
const boardsByAccount = <?= json_encode($boardsByAccount, JSON_HEX_TAG) ?>;
const accountSelect = document.getElementById('accountSelect');
const boardSelect = document.getElementById('boardSelect');
const newBoardFields = document.getElementById('newBoardFields');
const newBoardName = document.getElementById('newBoardName');

function populateBoards() {
    const accId = accountSelect.value;
    boardSelect.innerHTML = '';
    if (!accId) {
        boardSelect.innerHTML = '<option value="">-- Select an account first --</option>';
        return;
    }
    boardSelect.innerHTML = '<option value="">-- Select board --</option>';
    (boardsByAccount[accId] || []).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.value;
        opt.textContent = b.name;
        boardSelect.appendChild(opt);
    });
    const newOpt = document.createElement('option');
    newOpt.value = '__new__';
    newOpt.textContent = '+ Create New Board';
    boardSelect.appendChild(newOpt);
}
function toggleNewBoardFields() {
    const isNew = boardSelect.value === '__new__';
    newBoardFields.style.display = isNew ? 'block' : 'none';
    newBoardName.required = isNew;
}
accountSelect.addEventListener('change', populateBoards);
boardSelect.addEventListener('change', toggleNewBoardFields);

function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* ---------------- Image: Upload vs Create with AI tabs ---------------- */
const imageFileInput = document.getElementById('imageFileInput');
const aiImagePathField = document.getElementById('aiImagePathField');
const imageTabUpload = document.getElementById('imageTabUpload');
const imageTabAi = document.getElementById('imageTabAi');
document.querySelectorAll('[data-image-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-image-tab]').forEach(b => b.classList.toggle('active', b === btn));
        const tab = btn.dataset.imageTab;
        imageTabUpload.style.display = tab === 'upload' ? 'block' : 'none';
        imageTabAi.style.display = tab === 'ai' ? 'block' : 'none';
        if (tab === 'ai') imageFileInput.value = ''; // don't submit both an upload and an AI image
    });
});

/* ---------------- Coming from the Design editor ("Use this design") ---------------- */
<?php
$designFile = basename((string)($_GET['design'] ?? ''));
$designOk = $designFile !== '' && preg_match('/^design_[a-f0-9]{16}\.jpg$/', $designFile) && is_file(__DIR__ . '/../uploads/pins/' . $designFile);
?>
<?php if ($designOk): ?>
(function () {
    const file = <?= json_encode($designFile) ?>;
    document.querySelector('[data-image-tab="ai"]').click();
    aiImagePathField.value = 'uploads/pins/' + file;
    document.getElementById('aiImagePreview').src = '../uploads/pins/' + file;
    document.getElementById('aiImagePreviewWrap').style.display = 'block';
    document.getElementById('aiImageHint').style.display = 'none';
})();
<?php endif; ?>

/* ---------------- AI Image Creator popup ---------------- */
const PIN_IMAGE_URL = 'ajax-generate-pin-image';
const aiImageModalOverlay = document.getElementById('aiImageModalOverlay');

document.getElementById('openAiImageModalBtn').addEventListener('click', () => { aiImageModalOverlay.style.display = 'flex'; });
document.getElementById('aiImageModalClose').addEventListener('click', () => { aiImageModalOverlay.style.display = 'none'; });
aiImageModalOverlay.addEventListener('click', (e) => { if (e.target === aiImageModalOverlay) aiImageModalOverlay.style.display = 'none'; });

document.getElementById('aiImageChangeBtn').addEventListener('click', () => {
    aiImagePathField.value = '';
    document.getElementById('aiImagePreviewWrap').style.display = 'none';
    document.getElementById('aiImageHint').style.display = 'block';
});

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
function piStatusRow(text, status) {
    return `<div class="pi-status-row pi-status-${status}"><span class="pi-status-dot"></span><span class="pi-status-text">${escapeHtml(text)}</span></div>`;
}
function piBuildFormData() {
    const fd = new FormData();
    fd.append('input', document.getElementById('piInput').value.trim());
    fd.append('size', document.getElementById('piSize').value);
    fd.append('website', document.getElementById('piWebsite').value.trim());
    fd.append('cta_mode', document.getElementById('piCtaMode').value);
    fd.append('cta_text', document.getElementById('piCtaText').value.trim());
    fd.append('custom_prompt', document.getElementById('piCustomPrompt').value.trim());
    fd.append('image_type', document.getElementById('piImageType').value);
    fd.append('collage_count', document.getElementById('piCollageCount').value);
    fd.append('quality', document.getElementById('piQuality').value);
    fd.append('image_style', document.getElementById('piImageStyle').value);
    fd.append('image_category_id', document.getElementById('piImageCategory') ? document.getElementById('piImageCategory').value : '');
    fd.append('color_palette', JSON.stringify(piBuildColorPalette() || {}));
    return fd;
}
async function piGenerate() {
    const inputVal = document.getElementById('piInput').value.trim();
    if (!inputVal) { alert('Please enter a title or link for the AI image.'); return; }

    const statusList = document.getElementById('piStatusList');
    const resultWrap = document.getElementById('piResultWrap');
    resultWrap.style.display = 'none';
    statusList.innerHTML = piStatusRow(inputVal, 'pending');

    const genBtn = document.getElementById('piGenerateBtn');
    const regenBtn = document.getElementById('piRegenerateBtn');
    genBtn.disabled = true;
    regenBtn.disabled = true;
    genBtn.textContent = 'Generating…';

    let result;
    try {
        const res = await fetch(PIN_IMAGE_URL, { method: 'POST', body: piBuildFormData() });
        result = await res.json();
    } catch (err) {
        result = { ok: false, error: 'Network error.' };
    }

    genBtn.disabled = false;
    regenBtn.disabled = false;
    genBtn.textContent = '🎨 Generate Pin Image';

    if (result.ok) {
        statusList.innerHTML = piStatusRow('✓ ' + result.title, 'ok');
        const img = document.getElementById('piResultImage');
        img.src = '../' + result.path;
        img.dataset.path = result.path;
        img.dataset.title = result.title;
        resultWrap.style.display = 'block';
        if (result.title) document.getElementById('piInput').value = result.title; // reflect resolved/shortened title
        if (result.remaining_credits !== undefined) {
            document.getElementById('imageCreditsBalance').textContent = Number(result.remaining_credits).toFixed(1);
        }
    } else {
        statusList.innerHTML = piStatusRow('✗ ' + (result.error || 'Generation failed'), 'fail');
        if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(result.error);
    }
}
document.getElementById('piGenerateBtn').addEventListener('click', piGenerate);
document.getElementById('piRegenerateBtn').addEventListener('click', piGenerate);
document.getElementById('piUseImageBtn').addEventListener('click', () => {
    const img = document.getElementById('piResultImage');
    if (!img.dataset.path) return;
    aiImagePathField.value = img.dataset.path;
    document.getElementById('aiImagePreview').src = img.src;
    document.getElementById('aiImagePreviewWrap').style.display = 'block';
    document.getElementById('aiImageHint').style.display = 'none';
    if (!document.getElementById('fieldTitle').value && img.dataset.title) {
        document.getElementById('fieldTitle').value = img.dataset.title;
    }
    if (!document.getElementById('fieldAltText').value && img.dataset.title) {
        document.getElementById('fieldAltText').value = img.dataset.title;
    }
    aiImageModalOverlay.style.display = 'none';
});

/* ---------------- Write with AI: title, description, alt text, keywords ---------------- */
const AI_TEXT_URL = 'ajax-bulk-ai';
document.getElementById('waGenerateBtn').addEventListener('click', async () => {
    const topic = document.getElementById('waTopic').value.trim();
    if (!topic) { alert('Please enter a topic, keyword or link first.'); return; }

    const withTags = document.getElementById('waTagStyle').value === 'with_tags';
    const destLink = document.getElementById('fieldLink').value.trim();
    const customPrompt = document.getElementById('waCustomPrompt').value.trim();

    const progress = document.getElementById('waProgress');
    progress.textContent = 'Writing…';
    const btn = document.getElementById('waGenerateBtn');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('keywords', JSON.stringify([topic]));
    fd.append('with_tags', withTags ? '1' : '0');
    fd.append('dest_link', destLink);
    fd.append('custom_prompt', customPrompt);

    let result;
    try {
        const res = await fetch(AI_TEXT_URL, { method: 'POST', body: fd });
        result = await res.json();
    } catch (err) {
        result = { ok: false, error: 'Network error.' };
    }

    btn.disabled = false;

    if (result.ok && result.items && result.items.length) {
        const item = result.items[0];
        if (item.title) document.getElementById('fieldTitle').value = item.title;
        if (item.description) document.getElementById('fieldDescription').value = item.description;
        if (item.alt_text) document.getElementById('fieldAltText').value = item.alt_text;
        if (item.keywords) document.getElementById('fieldKeywords').value = item.keywords;
        progress.textContent = '✓ Written by AI — feel free to edit anything above.';
    } else {
        progress.textContent = '✗ ' + (result.error || 'Failed to write with AI.');
        if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(result.error);
    }
});

/* ---------------- Publish Now vs Schedule Pin ---------------- */
const fieldAction = document.getElementById('fieldAction');
const fieldPublishAt = document.getElementById('fieldPublishAt');
const scheduleSubmitBtn = document.getElementById('scheduleSubmitBtn');
const publishNowBtn = document.getElementById('publishNowBtn');

scheduleSubmitBtn.addEventListener('click', function () {
    fieldAction.value = 'schedule';
    fieldPublishAt.required = true;
});
publishNowBtn.addEventListener('click', function () {
    fieldAction.value = 'publish';
    fieldPublishAt.required = false; // publishing immediately — no date/time needed
});

/* ---------------- Require either an uploaded file or an AI image before submit ---------------- */
document.getElementById('singlePinForm').addEventListener('submit', function (e) {
    const usingAiTab = imageTabAi.style.display !== 'none';
    if (usingAiTab) {
        if (!aiImagePathField.value) {
            e.preventDefault();
            alert('Please create an AI image first (or switch to Upload Image).');
        }
    } else {
        if (!imageFileInput.files || !imageFileInput.files.length) {
            e.preventDefault();
            alert('Please choose an image to upload (or switch to Create with AI).');
        }
    }
});
</script>

<?php endif; ?>
<script src="../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/category-picker.js') ?: time() ?>"></script>
<script src="../assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/../assets/js/template-picker.js') ?: time() ?>"></script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>