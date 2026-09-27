<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'keyword_research_tool');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free searches. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }

    $mode = ($_POST['mode'] ?? 'keyword') === 'url' ? 'url' : 'keyword';
    $topic = '';
    if ($mode === 'url') {
        $ctx = free_tool_fetch_url_context(trim($_POST['url'] ?? ''));
        if (!$ctx['ok']) { echo json_encode(['ok' => false, 'error' => $ctx['error']]); exit; }
        $topic = $ctx['title'] . ($ctx['description'] ? ' — ' . $ctx['description'] : '');
    } else {
        $topic = trim($_POST['keyword'] ?? '');
    }

    $result = free_tool_generate_keywords($pdo, $topic, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'keyword_research_tool');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'keyword_research_tool');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'keyword_research_tool');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest Keyword Research Tool | <?= e(APP_NAME) ?></title>
<meta name="description" content="Find related Pinterest keywords and search-phrase ideas free with AI. Search by keyword or analyze a URL and get a ready list of Pinterest-style search phrases with interest and trend estimates.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Keyword Research Tool</h1>
        <p class="ft-sub">Find related keywords and search-phrase ideas so every pin targets what your audience is actually searching for.</p>
    </div>

    <div class="ft-attempts" id="ftAttempts">
        <?php if ($remainingAttempts > 0): ?>
            <?= (int)$remainingAttempts ?> free search<?= $remainingAttempts === 1 ? '' : 'es' ?> left in this session
        <?php else: ?>
            You've used your free searches — <a href="../../auth/register">sign up free</a> to keep going
        <?php endif; ?>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div class="ft-tone-row" style="margin-bottom:14px;">
                <button type="button" class="ft-tone-btn active" data-mode="keyword">Search By Keyword</button>
                <button type="button" class="ft-tone-btn" data-mode="url">Analyze A URL</button>
            </div>
            <div data-mode-panel="keyword">
                <div class="form-row"><label>Enter Keyword</label>
                    <input type="text" id="kwKeyword" placeholder="e.g. healthy recipes">
                </div>
            </div>
            <div data-mode-panel="url" style="display:none;">
                <div class="form-row"><label>URL</label>
                    <input type="url" id="kwUrl" placeholder="https://example.com/blog/post">
                </div>
            </div>
            <button type="button" id="kwGenerateBtn" class="btn-primary ft-generate-btn">🔍 Research Keywords</button>
            <p class="muted" style="margin-top:10px;font-size:12px;">Pinterest doesn't publish a public search-volume API, so Interest and Trend below are AI-estimated relative signals for content planning — not exact Pinterest traffic numbers.</p>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="kwEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🔍</div>
                <div class="ft-empty-title">No Keywords Yet</div>
                <div class="ft-empty-sub">Enter a keyword or URL and research to see related search phrases here.</div>
            </div>
            <div id="kwLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Researching Keywords</div>
            </div>
            <div id="kwResult" style="display:none;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead><tr style="text-align:left;border-bottom:2px solid var(--border);">
                        <th style="padding:8px 6px;">Keyword Phrase</th>
                        <th style="padding:8px 6px;">Interest</th>
                        <th style="padding:8px 6px;">Trend</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="kwTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to turn these keywords into pins automatically?</h2>
        <p>Sign up free and generate fully designed, scheduled Pinterest pins straight from your target keywords.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">How to Actually Do Keyword Research for Pinterest</h2>
        <p>Pinterest keyword research works differently from Google keyword research, and the biggest reason is data access: Google has an official Keyword Planner with real search-volume numbers, while Pinterest has never published an equivalent public tool. Anyone offering exact monthly search volume for Pinterest keywords is either estimating from limited third-party data or simply making it up — the platform doesn't expose that number to outside tools. That doesn't mean keyword research is pointless on Pinterest; it means the approach has to lean on pattern recognition and relative signals instead of precise volume figures.</p>
        <p>Because Pinterest is a visual search engine, the words people type into its search bar behave a lot like Google queries — full, natural phrases rather than single disconnected words. Someone doesn't search "recipes" on Pinterest; they search "easy vegan dinner recipes for beginners" or "30 minute weeknight meals." That's a meaningfully different pattern from a single-word keyword tool, which is why useful Pinterest keyword research returns search-like phrases rather than a flat list of individual words — the phrase itself carries the intent.</p>
        <p>Pinterest's own search bar remains the single best free research tool available, precisely because it's the same autocomplete real users see. Typing a broad term and watching what Pinterest suggests as you type — and what related searches appear below the results — shows you, directly from the platform, what people are actually searching around that topic right now. A dedicated research tool like this one is meant to sit alongside that, not replace it: it expands a single topic into a broader set of realistic phrase variations, mixing broad, niche, and long-tail angles, faster than manually typing a dozen variations into Pinterest's search bar yourself.</p>
        <p>"Interest" and "trend" labels on a keyword research tool without access to Pinterest's internal data are necessarily estimates, not measurements — and it's worth being upfront about that rather than dressing up a guess as a hard number. This tool labels them as AI-estimated signals for exactly that reason: useful for deciding which of fifteen related phrases to prioritize first, not a substitute for watching your own pins' actual performance in Pinterest Analytics once they're live. Real performance data from your own account, over time, will always outrank any external estimate.</p>
        <p>Once you have a list of candidate phrases, the next step is spreading them deliberately across your content rather than stuffing them all into one pin. A broad phrase might fit your board name, a mid-specificity phrase might fit your pin title, and a long-tail phrase might fit naturally inside your pin description — using different phrases in different fields, all pointing at the same underlying topic, covers more search ground than repeating one phrase everywhere.</p>
        <p>Seasonality is worth tracking separately from general popularity, since Pinterest's own usage patterns skew heavily toward planning ahead — people search for holiday decor, back-to-school organization, and summer travel weeks or months before the actual season, not during it. A keyword that looks quiet in general interest today might still be worth targeting right now if its season is coming up, which is part of why this tool's trend labels distinguish "Rising" and "Steady" from "Seasonal" rather than collapsing everything into a single popularity score.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Does Pinterest have an official keyword research tool like Google's?</summary><p>No. Pinterest doesn't publish a public keyword planner or search-volume API the way Google does. The closest first-party tool is Pinterest's own search bar and its autocomplete/related-search suggestions, which reflect real user search behavior directly.</p></details>
        <details><summary>Are the "Interest" and "Trend" numbers in this tool exact Pinterest data?</summary><p>No, and this tool doesn't claim they are. They're AI-estimated relative signals meant to help you prioritize which phrases to test first, based on general knowledge of the niche — not measured Pinterest search volume, which Pinterest doesn't make publicly available to any outside tool.</p></details>
        <details><summary>Should I search single words or full phrases on Pinterest?</summary><p>Full, natural phrases. Pinterest users tend to search the way they'd search Google — "easy vegan dinner recipes" rather than just "recipes" — so keyword research that returns realistic phrase variations is more useful than a list of disconnected single words.</p></details>
        <details><summary>Where should I use the keywords this tool finds?</summary><p>Spread different phrases across different fields rather than repeating one everywhere — a broader phrase in your board name, a mid-specificity phrase in your pin title, and a longer, more specific phrase worked naturally into your pin description.</p></details>
        <details><summary>Can I research keywords from a URL instead of typing a topic?</summary><p>Yes — switch to "Analyze a URL," paste the page's link, and the tool reads that page's title and meta description to understand the topic before generating related keyword phrases.</p></details>
        <details><summary>What's the best free way to validate a keyword on Pinterest myself?</summary><p>Type it directly into Pinterest's own search bar and watch the autocomplete suggestions and related searches that appear — that's real user search behavior straight from the platform, and it's worth cross-checking any external tool's suggestions against it.</p></details>
        <details><summary>How many keywords should I target per pin?</summary><p>Rather than targeting many keywords equally in one pin, pick one primary phrase for the title, a couple of supporting phrases for the description, and let your board name and profile cover broader category terms — spreading coverage across fields performs better than repeating everything in one place.</p></details>
        <details><summary>How is this different from a Google keyword tool?</summary><p>A Google keyword tool draws on Google's own measured search-volume data. This tool draws on AI's general knowledge of a niche to suggest realistic Pinterest-style search phrases and a relative interest/trend estimate, since Pinterest doesn't expose measured search volume to any outside tool the way Google does.</p></details>
        <details><summary>Is this Pinterest Keyword Research Tool free?</summary><p>Yes, every visitor gets a set number of free searches with no account required. Sign up free for unlimited searches plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.querySelectorAll('[data-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-mode]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const mode = btn.dataset.mode;
        document.querySelector('[data-mode-panel="keyword"]').style.display = mode === 'keyword' ? '' : 'none';
        document.querySelector('[data-mode-panel="url"]').style.display = mode === 'url' ? '' : 'none';
    });
});

