<?php
/**
 * Binance Pay webhook. The payload is only a hint: the order is re-checked with Binance's signed
 * query API before anything is activated, so a forged call can't activate a plan.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/payment_gateways.php';

header('Content-Type: application/json');
$d = json_decode((string)file_get_contents('php://input'), true) ?: [];
$data = is_array($d['data'] ?? null) ? $d['data'] : (json_decode((string)($d['data'] ?? ''), true) ?: []);
$tradeNo = (string)($data['merchantTradeNo'] ?? '');
if (preg_match('/^AP(\d+)T\d+$/', $tradeNo, $m)) {
    $payment = pg_payment($pdo, (int)$m[1]);
    if ($payment && $payment['payment_method'] === 'binance' && $payment['status'] === 'pending' && $payment['gateway_reference'] === $tradeNo) {
        $state = pg_verify($pdo, $payment);
        if ($state === 'paid') pg_mark_paid($pdo, (int)$payment['id'], 'Paid online via Binance Pay');
        elseif ($state === 'failed') pg_mark_failed($pdo, (int)$payment['id'], 'Binance Pay order not paid');
    }
}
echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
