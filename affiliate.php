<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/affiliate_functions.php';

$user = current_user($pdo);
$settings = affiliate_tables_ready($pdo) ? affiliate_settings_get($pdo) : [
    'commission_percent' => 40.0, 'duration_type' => 'lifetime', 'duration_months' => null,
    'min_payout_threshold' => 50.0, 'cookie_days' => 60, 'program_enabled' => true,
];
$commission = rtrim(rtrim(number_format($settings['commission_percent'], 1), '0'), '.');
$durationLabel = $settings['duration_type'] === 'custom'
    ? ($settings['duration_months'] . '-month')
    : 'lifetime';
$joinUrl = $user ? '/user/affiliate-dashboard' : '/auth/register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Affiliate Program — Earn ' . $commission . '% Commission — ' . APP_NAME,
    'description' => 'Join the ' . APP_NAME . ' affiliate program and earn ' . $commission . '% ' . $durationLabel . ' commission on every referral you send our way.',
]); ?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="/assets/css/rg-effects.css?v=<?= @filemtime(__DIR__ . '/assets/css/rg-effects.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="rg-affiliate-hero">
    <div class="container" style="max-width:820px;text-align:center;">
        <div class="rg-badge rg-border-glow rg-pop" style="margin-bottom:22px;">💸 Affiliate Program</div>
        <h1 class="rg-affiliate-h1">Earn <span class="rg-text-blink"><?= e($commission) ?>%</span> Commission<br>On Every Referral</h1>
        <p class="rg-affiliate-sub">Share <?= e(APP_NAME) ?> with your audience and get paid <?= e($durationLabel) ?> commission on everything they spend — no cap, no complicated tiers.</p>
        <div style="margin-top:30px;">
            <a href="<?= e($joinUrl) ?>" class="btn-primary rg-shine rg-border-glow" style="font-size:17px;padding:15px 34px;"><?= $user ? 'Go to Affiliate Dashboard' : 'Join Free — Start Earning' ?></a>
        </div>
        <p class="muted" style="margin-top:14px;font-size:13px;">Free to join · No minimum audience size · Get your link in seconds</p>
    </div>
</section>

<section class="container">
    <div class="rg-stat-grid" style="max-width:900px;margin:0 auto 50px;">
        <div class="rg-stat-card"><span class="rg-stat-num rg-text-blink"><?= e($commission) ?>%</span><span class="rg-stat-label">Commission on every referral</span></div>
        <div class="rg-stat-card"><span class="rg-stat-num" style="text-transform:capitalize;"><?= e($durationLabel) ?></span><span class="rg-stat-label">Commission duration</span></div>
        <div class="rg-stat-card"><span class="rg-stat-num"><?= (int)$settings['cookie_days'] ?> Days</span><span class="rg-stat-label">Referral cookie window</span></div>
        <div class="rg-stat-card"><span class="rg-stat-num">$<?= number_format($settings['min_payout_threshold'], 0) ?></span><span class="rg-stat-label">Minimum payout threshold</span></div>
    </div>
</section>

<section class="container" style="max-width:880px;">
    <h2 style="text-align:center;margin-bottom:30px;">How It Works</h2>
    <div class="rg-step-card">
        <div class="rg-step-num">1</div>
        <div><strong>Sign up for free.</strong> Create an account and get your unique referral link instantly from your Affiliate Dashboard — no application, no approval wait.</div>
    </div>
    <div class="rg-step-card">
        <div class="rg-step-num">2</div>
        <div><strong>Share your link.</strong> Post it on your blog, YouTube channel, newsletter, social profiles, or anywhere your audience already trusts you. Every click is tracked automatically.</div>
    </div>
    <div class="rg-step-card">
        <div class="rg-step-num">3</div>
        <div><strong>They sign up and subscribe.</strong> When someone clicks your link and becomes a paying customer, you're credited as their referrer — the cookie holds for <?= (int)$settings['cookie_days'] ?> days, so even a delayed signup still counts.</div>
    </div>
    <div class="rg-step-card">
        <div class="rg-step-num">4</div>
        <div><strong>Earn <?= e($commission) ?>% commission.</strong> You earn <?= e($commission) ?>% of what they pay, <?= $settings['duration_type'] === 'custom' ? 'for ' . $settings['duration_months'] . ' months from their signup' : 'for as long as they stay subscribed' ?> — tracked automatically in your dashboard.</div>
    </div>
    <div class="rg-step-card">
        <div class="rg-step-num">5</div>
        <div><strong>Request your payout.</strong> Once your balance clears $<?= number_format($settings['min_payout_threshold'], 0) ?>, request a payout straight from your dashboard.</div>
    </div>
</section>

<div class="rg-cta-inline rg-border-glow" style="max-width:800px;margin:50px auto;">
    <h3>Why creators promote <?= e(APP_NAME) ?></h3>
    <p style="margin-bottom:18px;">Pinterest automation is a genuinely recurring need — your referrals keep paying, and so do you, for as long as they stay subscribed. No caps on how much you can earn.</p>
    <a href="<?= e($joinUrl) ?>" class="btn-primary rg-shine"><?= $user ? 'Go to Affiliate Dashboard' : 'Join Free Now' ?></a>
</div>

<section class="container" style="max-width:820px;padding-bottom:70px;">
    <h2 style="text-align:center;margin-bottom:24px;">Frequently Asked Questions</h2>
    <div class="rg-section">
        <h3 style="margin-top:0;">Who can join?</h3>
        <p style="margin-bottom:0;">Anyone with a free account. There's no minimum audience size, follower count, or application process — sign up and your referral link is ready immediately.</p>
    </div>
    <div class="rg-section">
        <h3 style="margin-top:0;">How and when do I get paid?</h3>
        <p style="margin-bottom:0;">Commission accrues automatically as your referrals pay for their subscription. Once your available balance reaches $<?= number_format($settings['min_payout_threshold'], 0) ?>, request a payout from your Affiliate Dashboard and it's reviewed and sent out by the team.</p>
    </div>
    <div class="rg-section">
        <h3 style="margin-top:0;">How long does the referral cookie last?</h3>
        <p style="margin-bottom:0;"><?= (int)$settings['cookie_days'] ?> days. If someone clicks your link and signs up any time within that window, you're credited as their referrer.</p>
    </div>
    <div class="rg-section">
        <h3 style="margin-top:0;">Is there a limit on how much I can earn?</h3>
        <p style="margin-bottom:0;">No cap. The more referrals you bring who become paying customers, the more you earn — commission is calculated per referral, every month.</p>
    </div>
</section>

<?php render_site_footer($pdo); ?>

</body>
</html>
