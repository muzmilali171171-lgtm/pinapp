<?php
/**
 * The buyer comes back here from Stripe / PayPal / NOWPayments / Binance Pay. We ask the gateway
 * ourselves whether the payment really went through, then activate the plan (pg_mark_paid).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/payment_gateways.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'upgrade';
$pageTitle = 'Payment';

$payment = pg_payment($pdo, (int)($_GET['pid'] ?? 0));
if (!$payment || (int)$payment['user_id'] !== (int)$user['id'] || !isset(PG_LABELS[$payment['payment_method']])) redirect('upgrade');

$state = $payment['status'] === 'approved' ? 'paid' : ($payment['status'] === 'rejected' ? 'failed' : null);
if ($state === null) {
    $state = pg_verify($pdo, $payment, $_GET);
    if ($state === 'paid') pg_mark_paid($pdo, (int)$payment['id'], 'Paid online via ' . PG_LABELS[$payment['payment_method']]);
    if ($state === 'failed') pg_mark_failed($pdo, (int)$payment['id'], 'Payment not completed at ' . PG_LABELS[$payment['payment_method']]);
}
$label = PG_LABELS[$payment['payment_method']];

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Payment — <?= e($payment['plan_name']) ?></h1></div>
<div class="card" style="text-align:center; padding:40px 20px;">
    <?php if ($state === 'paid'): ?>
        <div style="font-size:48px;">🎉</div>
        <h2>Payment received — your <?= e($payment['plan_name']) ?> plan is active!</h2>
        <p class="muted">Paid $<?= number_format((float)$payment['amount'], 2) ?> with <?= e($label) ?>. Thank you!</p>
        <a href="dashboard" class="btn-primary">Go to Dashboard →</a>
    <?php elseif ($state === 'failed'): ?>
        <div style="font-size:48px;">⚠️</div>
        <h2>The payment was not completed</h2>
        <p class="muted">Nothing was activated. If money was taken from your account, contact support with payment #<?= (int)$payment['id'] ?>.</p>
        <a href="checkout?plan=<?= (int)$payment['plan_id'] ?>&cycle=<?= e($payment['billing_cycle']) ?>" class="btn-primary">Try Again</a>
    <?php else: ?>
        <div style="font-size:48px;">⏳</div>
        <h2>Waiting for payment confirmation…</h2>
        <p class="muted"><?= $payment['payment_method'] === 'nowpayments'
            ? 'Crypto payments are confirmed on the blockchain — this usually takes a few minutes.'
            : 'We’re confirming your payment with ' . e($label) . '.' ?>
            Your plan activates automatically — you can leave this page; you'll get a notification.</p>
        <p class="muted" style="font-size:13px;">This page checks again every 20 seconds.</p>
        <a href="upgrade" class="btn-secondary">Back to Plans</a>
        <script>setTimeout(function () { location.reload(); }, 20000);</script>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
