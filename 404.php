<?php
/**
 * Friendly 404 page.
 *  - Served by Apache for any URL that doesn't exist (ErrorDocument 404 in .htaccess)
 *  - Also required directly by blog.php / blog-post.php when a slug is wrong.
 * Uses absolute "/" paths so it renders correctly at any URL depth.
 */
// Never let the 404 page itself crash (e.g. database down) — it must always render.
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/auth.php';
} catch (Throwable $e) {
    $pdo = null;
}
if (!defined('SITE_BRAND')) define('SITE_BRAND', 'AutomatedPin');
if (!function_exists('e')) {
    function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

if (!headers_sent()) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
}

if (!isset($user)) {
    try { $user = ($pdo && function_exists('current_user')) ? current_user($pdo) : null; } catch (Throwable $e) { $user = null; }
}

$jokes = [
    "Our pins are usually great at staying put. This one wandered off to find itself.",
    "We searched every board, every drawer and under the couch. This page isn't here.",
    "This page went viral… so viral it disappeared completely.",
    "Looks like someone pinned this page to a board that doesn't exist.",
    "Even our AI shrugged. And it has an answer for everything.",
];
$joke = $jokes[array_rand($jokes)];
$cssVer = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<title>Page not found — <?= e(SITE_BRAND) ?></title>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= $cssVer ?>">
<style>
.nf-wrap {
    position: relative; overflow: hidden; min-height: calc(100vh - 70px);
    display: flex; align-items: center; justify-content: center; padding: 60px 20px 80px;
    background:
        radial-gradient(circle at 15% 20%, rgba(230,0,35,.08) 0, transparent 40%),
        radial-gradient(circle at 85% 80%, rgba(230,0,35,.10) 0, transparent 45%),
        #fff;
}
.nf-card { position: relative; z-index: 2; text-align: center; max-width: 760px; }
.nf-code {
    display: flex; align-items: center; justify-content: center; gap: clamp(6px, 2vw, 18px);
    font-size: clamp(110px, 24vw, 240px); font-weight: 900; line-height: 1; letter-spacing: -4px;
    color: var(--dark); user-select: none;
}
.nf-code .nf-four { display: inline-block; animation: nf-bob 3.2s ease-in-out infinite; }
.nf-code .nf-four:last-child { animation-delay: -1.6s; }
.nf-pin {
    position: relative; width: .78em; height: .78em; display: inline-flex; align-items: center; justify-content: center;
    animation: nf-swing 2.6s ease-in-out infinite; transform-origin: 50% 0;
}
.nf-pin-head {
    width: 100%; height: 100%; border-radius: 50%;
    background: radial-gradient(circle at 32% 30%, #ff6b81 0, var(--red) 45%, var(--red-dark) 100%);
    box-shadow: inset -10px -14px 0 rgba(0,0,0,.12), 0 18px 40px rgba(230,0,35,.35);
}
.nf-pin-face {
    position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: .06em; font-size: .16em; color: #fff;
}
.nf-eyes { display: flex; gap: .9em; }
.nf-eyes i { width: .55em; height: .55em; background: #fff; border-radius: 50%; display: block; animation: nf-blink 4s infinite; }
.nf-mouth { width: 1.3em; height: .65em; border: .16em solid #fff; border-top: none; border-radius: 0 0 1em 1em; transform: rotate(180deg); margin-top: .35em; }
.nf-needle {
    position: absolute; bottom: -.34em; left: 50%; width: .045em; height: .38em; min-width: 4px;
    background: linear-gradient(#9ca3af, #4b5563); transform: translateX(-50%) rotate(8deg); border-radius: 0 0 4px 4px;
}
.nf-title { font-size: clamp(26px, 4.4vw, 44px); margin: 34px 0 12px; font-weight: 800; }
.nf-text { font-size: clamp(16px, 2vw, 19px); color: var(--gray); margin: 0 auto 34px; max-width: 560px; }
.nf-home {
    display: inline-flex; align-items: center; gap: 12px;
    background: var(--red); color: #fff !important; text-decoration: none !important;
    font-size: clamp(18px, 2.4vw, 22px); font-weight: 800;
    padding: 20px 46px; border-radius: 999px;
    box-shadow: 0 14px 34px rgba(230,0,35,.35);
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.nf-home:hover { background: var(--red-dark); transform: translateY(-3px); box-shadow: 0 20px 40px rgba(230,0,35,.4); }
.nf-home .nf-arrow { font-size: 1.2em; transition: transform .15s ease; }
.nf-home:hover .nf-arrow { transform: translateX(-4px); }
.nf-links { margin-top: 26px; display: flex; gap: 10px 22px; justify-content: center; flex-wrap: wrap; font-weight: 600; }
.nf-links a { color: var(--dark); }
.nf-links a:hover { color: var(--red); }
.nf-url {
    display: inline-block; margin-top: 30px; font-size: 13px; color: var(--gray);
    background: var(--light); border: 1px dashed var(--border); border-radius: 10px; padding: 8px 14px;
    max-width: 100%; overflow-wrap: anywhere;
}
/* Floating lost "pins" in the background */
.nf-float { position: absolute; z-index: 1; border-radius: 14px; background: #fff; box-shadow: 0 10px 30px rgba(17,24,39,.08); border: 1px solid var(--border); animation: nf-drift 9s ease-in-out infinite; }
.nf-float::before { content: ""; position: absolute; top: -9px; left: 50%; width: 18px; height: 18px; border-radius: 50%; background: var(--red); transform: translateX(-50%); box-shadow: 0 3px 6px rgba(230,0,35,.3); }
.nf-float.f1 { width: 110px; height: 150px; top: 12%; left: 6%; transform: rotate(-12deg); }
.nf-float.f2 { width: 90px; height: 120px; top: 64%; left: 10%; transform: rotate(9deg); animation-delay: -3s; }
.nf-float.f3 { width: 120px; height: 90px; top: 16%; right: 7%; transform: rotate(10deg); animation-delay: -5s; }
.nf-float.f4 { width: 96px; height: 130px; bottom: 10%; right: 9%; transform: rotate(-8deg); animation-delay: -2s; }
.nf-float span { position: absolute; left: 12px; right: 12px; height: 8px; border-radius: 4px; background: var(--light); }
.nf-float span:nth-child(1) { top: 22px; } .nf-float span:nth-child(2) { top: 38px; right: 34px; } .nf-float span:nth-child(3) { bottom: 18px; height: 30%; background: #fde7ea; }
@keyframes nf-bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes nf-swing { 0%,100% { transform: rotate(-9deg); } 50% { transform: rotate(9deg); } }
@keyframes nf-blink { 0%,92%,100% { transform: scaleY(1); } 95% { transform: scaleY(.1); } }
@keyframes nf-drift { 0%,100% { translate: 0 0; } 50% { translate: 0 -18px; } }
@media (max-width: 700px) { .nf-float { display: none; } .nf-home { width: 100%; justify-content: center; padding: 20px 24px; } }
@media (prefers-reduced-motion: reduce) { .nf-wrap * { animation: none !important; } }
</style>
</head>
<body>

<?php if (function_exists('render_site_header')): render_site_header($pdo ?? null); else: ?>
<header class="navbar"><div class="container"><div class="logo"><a href="/" style="color:inherit;text-decoration:none;">Automated<span>Pin</span></a></div></div></header>
<?php endif; ?>

<main class="nf-wrap">
    <div class="nf-float f1"><span></span><span></span><span></span></div>
    <div class="nf-float f2"><span></span><span></span><span></span></div>
    <div class="nf-float f3"><span></span><span></span><span></span></div>
    <div class="nf-float f4"><span></span><span></span><span></span></div>

    <div class="nf-card">
        <div class="nf-code" aria-label="404">
            <span class="nf-four">4</span>
            <span class="nf-pin" aria-hidden="true">
                <span class="nf-pin-head"></span>
                <span class="nf-pin-face"><span class="nf-eyes"><i></i><i></i></span><span class="nf-mouth"></span></span>
                <span class="nf-needle"></span>
            </span>
            <span class="nf-four">4</span>
        </div>

        <h1 class="nf-title">Oops! This pin fell off the board.</h1>
        <p class="nf-text"><?= e($joke) ?></p>

        <a href="/" class="nf-home"><span class="nf-arrow">←</span> Back to Home Page</a>

        <div class="nf-links">
            <a href="/free-tools/">Free Tools</a>
            <a href="/blog">Blog</a>
            <a href="/pricing">Pricing</a>
            <a href="/contact">Contact</a>
        </div>

        <div class="nf-url">Not found: <?= e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') ?></div>
    </div>
</main>

</body>
</html>
