<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/affiliate_functions.php';
require_once __DIR__ . '/includes/free_tool_functions.php';

// Affiliate link landing (?ref=CODE) — logs the click and drops a cookie so
// auth/register can credit the referring affiliate if this visitor signs up.
if (!empty($_GET['ref'])) {
    affiliate_track_click($pdo, trim((string)$_GET['ref']));
}

$user = current_user($pdo);

/* ===================== Homepage "Create a Pin" widget — AJAX ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hw_action'])) {
    header('Content-Type: application/json');
    $hwAction = $_POST['hw_action'];

    // Step 1: visitor pastes a page URL — fetch its title + host so the popup opens pre-filled.
    if ($hwAction === 'resolve') {
        $url = trim($_POST['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['ok' => false, 'error' => 'Please paste a valid page URL.']);
            exit;
        }
        $fetchedTitle = extract_title_from_url($url);
        echo json_encode([
            'ok' => true,
            'title' => $fetchedTitle ?: '',
            'website' => parse_url($url, PHP_URL_HOST) ?: '',
        ]);
        exit;
    }

    // Step 2: generate (or regenerate) the pin with whatever the popup's fields currently hold.
    if ($hwAction === 'generate') {
        $pcSettings = free_tool_pincreate_settings($pdo);
        $remaining = free_tool_attempts_remaining($pdo, $pcSettings['max_pins'], 'pin_create');
        if ($remaining <= 0) {
            echo json_encode(['ok' => false, 'error' => "You've used all your free pin creations. Sign up free to keep going.", 'limit_reached' => true]);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $showWebsite = !empty($_POST['show_website']);
        $ctaMode = in_array($_POST['cta_mode'] ?? '', ['auto', 'custom', 'none'], true) ? $_POST['cta_mode'] : 'auto';
        $ctaText = trim($_POST['cta_text'] ?? '');
        $sizeKey = in_array($_POST['size'] ?? '', ['2:3', '9:16', '1:2.1', '1:1'], true) ? $_POST['size'] : '2:3';
        $imageStyle = trim($_POST['image_style'] ?? 'high_attractive_multi');
        $imageType = ($_POST['image_type'] ?? 'single') === 'collage' ? 'collage' : 'single';
        $collageCount = max(3, min(6, (int)($_POST['collage_count'] ?? 4)));
        $colorPaletteRaw = $_POST['color_palette'] ?? '';

        $result = free_tool_generate_homepage_pin(
            $pdo, $title, $website, $showWebsite, $ctaMode, $ctaText,
            $sizeKey, $imageStyle, $imageType, $collageCount, $colorPaletteRaw
        );
        if ($result['ok']) {
            free_tool_record_attempt($pdo, 'pin_create');
            $_SESSION['free_tool_pincreate_pending'] = ['clean_path' => $result['clean_path'], 'title' => $result['title']];
            $result['remaining'] = free_tool_attempts_remaining($pdo, $pcSettings['max_pins'], 'pin_create');
        }
        echo json_encode($result);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}
$hwRemainingAttempts = free_tool_attempts_remaining($pdo, free_tool_pincreate_settings($pdo)['max_pins'], 'pin_create');
require_once __DIR__ . '/includes/use_case_functions.php';

/* ===================== Page content (home) ===================== */
$seo = get_seo_settings($pdo);
$hpMeta = [
    // Admin → SEO Setting values win when set; these are the fallbacks.
    'title' => ($seo['meta_title'] ?? '') ?: 'AI Pinterest Automation & Auto Blog | ' . APP_NAME,
    'description' => ($seo['meta_description'] ?? '') ?: 'AI creates pins for hundreds of pages in 1 click, writes and publishes blog posts with images, and schedules Pinterest pins on autopilot. Start free.',
    'keywords' => ($seo['meta_keywords'] ?? '') ?: 'Pinterest automation, AI Pinterest pin maker, auto pin to Pinterest, Pinterest pin scheduler, AI auto blog, auto blogging tool, bulk pin scheduler, Pinterest marketing tool, unlimited pin templates, Pinterest traffic',
    'canonical' => ($seo['canonical_url'] ?? '') ?: rtrim(APP_URL, '/') . '/',
];
$hpFaq = [
    ['What is ' . APP_NAME . '?', APP_NAME . ' is a Pinterest automation platform. Connect your Pinterest account once and schedule pins — with AI-written titles, descriptions and pin designs — to publish automatically, on your own timeline.'],
    ['How does Pinterest pin scheduling work?', 'Scan your website, choose your pages, pick a design and a publishing pace, and approve. Our scheduler runs in the background and publishes each pin exactly when it is due.'],
    ['Is it safe to connect my Pinterest account?', 'Yes — you connect through Pinterest’s official login (OAuth). We never see or store your Pinterest password, and you can disconnect at any time.'],
    ['Do I need my own Pinterest developer API access?', 'No. Just click “Connect Pinterest” and authorize your own account.'],
    ['How many pin templates are there?', 'The Classic Wizard includes 70 templates, 56 colour palettes and 130+ Google fonts, and you can import your own design from Canva as an SVG.'],
    ['Can I schedule pins to hundreds of pages at once?', 'Yes — add your website, let it scan your sitemap, select hundreds of pages and schedule several pins for each in a single run.'],
    ['Does it write pin titles and descriptions automatically?', 'Yes. AI writes a unique title, description, alt text and keywords for every pin from the page content. You can edit anything before approving.'],
    ['How does AI choose or create a board?', 'AI matches each page to the closest board on your account, or creates a new, well-named board if none fits.'],
    ['Is it safe for a new Pinterest account?', 'Yes. The new-account warm-up grows your pace from 1 pin a day in month one to 20 a day by month five, and the time between pins is set automatically.'],
    ['Can AI create pins for hundreds of pages in 1 click?', 'Yes. Scan your site, select hundreds of pages and approve once — AI designs the pin images from your own photos, writes the copy, picks the boards and schedules everything.'],
    ['Can AI write and publish blog posts for me?', 'Yes, on plans that include Auto Blog. Paste a list of titles (or competitor links that AI rewrites into your own titles); AI writes each article with images, publishes to your WordPress, Shopify, Wix or custom site at your daily pace, and schedules pins for every post.'],
    ['Are pin templates really unlimited?', 'You get 70 templates, 56 colour palettes and 130+ fonts, plus any Canva designs you import — AI mixes them per page, so the number of different pin designs is practically unlimited.'],
    ['What is Bulk Pin Scheduler?', 'Bulk Pin Scheduler lets you paste a list of titles or links, generate pins for each, and queue them all in one pass.'],
    ['What is Auto Blog and Auto Pin?', 'On plans that include it, AI writes and publishes articles to your website and pins them to Pinterest on a daily schedule — no manual input after setup.'],
    ['Can I use my own images?', 'Yes. Pins use the photos from your own pages, and you can upload a different image for any pin before it is scheduled.'],
    ['Does it work with WordPress, Shopify, WooCommerce and Etsy?', 'Yes — any site with public pages works. Sitemaps from WordPress, Shopify and WooCommerce are read automatically, and single pages can be pasted in too.'],
    ['Is there a free plan or free tools?', 'Yes. Create a free account to get started, and use the free Pin Maker and Create Pin tools with no sign-up at all.'],
    ['Do free pins have a watermark?', 'Pins from the no-login Create Pin tool carry a small watermark. A free account removes it and gives you the original file.'],
    ['Can I invite team members?', 'Yes. Team Management lets you invite people by email to share your account’s resources without sharing your login.'],
    ['Can I cancel anytime?', 'Yes, upgrade, downgrade or cancel from your billing settings at any time — no long-term contract.'],
    ['What happens if a pin fails to publish?', 'Failed pins are logged with the reason and retried; you can see queued, upcoming and failed pins in your scheduler.'],
];
$hpHeroUp = uc_media()['hero_up'];
// Use cases featured on the home page (all of them are linked further down, by group).
$hpFeatured = array_intersect_key(uc_catalog(), array_flip(['shopify', 'woocommerce', 'wordpress', 'etsy', 'amazon', 'food-website', 'recipe-blog', 'home-decor', 'hairstyles-website', 'fashion-boutique', 'print-on-demand']));
$hpHeroDown = uc_media()['hero_down'];
$hpCta = fn(string $label, string $class = 'btn-primary rg-shine') => $user
    ? '<a href="user/dashboard" class="' . e($class) . '">Go to Dashboard →</a>'
    : '<a href="auth/register" class="' . e($class) . '">' . e($label) . '</a>';
