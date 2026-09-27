<?php
/**
 * Affiliate Program — every user can become an affiliate: they get a referral link
 * (?ref=CODE), earn a commission on plan purchases their referrals make, and request
 * payouts once their balance clears the admin's minimum threshold.
 *
 * Flow:
 *   1. affiliate_get_or_create_code() gives a user their own link (user/affiliate-dashboard.php).
 *   2. affiliate_track_click() runs from index.php on every ?ref=CODE visit — logs the
 *      click and drops a cookie.
 *   3. affiliate_attach_referral() runs from auth/register.php right after a new user
 *      is inserted — reads that cookie and links the new user to the affiliate.
 *   4. affiliate_record_commission_for_payment() runs from admin/plan-users.php whenever
 *      an admin approves a plan_payments row (first purchase OR a later renewal) — credits
 *      the affiliate if the referral is still inside the commission window.
 *   5. affiliate_request_payout() / affiliate_admin_decide_payout() handle withdrawals.
 *
 * Every read function here follows the same try/catch-safe pattern as pricing_functions.php:
 * a missing/unmigrated table must never 500 a page — see affiliate_tables_ready() below,
 * which every Affiliate page checks first, same as plan_pricing_tables_ready().
 */

const AFFILIATE_COOKIE_NAME = 'aff_ref';
const AFFILIATE_METHOD_LABELS = ['paypal' => 'PayPal', 'crypto' => 'Crypto Wallet (USDT · TRC20)', 'binance' => 'Binance Pay'];

/** True once migrate.php has created the Affiliate tables/columns on this database. */
function affiliate_tables_ready(PDO $pdo): bool
{
    try {
        $pdo->query("SELECT id FROM affiliate_settings LIMIT 1");
        $pdo->query("SELECT id FROM affiliate_referrals LIMIT 1");
        $pdo->query("SELECT id FROM affiliate_commissions LIMIT 1");
        $pdo->query("SELECT id FROM affiliate_payouts LIMIT 1");
        $pdo->query("SELECT affiliate_code FROM users LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ===================== Settings (Admin → Affiliate → Settings) =====================

function affiliate_settings_get(PDO $pdo): array
{
    $defaults = [
        'program_enabled' => true,
        'commission_percent' => 40.0,
        'duration_type' => 'lifetime',
        'duration_months' => null,
        'min_payout_threshold' => 50.0,
        'cookie_days' => 60,
        'paypal_enabled' => true,
        'crypto_enabled' => true,
        'binance_enabled' => true,
    ];
    try {
        $row = $pdo->query("SELECT * FROM affiliate_settings ORDER BY id DESC LIMIT 1")->fetch();
        if (!$row) return $defaults;
        return [
            'program_enabled' => (bool)$row['program_enabled'],
            'commission_percent' => (float)$row['commission_percent'],
            'duration_type' => $row['duration_type'] === 'custom' ? 'custom' : 'lifetime',
            'duration_months' => $row['duration_months'] !== null ? (int)$row['duration_months'] : null,
            'min_payout_threshold' => (float)$row['min_payout_threshold'],
            'cookie_days' => (int)$row['cookie_days'],
            'paypal_enabled' => (bool)$row['paypal_enabled'],
            'crypto_enabled' => (bool)$row['crypto_enabled'],
            'binance_enabled' => (bool)$row['binance_enabled'],
        ];
    } catch (Throwable $e) {
        return $defaults;
    }
}

function affiliate_settings_save(PDO $pdo, array $f): void
{
    $durationType = ($f['duration_type'] ?? 'lifetime') === 'custom' ? 'custom' : 'lifetime';
    $cols = [
        'program_enabled' => !empty($f['program_enabled']) ? 1 : 0,
        'commission_percent' => max(0, (float)($f['commission_percent'] ?? 40)),
        'duration_type' => $durationType,
        'duration_months' => $durationType === 'custom' ? max(1, (int)($f['duration_months'] ?? 1)) : null,
        'min_payout_threshold' => max(0, (float)($f['min_payout_threshold'] ?? 50)),
        'cookie_days' => max(1, (int)($f['cookie_days'] ?? 60)),
        'paypal_enabled' => !empty($f['paypal_enabled']) ? 1 : 0,
        'crypto_enabled' => !empty($f['crypto_enabled']) ? 1 : 0,
        'binance_enabled' => !empty($f['binance_enabled']) ? 1 : 0,
    ];
    $existing = $pdo->query("SELECT id FROM affiliate_settings ORDER BY id DESC LIMIT 1")->fetch();
    if ($existing) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols)));
        $pdo->prepare("UPDATE affiliate_settings SET $set WHERE id = ?")->execute([...array_values($cols), $existing['id']]);
    } else {
        $fields = array_keys($cols);
        $pdo->prepare("INSERT INTO affiliate_settings (" . implode(', ', $fields) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")")
            ->execute(array_values($cols));
    }
}

