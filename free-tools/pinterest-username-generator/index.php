<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);
$styleOptions = ['Professional', 'Creative', 'Short & Catchy', 'Keyword-Rich'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'username_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $niche = trim($_POST['niche'] ?? '');
    $style = in_array($_POST['style'] ?? '', $styleOptions, true) ? $_POST['style'] : 'Professional';

    $result = free_tool_generate_usernames($pdo, $niche, $style, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'username_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'username_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'username_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest Username Generator — Unique, SEO-Friendly Handles | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate unique, SEO-friendly Pinterest usernames free with AI. Enter your account topic or niche, pick a style, and get 10 ready-to-use username ideas.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Username Generator</h1>
        <p class="ft-sub">Create unique and SEO-friendly usernames for your Pinterest account.</p>
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
            <div class="form-row"><label>Account Topic / Niche</label>
                <input type="text" id="unNiche" placeholder="e.g., DIY crafts, Travel tips, Healthy recipes">
            </div>
            <div class="form-row"><label>Username Style</label>
                <select id="unStyle"><?php foreach ($styleOptions as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select>
            </div>
            <button type="button" id="unGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Usernames</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="unEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🔤</div>
                <div class="ft-empty-title">No Usernames Yet</div>
                <div class="ft-empty-sub">Enter your niche and generate to see username ideas here.</div>
            </div>
            <div id="unLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Finding Available-Style Handles</div>
            </div>
            <div id="unResult" style="display:none;">
                <div id="unNameList" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Ready to build your Pinterest presence?</h2>
        <p>Sign up free to design pins, write bios and descriptions, and schedule everything to Pinterest — all from one dashboard.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Choosing a Pinterest Username That Works For You</h2>
        <p>Your Pinterest username becomes part of your profile URL, so it's one of the few pieces of your brand identity that's genuinely permanent — most people don't change it once followers, backlinks, and saved pin URLs start pointing at it. That makes the choice worth slowing down for, even though it feels like a small detail compared to designing pins or writing your bio.</p>
        <p>Pinterest usernames are typically limited to somewhere in the range of 3 to 15 characters and can only use letters, numbers, and underscores — no spaces, no hyphens, no other symbols. That tight ceiling forces real trade-offs. A long, fully descriptive phrase like "healthyveganmealprep" simply won't fit, so you end up choosing between shortening it (healthyvegan), abbreviating it (hvmealprep), or picking a distinct brand-style word instead (nourishedbowl). None of these approaches is universally "correct" — it depends on whether you want your username to double as a keyword or to function more like a brand name.</p>
        <p>There's a real difference between a keyword-rich username and a brand-style one, and it's worth picking deliberately rather than by accident. A keyword-forward username (travelonabudget, easyweeknightmeals) can pick up a small amount of extra relevance in Pinterest's search and is instantly self-explanatory to anyone who sees it — useful if you're building a content-first, SEO-driven account. A short, brand-style username (nestandwander, thepantryedit) reads more like a business name, builds better long-term brand recall, and looks more professional on merchandise, email signatures, or cross-platform bios — useful if you're building toward a recognizable, ownable brand rather than pure search traffic.</p>
        <p>Consistency across platforms is worth weighing too. If you already have an Instagram handle, a TikTok handle, or a domain name, matching your Pinterest username to it (or getting as close as the character limit allows) makes you easier to find and remember across the platforms your audience actually splits their time between. A username that's unique to Pinterest and unrelated to anything else you run can work, but it adds one more thing for a follower to remember if they want to find you elsewhere.</p>
        <p>This generator gives you a batch of options built around your niche and a style you choose — professional, creative, short and catchy, or keyword-rich — all within Pinterest's character and character-set rules, so nothing you get back needs editing before you try it. Since Pinterest usernames must be unique platform-wide, treat every suggestion as a starting point: check availability directly on Pinterest before committing, and if your first choice is taken, the rest of the list gives you several fallback options that still fit your niche and style.</p>
        <p>One more thing worth weighing before you commit: a username tied tightly to your current content niche can start to feel restrictive if your account evolves. A creator who starts a board full of budget travel tips under a username like "budgettravel" may find themselves boxed in a year later if their content broadens into general lifestyle or family travel. A slightly more open, brand-style username tends to age better than a hyper-specific one — it's a small trade-off between immediate keyword clarity and long-term flexibility, and worth thinking through before you lock in a handle you'll likely keep for years.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How many characters can a Pinterest username be?</summary><p>Pinterest usernames are generally limited to a short range — roughly 3 to 15 characters — using only letters, numbers, and underscores. No spaces, hyphens, or other symbols are allowed.</p></details>
        <details><summary>Should my Pinterest username include a keyword?</summary><p>It can help a little with search relevance and makes your account instantly self-explanatory, but it's optional. A short, brand-style username (with your niche keywords living in your bio and board names instead) often works just as well and builds stronger brand recall over time.</p></details>
        <details><summary>Should my Pinterest username match my Instagram or TikTok handle?</summary><p>It's a good idea when possible. Matching usernames across platforms makes it easier for people who find you on one platform to recognize and follow you on another. It's not required, but it removes one small point of friction.</p></details>
        <details><summary>Can I change my Pinterest username later?</summary><p>Yes, Pinterest allows you to change your username in account settings. Keep in mind it changes your profile URL too, so any existing backlinks, bookmarks, or shared links pointing at your old username will break — it's best to choose carefully upfront rather than planning to change it often.</p></details>
        <details><summary>What's the difference between the username styles in this generator?</summary><p>"Professional" and "Keyword-Rich" lean toward clear, descriptive, business-appropriate handles. "Creative" and "Short & Catchy" lean toward brand-style names that prioritize memorability and personality over literal description. Pick based on whether you want your username to double as a search keyword or function more like a business name.</p></details>
        <details><summary>Does Pinterest allow numbers or underscores in usernames?</summary><p>Yes, numbers and underscores are allowed, though relying on them (like adding "_123" to a taken name) usually makes a username slightly harder to remember and type. They're best used sparingly, if at all.</p></details>
        <details><summary>What if every good username in my niche is already taken?</summary><p>Try a small, meaningful variation rather than a random number — swap "the" for a niche-specific word, add a short qualifier (co, hq, shop), or shift from a literal description to a brand-style word. Generating a fresh batch with a different style setting often surfaces options you wouldn't have thought of manually.</p></details>
        <details><summary>How do I know if a username is available on Pinterest?</summary><p>Try navigating directly to pinterest.com/yourusername — if a profile loads, it's taken. This generator suggests style- and niche-appropriate options, but availability always needs a final check directly on Pinterest since usernames are unique platform-wide.</p></details>
        <details><summary>Is this Pinterest Username Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('unGenerateBtn').addEventListener('click', async () => {
    const niche = document.getElementById('unNiche').value.trim();
    if (!niche) { alert('Please enter your account topic or niche.'); return; }
    document.getElementById('unEmptyState').style.display = 'none';
    document.getElementById('unResult').style.display = 'none';
    document.getElementById('unLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('niche', niche);
    body.set('style', document.getElementById('unStyle').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('unLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('unEmptyState').style.display = '';
            alert(data.error || 'Could not generate usernames.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('unNameList').innerHTML = data.usernames.map(n =>
            `<div style="display:flex;justify-content:space-between;align-items:center;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
                <span>@${n}</span>
                <button type="button" class="btn-secondary btn-small" onclick="navigator.clipboard.writeText('${n.replace(/'/g, "\\'")}'); this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200);">Copy</button>
            </div>`).join('');
        document.getElementById('unResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('unLoadingState').style.display = 'none';
        document.getElementById('unEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
