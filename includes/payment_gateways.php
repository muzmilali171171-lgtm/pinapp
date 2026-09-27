<?php
/**
 * Online checkout for Stripe, PayPal, NOWPayments (crypto) and Binance Pay.
 *
 * Flow: user/checkout.php creates a pending plan_payments row → pg_create_checkout() opens the
 * gateway's hosted payment page → the buyer comes back to user/payment-return.php, which asks the
 * gateway whether the payment really went through (never trusts the browser), and/or the gateway
 * calls webhooks/<gateway>.php. Either path ends in pg_mark_paid(), which activates the plan exactly
 * once (same steps as an admin approval: plan, coupon, affiliate commission, notification).
 */

require_once __DIR__ . '/pricing_functions.php';
require_once __DIR__ . '/affiliate_functions.php';

const PG_LABELS = ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'nowpayments' => 'NOWPayments (Crypto)', 'binance' => 'Binance Pay'];

/** Is this gateway switched on AND filled in (so checkout can really start)? */
function pg_ready(array $g, string $gw): bool
{
    if (empty($g[$gw . '_enabled'])) return false;
    switch ($gw) {
        case 'stripe': return $g['stripe_secret_key'] !== '';
        case 'paypal': return $g['paypal_client_id'] !== '' && $g['paypal_secret'] !== '';
        case 'nowpayments': return $g['nowpayments_api_key'] !== '';
        case 'binance': return $g['binance_api_key'] !== '' && $g['binance_secret_key'] !== '';
    }
    return false;
}

/** Small HTTP helper. Returns [http_code, decoded_json|null, raw_body]. */
function pg_http(string $method, string $url, array $headers = [], ?string $body = null, ?string $basicAuth = null): array
{
    if (isset($GLOBALS['PG_HTTP_MOCK']) && is_callable($GLOBALS['PG_HTTP_MOCK'])) {   // tests only
        return ($GLOBALS['PG_HTTP_MOCK'])($method, $url, $headers, $body);
    }
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_USERAGENT => 'AutomatedPin/1.0'];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    if ($basicAuth !== null) $opts[CURLOPT_USERPWD] = $basicAuth;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return [0, null, $err];
    return [$code, json_decode($raw, true), $raw];
}

function pg_return_url(int $paymentId, string $gw): string
{
    return rtrim(APP_URL, '/') . '/user/payment-return?pid=' . $paymentId . '&gw=' . $gw;
}

function pg_payment(PDO $pdo, int $paymentId): ?array
{
    $st = $pdo->prepare("SELECT pp.*, p.name AS plan_name FROM plan_payments pp JOIN pricing_plans p ON p.id = pp.plan_id WHERE pp.id = ?");
    $st->execute([$paymentId]);
    return $st->fetch() ?: null;
}

function pg_log(PDO $pdo, string $msg, ?int $userId = null): void
{
    try { log_event($pdo, 'system', '[payments] ' . $msg, $userId); } catch (Throwable $e) { /* logging is best-effort */ }
}

/**
 * Starts a hosted checkout. Returns ['ok' => true, 'url' => …] or ['ok' => false, 'error' => …].
 * $payment is the pending plan_payments row; the gateway reference is saved on it.
 */
