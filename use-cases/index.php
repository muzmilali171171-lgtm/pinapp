<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/footer_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';

$user = current_user($pdo);

/**
 * Each live use case gets its own dedicated, keyword-optimized landing page
 * under /use-cases/{slug}/. Add a new entry here (and a matching folder)
 * whenever a new use case page ships — this hub just links out to them.
 */
require_once __DIR__ . '/../includes/use_case_functions.php';
$useCases = [];
foreach (uc_catalog() as $slug => $c) {
    $useCases[] = ['slug' => $slug, 'icon' => $c['icon'], 'title' => $c['title'], 'summary' => $c['summary'], 'group' => $c['group']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Pinterest Automation Use Cases: Shopify, Etsy, WordPress & More | ' . APP_NAME,
    'description' => 'See how ' . APP_NAME . ' automates Pinterest for Shopify, WooCommerce, Amazon, Etsy, Redbubble, Printify, WordPress, food and home decor websites.',
    'keywords' => 'Pinterest automation use cases, Pinterest for Shopify, Pinterest for Etsy, Pinterest for WordPress, Pinterest for food bloggers, Pinterest for home decor, Pinterest for print on demand',
    'canonical' => rtrim(APP_URL, '/') . '/use-cases/',
]); ?>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="uc-hub-hero">
    <div class="container">
        <div class="uc-hub-badge">// USE CASES</div>
        <h1>Pinterest automation for every kind of website</h1>
        <p>Whether you sell products, publish content, or run Pinterest for clients — see exactly how <?= e(APP_NAME) ?> fits your business.</p>
    </div>
</section>

<section class="uc-hub-grid-section">
    <div class="container">
        <nav class="uc-hub-jump" aria-label="Use case groups">
            <?php foreach (uc_groups() as $gk => $gl): ?><a href="#<?= e($gk) ?>"><?= e($gl) ?></a><?php endforeach; ?>
        </nav>
        <?php foreach (uc_groups() as $gk => $gl): ?>
            <h2 class="uc-hub-group" id="<?= e($gk) ?>"><?= e($gl) ?></h2>
            <div class="uc-hub-grid">
                <?php foreach ($useCases as $uc): if ($uc['group'] !== $gk) continue; ?>
                <a href="<?= e($uc['slug']) ?>/" class="uc-hub-card uc-hub-card-live">
                    <div class="uc-hub-icon"><?= $uc['icon'] ?></div>
                    <h3><?= e($uc['title']) ?></h3>
                    <p><?= e($uc['summary']) ?></p>
                    <span class="uc-hub-link">See how it works →</span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="uc-hub-cta">
    <div class="container uc-hub-cta-inner">
        <div>
            <h2>Not sure which fits you?</h2>
            <p>Start free — every plan works the same way no matter what you're promoting on Pinterest.</p>
        </div>
        <?php if ($user): ?>
            <a href="../user/dashboard" class="uc-hub-cta-pill">Go to Dashboard →</a>
        <?php else: ?>
            <a href="../auth/register" class="uc-hub-cta-pill">Try <?= e(APP_NAME) ?> Free →</a>
        <?php endif; ?>
    </div>
</section>

<style>
.uc-hub-jump { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 0 0 20px; }
.uc-hub-jump a { background: #fff; border: 1px solid var(--border); border-radius: 30px; padding: 8px 16px; font-weight: 700; font-size: 13.5px; color: var(--dark); text-decoration: none; }
.uc-hub-jump a:hover { border-color: var(--red); color: var(--red); }
.uc-hub-group { font-size: 24px; margin: 44px auto 18px; max-width: 1100px; scroll-margin-top: 90px; }
.uc-hub-hero { padding: 64px 0 40px; text-align: center; background: linear-gradient(180deg, #fff 0%, var(--light) 100%); }
.uc-hub-badge { font-size: 12px; font-weight: 800; letter-spacing: .08em; color: var(--red); margin-bottom: 12px; }
.uc-hub-hero h1 { font-size: 38px; font-weight: 800; color: var(--dark); margin: 0 0 14px; }
.uc-hub-hero p { color: var(--gray); font-size: 16.5px; max-width: 620px; margin: 0 auto; }

.uc-hub-grid-section { padding: 40px 0 70px; }
.uc-hub-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; max-width: 1100px; margin: 0 auto; }
.uc-hub-card { display: block; background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 28px; text-decoration: none; box-shadow: 0 8px 22px rgba(17,24,39,0.05); transition: transform .2s ease, box-shadow .2s ease; }
.uc-hub-card-live { cursor: pointer; }
.uc-hub-card-live:hover { transform: translateY(-4px); box-shadow: 0 16px 34px rgba(17,24,39,0.1); border-color: #ffd3d8; }
.uc-hub-card-soon { opacity: .75; }
.uc-hub-icon { font-size: 30px; margin-bottom: 14px; }
.uc-hub-card h3 { font-size: 19px; margin: 0 0 8px; color: var(--dark); }
.uc-hub-card p { font-size: 14.5px; color: var(--gray); margin: 0 0 14px; line-height: 1.55; }
.uc-hub-link { color: var(--red); font-weight: 700; font-size: 14px; }
.uc-hub-soon-badge { display: inline-block; background: var(--light); color: var(--gray); font-weight: 700; font-size: 12px; padding: 5px 12px; border-radius: 20px; }

.uc-hub-cta { background: var(--red); padding: 46px 0; }
.uc-hub-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.uc-hub-cta-inner h2 { color: #fff; font-size: 28px; margin: 0 0 6px; }
.uc-hub-cta-inner p { color: rgba(255,255,255,0.9); margin: 0; font-size: 15px; }
.uc-hub-cta-pill { background: #fff; color: var(--red); font-weight: 700; padding: 13px 24px; border-radius: 30px; white-space: nowrap; text-decoration: none; }

@media (max-width: 980px) { .uc-hub-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 760px) {
    .uc-hub-grid { grid-template-columns: 1fr; }
    .uc-hub-hero h1 { font-size: 28px; }
    .uc-hub-cta-inner { text-align: center; justify-content: center; }
}
</style>

<?php render_site_footer($pdo); ?>
</body>
</html>
