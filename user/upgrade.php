<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'upgrade';
$pageTitle = 'Upgrade Your Plan';

$plans = get_all_plans($pdo, true);
$pricingSettings = credit_pricing_get($pdo);
$usage = get_user_plan_usage_summary($pdo, (int)$user['id']);
$currentPlan = $usage['plan'];
$currentPrice = $currentPlan['price_monthly'] ?? 0;

// Coupon auto-apply via link (?coupon=CODE)
$couponCode = trim($_GET['coupon'] ?? '');
$couponInfo = null;
$couponExpiredNotice = false;
if ($couponCode !== '') {
    $c = get_coupon_by_code($pdo, $couponCode);
    if ($c && $c['status'] === 'active' && (empty($c['end_date']) || strtotime($c['end_date']) >= strtotime(date('Y-m-d')))) {
        $couponInfo = $c;
    } elseif ($c) {
        $couponExpiredNotice = true;
    }
}

$migrationReady = true;
try {
    $pdo->query("SELECT plan_id FROM users LIMIT 1");
    $pdo->query("SELECT id FROM pricing_plans LIMIT 1");
} catch (Throwable $e) {
    $migrationReady = false;
}

// Find the best yearly-discount % across all plans, for the toggle's "Save up to X%" copy.
$maxYearlyDiscount = 0;
foreach ($plans as $p) $maxYearlyDiscount = max($maxYearlyDiscount, (float)$p['discount_yearly']);

include __DIR__ . '/includes/user-header.php';