$hpV = fn($f) => @filemtime(__DIR__ . '/' . $f) ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, $hpMeta); ?>
<link rel="preconnect" href="https://media.webtopin.com">
<link rel="preload" as="image" href="<?= e($hpHeroUp[0]) ?>" fetchpriority="high">
<link rel="stylesheet" href="assets/css/style.css?v=<?= $hpV('assets/css/style.css') ?>">
<link rel="stylesheet" href="assets/css/free-tool.css?v=<?= $hpV('assets/css/free-tool.css') ?>">
<link rel="stylesheet" href="assets/css/use-case.css?v=<?= $hpV('assets/css/use-case.css') ?>">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Organization', 'name' => APP_NAME, 'url' => rtrim(APP_URL, '/') . '/'],
        ['@type' => 'WebSite', 'name' => APP_NAME, 'url' => rtrim(APP_URL, '/') . '/'],
        ['@type' => 'SoftwareApplication', 'name' => APP_NAME, 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web',
            'description' => $hpMeta['description'], 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD']],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $hpFaq)],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body class="uc-page hp">
<div class="uc-progress" aria-hidden="true"><span></span></div>

<?php render_site_header($pdo ?? null); ?>

<!-- ===================== HERO ===================== -->
<section class="hp-hero">
    <div class="hp-aurora" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="hp-grid-bg" aria-hidden="true"></div>
    <div class="container hp-hero-grid">
        <div class="hp-hero-copy">
            <div class="uc-badge rg-border-glow rg-pop">🎯 Approved by Pinterest · Official API</div>
            <h1 class="hp-h1">AI Pinterest Automation: <span class="uc-grad-anim">Hundreds of Pins &amp; Blog Posts in 1 Click</span></h1>
            <p class="uc-sub">AI turns hundreds of your pages into designed pins, writes and publishes new blog posts with images, and schedules everything to Pinterest — so you build real growth and save hours every week.</p>
            <ul class="uc-feature-list">
                <?php foreach ([
                    ['⚡', 'AI Auto-Creates Pin Images for Hundreds of Pages in 1 Click & Schedules Them'],
                    ['✍️', 'AI Auto Blog: Creates & Publishes Posts With Images, Then Schedules Pins'],
                    ['♾️', 'Unlimited AI Pin Designs — 70 Templates, 56 Palettes, 130+ Fonts & Canva'],
                    ['🤖', 'AI Writes Titles, Descriptions, Alt Text & Keywords'],
                    ['💯', 'AI Picks the Best Board — or Creates One'],
                ] as $i => $b): ?>
                    <li class="hp-rv" data-rv="left" style="--d: <?= $i * 70 ?>ms"><span class="uc-feature-ic"><?= $b[0] ?></span> <?= e($b[1]) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="uc-cta-row">
                <?= $hpCta('Get Started Free →', 'btn-primary rg-shine uc-btn-pulse hp-btn-lg') ?>
                <?php if (!$user): ?><a href="#hp-try" class="btn-secondary uc-btn-outline hp-btn-lg">Create a Free Pin</a><?php endif; ?>
            </div>
            <div class="uc-core-pills">
                <span>📈 Build real growth</span>
                <span>⏱️ Save hours every week</span>
                <span>✅ Official Pinterest API</span>
            </div>
            <div class="hp-trust">
                <div class="hp-avatars" aria-hidden="true">
                    <?php foreach (array_slice(uc_media()['testimonials'], 0, 5) as $t): ?><img src="<?= e($t['img']) ?>" alt="" width="36" height="36" decoding="async"><?php endforeach; ?>
                </div>
                <div><span class="hp-stars" aria-hidden="true">★★★★★</span><br><small>Loved by bloggers, stores &amp; agencies · Free to start · No card needed</small></div>
            </div>
        </div>
        <div class="hp-hero-visual" aria-hidden="true">
            <div class="hp-glow-ring"></div>
            <div class="uc-scroll-col uc-scroll-up"><div class="uc-scroll-track">
                <?php for ($i = 0; $i < 2; $i++) foreach ($hpHeroUp as $k => $img): ?><img src="<?= e($img) ?>" alt="" width="220" height="330" decoding="async" <?= $i === 0 && $k < 3 ? ($k === 0 ? 'fetchpriority="high"' : '') : 'loading="lazy"' ?>><?php endforeach; ?>
            </div></div>
            <div class="uc-scroll-col uc-scroll-down"><div class="uc-scroll-track">
                <?php for ($i = 0; $i < 2; $i++) foreach ($hpHeroDown as $k => $img): ?><img src="<?= e($img) ?>" alt="" width="220" height="330" decoding="async" <?= $i === 0 && $k < 3 ? '' : 'loading="lazy"' ?>><?php endforeach; ?>
            </div></div>
            <div class="uc-float-chip uc-float-chip-1">📌 Auto-scheduled</div>
            <div class="uc-float-chip uc-float-chip-2">✅ Published on time</div>
            <div class="uc-float-chip uc-float-chip-3">✍️ Blog post published</div>
        </div>
    </div>
</section>

<!-- ===================== FREE CREATE PIN ===================== -->
<section class="uc-start" id="hp-try">
    <div class="container">
        <div class="uc-start-card hp-glass rg-border-glow hp-rv" data-rv="zoom">
            <div class="uc-eyebrow rg-text-blink">✨ FREE TOOL · NO SIGN-UP NEEDED</div>
            <h2>Create a Pinterest Pin From Any Page — Free</h2>
            <p class="uc-muted">Paste a page URL, click Create Pin, and fine-tune size, style, CTA and brand colours before you generate.</p>
            <form id="hwStartForm" class="uc-start-form">
                <label class="uc-sr" for="hwStartUrl">Page URL</label>
                <input type="url" id="hwStartUrl" placeholder="https://yourwebsite.com/blog/post" required>
                <button type="submit" class="btn-primary rg-shine">Create Pin →</button>
            </form>
            <div class="uc-start-meta" id="hwAttempts">
                <?php if ($hwRemainingAttempts > 0): ?>
                    <?= (int)$hwRemainingAttempts ?> free pin<?= $hwRemainingAttempts === 1 ? '' : 's' ?> left in this session
                <?php else: ?>
                    You've used your free pins — <a href="auth/register">sign up free</a> to keep going
                <?php endif; ?>
            </div>
            <p class="hp-mini-link">Want several pins with 70 templates? <a href="free-tools/pinterest-pin-maker/">Try the full free Pin Maker →</a></p>
        </div>
    </div>
</section>

<?php uc_section_power($pdo, $user, ['slug' => 'home', 'power_noun' => 'pages'], ''); ?>

<!-- ===================== PLATFORMS ===================== -->
<section class="hp-platforms">
    <div class="container">
        <p class="hp-platforms-title hp-rv" data-rv="up">Works with the platforms you already use</p>
        <div class="hp-platform-row">
            <?php $pi = 0; foreach ($hpFeatured as $slug => $c): ?>
                <a href="use-cases/<?= e($slug) ?>/" class="hp-platform hp-rv" data-rv="up" style="--d: <?= ($pi++) * 50 ?>ms"><span><?= $c['icon'] ?></span><?= e(preg_replace('/ (Stores|Shops|Blogs|Websites|Artists|Print-on-Demand|Sellers & Affiliates)$/', '', $c['title'])) ?></a>
            <?php endforeach; ?>
            <a href="use-cases/" class="hp-platform hp-rv" data-rv="up"><span>✨</span>+<?= count(uc_catalog()) - $pi ?> more</a>
        </div>
    </div>
</section>

<section class="uc-marquee" aria-label="Pinterest niches">
    <div class="uc-marquee-track">
        <?php $hpNiches = ['Recipe pins', 'Home decor ideas', 'Outfit ideas', 'Hairstyles', 'DIY projects', 'Travel guides', 'Product pins', 'Gift guides', 'Printables', 'Wedding ideas', 'Blog posts', 'Affiliate roundups'];
        for ($i = 0; $i < 2; $i++) foreach ($hpNiches as $w): ?><span><?= e($w) ?></span><?php endforeach; ?>
    </div>
</section>

<!-- ===================== SEE THE RESULTS ===================== -->
<section class="uc-graph" id="results">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// SEE THE RESULTS</div>
            <h2>Consistent Pinterest Scheduling <span class="uc-grad-text">Compounds</span></h2>
            <p class="uc-muted">A slower first month while Pinterest learns your content, then steady growth as your pin library builds up — the shape a real account scheduling daily takes over 12 months.</p>
        </div>
        <div class="uc-counters">
            <?php foreach ([[5, 'x', 'more traffic, up to'], [47, '%', 'higher engagement after pruning'], [365, '', 'days of pins in one run'], [70, '', 'pin templates']] as $i => $st): ?>
                <div class="uc-counter hp-spot hp-rv" data-rv="<?= $i % 2 ? 'down' : 'up' ?>" style="--d: <?= $i * 90 ?>ms"><b><span data-count="<?= $st[0] ?>">0</span><?= e($st[1]) ?></b><span><?= e($st[2]) ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="uc-graph-chart rg-border-glow hp-rv hp-lift" data-rv="zoom">
            <img src="<?= e(uc_media()['graph']) ?>" alt="Pinterest traffic growth chart over 12 months, slow at first then rising toward 1 million monthly views" loading="lazy">
            <p class="uc-graph-note">Illustrative growth curve — actual results vary by niche, content volume and consistency.</p>
        </div>
        <div class="uc-center">
            <div class="uc-graph-label rg-text-blink">Pin &amp; Grow Traffic</div>
            <?= $hpCta('Start Free →') ?>
        </div>
    </div>
</section>

<!-- ===================== PROBLEM → SOLUTION ===================== -->
<section class="hp-ps">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// WHY PINTEREST FEELS HARD</div>
            <h2>Pinterest Works. <span class="uc-red">Doing It by Hand Doesn’t.</span></h2>
        </div>
        <div class="hp-ps-grid">
            <div class="hp-ps-card hp-ps-pain hp-rv" data-rv="left">
                <h3>😩 Without automation</h3>
                <ul>
                    <li>Hours in Canva designing one pin at a time</li>
                    <li>Writing every title and description yourself</li>
                    <li>Forgetting to post — and losing momentum</li>
                    <li>Writing every blog post yourself, one by one</li>
                    <li>Only your newest posts ever get promoted</li>
                    <li>No idea which pins actually bring traffic</li>
                </ul>
            </div>
            <div class="hp-ps-arrow hp-rv" data-rv="zoom" aria-hidden="true">→</div>
            <div class="hp-ps-card hp-ps-gain rg-border-glow hp-rv" data-rv="right">
                <h3>🚀 With <?= e(APP_NAME) ?></h3>
                <ul>
                    <li>70 templates applied to your photos automatically</li>
                    <li>AI writes keyword-rich copy for every pin</li>
                    <li>A year of pins scheduled in one run</li>
                    <li>AI Auto Blog writes, publishes and pins new posts</li>
                    <li>Your whole archive and catalog gets pinned</li>
                    <li>Analytics show exactly what works</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ===================== HOW IT WORKS ===================== -->
<section class="uc-steps" id="how-it-works">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-pill-badge"><span class="uc-dot"></span> Simple 4-Step Process</div>
            <h2>From Website to <span class="uc-grad-text">Scheduled Pins</span> in Minutes</h2>
            <p class="uc-muted">No design skills. No Pinterest expertise. Scan your site, pick a look, set your pace — AI designs every pin, writes the copy, picks the board and publishes it all automatically.</p>
        </div>
        <div class="uc-steps-list">
            <?php
            $hpSteps = [
                ['📄', 'Setup', 'Add Your Website', ['We read your sitemap and list every page automatically.', 'Search and select pages in bulk — or select them all.'], 'Scanning a website to find pages for Pinterest pins'],
                ['🎨', 'Design', 'Match Pins to Your Brand', ['70 templates, 56 colour palettes and 130+ fonts with a live preview.', 'Import your own Canva design, or let AI pick the template per page.', 'Tiny and banner images are skipped automatically.'], 'Choosing pin templates, colours and fonts'],
                ['⚙️', 'Schedule', 'Set a Safe Publishing Pace', ['Pins per day with automatic gaps — or the new-account warm-up.', 'Pins per page and the gap before a page is pinned again.', 'Choose boards yourself or let AI place every pin.'], 'Pin publishing pace settings'],
                ['🚀', 'Approve', 'One Click. Fully Automated.', ['Review, edit or remove any pin, then approve.', 'Pins publish on autopilot all year. Anything unapproved waits in Drafts.'], 'One-click AI pin design and scheduling'],
            ];
            foreach ($hpSteps as $i => $st): $rev = $i % 2 === 1; $m = uc_media()['steps'][$i]; ?>
            <div class="uc-step-row <?= $rev ? 'uc-step-rev' : '' ?>">
                <div class="uc-step-copy">
                    <div class="uc-step-icon <?= $rev ? 'uc-ic-green' : 'uc-ic-red' ?>"><?= $st[0] ?></div>
                    <div class="uc-step-num">Step <?= $i + 1 ?> · <?= e($st[1]) ?></div>
                    <h3><?= e($st[2]) ?></h3>
                    <ul class="uc-step-points"><?php foreach ($st[3] as $pt): ?><li><span class="uc-check <?= $rev ? 'uc-check-red' : 'uc-check-green' ?>">✓</span> <?= e($pt) ?></li><?php endforeach; ?></ul>
                </div>
                <div class="uc-step-media <?= $rev ? 'uc-glow-green' : 'uc-glow-red' ?> hp-tilt">
                    <?php if ($m[0] === 'video'): ?>
                        <video muted loop playsinline preload="none" data-lazy-video aria-label="<?= e($st[4]) ?>"><source data-src="<?= e($m[1]) ?>" type="video/mp4"></video>
                    <?php else: ?>
                        <img src="<?= e($m[1]) ?>" alt="<?= e($st[4]) ?>" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="uc-center uc-steps-cta">
            <?= $hpCta('Start Scheduling Free →', 'btn-primary rg-shine uc-btn-pulse') ?>
            <a href="tutorials" class="btn-secondary uc-btn-outline">See It In Action</a>
        </div>
    </div>
</section>

<?php uc_section_autoblog($pdo, $user, ['slug' => 'home', 'autoblog_niche' => 'your niche'], ''); ?>

<!-- ===================== FEATURES (BENTO) ===================== -->
<section class="hp-bento-sec">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// EVERYTHING IN ONE PINTEREST MARKETING TOOL</div>
            <h2>Every Tool You Need to <span class="uc-grad-text">Grow on Pinterest</span></h2>
            <p class="uc-muted">From the first pin to a full year of scheduled content — design, copywriting, boards, scheduling and analytics in one place.</p>
        </div>
        <div class="hp-bento">
            <?php foreach ([
                ['wide', '⚡', 'Hundreds of Pins in 1 Click', 'Scan your site, select hundreds of pages and AI designs, writes and schedules every pin — with a live preview of 70 templates, palettes and fonts.', 'left'],
                ['', '🤖', 'AI Pin Copy', 'Titles, descriptions, alt text and keywords written for every pin.', 'right'],
                ['', '🗂️', 'Smart Boards', 'AI chooses the best board for each pin, or creates a new one.', 'up'],
                ['tall', '✍️', 'AI Auto Blog + Auto Pin', 'Paste a list of titles: AI writes every article with images, publishes to WordPress, Shopify, Wix or your site at your daily pace, and schedules pins for each post.', 'down'],
                ['', '📦', 'Bulk Pin Scheduler', 'Paste a list of titles or links and queue them all at once.', 'up'],
                ['', '♾️', 'Unlimited Pin Designs', '70 templates × 56 palettes × 130+ fonts, plus your Canva designs.', 'up'],
                ['wide', '📊', 'Pinterest Analytics', 'See which pins drive clicks and saves, compare by board, keyword or time, and clean out underperformers.', 'right'],
                ['', '👥', 'Team Management', 'Invite teammates without sharing your login.', 'left'],
            ] as $i => $f): ?>
                <article class="hp-bcard hp-spot <?= $f[0] ? 'hp-bcard-' . $f[0] : '' ?> hp-rv" data-rv="<?= $f[4] ?>" style="--d: <?= ($i % 4) * 70 ?>ms">
                    <div class="uc-fcard-ic"><?= $f[1] ?></div>
                    <h3><?= e($f[2]) ?></h3>
                    <p><?= e($f[3]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== TEMPLATE SHOWCASE ===================== -->
<section class="hp-show">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// PIN DESIGNS THAT GET SAVED</div>
            <h2>70 Pinterest Templates Modelled on Pins People Save</h2>
            <p class="uc-muted">Recipe bands, outfit collages, hairstyle numbers, home decor frames — every style applied to your own photos automatically.</p>
        </div>
    </div>
    <div class="hp-show-rows" aria-hidden="true">
        <div class="hp-show-track"><?php for ($i = 0; $i < 2; $i++) foreach (array_merge($hpHeroUp, $hpHeroDown) as $img): ?><img src="<?= e($img) ?>" alt="" width="170" height="255" loading="lazy" decoding="async"><?php endforeach; ?></div>
        <div class="hp-show-track hp-show-rev"><?php for ($i = 0; $i < 2; $i++) foreach (array_reverse(array_merge($hpHeroDown, $hpHeroUp)) as $img): ?><img src="<?= e($img) ?>" alt="" width="170" height="255" loading="lazy" decoding="async"><?php endforeach; ?></div>
    </div>
    <div class="uc-center"><a href="free-tools/pinterest-pin-maker/" class="btn-primary rg-shine">Try the Templates Free →</a></div>
</section>

<!-- ===================== MANUAL VS AUTOMATED ===================== -->
<section class="uc-compare">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// MANUAL VS AUTOMATED</div>
            <h2>Manual Pinning vs <?= e(APP_NAME) ?></h2>
        </div>
        <div class="uc-compare-wrap hp-rv" data-rv="zoom">
            <table class="uc-compare-table">
                <thead><tr><th scope="col">Task</th><th scope="col">Doing it by hand</th><th scope="col" class="uc-col-us">With <?= e(APP_NAME) ?></th></tr></thead>
                <tbody>
                <?php foreach ([
                    ['Designing pins', 'Canva, one pin at a time', '70 templates applied automatically'],
                    ['Titles & descriptions', 'Written one by one', 'AI writes keyword-rich copy'],
                    ['Choosing boards', 'Picked manually every time', 'AI picks or creates the board'],
                    ['Posting', 'Log in and post daily', 'A year scheduled in one run'],
                    ['Blog content', 'Write every post yourself', 'AI Auto Blog writes, publishes & pins'],
                    ['New accounts', 'Guess a safe pace', 'Built-in warm-up plan'],
                    ['Knowing what works', 'Guesswork', 'Pin analytics built in'],
                ] as $r): ?>
                    <tr><th scope="row"><?= e($r[0]) ?></th><td><span class="uc-x">✕</span> <?= e($r[1]) ?></td><td class="uc-col-us"><span class="uc-ok">✓</span> <?= e($r[2]) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===================== ANALYTICS ===================== -->
<?php $hpA = uc_media()['analytics']; ?>
<section class="uc-analytics">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// DATA-DRIVEN GROWTH</div>
            <h2>Powerful Pinterest Analytics That Convert</h2>
            <p class="uc-muted">Stop guessing what works. See exactly what drives traffic, so you can focus on what moves the needle.</p>
        </div>
        <div class="uc-analytics-grid">
            <div class="uc-acard hp-spot hp-rv" data-rv="left"><div class="uc-astat">Up to 5x more traffic</div><img src="<?= e($hpA[0]) ?>" alt="Pinterest analytics dashboard showing traffic growth" loading="lazy"><h3>Analytics</h3><p>Understand where your traffic comes from and make data-driven decisions.</p></div>
            <div class="uc-acard hp-spot hp-rv" data-rv="up" style="--d: 90ms"><div class="uc-astat">47% higher engagement</div><img src="<?= e($hpA[1]) ?>" alt="Deleting underperforming pins" loading="lazy"><h3>Delete Underperforming Pins</h3><p>Remove weak pins to raise your engagement rate.</p></div>
            <div class="uc-acard hp-spot hp-rv" data-rv="right" style="--d: 180ms"><img src="<?= e($hpA[2]) ?>" alt="Top pin performance report" loading="lazy"><h3>Top Pin Performance</h3><p>See which pins drive the most clicks, saves and traffic.</p></div>
            <div class="uc-acard uc-acard-wide hp-spot hp-rv" data-rv="zoom">
                <img src="<?= e($hpA[3]) ?>" alt="Analytics breakdown by board, URL, keyword, title, description and time" loading="lazy">
                <div><h3>Break It Down Any Way You Want</h3><p>Filter and compare performance across every angle that matters.</p>
                    <div class="uc-chips"><span>Boards</span><span>URLs</span><span>Keywords</span><span>Titles</span><span>Descriptions</span><span>Time</span></div></div>
            </div>
        </div>
    </div>
</section>

<?php
$hpUc = ['slug' => 'home', 'pricing_title' => 'Simple, Transparent Pricing', 'testimonials_title' => 'Loved by Pinterest Marketers'];
uc_section_pricing($pdo, $user, $hpUc, '');
uc_section_testimonials($pdo, $user, $hpUc, '');
uc_section_real_results($pdo, $user, $hpUc, '');
?>

<!-- ===================== USE CASES ===================== -->
<section class="hp-uses">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// USE CASES</div>
            <h2>Pinterest Automation for <span class="uc-grad-text">Every Kind of Website</span></h2>
            <p class="uc-muted"><?= count(uc_catalog()) ?> use cases — stores, blogs, marketplaces, creators and agencies.</p>
        </div>
        <div class="uc-related-grid">
            <?php $ui = 0; foreach ($hpFeatured as $slug => $c): ?>
                <a href="use-cases/<?= e($slug) ?>/" class="uc-rcard hp-spot hp-rv" data-rv="<?= ['left', 'up', 'down', 'right'][$ui % 4] ?>" style="--d: <?= ($ui++ % 4) * 60 ?>ms"><span class="uc-rcard-ic"><?= $c['icon'] ?></span><b><?= e($c['title']) ?></b><small><?= e($c['summary']) ?></small></a>
            <?php endforeach; ?>
            <a href="use-cases/" class="uc-rcard hp-rcard-all hp-rv" data-rv="zoom"><span class="uc-rcard-ic">✨</span><b>See all <?= count(uc_catalog()) ?> use cases</b><small>Find the setup that fits your site →</small></a>
        </div>
        <div class="hp-niches">
            <?php foreach (uc_groups() as $gk => $gl): ?>
                <div class="hp-niche-group hp-rv" data-rv="up">
                    <h3><?= e($gl) ?></h3>
                    <div class="hp-niche-links">
                        <?php foreach (uc_catalog() as $slug => $c): if ($c['group'] !== $gk) continue; ?>
                            <a href="use-cases/<?= e($slug) ?>/"><?= $c['icon'] ?> <?= e($c['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php uc_section_free_tools($pdo, $user, ['slug' => 'home', 'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-image-resizer', 'ai-image-creater', 'etsy-tags-generator']], ''); ?>

<!-- ===================== WHO ===================== -->
<section class="uc-who">
    <div class="container">
        <div class="uc-head hp-rv" data-rv="up">
            <div class="uc-eyebrow">// WHO IT'S FOR</div>
            <h2>Made for people who <span class="uc-red">monetize traffic</span></h2>
            <p class="uc-muted">Whether the money comes from products, affiliate links or ads — <?= e(APP_NAME) ?> keeps the Pinterest side running itself.</p>
        </div>
        <div class="uc-who-grid">
            <div class="uc-who-card hp-spot hp-rv" data-rv="left"><h3>Bloggers &amp; affiliates</h3><p>Every post becomes several fresh pins pointing at your content — compounding traffic from a platform where pins keep working for months.</p></div>
            <div class="uc-who-card uc-who-hl rg-border-glow hp-spot hp-rv" data-rv="up"><h3>E-commerce stores</h3><p>Every product becomes ongoing Pinterest creative that sends buyers straight to your product pages.</p></div>
            <div class="uc-who-card hp-spot hp-rv" data-rv="right"><h3>Agencies</h3><p>Manage Pinterest for every client site from one dashboard, with Team Management instead of shared logins.</p></div>
        </div>
    </div>
</section>

<section class="uc-cta-red">
    <div class="container uc-cta-red-inner hp-rv" data-rv="up">
        <div><h2>Make Pinterest the easy part.</h2><p>Create pins, schedule a year in a click, and bring visitors to your site.</p></div>
        <?= $hpCta('Try ' . APP_NAME . ' Free →', 'uc-cta-pill rg-shine') ?>
    </div>
</section>

<!-- ===================== FAQ ===================== -->
<section class="uc-faq" id="faq">
    <div class="container">
        <h2 class="uc-section-title hp-rv" data-rv="up">Pinterest Automation — Frequently Asked Questions</h2>
        <div class="uc-faq-grid">
            <?php foreach ($hpFaq as $i => $q): ?>
                <details class="hp-rv" data-rv="<?= $i % 2 ? 'right' : 'left' ?>"><summary><?= e($q[0]) ?></summary><p><?= e($q[1]) ?></p></details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="uc-cta-dark">
    <div class="uc-cta-dark-glow" aria-hidden="true"></div>
    <div class="container uc-cta-dark-inner hp-rv" data-rv="zoom">
        <h2>Ready to turn your pages into Pinterest traffic?</h2>
        <p>Free to start. Pay only when you want more pins live on Pinterest.</p>
        <div class="uc-cta-dark-actions">
            <?= $hpCta('Try It On Your Site →', 'btn-primary rg-shine rg-border-glow') ?>
            <a href="#pricing" class="uc-cta-dark-link rg-text-blink">See pricing</a>
        </div>
    </div>
</section>

<?php if (!$user): ?>
<div class="hp-sticky" id="hpSticky" aria-hidden="true">
    <span>📌 Schedule a year of pins in minutes</span>
    <a href="auth/register" class="btn-primary rg-shine" tabindex="-1">Start Free</a>
</div>
<?php endif; ?>

<!-- ===================== Create Pin popup ===================== -->
<div class="modal-overlay hw-modal-overlay" id="hwModalOverlay" style="display:none;">
    <div class="modal-box hw-modal-box">
        <div class="modal-header">
            <h2>Create Your Pin</h2>
            <button type="button" class="modal-close" id="hwModalClose">&times;</button>
        </div>

        <div class="hw-modal-body">
            <div class="hw-modal-left">
                <div class="form-row">
                    <label>Pin Title <span class="muted">(fetched from your link — edit if you like)</span></label>
                    <input type="text" id="hwTitle" placeholder="Fetching title…">
                </div>

                <div class="two-col">
                    <div class="form-row">
                        <label>Size</label>
                        <select id="hwSize">
                            <option value="2:3">1000 × 1500 px (2:3)</option>
                            <option value="9:16">1080 × 1920 px (9:16)</option>
                            <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                            <option value="1:1">1000 × 1000 px (1:1)</option>
                        </select>
                    </div>
                    <input type="hidden" id="hwImageType" value="auto">
                </div>
                <input type="hidden" id="hwCollageCount" value="4">

                <div class="form-row">
                    <label>Pin Templates &amp; Styles</label>
                    <input type="hidden" id="hwImageStyle" value="auto" data-tplpick>
                </div>

                <div class="two-col">
                    <div class="form-row">
                        <label>CTA</label>
                        <select id="hwCtaMode">
                            <option value="auto">Auto (added automatically based on the title)</option>
                            <option value="custom">Custom text</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div class="form-row" id="hwCtaCustomWrap" style="display:none;">
                        <label>CTA Text</label>
                        <input type="text" id="hwCtaText" list="hwCtaExamples" placeholder="e.g. Explore All Ideas">
                        <datalist id="hwCtaExamples">
                            <option value="Explore All Ideas"><option value="Visit Site"><option value="Explore Now">
                            <option value="See How"><option value="Get the Recipe"><option value="Learn More">
                        </datalist>
                    </div>
                </div>

                <div class="form-row">
                    <div class="ft-switch-row">
                        <div class="ft-switch-label">Show Website At Bottom</div>
                        <label class="ft-switch"><input type="checkbox" id="hwShowWebsite" checked><span></span></label>
                    </div>
                    <input type="text" id="hwWebsite" placeholder="example.com" style="margin-top:8px;">
                </div>

                <div class="form-row">
                    <label class="checkbox-row"><input type="checkbox" id="hwPaletteEnabled"> Use my brand color palette <span class="muted">(optional)</span></label>
                </div>
                <div id="hwPaletteWrap" style="display:none;">
                    <div class="form-row">
                        <label>Number of Colors</label>
                        <select id="hwPaletteCount">
                            <option value="3" selected>3 Colors</option>
                            <option value="4">4 Colors</option>
                        </select>
                    </div>
                    <div class="two-col">
                        <div class="form-row"><label>Color 1</label><input type="color" id="hwColor1" value="#E91E63"></div>
                        <div class="form-row"><label>Color 2</label><input type="color" id="hwColor2" value="#FFEB3B"></div>
                    </div>
                    <div class="two-col">
                        <div class="form-row"><label>Color 3</label><input type="color" id="hwColor3" value="#212121"></div>
                        <div class="form-row" id="hwColor4Wrap" style="display:none;"><label>Color 4</label><input type="color" id="hwColor4" value="#FFFFFF"></div>
                    </div>
                    <div class="two-col">
                        <div class="form-row"><label>Website Text Color</label><input type="color" id="hwWebsiteTextColor" value="#FFFFFF"></div>
                        <div class="form-row"><label>Website Background Color</label><input type="color" id="hwWebsiteBgColor" value="#E91E63"></div>
                    </div>
                    <div class="two-col">
                        <div class="form-row"><label>CTA Text Color</label><input type="color" id="hwCtaTextColor" value="#FFFFFF"></div>
                        <div class="form-row"><label>CTA Background Color</label><input type="color" id="hwCtaBgColor" value="#E91E63"></div>
                    </div>
                </div>

                <div class="hw-modal-actions">
                    <button type="button" class="btn-primary" id="hwGenerateBtn" style="width:100%;">✨ Create Now</button>
                </div>
                <div class="hw-modal-attempts" id="hwModalAttempts"></div>
            </div>

            <div class="hw-modal-right">
                <div id="hwEmptyState" class="ft-empty">
                    <div class="ft-empty-icon">📌</div>
                    <div class="ft-empty-title">No Pin Generated</div>
                    <div class="ft-empty-sub">Adjust the options and hit Create Now.</div>
                </div>
                <div id="hwLoadingState" class="ft-loading" style="display:none;">
                    <div class="ft-spinner"></div>
                    <div class="ft-loading-title">Designing Your Pin</div>
                    <div class="ft-loading-sub">AI is creating the image and laying out the design — usually under a minute.</div>
                </div>
                <div id="hwResult" style="display:none;text-align:center;">
                    <img id="hwResultImg" style="max-width:100%;border-radius:10px;border:1px solid var(--border);">
                    <div class="hw-result-actions">
                        <a id="hwDownloadWatermarked" class="btn-secondary" download>Download (with watermark)</a>
                        <a id="hwDownloadClean" href="auth/register?from=hw_widget" class="btn-primary">Create Free Account — Download Without Watermark</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById('hwModalOverlay');
    const openModal = () => { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; };
    const closeModal = () => { overlay.style.display = 'none'; document.body.style.overflow = ''; };
    document.getElementById('hwModalClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

    // Toggles inside the popup
    document.getElementById('hwCtaMode').addEventListener('change', function () {
        document.getElementById('hwCtaCustomWrap').style.display = this.value === 'custom' ? '' : 'none';
    });
    document.getElementById('hwShowWebsite').addEventListener('change', function () {
        document.getElementById('hwWebsite').style.display = this.checked ? '' : 'none';
    });
    document.getElementById('hwPaletteEnabled').addEventListener('change', function () {
        document.getElementById('hwPaletteWrap').style.display = this.checked ? '' : 'none';
    });
    document.getElementById('hwPaletteCount').addEventListener('change', function () {
        document.getElementById('hwColor4Wrap').style.display = this.value === '4' ? '' : 'none';
    });

    // Step 1: submit the hero widget's URL, open popup, resolve title.
    document.getElementById('hwStartForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const url = document.getElementById('hwStartUrl').value.trim();
        if (!url) return;

        const startBtn = this.querySelector('button[type="submit"]');
        startBtn.disabled = true;

        document.getElementById('hwTitle').value = '';
        document.getElementById('hwTitle').placeholder = 'Fetching title…';
        document.getElementById('hwWebsite').value = '';
        document.getElementById('hwEmptyState').style.display = '';
        document.getElementById('hwResult').style.display = 'none';
        document.getElementById('hwLoadingState').style.display = 'none';
        document.getElementById('hwGenerateBtn').textContent = '✨ Create Now';
        document.getElementById('hwGenerateBtn').disabled = false;
        document.getElementById('hwModalAttempts').textContent = '';
        openModal();

        try {
            const body = new URLSearchParams();
            body.set('hw_action', 'resolve');
            body.set('url', url);
            const res = await fetch(window.location.pathname, { method: 'POST', body });
            const data = await res.json();
            if (data.ok) {
                document.getElementById('hwTitle').value = data.title || '';
                document.getElementById('hwTitle').placeholder = 'e.g. 15 Easy Weeknight Dinners';
                document.getElementById('hwWebsite').value = data.website || '';
            } else {
                document.getElementById('hwTitle').placeholder = 'Enter a title for this pin';
            }
        } catch (err) {
            document.getElementById('hwTitle').placeholder = 'Enter a title for this pin';
        } finally {
            startBtn.disabled = false;
        }
    });

    // Step 2: Create Now / Recreate.
    document.getElementById('hwGenerateBtn').addEventListener('click', async function () {
        const title = document.getElementById('hwTitle').value.trim();
        if (!title) { alert('Please enter a title for the pin.'); return; }
        if (this.disabled) return;
        this.disabled = true;

        document.getElementById('hwEmptyState').style.display = 'none';
        document.getElementById('hwResult').style.display = 'none';
        document.getElementById('hwLoadingState').style.display = '';

        const palette = {
            enabled: document.getElementById('hwPaletteEnabled').checked,
            colors: [
                document.getElementById('hwColor1').value,
                document.getElementById('hwColor2').value,
                document.getElementById('hwColor3').value,
            ],
            website_text_color: document.getElementById('hwWebsiteTextColor').value,
            website_bg_color: document.getElementById('hwWebsiteBgColor').value,
            cta_text_color: document.getElementById('hwCtaTextColor').value,
            cta_bg_color: document.getElementById('hwCtaBgColor').value,
        };
        if (document.getElementById('hwPaletteCount').value === '4') {
            palette.colors.push(document.getElementById('hwColor4').value);
        }

        const body = new URLSearchParams();
        body.set('hw_action', 'generate');
        body.set('title', title);
        body.set('website', document.getElementById('hwWebsite').value.trim());
        body.set('show_website', document.getElementById('hwShowWebsite').checked ? '1' : '');
        body.set('size', document.getElementById('hwSize').value);
        body.set('image_style', document.getElementById('hwImageStyle').value);
        body.set('image_type', document.getElementById('hwImageType').value);
        body.set('collage_count', document.getElementById('hwCollageCount').value);
        body.set('cta_mode', document.getElementById('hwCtaMode').value);
        body.set('cta_text', document.getElementById('hwCtaText').value.trim());
        if (palette.enabled) body.set('color_palette', JSON.stringify(palette));

        try {
            const res = await fetch(window.location.pathname, { method: 'POST', body });
            const data = await res.json();
            document.getElementById('hwLoadingState').style.display = 'none';
            if (!data.ok) {
                document.getElementById('hwEmptyState').style.display = '';
                alert(data.error || 'Could not generate a pin.');
                if (data.limit_reached) {
                    document.getElementById('hwModalAttempts').innerHTML = 'You\'ve used your free pins — <a href="auth/register">sign up free</a> to keep going';
                }
                return;
            }
            document.getElementById('hwResultImg').src = data.watermarked_path;
            document.getElementById('hwDownloadWatermarked').href = data.watermarked_path;
            document.getElementById('hwResult').style.display = '';
            this.textContent = '🔁 Recreate';

            const remainingText = data.remaining > 0
                ? data.remaining + ' free pin' + (data.remaining === 1 ? '' : 's') + ' left in this session'
                : '';
            document.getElementById('hwModalAttempts').textContent = remainingText;
            document.getElementById('hwAttempts').textContent = remainingText;
        } catch (err) {
            document.getElementById('hwLoadingState').style.display = 'none';
            document.getElementById('hwEmptyState').style.display = '';
            alert('Something went wrong. Please try again.');
        } finally {
            this.disabled = false;
        }
    });
})();
</script>

<style>
/* ===================== Home page ===================== */
.hp { --hp-shadow: 0 1px 2px rgba(17,24,39,.04), 0 12px 32px rgba(17,24,39,.08); --hp-shadow-lg: 0 2px 6px rgba(17,24,39,.05), 0 28px 60px rgba(17,24,39,.14); }

/* Hero with moving aurora light */
.hp-hero { position: relative; padding: 64px 0 56px; overflow: hidden; isolation: isolate; }
.hp-aurora { position: absolute; inset: -20% -10% auto -10%; height: 140%; z-index: -2; pointer-events: none; }
.hp-aurora i { position: absolute; border-radius: 50%; opacity: .8; animation: hpDrift 18s ease-in-out infinite alternate; }
.hp-aurora i:nth-child(1) { width: 680px; height: 680px; background: radial-gradient(circle, rgba(255,184,195,.75) 0%, rgba(255,184,195,0) 65%); top: -160px; left: -200px; }
.hp-aurora i:nth-child(2) { width: 620px; height: 620px; background: radial-gradient(circle, rgba(185,246,212,.75) 0%, rgba(185,246,212,0) 65%); top: 40px; right: -200px; animation-delay: -6s; }
.hp-aurora i:nth-child(3) { width: 480px; height: 480px; background: radial-gradient(circle, rgba(255,227,168,.6) 0%, rgba(255,227,168,0) 65%); bottom: -80px; left: 30%; animation-delay: -12s; }
@keyframes hpDrift { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(60px, 40px) scale(1.12); } 100% { transform: translate(-40px, 20px) scale(.95); } }
.hp-grid-bg { position: absolute; inset: 0; z-index: -1; background-image: linear-gradient(rgba(17,24,39,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(17,24,39,.045) 1px, transparent 1px); background-size: 44px 44px; -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 75%); mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 75%); }
.hp-hero::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: linear-gradient(90deg, var(--red), #12c464, var(--red)); background-size: 200% 100%; animation: ucFlow 5s linear infinite; }
.hp-hero-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 44px; align-items: center; }
.hp-h1 { font-size: 50px; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; color: var(--dark); margin: 0 0 18px; }
.hp-btn-lg { padding: 14px 26px !important; font-size: 16px !important; border-radius: 30px !important; }
.hp-trust { display: flex; align-items: center; gap: 14px; margin-top: 22px; }
.hp-avatars { display: flex; }
.hp-avatars img { width: 36px; height: 36px; border-radius: 50%; border: 3px solid #fff; margin-left: -10px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,.12); }
.hp-avatars img:first-child { margin-left: 0; }
.hp-stars { color: #f5a623; letter-spacing: 2px; font-size: 15px; }
.hp-trust small { color: var(--gray); font-size: 12.5px; }
.hp-hero-visual { position: relative; height: 540px; display: flex; gap: 16px; justify-content: center; transition: transform .25s ease-out; transform-style: preserve-3d; }
.hp-glow-ring { position: absolute; width: 420px; height: 420px; left: 50%; top: 50%; transform: translate(-50%, -50%); border-radius: 50%; background: conic-gradient(from 0deg, rgba(230,0,35,.35), rgba(18,196,100,.35), rgba(255,184,0,.3), rgba(230,0,35,.35)); -webkit-mask: radial-gradient(circle, #000 20%, transparent 70%); mask: radial-gradient(circle, #000 20%, transparent 70%); animation: hpSpin 14s linear infinite; z-index: -1; }
@keyframes hpSpin { to { transform: translate(-50%, -50%) rotate(360deg); } }
.hp-hero-visual .uc-scroll-track img { box-shadow: 0 18px 38px rgba(17,24,39,.2); }

/* Glass card + lift shadow */
.hp-glass { background: rgba(255,255,255,.96) !important; box-shadow: var(--hp-shadow-lg) !important; }
.hp-mini-link { margin: 10px 0 0; font-size: 13.5px; }
.hp-mini-link a { color: var(--red); font-weight: 700; }
.hp-lift { box-shadow: var(--hp-shadow-lg); }

/* Mouse-follow spotlight on cards */
.hp-spot { position: relative; overflow: hidden; }
.hp-spot::after { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: 0; transition: opacity .3s ease; background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 50%), rgba(230,0,35,.10), rgba(18,196,100,.06) 40%, transparent 70%); }
.hp-spot:hover::after { opacity: 1; }

/* Platforms */
.hp-platforms { padding: 20px 0 34px; }
.hp-platforms-title { text-align: center; color: var(--gray); font-weight: 700; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 16px; }
.hp-platform-row { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.hp-platform { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 30px; padding: 9px 16px; font-weight: 700; font-size: 14px; color: var(--dark); text-decoration: none; box-shadow: var(--hp-shadow); transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease; }
.hp-platform:hover { transform: translateY(-3px); border-color: var(--red); box-shadow: 0 14px 30px rgba(230,0,35,.14); }

/* Problem → solution */
.hp-ps { padding: 80px 0; background: linear-gradient(180deg, #fff, #fafafa); }
.hp-ps-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 26px; align-items: center; max-width: 1050px; margin: 0 auto; }
.hp-ps-card { background: #fff; border: 1px solid var(--border); border-radius: 20px; padding: 30px; box-shadow: var(--hp-shadow); }
.hp-ps-card h3 { margin: 0 0 16px; font-size: 21px; }
.hp-ps-card ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.hp-ps-card li { display: flex; gap: 10px; font-size: 15px; line-height: 1.5; }
.hp-ps-pain li::before { content: '✕'; color: #dc2626; font-weight: 800; }
.hp-ps-gain { background: linear-gradient(160deg, #fff 0%, #f1fff7 100%); box-shadow: var(--hp-shadow-lg); }
.hp-ps-gain li::before { content: '✓'; color: #0b8a45; font-weight: 800; }
.hp-ps-arrow { width: 60px; height: 60px; border-radius: 50%; display: grid; place-items: center; font-size: 26px; color: #fff; background: linear-gradient(135deg, var(--red), #12c464); box-shadow: 0 10px 30px rgba(230,0,35,.3); animation: ucPulseRed 2.4s infinite; }

/* Bento features */
.hp-bento-sec { padding: 84px 0; background: radial-gradient(circle at 10% 10%, #fff3f5 0, transparent 40%), radial-gradient(circle at 90% 80%, #effff6 0, transparent 40%); }
.hp-bento { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: minmax(180px, auto); gap: 18px; max-width: 1150px; margin: 0 auto; }
.hp-bcard { background: #fff; border: 1px solid var(--border); border-radius: 20px; padding: 26px; box-shadow: var(--hp-shadow); transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease; }
.hp-bcard:hover { transform: translateY(-6px); box-shadow: var(--hp-shadow-lg); border-color: #ffd3d8; }
.hp-bcard-wide { grid-column: span 2; background: linear-gradient(135deg, #fff 0%, #fff6f7 100%); }
.hp-bcard-tall { grid-row: span 2; background: linear-gradient(180deg, #fff 0%, #effff6 100%); }
.hp-bcard h3 { margin: 0 0 8px; font-size: 19px; }
.hp-bcard p { margin: 0; color: var(--gray); font-size: 14.5px; line-height: 1.6; }
.hp-bcard:hover .uc-fcard-ic { transform: rotate(-8deg) scale(1.08); }

/* Template showcase rows */
.hp-show { padding: 80px 0; overflow: hidden; background: #0d0d10; color: #fff; }
.hp-show .uc-head h2 { color: #fff; }
.hp-show .uc-muted { color: rgba(255,255,255,.7); }
.hp-show-rows { display: flex; flex-direction: column; gap: 16px; transform: rotate(-3deg); margin: 10px -40px 30px; }
.hp-show-track { display: flex; gap: 16px; width: max-content; animation: ucScrollX 60s linear infinite; }
.hp-show-rev { animation-direction: reverse; }
.hp-show-track img { width: 170px; aspect-ratio: 2/3; object-fit: cover; border-radius: 14px; box-shadow: 0 16px 40px rgba(0,0,0,.45); transition: transform .3s ease; }
.hp-show-track img:hover { transform: scale(1.06) translateY(-6px); }
.hp-show-rows:hover .hp-show-track { animation-play-state: paused; }

/* Use cases on home */
.hp-uses { padding: 70px 0 30px; }
.hp-rcard-all { background: linear-gradient(135deg, var(--red), #ff4d6a) !important; color: #fff !important; border-color: transparent !important; }
.hp-rcard-all small { color: rgba(255,255,255,.85) !important; }

/* All use cases as grouped links */
.hp-niches { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; max-width: 1150px; margin: 34px auto 0; }
.hp-niche-group { background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 20px; box-shadow: var(--hp-shadow); }
.hp-niche-group h3 { font-size: 15px; margin: 0 0 12px; }
.hp-niche-links { display: flex; flex-wrap: wrap; gap: 6px; }
.hp-niche-links a { font-size: 12.5px; font-weight: 600; color: var(--dark); background: #fafafa; border: 1px solid var(--border); border-radius: 20px; padding: 5px 10px; text-decoration: none; transition: border-color .2s ease, color .2s ease; }
.hp-niche-links a:hover { border-color: var(--red); color: var(--red); }

/* 3D tilt on step media */
.hp-tilt { transition: transform .25s ease-out; }

/* Sticky mobile CTA */
.hp-sticky { position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 900; display: none; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 10px 10px 16px; background: rgba(13,13,16,.96); color: #fff; border-radius: 16px; box-shadow: 0 16px 40px rgba(0,0,0,.35); font-weight: 600; font-size: 14px; transform: translateY(140%); transition: transform .35s ease; }
.hp-sticky.is-on { transform: translateY(0); }
@media (max-width: 760px) { .hp-sticky { display: flex; } }

/* ===== Two-way scroll reveal: from below when scrolling down, from above when scrolling up ===== */
.hp-rv { opacity: 0; transition: opacity .8s ease var(--d, 0ms), transform .8s cubic-bezier(.2,.7,.2,1) var(--d, 0ms); }
.hp-rv[data-rv="up"] { transform: translateY(46px); }
.hp-rv[data-rv="down"] { transform: translateY(-46px); }
.hp-rv[data-rv="left"] { transform: translateX(-60px); }
.hp-rv[data-rv="right"] { transform: translateX(60px); }
.hp-rv[data-rv="zoom"] { transform: scale(.9); }
.hp-rv.hp-from-top[data-rv="up"], .hp-rv.hp-from-top[data-rv="zoom"] { transform: translateY(-46px); }
.hp-rv.hp-from-top[data-rv="down"] { transform: translateY(46px); }
.hp-rv.is-in { opacity: 1; transform: none; }
.rg-reveal.hp-from-top:not(.rg-in) { transform: translateY(-34px); }
.uc-step-row.hp-from-top:not(.rg-in) { transform: translateY(-46px); }

/* ===== Create Pin popup (unchanged behaviour) ===== */
.hw-modal-box { max-width: 920px; }
.hw-modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; max-height: 78vh; overflow-y: auto; padding-top: 6px; }
.hw-modal-left .form-row { margin-bottom: 14px; }
.hw-modal-attempts { text-align: center; font-size: 12.5px; color: var(--gray); margin-top: 8px; }
.hw-result-actions { display: flex; gap: 10px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.hw-modal-right { display: flex; flex-direction: column; justify-content: center; }
#hwGenerateBtn:disabled, .uc-start-form button:disabled { opacity: .6; cursor: not-allowed; }

@media (max-width: 980px) {
    .hp-hero-grid { grid-template-columns: 1fr; }
    .hp-h1 { font-size: 36px; }
    .hp-hero-visual { height: 380px; }
    .hp-bento { grid-template-columns: 1fr 1fr; }
    .hp-bcard-tall { grid-row: auto; }
    .hw-modal-body { grid-template-columns: 1fr; max-height: 74vh; }
}
@media (max-width: 760px) {
    .hp-ps-grid { grid-template-columns: 1fr; }
    .hp-ps-arrow { margin: 0 auto; transform: rotate(90deg); }
    .hp-bento { grid-template-columns: 1fr; }
    .hp-bcard-wide { grid-column: auto; }
    .hp-show-track img { width: 130px; }
    .hp-trust { flex-wrap: wrap; }
}
/* Phones: slide in vertically only, so nothing starts off the side of the screen */
@media (max-width: 760px) {
    .hp-rv[data-rv="left"], .hp-rv[data-rv="right"] { transform: translateY(40px); }
    .hp-rv.hp-from-top[data-rv="left"], .hp-rv.hp-from-top[data-rv="right"] { transform: translateY(-40px); }
    .hp-show-rows { margin-left: 0; margin-right: 0; transform: none; }
    .hp-aurora { inset: -10% 0 auto 0; }
    .hp-niches { grid-template-columns: 1fr; }
    .hp-h1 { font-size: 31px; }
}
@media (prefers-reduced-motion: reduce) {
    .hp-rv { opacity: 1 !important; transform: none !important; }
    .hp-aurora i, .hp-glow-ring { animation: none !important; }
}
</style>

<script>
/* Home: two-way reveals, counters, progress bar, parallax, spotlight, tilt, pricing toggle, sticky CTA. */
(function () {
    'use strict';
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var targets = document.querySelectorAll('.hp-rv, .rg-reveal, .uc-step-row');

    function show(el) { el.classList.remove('hp-from-top'); el.classList.add(el.classList.contains('hp-rv') ? 'is-in' : 'rg-in'); }
    if (reduce || !('IntersectionObserver' in window)) {
        targets.forEach(show);
    } else {
        // Not unobserved: when an element leaves above the viewport it is reset to animate
        // in from the top on the way back up; leaving below, it animates in from the bottom.
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var el = en.target;
                if (en.isIntersecting) { show(el); return; }
                el.classList.remove('is-in', 'rg-in');
                el.classList.toggle('hp-from-top', en.boundingClientRect.top < 0);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
        targets.forEach(function (el) { io.observe(el); });
    }

    // Count-up numbers (replay each time they come into view)
    function countUp(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce) { el.textContent = end.toLocaleString(); return; }
        var t0 = null;
        (function step(t) {
            if (!t0) t0 = t;
            var p = Math.min(1, (t - t0) / 1400);
            el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
        })(performance.now());
    }
    var nums = document.querySelectorAll('[data-count]');
    if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) { entries.forEach(function (en) { if (en.isIntersecting) countUp(en.target); }); }, { threshold: 0.6 });
        nums.forEach(function (n) { cio.observe(n); });
    } else nums.forEach(countUp);

    // Scroll progress, hero parallax, sticky CTA
    var bar = document.querySelector('.uc-progress span');
    var aurora = document.querySelector('.hp-aurora');
    var sticky = document.getElementById('hpSticky');
    var ticking = false;
    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            var h = document.documentElement.scrollHeight - innerHeight, y = scrollY;
            if (bar) bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0) + ')';
            if (aurora && !reduce) aurora.style.transform = 'translateY(' + (y * 0.25) + 'px)';
            if (sticky) { var on = y > 700 && y < h - 400; sticky.classList.toggle('is-on', on); sticky.setAttribute('aria-hidden', on ? 'false' : 'true'); }
            ticking = false;
        });
    }
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (!reduce && matchMedia('(hover: hover)').matches) {
        // Mouse-follow light on cards
        document.addEventListener('pointermove', function (e) {
            var c = e.target.closest && e.target.closest('.hp-spot');
            if (!c) return;
            var r = c.getBoundingClientRect();
            c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            c.style.setProperty('--my', (e.clientY - r.top) + 'px');
        }, { passive: true });
        // Gentle 3D tilt on hero pins and step media
        document.querySelectorAll('.hp-hero-visual, .hp-tilt').forEach(function (el) {
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
                el.style.transform = 'perspective(900px) rotateY(' + (x * 6) + 'deg) rotateX(' + (-y * 6) + 'deg)';
            });
            el.addEventListener('pointerleave', function () { el.style.transform = ''; });
        });
    }

    // Speed: pause animations in sections that are off-screen, and load/play step videos only
    // while they're visible (they used to download on page load).
    if ('IntersectionObserver' in window) {
        var secIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { en.target.classList.toggle('is-off', !en.isIntersecting); });
        }, { rootMargin: '150px 0px' });
        document.querySelectorAll('body > section, .uc-marquee').forEach(function (s) { secIO.observe(s); });
        var vidIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var v = en.target;
                if (en.isIntersecting) {
                    if (!v.dataset.loaded) {
                        v.querySelectorAll('source[data-src]').forEach(function (s) { s.src = s.getAttribute('data-src'); });
                        v.load();
                        v.dataset.loaded = '1';
                    }
                    var p = v.play(); if (p && p.catch) p.catch(function () {});
                } else if (!v.paused) v.pause();
            });
        }, { rootMargin: '200px 0px' });
        document.querySelectorAll('video[data-lazy-video]').forEach(function (v) { vidIO.observe(v); });
    } else {
        document.querySelectorAll('video[data-lazy-video]').forEach(function (v) {
            v.querySelectorAll('source[data-src]').forEach(function (s) { s.src = s.getAttribute('data-src'); });
            v.load(); v.play();
        });
    }

    // Pricing monthly / annual
    document.querySelectorAll('.uc-billing-toggle').forEach(function (tg) {
        tg.addEventListener('change', function () {
            var yearly = tg.checked;
            document.querySelectorAll('.uc-plan').forEach(function (c) {
                var d = c.dataset, disc = +(yearly ? d.yearlyDiscount : d.monthlyDiscount), q = function (s) { return c.querySelector(s); };
                if (q('.js-price')) q('.js-price').textContent = yearly ? d.yearlyMonthlyEquiv : d.monthlyFinal;
                if (q('.js-off')) { q('.js-off').style.display = disc > 0 ? 'inline-block' : 'none'; q('.js-off').textContent = disc + '% OFF'; }
                if (q('.js-was')) { q('.js-was').style.display = disc > 0 ? 'block' : 'none'; q('.js-was').textContent = 'Was $' + (yearly ? (d.yearlyBase / 12).toFixed(2) : d.monthlyBase) + '/month'; }
                if (q('.js-note')) { q('.js-note').hidden = !yearly; q('.js-note').textContent = 'Billed annually at $' + d.yearlyFinal; }
                if (q('.js-buy')) q('.js-buy').setAttribute('href', yearly ? d.checkoutYearly : d.checkoutMonthly);
            });
        });
    });
})();
</script>

<?php render_site_footer($pdo); ?>
<script src="assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/assets/js/template-picker.js') ?: time() ?>"></script>
</body>
</html>
