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
    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_keyword_tool');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free searches. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }
    $product = trim($_POST['product'] ?? '');
    $audience = trim($_POST['audience'] ?? '');
    $season = trim($_POST['season'] ?? '');

    $result = free_tool_generate_etsy_keywords($pdo, $product, $audience, $season, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'etsy_keyword_tool');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_keyword_tool');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'etsy_keyword_tool');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Free Etsy Keyword Tool for SEO | ' . SITE_BRAND,
    'description' => 'Find Etsy keywords buyers actually search for. Get long-tail ideas for your titles and tags, grouped by intent, to rank higher in Etsy search. Free.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/etsy-keyword-tool/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Etsy Keyword Tool', 'free-tools/etsy-keyword-tool/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Keyword Tool</h1>
        <p class="ft-sub">Find Etsy keyword ideas with demand, competition, and opportunity estimates — built for optimizing listings and ads.</p>
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
            <div class="form-row"><label>What Are You Selling? *</label>
                <input type="text" id="ekProduct" placeholder="e.g. Handmade soy candles, printable wedding invites">
                <p class="muted" style="margin-top:6px;">Be specific about materials, style, and purpose.</p>
            </div>
            <div class="form-row"><label>Ideal Customer <span class="muted">(optional)</span></label>
                <input type="text" id="ekAudience" placeholder="e.g. minimalist home decor lovers, eco-conscious shoppers">
            </div>
            <div class="form-row"><label>Season Or Occasion <span class="muted">(optional)</span></label>
                <input type="text" id="ekSeason" placeholder="e.g. Mother's Day gifts, Christmas, summer wedding">
            </div>
            <button type="button" id="ekGenerateBtn" class="btn-primary ft-generate-btn">🔍 Generate Etsy Keyword Ideas</button>
            <p class="muted" style="margin-top:10px;font-size:12px;">Etsy doesn't publish a public search-volume API, so Volume, Competition, and Opportunity below are AI-estimated signals for planning — not exact Etsy traffic numbers.</p>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="ekEmptyState" class="ft-empty">
                <div class="ft-empty-icon">🔍</div>
                <div class="ft-empty-title">No Keywords Yet</div>
                <div class="ft-empty-sub">Describe your product and generate to see keyword ideas here.</div>
            </div>
            <div id="ekLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Researching Etsy Keywords</div>
            </div>
            <div id="ekResult" style="display:none;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr style="text-align:left;border-bottom:2px solid var(--border);">
                        <th style="padding:6px;">Keyword</th><th style="padding:6px;">Volume</th><th style="padding:6px;">Competition</th><th style="padding:6px;">Opportunity</th><th></th>
                    </tr></thead>
                    <tbody id="ekTableBody"></tbody>
                </table>
                <button type="button" id="ekExportBtn" class="btn-secondary" style="margin-top:14px;">⬇ Export as CSV</button>
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
        <h2 style="text-align:left;">Doing Real Keyword Research on Etsy</h2>
        <p>Etsy search behaves less like a social platform and more like a narrow, purchase-intent version of Google — every query typed into that search bar is someone actively looking to buy something, right now, not idly browsing. That single fact changes what "good" keyword research looks like on Etsy compared with a platform built around casual discovery. A keyword that gets people looking is nice; a keyword that gets people who are ready to check out is what actually moves your shop's numbers.</p>
        <p>Etsy doesn't run a public keyword planner the way Google does, so there's no first-party source for exact search volume on any given phrase. Tools that claim exact Etsy search numbers are working from third-party estimation models, not Etsy's own data — worth knowing before you treat any single number as gospel. The more durable skill is pattern recognition: understanding how real buyers phrase what they want (specific materials, use case, recipient, occasion) well enough to generate and prioritize a strong list of candidate phrases, then verifying the strongest ones against Etsy's own search bar and your shop's own analytics over time.</p>
        <p>Search intent matters as much as raw popularity. A broad term like "necklace" pulls enormous volume but attracts browsers as much as buyers — someone typing that alone could be looking for literally anything. A phrase like "personalized birthstone necklace for mom" is narrower but carries obvious, specific buying intent; the person typing it already knows roughly what they want and is close to a purchase decision. Especially for a newer or smaller shop, ranking well for a handful of specific, intent-rich phrases usually converts better than chasing a broad term you're unlikely to outrank established shops for anyway.</p>
        <p>Competition and opportunity aren't the same axis, and conflating them is a common mistake. A high-volume, high-competition keyword can still be worth targeting if you have a genuinely differentiated product and strong photos — you're just accepting a harder climb. A lower-volume, lower-competition keyword can be an easier near-term win precisely because fewer listings are fighting for it, even though the total traffic ceiling is smaller. Balancing both types across your keyword strategy — a few competitive aspirational terms, a larger base of easier long-tail ones — tends to outperform betting everything on one or the other.</p>
        <p>Seasonality deserves its own line of thinking on Etsy specifically, because gift-buying and holiday shopping drive an outsized share of the platform's traffic. Buyers searching "Christmas gift for coworker" or "Mother's Day jewelry" start weeks, sometimes months, before the actual date — which means seasonal keyword optimization has to happen well ahead of the season itself to be caught by that early search behavior, not during the week the holiday actually lands.</p>
        <p>Once you have a working keyword list, the next move is spreading those phrases deliberately: a primary keyword anchoring the first 40 characters of your title (where Etsy search weighs it most), supporting phrases distributed naturally across your 13 tags, and longer-tail variations worked into the description where they read like real sentences rather than a list. Repeating one phrase everywhere wastes the other twelve tag slots you're given.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Does Etsy have an official keyword research tool?</summary><p>No — Etsy doesn't publish a public search-volume API or planner the way Google does. The closest first-party source is Etsy's own search bar and its autocomplete suggestions, which reflect real buyer search behavior directly.</p></details>
        <details><summary>Are the Volume, Competition, and Opportunity scores exact Etsy data?</summary><p>No, and this tool doesn't claim they are. They're AI-estimated relative signals to help you prioritize which phrases to test, based on general knowledge of the niche — not measured Etsy traffic, which Etsy doesn't expose publicly to any outside tool.</p></details>
        <details><summary>Should I target high-competition or low-competition keywords?</summary><p>A mix of both works best. High-competition keywords have proven demand but are harder to rank for; low-competition keywords are easier wins but usually carry less traffic. Newer shops generally benefit from leaning more toward the easier, lower-competition end until they've built some sales history.</p></details>
        <details><summary>How many keywords should I target per listing?</summary><p>Focus on 3-5 core keywords, then build natural variations around them across your title, tags, and description. Ranking well for a handful of genuinely relevant phrases tends to outperform spreading thin across many loosely related ones.</p></details>
        <details><summary>What's the best way to use these keywords in my listing?</summary><p>Put your strongest, most specific keyword in the first 40 characters of your title — that's the part Etsy's search weighs most and the part visible before truncation. Spread supporting phrases across your 13 tags without duplicating the title exactly, and work longer-tail variations naturally into your description.</p></details>
        <details><summary>Do seasonal keywords really matter that much?</summary><p>Yes — a large share of Etsy's traffic is gift and holiday driven, and buyers search well ahead of the actual date. Start optimizing for a season roughly 6-8 weeks before it arrives, and revisit afterward so your listings don't stay stuck advertising a holiday that's already passed.</p></details>
        <details><summary>What's the risk of keyword stuffing?</summary><p>Etsy's search algorithm is built to recognize unnatural, repetitive keyword use, and listings that read like a stuffed list rather than natural language tend to be discounted rather than boosted. Write for the human buyer first — a phrase that would make sense read aloud — and let the keyword coverage follow from that.</p></details>
        <details><summary>Should I focus on single keywords or full search phrases?</summary><p>Full phrases. Etsy buyers tend to search the way they'd search Google — specific, multi-word phrases with real intent behind them — so keyword research that surfaces realistic phrases tends to be more actionable than a list of disconnected single words.</p></details>
        <details><summary>Is this Etsy Keyword Tool free?</summary><p>Yes, every visitor gets a set number of free searches with no account required. Sign up free for unlimited searches plus the rest of our Etsy and Pinterest tools.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