// ===================== Referral codes & links =====================

function affiliate_slugify_code(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    return substr($s, 0, 20);
}

/** Returns the user's existing affiliate code, generating one from their name on first use. */
function affiliate_get_or_create_code(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare("SELECT affiliate_code, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row && !empty($row['affiliate_code'])) return $row['affiliate_code'];

    $base = affiliate_slugify_code($row['name'] ?? 'user');
    if ($base === '') $base = 'user';
    $code = $base;
    $i = 0;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM users WHERE affiliate_code = ?");
        $check->execute([$code]);
        if (!$check->fetch()) break;
        $i++;
        $code = $base . $i;
    }
    $pdo->prepare("UPDATE users SET affiliate_code = ? WHERE id = ?")->execute([$code, $userId]);
    return $code;
}

/** The "edit link" feature — lets a user pick their own custom link slug. */
function affiliate_set_custom_code(PDO $pdo, int $userId, string $desired): array
{
    $code = affiliate_slugify_code($desired);
    if (strlen($code) < 3) {
        return ['ok' => false, 'error' => 'Your link must be at least 3 letters or numbers.'];
    }
    $check = $pdo->prepare("SELECT id FROM users WHERE affiliate_code = ? AND id != ?");
    $check->execute([$code, $userId]);
    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'That link is already taken — please choose another.'];
    }
    $pdo->prepare("UPDATE users SET affiliate_code = ? WHERE id = ?")->execute([$code, $userId]);
    return ['ok' => true, 'error' => null];
}

function affiliate_link_url(string $code): string
{
    return rtrim(APP_URL, '/') . '/?ref=' . urlencode($code);
}

// ===================== Click tracking & referral attach =====================

