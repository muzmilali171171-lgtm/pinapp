<?php
/**
 * Shared layout for auth/login and auth/register: form card + marketing side (animated
 * 3-month growth graph, testimonials, features). On phones the form comes first.
 *
 *   auth_layout_start($pdo, 'Log in — App', 'login');   ...page prints its form...   auth_layout_end('login');
 */
require_once __DIR__ . '/use_case_functions.php';

function auth_layout_start(PDO $pdo, string $title, string $mode): void
{
    $root = rtrim(APP_URL, '/');
    $v = fn($f) => @filemtime(__DIR__ . '/../' . $f) ?: time();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php seo_render_head($pdo, ['title' => $title, 'description' => '', 'noindex' => true, 'schema' => false]); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= $root ?>/assets/css/style.css?v=<?= $v('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= $root ?>/assets/css/auth.css?v=<?= $v('assets/css/auth.css') ?>">
</head>
<body class="au-body au-<?= e($mode) ?>">
<div class="au-shell">
    <main class="au-main">
        <a href="<?= $root ?>/" class="au-brand"><span class="au-logo">📌</span> <?= e(APP_NAME) ?></a>
        <div class="au-card">
<?php
}

function auth_layout_end(string $mode): void
{
    $root = rtrim(APP_URL, '/');
    $t = uc_media()['testimonials'];
    $isLogin = $mode === 'login';
    ?>
        </div>
        <p class="au-legal">By continuing you agree to our <a href="<?= $root ?>/terms">Terms</a> and <a href="<?= $root ?>/privacy-policy">Privacy Policy</a>.</p>
    </main>

    <aside class="au-side" aria-label="Why <?= e(APP_NAME) ?>">
        <div class="au-glow au-glow-1" aria-hidden="true"></div>
        <div class="au-glow au-glow-2" aria-hidden="true"></div>
        <div class="au-side-inner">
            <div class="au-kicker"><span class="au-live"></span> <?= $isLogin ? 'Your pins kept working while you were away' : 'Join creators growing on autopilot' ?></div>
            <h2 class="au-head"><?= $isLogin ? 'Welcome back to your' : 'Turn your website into a' ?> <span class="au-grad">Pinterest traffic machine</span></h2>
            <p class="au-sub">AI designs hundreds of pins in one click, writes and publishes blog posts, and schedules everything — so your traffic grows while you sleep.</p>

            <figure class="au-graph" aria-label="Illustration: Pinterest traffic rising from month 1 to month 3">
                <div class="au-graph-top">
                    <div><b>Pinterest traffic</b><small>with daily scheduling</small></div>
                    <span class="au-badge">📈 Growing</span>
                </div>
                <svg viewBox="0 0 400 190" class="au-svg" role="img" aria-hidden="true">
                    <defs>
                        <linearGradient id="auLine" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#ff4d6a"/><stop offset="1" stop-color="#22c55e"/></linearGradient>
                        <linearGradient id="auFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#22c55e" stop-opacity=".35"/><stop offset="1" stop-color="#22c55e" stop-opacity="0"/></linearGradient>
                    </defs>
                    <g class="au-gridlines"><line x1="20" y1="40" x2="390" y2="40"/><line x1="20" y1="85" x2="390" y2="85"/><line x1="20" y1="130" x2="390" y2="130"/><line x1="20" y1="165" x2="390" y2="165"/></g>
                    <path class="au-area" d="M20,160 C60,158 90,156 130,150 C170,144 190,132 230,116 C270,100 300,78 330,52 C350,36 370,24 390,14 L390,165 L20,165 Z"/>
                    <path class="au-line" d="M20,160 C60,158 90,156 130,150 C170,144 190,132 230,116 C270,100 300,78 330,52 C350,36 370,24 390,14"/>
                    <g class="au-dots">
                        <circle cx="143" cy="148" r="6" class="au-dot au-d1"/>
                        <circle cx="267" cy="97" r="6" class="au-dot au-d2"/>
                        <circle cx="390" cy="14" r="7" class="au-dot au-d3"/>
                    </g>
                </svg>
                <div class="au-months"><span>Month 1<small>Warming up</small></span><span>Month 2<small>Picking up</small></span><span>Month 3<small>Taking off 🚀</small></span></div>
                <figcaption>Illustration — results vary by niche and consistency.</figcaption>
            </figure>

            <ul class="au-feats">
                <li><span>⚡</span> Hundreds of pages → pins in 1 click</li>
                <li><span>✍️</span> AI Auto Blog: writes, publishes &amp; pins</li>
                <li><span>♾️</span> Unlimited pin designs, AI-picked boards</li>
            </ul>

            <div class="au-tests" aria-roledescription="carousel" aria-label="What users say">
                <?php foreach ($t as $i => $q): ?>
                    <figure class="au-test <?= $i === 0 ? 'is-on' : '' ?>" <?= $i ? 'aria-hidden="true"' : '' ?>>
                        <div class="au-stars" aria-label="<?= (int)$q['stars'] ?> out of 5"><?= str_repeat('★', $q['stars']) . str_repeat('☆', 5 - $q['stars']) ?></div>
                        <blockquote>“<?= e($q['quote']) ?>”</blockquote>
                        <figcaption><img src="<?= e($q['img']) ?>" alt="" width="38" height="38" loading="lazy" decoding="async"><span><b><?= e($q['name']) ?></b><small><?= e($q['role']) ?></small></span></figcaption>
                    </figure>
                <?php endforeach; ?>
                <div class="au-test-dots" role="tablist">
                    <?php foreach ($t as $i => $q): ?><button type="button" class="<?= $i === 0 ? 'is-on' : '' ?>" data-i="<?= $i ?>" aria-label="Show review <?= $i + 1 ?>"></button><?php endforeach; ?>
                </div>
            </div>

            <div class="au-trust"><span>✅ Official Pinterest API</span><span>🆓 Free to start</span><span>💳 No card needed</span></div>
        </div>
    </aside>
</div>
<script>
(function () {
    // Testimonials: rotate every 5s, pause on hover
    var items = document.querySelectorAll('.au-test'), dots = document.querySelectorAll('.au-test-dots button'), i = 0, timer;
    function show(n) {
        items[i].classList.remove('is-on'); items[i].setAttribute('aria-hidden', 'true'); dots[i].classList.remove('is-on');
        i = (n + items.length) % items.length;
        items[i].classList.add('is-on'); items[i].removeAttribute('aria-hidden'); dots[i].classList.add('is-on');
    }
    function start() { stop(); timer = setInterval(function () { show(i + 1); }, 5000); }
    function stop() { clearInterval(timer); }
    dots.forEach(function (d) { d.addEventListener('click', function () { show(+d.dataset.i); start(); }); });
    var box = document.querySelector('.au-tests');
    if (box) { box.addEventListener('mouseenter', stop); box.addEventListener('mouseleave', start); }
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) start();

    // Replay the growth line each time the graph scrolls into view (mobile shows it below the form)
    var g = document.querySelector('.au-graph');
    if (g && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { g.classList.remove('is-play'); void g.offsetWidth; g.classList.add('is-play'); } }); }, { threshold: .4 }).observe(g);
    } else if (g) g.classList.add('is-play');

    // Show / hide password
    document.querySelectorAll('[data-toggle-pw]').forEach(function (b) {
        b.addEventListener('click', function () {
            var inp = document.getElementById(b.getAttribute('data-toggle-pw'));
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            b.textContent = show ? 'Hide' : 'Show';
            b.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });
    // Password strength (signup)
    var pw = document.getElementById('au-password'), meter = document.querySelector('.au-meter');
    if (pw && meter) pw.addEventListener('input', function () {
        var v = pw.value, s = 0;
        if (v.length >= 6) s++; if (v.length >= 10) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
        meter.dataset.s = v ? s : 0;
        meter.querySelector('small').textContent = ['', 'Weak', 'Okay', 'Good', 'Strong'][v ? Math.max(1, s) : 0];
    });
    // Prevent double submit
    document.querySelectorAll('.au-form').forEach(function (f) { f.addEventListener('submit', function () { var b = f.querySelector('.au-submit'); if (b) { b.disabled = true; b.classList.add('is-loading'); } }); });
})();
</script>
</body>
</html>
<?php
}

/** Social login buttons (shared). */
function auth_social_buttons(PDO $pdo, array $authSettings): void
{
    $btns = [];
    if ($authSettings['google_enabled']) $btns[] = [oauth_build_authorize_url($pdo, 'google', 'login'), 'Google', '<svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'];
    if ($authSettings['facebook_enabled']) $btns[] = [oauth_build_authorize_url($pdo, 'facebook', 'login'), 'Facebook', '<span style="color:#1877f2;font-weight:900">f</span>'];
    if ($authSettings['microsoft_enabled']) $btns[] = [oauth_build_authorize_url($pdo, 'microsoft', 'login'), 'Microsoft', '<span style="font-size:15px">⊞</span>'];
    if ($authSettings['pinterest_login_enabled'] && ($purl = pinterest_login_authorize_url($pdo))) $btns[] = [$purl, 'Pinterest', '<span style="color:#e60023">📌</span>'];
    if (!$btns) return;
    echo '<div class="au-social">';
    foreach ($btns as $b) echo '<a href="' . e($b[0]) . '" class="au-social-btn">' . $b[2] . '<span>Continue with ' . e($b[1]) . '</span></a>';
    echo '</div>';
}