let ekLastKeywords = [];
const volColor = { High: 'var(--green)', Medium: 'var(--amber)', Low: 'var(--gray)' };
const compColor = { High: '#b91c1c', Medium: 'var(--amber)', Low: 'var(--green)' };

document.getElementById('ekGenerateBtn').addEventListener('click', async () => {
    const product = document.getElementById('ekProduct').value.trim();
    if (!product) { alert('Please describe what you\'re selling.'); return; }
    document.getElementById('ekEmptyState').style.display = 'none';
    document.getElementById('ekResult').style.display = 'none';
    document.getElementById('ekLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('product', product);
    body.set('audience', document.getElementById('ekAudience').value.trim());
    body.set('season', document.getElementById('ekSeason').value.trim());

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('ekLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('ekEmptyState').style.display = '';
            alert(data.error || 'Could not generate keywords.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free searches — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        ekLastKeywords = data.keywords;
        document.getElementById('ekTableBody').innerHTML = data.keywords.map(k => `
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:6px;">${k.keyword}</td>
                <td style="padding:6px;color:${volColor[k.volume]};font-weight:600;">${k.volume}</td>
                <td style="padding:6px;color:${compColor[k.competition]};font-weight:600;">${k.competition}</td>
                <td style="padding:6px;">${k.opportunity}/100</td>
                <td style="padding:6px;"><button type="button" class="btn-secondary btn-small" onclick="navigator.clipboard.writeText('${k.keyword.replace(/'/g, "\\'")}'); this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200);">Copy</button></td>
            </tr>`).join('');
        document.getElementById('ekResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free search' + (data.remaining === 1 ? '' : 'es') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('ekLoadingState').style.display = 'none';
        document.getElementById('ekEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});

document.getElementById('ekExportBtn').addEventListener('click', () => {
    if (!ekLastKeywords.length) return;
    let csv = 'Keyword,Volume,Competition,Opportunity\n';
    ekLastKeywords.forEach(k => { csv += `"${k.keyword}",${k.volume},${k.competition},${k.opportunity}\n`; });
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'etsy-keywords.csv';
    link.click();
});
</script>

</body>
</html>
