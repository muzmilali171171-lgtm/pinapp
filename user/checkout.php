<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'upgrade';
$pageTitle = 'Checkout';

$planId = (int)($_GET['plan'] ?? $_POST['plan_id'] ?? 0);
$plan = get_plan($pdo, $planId);
if (!$plan) redirect('upgrade');

$billingCycle = (($_GET['cycle'] ?? $_POST['billing_cycle'] ?? 'monthly') === 'yearly') ? 'yearly' : 'monthly';
$errors = [];
$done = false;

// Free plan — no checkout needed, activate immediately.
if ($plan['is_free']) {
    activate_plan_for_user($pdo, (int)$user['id'], (int)$plan['id'], 'monthly');
    redirect('upgrade?activated=1');
}

$gateways = payment_gateway_settings_get($pdo);
$customMethods = get_custom_payment_methods($pdo, true);

function checkout_price(array $plan, string $cycle): array
{
    $discount = $cycle === 'yearly' ? (float)$plan['discount_yearly'] : (float)$plan['discount_monthly'];
    $base = (float)$plan['price_monthly'] * ($cycle === 'yearly' ? 12 : 1);
    $final = $discount > 0 ? $base * (1 - $discount / 100) : $base;
    return ['base' => $base, 'discount' => $discount, 'final' => $final];
}

$couponCode = trim($_GET['coupon'] ?? $_POST['coupon_code'] ?? '');
$coupon = null;
$couponError = null;
if ($couponCode !== '') {
    $c = get_coupon_by_code($pdo, $couponCode);
    if (!$c) {
        $couponError = "Coupon \"$couponCode\" was not found.";
    } elseif ($c['status'] !== 'active') {
        $couponError = "Coupon \"$couponCode\" is no longer active.";
    } elseif (!empty($c['end_date']) && strtotime($c['end_date']) < strtotime(date('Y-m-d'))) {
        $couponError = "Coupon \"$couponCode\" has expired.";
    } elseif (!coupon_applies_to_plan($pdo, $c, (int)$plan['id'])) {
        $couponError = "Coupon \"$couponCode\" doesn't apply to the {$plan['name']} plan.";
    } else {
        $coupon = $c;
    }
}

$price = checkout_price($plan, $billingCycle);
$finalAmount = $price['final'];
if ($coupon) $finalAmount = $finalAmount * (1 - (float)$coupon['discount_percent'] / 100);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'] ?? '';
    $customMethodId = null;
    $screenshotPath = null;

    $validGateway = in_array($method, ['stripe', 'paypal', 'nowpayments', 'binance'], true) && !empty($gateways["{$method}_enabled"]);
    if (strpos($method, 'custom:') === 0) {
        $customMethodId = (int)substr($method, 7);
        $validCustom = false;
        foreach ($customMethods as $cm) if ((int)$cm['id'] === $customMethodId) $validCustom = true;
    } else {
        $validCustom = false;
    }

    if (!$validGateway && !$validCustom) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if ($validCustom && empty($_FILES['screenshot']['tmp_name'])) {
        $errors[] = 'Please upload a screenshot as proof of payment.';
    }

    if (empty($errors)) {
        if ($validCustom) {
            $uploadDir = __DIR__ . '/../uploads/payments';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
            $ext = preg_match('/^(jpg|jpeg|png|gif|webp|pdf)$/i', $ext) ? $ext : 'jpg';
            $filename = 'payment_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], "$uploadDir/$filename")) {
                $screenshotPath = 'uploads/payments/' . $filename;
            } else {
                $errors[] = 'Could not save the uploaded screenshot. Please try again.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO plan_payments (user_id, plan_id, billing_cycle, amount, coupon_id, payment_method, custom_payment_method_id, proof_screenshot_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $user['id'], $plan['id'], $billingCycle, $finalAmount, $coupon['id'] ?? null,
                $validCustom ? 'custom:' . $customMethodId : $method, $customMethodId, $screenshotPath,
            ]);
            log_event($pdo, 'system', "User requested plan '{$plan['name']}' via " . ($validCustom ? 'custom payment method' : $method), $user['id']);
            $done = true;
        }
    }
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Checkout — <?= e($plan['name']) ?></h1><a href="upgrade" class="btn-secondary">← Back to plans</a></div>

