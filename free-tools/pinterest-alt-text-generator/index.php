<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'alt_text_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    if (empty($_FILES['image']['tmp_name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Please upload an image.']);
        exit;
    }
    $mimeType = mime_content_type($_FILES['image']['tmp_name']);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Please upload a JPG, PNG, or WEBP image.']);
        exit;
    }
    if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'Please upload an image under 8MB.']);
        exit;
    }
    $imageBinary = file_get_contents($_FILES['image']['tmp_name']);

    $result = free_tool_generate_alt_text($pdo, $imageBinary, $mimeType, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'alt_text_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'alt_text_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'alt_text_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Free Pinterest Alt Text Generator (AI) | ' . SITE_BRAND,
    'description' => 'Upload a pin image and get accessible, keyword-rich Pinterest alt text in seconds. AI describes what is in the picture so more people can find it.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/pinterest-alt-text-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Pinterest Alt Text Generator', 'free-tools/pinterest-alt-text-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Alt Text Generator</h1>
        <p class="ft-sub">Create SEO-optimized alt text for your Pinterest pins to improve accessibility and search visibility.</p>
    </div>

    <div class="ft-attempts" id="ftAttempts">
        <?php if ($remainingAttempts > 0): ?>
            <?= (int)$remainingAttempts ?> free generation<?= $remainingAttempts === 1 ? '' : 's' ?> left in this session
        <?php else: ?>
            You've used your free generations — <a href="../../auth/register">sign up free</a> to keep going
        <?php endif; ?>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div id="atDropzone" style="border:2px dashed var(--border);border-radius:10px;padding:32px 16px;text-align:center;cursor:pointer;">
                <div id="atDropzoneText">
                    <div style="font-size:32px;margin-bottom:10px;">🖼️</div>
                    Drag &amp; drop an image here, or click to select one
                </div>
                <img id="atPreview" style="max-width:100%;max-height:240px;border-radius:8px;display:none;">
            </div>
            <input type="file" id="atFileInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <button type="button" id="atGenerateBtn" class="btn-primary ft-generate-btn" style="margin-top:14px;" disabled>✨ Generate Alt Text</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="atEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📝</div>
                <div class="ft-empty-title">No Alt Text Yet</div>
                <div class="ft-empty-sub">Upload an image and generate to see its alt text here.</div>
            </div>
            <div id="atLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Reading Your Image</div>
            </div>
            <div id="atResult" style="display:none;">
                <div class="form-row">
                    <label>Alt Text <span class="muted" id="atCharCount"></span></label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <textarea id="atResultText" readonly rows="3" style="flex:1;"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-copy="atResultText">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want alt text written automatically for every pin?</h2>
        <p>Sign up free and every pin you design or schedule gets AI-written alt text automatically — no extra step required.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Why Pinterest Alt Text Matters More Than It Looks Like It Does</h2>
        <p>Alt text was originally an accessibility feature — a short written description that screen readers speak aloud for people who can't see an image — and it's still worth writing for exactly that reason. A Pinterest pin with no alt text is effectively invisible to a visually impaired user browsing with a screen reader, no matter how good the image itself is. But on Pinterest specifically, alt text has taken on a second job: it's one more piece of text the platform's search and recommendation systems read to understand what's actually in your pin, alongside the title and description.</p>
        <p>That dual purpose changes how alt text should be written compared to, say, a random product photo on an e-commerce site. Good Pinterest alt text describes what's literally visible in the image — the subject, the setting, the action, sometimes the color or style — in a natural sentence, the way you'd describe the picture to someone on the phone who can't see it. "Woman kneading bread dough on a wooden countertop" tells both a screen reader and Pinterest's algorithm something concrete and true. A vague alt text like "delicious homemade recipe" describes a feeling, not an image, and gives neither a person nor an algorithm anything to work with.</p>
        <p>Keyword-stuffing alt text — cramming in five related search terms instead of an actual description — technically fills the field but works against both goals at once. It reads as nonsense to a screen reader, and search engines (including Pinterest's) are generally good at recognizing unnatural, list-like text and discounting it compared to a genuine description. The sweet spot is a single accurate sentence, ideally under about 125 characters, that would still make sense read aloud with no image in front of you — with a relevant keyword woven in naturally only where it actually belongs in that description.</p>
        <p>Alt text and your pin description aren't the same thing and shouldn't be copy-pasted into each other. Your description has room to sell the click — what the reader will learn, why they should care, a call-to-action. Alt text has one job: accurately describe what's in the frame. A pin can (and often should) have a punchier, more persuasive description alongside a plainer, purely descriptive alt text — they're doing different work even though they sit right next to each other on the same pin.</p>
        <p>Manually writing accurate, natural alt text for every single pin is exactly the kind of small, repetitive task that's easy to skip when you're publishing a lot of pins — which is usually when it gets skipped entirely, on the pins that could benefit from it most. This tool reads your uploaded image directly and writes a natural, accurate, appropriately short description automatically, so accessibility and that extra bit of Pinterest SEO stop depending on remembering to do it by hand.</p>
        <p>It's worth building alt text into your actual publishing habit rather than treating it as an optional extra you'll add later. In practice, "later" rarely comes — a backlog of fifty un-described pins is a much less appealing task than writing one alt text at the moment you're already looking at the image and know exactly what's in it. Since this tool takes seconds per image, the lowest-friction approach is generating alt text right when you upload or design a pin, before it ever gets scheduled or published, so accessibility and Pinterest SEO both benefit without adding a separate step to remember down the line.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What is Pinterest alt text used for?</summary><p>Two things: it's read aloud by screen readers for visually impaired users browsing Pinterest, and it's one more signal Pinterest's search and recommendation systems use to understand what's actually in your pin image.</p></details>
        <details><summary>How long should Pinterest alt text be?</summary><p>Pinterest allows a generous character limit, but the accessibility best practice most screen-reader and SEO guidance agrees on is to keep alt text to roughly under 125 characters — long enough for one accurate, natural sentence, short enough to stay easy to listen to.</p></details>
        <details><summary>Should I stuff my alt text with keywords?</summary><p>No. Keyword-stuffed alt text reads as nonsense to screen readers and tends to be discounted by search algorithms that recognize unnatural, list-like text. One accurate descriptive sentence with a keyword woven in naturally performs better than a string of disconnected search terms.</p></details>
        <details><summary>Is alt text the same as a pin description?</summary><p>No — they do different jobs. Alt text should accurately describe what's literally visible in the image. A pin description has more room to persuade, explain context, and include a call-to-action. Copy-pasting one into the other usually serves neither purpose well.</p></details>
        <details><summary>Does good alt text actually help my pin get found?</summary><p>It's one signal among several, not a silver bullet — your title, description, and overall pin quality still carry the most weight. But an accurate, keyword-natural alt text gives Pinterest one more genuine data point about your image's content, and it's effectively free to add since this tool generates it in seconds.</p></details>
        <details><summary>What image formats does this tool accept?</summary><p>JPG, PNG, and WEBP, up to 8MB. Those cover the vast majority of images people save from their site or design tools before pinning.</p></details>
        <details><summary>Will the alt text be 100% accurate every time?</summary><p>AI image description is very good but not infallible — always give the generated alt text a quick read before publishing to make sure it matches your image, the same way you'd proofread any AI-assisted writing.</p></details>
        <details><summary>Do I need alt text if my pin title and description already describe the image?</summary><p>Yes — a title and description are written to persuade and hook a sighted reader, while alt text is read aloud, word for word, in place of the image itself for someone using a screen reader. They serve different audiences and different purposes, so skipping alt text because the description "already covers it" still leaves a gap for accessibility.</p></details>
        <details><summary>Is this Pinterest Alt Text Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
let atFile = null;
const dropzone = document.getElementById('atDropzone');
const fileInput = document.getElementById('atFileInput');

dropzone.addEventListener('click', () => fileInput.click());
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.style.borderColor = 'var(--red)'; });
dropzone.addEventListener('dragleave', () => { dropzone.style.borderColor = 'var(--border)'; });
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.style.borderColor = 'var(--border)';
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => { if (fileInput.files.length) handleFile(fileInput.files[0]); });

