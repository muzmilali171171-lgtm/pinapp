<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_etsy_settings($pdo);
$toneOptions = ['Professional', 'Friendly', 'Casual'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_bio_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $shopInfo = trim($_POST['shop_info'] ?? '');
    $tone = in_array($_POST['tone'] ?? '', $toneOptions, true) ? $_POST['tone'] : 'Friendly';

    $result = free_tool_generate_etsy_bio($pdo, $shopInfo, $tone, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_bio_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_bio_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_bio_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Free Etsy Shop Bio Generator (AI) | ' . SITE_BRAND,
    'description' => 'Write a warm, keyword-rich Etsy shop bio and About section in seconds. Tell the AI what you sell and pick a tone to get ready-to-paste bios free.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/etsy-bio-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Etsy Bio Generator', 'free-tools/etsy-bio-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Shop Bio Generator</h1>
        <p class="ft-sub">Create authentic and compelling shop bios that build trust and convert visitors into customers.</p>
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
            <div class="form-row"><label>Tell Us About Your Shop</label>
                <textarea id="ebInfo" rows="5" placeholder="What do you sell? What's your creative process? Any unique features or values? e.g. I create handmade polymer clay jewelry inspired by nature, using eco-friendly materials, with a minimalist aesthetic."></textarea>
            </div>
            <div class="form-row"><label>Bio Tone</label>
                <select id="ebTone">
                    <option value="Professional">Professional — Polished and business-focused</option>
                    <option value="Friendly" selected>Friendly — Warm and approachable</option>
                    <option value="Casual">Casual — Relaxed and conversational</option>
                </select>
            </div>
            <button type="button" id="ebGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Bio</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="ebEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🏪</div>
                <div class="ft-empty-title">No Bio Yet</div>
                <div class="ft-empty-sub">Tell us about your shop and generate to see your bio here.</div>
            </div>
            <div id="ebLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Writing Your Bio</div>
            </div>
            <div id="ebResult" style="display:none;">
                <div class="form-row">
                    <label>Bio</label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <textarea id="ebResultText" readonly rows="8" style="flex:1;"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-copy="ebResultText">Copy</button>
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
        <h2 style="text-align:left;">Writing an Etsy Bio That Builds Trust Before The First Message</h2>
        <p>An Etsy shop's "About" section carries more weight than its length suggests, because it's often the first genuinely personal thing a visitor reads after landing on your listings from search or a Pinterest pin. The photos got them there; the bio decides whether they trust a stranger enough to actually hand over payment information. That's a real psychological hurdle on a marketplace built almost entirely on small, independent sellers a buyer has never heard of — the bio is where that stranger starts to feel like a real person running a real, careful business.</p>
        <p>The strongest Etsy bios tend to share a shape, even when the tone varies wildly between shops: a bit of origin story (why this shop exists, what started it), a clear description of what's actually made and how, and something that signals care — materials sourcing, quality control, a value like sustainability or size-inclusivity, whatever's genuinely true of the shop. Buyers researching handmade or vintage goods are actively looking for reasons to trust the seller behind the listing; a bio that answers "who makes this and why should I believe it's good" before the buyer has to ask is doing real conversion work, not just filling space.</p>
        <p>Specificity beats polish here more often than sellers expect. "I've been making jewelry for years" is a claim anyone could make about anything. "Each piece starts as a hand-carved wax model before I cast it in recycled sterling silver" is a claim only someone who actually does that could write, and it reads as more credible precisely because it's concrete. The instinct to keep a bio broad and safe usually produces something forgettable; the instinct to include one or two real, specific details from the actual process usually produces something a buyer remembers.</p>
        <p>Tone is where a lot of the difference between shops actually lives, even when the underlying facts are similar. A professional tone suits a shop positioning itself as premium, established, or B2B-adjacent (wedding suppliers, business gifting). A friendly tone suits the broad middle ground most handmade and craft shops sit in — warm, approachable, still clearly capable. A casual tone works well for shops built around a strong individual personality, where buyers are drawn as much to the maker as the product. None of these is more "correct" than the others; the right one is whichever matches how the shop actually wants to be perceived by the buyer reading it.</p>
        <p>Keywords still matter in a bio, the same way they matter everywhere else on Etsy — a bio mentioning "handmade ceramic mugs" in a natural sentence gives Etsy's search index one more relevant signal about the shop, on top of whatever the individual listings already carry. But a bio that reads like a keyword list rather than a person talking undermines the exact trust it's supposed to build; the keyword density should never be higher than what a real person would naturally say describing their own work to a new customer.</p>
        <p>A bio isn't a write-once document, either. As a shop's product line shifts, as new values or certifications get added, or as the shop simply matures past its earliest description of itself, revisiting the bio keeps it aligned with what's actually true — a quick quarterly glance is enough for most sellers to catch when the "About" section has quietly fallen out of step with the shop it's describing.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What should I include in my Etsy shop bio?</summary><p>Your origin story (what started the shop), a clear description of what you make and how, and something that builds trust — your materials, process, or values. Specific, concrete details read as more credible than broad, generic claims.</p></details>
        <details><summary>How long should an Etsy bio be?</summary><p>A couple of well-crafted short paragraphs is usually enough — long enough to tell your story and build trust, short enough that a visitor actually reads the whole thing rather than skimming past it.</p></details>
        <details><summary>Should I include keywords in my bio?</summary><p>Yes, naturally — mentioning your product category and niche in real sentences gives Etsy's search one more relevant signal. Avoid stacking keywords in a way that stops sounding like a real person describing their own work.</p></details>
        <details><summary>Should I mention my production process?</summary><p>If it's genuinely part of your story, yes — specific process details (materials, techniques, quality checks) are some of the most trust-building content you can include, since they're details only a real maker would know to mention.</p></details>
        <details><summary>What tone should I use for my bio?</summary><p>Match it to how you want your shop perceived: professional for a premium or B2B-leaning shop, friendly for most handmade and craft shops, casual for a shop built around a strong individual personality. None is objectively better — it depends on your brand.</p></details>
        <details><summary>How often should I update my Etsy bio?</summary><p>Whenever your products, process, or values meaningfully change. Many active sellers give it a quick review every few months just to make sure it still accurately reflects the shop.</p></details>
        <details><summary>Can I mention my social media in my bio?</summary><p>Yes — linking to where buyers can see behind-the-scenes content or your broader portfolio can strengthen trust, as long as it's a genuine value-add rather than just a list of every account you have.</p></details>
        <details><summary>What if I don't have a dramatic origin story?</summary><p>Most sellers don't, and that's fine — a bio doesn't need a dramatic hook to work. A clear, honest description of what you make, how, and why you care about doing it well is enough to build trust; forced drama usually reads as less credible than a simple, specific truth.</p></details>
        <details><summary>Is this Etsy Shop Bio Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('ebGenerateBtn').addEventListener('click', async () => {
    const shopInfo = document.getElementById('ebInfo').value.trim();
    if (!shopInfo) { alert('Please tell us about your shop first.'); return; }
    document.getElementById('ebEmptyState').style.display = 'none';
    document.getElementById('ebResult').style.display = 'none';
    document.getElementById('ebLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('shop_info', shopInfo);
    body.set('tone', document.getElementById('ebTone').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('ebLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('ebEmptyState').style.display = '';
            alert(data.error || 'Could not generate a bio.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('ebResultText').value = data.bio;
        document.getElementById('ebResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('ebLoadingState').style.display = 'none';
        document.getElementById('ebEmptyState').style.display = '';
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
