<?php
/**
 * Public page of a shared Custom Design:  /design-share?t=<token>
 *  - Logged in  → opens straight in the design editor (their own copy; the owner gets the original).
 *  - Visitor    → preview + "Edit this design" (log in / sign up, then back into the editor on this design).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/design_functions.php';

$token = strtolower(trim((string)($_GET['t'] ?? '')));
$design = design_by_share_token($pdo, $token);
if (!$design) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$editorPath = '/user/design-editor?shared=' . $design['share_token'];
$user = current_user($pdo);
if ($user) redirect(rtrim(APP_URL, '/') . $editorPath);

// After logging in or signing up, the visitor lands back on this design in the editor.
$_SESSION['after_login_redirect'] = $editorPath;

$images = array_values(array_filter((array)json_decode((string)($design['share_images'] ?? ''), true), 'is_string'));
if (!$images && $design['thumb_path']) $images = [$design['thumb_path']];
$shareUrl = design_share_url($design['share_token']);
$title = $design['title'] ?: 'Untitled design';
$owner = trim(explode(' ', (string)$design['owner_name'])[0] ?? '');
$pages = count((array)(json_decode((string)$design['design_json'], true)['pages'] ?? [1]));
$img0 = $images ? seo_asset_url($images[0]) : '';
$u = rawurlencode($shareUrl);
$t = rawurlencode($title . ' — made with ' . SITE_BRAND);
$socials = [
    ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u, '#1877f2', 'f'],
    ['X', 'https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, '#000000', '𝕏'],
    ['Pinterest', 'https://pinterest.com/pin/create/button/?url=' . $u . '&media=' . rawurlencode($img0) . '&description=' . $t, '#e60023', 'P'],
    ['WhatsApp', 'https://wa.me/?text=' . $t . '%20' . $u, '#25d366', 'W'],
    ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, '#0a66c2', 'in'],
    ['Telegram', 'https://t.me/share/url?url=' . $u . '&text=' . $t, '#229ed9', '➤'],
    ['Reddit', 'https://www.reddit.com/submit?url=' . $u . '&title=' . $t, '#ff4500', 'r'],
    ['Email', 'mailto:?subject=' . $t . '&body=' . $u, '#6b7280', '✉'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => $title . ' — Design on ' . SITE_BRAND,
    'description' => 'A design shared on ' . SITE_BRAND . '. Open it in the free design editor, edit it and make it yours.',
    'image' => $img0 ?: null,
    'canonical' => $shareUrl,
    'noindex' => true,   // user-made designs: shareable, but kept out of search results (thin / duplicate pages)
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
<style>
.ds-wrap { max-width: 1080px; margin: 0 auto; padding: 36px 16px 60px; display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 36px; align-items: start; }
.ds-preview { background: #f3f4f6; border-radius: 18px; padding: 18px; display: flex; flex-direction: column; gap: 14px; align-items: center; }
.ds-preview img { max-width: 100%; max-height: 78vh; border-radius: 10px; box-shadow: 0 14px 40px rgba(17,24,39,.18); background: #fff; }
.ds-side h1 { margin: 0 0 6px; font-size: 28px; line-height: 1.2; word-break: break-word; }
.ds-meta { color: var(--gray); margin: 0 0 20px; font-size: 14px; }
.ds-edit { display: block; width: 100%; text-align: center; background: linear-gradient(90deg, #6d28d9, #db2777); color: #fff; border: none; border-radius: 12px; padding: 15px 18px; font-size: 16px; font-weight: 700; cursor: pointer; }
.ds-edit:hover { filter: brightness(1.05); text-decoration: none; }
.ds-auth { margin-top: 12px; border: 1px solid var(--border); border-radius: 14px; padding: 16px; background: #fff; }
.ds-auth p { margin: 0 0 12px; font-size: 14px; }
.ds-auth-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.ds-auth-btns a { flex: 1 1 140px; text-align: center; border-radius: 10px; padding: 11px 14px; font-weight: 700; }
.ds-auth-btns .ds-login { border: 1.5px solid #6d28d9; color: #6d28d9; }
.ds-auth-btns .ds-signup { background: #6d28d9; color: #fff; }
.ds-auth-btns a:hover { text-decoration: none; filter: brightness(1.05); }
.ds-share { margin-top: 26px; }
.ds-share h2 { font-size: 15px; margin: 0 0 10px; }
.ds-link { display: flex; gap: 8px; }
.ds-link input { flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; font: inherit; font-size: 13px; background: #f9fafb; }
.ds-link button { border: none; background: #111827; color: #fff; border-radius: 10px; padding: 0 16px; font-weight: 700; cursor: pointer; white-space: nowrap; }
.ds-socials { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.ds-socials a { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border-radius: 999px; color: #fff; font-size: 13px; font-weight: 600; }
.ds-socials a:hover { text-decoration: none; filter: brightness(1.08); }
.ds-socials b { display: inline-flex; width: 20px; height: 20px; align-items: center; justify-content: center; background: rgba(255,255,255,.22); border-radius: 50%; font-size: 11px; }
@media (max-width: 820px) { .ds-wrap { grid-template-columns: 1fr; gap: 22px; padding-top: 20px; } .ds-side h1 { font-size: 23px; } }
[data-theme="dark"] .ds-preview { background: #1e2025; }
[data-theme="dark"] .ds-auth { background: #1e2025; border-color: #33353c; }
</style>
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<main class="ds-wrap">
    <div class="ds-preview">
        <?php foreach ($images as $i => $img): ?>
            <img src="<?= e(seo_asset_url($img)) ?>" alt="<?= e($title) ?><?= count($images) > 1 ? ' — page ' . ($i + 1) : '' ?>" loading="<?= $i ? 'lazy' : 'eager' ?>">
        <?php endforeach; ?>
    </div>
    <div class="ds-side">
        <h1><?= e($title) ?></h1>
        <p class="ds-meta"><?= (int)$design['width'] ?> × <?= (int)$design['height'] ?> px<?= $pages > 1 ? ' · ' . $pages . ' pages' : '' ?><?= $owner !== '' ? ' · shared by ' . e($owner) : '' ?></p>

        <button type="button" class="ds-edit" id="dsEdit">✏️ Edit this design</button>
        <div class="ds-auth" id="dsAuth" hidden>
            <p>Log in or create a free account to edit this design. You'll come straight back to it in the design editor.</p>
            <div class="ds-auth-btns">
                <a href="auth/login" class="ds-login">Log in</a>
                <a href="auth/register" class="ds-signup">Sign up free</a>
            </div>
        </div>

        <div class="ds-share">
            <h2>Share this design</h2>
            <div class="ds-link">
                <input type="text" id="dsUrl" value="<?= e($shareUrl) ?>" readonly aria-label="Design link">
                <button type="button" id="dsCopy">Copy link</button>
            </div>
            <div class="ds-socials">
                <?php foreach ($socials as [$name, $href, $color, $icon]): ?>
                    <a href="<?= e($href) ?>" target="_blank" rel="noopener" style="background:<?= e($color) ?>"><b><?= e($icon) ?></b><?= e($name) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php render_site_footer($pdo); ?>

<script>
document.getElementById('dsEdit').addEventListener('click', function () {
    var box = document.getElementById('dsAuth');
    box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
document.getElementById('dsCopy').addEventListener('click', function () {
    var inp = document.getElementById('dsUrl'), btn = this;
    var done = function () { btn.textContent = 'Copied ✓'; setTimeout(function () { btn.textContent = 'Copy link'; }, 1800); };
    if (navigator.clipboard) navigator.clipboard.writeText(inp.value).then(done, function () { inp.select(); document.execCommand('copy'); done(); });
    else { inp.select(); document.execCommand('copy'); done(); }
});
</script>
</body>
</html>
