<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/free_tool_functions.php';

$user = current_user($pdo);
$settings = free_tool_text_settings($pdo);
$toneOptions = ['Neutral', 'Funny', 'Professional', 'Informal', 'Formal', 'Positive'];
$languageOptions = ['English', 'Spanish', 'French', 'German', 'Italian'];

/* ===================== AJAX: generate ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    header('Content-Type: application/json');

    $remaining = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'bio_generator');
    if ($remaining <= 0) {
        echo json_encode(['ok' => false, 'error' => 'You\'ve used all your free generations. Sign up free to keep going.', 'limit_reached' => true]);
        exit;
    }

    $niche = trim($_POST['niche'] ?? '');
    $tone = in_array($_POST['tone'] ?? '', $toneOptions, true) ? $_POST['tone'] : 'Neutral';
    $language = in_array($_POST['language'] ?? '', $languageOptions, true) ? $_POST['language'] : 'English';

    $result = free_tool_generate_bio($pdo, $niche, $tone, $language, $settings);
    if ($result['ok']) {
        free_tool_record_attempt($pdo, 'bio_generator');
        $result['remaining'] = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'bio_generator');
    }
    echo json_encode($result);
    exit;
}

$remainingAttempts = free_tool_attempts_remaining($pdo, $settings['max_attempts'], 'bio_generator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest Bio Generator — SEO-Optimized Profile Bios | <?= e(APP_NAME) ?></title>
<meta name="description" content="Generate a compelling, SEO-optimized Pinterest bio free with AI. Enter your niche, pick a tone and language, and get a ready-to-use profile bio under Pinterest's character limit.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Bio Generator</h1>
        <p class="ft-sub">Create compelling, SEO-optimized bios for your Pinterest profile that attract followers.</p>
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
                <input type="text" id="bgNiche" placeholder="e.g. budget-friendly home decor for renters">
            </div>
            <div class="form-row"><label>Tone Of Voice</label>
                <select id="bgTone"><?php foreach ($toneOptions as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row"><label>Language</label>
                <select id="bgLanguage"><?php foreach ($languageOptions as $l): ?><option value="<?= e($l) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
            </div>
            <button type="button" id="bgGenerateBtn" class="btn-primary ft-generate-btn">✨ Generate Bio</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="bgEmptyState" class="ft-empty">
                <div class="ft-empty-icon">👤</div>
                <div class="ft-empty-title">No Bio Yet</div>
                <div class="ft-empty-sub">Enter your niche and generate to see your Pinterest bio here.</div>
            </div>
            <div id="bgLoadingState" class="ft-loading" style="display:none;">
                <div class="ft-spinner"></div>
                <div class="ft-loading-title">Writing Your Bio</div>
            </div>
            <div id="bgResult" style="display:none;">
                <div class="form-row">
                    <label>Bio <span class="muted" id="bgBioCount"></span></label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <textarea id="bgResultBio" readonly rows="3" style="flex:1;"></textarea>
                        <button type="button" class="btn-secondary btn-small" data-copy="bgResultBio">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want your whole Pinterest presence on autopilot?</h2>
        <p>Sign up free to design pins, write titles and descriptions, and schedule everything to Pinterest — all from one dashboard.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">How to Write a Pinterest Bio That Attracts the Right Followers</h2>
        <p>Your Pinterest bio is one of the smallest pieces of copy on your whole profile, and one of the most overlooked — yet it's often the first thing a visitor reads after your profile photo, before they decide whether to follow you, click through to your website, or scroll straight past. Unlike a personal Instagram bio, which can lean entirely on personality, a Pinterest bio does double duty: it has to say something a human finds compelling in a few seconds, while also carrying the keywords Pinterest's search engine uses to decide whose profile shows up when someone searches your niche.</p>
        <p>Pinterest's "About" field caps out at 160 characters — noticeably tighter than the 500 characters you get in a pin description, which means every word has to earn its place. There's no room for a long mission statement or a list of your five different content pillars. The bios that work best tend to follow a simple shape: who you help, what you post about, and — if there's room — a light call-to-action. "Helping busy parents cook 30-minute dinners the whole family loves. New recipes every week." says more in two short sentences than a vague "Welcome to my page!" ever could, and it naturally contains the keywords — busy parents, 30-minute dinners, recipes — that Pinterest's search index picks up on.</p>
        <p>Keywords matter here for the same reason they matter in a pin description: Pinterest is a visual search engine first, and profiles are searchable just like pins are. If your niche is home organization for small apartments, a bio built around "small space living," "apartment organization," and "renter-friendly storage" gives Pinterest concrete signals about who should see your profile in search results — much more than a personal statement about why you love organizing. The trick is folding those keywords into a sentence that still reads naturally, the same way a good pin description does, rather than stacking bare keywords with no connective language.</p>
        <p>Tone shapes how that message lands. A professional tone suits a coaching business or an agency account; a funny, informal tone might suit a lifestyle or meme-adjacent account far better, even in the exact same niche. Getting the tone right isn't just a style preference — it's part of qualifying your audience. A follower drawn in by a playful, informal bio expects playful, informal content on your boards, and a mismatch between bio tone and pin content tends to show up later as lower engagement, even if the topic itself was a perfect fit.</p>
        <p>If you run more than one language market, generating your bio directly in that language — rather than machine-translating an English version after the fact — usually reads more naturally and keeps the keyword phrasing aligned with how people in that market actually search. This generator supports a handful of common languages for exactly that reason.</p>
        <p>This tool takes your niche, tone, and language and returns one polished, ready-to-paste bio that respects Pinterest's 160-character limit from the start, so you're never left trimming a great sentence down to fit. Adjust the niche description or tone and regenerate as many times as you like until the wording feels like you.</p>
        <p>It's also worth revisiting your bio more often than most creators do. A bio written when you first opened your account rarely reflects where your content has actually landed six months or a year later — niches narrow, offers change, and the audience you're actually attracting on Pinterest often turns out to be more specific than what you originally wrote for. Treat your bio the way you'd treat any other piece of on-page copy: worth a quick refresh whenever your content focus shifts, whenever you launch something new, or simply every season if your niche is time-sensitive (holiday decor, back-to-school organization, summer travel). A five-minute regeneration here costs far less than the follower who scrolled past a bio that no longer matches what's actually on your boards.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What is the Pinterest bio character limit?</summary><p>Pinterest's "About" field — your profile bio — has a hard limit of 160 characters. That's tighter than a pin description (500 characters), so every word needs to count.</p></details>
        <details><summary>What should I include in my Pinterest bio?</summary><p>The strongest bios cover three things in a short space: who you help, what you post about, and optionally a light call-to-action. Weaving in one or two natural keywords for your niche also helps Pinterest's search engine understand and surface your profile.</p></details>
        <details><summary>Do keywords in my bio actually help people find my profile?</summary><p>Yes. Pinterest is a visual search engine, and profiles are indexed and surfaced in search results much like pins are. A bio with clear, relevant niche keywords gives Pinterest a stronger signal about who should see your profile when they search related terms.</p></details>
        <details><summary>Should my Pinterest bio sound different from my Instagram or TikTok bio?</summary><p>Often, yes. Pinterest users tend to be in a planning or discovery mindset — searching for ideas rather than following personalities — so a bio that leads with what you help people find or do usually performs better than one built purely around personal branding.</p></details>
        <details><summary>Can I use hashtags in my Pinterest bio?</summary><p>You can, but it's not necessary and eats into your very limited 160 characters. Natural keyword phrases tend to read better and carry the same search benefit without looking cluttered.</p></details>
        <details><summary>How do I choose the right tone for my bio?</summary><p>Match the tone to the content your followers will actually see on your boards. A professional tone suits a coaching or service business; a funny or informal tone suits a lifestyle or personality-driven account. A mismatch between your bio's tone and your pins' tone tends to hurt engagement even when the topic is a good fit.</p></details>
        <details><summary>Can I generate a bio in a language other than English?</summary><p>Yes — choose your language before generating, and the bio is written natively in that language rather than translated afterward, which tends to read more naturally and match how people in that market actually search.</p></details>
        <details><summary>Is this Pinterest Bio Generator free?</summary><p>Yes, every visitor gets a set number of free generations with no account required. Sign up free for unlimited generations plus the rest of our Pinterest tools — pin design, scheduling, and more.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('bgGenerateBtn').addEventListener('click', async () => {
    const niche = document.getElementById('bgNiche').value.trim();
    if (!niche) { alert('Please enter your account topic or niche.'); return; }

    document.getElementById('bgEmptyState').style.display = 'none';
    document.getElementById('bgResult').style.display = 'none';
    document.getElementById('bgLoadingState').style.display = '';

    const body = new URLSearchParams();
    body.set('action', 'generate');
    body.set('niche', niche);
    body.set('tone', document.getElementById('bgTone').value);
    body.set('language', document.getElementById('bgLanguage').value);

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body });
        const data = await res.json();
        document.getElementById('bgLoadingState').style.display = 'none';
        if (!data.ok) {
            document.getElementById('bgEmptyState').style.display = '';
            alert(data.error || 'Could not generate a bio.');
            if (data.limit_reached) document.getElementById('ftAttempts').innerHTML =
                'You\'ve used your free generations — <a href="../../auth/register">sign up free</a> to keep going';
            return;
        }
        document.getElementById('bgResultBio').value = data.bio;
        document.getElementById('bgBioCount').textContent = '(' + data.bio.length + '/160)';
        document.getElementById('bgResult').style.display = '';
        if (typeof data.remaining !== 'undefined') {
            document.getElementById('ftAttempts').textContent = data.remaining > 0
                ? data.remaining + ' free generation' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
        }
    } catch (e) {
        document.getElementById('bgLoadingState').style.display = 'none';
        document.getElementById('bgEmptyState').style.display = '';
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
