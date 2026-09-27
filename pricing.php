<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/pricing_functions.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/auth.php';

$user = current_user($pdo);

$migrationReady = plan_pricing_tables_ready($pdo);
$plans = $migrationReady ? get_all_plans($pdo, true) : [];

$maxYearlyDiscount = 0;
foreach ($plans as $p) $maxYearlyDiscount = max($maxYearlyDiscount, (float)$p['discount_yearly']);

$popularPlanId = null;
foreach ($plans as $p) if ($p['tag'] === 'popular') $popularPlanId = (int)$p['id'];

// If already logged in, send straight to real checkout instead of signup.
function pricing_cta_url(array $plan, string $cycle, ?array $user): string
{
    if ($plan['is_free']) {
        return $user ? 'user/upgrade' : 'auth/register';
    }
    return $user
        ? 'user/checkout?plan=' . (int)$plan['id'] . '&cycle=' . $cycle
        : 'auth/register?plan=' . (int)$plan['id'] . '&cycle=' . $cycle;
}

$features = [
    ['icon' => '🔗', 'name' => 'URL & Sitemap Scanner', 'desc' => 'Point it at any page or full sitemap and it finds every post, product, or page worth turning into a pin.'],
    ['icon' => '✨', 'name' => 'AI-Powered Content', 'desc' => 'AI writes on-brand titles, descriptions, and keywords for every pin from your actual page content.'],
    ['icon' => '🖼️', 'name' => 'AI Image Creation', 'desc' => 'Generate fresh, scroll-stopping pin backgrounds with AI when your page has no images of its own.'],
    ['icon' => '🎨', 'name' => 'AI Template Design', 'desc' => 'A library of proven pin layouts, automatically matched to your content and colors.'],
    ['icon' => '📅', 'name' => 'Smart Scheduling', 'desc' => 'Pins go out at the times and pace that keep your account healthy — no manual calendar juggling.'],
    ['icon' => '📌', 'name' => 'Auto Pin', 'desc' => 'New content on your site gets turned into pins and queued automatically, no manual trigger needed.'],
    ['icon' => '🔍', 'name' => 'Keyword Research', 'desc' => 'Find the phrases your audience is actually searching for before you write a single pin.'],
    ['icon' => '📷', 'name' => 'Stock Images & Videos', 'desc' => 'A built-in library of licensed visuals for pages that need a little extra polish.'],
    ['icon' => '⬇️', 'name' => 'CSV Download', 'desc' => 'Export your pin data, keywords, or schedules any time you need them outside the app.'],
    ['icon' => '⬆️', 'name' => 'CSV Upload', 'desc' => 'Bring in bulk page lists, titles, or boards from a spreadsheet instead of typing them by hand.'],
    ['icon' => '🛍️', 'name' => 'Pinterest Product Catalogs', 'desc' => 'Sync your product catalog so listings can be pinned and kept up to date automatically.'],
    ['icon' => '📝', 'name' => 'Auto Article + Auto Pin', 'desc' => 'Write and publish articles straight to your website, then automatically schedule pins for them.'],
    ['icon' => '✍️', 'name' => 'Article Writer', 'desc' => 'Draft full, SEO-ready articles with AI whenever you need fresh content for your site.'],
];