/** Call from index.php whenever ?ref=CODE is present. Logs the click and drops a cookie. */
function affiliate_track_click(PDO $pdo, string $code): void
{
    try {
        $settings = affiliate_settings_get($pdo);
        if (!$settings['program_enabled']) return;

        $stmt = $pdo->prepare("SELECT id FROM users WHERE affiliate_code = ?");
        $stmt->execute([$code]);
        $affiliateId = $stmt->fetchColumn();
        if (!$affiliateId) return;

        $pdo->prepare("INSERT INTO affiliate_clicks (affiliate_user_id, ip_address, landing_url, user_agent) VALUES (?, ?, ?, ?)")
            ->execute([
                (int)$affiliateId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

        setcookie(AFFILIATE_COOKIE_NAME, $code, time() + $settings['cookie_days'] * 86400, '/');
        $_COOKIE[AFFILIATE_COOKIE_NAME] = $code; // so it's available immediately this same request too
    } catch (Throwable $e) {
        // Affiliate tables not migrated yet — never break the homepage over this.
    }
}

/** Call from auth/register.php right after a new user row is inserted. */
function affiliate_attach_referral(PDO $pdo, int $newUserId): void
{
    try {
        $code = trim($_COOKIE[AFFILIATE_COOKIE_NAME] ?? '');
        if ($code === '') return;

        $stmt = $pdo->prepare("SELECT id FROM users WHERE affiliate_code = ?");
        $stmt->execute([$code]);
        $affiliateId = $stmt->fetchColumn();
        if (!$affiliateId || (int)$affiliateId === $newUserId) return; // no self-referrals

        $pdo->prepare("UPDATE users SET referred_by_user_id = ?, referred_by_code = ? WHERE id = ?")
            ->execute([(int)$affiliateId, $code, $newUserId]);
        $pdo->prepare("INSERT IGNORE INTO affiliate_referrals (affiliate_user_id, referred_user_id, ref_code_used) VALUES (?, ?, ?)")
            ->execute([(int)$affiliateId, $newUserId, $code]);
    } catch (Throwable $e) {
        // Never block registration over the affiliate tables not being migrated yet.
    }
}

// ===================== Commission recording =====================

/**
 * Call right after activate_plan_for_user() in admin/plan-users.php's approve branch.
 * $payment is a plan_payments row (needs id, user_id, plan_id, amount). Credits the
 * referring affiliate if this referred user's purchase falls inside the commission
 * window (lifetime, or duration_months from the referral's own signup date) — covers
 * both a first purchase and any later renewal payment the same way.
 */
function affiliate_record_commission_for_payment(PDO $pdo, array $payment): void
{
    try {
        $settings = affiliate_settings_get($pdo);
        if (!$settings['program_enabled']) return;

        $stmt = $pdo->prepare("SELECT * FROM affiliate_referrals WHERE referred_user_id = ?");
        $stmt->execute([(int)$payment['user_id']]);
        $referral = $stmt->fetch();
        if (!$referral) return;

        if ($settings['duration_type'] === 'custom' && $settings['duration_months']) {
            $windowEnd = strtotime($referral['created_at'] . " +{$settings['duration_months']} months");
            if ($windowEnd !== false && time() > $windowEnd) return; // outside the commission window
        }

        $amount = (float)($payment['amount'] ?? 0);
        if ($amount <= 0) return;
        $commissionAmount = round($amount * $settings['commission_percent'] / 100, 2);
        if ($commissionAmount <= 0) return;

        $pdo->prepare("INSERT INTO affiliate_commissions (affiliate_user_id, referred_user_id, plan_payment_id, plan_id, revenue_amount, commission_percent, commission_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $referral['affiliate_user_id'], $payment['user_id'], $payment['id'] ?? null, $payment['plan_id'] ?? null,
                $amount, $settings['commission_percent'], $commissionAmount,
            ]);

        $pdo->prepare("UPDATE affiliate_referrals SET status = 'customer', first_purchase_at = COALESCE(first_purchase_at, NOW()) WHERE id = ?")
            ->execute([$referral['id']]);

        if (function_exists('create_notification')) {
            create_notification($pdo, (int)$referral['affiliate_user_id'], 'affiliate', 'New affiliate commission',
                'You earned $' . number_format($commissionAmount, 2) . ' from a referral sale.', '/user/affiliate-dashboard');
        }
    } catch (Throwable $e) {
        // Never break payment approval over the affiliate tables not being migrated yet.
    }
}

// ===================== Balances & stats (user side) =====================

function affiliate_balance(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM affiliate_commissions WHERE affiliate_user_id = ?");
        $stmt->execute([$userId]);
        $totalEarning = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM affiliate_payouts WHERE user_id = ? AND status = 'paid'");
        $stmt->execute([$userId]);
        $totalPaid = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM affiliate_payouts WHERE user_id = ? AND status IN ('pending','approved')");
        $stmt->execute([$userId]);
        $pendingPayout = (float)$stmt->fetchColumn();

        $toPay = max(0, round($totalEarning - $totalPaid - $pendingPayout, 2));
        return ['total_earning' => $totalEarning, 'total_paid' => $totalPaid, 'pending_payout' => $pendingPayout, 'to_pay' => $toPay];
    } catch (Throwable $e) {
        return ['total_earning' => 0.0, 'total_paid' => 0.0, 'pending_payout' => 0.0, 'to_pay' => 0.0];
    }
}

function affiliate_get_stats(PDO $pdo, int $userId): array
{
    $balance = affiliate_balance($pdo, $userId);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_user_id = ?");
        $stmt->execute([$userId]);
        $totalClicks = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_user_id = ?");
        $stmt->execute([$userId]);
        $totalReferrals = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT referred_user_id) FROM affiliate_referrals WHERE affiliate_user_id = ? AND status = 'customer'");
        $stmt->execute([$userId]);
        $totalCustomers = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_commissions WHERE affiliate_user_id = ?");
        $stmt->execute([$userId]);
        $totalSales = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $totalClicks = $totalReferrals = $totalCustomers = $totalSales = 0;
    }
    return array_merge($balance, [
        'total_clicks' => $totalClicks, 'total_referrals' => $totalReferrals,
        'total_customers' => $totalCustomers, 'total_sales' => $totalSales,
    ]);
}