function handleFile(file) {
    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) { alert('Please choose a JPG, PNG, or WEBP image.'); return; }
    atFile = file;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('atPreview');
        img.src = e.target.result;
        img.style.display = '';
        document.getElementById('atDropzoneText').style.display = 'none';
    };
    reader.readAsDataURL(file);
    document.getElementById('atGenerateBtn').disabled = false;
}

document.getElementById('atGenerateBtn').addEventListener('click', async () => {
    if (!atFile) { alert('Please upload an image first.'); return; }
    document.getElementById('atEmptyState').style.display = 'none';
    document.getElementById('atResult').style.display = 'none';
    document.getElementById('atLoadingState').style.display = '';

    const fd = new FormData();
    fd.append('action', 'generate');
    fd.append('image', atFile);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body: fd });
        const data = await res.json();
        document.getElementById('atLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('atEmptyState').style.display = '';
            alert(data.error || 'Could not generate alt text.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('atResultText').value = data.alt_text;
        document.getElementById('atCharCount').textContent = '(' + data.alt_text.length + '/125)';
        document.getElementById('atResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('atLoadingState').style.display = 'none';
        document.getElementById('atEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});

document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', function () {
        const el = document.getElementById(this.dataset.copy);
        navigator.clipboard.writeText(el.value).then(() => {
            const original = this.textContent;
            this.textContent = '✓';
            setTimeout(() => { this.textContent = original; }, 1500);
        });
    });
});
</script>

</body>
</html>