$compareRows = [
    ['label' => 'Pinterest accounts', 'tooltip' => 'Connect as many Pinterest accounts as you need.', 'values' => ['Unlimited', 'Unlimited', 'Unlimited', 'Unlimited', 'Unlimited']],
    ['label' => 'Websites', 'tooltip' => 'Add as many websites as you need to create pins from.', 'values' => ['Unlimited', 'Unlimited', 'Unlimited', 'Unlimited', 'Unlimited']],
    ['label' => 'Scheduled pins per day', 'tooltip' => 'Maximum number of pins you can schedule per day.', 'values' => ['15', '70', '140', '300', '650']],
    ['label' => 'AI Image credits / month', 'tooltip' => null, 'values' => ['5', '300', '600', '1,200', '2,500']],
    ['label' => 'AI Text credits / month', 'tooltip' => null, 'values' => ['10,000', '1,000,000', '2,000,000', '4,000,000', '10,000,000']],
    ['label' => 'Cloud storage', 'tooltip' => null, 'values' => ['100MB', '1GB', '2GB', '5GB', '10GB']],
    ['label' => 'Bulk scheduling', 'tooltip' => null, 'values' => [false, true, true, true, true]],
    ['label' => 'Auto website-to-daily-pin', 'tooltip' => null, 'values' => [false, true, true, true, true]],
    ['label' => 'Auto article + auto pin publish', 'tooltip' => null, 'values' => [false, true, true, true, true]],
    ['label' => 'Single article writer', 'tooltip' => null, 'values' => [true, true, true, true, true]],
    ['label' => 'AI text & board generation', 'tooltip' => 'Generate pin descriptions, titles, and board suggestions using AI.', 'values' => ['Limited', 'Unlimited', 'Unlimited', 'Unlimited', 'Unlimited']],
    ['label' => 'AI bulk title, description, alt & keywords', 'tooltip' => null, 'values' => ['Limited', 'Unlimited', 'Unlimited', 'Unlimited', 'Unlimited']],
    ['label' => 'Team members', 'tooltip' => null, 'values' => ['2', '2', '4', '8', '15']],
    ['label' => 'Analytics dashboard', 'tooltip' => null, 'values' => [true, true, true, true, true]],
    ['label' => 'Top pins & top boards', 'tooltip' => null, 'values' => [true, true, true, true, true]],
    ['label' => 'Support', 'tooltip' => null, 'values' => ['Standard', 'Standard', 'Priority', 'Priority', 'Urgent']],
];

// Sample testimonials — placeholder social proof to show the layout; swap in real reviews
// once you have them (see admin note in the reply this file came with).
$reviews = [
    ['name' => 'Sarah M.', 'role' => 'Food blogger', 'text' => 'My pin volume went from a handful a week to dozens a day without me touching Canva once. Traffic followed within a month.'],
    ['name' => 'James T.', 'role' => 'Etsy seller', 'text' => 'The auto website-to-daily-pin feature alone paid for the plan. I forget it\'s even running most weeks.'],
    ['name' => 'Priya K.', 'role' => 'Home decor blog', 'text' => 'Board name and bio generators saved me an entire afternoon of staring at a blank page trying to sound clever.'],
    ['name' => 'Daniel R.', 'role' => 'Travel content creator', 'text' => 'Bulk scheduling across three accounts used to be a spreadsheet nightmare. Now it\'s a checklist.'],
    ['name' => 'Amina H.', 'role' => 'Handmade jewelry shop', 'text' => 'The keyword tool actually changed which products I photograph next — that\'s the kind of insight I didn\'t expect from a pinning app.'],
    ['name' => 'Chris B.', 'role' => 'Recipe site owner', 'text' => 'Auto article plus auto pin scheduling means new recipes are on Pinterest before I\'ve finished my coffee.'],
    ['name' => 'Emily W.', 'role' => 'Wedding stationery designer', 'text' => 'Switched from a competitor mid-year and the template variety alone was worth it.'],
    ['name' => 'Marcus L.', 'role' => 'Fitness blogger', 'text' => 'Text credits go further than I expected — I was ready to hit a wall and never did.'],
    ['name' => 'Nadia F.', 'role' => 'Vintage reseller', 'text' => 'Support answered a billing question in under an hour on the Growth plan. Didn\'t expect that speed honestly.'],
    ['name' => 'Tyler S.', 'role' => 'DIY & crafts channel', 'text' => 'Deleting underperforming pins automatically keeps my boards from looking stale without me babysitting analytics daily.'],
    ['name' => 'Grace O.', 'role' => 'Small business coach', 'text' => 'My team of 3 all work from the same dashboard now instead of passing a spreadsheet back and forth.'],
    ['name' => 'Ben A.', 'role' => 'Print-on-demand shop', 'text' => 'The CSV upload for bulk pages saved me from manually adding 200+ product URLs one at a time.'],
];

