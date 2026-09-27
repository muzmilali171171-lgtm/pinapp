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
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_title_desc_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $product = trim($_POST['product'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $details = trim($_POST['details'] ?? '');

    $result = free_tool_generate_etsy_title_description($pdo, $product, $keywords, $details, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_title_desc_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_title_desc_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_title_desc_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Etsy Title & Description Generator (AI) | ' . SITE_BRAND,
    'description' => 'Write keyword-rich Etsy listing titles and descriptions that convert. Describe your product and AI drafts copy ready to paste into Etsy. Free to use.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/etsy-title-description-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Etsy Title & Description Generator', 'free-tools/etsy-title-description-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Title &amp; Description Generator</h1>
        <p class="ft-sub">Write SEO-optimized product titles and descriptions that convert browsers into buyers.</p>
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
            <div class="form-row"><label>What Are You Selling?</label>
                <textarea id="tdProduct" rows="3" placeholder="e.g. Hand-thrown ceramic mug, matte glaze, holds 12oz"></textarea>
            </div>
            <div class="form-row"><label>Target Keywords <span class="muted">(optional)</span></label>
                <input type="text" id="tdKeywords" placeholder="e.g. handmade ceramic mug, pottery coffee cup">
            </div>
            <div class="form-row"><label>Important Details <span class="muted">(materials, size, colors, etc.)</span></label>
                <textarea id="tdDetails" rows="3" placeholder="e.g. Stoneware clay, dishwasher and microwave safe, 4in tall, available in sage green and cream"></textarea>
            </div>
            <button type="button" id="tdGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Listing</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="tdEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📝</div>
                <div class="ft-empty-title">Nothing Generated Yet</div>
                <div class="ft-empty-sub">Describe your product and generate to see your listing copy here.</div>
            </div>
            <div id="tdLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Writing Your Listing</div>
            </div>
            <div id="tdResult" style="display:none;">
                <div class="form-row">
                    <label>Title <span class="muted" id="tdTitleCount"></span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="tdResultTitle" readonly style="flex:1;">
                        <button type="button" class="btn-secondary btn-small" data-copy="tdResultTitle">Copy</button>
                    </div>
                </div>
                <div class="form-row">
                    <label>Description</label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <textarea id="tdResultDesc" readonly rows="8" style="flex:1;"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-copy="tdResultDesc">Copy</button>
                    </div>
                </div>
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
        <h2 style="text-align:left;">Writing Etsy Listings That Rank And Actually Convert</h2>
        <p>An Etsy title has to do two competing jobs at once, and most listings lean too hard toward one at the expense of the other. It needs to feed Etsy's search algorithm enough relevant keywords to surface in the right searches, and it needs to read as a real, understandable product name to the human who actually sees it in results. A title built entirely from a comma-separated keyword dump technically hits the SEO checkbox but reads as spam to a browsing shopper. A title written purely for a human, with no thought toward what they'd actually search, may look nice but never surfaces in the first place.</p>
        <p>Etsy gives titles up to 140 characters, and using close to that full budget is generally worth doing — every unused character is a keyword opportunity left on the table. But the placement inside that budget matters more than the total length: the words at the very start of the title carry more weight in Etsy's search algorithm than words buried near the end, so the single most important keyword phrase for that listing belongs in the opening few words, not saved for later in a longer, more "natural-sounding" sentence.</p>
        <p>Descriptions work under different rules. There's no meaningful character cap forcing brevity the way there is with a title, but that doesn't mean longer is automatically better — a description exists to answer the questions a buyer actually has before purchasing: what is this, what's it made of, what size is it, how will it be shipped, and why should I trust this particular seller over another listing that looks similar. Front-loading the main keyword and the core value proposition into the first sentence or two matters because Etsy (and Google, when a listing gets indexed there too) weighs the opening of a description more heavily, and because many buyers decide whether to keep reading based on just those first couple of lines.</p>
        <p>Specificity is what actually separates a converting description from a forgettable one. "Beautiful handmade mug" tells a buyer nothing they couldn't guess from the photo. "Hand-thrown stoneware mug in a matte sage glaze, holds 12oz, dishwasher and microwave safe" answers three or four real purchase-decision questions in one sentence, using language a shopper would naturally search for in the first place. The generic version wastes space; the specific version does SEO and conversion work simultaneously.</p>
        <p>A description that closes without a clear next step also leaves value on the table. After the practical details, a short call-to-action — pointing toward variations, a related item, or simply inviting a question — gives an already-interested reader a concrete next action instead of ending on a plain product fact and leaving the decision entirely up to them to initiate.</p>
        <p>None of this replaces personalizing the final copy before it goes live. Generated title and description drafts are a strong, keyword-aware starting point built from exactly what you provided — but weaving in your shop's specific voice, any policies worth mentioning (processing time, customization options, care instructions), and a final proofread against your actual product photos is what turns a good draft into a listing that's unmistakably yours.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How long can an Etsy listing title be?</summary><p>Up to 140 characters. It's generally worth using most of that space for relevant keywords, but the opening few words matter most — Etsy's search algorithm weighs the start of a title more heavily than the end.</p></details>
        <details><summary>Where should my main keyword go in the title?</summary><p>As close to the very beginning as it can naturally fit. Words at the start of an Etsy title carry more search weight than words later on, so leading with your most important, most-searched phrase gives the listing its best shot at ranking for it.</p></details>
        <details><summary>Should my description be long or short?</summary><p>Long enough to actually answer a buyer's real questions — what it is, what it's made of, size, care, shipping — without padding. A description packed with specific, useful details tends to outperform both an overly short one and a needlessly long one stuffed with filler.</p></details>
        <details><summary>Should I put keywords in my description too, or just the title?</summary><p>Both — your title carries the most weight, but a keyword-relevant, naturally written description reinforces the same signals and gives Etsy's search more context about the listing. Front-load your main keyword into the first sentence for the strongest effect.</p></details>
        <details><summary>What details should I always include in a description?</summary><p>Materials, size or dimensions, color or variation options, care instructions if relevant, and anything about customization or processing time. These are the questions most buyers are actually trying to answer before they commit to a purchase.</p></details>
        <details><summary>Should I edit the generated title and description before publishing?</summary><p>Yes — treat the output as a strong, keyword-aware first draft. Add your shop's specific voice, any policies worth mentioning, and double-check every detail matches your actual product and photos before it goes live.</p></details>
        <details><summary>Can I use the same title and description structure for multiple similar listings?</summary><p>The structure can repeat, but vary the actual wording and keyword emphasis between similar listings so each one has its own distinct chance to rank, rather than several near-identical listings competing against each other for the same searches.</p></details>
        <details><summary>Should I write different titles for very similar listings?</summary><p>Yes — even for near-identical product variations, giving each listing its own distinct title (leading with a different specific detail or angle) lets each one rank for slightly different searches, rather than several similar listings all competing for the exact same query.</p></details>
        <details><summary>Is this Etsy Title &amp; Description Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('tdGenerateBtn').addEventListener('click', async () => {
    const product = document.getElementById('tdProduct').value.trim();
    if (!product) { alert('Please describe what you\'re selling.'); return; }
    document.getElementById('tdEmptyState').style.display = 'none';
    document.getElementById('tdResult').style.display = 'none';
    document.getElementById('tdLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('product', product);
    body.set('keywords', document.getElementById('tdKeywords').value.trim());
    body.set('details', document.getElementById('tdDetails').value.trim());

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('tdLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('tdEmptyState').style.display = '';
            alert(data.error || 'Could not generate listing copy.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('tdResultTitle').value = data.title;
        document.getElementById('tdResultDesc').value = data.description;
        document.getElementById('tdTitleCount').textContent = '(' + data.title.length + '/140)';
        document.getElementById('tdResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('tdLoadingState').style.display = 'none';
        document.getElementById('tdEmptyState').style.display = '';
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
