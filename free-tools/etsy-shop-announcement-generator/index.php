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
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_announcement_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $shopName = trim($_POST['shop_name'] ?? '');
    $whatYouSell = trim($_POST['what_you_sell'] ?? '');
    $announcement = trim($_POST['announcement'] ?? '');

    $result = free_tool_generate_etsy_announcement($pdo, $shopName, $whatYouSell, $announcement, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_announcement_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_announcement_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_announcement_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Etsy Shop Announcement Generator | <?= e(APP_NAME) ?></title>
<meta name="description" content="Write SEO-friendly Etsy shop announcements free with AI. Spotlight launches, promos, and shipping updates within Etsy's 500-character limit — three tone variants in one click.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Shop Announcement Generator</h1>
        <p class="ft-sub">Craft SEO-friendly announcements that spotlight launches, promos, and shipping updates — within Etsy's 500-character limit.</p>
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
            <div class="form-row"><label>Shop Name *</label>
                <input type="text" id="eaShopName" placeholder="e.g. Wild Fern Paper Co.">
            </div>
            <div class="form-row"><label>What Do You Sell? *</label>
                <input type="text" id="eaWhatYouSell" placeholder="Describe your hero products, materials, and what makes them special">
            </div>
            <div class="form-row"><label>What Are You Announcing? *</label>
                <textarea id="eaAnnouncement" rows="3" placeholder="Launch details, collection theme, turnaround updates, shipping deadlines, etc."></textarea>
            </div>
            <button type="button" id="eaGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Announcements</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="eaEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📣</div>
                <div class="ft-empty-title">Nothing Generated Yet</div>
                <div class="ft-empty-sub">Fill in the details and generate to see three announcement options here.</div>
            </div>
            <div id="eaLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Writing Your Announcements</div>
            </div>
            <div id="eaResult" style="display:flex;flex-direction:column;gap:12px;display:none;"></div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want to grow your traffic via Pinterest too?</h2>
        <p>Sign up free to connect your Etsy listings, auto-design on-brand pins, and schedule them straight to Pinterest.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Getting More Out of Your Etsy Shop Announcement</h2>
        <p>The announcement banner sitting at the top of an Etsy shop is one of the most under-used pieces of real estate a seller has. It's visible to every visitor before they scroll to a single listing, it's indexed the same way other on-page text is, and — unlike a listing description tied to one product — it's the one place a seller can speak about the shop as a whole: what's new, what's changing, what deserves attention right now. Most shops either leave it blank or let it go stale for months, which means a current, specific announcement is a cheap way to stand out simply by being maintained.</p>
        <p>Etsy caps the announcement at 500 characters, which is tight enough that vague, generic copy wastes the space almost entirely. "Welcome to our shop, thanks for stopping by!" uses up real character budget while telling a visitor nothing they couldn't guess. A specific announcement — what's new this week, what's on sale, when orders currently ship — gives a returning or first-time visitor an actual reason to look further, and gives Etsy's search index a fresher, more relevant piece of text to associate with the shop.</p>
        <p>Good announcement copy tends to lead with the most time-sensitive or exciting fact first, since the character limit means anything buried at the end risks feeling like an afterthought even if it technically fits. A new collection launch, a limited-time discount code, or an important shipping deadline (especially around holidays, when processing times matter more than usual) are the kinds of facts worth opening with — followed by enough context that a visitor understands what to do next, whether that's browsing a new section or ordering before a cutoff date.</p>
        <p>Tone is worth matching deliberately to what's actually being announced, not applied uniformly across every update. A big seasonal launch can carry genuine excitement and energy without feeling forced. A shipping-delay notice reads better calm and reassuring than falsely upbeat — buyers are looking for confidence that their order is handled, not enthusiasm about a delay. A routine restock or small update usually sits best in a straightforward, professional register that doesn't oversell something minor. Generating a few different tone options for the same underlying update, and picking whichever fits the actual news, tends to produce a more natural-feeling banner than forcing one default voice onto everything.</p>
        <p>An announcement is also one of the lowest-effort things to keep current, precisely because it's short. Unlike rewriting a full listing description, updating a 500-character banner takes a minute — which is exactly why it's worth treating as a living piece of copy that changes with the shop's actual status: current for whatever's genuinely new right now, and swapped out again the moment that news is stale, rather than left describing a sale that ended weeks ago.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How long can an Etsy shop announcement be?</summary><p>Etsy caps it at 500 characters. That's tight enough that generic filler wastes real space — specific, current information about what's actually new performs better than a vague welcome message.</p></details>
        <details><summary>Does the shop announcement actually affect SEO?</summary><p>It's indexed on-page text the same as other parts of your shop, so keyword-relevant, current copy in the announcement is one more small signal reinforcing what your shop is about — not a major ranking factor on its own, but not nothing either.</p></details>
        <details><summary>How often should I update my announcement?</summary><p>Whenever you launch something new, run a promotion, or change your processing/shipping times — especially around busy seasons. A stale announcement describing an offer that already ended undercuts the trust it's meant to build.</p></details>
        <details><summary>Should my announcement tone match my whole shop's branding?</summary><p>Generally yes, but it's also fine to shift tone slightly based on what's actually being announced — an exciting launch can carry more energy than a routine shipping-time update, even within the same overall brand voice.</p></details>
        <details><summary>Can I use the announcement for seasonal promotions?</summary><p>Yes — mention the season or holiday directly and keep the copy focused on what's actually available or discounted right now. Just remember to update or remove it once that promotion ends.</p></details>
        <details><summary>What should I lead with in a short 500-character announcement?</summary><p>Your most time-sensitive or important fact first — a launch, a sale, or a shipping deadline — since anything at the very end of a tight character count risks reading as an afterthought even if it technically fits.</p></details>
        <details><summary>Can I edit the generated announcement before publishing it?</summary><p>Yes, treat each generated option as a strong starting draft — add emojis, adjust the phrasing, or tighten it further in Etsy's own editor so it sounds unmistakably like your shop before it goes live.</p></details>
        <details><summary>Can I use the announcement to explain a shipping delay?</summary><p>Yes, and it's one of the more valuable uses — a calm, clear explanation of a delay (with an updated timeframe if you have one) reassures buyers who might otherwise message asking where their order is, saving you time on repeat customer-service questions.</p></details>
        <details><summary>Is this Etsy Shop Announcement Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('eaGenerateBtn').addEventListener('click', async () => {
    const shopName = document.getElementById('eaShopName').value.trim();
    const whatYouSell = document.getElementById('eaWhatYouSell').value.trim();
    const announcement = document.getElementById('eaAnnouncement').value.trim();
    if (!shopName || !whatYouSell || !announcement) { alert('Please fill in your shop name, what you sell, and what you\'re announcing.'); return; }

    document.getElementById('eaEmptyState').style.display = 'none';
    document.getElementById('eaResult').style.display = 'none';
    document.getElementById('eaLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('shop_name', shopName);
    body.set('what_you_sell', whatYouSell);
    body.set('announcement', announcement);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('eaLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('eaEmptyState').style.display = '';
            alert(data.error || 'Could not generate announcements.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('eaResult').innerHTML = data.variants.map((v, i) => `
            <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
                <div class="muted" style="font-size:12px;margin-bottom:6px;">Variant ${i + 1} · ${v.length}/500 characters</div>
                <textarea readonly rows="3" style="width:100%;" id="eaVariant${i}">${v}</textarea>
                <button type="button" class="btn-secondary btn-small" style="margin-top:8px;" onclick="navigator.clipboard.writeText(document.getElementById('eaVariant${i}').value); this.textContent='✓ Copied';setTimeout(()=>this.textContent='Copy',1200);">Copy</button>
            </div>`).join('');
        document.getElementById('eaResult').style.display = 'flex';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('eaLoadingState').style.display = 'none';
        document.getElementById('eaEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
