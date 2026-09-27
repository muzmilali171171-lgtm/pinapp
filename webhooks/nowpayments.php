<?php
/** NOWPayments IPN: signature checked with the IPN secret, amount and order checked before activation. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/payment_gateways.php';

$payload = (string)file_get_contents('php://input');
$g = payment_gateway_settings_get($pdo);
if (!pg_nowpayments_signature_ok($payload, (string)($_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? ''), $g['nowpayments_ipn_secret'])) {
    http_response_code(400); exit('bad signature');
}
$d = json_decode($payload, true) ?: [];
$payment = pg_payment($pdo, (int)($d['order_id'] ?? 0));
if ($payment && $payment['payment_method'] === 'nowpayments' && $payment['status'] === 'pending') {
    $status = (string)($d['payment_status'] ?? '');
    $paidEnough = (float)($d['price_amount'] ?? 0) + 0.001 >= (float)$payment['amount'] && strtolower((string)($d['price_currency'] ?? 'usd')) === 'usd';
    if (in_array($status, ['finished', 'confirmed'], true) && $paidEnough) {
        pg_mark_paid($pdo, (int)$payment['id'], 'Paid online via NOWPayments (payment ' . preg_replace('/\D/', '', (string)($d['payment_id'] ?? '')) . ')');
    } elseif (in_array($status, ['failed', 'expired', 'refunded'], true)) {
        pg_mark_failed($pdo, (int)$payment['id'], 'NOWPayments: ' . $status);
    }
}
http_response_code(200);
echo 'ok';
