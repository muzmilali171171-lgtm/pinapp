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
    'title' => 'Free Etsy QR Code Generator | ' . SITE_BRAND,
    'description' => 'Make a free QR code for your Etsy shop, listing or review page. Customise colours, add your logo and download PNG or SVG to print on packaging.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/etsy-qr-code-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Etsy QR Code Generator', 'free-tools/etsy-qr-code-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy QR Code Generator</h1>
        <p class="ft-sub">Create scannable QR codes that send buyers to your Etsy shop, product listings, or coupon links.</p>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div class="form-row"><label>Etsy Link</label>
                <input type="url" id="qrUrl" placeholder="https://www.etsy.com/shop/yourshopname">
                <p class="muted" style="margin-top:6px;">Paste your Etsy listing, shop, or coupon link.</p>
            </div>
            <div class="form-row"><label>Size (px)</label>
                <input type="range" id="qrSize" min="160" max="640" step="20" value="320">
                <span class="muted" id="qrSizeLabel">320px</span>
            </div>
            <div class="form-row"><label>Quiet Zone (margin)</label>
                <input type="range" id="qrMargin" min="0" max="20" step="1" value="4">
                <span class="muted" id="qrMarginLabel">4</span>
            </div>
            <div class="two-col">
                <div class="form-row"><label>Foreground Color</label><input type="color" id="qrFg" value="#111827" style="width:100%;height:40px;"></div>
                <div class="form-row"><label>Background Color</label><input type="color" id="qrBg" value="#ffffff" style="width:100%;height:40px;"></div>
            </div>
            <button type="button" id="qrGenerateBtn" class="btn-primary ft-generate-btn">Generate QR Code</button>
        </div>

        <div class="ft-panel ft-panel-right" style="text-align:center;">
            <div id="qrEmptyState" class="ft-empty">
                <div class="ft-empty-icon">▦</div>
                <div class="ft-empty-title">No QR Code Yet</div>
                <div class="ft-empty-sub">Paste your Etsy link and generate to see the preview here.</div>
            </div>
            <div id="qrCanvasWrap" style="display:none;">
                <div id="qrCanvas" style="display:inline-block;padding:12px;"></div>
                <div style="margin-top:14px;">
                    <a id="qrDownload" href="#" download="etsy-qr-code.png" class="btn-primary">Download PNG</a>
                    <button type="button" id="qrCopyLink" class="btn-secondary">Copy Image Link</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want more traffic than a QR code on a card can bring?</h2>
        <p>Sign up free to turn your Etsy listings into scheduled Pinterest pins that send shoppers back to your shop around the clock.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Where a QR Code Actually Helps an Etsy Shop</h2>
        <p>A QR code solves one specific problem: getting someone from a physical object — a business card, a packaging insert, a market booth sign, a thank-you note tucked inside a shipped order — to your Etsy shop without them having to type a URL by hand. Typing a long Etsy shop URL correctly on a phone is more friction than it sounds like, and every bit of friction between "interested" and "on your shop page" costs you some percentage of people who would have followed through. A scan takes one second and can't be mistyped.</p>
        <p>The most common places sellers actually get value from an Etsy QR code are packaging inserts (pointing repeat or referred customers straight to the shop, or to a specific "shop the collection" page), in-person events like craft fairs and pop-up markets (where a phone camera is faster than reading out a shop name over noise), and printed marketing material — flyers, business cards, thank-you cards — where there's no clickable link at all and a QR code is the only practical way to bridge from paper to a live Etsy page.</p>
        <p>Where a QR code stops being useful is in the mistaken idea that it does any of the discovery or SEO work a keyword-optimized listing does. A QR code doesn't rank in search, doesn't get indexed, and doesn't reach anyone who hasn't already encountered the physical object it's printed on. It's a bridge for someone who's already interested, not a discovery tool — the equivalent of a phone number on a card, not an ad. Pair it with the actual discovery work (tags, titles, and — if you use Pinterest — pins that reach new browsers) rather than expecting it to generate new traffic on its own.</p>
        <p>A few practical details make a real difference in whether a QR code actually scans reliably once it's printed small. Contrast matters more than color choice — a QR code needs a meaningfully different foreground and background so a camera can distinguish the pattern; two similar mid-tones, even if they look fine on screen, can fail to scan in print. The "quiet zone" — the blank margin around the code — isn't decorative; cameras use it to detect where the code starts and ends, and a code cropped too tightly against other design elements can become unreadable even though the pattern itself is intact.</p>
        <p>Size matters more than most people expect, especially for small applications like earring cards or jewelry tags. A QR code that looks perfectly clear at full screen size can become unscannable once it's shrunk to fit a one-inch sticker, because the individual modules (the small black/white squares that make up the pattern) become too fine for a camera to resolve at a distance. As a rough rule, don't shrink a QR code below roughly an inch square for anything meant to be scanned from a normal arm's-length distance — test print a sample and actually scan it with your own phone before committing to a full print run.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What links can I turn into a QR code with this tool?</summary><p>Any Etsy link — your shop homepage, an individual listing, a specific section, or a coupon URL. Anything that opens on Etsy.com will work.</p></details>
        <details><summary>Does the QR code expire?</summary><p>No. It points directly to the URL you entered and works for as long as that URL stays valid. If you need it to point somewhere new, generate a fresh code with the updated link — the old code won't automatically update.</p></details>
        <details><summary>What size should I download for printing?</summary><p>For stickers, packaging inserts, or thank-you cards, somewhere around 320-480px works well. For signs or posters meant to be scanned from further away, go larger — 600px or more, and test-scan a printed sample before a full run.</p></details>
        <details><summary>Can I customize the colors to match my brand?</summary><p>Yes — set your own foreground and background colors. Just keep strong contrast between the two; low-contrast color pairs (like two similar pastel tones) can fail to scan reliably, especially once printed small.</p></details>
        <details><summary>Why does my QR code need a margin around it?</summary><p>That blank border (the "quiet zone") helps a camera's scanner detect where the code pattern starts and stops. Cropping it too tightly, or placing other design elements right up against the code, can make an otherwise valid code unreadable.</p></details>
        <details><summary>Can I put a QR code on jewelry or very small items?</summary><p>You can, but keep the printed size reasonable — shrinking a QR code below roughly an inch square risks making the fine pattern unreadable to a phone camera at normal scanning distance. Always test a physical print before ordering in bulk.</p></details>
        <details><summary>Does a QR code help my Etsy SEO or search ranking?</summary><p>No — a QR code is a bridge for someone who already has the physical object in front of them, not a discovery or search tool. It doesn't get indexed or help new buyers find your shop; it just removes friction for people who are already about to visit.</p></details>
        <details><summary>Do I need special software to scan a QR code?</summary><p>No — every modern smartphone can scan a QR code directly through its built-in camera app, with no separate scanner app required. That universal support is a big part of why QR codes work so well for print-to-digital handoffs.</p></details>
        <details><summary>Is this Etsy QR Code Generator free?</summary><p>Yes, completely free with no account required and no limit on how many you create.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('qrSize').addEventListener('input', function () {
    document.getElementById('qrSizeLabel').textContent = this.value + 'px';
});
document.getElementById('qrMargin').addEventListener('input', function () {
    document.getElementById('qrMarginLabel').textContent = this.value;
});