<?php if ($done): ?>
    <div class="card">
        <div class="alert alert-success">Your payment request was submitted and is pending review. You'll get a
        notification as soon as it's approved and your plan is activated.</div>
        <a href="upgrade" class="btn-secondary">Back to Plans</a>
    </div>
<?php else: ?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
    <h2>Order Summary</h2>
    <p><?= e($plan['name']) ?> — <?= e(ucfirst($billingCycle)) ?> billing</p>
    <?php if ($price['discount'] > 0 || $coupon): ?><p class="muted" style="text-decoration:line-through;">$<?= number_format($price['base'], 2) ?></p><?php endif; ?>
    <p style="font-size:24px; font-weight:800;">$<?= number_format($finalAmount, 2) ?></p>
    <?php if ($coupon): ?><p class="muted" style="color:#16a34a;">✓ Coupon <?= e($coupon['code']) ?> applied (<?= e($coupon['discount_percent']) ?>% off)</p><?php endif; ?>
    <?php if ($couponError): ?><p class="muted" style="color:#dc2626;"><?= e($couponError) ?></p><?php endif; ?>

    <form method="GET" style="display:flex; gap:8px; align-items:flex-end; margin-top:10px;">
        <input type="hidden" name="plan" value="<?= (int)$plan['id'] ?>">
        <input type="hidden" name="cycle" value="<?= e($billingCycle) ?>">
        <div class="form-row" style="margin-bottom:0; flex:1;">
            <label>Coupon code</label>
            <input type="text" name="coupon" value="<?= e($couponCode) ?>" placeholder="Enter coupon code">
        </div>
        <button type="submit" class="btn-secondary">Apply</button>
    </form>
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
    <input type="hidden" name="billing_cycle" value="<?= e($billingCycle) ?>">
    <input type="hidden" name="coupon_code" value="<?= e($couponCode) ?>">

    <div class="card">
        <h2>Payment Method</h2>
        <?php $anyMethod = false; ?>
        <?php foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal', 'nowpayments' => 'NOWPayments (Crypto)', 'binance' => 'Binance Pay'] as $key => $label): ?>
            <?php if (!empty($gateways["{$key}_enabled"])): $anyMethod = true; ?>
                <label class="checkbox-row"><input type="radio" name="payment_method" value="<?= e($key) ?>" onchange="showMethodDetails('<?= e($key) ?>')"> <?= e($label) ?></label>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($customMethods as $cm): $anyMethod = true; ?>
            <label class="checkbox-row"><input type="radio" name="payment_method" value="custom:<?= (int)$cm['id'] ?>" onchange="showMethodDetails('custom:<?= (int)$cm['id'] ?>')"> <?= e($cm['name']) ?></label>
        <?php endforeach; ?>
        <?php if (!$anyMethod): ?><p class="muted">No payment methods are configured yet — please contact support.</p><?php endif; ?>

        <?php foreach (['stripe', 'paypal', 'nowpayments', 'binance'] as $key): if (empty($gateways["{$key}_enabled"])) continue; ?>
            <div class="method-details" id="method-<?= e($key) ?>" style="display:none; margin-top:14px;">
                <div class="alert alert-info">Online checkout with <?= e(ucfirst($key)) ?> isn't available right now.
                Submit below to save your request — our team will contact you to complete the payment.</div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($customMethods as $cm): ?>
            <div class="method-details" id="method-custom:<?= (int)$cm['id'] ?>" style="display:none; margin-top:14px;">
                <div class="guide-box"><?= $cm['details_html'] ?></div>
                <div class="form-row">
                    <label>Upload payment screenshot</label>
                    <input type="file" name="screenshot" accept="image/*,.pdf">
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($anyMethod): ?><button type="submit" class="btn-primary">Submit Payment Request</button><?php endif; ?>
</form>
<script>
function showMethodDetails(key) {
    document.querySelectorAll('.method-details').forEach(function (el) { el.style.display = 'none'; });
    var el = document.getElementById('method-' + key);
    if (el) el.style.display = 'block';
}
</script>

<?php endif; ?>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