function pg_create_checkout(PDO $pdo, array $payment, array $user, string $gw): array
{
    $g = payment_gateway_settings_get($pdo);
    if (!pg_ready($g, $gw)) return ['ok' => false, 'error' => 'This payment method is not set up yet.'];
    $pid = (int)$payment['id'];
    $amount = round((float)$payment['amount'], 2);
    if ($amount <= 0) return ['ok' => false, 'error' => 'Nothing to pay for this plan.'];
    $desc = SITE_BRAND . ' — ' . $payment['plan_name'] . ' plan (' . $payment['billing_cycle'] . ')';
    $cancel = rtrim(APP_URL, '/') . '/user/checkout?plan=' . (int)$payment['plan_id'] . '&cycle=' . $payment['billing_cycle'] . '&cancelled=1';
    $ref = null; $url = null; $err = null;

    try {
        if ($gw === 'stripe') {
            $body = http_build_query([
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($amount * 100), 'product_data' => ['name' => $desc]],
                ]],
                'success_url' => pg_return_url($pid, 'stripe') . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancel,
                'client_reference_id' => (string)$pid,
                'customer_email' => $user['email'] ?? null,
                'metadata' => ['payment_id' => (string)$pid, 'user_id' => (string)$user['id']],
            ]);
            [$code, $j] = pg_http('POST', 'https://api.stripe.com/v1/checkout/sessions',
                ['Authorization: Bearer ' . $g['stripe_secret_key'], 'Content-Type: application/x-www-form-urlencoded'], $body);
            if ($code === 200 && !empty($j['url'])) { $ref = $j['id']; $url = $j['url']; }
            else $err = 'Stripe: ' . ($j['error']['message'] ?? 'HTTP ' . $code);
        } elseif ($gw === 'paypal') {
            $token = pg_paypal_token($g);
            if (!$token) throw new RuntimeException('PayPal: could not sign in with the Client ID / Secret.');
            $body = json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string)$pid, 'custom_id' => (string)$pid, 'description' => mb_substr($desc, 0, 120),
                    'amount' => ['currency_code' => 'USD', 'value' => number_format($amount, 2, '.', '')],
                ]],
                'application_context' => [
                    'brand_name' => mb_substr(SITE_BRAND, 0, 120), 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
                    'return_url' => pg_return_url($pid, 'paypal'), 'cancel_url' => $cancel,
                ],
            ]);
            [$code, $j] = pg_http('POST', pg_paypal_base($g) . '/v2/checkout/orders',
                ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'PayPal-Request-Id: ap-' . $pid], $body);
            foreach (($j['links'] ?? []) as $l) if (($l['rel'] ?? '') === 'approve' || ($l['rel'] ?? '') === 'payer-action') $url = $l['href'];
            if (in_array($code, [200, 201], true) && $url) $ref = $j['id'];
            else { $url = null; $err = 'PayPal: ' . ($j['message'] ?? $j['error_description'] ?? 'HTTP ' . $code); }
        } elseif ($gw === 'nowpayments') {
            $body = json_encode([
                'price_amount' => $amount, 'price_currency' => 'usd',
                'order_id' => (string)$pid, 'order_description' => $desc,
                'ipn_callback_url' => rtrim(APP_URL, '/') . '/webhooks/nowpayments.php',
                'success_url' => pg_return_url($pid, 'nowpayments'), 'cancel_url' => $cancel,
            ]);
            [$code, $j] = pg_http('POST', 'https://api.nowpayments.io/v1/invoice',
                ['x-api-key: ' . $g['nowpayments_api_key'], 'Content-Type: application/json'], $body);
            if (in_array($code, [200, 201], true) && !empty($j['invoice_url'])) { $ref = (string)$j['id']; $url = $j['invoice_url']; }
            else $err = 'NOWPayments: ' . ($j['message'] ?? 'HTTP ' . $code);
        } elseif ($gw === 'binance') {
            $tradeNo = 'AP' . $pid . 'T' . time();
            $body = json_encode([
                'env' => ['terminalType' => 'WEB'],
                'merchantTradeNo' => $tradeNo,
                'orderAmount' => (float)number_format($amount, 2, '.', ''),
                'currency' => 'USDT',
                'description' => mb_substr($desc, 0, 256),
                'goodsDetails' => [['goodsType' => '02', 'goodsCategory' => 'Z000', 'referenceGoodsId' => 'plan' . (int)$payment['plan_id'], 'goodsName' => mb_substr($desc, 0, 256)]],
                'returnUrl' => pg_return_url($pid, 'binance'), 'cancelUrl' => $cancel,
                'webhookUrl' => rtrim(APP_URL, '/') . '/webhooks/binance.php',
            ]);
            [$code, $j] = pg_binance_call($g, '/binancepay/openapi/v3/order', $body);
            if (($j['status'] ?? '') === 'SUCCESS' && !empty($j['data']['checkoutUrl'])) { $ref = $tradeNo; $url = $j['data']['checkoutUrl']; }
            else $err = 'Binance Pay: ' . ($j['errorMessage'] ?? 'HTTP ' . $code);
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }

    if (!$url) {
        pg_log($pdo, "checkout start failed for payment #$pid ($gw): $err", (int)$user['id']);
        $pdo->prepare("UPDATE plan_payments SET status = 'rejected', admin_message = ?, decided_at = NOW() WHERE id = ? AND status = 'pending'")
            ->execute([mb_substr('Online checkout could not start: ' . $err, 0, 500), $pid]);
        return ['ok' => false, 'error' => 'We couldn’t open the ' . (PG_LABELS[$gw] ?? $gw) . ' payment page right now. Please try again in a moment or choose another payment method.'];
    }
    $pdo->prepare("UPDATE plan_payments SET gateway_reference = ? WHERE id = ?")->execute([$ref, $pid]);
    return ['ok' => true, 'url' => $url];
}

