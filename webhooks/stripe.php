<?php
/** Stripe webhook (checkout.session.completed): verified signature, then the session is re-checked with Stripe. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/payment_gateways.php';

$payload = (string)file_get_contents('php://input');
$g = payment_gateway_settings_get($pdo);
if (!pg_stripe_signature_ok($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $g['stripe_webhook_secret'])) {
    http_response_code(400); exit('bad signature');
}
$event = json_decode($payload, true);
$session = $event['data']['object'] ?? [];
if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    $payment = pg_payment($pdo, (int)($session['metadata']['payment_id'] ?? $session['client_reference_id'] ?? 0));
    if ($payment && $payment['payment_method'] === 'stripe' && $payment['status'] === 'pending' && $payment['gateway_reference'] === ($session['id'] ?? '')) {
        if (pg_verify($pdo, $payment) === 'paid') pg_mark_paid($pdo, (int)$payment['id'], 'Paid online via Stripe');
    }
}
http_response_code(200);
echo 'ok';
