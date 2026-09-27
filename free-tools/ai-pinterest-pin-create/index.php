<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_pincreate_settings($pdo);

/* ===================== AJAX: generate one pin ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');

    $remaining = free_tool_attempts_remaining($pdo, $settings['max_pins'], 'pin_create');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free pin creations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $useCta = !empty($_POST['use_cta']);
    $ctaText = $useCta ? trim($_POST['cta_text'] ?? '') : '';
    if ($useCta && $ctaText === '') $ctaText = auto_pick_cta($title);
    $sizeKey = in_array($_POST['size'] ?? '', ['2:3', '9:16', '1:2.1', '1:1'], true) ? $_POST['size'] : '2:3';
    $customPrompt = !empty($_POST['use_custom_prompt']) ? trim($_POST['custom_prompt'] ?? '') : '';

    $result = free_tool_generate_single_pin($pdo, $title, $website, $ctaText, $sizeKey, $customPrompt, max(0, (int)($_POST['image_category_id'] ?? 0)));
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'pin_create');
        $_SESSION['free_tool_pincreate_pending'] = ['clean_path' => $result['clean_path'], 'title' => $result['title']];
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_pins'], 'pin_create');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_pins'], 'pin_create');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'AI Pinterest Pin Creator: Pin From a Title | ' . SITE_BRAND,
    'description' => 'Turn any title or keyword into a fully designed Pinterest pin with AI. Choose a template, size and colours, then download your pin free.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/ai-pinterest-pin-create/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['AI Pinterest Pin Creator', 'free-tools/ai-pinterest-pin-create/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free AI Pinterest Pin Create</h1>
        <p class="ft-sub">Enter a blog title or keyword — AI designs the pin for you.</p>
    </div>

    <div class="ft-attempts" id="ftAttempts">
        <?php if ($remainingAttempts > 0): ?>
            <?= (int)$remainingAttempts ?> free pin<?= $remainingAttempts === 1 ? '' : 's' ?> left in this session
        <?php else: ?>
            You've used your free pins — <a href="../../auth/register">sign up free</a> to keep going
        <?php endif; ?>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div class="form-row"><label>Blog Title Or Keyword</label>
                <input type="text" id="pcTitle" placeholder="e.g. 15 Easy Weeknight Dinners">
            </div>
            <div class="form-row"><label>Website <span class="muted">(optional — shown on the pin)</span></label>
                <input type="text" id="pcWebsite" placeholder="example.com">
            </div>
            <div class="form-row"><label>Select Category <span class="muted">(optional — the image matches this niche)</span></label>
                <input type="hidden" id="pcImageCategory" data-catpick>
            </div>
            <div class="form-row"><label>Pin Size</label>
                <select id="pcSize">
                    <option value="2:3" selected>1000 × 1500 px (2:3)</option>
                    <option value="9:16">1080 × 1920 px (9:16)</option>
                    <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                    <option value="1:1">1000 × 1000 px (1:1)</option>
                </select>
            </div>
            <div class="ft-switch-row" style="margin-bottom:10px;">
                <div class="ft-switch-label">Add CTA Button</div>
                <label class="ft-switch"><input type="checkbox" id="pcUseCta"><span></span></label>
            </div>
            <div class="form-row" id="pcCtaWrap" style="display:none;">
                <label>CTA Text</label>
                <input type="text" id="pcCtaText" placeholder="e.g. Read More">
            </div>
            <div class="ft-switch-row" style="margin-bottom:10px;">
                <div class="ft-switch-label">Switch to Custom Prompt</div>
                <label class="ft-switch"><input type="checkbox" id="pcUseCustomPrompt"><span></span></label>
            </div>
            <div class="form-row" id="pcCustomPromptWrap" style="display:none;">
                <label>Custom Image Prompt</label>
                <textarea id="pcCustomPrompt" rows="3" placeholder="Describe the background image you want AI to create..."></textarea>
            </div>
            <button type="button" id="pcGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Pin</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="pcEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📌</div>
                <div class="ft-empty-title">No Pin Generated</div>
                <div class="ft-empty-sub">Enter a title and generate to see your pin here.</div>
            </div>
            <div id="pcLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Designing Your Pin</div>
                <div class="ft-loading-sub">AI is creating the image and laying out the design — usually under a minute.</div>
            </div>
            <div id="pcResult" style="display:none;text-align:center;">
                <img id="pcResultImg" style="max-width:100%;border-radius:10px;border:1px solid var(--border);">
                <div style="display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap;">
                    <a id="pcDownloadWatermarked" class="btn-secondary" download>Download (with watermark)</a>
                    <a id="pcDownloadClean" href="../../auth/register?from=pincreate" class="btn-primary">Download Without Watermark — Create Free Account</a>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want to create pins on autopilot?</h2>
        <p>Sign up free to remove the watermark, generate unlimited pins, and schedule them straight to Pinterest — all from one dashboard.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:760px;">
        <h2 style="text-align:left;">How AI Pinterest Pin Create Works</h2>
        <p>AI Pinterest Pin Create turns a single blog title or keyword into a ready-to-post Pinterest pin. Type in
        your title, optionally add your website and a call-to-action button, and our AI writes a matching background
        image and lays out the text for you — no design software, templates, or Canva skills required. Pick from
        four standard Pinterest sizes, or switch to a custom prompt if you want full control over the background
        image's look. Every free pin ships with a small watermark; creating a free account removes it instantly and
        unlocks unlimited pin creation plus scheduling.</p>
    </div>

    <div class="ft-faq">
        <h2>FAQ</h2>
        <details><summary>Is AI Pinterest Pin Create free?</summary><p>Yes — every visitor gets a set number of free pin creations with no account required. Sign up free for unlimited, watermark-free pins.</p></details>
        <details><summary>Why does my pin have a watermark?</summary><p>Free, no-login pins include a small watermark. Creating a free account removes it and gives you the original file.</p></details>
        <details><summary>Can I use my own background image instead of AI?</summary><p>Not on this tool — it always generates a fresh AI background. If you'd rather use your own site's images, try our Pinterest Pin Maker tool instead.</p></details>
        <details><summary>What's the "Custom Prompt" option for?</summary><p>By default, AI writes the background image prompt from your title. Custom Prompt lets you describe exactly what the background image should look like.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script src="../../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/category-picker.js') ?: time() ?>"></script>
<script>
document.getElementById('pcUseCta').addEventListener('change', function () {
    document.getElementById('pcCtaWrap').style.display = this.checked ? '' : 'none';
});
document.getElementById('pcUseCustomPrompt').addEventListener('change', function () {
    document.getElementById('pcCustomPromptWrap').style.display = this.checked ? '' : 'none';
});

document.getElementById('pcGenerateBtn').addEventListener('click', async () => {
    const title = document.getElementById('pcTitle').value.trim();
    if (!title) { alert('Please enter a blog title or keyword.'); return; }

    document.getElementById('pcEmptyState').style.display = 'none';
    document.getElementById('pcResult').style.display = 'none';
    document.getElementById('pcLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('title', title);
    body.set('website', document.getElementById('pcWebsite').value.trim());
    body.set('size', document.getElementById('pcSize').value);
    body.set('image_category_id', document.getElementById('pcImageCategory').value);
    if (document.getElementById('pcUseCta').checked) {
        body.set('use_cta', '1');
        body.set('cta_text', document.getElementById('pcCtaText').value.trim());
    }
    if (document.getElementById('pcUseCustomPrompt').checked) {
        body.set('use_custom_prompt', '1');
        body.set('custom_prompt', document.getElementById('pcCustomPrompt').value.trim());
    }

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('pcLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('pcEmptyState').style.display = '';
            alert(data.error || 'Could not generate a pin.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free pins — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('pcResultImg').src = '../../' + data.watermarked_path;
        document.getElementById('pcDownloadWatermarked').href = '../../' + data.watermarked_path;
        document.getElementById('pcResult').style.display = '';
        document.getElementById('ftAttempts').textContent = data.remaining > 0
            ? data.remaining + ' free pin' + (data.remaining === 1 ? '' : 's') + ' left in this session'
            : '';
    } catch (e) {
        document.getElementById('pcLoadingState').style.display = 'none';
        document.getElementById('pcEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
