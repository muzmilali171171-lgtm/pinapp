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

    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'title_desc_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }

    $mode = ($_POST['mode'] ?? 'manual') === 'url' ? 'url' : 'manual';
    $tone = in_array($_POST['tone'] ?? '', $toneOptions, true) ? $_POST['tone'] : 'Neutral';
    $audience = trim($_POST['audience'] ?? '');

    $topic = '';
    if ($mode === 'url') {
        $ctx = free_tool_fetch_url_context(trim($_POST['url'] ?? ''));
        if (!$ctx['ok']) { echo json_encode(['ok' => false, 'error' => $ctx['error']]); exit; }
        $topic = $ctx['title'] . ($ctx['description'] ? ' — ' . $ctx['description'] : '');
    } else {
        $topic = trim($_POST['topic'] ?? '');
    }

    $result = free_tool_generate_title_description($pdo, $topic, $tone, $audience, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'title_desc_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'title_desc_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'title_desc_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest Title &amp; Description Generator | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate click-worthy Pinterest pin titles and SEO-friendly descriptions free with AI. Enter a topic or paste a URL, pick your tone and audience, and get copy-ready results.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Title &amp; Description Generator</h1>
        <p class="ft-sub">Create compelling titles and descriptions that get clicks and saves on Pinterest.</p>
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
                <button type="button" class="ft-tone-btn active" data-mode="manual">Enter Topic Manually</button>
                <button type="button" class="ft-tone-btn" data-mode="url">Extract From URL</button>
            </div>

            <div data-mode-panel="manual">
                <div class="form-row"><label>Topic</label>
                    <input type="text" id="tdTopic" placeholder="e.g. DIY Fall Wreath Tutorial">
                </div>
            </div>
            <div data-mode-panel="url" style="display:none;">
                <div class="form-row"><label>URL</label>
                    <input type="url" id="tdUrl" placeholder="https://example.com/blog/post">
                </div>
            </div>

            <div class="form-row"><label>Tone Of Voice</label>
                <select id="tdTone"><?php foreach ($toneOptions as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row"><label>Target Audience <span class="muted">(optional)</span></label>
                <input type="text" id="tdAudience" placeholder="e.g. busy moms, home cooks, small business owners">
            </div>
            <button type="button" id="tdGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Title &amp; Description</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="tdEmptyState" class="ft-empty">
                <div class="ft-empty-icon">📝</div>
                <div class="ft-empty-title">Nothing Generated Yet</div>
                <div class="ft-empty-sub">Enter a topic or URL and generate to see your title and description here.</div>
            </div>
            <div id="tdLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Writing Your Copy</div>
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
                    <label>Description <span class="muted" id="tdDescCount"></span></label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <textarea id="tdResultDesc" readonly rows="4" style="flex:1;"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-copy="tdResultDesc">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to design the whole pin, not just the copy?</h2>
        <p>Sign up free and turn any topic or URL into a fully designed Pinterest pin — image, title, and description, ready to schedule.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Writing Pinterest Titles and Descriptions That Actually Get Clicks</h2>
        <p>Pinterest is often described as a visual discovery engine, but underneath the images it works a lot like a search engine — and your title and description are the text it reads to decide who sees your pin. Get them right and Pinterest matches your pin to the searches your ideal audience is already typing in. Get them wrong — vague, keyword-stuffed, or copied word-for-word from your blog post's headline — and even a beautiful pin design can sit buried in search results.</p>
        <p>Pinterest gives you 100 characters for a title, but only shows around the first 40 before truncating it in the feed and in search results with an ellipsis. That means your most important word or phrase needs to sit right at the start, not buried in the middle. "10-Minute Vegan Stir-Fry: Quick, Easy, Weeknight-Ready" works because the main keyword — vegan stir-fry — leads the sentence; a title that opens with a clever pun and saves the keyword for the end loses that keyword the moment it gets cut off on mobile.</p>
        <p>Descriptions have more room — up to 500 characters — but the visible preview in the feed only shows the first 50 to 60 before Pinterest truncates it too, and data from creators who track pin performance consistently shows that descriptions in the 150-250 character range tend to perform best. Longer isn't automatically better; a description packed with every keyword you can think of reads as spam to both Pinterest's algorithm and the human deciding whether to click. The better approach is to write the description like a short, helpful text message to a friend: what is this, why should they care, and what should they do next (read the full recipe, shop the look, save the idea for later). That natural sentence structure still carries your keywords — it just carries them inside real, readable language instead of a list.</p>
        <p>Tone matters more than most people realize on Pinterest, because unlike a lot of search traffic, Pinterest users are often in a planning or dreaming headspace — browsing a wedding board, a home renovation, or next week's dinners. A dry, purely informational title can undersell a genuinely exciting idea, while an overly salesy one can feel out of place next to someone's cozy aesthetic mood board. That's why this generator lets you pick a tone — neutral, professional, positive, inspirational, funny, or informal — so the copy matches the feeling of your niche rather than sounding like generic marketing text pasted onto every pin.</p>
        <p>Naming a target audience sharpens the result further. "Easy Meal Prep Ideas" written for busy parents reads differently than the same topic written for college students on a budget or for fitness-focused meal preppers — the pain points, the vocabulary, and the promise all shift even though the underlying topic is identical. Telling the generator who you're writing for (even a short phrase like "beginner gardeners" or "small Etsy shop owners") lets it choose language that speaks directly to that reader instead of a generic audience.</p>
        <p>If you'd rather not summarize your own content, switch to "Extract from URL," paste the link to the page you're pinning, and the tool reads that page's existing title and meta description to understand what it's about before writing fresh, Pinterest-optimized copy — useful when you're pinning dozens of blog posts or product pages and don't want to retype each topic by hand.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>How long should a Pinterest pin title be?</summary><p>Pinterest allows up to 100 characters, but only around the first 40 show before the title gets truncated in the feed and search results. Keep your main keyword and hook within that first 40 characters so it's never cut off, even on mobile.</p></details>
        <details><summary>How long should a Pinterest pin description be?</summary><p>The hard limit is 500 characters, but the feed preview only shows about the first 50-60. Many high-performing descriptions land in the 150-250 character range — long enough to include context and a keyword or two, short enough to stay readable and avoid keyword stuffing.</p></details>
        <details><summary>Should I write my Pinterest title the same as my blog post's headline?</summary><p>Not necessarily. Blog headlines are written for Google and for readers already on your site; Pinterest titles need to work as search terms and hook a browsing audience in a much shorter space, often front-loading a keyword that a blog headline might save for later. It's common — and usually more effective — for the two to differ.</p></details>
        <details><summary>What's the ideal Pinterest description format?</summary><p>Write it like a short, natural sentence or two explaining what the pin is and why it's worth clicking or saving, include a relevant keyword or two naturally, and end with a light call-to-action ("Get the full recipe," "See the tutorial," "Shop the look"). Avoid stringing together disconnected keywords — Pinterest and pinners both respond better to real sentences.</p></details>
        <details><summary>Does the target audience field actually change the output?</summary><p>Yes. Naming an audience shifts the vocabulary, pain points, and promise in the generated copy — the same topic written for "busy parents" versus "college students on a budget" comes out noticeably different, even though the subject is identical. It's optional, but filling it in sharpens the result.</p></details>
        <details><summary>Can I generate a title and description straight from a URL?</summary><p>Yes — switch to "Extract from URL," paste the page's link, and the tool reads that page's existing title and meta description to understand the topic, then writes fresh Pinterest-optimized copy from it, so you don't need to summarize the content yourself.</p></details>
        <details><summary>Do keywords in the title and description actually help my pin get found?</summary><p>Yes — Pinterest is search-driven, and the words in your title and description are among the strongest signals it uses to match your pin to what people are searching. Natural, relevant keywords in both fields meaningfully improve discoverability; keyword-stuffed, unnatural text tends to hurt more than it helps.</p></details>
        <details><summary>Is this Pinterest Title &amp; Description Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
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

document.getElementById('tdGenerateBtn').addEventListener('click', async () => {
    const mode = document.querySelector('[data-mode].active').dataset.mode;
    const topic = document.getElementById('tdTopic').value.trim();
    const url = document.getElementById('tdUrl').value.trim();
    if (mode === 'manual' && !topic) { alert('Please enter a topic.'); return; }
    if (mode === 'url' && !url) { alert('Please enter a URL.'); return; }

    document.getElementById('tdEmptyState').style.display = 'none';
    document.getElementById('tdResult').style.display = 'none';
    document.getElementById('tdLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('mode', mode);
    body.set('topic', topic);
    body.set('url', url);
    body.set('tone', document.getElementById('tdTone').value);
    body.set('audience', document.getElementById('tdAudience').value.trim());

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('tdLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('tdEmptyState').style.display = '';
            alert(data.error || 'Could not generate title and description.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('tdResultTitle').value = data.title;
        document.getElementById('tdResultDesc').value = data.description;
        document.getElementById('tdTitleCount').textContent = '(' + data.title.length + '/100)';
        document.getElementById('tdDescCount').textContent = '(' + data.description.length + '/500)';
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
