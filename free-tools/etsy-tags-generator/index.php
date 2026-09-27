<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_etsy_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_tags_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $product = trim($_POST['product'] ?? '');

    $result = free_tool_generate_etsy_tags($pdo, $product, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_tags_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_tags_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_tags_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Etsy Tags Generator — SEO Tags In Seconds | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate SEO-optimized Etsy listing tags free with AI. Describe your product and get 30 tag ideas to choose your best 13 from, each within Etsy's 20-character limit.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Tags Generator</h1>
        <p class="ft-sub">Generate SEO-optimized tags for your Etsy products to increase visibility and sales.</p>
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
            <div class="form-row"><label>What Is Your Product About?</label>
                <textarea id="tgProduct" rows="4" placeholder="e.g. Handmade sterling silver leaf necklace, nature-inspired, minimalist, gift for her"></textarea>
            </div>
            <button type="button" id="tgGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Tags</button>
            <p class="muted" style="margin-top:10px;font-size:12px;">Etsy allows 13 tags per listing, up to 20 characters each. This generates 30 ideas so you can pick your best 13.</p>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="tgEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🏷️</div>
                <div class="ft-empty-title">No Tags Yet</div>
                <div class="ft-empty-sub">Describe your product and generate to see tag ideas here.</div>
            </div>
            <div id="tgLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Finding Tags</div>
            </div>
            <div id="tgResult" style="display:none;">
                <div id="tgTagList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;"></div>
                <button type="button" id="tgCopyBtn" class="btn-secondary">📋 Copy All 30</button>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want to grow your traffic via Pinterest too?</h2>
        <p>Sign up free to connect your Etsy listings, auto-design on-brand pins, and schedule them straight to Pinterest.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Getting the Most Out of Your 13 Etsy Tags</h2>
        <p>Etsy gives every listing exactly 13 tags, each capped at 20 characters, and how you use those 13 slots is one of the more mechanical but genuinely impactful pieces of Etsy SEO. Unlike your title and description, which buyers actually read, tags exist purely for search — they're invisible on the listing page itself and do one job: telling Etsy's algorithm what search queries this specific listing should be eligible to appear for. Thirteen wasted or redundant tags is thirteen missed chances to match a real search.</p>
        <p>The most common mistake is filling tags with near-duplicates of the title or of each other — "silver necklace," "silver necklaces," "necklace silver" — which burns multiple slots covering essentially one search concept instead of using each tag to reach a genuinely different query. A stronger approach treats each of the 13 tags as its own bet on a different way a buyer might search: some broader category terms, some specific to the materials or style, some built around occasion or recipient ("gift for mom," "bridesmaid gift"), and a few longer, more specific phrases that match exactly how a decided buyer searches.</p>
        <p>Etsy explicitly supports full phrases in a single tag, not just single words — a tag can be "personalized birthstone necklace" as one 20-character-or-under entry, and that's usually a better use of a slot than splitting the same concept across three separate single-word tags. Multi-word phrase tags tend to carry more specific buying intent than single words, which is exactly the kind of match that converts once a shopper actually clicks through.</p>
        <p>It's worth deliberately varying tags across similar listings in a shop rather than copy-pasting the same 13 everywhere. If you sell five variations of a similar product, giving each listing 30-50% unique tags (alongside some shared core tags) means each one can be discovered through a slightly different set of searches, instead of all five competing against each other for exactly the same limited set of queries — which helps neither listing and wastes the shop's overall search coverage.</p>
        <p>Tags aren't a "set once and forget" element either. As trends shift, as you learn which of your existing tags are actually driving clicks (visible in Etsy's own Shop Stats), and as seasons change, revisiting and swapping out underperforming tags keeps a listing's search eligibility current rather than locked to whatever seemed reasonable on the day it was first published — often months or years earlier.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How many tags can I use on an Etsy listing?</summary><p>Up to 13 tags per listing, each up to 20 characters. This tool generates 30 candidate tags so you have room to choose your strongest, most varied 13 rather than being handed exactly 13 with no options.</p></details>
        <details><summary>Should I use single words or full phrases as tags?</summary><p>Full phrases, when they fit the character limit. Etsy explicitly supports multi-word tags, and a phrase like "personalized birthstone necklace" usually captures more specific buying intent — and uses your limited slots more efficiently — than splitting the same idea across several single-word tags.</p></details>
        <details><summary>Should I use the same tags on all my listings?</summary><p>Not exactly the same set. If you sell similar products, vary roughly 30-50% of your tags between listings so each one is discoverable through different searches, rather than every similar listing competing against each other for the identical set of queries.</p></details>
        <details><summary>What's the difference between tags and Etsy's attribute fields?</summary><p>Tags are freeform searchable keywords you choose yourself. Attributes are Etsy's own structured product fields (like color, material, or occasion) that you select from a fixed list. Both influence search, and it's worth filling in relevant attributes in addition to your 13 tags rather than relying on tags alone.</p></details>
        <details><summary>How do I know if my tags are actually working?</summary><p>Check your Etsy Shop Stats for search-term data on each listing — it shows some of the actual queries buyers used to find you. If impressions are healthy but clicks are low, the tags are working; the photo or price is more likely the issue at that point.</p></details>
        <details><summary>Should I avoid broad, high-competition tags entirely?</summary><p>No — a mix works best. A couple of broad tags can still bring meaningful volume even with heavy competition, while several more specific, lower-competition tags give you an easier path to actually ranking. Relying only on one type usually underperforms a mixed strategy.</p></details>
        <details><summary>How often should I update my tags?</summary><p>Whenever you notice a tag underperforming in your Shop Stats, when a season changes, or roughly every few months as a routine check — tags chosen when a listing first went live can drift out of step with current search trends over time.</p></details>
        <details><summary>Do tags matter more or less than my title?</summary><p>Your title generally carries more search weight than any single tag, since it's the most prominent text Etsy associates with the listing. Tags are still valuable for covering additional search angles the title doesn't include — think of them as a supporting layer around your title, not a replacement for a strong one.</p></details>
        <details><summary>Is this Etsy Tags Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
let tgLastTags = [];
document.getElementById('tgGenerateBtn').addEventListener('click', async () => {
    const product = document.getElementById('tgProduct').value.trim();
    if (!product) { alert('Please describe your product.'); return; }
    document.getElementById('tgEmptyState').style.display = 'none';
    document.getElementById('tgResult').style.display = 'none';
    document.getElementById('tgLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('product', product);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('tgLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('tgEmptyState').style.display = '';
            alert(data.error || 'Could not generate tags.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        tgLastTags = data.tags;
        document.getElementById('tgTagList').innerHTML = data.tags.map(t => `<span class="ft-template-chip" style="cursor:pointer;" onclick="navigator.clipboard.writeText('${t.replace(/'/g, "\\'")}'); this.style.borderColor='var(--green)';">${t}</span>`).join('');
        document.getElementById('tgResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('tgLoadingState').style.display = 'none';
        document.getElementById('tgEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});

document.getElementById('tgCopyBtn').addEventListener('click', function () {
    navigator.clipboard.writeText(tgLastTags.join(', ')).then(() => {
        const original = this.textContent;
        this.textContent = '✓ Copied!';
        setTimeout(() => { this.textContent = original; }, 1500);
    });
});
</script>

</body>
</html>
