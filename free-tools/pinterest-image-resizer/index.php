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
<title>Free Pinterest Image Resizer — Perfect Pin Sizes In Seconds | <?= e(APP_NAME) ?></title>
<meta name="description" content="Resize any image to the perfect Pinterest pin dimensions free, right in your browser. Choose the standard 2:3 size or any Pinterest ratio, crop to fit, and download instantly.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Image Resizer</h1>
        <p class="ft-sub">Resize any image to the perfect Pinterest pin dimensions — right in your browser, no upload required.</p>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div id="irDropzone" style="border:2px dashed var(--border);border-radius:10px;padding:24px 16px;text-align:center;cursor:pointer;margin-bottom:14px;">
                <div id="irDropzoneText"><div style="font-size:28px;margin-bottom:6px;">🖼️</div>Click or drag an image here</div>
            </div>
            <input type="file" id="irFileInput" accept="image/*" style="display:none;">
            <div class="form-row"><label>Pinterest Size</label>
                <select id="irSize">
                    <option value="1000x1500" selected>1000 × 1500 px (2:3 — Standard Pin)</option>
                    <option value="1080x1920">1080 × 1920 px (9:16 — Tall Pin)</option>
                    <option value="1000x2100">1000 × 2100 px (1:2.1 — Extra Tall)</option>
                    <option value="1000x1000">1000 × 1000 px (1:1 — Square)</option>
                    <option value="600x900">600 × 900 px (2:3 — Small/Web)</option>
                </select>
            </div>
            <div class="form-row"><label>Fit Mode</label>
                <select id="irFit">
                    <option value="cover">Fill &amp; Crop (cover — no empty space)</option>
                    <option value="contain">Fit Whole Image (contain — may add white bars)</option>
                </select>
            </div>
            <button type="button" id="irResizeBtn" class="btn-primary ft-generate-btn" disabled>Resize Image</button>
        </div>

        <div class="ft-panel ft-panel-right" style="text-align:center;">
            <div id="irEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📐</div>
                <div class="ft-empty-title">No Image Yet</div>
                <div class="ft-empty-sub">Upload an image, pick a size, and resize to see the result here.</div>
            </div>
            <div id="irResultWrap" style="display:none;">
                <canvas id="irCanvas" style="max-width:100%;border-radius:10px;border:1px solid var(--border);"></canvas>
                <div style="margin-top:14px;">
                    <a id="irDownload" href="#" download="pinterest-pin.jpg" class="btn-primary">Download JPG</a>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to design the whole pin, not just resize it?</h2>
        <p>Sign up free and let AI pick a template, add your title, and design a complete pin — sized correctly from the start.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Getting Pinterest Image Sizing Right</h2>
        <p>Pinterest is unusually specific about the shapes it favors, compared to a platform like Instagram where square used to dominate or a blog where featured images tend to be wide and short. Pinterest's feed is built around a masonry-style vertical layout, and tall, portrait-oriented images are what that layout is designed to showcase — a wide, short image gets shrunk down to a sliver in the feed, while a proper tall pin gets a full, prominent column of space. Sizing correctly isn't a cosmetic nicety here; it directly affects how much room your pin actually gets on screen.</p>
        <p>The de facto standard is a 2:3 aspect ratio, most commonly published at 1000×1500 pixels. That ratio consistently displays well across Pinterest's desktop and mobile feeds without excessive cropping or shrinking, which is why it's the safe, reliable default when you're not sure what else to use. Taller ratios — 9:16 or the more extreme 1:2.1 — can grab slightly more vertical space in the feed and work well for step-by-step or infographic-style pins with a lot of visual content stacked vertically, but they're a deliberate choice rather than a universal default.</p>
        <p>The difference between "fill and crop" and "fit the whole image" resizing matters more than it might seem. Cropping to fill the target dimensions (what's often called "cover" mode) guarantees no empty space or awkward white bars, but it necessarily cuts off some of the original image's edges — fine for a photo with room to spare around the subject, risky for one where something important sits right at the edge of the frame. Fitting the whole image in (contain mode) preserves every pixel of the original but can leave blank space on the sides or top/bottom if the original aspect ratio doesn't match the target exactly. Neither mode is universally correct; the right choice depends on whether your source image has safe margins to crop into.</p>
        <p>It's worth resizing toward Pinterest's actual recommended dimensions rather than any size that happens to look fine on your own screen. An image that's technically too small gets upscaled by Pinterest (or by browsers displaying it), which introduces visible blur and softness compared to a properly sized original. An image that's excessively larger than needed wastes load time without adding any visible quality, since Pinterest displays it at a fixed feed-card size regardless. Matching your source image to the actual target dimensions avoids both problems at once.</p>
        <p>This tool resizes and crops entirely in your browser using the canvas element — nothing is uploaded anywhere — so you can quickly reshape any image into Pinterest's standard sizes and download it ready to pin.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What's the best Pinterest pin size?</summary><p>1000×1500 pixels (a 2:3 aspect ratio) is the widely recommended standard — it displays reliably across Pinterest's desktop and mobile feeds without excessive shrinking or cropping.</p></details>
        <details><summary>Should I use a taller ratio like 9:16?</summary><p>It can work well for step-by-step, infographic, or list-style pins with a lot of vertical content, and it grabs slightly more feed space. For most single-image pins, the standard 2:3 ratio remains the safer, more broadly reliable default.</p></details>
        <details><summary>What's the difference between "fill and crop" and "fit the whole image"?</summary><p>Fill and crop resizes to completely cover the target dimensions, cropping any excess — no empty space, but some edges of the original may be cut off. Fit the whole image keeps every pixel visible but can leave blank space on the sides if the aspect ratios don't match exactly.</p></details>
        <details><summary>Will resizing make my image blurry?</summary><p>Shrinking a larger image down generally stays sharp. Stretching a smaller image up to a larger target size is what introduces visible blur — for the cleanest result, start with a source image at least as large as your target Pinterest dimensions.</p></details>
        <details><summary>Does this tool upload my image to a server?</summary><p>No — resizing happens entirely in your browser using the HTML canvas element. Your image is never uploaded or stored anywhere.</p></details>
        <details><summary>What file format does the download use?</summary><p>JPG, which keeps file sizes reasonable for fast-loading pins while maintaining strong visual quality for photos and most pin designs.</p></details>
        <details><summary>Can I resize multiple images at once?</summary><p>This tool handles one image at a time for a clean, focused workflow — upload, resize, download, then repeat for your next image.</p></details>
        <details><summary>Is this Pinterest Image Resizer free?</summary><p>Yes — completely free, unlimited, with no account required, since it runs entirely in your browser.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
let irImage = null;
const dropzone = document.getElementById('irDropzone');
const fileInput = document.getElementById('irFileInput');
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
        const img = new Image();
        img.onload = () => {
            irImage = img;
            document.getElementById('irDropzoneText').innerHTML = '<div style="font-size:28px;margin-bottom:6px;">✓</div>' + file.name;
            document.getElementById('irResizeBtn').disabled = false;
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

document.getElementById('irResizeBtn').addEventListener('click', () => {
    if (!irImage) return;
    const [w, h] = document.getElementById('irSize').value.split('x').map(Number);
    const fit = document.getElementById('irFit').value;
    const canvas = document.getElementById('irCanvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);

    const srcRatio = irImage.width / irImage.height;
    const dstRatio = w / h;
    let sx, sy, sw, sh, dx, dy, dw, dh;

    if (fit === 'cover') {
        if (srcRatio > dstRatio) { sh = irImage.height; sw = sh * dstRatio; sx = (irImage.width - sw) / 2; sy = 0; }
        else { sw = irImage.width; sh = sw / dstRatio; sx = 0; sy = (irImage.height - sh) / 2; }
        dx = 0; dy = 0; dw = w; dh = h;
        ctx.drawImage(irImage, sx, sy, sw, sh, dx, dy, dw, dh);
    } else {
        if (srcRatio > dstRatio) { dw = w; dh = w / srcRatio; dx = 0; dy = (h - dh) / 2; }
        else { dh = h; dw = h * srcRatio; dy = 0; dx = (w - dw) / 2; }
        ctx.drawImage(irImage, 0, 0, irImage.width, irImage.height, dx, dy, dw, dh);
    }

    document.getElementById('irEmptyState').style.display = 'none';
    document.getElementById('irResultWrap').style.display = '';
    document.getElementById('irDownload').href = canvas.toDataURL('image/jpeg', 0.92);
});
</script>

</body>
</html>
