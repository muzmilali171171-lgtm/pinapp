<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_imagecreator_settings($pdo);

/* ===================== AJAX: generate ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');

    $remaining = free_tool_attempts_remaining($pdo, $settings['daily_limit'], 'image_creator', true);
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve reached today\'s free image limit. Please come back tomorrow, or sign up free for more.', 'limit_reached' => true]);
        exit;
    }

    $prompt = trim($_POST['prompt'] ?? '');
    $sizeKey = in_array($_POST['size'] ?? '', ['2:3', '9:16', '1:2.1', '1:1'], true) ? $_POST['size'] : '1:1';

    $result = free_tool_generate_plain_image($pdo, $prompt, $sizeKey);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'image_creator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['daily_limit'], 'image_creator', true);
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['daily_limit'], 'image_creator', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Free AI Image Creator: Text to Image | ' . SITE_BRAND,
    'description' => 'Create unique AI images from a text prompt for free. Pick a style and size for Pinterest pins, blog posts or social media and download in seconds.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/ai-image-creater/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['AI Image Creator', 'free-tools/ai-image-creater/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free AI Image Creator</h1>
        <p class="ft-sub">Describe an image, pick a size, and generate it with AI.</p>
    </div>

    <div class="ft-attempts" id="ftAttempts">
        <?php if ($remainingAttempts > 0): ?>
            <?= (int)$remainingAttempts ?> free image<?= $remainingAttempts === 1 ? '' : 's' ?> left today
        <?php else: ?>
            You've used today's free images — <a href="../../auth/register">sign up free</a> for more
        <?php endif; ?>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div class="form-row"><label>Prompt</label>
                <textarea id="icPrompt" rows="4" placeholder="e.g. A cozy cabin in a snowy forest at sunset, warm lighting, photorealistic"></textarea>
            </div>
            <div class="form-row"><label>Size</label>
                <select id="icSize">
                    <option value="1:1" selected>1000 × 1000 px (1:1)</option>
                    <option value="2:3">1000 × 1500 px (2:3)</option>
                    <option value="9:16">1080 × 1920 px (9:16)</option>
                    <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                </select>
            </div>
            <button type="button" id="icGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Image</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="icEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🖼️</div>
                <div class="ft-empty-title">No Image Generated</div>
                <div class="ft-empty-sub">Describe what you want to see and generate to view it here.</div>
            </div>
            <div id="icLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Generating Your Image</div>
                <div class="ft-loading-sub">Usually under a minute.</div>
            </div>
            <div id="icResult" style="display:none;text-align:center;">
                <img id="icResultImg" style="max-width:100%;border-radius:10px;border:1px solid var(--border);">
                <div style="margin-top:16px;">
                    <a id="icDownload" class="btn-primary" download>Download Image</a>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want more free images every day?</h2>
        <p>Sign up free for a higher daily limit, saved image history, and the rest of our Pinterest tools — pin design, scheduling and more, all in one place.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Sign Up Free →</a>
    </div>

    <div class="ft-faq" style="max-width:760px;">
        <h2 style="text-align:left;">About This AI Image Creator</h2>
        <p>This free AI Image Creator turns any text description into an image in seconds. Describe a scene, style,
        subject, or mood — the more detail you give, the closer the result matches what you had in mind — pick the
        aspect ratio you need, and generate. It's useful for blog headers, social media graphics, mood boards, or
        just exploring an idea visually before committing to a design. No account is required to try it, and every
        image is yours to download and use.</p>
    </div>

    <div class="ft-faq">
        <h2>FAQ</h2>
        <details><summary>Is this AI Image Creator free?</summary><p>Yes — every visitor gets a number of free images per day with no account required.</p></details>
        <details><summary>What size images can I create?</summary><p>Square (1:1), portrait (2:3 and 1:2.1), and tall (9:16) — the common sizes used for social graphics and Pinterest pins.</p></details>
        <details><summary>Does my daily limit reset?</summary><p>Yes, your free image count resets every day.</p></details>
        <details><summary>Can I use these images commercially?</summary><p>You're free to use images you generate here. If you need higher volume or saved history, sign up for a free account.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('icGenerateBtn').addEventListener('click', async () => {
    const prompt = document.getElementById('icPrompt').value.trim();
    if (!prompt) { alert('Please enter a prompt.'); return; }

    document.getElementById('icEmptyState').style.display = 'none';
    document.getElementById('icResult').style.display = 'none';
    document.getElementById('icLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('prompt', prompt);
    body.set('size', document.getElementById('icSize').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('icLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('icEmptyState').style.display = '';
            alert(data.error || 'Could not generate an image.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used today\'s free images — <a href="../../auth/register">sign up free</a> for more';
            return;
        }
        document.getElementById('icResultImg').src = '../../' + data.image_path;
        document.getElementById('icDownload').href = '../../' + data.image_path;
        document.getElementById('icResult').style.display = '';
        document.getElementById('ftAttempts').textContent = data.remaining > 0
            ? data.remaining + ' free image' + (data.remaining === 1 ? '' : 's') + ' left today'
            : '';
    } catch (e) {
        document.getElementById('icLoadingState').style.display = 'none';
        document.getElementById('icEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