function fmt_limit($v) { return $v === null ? 'Unlimited' : number_format((int)$v); }
function fmt_bytes_mb($bytes) { return round($bytes / 1048576, 1) . ' MB'; }
function fmt_storage($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<div class="page-header"><h1>Upgrade Your Plan</h1></div>
<p class="muted upgrade-tagline">Choose a plan to start creating AI pins, auto article publish, auto daily
scheduled publish, and grow your Pinterest traffic.</p>

<?php if (!$migrationReady): ?><div class="alert alert-info">Plans are not available right now. Please check back soon.</div><?php endif; ?>
<?php if ($couponExpiredNotice): ?><div class="alert alert-error">This coupon has expired.</div><?php endif; ?>

<!-- ===================== Your Usage ===================== -->
<div class="card" style="display:flex; gap:10px; flex-wrap:wrap;">
    <button type="button" class="btn-primary" onclick="openUsagePanel();">Your current Plan<?php if ($currentPlan): ?>: <?= e($currentPlan['name']) ?><?php endif; ?></button>
    <button type="button" class="btn-secondary" id="usageToggleBtn" onclick="toggleUsagePanel();">Your Usage</button>
</div>

<script>
function openUsagePanel() {
    var panel = document.getElementById('usage-panel');
    var btn = document.getElementById('usageToggleBtn');
    panel.classList.add('open');
    if (btn) btn.textContent = 'Hide Your Usage';
    panel.scrollIntoView({behavior: 'smooth'});
}
function toggleUsagePanel() {
    var panel = document.getElementById('usage-panel');
    var btn = document.getElementById('usageToggleBtn');
    var opening = !panel.classList.contains('open');
    panel.classList.toggle('open', opening);
    if (btn) btn.textContent = opening ? 'Hide Your Usage' : 'Your Usage';
    if (opening) panel.scrollIntoView({behavior: 'smooth'});
}
</script>

<div id="usage-panel" class="card usage-panel">
    <h2>Your Usage <?php if ($currentPlan): ?><span class="muted" style="font-weight:400;">— <?= e($currentPlan['name']) ?> plan</span><?php endif; ?></h2>

    <div class="usage-grid">
        <?php
        $usageRows = [
            ['label' => 'Image AI credits', 'item' => $usage['image_credits']],
            ['label' => 'Text AI credits', 'item' => $usage['text_credits']],
            ['label' => 'Pin scheduling (month)', 'item' => $usage['pin_monthly']],
            ['label' => 'Pin scheduling (today)', 'item' => $usage['pin_daily']],
            ['label' => 'Uploaded pins', 'item' => $usage['upload_pins']],
            ['label' => 'Pinterest accounts', 'item' => $usage['pinterest_accounts']],
            ['label' => 'Websites', 'item' => $usage['websites']],
            ['label' => 'Team members invited', 'item' => $usage['team_invites']],
        ];
        foreach ($usageRows as $row):
            $it = $row['item'];
            $atLimit = usage_is_at_limit($it);
        ?>
        <div class="usage-item <?= $atLimit ? 'usage-item-warn' : '' ?>">
            <span><?= e($row['label']) ?></span>
            <strong><?= (int)$it['used'] ?> / <?= fmt_limit($it['total']) ?></strong>
            <?php if ($it['total']): ?><div class="usage-bar"><div class="usage-bar-fill" style="width:<?= (int)$it['pct'] ?>%; background:<?= $atLimit ? '#dc2626' : '#16a34a' ?>;"></div></div><?php endif; ?>
            <?php if ($atLimit): ?><a href="#pricing-cards" class="usage-warn-link">Limit reached — upgrade →</a><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="usage-item <?= usage_is_at_limit($usage['storage']) ? 'usage-item-warn' : '' ?>">
            <span>Cloud storage</span>
            <strong><?= fmt_storage($usage['storage']['used']) ?> / <?= fmt_storage($usage['storage']['total']) ?></strong>
            <div class="usage-bar"><div class="usage-bar-fill" style="width:<?= (int)$usage['storage']['pct'] ?>%; background:<?= usage_is_at_limit($usage['storage']) ? '#dc2626' : '#16a34a' ?>;"></div></div>
            <?php if (usage_is_at_limit($usage['storage'])): ?><a href="#pricing-cards" class="usage-warn-link">Limit reached — upgrade →</a><?php endif; ?>
        </div>
    </div>

    <h3 style="margin-top:20px;">Plan Features</h3>
    <div class="feature-flags-grid">
        <?php foreach ($usage['features'] as $key => $f): ?>
            <div class="feature-flag <?= $f['available'] ? 'feature-flag-on' : 'feature-flag-off' ?>">
                <span><?= $f['available'] ? '✓' : '✕' ?></span> <?= e($f['label']) ?>
                <?php if (!$f['available']): ?><a href="#pricing-cards">Upgrade</a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $higherPlans = array_values(array_filter($plans, fn($p) => (float)$p['price_monthly'] > (float)$currentPrice));
    ?>
    <?php if (!empty($higherPlans)): ?>
        <h3 style="margin-top:18px;">Upgrade to unlock more</h3>
        <div class="plan-mini-grid">
            <?php foreach ($higherPlans as $p): ?>
                <div class="plan-mini-card">
                    <div class="plan-mini-name"><?= e($p['name']) ?></div>
                    <div class="plan-mini-price">$<?= number_format((float)$p['price_monthly'], 2) ?>/mo</div>
                    <a href="checkout?plan=<?= (int)$p['id'] ?>&cycle=monthly<?= $couponInfo ? '&coupon=' . urlencode($couponInfo['code']) : '' ?>" class="btn-primary btn-small">Upgrade</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($currentPlan): ?>
        <p class="muted" style="margin-top:14px;">You're on the highest available plan.</p>
    <?php endif; ?>
</div>

<!-- ===================== Pricing cards ===================== -->
<a id="pricing-cards"></a>
<?php if ($couponInfo): ?><div class="alert alert-success">Coupon <strong><?= e($couponInfo['code']) ?></strong> ready — <?= e($couponInfo['discount_percent']) ?>% off applicable plans at checkout.</div><?php endif; ?>

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
<?php
$popularPlanId = null;
foreach ($plans as $p) if ($p['tag'] === 'popular') $popularPlanId = (int)$p['id'];
?>
<?php foreach ($plans as $p):
    $isCurrent = $currentPlan && (int)$currentPlan['id'] === (int)$p['id'];
    $rows = get_plan_feature_rows($pdo, (int)$p['id']);
    $tagColor = PLAN_TAG_COLORS[$p['tag_color']] ?? '#e60023';

    $monthlyBase = (float)$p['price_monthly'];
    $monthlyDiscount = (float)$p['discount_monthly'];
    $monthlyFinal = $monthlyDiscount > 0 ? $monthlyBase * (1 - $monthlyDiscount / 100) : $monthlyBase;
    $yearlyBase = $monthlyBase * 12;
    $yearlyDiscount = (float)$p['discount_yearly'];
    $yearlyFinal = $yearlyDiscount > 0 ? $yearlyBase * (1 - $yearlyDiscount / 100) : $yearlyBase;
    $yearlyFinalMonthlyEquiv = $yearlyFinal / 12;

    $checkoutUrlMonthly = 'checkout?plan=' . (int)$p['id'] . '&cycle=monthly' . ($couponInfo ? '&coupon=' . urlencode($couponInfo['code']) : '');
    $checkoutUrlYearly = 'checkout?plan=' . (int)$p['id'] . '&cycle=yearly' . ($couponInfo ? '&coupon=' . urlencode($couponInfo['code']) : '');
?>
    <div class="plan-card <?= $isCurrent ? 'plan-card-current' : '' ?> <?= (int)$p['id'] === $popularPlanId ? 'plan-card-popular' : '' ?>"
         data-monthly-base="<?= e(number_format($monthlyBase, 2)) ?>" data-monthly-final="<?= e(number_format($monthlyFinal, 2)) ?>" data-monthly-discount="<?= (int)$monthlyDiscount ?>"
         data-yearly-base="<?= e(number_format($yearlyBase, 2)) ?>" data-yearly-final="<?= e(number_format($yearlyFinal, 2)) ?>" data-yearly-monthly-equiv="<?= e(number_format($yearlyFinalMonthlyEquiv, 2)) ?>" data-yearly-discount="<?= (int)$yearlyDiscount ?>"
         data-checkout-monthly="<?= e($checkoutUrlMonthly) ?>" data-checkout-yearly="<?= e($checkoutUrlYearly) ?>">
        <?php if ((int)$p['id'] === $popularPlanId): ?><div class="plan-popular-ribbon">Popular</div><?php endif; ?>
        <?php if ($p['tag'] && (int)$p['id'] !== $popularPlanId): ?><div class="plan-tag" style="background:<?= e($tagColor) ?>;"><?= e(PLAN_TAG_OPTIONS[$p['tag']] ?? $p['tag']) ?></div><?php endif; ?>

        <h3><?= e($p['name']) ?></h3>
        <?php if ($p['short_description']): ?><p class="muted plan-desc"><?= e($p['short_description']) ?></p><?php endif; ?>

        <div class="plan-price">
            <?php if ($monthlyDiscount > 0): ?><span class="plan-discount-badge js-plan-discount-badge"><?= (int)$monthlyDiscount ?>% OFF</span><?php endif; ?><br>
            <span class="plan-price-main">$<span class="js-plan-price"><?= number_format($monthlyFinal, 2) ?></span></span>
            <span class="muted">/<span class="js-plan-period">month</span></span>
            <?php if ($monthlyDiscount > 0): ?><div class="plan-price-was js-plan-was">Was $<?= number_format($monthlyBase, 2) ?>/month</div><?php endif; ?>
            <div class="muted js-plan-billed-note" style="display:none; font-size:12px;"></div>
        </div>

        <?php if (in_array($p['buy_button_position'], ['top', 'both'], true)): ?>
            <?php if ($isCurrent): ?>
                <button class="plan-buy-btn" disabled>Current Plan</button>
            <?php else: ?>
                <a href="<?= e($checkoutUrlMonthly) ?>" class="plan-buy-btn js-plan-buy-btn" style="background:<?= e($p['pay_button_bg']) ?>; color:<?= e($p['pay_button_text_color']) ?>; border-color:<?= e($p['pay_button_border_color']) ?>;"><?= e($p['pay_button_text']) ?></a>
            <?php endif; ?>
        <?php endif; ?>

        <ul class="plan-feature-list">
            <?php foreach ($rows as $r): ?>
                <li style="font-size:<?= e($r['text_size']) ?>; color:<?= e($r['text_color']) ?>; <?= $r['font'] ? 'font-family:' . e($r['font']) . ';' : '' ?>" title="<?= e($r['tooltip'] ?? '') ?>">
                    <span style="font-size:<?= e($r['checkmark_size']) ?>; color:<?= e($r['checkmark_color']) ?>;"><?= PLAN_CHECKMARK_TYPES[$r['checkmark_type']] ?? '✓' ?></span>
                    <?= e($r['text']) ?>
                </li>
            <?php endforeach; ?>
            <?php foreach ($usage['features'] as $fkey => $fdata): ?>
                <?php $has = (bool)($p[$fkey . '_enabled'] ?? false); ?>
                <li class="<?= $has ? 'plan-feature-yes' : 'plan-feature-no' ?>">
                    <span><?= $has ? '✓' : '✕' ?></span> <?= e($fdata['label']) ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (in_array($p['buy_button_position'], ['bottom', 'both'], true)): ?>
            <?php if ($isCurrent): ?>
                <button class="plan-buy-btn" disabled>Current Plan</button>
            <?php else: ?>
                <a href="<?= e($checkoutUrlMonthly) ?>" class="plan-buy-btn js-plan-buy-btn" style="background:<?= e($p['pay_button_bg']) ?>; color:<?= e($p['pay_button_text_color']) ?>; border-color:<?= e($p['pay_button_border_color']) ?>;"><?= e($p['pay_button_text']) ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php if ($pricingSettings['contact_sales_enabled']): ?>
<div class="card contact-sales-cta">
    <h2>Need custom limits?</h2>
    <p>Tell us your budget and the AI credits/limits you need — we'll set up a custom plan just for you.</p>
    <a href="contact-sales" class="btn-primary">Contact Sales</a>
</div>
<?php endif; ?>

<script>
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
.upgrade-tagline { max-width: 640px; margin-top: -10px; }
.plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 20px; margin: 20px 0; }
.plan-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:26px; position:relative; display:flex; flex-direction:column; overflow:hidden; }
.plan-card-current { border-color: var(--red); box-shadow: 0 0 0 2px var(--red) inset; }
.plan-card-popular { border-color: var(--red); box-shadow: 0 8px 28px rgba(230,0,35,0.12); transform: translateY(-4px); }
.plan-popular-ribbon {
    position: absolute; top: 14px; right: -32px; background: var(--red); color: #fff; font-size: 11px; font-weight: 700;
    padding: 4px 36px; transform: rotate(45deg); box-shadow: 0 2px 6px rgba(0,0,0,.15);
}
.plan-tag { display:inline-block; color:#fff; font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:10px; }
.plan-desc { min-height: 36px; }
.plan-price { margin: 6px 0 16px; }
.plan-discount-badge { display:inline-block; background:#dcfce7; color:#16a34a; font-size:11px; font-weight:800; padding:2px 8px; border-radius:10px; margin-bottom:4px; }
.plan-price-main { font-size:32px; font-weight:800; }
.plan-price-was { text-decoration: line-through; color: var(--gray); font-size: 13px; margin-top:2px; }
.plan-buy-btn { display:block; text-align:center; text-decoration:none !important; padding:12px; border-radius:10px; border:2px solid; font-weight:700; margin: 10px 0; cursor:pointer; }
.plan-feature-list { list-style:none; padding:0; margin:10px 0; flex:1; }
.plan-feature-list li { display:flex; align-items:flex-start; gap:8px; padding:6px 0; }
.plan-feature-yes span { color:#16a34a; font-weight:700; }
.plan-feature-no { color: var(--gray); }
.plan-feature-no span { color:#dc2626; font-weight:700; }
.billing-toggle-wrap { display:flex; align-items:center; justify-content:center; gap:12px; margin: 24px 0 6px; flex-wrap:wrap; text-align:center; }
.billing-toggle-label { font-weight:700; }
.billing-save-badge { background:#16a34a; color:#fff; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; }
.usage-panel { display:none; }
.usage-panel.open { display:block; }
.usage-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:14px; margin-top:10px; }
.usage-item { background: var(--light); border-radius:10px; padding:12px; }
.usage-item span { display:block; font-size:12px; color:var(--gray); margin-bottom:4px; }
.usage-item-warn { background:#fef2f2; }
.usage-bar { height:6px; background:#e5e7eb; border-radius:4px; margin-top:8px; overflow:hidden; }
.usage-bar-fill { height:100%; border-radius:4px; }
.usage-warn-link { display:block; font-size:12px; color:#dc2626; font-weight:700; margin-top:6px; }
.feature-flags-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:10px; margin-top:10px; }
.feature-flag { border-radius:8px; padding:10px 12px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; }
.feature-flag-on { background:#f0fdf4; color:#15803d; }
.feature-flag-off { background:#fef2f2; color:#b91c1c; }
.feature-flag-off a { margin-left:auto; text-decoration:underline !important; }
.plan-mini-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:14px; margin-top:10px; }
.plan-mini-card { background: var(--light); border-radius:10px; padding:12px; text-align:center; }
.plan-mini-name { font-weight:700; } .plan-mini-price { color:var(--gray); font-size:13px; margin:4px 0 8px; }
.contact-sales-cta { text-align:center; background: linear-gradient(135deg, #fff0f2, #fff); border:1px solid #ffd7db; margin-top:20px; }
[data-theme="dark"] .plan-card { background:#1c1e24; }
[data-theme="dark"] .usage-item, [data-theme="dark"] .plan-mini-card { background:#17181c; }
[data-theme="dark"] .usage-item-warn { background:#241417; }
[data-theme="dark"] .feature-flag-on { background:#0f2418; }
[data-theme="dark"] .feature-flag-off { background:#241417; }
[data-theme="dark"] .contact-sales-cta { background: linear-gradient(135deg, #241417, #1c1e24); border-color:#3a2226; }
</style>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
