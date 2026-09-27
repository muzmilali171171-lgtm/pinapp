<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest Character Counter — Titles, Descriptions, Bio Limits | <?= e(APP_NAME) ?></title>
<meta name="description" content="Count characters for every Pinterest field free — pin titles, descriptions, board names, board descriptions, and profile bio. See exactly where each one truncates in the feed.">
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Character Counter</h1>
        <p class="ft-sub">Check every Pinterest field against its real limit — and see exactly where it truncates in the feed.</p>
    </div>

    <div style="max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:18px;">
        <?php
        $fields = [
            ['id' => 'pcTitle', 'label' => 'Pin Title', 'limit' => 100, 'visible' => 40, 'sweet' => '30-40', 'multiline' => false],
            ['id' => 'pcDesc', 'label' => 'Pin Description', 'limit' => 500, 'visible' => 60, 'sweet' => '150-250', 'multiline' => true],
            ['id' => 'pcBoard', 'label' => 'Board Name', 'limit' => 50, 'visible' => null, 'sweet' => null, 'multiline' => false],
            ['id' => 'pcBoardDesc', 'label' => 'Board Description', 'limit' => 500, 'visible' => null, 'sweet' => null, 'multiline' => true],
            ['id' => 'pcBio', 'label' => 'Profile Bio (About)', 'limit' => 160, 'visible' => null, 'sweet' => null, 'multiline' => true],
            ['id' => 'pcDisplayName', 'label' => 'Display Name', 'limit' => 30, 'visible' => null, 'sweet' => null, 'multiline' => false],
        ];
        foreach ($fields as $f):
        ?>
        <div class="ft-panel" style="padding:16px 18px;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                <label style="font-weight:600;"><?= e($f['label']) ?></label>
                <span class="muted" id="<?= $f['id'] ?>Count">0 / <?= $f['limit'] ?></span>
            </div>
            <?php if ($f['multiline']): ?>
                <textarea class="pc-input" id="<?= $f['id'] ?>" data-limit="<?= $f['limit'] ?>" data-visible="<?= $f['visible'] ?>" rows="3" style="width:100%;"></textarea>
            <?php else: ?>
                <input type="text" class="pc-input" id="<?= $f['id'] ?>" data-limit="<?= $f['limit'] ?>" data-visible="<?= $f['visible'] ?>" style="width:100%;">
            <?php endif; ?>
            <div class="progress-bar" style="margin-top:8px;"><div id="<?= $f['id'] ?>Bar" style="height:100%;width:0%;background:var(--green);border-radius:6px;transition:width .15s;"></div></div>
            <?php if ($f['visible']): ?>
                <p class="muted" style="font-size:12px;margin:6px 0 0;">Truncates at ~<?= $f['visible'] ?> characters in the feed<?= $f['sweet'] ? '. Engagement sweet spot: ' . $f['sweet'] . ' characters.' : '.' ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want AI to write copy that already fits?</h2>
        <p>Sign up free and let AI write titles, descriptions, and bios sized correctly from the start — no counting required.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Why Pinterest's Character Limits Are Worth Watching Closely</h2>
        <p>Pinterest treats its text fields more like search-ranking inputs than casual social copy, which makes character limits matter in a way they don't on platforms built for scrolling and reacting. Every field — title, description, board name, bio — has both a hard limit (the point where Pinterest simply stops accepting more characters) and a much shorter visible limit (the point where the feed or search result truncates what's shown with an ellipsis, even though the full text is technically saved). Writing to the hard limit while ignoring the visible one is a common, avoidable mistake.</p>
        <p>Pin titles are the clearest example. Pinterest allows up to 100 characters, but the feed and search results only show roughly the first 40 before cutting the rest off. That gap between "allowed" and "visible" is exactly why the most important keyword or hook needs to live in that first 40 characters rather than the back half of a longer, cleverer sentence — anything past that point is essentially invisible until someone actually clicks through to the pin's full page.</p>
        <p>Descriptions have more breathing room — a 500-character hard limit — but the visible window in the feed is even tighter proportionally, cutting off around 50-60 characters. Data from creators who track pin performance consistently points to a sweet spot well short of the full 500: descriptions in roughly the 150-250 character range tend to perform best, long enough to include real context and a keyword or two, short enough to stay readable and avoid feeling like a wall of text once someone does click through.</p>
        <p>Board names, board descriptions, and your profile bio all carry their own separate limits, and it's easy to lose track of which number applies where when you're moving fast between fields. A bio capped at 160 characters needs far tighter, more deliberate writing than a board description allowed up to 500 — mixing those budgets up in your head is how a bio ends up getting cut off mid-sentence when you paste in something written for a more generous field.</p>
        <p>This tool exists to remove the guesswork: type or paste into any field and watch the count, the truncation point, and (where it applies) the engagement sweet spot update live, so you know exactly how much room you actually have before you're anywhere near Pinterest's own editor.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What's the character limit for a Pinterest pin title?</summary><p>100 characters, but only about the first 40 show in the feed before truncating. Keep your main keyword and hook within that first 40 characters.</p></details>
        <details><summary>What's the character limit for a Pinterest pin description?</summary><p>500 characters, with roughly 50-60 visible in the feed preview. Many well-performing descriptions land in the 150-250 character range rather than using the full 500.</p></details>
        <details><summary>What's the Pinterest profile bio character limit?</summary><p>160 characters for the "About" field on your profile — noticeably tighter than a pin description, so every word needs to earn its place.</p></details>
        <details><summary>What's the Pinterest board name character limit?</summary><p>Board names are generally capped around 50 characters, with longer names getting truncated in some display views.</p></details>
        <details><summary>Why does "visible in feed" matter if the full text still saves?</summary><p>Because most people deciding whether to click or save your pin never see past the truncation point. The full text still exists and matters once someone opens the pin, but the truncated portion is what determines whether they open it in the first place.</p></details>
        <details><summary>Does this tool count spaces and punctuation as characters?</summary><p>Yes — the count reflects every character you type, including spaces and punctuation, matching how Pinterest's own limit is enforced.</p></details>
        <details><summary>Does this tool save or send my text anywhere?</summary><p>No — everything happens locally in your browser using JavaScript. Nothing you type is sent to a server or stored.</p></details>
        <details><summary>Is this Pinterest Character Counter free?</summary><p>Yes — completely free, unlimited, with no account required, since it runs entirely in your browser.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.querySelectorAll('.pc-input').forEach(input => {
    input.addEventListener('input', () => update(input));
    update(input);
});

function update(input) {
    const limit = parseInt(input.dataset.limit, 10);
    const visible = input.dataset.visible ? parseInt(input.dataset.visible, 10) : null;
    const len = input.value.length;
    const countEl = document.getElementById(input.id + 'Count');
    const barEl = document.getElementById(input.id + 'Bar');
    countEl.textContent = len + ' / ' + limit + (visible ? ' (' + Math.min(len, visible) + '/' + visible + ' visible)' : '');
    const pct = Math.min(100, (len / limit) * 100);
    barEl.style.width = pct + '%';
    barEl.style.background = len > limit ? 'var(--red)' : (pct > 90 ? 'var(--amber)' : 'var(--green)');
    countEl.style.color = len > limit ? 'var(--red)' : '';
}
</script>

</body>
</html>