$faqs = [
    ['q' => 'Do I need a credit card to start on the Free plan?', 'a' => 'No — the Free plan is genuinely free with no credit card required. You can upgrade whenever you\'re ready for higher limits.'],
    ['q' => 'What exactly is an AI Image credit?', 'a' => 'Every AI-generated pin image costs a fraction of a credit depending on quality — Budget pins cost 0.2 credits, Best Quality pins cost 0.7 credits, and Classic pins cost 1 credit. Your plan\'s monthly credits refill automatically.'],
    ['q' => 'What is a Text AI credit used for?', 'a' => 'Every AI text request — a title, description, alt text, board suggestion, or keyword list — uses 1 text credit. Text credits are separate from image credits.'],
    ['q' => 'Can I change plans later?', 'a' => 'Yes — upgrade or downgrade any time from your dashboard. Changes take effect on your next billing cycle, and your usage carries over within that cycle.'],
    ['q' => 'What happens if I hit my monthly pin limit?', 'a' => 'Scheduling pauses until your limit resets next month, or you can upgrade immediately to a higher plan to keep going without waiting.'],
    ['q' => 'What\'s the difference between monthly and annual billing?', 'a' => 'Annual billing is paid upfront for the year at a larger discount than the monthly rate — the exact savings are shown on each plan when you toggle to Annual above.'],
    ['q' => 'Do unused AI credits roll over to next month?', 'a' => 'Credits refill to your plan\'s monthly amount at the start of each billing cycle and don\'t accumulate — this keeps every plan\'s cost predictable.'],
    ['q' => 'Is there a limit on how many websites I can connect?', 'a' => 'No — every plan, including Free, includes unlimited connected websites and unlimited Pinterest accounts. Your plan\'s limits are on AI credits and scheduled pins.'],
    ['q' => 'Can I cancel any time?', 'a' => 'Yes, cancel any time from your account settings with no cancellation fee. You\'ll keep access through the end of your current billing period.'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Pricing: Pinterest Automation Plans | ' . SITE_BRAND,
    'description' => 'Simple, transparent pricing for Pinterest automation: AI pin design, pin scheduling, auto blog and analytics. Start free, no card needed, upgrade any time.',
    'breadcrumbs' => [['Pricing', 'pricing']],
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">

    <!-- ===================== Hero ===================== -->
    <div class="ft-hero rg-reveal" style="max-width:760px;">
        <div class="pr-eyebrow rg-text-blink">Pricing</div>
        <h1>Pinterest Marketing, on Autopilot</h1>
        <p class="ft-sub">Turn AI into your always-on Pinterest marketing team. Create scroll-stopping pins, write optimized copy, and schedule fresh content every day — automatically.</p>
        <p style="font-weight:700;font-size:18px;margin:10px 0;">No website. No design skills. No manual posting.</p>
        <p class="muted" style="font-size:14px;">Every plan includes AI credits, giving you the flexibility to choose the image quality that works for your budget.</p>
    </div>

    <!-- ===================== Stats ===================== -->
    <div class="pr-stats rg-reveal">
        <div class="pr-stat"><div class="pr-stat-num rg-text-blink">36,739+</div><div class="pr-stat-label">Active users</div></div>
        <div class="pr-stat"><div class="pr-stat-num rg-text-blink">5M+</div><div class="pr-stat-label">Pins created monthly</div></div>
        <div class="pr-stat"><div class="pr-stat-num rg-text-blink">10+</div><div class="pr-stat-label">Hours saved per week</div></div>
    </div>

    <?php if (!$migrationReady): ?>
        <div class="alert alert-info" style="max-width:600px;margin:0 auto 20px;">Pricing plans are being set up — check back shortly.</div>
    <?php else: ?>

    <!-- ===================== View toggle ===================== -->
    <div class="pr-view-toggle">
        <button type="button" class="pr-view-btn active rg-shine" data-view="plans" onclick="showView('plans')">Show All Plans</button>
        <button type="button" class="pr-view-btn rg-shine" data-view="compare" onclick="showView('compare')">Show Compare</button>
    </div>

    <div id="view-plans">
        <div class="billing-toggle-wrap">
            <span class="billing-toggle-label">Monthly</span>
            <label class="toggle-pill" style="width:50px; height:28px;">
                <input type="checkbox" id="billingToggle" onchange="toggleBilling(this.checked)">
                <span class="toggle-pill-slider" style="border-radius:28px;"></span>
            </label>
            <span class="billing-toggle-label">Annual</span>
            <?php if ($maxYearlyDiscount > 0): ?><span class="billing-save-badge">Save up to <?= (int)$maxYearlyDiscount ?>% with annual</span><?php endif; ?>
        </div>

        <div class="plan-grid">
        <?php foreach ($plans as $p):
            $rows = get_plan_feature_rows($pdo, (int)$p['id']);
            $tagColor = PLAN_TAG_COLORS[$p['tag_color']] ?? '#e60023';

            $monthlyBase = (float)$p['price_monthly'];
            $monthlyDiscount = (float)$p['discount_monthly'];
            $monthlyFinal = $monthlyDiscount > 0 ? $monthlyBase * (1 - $monthlyDiscount / 100) : $monthlyBase;
            $yearlyBase = $monthlyBase * 12;
            $yearlyDiscount = (float)$p['discount_yearly'];
            $yearlyFinal = $yearlyDiscount > 0 ? $yearlyBase * (1 - $yearlyDiscount / 100) : $yearlyBase;
            $yearlyFinalMonthlyEquiv = $yearlyFinal / 12;

            $ctaMonthly = pricing_cta_url($p, 'monthly', $user);
            $ctaYearly = pricing_cta_url($p, 'yearly', $user);
        ?>
            <div class="plan-card <?= (int)$p['id'] === $popularPlanId ? 'plan-card-popular rg-border-glow' : '' ?> rg-reveal"
                 data-monthly-base="<?= e(number_format($monthlyBase, 2)) ?>" data-monthly-final="<?= e(number_format($monthlyFinal, 2)) ?>" data-monthly-discount="<?= (int)$monthlyDiscount ?>"
                 data-yearly-base="<?= e(number_format($yearlyBase, 2)) ?>" data-yearly-final="<?= e(number_format($yearlyFinal, 2)) ?>" data-yearly-monthly-equiv="<?= e(number_format($yearlyFinalMonthlyEquiv, 2)) ?>" data-yearly-discount="<?= (int)$yearlyDiscount ?>"
                 data-checkout-monthly="<?= e($ctaMonthly) ?>" data-checkout-yearly="<?= e($ctaYearly) ?>">
                <?php if ((int)$p['id'] === $popularPlanId): ?><div class="plan-popular-ribbon">Popular</div><?php endif; ?>
                <?php if ($p['tag'] && (int)$p['id'] !== $popularPlanId): ?><div class="plan-tag" style="background:<?= e($tagColor) ?>;"><?= e(PLAN_TAG_OPTIONS[$p['tag']] ?? $p['tag']) ?></div><?php endif; ?>

                <h3><?= e($p['name']) ?></h3>
                <?php if ($p['short_description']): ?><p class="muted plan-desc"><?= e($p['short_description']) ?></p><?php endif; ?>

                <div class="plan-price">
                    <?php if ($monthlyDiscount > 0): ?><span class="plan-discount-badge js-plan-discount-badge"><?= (int)$monthlyDiscount ?>% OFF</span><br><?php endif; ?>
                    <span class="plan-price-main">$<span class="js-plan-price"><?= number_format($monthlyFinal, 2) ?></span></span>
                    <span class="muted">/<span class="js-plan-period">month</span></span>
                    <?php if ($monthlyDiscount > 0): ?><div class="plan-price-was js-plan-was">Was $<?= number_format($monthlyBase, 2) ?>/month</div><?php endif; ?>
                    <div class="muted js-plan-billed-note" style="display:none; font-size:12px;"></div>
                </div>

                <a href="<?= e($ctaMonthly) ?>" class="plan-buy-btn js-plan-buy-btn rg-shine" style="background:<?= e($p['pay_button_bg']) ?>; color:<?= e($p['pay_button_text_color']) ?>; border-color:<?= e($p['pay_button_border_color']) ?>;"><?= e($p['pay_button_text']) ?></a>

                <ul class="plan-feature-list">
                    <?php foreach ($rows as $r): ?>
                        <li style="font-size:<?= e($r['text_size']) ?>; color:<?= e($r['text_color']) ?>; <?= $r['font'] ? 'font-family:' . e($r['font']) . ';' : '' ?>" title="<?= e($r['tooltip'] ?? '') ?>">
                            <span style="font-size:<?= e($r['checkmark_size']) ?>; color:<?= e($r['checkmark_color']) ?>;"><?= PLAN_CHECKMARK_TYPES[$r['checkmark_type']] ?? '✓' ?></span>
                            <?= e($r['text']) ?>
                        </li>
                    <?php endforeach; ?>
                    <?php
                    $toggleFeatures = [
                        'bulk_scheduling_enabled' => 'Bulk scheduling',
                        'auto_website_daily_pin_enabled' => 'Auto website-to-daily-pin',
                        'auto_article_enabled' => 'Auto article',
                        'single_article_writer_enabled' => 'Single article writer',
                    ];
                    foreach ($toggleFeatures as $fkey => $flabel): $has = (bool)($p[$fkey] ?? false); ?>
                        <li class="<?= $has ? 'plan-feature-yes' : 'plan-feature-no' ?>">
                            <span><?= $has ? '✓' : '✕' ?></span> <?= e($flabel) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ===================== Compare table ===================== -->
    <div id="view-compare" style="display:none;">
        <div class="pr-compare-wrap">
            <table class="pr-compare-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <?php foreach ($plans as $p): ?><th><?= e($p['name']) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compareRows as $row): ?>
                    <tr>
                        <td title="<?= e($row['tooltip'] ?? '') ?>"><?= e($row['label']) ?></td>
                        <?php foreach ($row['values'] as $v): ?>
                            <td>
                                <?php if ($v === true): ?><span style="color:var(--green);font-weight:700;">✓</span>
                                <?php elseif ($v === false): ?><span style="color:var(--gray);">✕</span>
                                <?php else: ?><?= e($v) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <!-- ===================== Feature explainer ===================== -->
    <h2 class="rg-reveal" style="text-align:center;font-size:34px;margin:64px 0 6px;">Everything you need to grow on Pinterest</h2>
    <p class="ft-sub" style="text-align:center;max-width:640px;margin:0 auto 32px;">All plans include access to our complete suite of Pinterest marketing tools.</p>
    <div class="pr-feature-grid">
        <?php foreach ($features as $f): ?>
            <div class="pr-feature-card rg-reveal">
                <div class="pr-feature-icon"><?= $f['icon'] ?></div>
                <div class="pr-feature-name"><?= e($f['name']) ?></div>
                <div class="pr-feature-desc"><?= e($f['desc']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===================== Reviews ===================== -->
    <h2 class="rg-reveal" style="text-align:center;font-size:34px;margin:64px 0 32px;">Join 36,739+ Pinterest pros</h2>
    <div class="pr-review-grid">
        <?php foreach ($reviews as $r):
            $initials = strtoupper(substr($r['name'], 0, 1) . (strpos($r['name'], ' ') !== false ? substr($r['name'], strpos($r['name'], ' ') + 1, 1) : ''));
        ?>
            <div class="pr-review-card rg-reveal">
                <div class="pr-review-stars">★★★★★</div>
                <p class="pr-review-text">"<?= e($r['text']) ?>"</p>
                <div class="pr-review-person">
                    <div class="pr-review-avatar"><?= e($initials) ?></div>
                    <div><div class="pr-review-name"><?= e($r['name']) ?></div><div class="pr-review-role"><?= e($r['role']) ?></div></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===================== FAQ ===================== -->
    <div class="ft-faq rg-reveal" style="margin-top:64px;">
        <h2>Frequently Asked Questions</h2>
        <?php foreach ($faqs as $f): ?>
            <details><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details>
        <?php endforeach; ?>
    </div>

    <div class="ft-marketing rg-border-glow rg-reveal" style="margin-top:56px;">
        <h2>Ready to put Pinterest on autopilot?</h2>
        <p>Start free — no credit card required. Upgrade any time as you grow.</p>
        <a href="auth/register" class="btn-primary ft-marketing-cta rg-shine">Start Free →</a>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
function showView(view) {
    document.getElementById('view-plans').style.display = view === 'plans' ? '' : 'none';
    document.getElementById('view-compare').style.display = view === 'compare' ? '' : 'none';
    document.querySelectorAll('.pr-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
}

function toggleBilling(isYearly) {
    document.querySelectorAll('.plan-card').forEach(function (card) {
        var price = isYearly ? card.dataset.yearlyMonthlyEquiv : card.dataset.monthlyFinal;
        var base = isYearly ? card.dataset.yearlyBase : card.dataset.monthlyBase;
        var discount = isYearly ? card.dataset.yearlyDiscount : card.dataset.monthlyDiscount;
        var priceEl = card.querySelector('.js-plan-price');
        var periodEl = card.querySelector('.js-plan-period');
        var wasEl = card.querySelector('.js-plan-was');
        var badgeEl = card.querySelector('.js-plan-discount-badge');
        var noteEl = card.querySelector('.js-plan-billed-note');
        var buyBtn = card.querySelector('.js-plan-buy-btn');
        if (priceEl) priceEl.textContent = price;
        if (periodEl) periodEl.textContent = 'month';
        if (discount > 0) {
            if (badgeEl) { badgeEl.style.display = 'inline-block'; badgeEl.textContent = discount + '% OFF'; }
            if (wasEl) { wasEl.style.display = 'block'; wasEl.textContent = 'Was $' + (isYearly ? (card.dataset.yearlyBase / 12).toFixed(2) : base) + '/month'; }
        } else {
            if (badgeEl) badgeEl.style.display = 'none';
            if (wasEl) wasEl.style.display = 'none';
        }
        if (noteEl) {
            if (isYearly) { noteEl.style.display = 'block'; noteEl.textContent = 'Billed annually at $' + card.dataset.yearlyFinal; }
            else noteEl.style.display = 'none';
        }
        if (buyBtn) buyBtn.setAttribute('href', isYearly ? card.dataset.checkoutYearly : card.dataset.checkoutMonthly);
    });
}
</script>

<style>
.pr-eyebrow { display:inline-block; color: var(--red); font-weight:700; font-size:13px; letter-spacing:.06em; text-transform:uppercase; margin-bottom:10px; }
.pr-stats { display:flex; justify-content:center; gap:56px; flex-wrap:wrap; margin:36px 0 44px; text-align:center; }
.pr-stat-num { font-size:36px; font-weight:800; color: var(--dark); }
.pr-stat-label { color: var(--gray); font-size:13px; margin-top:4px; }

.pr-view-toggle { display:flex; justify-content:center; gap:10px; margin-bottom:8px; }
.pr-view-btn { border:1px solid var(--border); background:#fff; padding:9px 20px; border-radius:24px; font-weight:600; font-size:14px; cursor:pointer; }
.pr-view-btn.active { background: var(--red); color:#fff; border-color: var(--red); }

.plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; max-width: 1200px; margin-left:auto; margin-right:auto; }
.plan-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:26px; position:relative; display:flex; flex-direction:column; overflow:hidden; }
.plan-card-popular { border-color: var(--red); box-shadow: 0 8px 28px rgba(230,0,35,0.12); transform: translateY(-4px); }
.plan-popular-ribbon { position: absolute; top: 14px; right: -32px; background: var(--red); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 36px; transform: rotate(45deg); box-shadow: 0 2px 6px rgba(0,0,0,.15); }
.plan-tag { display:inline-block; color:#fff; font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:10px; }
.plan-desc { min-height: 36px; font-size:13px; }
.plan-price { margin: 6px 0 16px; }
.plan-discount-badge { display:inline-block; background:#dcfce7; color:#16a34a; font-size:11px; font-weight:800; padding:2px 8px; border-radius:10px; margin-bottom:4px; }
.plan-price-main { font-size:32px; font-weight:800; }
.plan-price-was { text-decoration: line-through; color: var(--gray); font-size: 13px; margin-top:2px; }
.plan-buy-btn { display:block; text-align:center; text-decoration:none !important; padding:12px; border-radius:10px; border:2px solid; font-weight:700; margin: 10px 0; cursor:pointer; }
.plan-feature-list { list-style:none; padding:0; margin:10px 0; flex:1; }
.plan-feature-list li { display:flex; align-items:flex-start; gap:8px; padding:6px 0; font-size:13px; }
.plan-feature-yes span { color:#16a34a; font-weight:700; }
.plan-feature-no { color: var(--gray); }
.plan-feature-no span { color:#dc2626; font-weight:700; }
.billing-toggle-wrap { display:flex; align-items:center; justify-content:center; gap:12px; margin: 20px 0 6px; flex-wrap:wrap; text-align:center; }
.billing-toggle-label { font-weight:700; }
.billing-save-badge { background:#16a34a; color:#fff; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; }

.pr-compare-wrap { overflow-x:auto; max-width:1100px; margin:20px auto; border:1px solid var(--border); border-radius:12px; }
.pr-compare-table { width:100%; border-collapse:collapse; font-size:13px; min-width:700px; }
.pr-compare-table th, .pr-compare-table td { padding:10px 14px; text-align:center; border-bottom:1px solid var(--border); }
.pr-compare-table th:first-child, .pr-compare-table td:first-child { text-align:left; font-weight:600; }
.pr-compare-table thead th { background: var(--light); font-weight:700; }

.pr-feature-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:18px; max-width:1100px; margin:0 auto; }
.pr-feature-card { border:1px solid var(--border); border-radius:12px; padding:18px; }
.pr-feature-icon { font-size:26px; margin-bottom:8px; }
.pr-feature-name { font-weight:700; margin-bottom:6px; }
.pr-feature-desc { font-size:13px; color: var(--gray); line-height:1.5; }

.pr-review-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:18px; max-width:1100px; margin:0 auto; }
.pr-review-card { border:1px solid var(--border); border-radius:12px; padding:18px; }
.pr-review-stars { color:#f59e0b; letter-spacing:2px; margin-bottom:8px; }
.pr-review-text { font-size:14px; line-height:1.5; margin-bottom:14px; color: var(--dark); }
.pr-review-person { display:flex; align-items:center; gap:10px; }
.pr-review-avatar { width:36px; height:36px; border-radius:50%; background: var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex:none; }
.pr-review-name { font-weight:700; font-size:13px; }
.pr-review-role { color: var(--gray); font-size:12px; }
</style>

<style>
/* ===================== Red & Green Shine / Glow Enhancement Pack ===================== */
html { scroll-behavior: smooth; }

body, .ft-page, .ft-hero, .ft-faq, .ft-marketing {
    background: #ffffff !important;
}

@keyframes rgBlinkColor { 0%, 100% { color: var(--red); } 50% { color: #12c464; } }
@keyframes rgBlinkBg {
    0%, 100% { background-color: var(--red); box-shadow: 0 0 16px 3px rgba(230,0,35,.65); }
    50%      { background-color: #12c464;   box-shadow: 0 0 16px 3px rgba(18,196,100,.65); }
}
@keyframes rgBorderCycle {
    0%, 100% { border-color: var(--red);   box-shadow: 0 0 22px rgba(230,0,35,.35), inset 0 0 14px rgba(230,0,35,.06); }
    50%      { border-color: #12c464;      box-shadow: 0 0 22px rgba(18,196,100,.35), inset 0 0 14px rgba(18,196,100,.06); }
}
@keyframes rgShineSweep {
    0%   { transform: translateX(-160%) skewX(-20deg); opacity: 0; }
    10%  { opacity: 1; }
    50%  { transform: translateX(0%) skewX(-20deg); opacity: 1; }
    90%  { opacity: 1; }
    100% { transform: translateX(160%) skewX(-20deg); opacity: 0; }
}
@keyframes rgPop { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.045); } }

.rg-reveal { opacity: 0; transform: translateY(34px); transition: opacity .7s ease, transform .7s ease; }
.rg-reveal.rg-in { opacity: 1; transform: translateY(0); }

.rg-border-glow {
    border: 2px solid var(--red) !important;
    animation: rgBorderCycle 3.2s ease-in-out infinite;
}
.rg-text-blink { animation: rgBlinkColor 1.6s ease-in-out infinite; display: inline-block; }
.rg-dot-blink  { animation: rgBlinkBg 1.4s ease-in-out infinite; }
.rg-pop        { animation: rgPop 2.4s ease-in-out infinite; }

/* White diagonal shine streak sweeping across buttons/pills */
.rg-shine { position: relative; overflow: hidden; isolation: isolate; }
.rg-shine::after {
    content: '';
    position: absolute; top: -60%; left: 0; width: 34%; height: 220%;
    background: linear-gradient(115deg, transparent 0%, rgba(255,255,255,.9) 50%, transparent 100%);
    transform: translateX(-160%) skewX(-20deg);
    animation: rgShineSweep 2.8s ease-in-out infinite;
    pointer-events: none;
    z-index: 1;
}

@media (prefers-reduced-motion: reduce) {
    .rg-border-glow, .rg-text-blink, .rg-dot-blink, .rg-pop, .rg-shine::after, .rg-reveal {
        animation: none !important;
        transition: none !important;
    }
    .rg-reveal { opacity: 1; transform: none; }
}
</style>

<script>
(function () {
    var els = document.querySelectorAll('.rg-reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
        els.forEach(function (e) { e.classList.add('rg-in'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('rg-in');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (e) { io.observe(e); });
})();
</script>

</body>
</html>