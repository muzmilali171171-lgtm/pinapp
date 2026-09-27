<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);
$toneOptions = ['Neutral', 'Funny', 'Professional', 'Informal', 'Positive', 'Inspirational'];

/* ===================== AJAX: generate ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');

    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'hashtag_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }

    $mode = ($_POST['mode'] ?? 'manual') === 'url' ? 'url' : 'manual';
    $tone = in_array($_POST['tone'] ?? '', $toneOptions, true) ? $_POST['tone'] : 'Neutral';
    $count = max(2, min(20, (int)($_POST['count'] ?? 10)));

    $topic = '';
    if ($mode === 'url') {
        $ctx = free_tool_fetch_url_context(trim($_POST['url'] ?? ''));
        if (!$ctx['ok']) { echo json_encode(['ok' => false, 'error' => $ctx['error']]); exit; }
        $topic = $ctx['title'] . ($ctx['description'] ? ' — ' . $ctx['description'] : '');
    } else {
        $topic = trim($_POST['title'] ?? '');
    }

    $result = free_tool_generate_hashtags($pdo, $topic, $tone, $count, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'hashtag_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'hashtag_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'hashtag_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free AI Pinterest Hashtag Generator — Trending Hashtags In Seconds | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate relevant, trending Pinterest hashtags free with AI. Enter a title or paste a URL, pick your tone, and get a ready-to-use hashtag set to boost your pin reach.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free AI Pinterest Hashtag Generator</h1>
        <p class="ft-sub">Find the best trending hashtags for your Pinterest Pins to increase reach and followers.</p>
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
            <div class="ft-tone-row" style="margin-bottom:14px;">
                <button type="button" class="ft-tone-btn active" data-mode="manual">Enter Title Manually</button>
                <button type="button" class="ft-tone-btn" data-mode="url">Extract From URL</button>
            </div>

            <div data-mode-panel="manual">
                <div class="form-row"><label>Title / Topic</label>
                    <input type="text" id="hgTitle" placeholder="e.g. Easy Vegan Breakfast Recipes">
                </div>
            </div>
            <div data-mode-panel="url" style="display:none;">
                <div class="form-row"><label>URL</label>
                    <input type="url" id="hgUrl" placeholder="https://example.com/blog/post">
                </div>
            </div>

            <div class="form-row"><label>Tone Of Voice</label>
                <select id="hgTone"><?php foreach ($toneOptions as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row"><label>Number Of Hashtags</label>
                <input type="number" id="hgCount" value="10" min="2" max="20">
            </div>
            <button type="button" id="hgGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Hashtags</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="hgEmptyState" class="ft-empty">
                <div class="ft-empty-icon">#️⃣</div>
                <div class="ft-empty-title">No Hashtags Yet</div>
                <div class="ft-empty-sub">Enter a title or URL and generate to see your hashtags here.</div>
            </div>
            <div id="hgLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Finding Hashtags</div>
            </div>
            <div id="hgResult" style="display:none;">
                <div id="hgTagList" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;"></div>
                <button type="button" id="hgCopyBtn" class="btn-secondary">📋 Copy All</button>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to write and schedule your pins too?</h2>
        <p>Sign up free to turn any page into a fully designed, scheduled Pinterest pin — titles, descriptions, hashtags, and images, all handled for you.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">How to Use Hashtags on Pinterest the Right Way</h2>
        <p>Pinterest hashtags behave differently from hashtags on Instagram or X. They aren't a community feed you scroll through — they work more like extra search terms attached to your pin description. When someone searches a keyword, Pinterest's algorithm weighs the words in your title, your description, and any hashtags you've added to decide whether your pin belongs in the results. That's the whole point of a hashtag generator: instead of guessing which words your audience actually searches for, you get a ready mix of broad, niche, and long-tail tags built around your specific topic.</p>
        <p>Because Pinterest treats hashtags as search terms rather than social tags, relevance beats volume every time. A pin about a 10-minute vegan breakfast doesn't need #food or #yum — it needs #veganbreakfast, #plantbasedrecipes, and #quickvegan meals, the kind of phrases someone would actually type into the Pinterest search bar. Broad tags like #recipes cast a wide net but face heavy competition from millions of other pins. Niche tags like #veganoatmealbowl face far less competition and tend to reach people who are already close to saving or clicking. A good hashtag set blends both: a couple of broad, evergreen tags for reach, a handful of niche tags for qualified traffic, and one or two long-tail tags that mirror how people phrase real searches.</p>
        <p>Timing matters too. Hashtags do most of their work in the first 24 to 48 hours after a pin goes live, while Pinterest is still deciding how to categorize and distribute it. After that window, your title, description, and overall pin quality carry more weight than the hashtags themselves. That's why it's worth generating a fresh, relevant hashtag set for every new pin rather than reusing the same block of tags on everything you publish — reused, generic hashtags read as spammy to the algorithm and don't reflect what's actually in that specific pin.</p>
        <p>How many hashtags should you actually use? Pinterest technically allows up to 20 per pin, but most creators and marketers who track performance land on somewhere between 3 and 10 well-chosen tags — enough to cover a few different search angles without crowding out the natural-language keywords in your description, which still do the heavier lifting for Pinterest's search engine. This generator defaults to 10 and lets you dial the count down or up depending on how much room your description leaves and how specific your topic is.</p>
        <p>Placement matters as much as selection. The best practice is to write your description first, in full sentences, the way you'd explain the pin to a friend — then add your hashtags at the very end, after the description text, rather than scattering them through the sentence. That keeps the description readable for humans (who still decide whether to click or save) while giving Pinterest's algorithm the extra signal it's looking for.</p>
        <p>This generator saves you the trial and error. Type in your pin's title or topic — or paste the URL of the page you're pinning and it'll pull the title and description automatically — pick the tone that matches your brand voice, and choose how many hashtags you want. AI reads the topic the way a Pinterest searcher would and returns a set that mixes broad, niche, and long-tail tags, ready to paste straight into your pin description.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Do hashtags actually work on Pinterest?</summary><p>Yes, but differently than you might expect. Pinterest hashtags aren't clickable community tags like on Instagram — they function as extra search keywords that help the algorithm understand and categorize your pin, especially in the first day or two after you post it. They won't rescue a pin with a weak title or a blurry image, but paired with a solid description they give your pin one more relevant signal to match against what people are searching for.</p></details>
        <details><summary>How many hashtags should I use on a Pinterest pin?</summary><p>Pinterest allows up to 20 hashtags per pin, but more isn't automatically better. Most successful pins use somewhere between 3 and 10 hashtags — enough to cover a couple of different angles on the topic (broad, niche, long-tail) without turning the description into a wall of tags. Quality and relevance matter far more than hitting a specific number.</p></details>
        <details><summary>Where should I place my hashtags in the pin description?</summary><p>At the end. Write your description first as a natural, helpful sentence or two, then add your hashtags after it. This keeps the description readable for the person deciding whether to click or save your pin, while still giving Pinterest's search algorithm the keyword signal from your hashtags.</p></details>
        <details><summary>Should I use the same hashtags on every pin?</summary><p>No — reusing an identical block of hashtags across unrelated pins looks spammy to Pinterest's systems and doesn't reflect what's actually in each pin. Generate a fresh, relevant set for every new pin based on that specific pin's topic, even if some broad tags repeat naturally across similar content.</p></details>
        <details><summary>What's the difference between broad and niche hashtags?</summary><p>Broad (or "evergreen") hashtags cover a wide category — think #homedecor or #recipes — and reach a large audience, but face heavy competition from millions of other pins. Niche hashtags are more specific — #midcenturylivingroom or #onepotveganchili — and reach a smaller but far more relevant audience who are more likely to click, save, or follow. A strong hashtag set mixes both.</p></details>
        <details><summary>Do hashtags help old or "not fresh" pins?</summary><p>Not much. Hashtags mainly influence how Pinterest distributes a pin in its first 24-48 hours while it's still being freshly indexed. For a pin that's been live for weeks or months, your description, board placement, and overall engagement history matter far more than any hashtags attached to it.</p></details>
        <details><summary>Can I generate hashtags from a blog post URL instead of typing a title?</summary><p>Yes — switch to "Extract from URL" above, paste the page's link, and the tool reads that page's title and meta description to understand the topic before generating hashtags, so you don't have to summarize the post yourself.</p></details>
        <details><summary>Is this Pinterest Hashtag Generator free to use?</summary><p>Yes, every visitor gets a set number of free hashtag generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.querySelectorAll('[data-mode]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-mode]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const mode = btn.dataset.mode;
        document.querySelector('[data-mode-panel="manual"]').style.display = mode === 'manual' ? '' : 'none';
        document.querySelector('[data-mode-panel="url"]').style.display = mode === 'url' ? '' : 'none';
    });
});

document.getElementById('hgGenerateBtn').addEventListener('click', async () => {
    const mode = document.querySelector('[data-mode].active').dataset.mode;
    const title = document.getElementById('hgTitle').value.trim();
    const url = document.getElementById('hgUrl').value.trim();
    if (mode === 'manual' && !title) { alert('Please enter a title or topic.'); return; }
    if (mode === 'url' && !url) { alert('Please enter a URL.'); return; }

    document.getElementById('hgEmptyState').style.display = 'none';
    document.getElementById('hgResult').style.display = 'none';
    document.getElementById('hgLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('mode', mode);
    body.set('title', title);
    body.set('url', url);
    body.set('tone', document.getElementById('hgTone').value);
    body.set('count', document.getElementById('hgCount').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('hgLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('hgEmptyState').style.display = '';
            alert(data.error || 'Could not generate hashtags.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        const list = document.getElementById('hgTagList');
        list.innerHTML = data.hashtags.map(t => `<span class="ft-template-chip" style="cursor:default;">${t}</span>`).join('');
        document.getElementById('hgResult').style.display = '';
        document.getElementById('hgCopyBtn').dataset.text = data.hashtags.join(' ');
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('hgLoadingState').style.display = 'none';
        document.getElementById('hgEmptyState').style.display = '';
        alert('Something went wrong. Please try again.');
    }
});

document.getElementById('hgCopyBtn').addEventListener('click', function () {
    navigator.clipboard.writeText(this.dataset.text || '').then(() => {
        const original = this.textContent;
        this.textContent = '✓ Copied!';
        setTimeout(() => { this.textContent = original; }, 1500);
    });
});
</script>

</body>
</html>
