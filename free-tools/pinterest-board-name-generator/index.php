<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);
$toneOptions = ['Neutral', 'Funny', 'Professional', 'Informal', 'Positive', 'Inspirational'];
$languageOptions = ['English', 'Spanish', 'French', 'German', 'Italian'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'board_name_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $topic = trim($_POST['topic'] ?? '');
    $tone = in_array($_POST['tone'] ?? '', $toneOptions, true) ? $_POST['tone'] : 'Neutral';
    $language = in_array($_POST['language'] ?? '', $languageOptions, true) ? $_POST['language'] : 'English';

    $result = free_tool_generate_board_names($pdo, $topic, $tone, $language, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'board_name_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'board_name_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'board_name_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free AI Pinterest Board Name Generator | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate catchy, SEO-friendly Pinterest board names free with AI. Enter your board topic, pick a tone and language, and get 8 ready-to-use name ideas.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free AI Pinterest Board Name Generator</h1>
        <p class="ft-sub">Create catchy and engaging names for your Pinterest boards to attract more followers.</p>
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
            <div class="form-row"><label>Board Topic</label>
                <input type="text" id="bnTopic" placeholder="Enter your board topic or theme">
            </div>
            <div class="form-row"><label>Tone Of Voice</label>
                <select id="bnTone"><?php foreach ($toneOptions as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row"><label>Language</label>
                <select id="bnLanguage"><?php foreach ($languageOptions as $l): ?><option value="<?= e($l) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
            </div>
            <button type="button" id="bnGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Board Names</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="bnEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🗂️</div>
                <div class="ft-empty-title">No Names Yet</div>
                <div class="ft-empty-sub">Enter a board topic and generate to see name ideas here.</div>
            </div>
            <div id="bnLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Naming Your Board</div>
            </div>
            <div id="bnResult" style="display:none;">
                <div id="bnNameList" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to organize your whole Pinterest strategy?</h2>
        <p>Sign up free to design pins, write copy, and schedule everything to the right boards — all from one dashboard.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">How to Name Pinterest Boards People Actually Find</h2>
        <p>A Pinterest board name does more work than it looks like it does. It's not just a folder label for your own pins — it's a searchable piece of text that Pinterest's algorithm indexes right alongside your pin titles and descriptions. A board called "Board Name Generator Bedroom Storage" surfaces for narrower, more specific searches than a board simply called "Bedroom Ideas," and that specificity tends to pull in exactly the people who are already looking for what's on that board, rather than a broad audience that scrolls past.</p>
        <p>Pinterest board names are generally kept to around 50 characters, and names that run longer can get cut off in some display views — so the keyword that matters most needs to sit in the first few words, not buried at the end of a longer, cleverer phrase. This is the same principle that governs Pinterest titles and descriptions: front-load what you want to be found for, because truncation happens more often than most people expect.</p>
        <p>There's a real tension between being clever and being findable. A board named "Yum Yum Goodness" might feel more personal, but it tells Pinterest's search algorithm nothing about what's actually on it — no ingredient, no cuisine, no meal type. A board named "Easy Family Dinners" or "30-Minute Vegan Meals" gives up a little cuteness in exchange for showing up when someone actually searches those terms. For a personal, private-feeling board where discovery doesn't matter, creativity can lead. For a board you want strangers to find, clarity and keywords should lead instead.</p>
        <p>The best Pinterest board names tend to name three things at once, even in a short phrase: the topic, a qualifier that narrows it, and sometimes an audience or context. "Recipes" is too broad to compete against millions of other boards; "Quick Vegan Dinner Recipes" narrows the field dramatically while still reading naturally to a human scanning your profile. That narrowing is exactly what a board name generator is built to shortcut — instead of brainstorming five or six phrasings yourself, you get a spread of options that balance broad appeal against a specific, searchable angle, so you can pick the one that fits your actual content instead of settling for the first name that came to mind.</p>
        <p>Tone and language matter here too, the same way they do for a bio or a pin description. A board aimed at a professional audience — interior designers, small business owners — usually reads better with a straightforward, keyword-forward name, while a personal lifestyle or hobby board has more room for a playful or inspirational tone without hurting discoverability much. This generator lets you set both, so the names you get back already sound like something you'd actually use rather than something you'd need to heavily edit.</p>
        <p>Once you've settled on a name, it's worth checking Pinterest's own search bar for that phrase before committing — typing your candidate name into Pinterest search shows you what's already ranking for it and whether the phrasing matches what real searchers are typing, which is the fastest gut-check for whether a name will actually pull traffic.</p>
        <p>It also helps to think about how your board names read as a set, not just individually. A profile where every board is named with the same narrow formula — "X Ideas," "X Ideas," "X Ideas" — reads as repetitive to a visitor scanning your profile, even if each name is individually searchable. Varying the structure slightly across boards (some starting with the topic, some with a qualifier, some with a light call like "for beginners" or "on a budget") keeps a profile feeling curated rather than templated, while every name still carries its own relevant keyword.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What is the Pinterest board name character limit?</summary><p>Pinterest board names are generally capped around 50 characters, and longer names can get truncated in some display views. Keep your most important keyword in the first few words so it's never the part that gets cut off.</p></details>
        <details><summary>Do board names actually affect Pinterest SEO?</summary><p>Yes. Pinterest indexes board names for search the same way it indexes pin titles and descriptions. A specific, keyword-relevant board name can help that board (and the pins on it) surface for more targeted searches than a vague or purely creative name would.</p></details>
        <details><summary>Should I prioritize being clever or being clear?</summary><p>For a public, discovery-focused board, clarity wins — a specific, keyword-forward name consistently outperforms a cute but vague one in search. For a private or purely personal board where nobody else needs to find it, creativity can take priority since discoverability doesn't matter as much.</p></details>
        <details><summary>Can I rename a Pinterest board later without losing my pins?</summary><p>Yes — renaming a board is instant and doesn't affect the pins already saved to it. The board's URL updates to match the new name, but your saved pins, followers, and board history stay intact.</p></details>
        <details><summary>How many boards can I have on Pinterest?</summary><p>Pinterest doesn't publish a hard cap on the number of boards a profile can create, though very high board counts are uncommon. Most active profiles work with somewhere between a handful and a few dozen well-organized boards rather than hundreds of narrow ones.</p></details>
        <details><summary>Should every board name include a keyword?</summary><p>For any board you want strangers to discover through search, yes — at least one clear, relevant keyword. Purely personal or inside-joke board names are fine for boards you don't expect outside traffic to, but they work against you on boards meant to grow your following.</p></details>
        <details><summary>Does the tone I pick actually change the board names generated?</summary><p>Yes — a professional tone produces more straightforward, keyword-first names, while a funny or inspirational tone produces more playful, personality-driven options. Both can include the same underlying keywords; the phrasing and feel around them shift.</p></details>
        <details><summary>Is this Pinterest Board Name Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('bnGenerateBtn').addEventListener('click', async () => {
    const topic = document.getElementById('bnTopic').value.trim();
    if (!topic) { alert('Please enter a board topic or theme.'); return; }
    document.getElementById('bnEmptyState').style.display = 'none';
    document.getElementById('bnResult').style.display = 'none';
    document.getElementById('bnLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('topic', topic);
    body.set('tone', document.getElementById('bnTone').value);
    body.set('language', document.getElementById('bnLanguage').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('bnLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('bnEmptyState').style.display = '';
            alert(data.error || 'Could not generate board names.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('bnNameList').innerHTML = data.names.map(n =>
            `<div style="display:flex;justify-content:space-between;align-items:center;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
                <span>${n}</span>
                <button type="button" class="btn-secondary btn-small" onclick="navigator.clipboard.writeText('${n.replace(/'/g, "\\'")}'); this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200);">Copy</button>
            </div>`).join('');
        document.getElementById('bnResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('bnLoadingState').style.display = 'none';
        document.getElementById('bnEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
