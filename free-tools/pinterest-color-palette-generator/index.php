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
    'title' => 'Pinterest Color Palette Generator (Free) | ' . SITE_BRAND,
    'description' => 'Create beautiful colour palettes for your pins and brand. Generate, lock and tweak colours, then copy HEX codes for your Pinterest designs. Free tool.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/pinterest-color-palette-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Pinterest Color Palette Generator', 'free-tools/pinterest-color-palette-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Color Palette Generator</h1>
        <p class="ft-sub">Extract a color palette from any image, or generate a fresh one — ready-to-use hex codes for consistent, on-brand pins.</p>
    </div>

    <div class="ft-tone-row" style="justify-content:center;margin-bottom:20px;">
        <button type="button" class="ft-tone-btn active" data-mode="extract">Extract From Image</button>
        <button type="button" class="ft-tone-btn" data-mode="random">Generate Random Palette</button>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div data-mode-panel="extract">
                <div id="cpDropzone" style="border:2px dashed var(--border);border-radius:10px;padding:24px 16px;text-align:center;cursor:pointer;margin-bottom:14px;">
                    <div id="cpDropzoneText"><div style="font-size:28px;margin-bottom:6px;">🎨</div>Click or drag an image here</div>
                    <img id="cpPreview" style="max-width:100%;max-height:180px;border-radius:8px;display:none;margin-top:10px;">
                </div>
                <input type="file" id="cpFileInput" accept="image/*" style="display:none;">
                <div class="form-row"><label>Number Of Colors</label>
                    <input type="number" id="cpCount" value="6" min="3" max="10">
                </div>
                <button type="button" id="cpExtractBtn" class="btn-primary ft-generate-btn" disabled>Extract Palette</button>
            </div>
            <div data-mode-panel="random" style="display:none;">
                <p class="muted">Generate a fresh, harmonious color palette to explore ideas even without a source image.</p>
                <div class="form-row"><label>Style</label>
                    <select id="cpStyle">
                        <option value="pastel">Soft Pastel</option>
                        <option value="bold">Bold &amp; Vibrant</option>
                        <option value="earthy">Earthy &amp; Warm</option>
                        <option value="cool">Cool &amp; Calm</option>
                        <option value="monochrome">Monochrome</option>
                    </select>
                </div>
                <button type="button" id="cpRandomBtn" class="btn-primary ft-generate-btn">Generate Palette</button>
            </div>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="cpEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🎨</div>
                <div class="ft-empty-title">No Palette Yet</div>
                <div class="ft-empty-sub">Upload an image or generate a random palette to see colors here.</div>
            </div>
            <div id="cpResult" style="display:none;">
                <div id="cpSwatches" style="display:flex;flex-wrap:wrap;gap:10px;"></div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to design pins in your brand colors automatically?</h2>
        <p>Sign up free and let AI pick templates and color palettes that match your brand every time.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Why a Consistent Color Palette Matters on Pinterest</h2>
        <p>Pinterest is a genuinely visual-first platform — people scroll fast, and a pin has a fraction of a second to register before a thumb moves on. Color is one of the quickest signals a brain processes in that split second, faster than reading a title or even fully registering a photo's subject. A shop or blog whose pins consistently use the same handful of colors starts to become recognizable at a glance, the same way a familiar brand's packaging is identifiable across a shelf before you've read a single word on it.</p>
        <p>Extracting a palette directly from an existing image — your product photo, your logo, a flat-lay you're proud of — anchors your color choices in something real rather than starting from an arbitrary guess at "colors that go together." The dominant tones already present in a well-shot photo often make a genuinely strong palette on their own; pulling them out explicitly just makes those colors reusable and deliberate across future pins, graphics, and templates instead of only existing inside that one photo.</p>
        <p>A generated, style-based palette serves a different purpose — useful when you're starting from nothing, exploring a new brand direction, or need color inspiration that isn't tied to any specific existing photo. A soft pastel palette signals something different to a browsing eye than a bold, saturated one; earthy tones read as natural and grounded, cool tones read as calm and clean. None of these is objectively better — the right style depends entirely on what feeling your niche and audience actually respond to, which is worth testing rather than assuming.</p>
        <p>Once you have a palette, the highest-value use isn't necessarily using all the colors everywhere at once — it's establishing a consistent relationship between them. A common, reliable pattern is one dominant background or accent color, one or two supporting colors for text or secondary elements, and a single high-contrast color reserved specifically for the parts you most want to draw the eye toward — a call-to-action badge, a headline, a price. Spreading five colors evenly across a design tends to look busier and less intentional than committing to that kind of hierarchy.</p>
        <p>Contrast is worth checking deliberately, not just for text readability (though that matters too) but for whether your palette actually stands out in a Pinterest feed full of other pins. A palette that's technically harmonious but sits entirely in the same narrow mid-tone range can blend into the visual noise around it; adding at least one genuinely higher-contrast color — even used sparingly — often helps a pin register as distinct while scrolling past dozens of others.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How does extracting a palette from an image work?</summary><p>The tool samples the actual pixel colors in your uploaded image and groups similar tones together to identify the most dominant, representative colors — all processed locally in your browser.</p></details>
        <details><summary>Why would I use a generated palette instead of extracting one?</summary><p>A generated palette is useful when you don't have a source image yet, or want fresh color inspiration for a new brand direction rather than colors tied to one specific photo.</p></details>
        <details><summary>How many colors should a good pin palette have?</summary><p>Somewhere around 3-6 colors is typical — enough for a background, text, and one or two accent colors, without spreading attention too thin. More than that tends to look busy rather than deliberate.</p></details>
        <details><summary>Does this tool upload my image anywhere?</summary><p>No — color extraction happens entirely in your browser using the HTML canvas element. Your image is never uploaded or stored on a server.</p></details>
        <details><summary>Can I use this palette for more than just pins?</summary><p>Yes — the same hex codes work anywhere you need consistent brand colors: your website, packaging, social graphics, or Etsy shop banner, not just Pinterest pin designs specifically.</p></details>
        <details><summary>What's the best way to actually use a palette once I have it?</summary><p>Establish a clear hierarchy rather than using every color equally — one dominant color, one or two supporting colors, and a single high-contrast accent reserved for the element you most want noticed, like a call-to-action or headline.</p></details>
        <details><summary>Should my palette match my brand across every platform?</summary><p>Generally yes — visual consistency across Pinterest, your website, and other platforms helps people recognize your brand faster, which compounds over time as they see your content repeatedly in different places.</p></details>
        <details><summary>Is this Pinterest Color Palette Generator free?</summary><p>Yes — completely free, unlimited, with no account required, since it runs entirely in your browser.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.querySelectorAll('[data-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-mode]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const mode = btn.dataset.mode;
        document.querySelector('[data-mode-panel="extract"]').style.display = mode === 'extract' ? '' : 'none';
        document.querySelector('[data-mode-panel="random"]').style.display = mode === 'random' ? '' : 'none';
    });
});