const interestColor = { High: 'var(--green)', Medium: 'var(--amber)', Low: 'var(--gray)' };
const trendIcon = { Rising: '📈', Steady: '➡️', Seasonal: '🍂' };

document.getElementById('kwGenerateBtn').addEventListener('click', async () => {
    const mode = document.querySelector('[data-mode].active').dataset.mode;
    const keyword = document.getElementById('kwKeyword').value.trim();
    const url = document.getElementById('kwUrl').value.trim();
    if (mode === 'keyword' && !keyword) { alert('Please enter a keyword.'); return; }
    if (mode === 'url' && !url) { alert('Please enter a URL.'); return; }

    document.getElementById('kwEmptyState').style.display = 'none';
    document.getElementById('kwResult').style.display = 'none';
    document.getElementById('kwLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('mode', mode);
    body.set('keyword', keyword);
    body.set('url', url);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('kwLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('kwEmptyState').style.display = '';
            alert(data.error || 'Could not research keywords.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free searches — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('kwTableBody').innerHTML = data.keywords.map(k => `
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:8px 6px;">${k.keyword}</td>
                <td style="padding:8px 6px;color:${interestColor[k.interest] || 'var(--gray)'};font-weight:600;">${k.interest}</td>
                <td style="padding:8px 6px;">${trendIcon[k.trend] || ''} ${k.trend}</td>
                <td style="padding:8px 6px;"><button type="button" class="btn-secondary btn-small" onclick="navigator.clipboard.writeText('${k.keyword.replace(/'/g, "\\'")}'); this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200);">Copy</button></td>
            </tr>`).join('');
        document.getElementById('kwResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free search' + (data.remaining === 1 ? '' : 'es') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('kwLoadingState').style.display = 'none';
        document.getElementById('kwEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});
</script>

</body>
</html>