document.getElementById('qrGenerateBtn').addEventListener('click', () => {
    const url = document.getElementById('qrUrl').value.trim();
    if (!url) { alert('Please paste your Etsy link first.'); return; }
    if (!/^https?:\/\//i.test(url)) { alert('Please include http:// or https:// in your link.'); return; }

    const size = parseInt(document.getElementById('qrSize').value, 10);
    const margin = parseInt(document.getElementById('qrMargin').value, 10);
    const fg = document.getElementById('qrFg').value;
    const bg = document.getElementById('qrBg').value;

    const wrap = document.getElementById('qrCanvas');
    wrap.innerHTML = '';
    wrap.style.padding = margin * 4 + 'px';
    wrap.style.background = bg;

    if (typeof QRCode === 'undefined') {
        alert('QR library failed to load — please check your connection and try again.');
        return;
    }

    new QRCode(wrap, {
        text: url,
        width: size,
        height: size,
        colorDark: fg,
        colorLight: bg,
        correctLevel: QRCode.CorrectLevel.M,
    });

    document.getElementById('qrEmptyState').style.display = 'none';
    document.getElementById('qrCanvasWrap').style.display = '';

    setTimeout(() => {
        const img = wrap.querySelector('img') || wrap.querySelector('canvas');
        if (img) {
            const src = img.tagName === 'CANVAS' ? img.toDataURL('image/png') : img.src;
            document.getElementById('qrDownload').href = src;
            document.getElementById('qrDownload').dataset.src = src;
        }
    }, 150);
});

document.getElementById('qrCopyLink').addEventListener('click', function () {
    const src = document.getElementById('qrDownload').dataset.src;
    if (!src) { alert('Generate a QR code first.'); return; }
    navigator.clipboard.writeText(src).then(() => {
        const original = this.textContent;
        this.textContent = '✓ Copied';
        setTimeout(() => { this.textContent = original; }, 1500);
    });
});
</script>

</body>
</html>