function affiliate_get_recent_referrals(PDO $pdo, int $userId, int $limit = 20): array
{
    try {
        $stmt = $pdo->prepare("SELECT r.*, u.name, u.email FROM affiliate_referrals r
            JOIN users u ON u.id = r.referred_user_id
            WHERE r.affiliate_user_id = ? ORDER BY r.created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** "Affiliate Sales" table rows: Date, user handle, plan, revenue, earn %, earnings. */
function affiliate_get_sales(PDO $pdo, int $userId, int $limit = 50): array
{
    try {
        $stmt = $pdo->prepare("SELECT c.*, u.name, u.email, p.name AS plan_name FROM affiliate_commissions c
            JOIN users u ON u.id = c.referred_user_id
            LEFT JOIN pricing_plans p ON p.id = c.plan_id
            WHERE c.affiliate_user_id = ? ORDER BY c.created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// ===================== Payout methods & payout requests (user side) =====================

function affiliate_get_payout_methods(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM affiliate_payout_methods WHERE user_id = ? ORDER BY method ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function affiliate_save_payout_method(PDO $pdo, int $userId, string $method, string $details): array
{
    if (!array_key_exists($method, AFFILIATE_METHOD_LABELS)) {
        return ['ok' => false, 'error' => 'Please choose a valid payout method.'];
    }
    $details = trim($details);
    if ($details === '') {
        return ['ok' => false, 'error' => 'Please enter your payment details.'];
    }
    // Crypto payouts are USDT on the TRC20 network only — TRC20 addresses always
    // start with "T" and are 34 characters long. Catching an obviously wrong
    // address here (e.g. a pasted ERC20/BEP20 "0x..." address) prevents a payout
    // being sent on the wrong network, where funds can't be recovered.
    if ($method === 'crypto' && !preg_match('~^T[a-zA-Z0-9]{33}$~', $details)) {
        return ['ok' => false, 'error' => 'That doesn\'t look like a valid USDT TRC20 address — it should start with "T" and be 34 characters long. Double-check you copied the TRC20 address, not one for another network.'];
    }
    $pdo->prepare("INSERT INTO affiliate_payout_methods (user_id, method, details) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE details = VALUES(details), updated_at = NOW()")
        ->execute([$userId, $method, $details]);
    return ['ok' => true, 'error' => null];
}

function affiliate_request_payout(PDO $pdo, int $userId, string $method): array
{
    $settings = affiliate_settings_get($pdo);
    $balance = affiliate_balance($pdo, $userId);

    if (!array_key_exists($method, AFFILIATE_METHOD_LABELS) || empty($settings["{$method}_enabled"])) {
        return ['ok' => false, 'error' => 'Please choose a valid payout method.'];
    }
    if ($balance['to_pay'] <= 0) {
        return ['ok' => false, 'error' => 'You have no available balance to withdraw yet.'];
    }
    if ($balance['to_pay'] < $settings['min_payout_threshold']) {
        return ['ok' => false, 'error' => 'You need at least $' . number_format($settings['min_payout_threshold'], 2) . ' available before you can request a payout.'];
    }
    $methods = affiliate_get_payout_methods($pdo, $userId);
    $chosen = null;
    foreach ($methods as $m) if ($m['method'] === $method) $chosen = $m;
    if (!$chosen) {
        return ['ok' => false, 'error' => 'Please add this payout method below first.'];
    }

    $pdo->prepare("INSERT INTO affiliate_payouts (user_id, amount, method, payment_details, status) VALUES (?, ?, ?, ?, 'pending')")
        ->execute([$userId, $balance['to_pay'], $method, $chosen['details']]);
    return ['ok' => true, 'error' => null];
}

function affiliate_get_user_payouts(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM affiliate_payouts WHERE user_id = ? ORDER BY requested_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// ===================== Admin side =====================

/** Numbers for Admin → Affiliate → Dashboard's top stat cards. */
function affiliate_admin_overview(PDO $pdo): array
{
    $empty = ['total_affiliates' => 0, 'total_sales' => 0, 'total_commission' => 0.0, 'total_revenue' => 0.0,
        'total_customers' => 0, 'total_clicks' => 0, 'pending_payouts' => 0.0];
    try {
        return [
            'total_affiliates' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE affiliate_code IS NOT NULL")->fetchColumn(),
            'total_sales' => (int)$pdo->query("SELECT COUNT(*) FROM affiliate_commissions")->fetchColumn(),
            'total_commission' => (float)$pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM affiliate_commissions")->fetchColumn(),
            'total_revenue' => (float)$pdo->query("SELECT COALESCE(SUM(revenue_amount),0) FROM affiliate_commissions")->fetchColumn(),
            'total_customers' => (int)$pdo->query("SELECT COUNT(DISTINCT referred_user_id) FROM affiliate_referrals WHERE status = 'customer'")->fetchColumn(),
            'total_clicks' => (int)$pdo->query("SELECT COUNT(*) FROM affiliate_clicks")->fetchColumn(),
            'pending_payouts' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM affiliate_payouts WHERE status = 'pending'")->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return $empty;
    }
}

/** Every affiliate with their aggregated stats, for Admin → Affiliate → Dashboard's user list. */
function affiliate_admin_list(PDO $pdo): array
{
    try {
        $sql = "SELECT u.id, u.name, u.email, u.affiliate_code, u.created_at AS joined_at,
                (SELECT COUNT(*) FROM affiliate_clicks c WHERE c.affiliate_user_id = u.id) AS total_clicks,
                (SELECT COUNT(*) FROM affiliate_referrals r WHERE r.affiliate_user_id = u.id) AS total_referrals,
                (SELECT COUNT(*) FROM affiliate_referrals r WHERE r.affiliate_user_id = u.id AND r.status = 'customer') AS total_customers,
                (SELECT COUNT(*) FROM affiliate_commissions cm WHERE cm.affiliate_user_id = u.id) AS total_sales,
                (SELECT COALESCE(SUM(cm.commission_amount),0) FROM affiliate_commissions cm WHERE cm.affiliate_user_id = u.id) AS total_earning
                FROM users u WHERE u.affiliate_code IS NOT NULL
                ORDER BY total_earning DESC";
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function affiliate_admin_get_affiliate(PDO $pdo, int $userId): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND affiliate_code IS NOT NULL");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Payout requests for Admin → Affiliate → Payouts. Pass a status to filter, or null for all. */
function affiliate_admin_get_payout_requests(PDO $pdo, ?string $status = null): array
{
    try {
        $sql = "SELECT p.*, u.name AS user_name, u.email AS user_email FROM affiliate_payouts p JOIN users u ON u.id = p.user_id";
        $params = [];
        if ($status) {
            $sql .= " WHERE p.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY p.requested_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** $decision is one of: approve | reject | paid. */
function affiliate_admin_decide_payout(PDO $pdo, int $payoutId, string $decision, string $note): void
{
    $status = $decision === 'approve' ? 'approved' : ($decision === 'paid' ? 'paid' : 'rejected');

    $stmt = $pdo->prepare("SELECT * FROM affiliate_payouts WHERE id = ?");
    $stmt->execute([$payoutId]);
    $payout = $stmt->fetch();
    if (!$payout) return;

    $paidAtSql = $status === 'paid' ? ', paid_at = NOW()' : '';
    $pdo->prepare("UPDATE affiliate_payouts SET status = ?, admin_note = ?, decided_at = NOW()$paidAtSql WHERE id = ?")
        ->execute([$status, $note ?: null, $payoutId]);

    if (function_exists('create_notification')) {
        $title = $status === 'paid' ? 'Payout sent' : ($status === 'approved' ? 'Payout approved' : 'Payout request declined');
        $message = $status === 'paid'
            ? ('$' . number_format((float)$payout['amount'], 2) . ' has been sent to your ' . (AFFILIATE_METHOD_LABELS[$payout['method']] ?? $payout['method']) . '.')
            : ($note ?: ($status === 'approved' ? 'Your payout was approved and is being processed.' : 'Please contact support for details.'));
        create_notification($pdo, (int)$payout['user_id'], 'affiliate', $title, $message, '/user/affiliate-payouts');
    }
}
