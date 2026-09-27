<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_etsy_settings($pdo);
$categories = ['Jewelry', 'Art Prints', 'Stickers', 'Digital Downloads', 'Home Decor', 'Handmade Crafts', 'Vintage Items', 'Wedding Supplies', 'Pet Accessories', 'Baby Items', 'Clothing', 'Personalized Gifts'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_shop_name_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $category = trim($_POST['category'] ?? '');

    $result = free_tool_generate_etsy_shop_names($pdo, $category, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_shop_name_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_shop_name_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_shop_name_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Etsy Shop Name Generator | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate unique, SEO-friendly Etsy shop names free with AI. Pick your category or describe your products and get 8 memorable name ideas ready to check on Etsy.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Shop Name Generator</h1>
        <p class="ft-sub">Generate unique, SEO-friendly Etsy shop names that help customers find your shop.</p>
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
            <div class="form-row"><label>What Will Your Store Sell?</label>
                <input type="text" id="snCategory" placeholder="e.g. Minimalist gold jewelry, boho macrame wall hangings">
            </div>
            <div class="form-row"><label>Or Choose A Popular Category</label>
                <div class="ft-template-grid">
                    <?php foreach ($categories as $c): ?>
                        <label class="ft-template-chip"><input type="radio" name="snCat" value="<?= e($c) ?>"><span><?= e($c) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" id="snGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Store Names</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="snEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🏷️</div>
                <div class="ft-empty-title">No Names Yet</div>
                <div class="ft-empty-sub">Describe your products or pick a category and generate to see name ideas here.</div>
            </div>
            <div id="snLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Naming Your Shop</div>
            </div>
            <div id="snResult" style="display:none;">
                <div id="snNameList" style="display:flex;flex-direction:column;gap:8px;"></div>
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
        <h2 style="text-align:left;">Choosing an Etsy Shop Name You Won't Want to Change</h2>
        <p>An Etsy shop name is one of the few decisions a new seller makes that's genuinely hard to walk back. Etsy allows exactly one free name change after your shop is created — after that, changing it requires contacting Etsy Support directly, and by then you may already have reviews, backlinks, and returning customers all pointing at the original name. That single-change rule is exactly why it's worth spending real time on this decision upfront rather than picking whatever sounds fine in the moment and planning to "fix it later."</p>
        <p>Etsy shop names have their own technical constraints that shape what's actually possible: no spaces, and generally no hyphens, underscores, or special characters — which means a name has to work as one continuous run of letters and numbers, like "FernAndClay" rather than "Fern & Clay." That constraint forces a different kind of creativity than naming a business elsewhere; internal capitalization (CamelCase) becomes the main tool for keeping a longer name readable, since there's no punctuation to lean on for separation.</p>
        <p>Short names consistently outperform long ones for the simple reason that they're easier to remember, easier to say out loud, and easier to type correctly from memory. A name under roughly 20 characters tends to survive all three of those tests; much beyond that, and a customer trying to find your shop again by typing the name into Etsy search starts introducing typos, which is exactly the kind of friction that loses a sale to "I couldn't find that shop again."</p>
        <p>There's a real trade-off between a keyword-descriptive name and a purely brandable one, and it's worth choosing deliberately rather than by accident. A name like "BohoMacrameCo" tells a browser instantly what the shop sells and can pick up a small amount of extra relevance in Etsy's search. A name like "WillowAndReed" reads as a more distinctive, ownable brand but requires the shop's actual listings to carry all the descriptive/keyword weight instead. Neither approach is wrong — a shop planning to expand into many product categories over time often benefits from the more open brand-style name, while a shop staying tightly focused on one niche can lean harder into a descriptive one.</p>
        <p>Before locking in any name, it's worth checking more than just Etsy's own username availability. A name that's free on Etsy but already taken as a matching domain or social handle on Instagram and Pinterest creates a fragmented brand from day one — buyers who find you on one platform may not be able to find the matching account elsewhere. Checking availability across at least Etsy, your preferred social platforms, and a matching .com domain before committing saves a much harder rebrand later, once a name has actually started building recognition.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Can I change my Etsy shop name later?</summary><p>Yes, once for free through your Shop Manager settings. After that first change, any further changes require contacting Etsy Support directly — which is why it's worth choosing carefully the first time rather than planning to adjust it repeatedly.</p></details>
        <details><summary>What characters can an Etsy shop name include?</summary><p>Letters and numbers only — no spaces, and generally no hyphens, underscores, or other symbols. Multi-word names need to run together as one word, often using internal capitalization (like "FernAndClay") to stay readable.</p></details>
        <details><summary>How long should an Etsy shop name be?</summary><p>Shorter names — generally under about 20 characters — tend to work best. They're easier to remember, say out loud, and type correctly from memory, all of which matter if a customer wants to find your shop again later.</p></details>
        <details><summary>Should my shop name describe what I sell, or just be a brand name?</summary><p>Both approaches work. A descriptive name (like BohoMacrameCo) gives an instant clue about your products and a small SEO boost. A brand-style name (like WillowAndReed) is more distinctive and flexible if you plan to expand into other product categories later. Pick based on how focused your niche will stay.</p></details>
        <details><summary>How do I check if an Etsy shop name is available?</summary><p>Search for it directly on Etsy, and also check it as a social media handle and domain name if brand consistency matters to you. A name free on Etsy but taken everywhere else can create a fragmented, harder-to-find brand.</p></details>
        <details><summary>Can I use my personal name as my Etsy shop name?</summary><p>Yes — this works especially well for artistic, handmade, or personal-brand-driven shops where the maker's identity is part of the appeal, rather than the product category alone.</p></details>
        <details><summary>What should I avoid in an Etsy shop name?</summary><p>Trademarked terms, names confusingly similar to existing shops, and anything overly generic that blends into the category rather than standing out. Numbers and unusual symbol substitutions (like using "0" for "O") also tend to make a name harder to remember and type correctly.</p></details>
        <details><summary>Should I pick a name that could feel limiting if my shop grows?</summary><p>Worth thinking about upfront — a name tightly built around one specific product ("OnlyMacrame") can feel restrictive if you later expand into related categories. A slightly broader, still-relevant name often ages better than a hyper-specific one, though a focused name is perfectly fine if you're confident about staying in that lane.</p></details>
        <details><summary>Is this Etsy Shop Name Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.querySelectorAll('input[name="snCat"]').forEach(r => {
    r.addEventListener('change', () => { document.getElementById('snCategory').value = r.value; });
});

document.getElementById('snGenerateBtn').addEventListener('click', async () => {
    const category = document.getElementById('snCategory').value.trim();
    if (!category) { alert('Please describe what your store will sell, or pick a category.'); return; }
    document.getElementById('snEmptyState').style.display = 'none';
    document.getElementById('snResult').style.display = 'none';
    document.getElementById('snLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('category', category);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('snLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('snEmptyState').style.display = '';
            alert(data.error || 'Could not generate store names.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('snNameList').innerHTML = data.names.map(n =>
            `<div style="display:flex;justify-content:space-between;align-items:center;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
                <span>${n}</span>
                <button type="button" class="btn-secondary btn-small" onclick="navigator.clipboard.writeText('${n.replace(/'/g, "\\'")}'); this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200);">Copy</button>
            </div>`).join('');
        document.getElementById('snResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('snLoadingState').style.display = 'none';
        document.getElementById('snEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