/* ---------------- verification: ask the gateway, never trust the browser ---------------- */

/**
 * Checks with the gateway whether this payment is paid. Returns 'paid', 'pending' or 'failed'.
 * $extra: values the gateway sent back to the return URL (session_id / token / …).
 */
function pg_verify(PDO $pdo, array $payment, array $extra = []): string
{
    $g = payment_gateway_settings_get($pdo);
    $gw = $payment['payment_method'];
    $pid = (int)$payment['id'];
    $amount = round((float)$payment['amount'], 2);
    $ref = (string)($payment['gateway_reference'] ?? '');
    try {
        if ($gw === 'stripe' && $ref !== '') {
            [$code, $j] = pg_http('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($ref), ['Authorization: Bearer ' . $g['stripe_secret_key']]);
            if ($code !== 200) return 'pending';
            if ((string)($j['metadata']['payment_id'] ?? $j['client_reference_id'] ?? '') !== (string)$pid) return 'failed';
            if (($j['payment_status'] ?? '') === 'paid' && (int)($j['amount_total'] ?? 0) >= (int)round($amount * 100)) return 'paid';
            return ($j['status'] ?? '') === 'expired' ? 'failed' : 'pending';
        }
        if ($gw === 'paypal' && $ref !== '') {
            $token = pg_paypal_token($g);
            if (!$token) return 'pending';
            $h = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
            [$code, $j] = pg_http('GET', pg_paypal_base($g) . '/v2/checkout/orders/' . rawurlencode($ref), $h);
            if ($code !== 200) return 'pending';
            if (($j['status'] ?? '') === 'APPROVED') {   // buyer approved → capture the money now
                [$code, $j] = pg_http('POST', pg_paypal_base($g) . '/v2/checkout/orders/' . rawurlencode($ref) . '/capture', array_merge($h, ['PayPal-Request-Id: ap-cap-' . $pid]), '{}');
                if (!in_array($code, [200, 201], true)) return 'pending';
            }
            if (($j['status'] ?? '') !== 'COMPLETED') return in_array($j['status'] ?? '', ['VOIDED'], true) ? 'failed' : 'pending';
            $unit = $j['purchase_units'][0] ?? [];
            if ((string)($unit['custom_id'] ?? $unit['reference_id'] ?? '') !== (string)$pid) return 'failed';
            $cap = $unit['payments']['captures'][0] ?? null;
            $paid = (float)($cap['amount']['value'] ?? $unit['amount']['value'] ?? 0);
            return $paid + 0.001 >= $amount ? 'paid' : 'failed';
        }
        if ($gw === 'nowpayments') {
            // Crypto confirms on the blockchain: the IPN webhook finishes it. With a payment id we can also ask.
            $np = preg_replace('/\D/', '', (string)($extra['NP_id'] ?? $extra['payment_id'] ?? ''));
            if ($np !== '') {
                [$code, $j] = pg_http('GET', 'https://api.nowpayments.io/v1/payment/' . $np, ['x-api-key: ' . $g['nowpayments_api_key']]);
                if ($code === 200 && (string)($j['order_id'] ?? '') === (string)$pid) {
                    if (in_array($j['payment_status'] ?? '', ['finished', 'confirmed'], true) && (float)($j['price_amount'] ?? 0) + 0.001 >= $amount) return 'paid';
                    if (in_array($j['payment_status'] ?? '', ['failed', 'expired', 'refunded'], true)) return 'failed';
                }
            }
            return 'pending';
        }
        if ($gw === 'binance' && $ref !== '') {
            [$code, $j] = pg_binance_call($g, '/binancepay/openapi/v2/order/query', json_encode(['merchantTradeNo' => $ref]));
            $st = $j['data']['status'] ?? '';
            if ($st === 'PAID' && (float)($j['data']['orderAmount'] ?? 0) + 0.001 >= $amount) return 'paid';
            if (in_array($st, ['CANCELED', 'EXPIRED', 'ERROR', 'REFUNDED', 'FULLY_REFUNDED'], true)) return 'failed';
            return 'pending';
        }
    } catch (Throwable $e) {
        pg_log($pdo, "verify failed for payment #$pid ($gw): " . $e->getMessage());
    }
    return 'pending';
}

/** Activates the plan for a paid payment — exactly once, whichever path gets here first. */
function pg_mark_paid(PDO $pdo, int $paymentId, string $note): bool
{
    $upd = $pdo->prepare("UPDATE plan_payments SET status = 'approved', admin_message = ?, decided_at = NOW() WHERE id = ? AND status = 'pending'");
    $upd->execute([mb_substr($note, 0, 500), $paymentId]);
    if ($upd->rowCount() === 0) return false;   // already handled (or not pending)
    $payment = pg_payment($pdo, $paymentId);
    if (!$payment) return false;
    activate_plan_for_user($pdo, (int)$payment['user_id'], (int)$payment['plan_id'], $payment['billing_cycle']);
    if ($payment['coupon_id']) {
        $pdo->prepare("INSERT INTO coupon_redemptions (coupon_id, user_id, plan_id) VALUES (?,?,?)")->execute([$payment['coupon_id'], $payment['user_id'], $payment['plan_id']]);
    }
    affiliate_record_commission_for_payment($pdo, $payment);
    create_notification($pdo, (int)$payment['user_id'], 'plan', 'Payment received', 'Thank you! Your ' . $payment['plan_name'] . ' plan is now active.', '/user/upgrade');
    pg_log($pdo, "payment #$paymentId paid online ({$payment['payment_method']}) — plan activated", (int)$payment['user_id']);
    return true;
}

function pg_mark_failed(PDO $pdo, int $paymentId, string $note): void
{
    $pdo->prepare("UPDATE plan_payments SET status = 'rejected', admin_message = ?, decided_at = NOW() WHERE id = ? AND status = 'pending'")
        ->execute([mb_substr($note, 0, 500), $paymentId]);
}

/* ---------------- gateway helpers ---------------- */

function pg_paypal_base(array $g): string
{
    return $g['paypal_mode'] === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

function pg_paypal_token(array $g): ?string
{
    [$code, $j] = pg_http('POST', pg_paypal_base($g) . '/v1/oauth2/token', ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        'grant_type=client_credentials', $g['paypal_client_id'] . ':' . $g['paypal_secret']);
    return $code === 200 && !empty($j['access_token']) ? $j['access_token'] : null;
}

/** Signed Binance Pay API call (HMAC-SHA512 of timestamp \n nonce \n body \n). */
function pg_binance_call(array $g, string $path, string $body): array
{
    $ts = (string)round(microtime(true) * 1000);
    $nonce = bin2hex(random_bytes(16));
    $sig = strtoupper(hash_hmac('sha512', $ts . "\n" . $nonce . "\n" . $body . "\n", $g['binance_secret_key']));
    return pg_http('POST', 'https://bpay.binanceapi.com' . $path, [
        'Content-Type: application/json', 'BinancePay-Timestamp: ' . $ts, 'BinancePay-Nonce: ' . $nonce,
        'BinancePay-Certificate-SN: ' . $g['binance_api_key'], 'BinancePay-Signature: ' . $sig,
    ], $body);
}

/** Stripe webhook signature check ("Stripe-Signature: t=…,v1=…", 5-minute tolerance). */
function pg_stripe_signature_ok(string $payload, string $header, string $secret): bool
{
    if ($secret === '' || $header === '') return false;
    $t = null; $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') $t = $v; elseif ($k === 'v1') $sigs[] = $v;
    }
    if (!$t || !$sigs || abs(time() - (int)$t) > 300) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
    return false;
}

/** NOWPayments IPN signature: HMAC-SHA512 of the JSON body with keys sorted, using the IPN secret. */
function pg_nowpayments_signature_ok(string $payload, string $header, string $secret): bool
{
    if ($secret === '' || $header === '') return false;
    $data = json_decode($payload, true);
    if (!is_array($data)) return false;
    $sort = function (&$a) use (&$sort) { if (is_array($a)) { ksort($a); foreach ($a as &$v) $sort($v); } };
    $sort($data);
    $expected = hash_hmac('sha512', json_encode($data, JSON_UNESCAPED_SLASHES), $secret);
    return hash_equals($expected, strtolower(trim($header)));
}