let cpImage = null;
const dropzone = document.getElementById('cpDropzone');
const fileInput = document.getElementById('cpFileInput');
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
            cpImage = img;
            const preview = document.getElementById('cpPreview');
            preview.src = e.target.result;
            preview.style.display = '';
            document.getElementById('cpExtractBtn').disabled = false;
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(x => Math.round(x).toString(16).padStart(2, '0')).join('');
}

function renderSwatches(colors) {
    document.getElementById('cpSwatches').innerHTML = colors.map(hex => `
        <div style="flex:1;min-width:100px;text-align:center;">
            <div style="height:90px;border-radius:8px;background:${hex};border:1px solid var(--border);cursor:pointer;" onclick="navigator.clipboard.writeText('${hex}'); this.title='Copied!';" title="Click to copy"></div>
            <div class="muted" style="font-size:12px;margin-top:6px;font-family:monospace;">${hex}</div>
        </div>`).join('');
    document.getElementById('cpEmptyState').style.display = 'none';
    document.getElementById('cpResult').style.display = '';
}

document.getElementById('cpExtractBtn').addEventListener('click', () => {
    if (!cpImage) return;
    const count = Math.max(3, Math.min(10, parseInt(document.getElementById('cpCount').value, 10) || 6));

    const canvas = document.createElement('canvas');
    const scale = Math.min(1, 150 / Math.max(cpImage.width, cpImage.height));
    canvas.width = Math.max(1, Math.round(cpImage.width * scale));
    canvas.height = Math.max(1, Math.round(cpImage.height * scale));
    const ctx = canvas.getContext('2d');
    ctx.drawImage(cpImage, 0, 0, canvas.width, canvas.height);
    const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

    // Simple bucket quantization: group pixels into coarse RGB buckets, then pick the most frequent buckets.
    const buckets = {};
    for (let i = 0; i < data.length; i += 4) {
        const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
        if (a < 100) continue;
        const key = [Math.round(r / 24), Math.round(g / 24), Math.round(b / 24)].join(',');
        if (!buckets[key]) buckets[key] = { r: 0, g: 0, b: 0, n: 0 };
        buckets[key].r += r; buckets[key].g += g; buckets[key].b += b; buckets[key].n++;
    }
    const sorted = Object.values(buckets).sort((a, b) => b.n - a.n);
    const colors = sorted.slice(0, count).map(b => rgbToHex(b.r / b.n, b.g / b.n, b.b / b.n));
    renderSwatches(colors);
});

const PALETTE_STYLES = {
    pastel: () => Array.from({ length: 6 }, () => hslToHex(Math.random() * 360, 40 + Math.random() * 20, 80 + Math.random() * 10)),
    bold: () => Array.from({ length: 6 }, () => hslToHex(Math.random() * 360, 70 + Math.random() * 25, 45 + Math.random() * 15)),
    earthy: () => Array.from({ length: 6 }, () => hslToHex(20 + Math.random() * 40, 35 + Math.random() * 25, 40 + Math.random() * 25)),
    cool: () => Array.from({ length: 6 }, () => hslToHex(180 + Math.random() * 80, 35 + Math.random() * 30, 45 + Math.random() * 25)),
    monochrome: () => { const h = Math.random() * 360; return Array.from({ length: 6 }, (_, i) => hslToHex(h, 30 + Math.random() * 20, 20 + i * 13)); },
};
function hslToHex(h, s, l) {
    s /= 100; l /= 100;
    const c = (1 - Math.abs(2 * l - 1)) * s, x = c * (1 - Math.abs((h / 60) % 2 - 1)), m = l - c / 2;
    let r = 0, g = 0, b = 0;
    if (h < 60) [r, g, b] = [c, x, 0]; else if (h < 120) [r, g, b] = [x, c, 0]; else if (h < 180) [r, g, b] = [0, c, x];
    else if (h < 240) [r, g, b] = [0, x, c]; else if (h < 300) [r, g, b] = [x, 0, c]; else [r, g, b] = [c, 0, x];
    return rgbToHex((r + m) * 255, (g + m) * 255, (b + m) * 255);
}

document.getElementById('cpRandomBtn').addEventListener('click', () => {
    const style = document.getElementById('cpStyle').value;
    renderSwatches(PALETTE_STYLES[style]());
});
</script>

</body>
</html>
