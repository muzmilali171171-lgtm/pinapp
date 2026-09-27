<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Pinterest Pin Preview: See It Before Posting | ' . SITE_BRAND,
    'description' => 'Preview how your pin looks in the Pinterest feed on desktop and mobile before you post. Check the title, image crop and description for free.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/pinterest-pin-preview/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Pinterest Pin Preview', 'free-tools/pinterest-pin-preview/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Pin Preview</h1>
        <p class="ft-sub">See exactly how your pin will look in the Pinterest feed before you post it.</p>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div id="ppDropzone" style="border:2px dashed var(--border);border-radius:10px;padding:24px 16px;text-align:center;cursor:pointer;margin-bottom:14px;">
                <div id="ppDropzoneText"><div style="font-size:28px;margin-bottom:6px;">🖼️</div>Click or drag an image here</div>
            </div>
            <input type="file" id="ppFileInput" accept="image/*" style="display:none;">
            <div class="form-row"><label>Pin Title <span class="muted" id="ppTitleCount"></span></label>
                <input type="text" id="ppTitle" maxlength="100" placeholder="Your pin title">
            </div>
            <div class="form-row"><label>Pin Description <span class="muted" id="ppDescCount"></span></label>
                <textarea id="ppDesc" rows="3" maxlength="500" placeholder="Your pin description"></textarea>
            </div>
            <div class="form-row"><label>Website / Account Name</label>
                <input type="text" id="ppSite" placeholder="e.g. yoursite.com" value="yoursite.com">
            </div>
            <div class="form-row"><label>Preview Width</label>
                <select id="ppView">
                    <option value="236">Feed Card (Desktop, ~236px)</option>
                    <option value="300">Feed Card (Wide, ~300px)</option>
                    <option value="164">Mobile Feed (~164px)</option>
                </select>
            </div>
        </div>

        <div class="ft-panel ft-panel-right" style="display:flex;justify-content:center;align-items:flex-start;">
            <div id="ppCard" style="width:236px;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.15);font-family:inherit;">
                <div id="ppImgWrap" style="background:var(--light);min-height:200px;display:flex;align-items:center;justify-content:center;color:var(--gray);font-size:13px;position:relative;">
                    No image yet
                </div>
                <div style="padding:10px 4px;">
                    <div id="ppTitlePreview" style="font-weight:700;font-size:14px;line-height:1.3;color:#111;margin-bottom:4px;">Your pin title</div>
                    <div id="ppSitePreview" style="font-size:12px;color:var(--gray);">yoursite.com</div>
                </div>
            </div>
        </div>
    </div>

    <p class="muted" style="text-align:center;max-width:600px;margin:16px auto 0;font-size:13px;">Pinterest's feed shows your image and title prominently; the description only appears once someone clicks into the pin. This preview mirrors that — type a description above to see its truncation, but note it renders on the pin's own page, not the card.</p>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to design the whole pin for you?</h2>
        <p>Sign up free and let AI pick the template, write the copy, and design a pin ready to preview and post.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Why Previewing a Pin Before Posting Actually Matters</h2>
        <p>A pin looks completely different inside your design tool than it does sitting in a crowded Pinterest feed next to a dozen other pins competing for the same half-second of attention. Colors that felt bold on a full screen can wash out next to brighter neighbors; text that read clearly at full size can shrink into an unreadable smear at feed-card size; a title that seemed complete can get sliced off mid-word once Pinterest's own truncation kicks in. None of that is visible until you see the pin roughly the size and context it'll actually be viewed in — which is exactly the gap a preview tool closes before you've already published.</p>
        <p>Pinterest's feed card is a genuinely small, dense piece of real estate. The image does almost all of the persuasive work at that size — it's what stops a thumb mid-scroll — while the title underneath gets a brief glance at best. A design built around the assumption that someone will read three lines of small text on a feed card is designed for a context that doesn't really exist; most of that reading only happens after someone has already decided, from the image and headline alone, that the pin is worth a second look.</p>
        <p>That's also why the pin description barely matters at the card-in-feed stage, even though it matters enormously once someone clicks through. Pinterest doesn't show your description on the feed card at all — it appears on the pin's own detail page, after the click has already happened. A brilliant description attached to a forgettable image and title never gets read, because nobody clicked to see it. Previewing helps make that distinction concrete: the image and title are what earn the click; the description is what happens after.</p>
        <p>Small details that are easy to miss at full design size become obvious at preview size — a subtitle that's technically legible but visually cramped, a color combination with too little contrast against Pinterest's white feed background, a title that wraps awkwardly onto a third line instead of two clean ones. Catching these before publishing costs a few seconds; catching them after a pin has already been live and getting impressions costs whatever engagement it missed in the meantime.</p>
        <p>This preview is intentionally simple — your actual image, title, and site name, rendered at roughly the size Pinterest itself displays a feed card — so you can catch the gap between "looks good in the editor" and "looks good in the feed" before it costs you a click.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Does Pinterest show my pin description in the feed?</summary><p>No — only the image and title appear on the feed card. The description only shows once someone clicks through to the pin's own page, which is why the image and title carry almost all of the weight in getting that click in the first place.</p></details>
        <details><summary>What size should my pin image actually be?</summary><p>Pinterest recommends a 2:3 aspect ratio (like 1000×1500px) as the standard, though 9:16 and other taller ratios also work well. Whatever size you use, this preview shows how it'll actually appear scaled down to feed-card size.</p></details>
        <details><summary>Why does my title look cut off in the preview?</summary><p>Pinterest's feed only shows roughly the first 40 characters of a title before truncating with an ellipsis. If your title gets cut off in this preview, it'll get cut off the same way on Pinterest — move your key phrase earlier in the title to fix it.</p></details>
        <details><summary>Does this tool upload or store my image anywhere?</summary><p>No — the image preview happens entirely in your browser using JavaScript's FileReader. Nothing is uploaded to a server or saved.</p></details>
        <details><summary>Should I design differently for mobile vs. desktop Pinterest?</summary><p>The core design principles are the same, but mobile feed cards are narrower, so text and details need to stay legible at an even smaller size. Checking the mobile-width preview option here is a quick way to sanity-check that before publishing.</p></details>
        <details><summary>Can I preview a pin without uploading an image?</summary><p>You can preview the title and site name placement without an image, but since the image is the dominant visual element on a real pin, uploading your actual design gives a far more useful preview of how the whole card will look together.</p></details>
        <details><summary>Is this Pinterest Pin Preview tool free?</summary><p>Yes — completely free, unlimited, with no account required, since it runs entirely in your browser.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
const dropzone = document.getElementById('ppDropzone');
const fileInput = document.getElementById('ppFileInput');
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
    if (!file.type.match(/^image\//)) { alert('Please choose an image file.'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('ppImgWrap').innerHTML = `<img src="${e.target.result}" style="width:100%;display:block;">`;
    };
    reader.readAsDataURL(file);
}

function updatePreview() {
    const title = document.getElementById('ppTitle').value || 'Your pin title';
    const site = document.getElementById('ppSite').value || 'yoursite.com';
    document.getElementById('ppTitlePreview').textContent = title.length > 40 ? title.slice(0, 40) + '…' : title;
    document.getElementById('ppSitePreview').textContent = site;
    document.getElementById('ppTitleCount').textContent = '(' + document.getElementById('ppTitle').value.length + '/100)';
    document.getElementById('ppDescCount').textContent = '(' + document.getElementById('ppDesc').value.length + '/500)';
    document.getElementById('ppCard').style.width = document.getElementById('ppView').value + 'px';
}
['ppTitle', 'ppDesc', 'ppSite', 'ppView'].forEach(id => document.getElementById(id).addEventListener('input', updatePreview));
document.getElementById('ppView').addEventListener('change', updatePreview);
updatePreview();
</script>

</body>
</html>
