<?php
/**
 * Plan Pricing — plans/features/limits, coupons, custom payment methods, contact-sales
 * submissions, and header notifications. Payment GATEWAY credentials (Stripe/PayPal/
 * NOWPayments/Binance) live in platform_settings (see includes/platform_functions.php),
 * same pattern as the rest of the admin settings.
 *
 * IMPORTANT: every read function here is wrapped in try/catch and returns a safe empty
 * default (rather than letting a PDOException bubble up and 500 the whole page) — this
 * matters because several of these are called unconditionally from user-header.php (every
 * user page) and settings.php/upgrade.php. If you see plans/coupons/notifications not
 * showing up even though the page loads fine, it almost always means migrate.php hasn't
 * been (re)run yet on this database — visit /migrate.php once to create the tables these
 * functions expect (pricing_plans, plan_feature_rows, custom_payment_methods, coupons,
 * coupon_plans, coupon_redemptions, plan_payments, contact_sales_submissions,
 * notifications) and the new users/team_members columns.
 */

// ===================== Plans =====================

function get_all_plans(PDO $pdo, bool $activeOnly = false): array
{
    try {
        $sql = "SELECT * FROM pricing_plans" . ($activeOnly ? " WHERE status = 'active'" : "") . " ORDER BY sort_order ASC, price_monthly ASC";
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_plan(PDO $pdo, int $planId): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE id = ?");
        $stmt->execute([$planId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_free_plan(PDO $pdo): ?array
{
    try {
        $stmt = $pdo->query("SELECT * FROM pricing_plans WHERE is_free = 1 LIMIT 1");
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_plan_feature_rows(PDO $pdo, int $planId): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM plan_feature_rows WHERE plan_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function plan_total_users(PDO $pdo, int $planId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE plan_id = ?");
        $stmt->execute([$planId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Saves (creates or updates) a plan's core fields + feature limits. Feature rows are saved separately via save_plan_feature_rows(). */
function save_plan(PDO $pdo, ?int $planId, array $f): int
{
    if (!empty($f['is_free'])) {
        // Only one free plan is allowed — unset any other.
        if ($planId) {
            $pdo->prepare("UPDATE pricing_plans SET is_free = 0 WHERE is_free = 1 AND id != ?")->execute([$planId]);
        } else {
            $pdo->exec("UPDATE pricing_plans SET is_free = 0 WHERE is_free = 1");
        }
    }

    $cols = [
        'name' => $f['name'], 'is_free' => !empty($f['is_free']) ? 1 : 0,
        'price_monthly' => $f['price_monthly'] ?? 0, 'discount_monthly' => $f['discount_monthly'] ?? 0, 'discount_yearly' => $f['discount_yearly'] ?? 0,
        'short_description' => $f['short_description'] ?? '', 'tag' => $f['tag'] ?? null, 'tag_color' => $f['tag_color'] ?? null,
        'pay_button_text' => ($f['pay_button_text'] ?? '') ?: 'Choose Plan',
        'pay_button_bg' => ($f['pay_button_bg'] ?? '') ?: '#e60023', 'pay_button_text_color' => ($f['pay_button_text_color'] ?? '') ?: '#ffffff', 'pay_button_border_color' => ($f['pay_button_border_color'] ?? '') ?: '#e60023',
        'buy_button_position' => in_array($f['buy_button_position'] ?? '', ['top', 'bottom', 'both'], true) ? $f['buy_button_position'] : 'bottom',
        'image_ai_credits_monthly' => (int)($f['image_ai_credits_monthly'] ?? 0), 'text_ai_credits_monthly' => (int)($f['text_ai_credits_monthly'] ?? 0),
        'pin_scheduling_monthly_limit' => $f['pin_scheduling_monthly_unlimited'] ?? false ? null : (int)($f['pin_scheduling_monthly_limit'] ?? 0),
        'pin_scheduling_daily_limit' => $f['pin_scheduling_daily_unlimited'] ?? false ? null : (int)($f['pin_scheduling_daily_limit'] ?? 0),
        'pinterest_accounts_limit' => $f['pinterest_accounts_unlimited'] ?? false ? null : (int)($f['pinterest_accounts_limit'] ?? 0),
        'websites_limit' => $f['websites_unlimited'] ?? false ? null : (int)($f['websites_limit'] ?? 0),
        'upload_pins_limit' => $f['upload_pins_unlimited'] ?? false ? null : (int)($f['upload_pins_limit'] ?? 0),
        'bulk_scheduling_enabled' => !empty($f['bulk_scheduling_enabled']) ? 1 : 0,
        'invite_team_members_limit' => (int)($f['invite_team_members_limit'] ?? 0),
        'auto_website_daily_pin_enabled' => !empty($f['auto_website_daily_pin_enabled']) ? 1 : 0,
        'auto_article_enabled' => !empty($f['auto_article_enabled']) ? 1 : 0,
        'single_article_writer_enabled' => !empty($f['single_article_writer_enabled']) ? 1 : 0,
        'cloud_storage_mb' => ((int)($f['cloud_storage_gb'] ?? 0) * 1024) + (int)($f['cloud_storage_mb_extra'] ?? 0),
        'sort_order' => (int)($f['sort_order'] ?? 0),
        'status' => ($f['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
    ];

    if ($planId) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols)));
        $pdo->prepare("UPDATE pricing_plans SET $set WHERE id = ?")->execute([...array_values($cols), $planId]);
        return $planId;
    }
    $fields = array_keys($cols);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $pdo->prepare("INSERT INTO pricing_plans (" . implode(', ', $fields) . ") VALUES ($placeholders)")->execute(array_values($cols));
    return (int)$pdo->lastInsertId();
}

function save_plan_feature_rows(PDO $pdo, int $planId, array $rows): void
{
    $pdo->prepare("DELETE FROM plan_feature_rows WHERE plan_id = ?")->execute([$planId]);
    $stmt = $pdo->prepare("INSERT INTO plan_feature_rows (plan_id, text, tooltip, checkmark_type, text_size, text_color, font, checkmark_size, checkmark_color, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $order = 0;
    foreach ($rows as $row) {
        $text = trim($row['text'] ?? '');
        if ($text === '') continue;
        $stmt->execute([
            $planId, $text, trim($row['tooltip'] ?? '') ?: null,
            ($row['checkmark_type'] ?? '') ?: 'check', ($row['text_size'] ?? '') ?: '14px', ($row['text_color'] ?? '') ?: '#1a1a1a',
            trim($row['font'] ?? '') ?: null, ($row['checkmark_size'] ?? '') ?: '16px', ($row['checkmark_color'] ?? '') ?: '#16a34a', $order++,
        ]);
    }
}

function delete_plan(PDO $pdo, int $planId): void
{
    $pdo->prepare("UPDATE users SET plan_id = NULL WHERE plan_id = ?")->execute([$planId]);
    $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?")->execute([$planId]);
}

const PLAN_TAG_OPTIONS = ['popular' => 'Popular', 'recommended' => 'Recommended', 'budget' => 'Budget Friendly', 'best_value' => 'Best Value', 'new' => 'New'];
const PLAN_TAG_COLORS = ['red' => '#e60023', 'green' => '#16a34a', 'blue' => '#2563eb', 'orange' => '#ea580c', 'purple' => '#7c3aed'];
const PLAN_CHECKMARK_TYPES = ['check' => '✓', 'check-circle' => '✅', 'star' => '★', 'bolt' => '⚡', 'dot' => '●'];

/** True once migrate.php has created the Plan Pricing tables/columns on this database. Every
 *  admin Plan Pricing page checks this first and shows a clear notice instead of a 500 if not. */
function plan_pricing_tables_ready(PDO $pdo): bool
{
    try {
        $pdo->query("SELECT id FROM pricing_plans LIMIT 1");
        $pdo->query("SELECT id FROM plan_payments LIMIT 1");
        $pdo->query("SELECT id FROM coupons LIMIT 1");
        $pdo->query("SELECT id FROM custom_payment_methods LIMIT 1");
        $pdo->query("SELECT plan_id FROM users LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ===================== A user's effective plan (team-aware) =====================

/** A team member's plan/limits are their OWNER's — same sharing model as credits and BYOK keys. Returns null on any DB error (e.g. tables/columns not migrated yet) instead of crashing the page. */
function get_user_plan(PDO $pdo, int $userId): ?array
{
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("SELECT u.plan_id, u.plan_end_date, u.plan_billing_cycle, u.plan_auto_renew FROM users u WHERE u.id = ?");
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();
        if (!$row || !$row['plan_id']) return null;
        return get_plan($pdo, (int)$row['plan_id']);
    } catch (Throwable $e) {
        return null;
    }
}

/** For a plan-limit check, applies the team member's own share-percentage (Settings → Team) on top of the owner's plan limit. Null limit (unlimited) passes through unchanged. */
function plan_limit_for_member(PDO $pdo, int $userId, ?int $ownerLimit): ?int
{
    if ($ownerLimit === null) return null;
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        if ($ownerId === $userId) return $ownerLimit;
        $stmt = $pdo->prepare("SELECT limit_share_percent FROM team_members WHERE owner_id = ? AND member_user_id = ? AND status = 'active'");
        $stmt->execute([$ownerId, $userId]);
        $pct = $stmt->fetchColumn();
        $pct = $pct !== false ? max(0, min(100, (int)$pct)) : 100;
        return (int)floor($ownerLimit * $pct / 100);
    } catch (Throwable $e) {
        return $ownerLimit;
    }
}

/** Assigns the (single allowed) free plan to a brand-new user, if one exists. Call right after registration. Silently no-ops if the plan tables aren't migrated yet — registration must never fail because of this. */
function assign_free_plan_to_new_user(PDO $pdo, int $userId): void
{
    try {
        $free = get_free_plan($pdo);
        if (!$free) return;
        $pdo->prepare("UPDATE users SET plan_id = ?, plan_started_at = NOW(), plan_end_date = NULL, image_credits_balance = ?, text_credits_balance = ? WHERE id = ?")
            ->execute([$free['id'], $free['image_ai_credits_monthly'], $free['text_ai_credits_monthly'], $userId]);
    } catch (Throwable $e) {
        // Registration should still succeed even if plan tables aren't migrated yet.
    }
}

/** Activates a plan for a user after a payment is approved/completed — sets the end date and RESETS
 *  their Image AI / Text AI credit balances to the plan's monthly allowance (balances reset each
 *  cycle, they don't accumulate). */
function activate_plan_for_user(PDO $pdo, int $userId, int $planId, string $billingCycle): void
{
    $plan = get_plan($pdo, $planId);
    if (!$plan) return;
    try {
        $interval = $billingCycle === 'yearly' ? '1 YEAR' : '1 MONTH';
        $pdo->prepare("UPDATE users SET plan_id = ?, plan_billing_cycle = ?, plan_started_at = NOW(),
                plan_end_date = DATE_ADD(NOW(), INTERVAL $interval), plan_reminder_sent_at = NULL,
                image_credits_balance = ?, text_credits_balance = ? WHERE id = ?")
            ->execute([$planId, $billingCycle, $plan['image_ai_credits_monthly'], $plan['text_ai_credits_monthly'], $userId]);
    } catch (Throwable $e) {
        // Table/columns not migrated yet — nothing more we can safely do here.
    }
}

// ===================== Credit pricing (Plan Pricing → Setting) =====================

function credit_pricing_get(PDO $pdo): array
{
    $defaults = [
        'image_quality_low' => 0.2, 'image_quality_medium' => 0.7, 'image_quality_high' => 1.0,
        'text_credit_per_call' => 1.0, 'renewal_reminder_days_before' => 7, 'contact_sales_enabled' => true,
    ];
    try {
        return [
            'image_quality_low' => (float)platform_setting($pdo, 'pricing_image_quality_low_multiplier', '0.2'),
            'image_quality_medium' => (float)platform_setting($pdo, 'pricing_image_quality_medium_multiplier', '0.7'),
            'image_quality_high' => (float)platform_setting($pdo, 'pricing_image_quality_high_multiplier', '1'),
            'text_credit_per_call' => (float)platform_setting($pdo, 'pricing_text_credit_per_call', '1'),
            'renewal_reminder_days_before' => (int)platform_setting($pdo, 'pricing_renewal_reminder_days_before', '7'),
            'contact_sales_enabled' => platform_setting($pdo, 'pricing_contact_sales_enabled', '1') === '1',
        ];
    } catch (Throwable $e) {
        return $defaults;
    }
}

/**
 * THE single source of truth for what one AI-generated image costs, in credits — driven
 * entirely by the user's own quality selection (Plan Pricing → Setting's Low/Medium/High
 * multipliers), never by which provider/model the admin has configured to actually generate
 * it. Cloudflare (free to the admin) costs the user exactly the same as any other provider
 * at the same quality — there is no separate "flat Cloudflare rate" any more.
 * $quality accepts the request-level values used across the app: 'budget' (low),
 * 'high' (medium), 'ultra' (high) — any other/unrecognized value falls back to 'high' (the
 * highest, safest default) rather than silently under-charging.
 */
function image_quality_cost(PDO $pdo, string $quality): float
{
    $pricing = credit_pricing_get($pdo);
    if ($quality === 'budget') return $pricing['image_quality_low'];
    if ($quality === 'high') return $pricing['image_quality_medium'];
    return $pricing['image_quality_high']; // 'ultra' and any other/legacy value (incl. old 'cloudflare')
}

// ===================== Coupons =====================

function get_all_coupons(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id = c.id) AS total_used FROM coupons c ORDER BY c.created_at DESC");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_coupon_by_code(PDO $pdo, string $code): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ?");
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function coupon_applies_to_plan(PDO $pdo, array $coupon, int $planId): bool
{
    if ($coupon['apply_to_all_plans']) return true;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM coupon_plans WHERE coupon_id = ? AND plan_id = ?");
        $stmt->execute([$coupon['id'], $planId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function coupon_is_valid(array $coupon, ?int $planId = null, ?PDO $pdo = null): bool
{
    if (($coupon['status'] ?? '') !== 'active') return false;
    if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < strtotime(date('Y-m-d'))) return false;
    if ($planId !== null && $pdo && !coupon_applies_to_plan($pdo, $coupon, $planId)) return false;
    return true;
}

function coupon_public_link(array $coupon, ?int $planId = null): string
{
    $url = rtrim(APP_URL, '/') . '/user/upgrade?coupon=' . urlencode($coupon['code']);
    if ($planId) $url .= '&plan=' . $planId;
    return $url;
}

function save_coupon(PDO $pdo, ?int $couponId, array $f): int
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $f['code'] ?? ''));
    $cols = [
        'name' => trim($f['name'] ?? ''), 'code' => $code, 'end_date' => $f['end_date'] ?: null,
        'discount_percent' => (float)($f['discount_percent'] ?? 0),
        'apply_to_all_plans' => !empty($f['apply_to_all_plans']) ? 1 : 0,
        'status' => ($f['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
    ];
    if ($couponId) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols)));
        $pdo->prepare("UPDATE coupons SET $set WHERE id = ?")->execute([...array_values($cols), $couponId]);
    } else {
        $fields = array_keys($cols);
        $pdo->prepare("INSERT INTO coupons (" . implode(', ', $fields) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")")->execute(array_values($cols));
        $couponId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("DELETE FROM coupon_plans WHERE coupon_id = ?")->execute([$couponId]);
    if (empty($cols['apply_to_all_plans']) && !empty($f['plan_ids'])) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO coupon_plans (coupon_id, plan_id) VALUES (?, ?)");
        foreach ((array)$f['plan_ids'] as $pid) $stmt->execute([$couponId, (int)$pid]);
    }
    return $couponId;
}

// ===================== Custom payment methods =====================

function get_custom_payment_methods(PDO $pdo, bool $enabledOnly = false): array
{
    try {
        $sql = "SELECT * FROM custom_payment_methods" . ($enabledOnly ? " WHERE enabled = 1" : "") . " ORDER BY sort_order ASC, id ASC";
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function save_custom_payment_method(PDO $pdo, ?int $id, string $name, string $detailsHtml, bool $enabled): int
{
    $detailsHtml = sanitize_guide_html($detailsHtml);
    if ($id) {
        $pdo->prepare("UPDATE custom_payment_methods SET name = ?, details_html = ?, enabled = ? WHERE id = ?")
            ->execute([$name, $detailsHtml, $enabled ? 1 : 0, $id]);
        return $id;
    }
    $pdo->prepare("INSERT INTO custom_payment_methods (name, details_html, enabled) VALUES (?, ?, ?)")->execute([$name, $detailsHtml, $enabled ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

// ===================== Contact Sales =====================

function save_contact_sales_submission(PDO $pdo, ?int $userId, string $name, string $whatsapp, string $email, string $budget, string $message): void
{
    $pdo->prepare("INSERT INTO contact_sales_submissions (user_id, name, whatsapp, email, budget, message) VALUES (?,?,?,?,?,?)")
        ->execute([$userId, $name, $whatsapp, $email, $budget, $message]);
}

function get_contact_sales_submissions(PDO $pdo): array
{
    try {
        return $pdo->query("SELECT * FROM contact_sales_submissions ORDER BY created_at DESC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// ===================== Notifications =====================
// These are called from user-header.php on EVERY user page, so they are especially
// important to keep from ever throwing — a missing `notifications` table must never
// take down the whole app.

function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message = '', string $link = ''): void
{
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)")
            ->execute([$userId, $type, $title, $message ?: null, $link ?: null]);
    } catch (Throwable $e) {
        // Best-effort — never break the action that triggered this notification.
    }
}

function get_user_notifications(PDO $pdo, int $userId, int $limit = 15): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function count_unread_notifications(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function mark_notifications_read(PDO $pdo, int $userId): void
{
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
    } catch (Throwable $e) {
        // no-op
    }
}

// ===================== Usage summary (Settings/Upgrade page "Your Usage" panel) =====================

/**
 * Everything the "Your Usage" panel needs in one call: separate Image/Text AI credit
 * usage, pin scheduling (monthly + daily), uploaded pins, team invites, storage, and the
 * plan's boolean feature flags — each as ['used'=>, 'total'=>, 'pct'=>] where total=null
 * means unlimited. Team-aware throughout (a member's usage is measured against their
 * OWNER's plan/limits, same sharing model as credits).
 */
function get_user_plan_usage_summary(PDO $pdo, int $userId): array
{
    $plan = get_user_plan($pdo, $userId);
    $ownerId = team_effective_owner_id($pdo, $userId);

    $imageTotal = $plan['image_ai_credits_monthly'] ?? 0;
    $imageRemaining = get_user_image_credits($pdo, $userId);
    $textTotal = $plan['text_ai_credits_monthly'] ?? 0;
    $textRemaining = get_user_text_credits($pdo, $userId);

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        $stmt->execute([$ownerId]);
        $pinMonthlyUsed = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND created_at >= CURDATE()");
        $stmt->execute([$ownerId]);
        $pinDailyUsed = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND source = 'manual'");
        $stmt->execute([$ownerId]);
        $uploadPinsUsed = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pinterest_accounts WHERE user_id = ?");
        $stmt->execute([$ownerId]);
        $accountsUsed = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE user_id = ?");
        $stmt->execute([$ownerId]);
        $websitesUsed = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $pinMonthlyUsed = $pinDailyUsed = $uploadPinsUsed = $accountsUsed = $websitesUsed = 0;
    }

    $teamUsed = count(array_filter(get_owned_team_members($pdo, $ownerId), fn($m) => $m['status'] === 'active'));

    try {
        $storageUsed = get_user_storage_used($pdo, $ownerId);
        $storageTotal = get_user_storage_quota_bytes($pdo, $userId);
    } catch (Throwable $e) {
        $storageUsed = 0;
        $storageTotal = STORAGE_QUOTA_BYTES ?? 1073741824;
    }

    $mk = fn($used, $total) => ['used' => $used, 'total' => $total, 'pct' => $total ? min(100, round($used / max(1, $total) * 100)) : 0];

    return [
        'plan' => $plan,
        'image_credits' => $mk(max(0, $imageTotal - $imageRemaining), $plan ? $imageTotal : null),
        'text_credits' => $mk(max(0, $textTotal - $textRemaining), $plan ? $textTotal : null),
        'pin_monthly' => $mk($pinMonthlyUsed, $plan['pin_scheduling_monthly_limit'] ?? null),
        'pin_daily' => $mk($pinDailyUsed, $plan['pin_scheduling_daily_limit'] ?? null),
        'upload_pins' => $mk($uploadPinsUsed, $plan['upload_pins_limit'] ?? null),
        'pinterest_accounts' => $mk($accountsUsed, $plan['pinterest_accounts_limit'] ?? null),
        'websites' => $mk($websitesUsed, $plan['websites_limit'] ?? null),
        'team_invites' => $mk($teamUsed, plan_limit_value($plan, 'invite_team_members_limit')),
        'storage' => $mk($storageUsed, $storageTotal),
        'features' => [
            'bulk_scheduling' => ['label' => 'Bulk scheduling', 'available' => (bool)($plan['bulk_scheduling_enabled'] ?? false)],
            'auto_website_daily_pin' => ['label' => 'Auto website-to-daily-pin', 'available' => (bool)($plan['auto_website_daily_pin_enabled'] ?? false)],
            'auto_article' => ['label' => 'Auto article', 'available' => (bool)($plan['auto_article_enabled'] ?? false)],
            'single_article_writer' => ['label' => 'Single article writer', 'available' => (bool)($plan['single_article_writer_enabled'] ?? false)],
        ],
    ];
}

/**
 * A plan limit: NULL in the plan = Unlimited (the "Unlimited" checkbox), a number = that limit,
 * and no plan at all = 0. (Using `?? 0` turned every "Unlimited" plan into a limit of 0.)
 */
function plan_limit_value(?array $plan, string $key): ?int
{
    if (!$plan) return 0;
    if (!array_key_exists($key, $plan)) return 0;
    return $plan[$key] === null ? null : (int)$plan[$key];
}

/** True if a plan flag/limit is exceeded — used to decide whether to show a "limit reached, upgrade" warning. */
function usage_is_at_limit(array $item): bool
{
    return $item['total'] !== null && $item['used'] >= $item['total'];
}

/**
 * Checks the plan's pin-scheduling monthly AND daily limits before actually creating
 * $count new pin(s). Returns ['allowed'=>bool,'message'=>?string] — call this right before
 * any INSERT INTO scheduled_pins and block with the message if not allowed.
 */
function check_pin_scheduling_limit(PDO $pdo, int $userId, int $count = 1): array
{
    $plan = get_user_plan($pdo, $userId);
    $ownerId = team_effective_owner_id($pdo, $userId);
    $monthlyLimit = plan_limit_value($plan, 'pin_scheduling_monthly_limit');
    $dailyLimit = plan_limit_value($plan, 'pin_scheduling_daily_limit');

    try {
        if ($monthlyLimit !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$ownerId]);
            if ((int)$stmt->fetchColumn() + $count > $monthlyLimit) {
                return ['allowed' => false, 'message' => "Your plan allows up to $monthlyLimit pin(s) per month. Upgrade your plan to schedule more."];
            }
        }
        if ($dailyLimit !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND created_at >= CURDATE()");
            $stmt->execute([$ownerId]);
            if ((int)$stmt->fetchColumn() + $count > $dailyLimit) {
                return ['allowed' => false, 'message' => "Your plan allows up to $dailyLimit pin(s) per day. Upgrade your plan to schedule more today, or try again tomorrow."];
            }
        }
    } catch (Throwable $e) {
        return ['allowed' => true, 'message' => null]; // fail open — never block scheduling over a migration issue
    }
    return ['allowed' => true, 'message' => null];
}

/**
 * Checks whether the given user's plan allows a boolean feature (bulk_scheduling,
 * auto_website_daily_pin, auto_article, single_article_writer). Returns
 * ['allowed'=>bool,'message'=>?string] — feature pages call this at the top and show the
 * message (a "this feature is locked, upgrade your plan" notice) instead of the page's
 * normal content when not allowed.
 */
function require_plan_feature(PDO $pdo, int $userId, string $featureKey): array
{
    $labels = [
        'bulk_scheduling' => 'Bulk Pin Scheduler',
        'auto_website_daily_pin' => 'Auto Website to Daily Pin',
        'auto_article' => 'Auto Article',
        'single_article_writer' => 'Single Article Writer',
    ];
    $column = $featureKey . '_enabled';
    $plan = get_user_plan($pdo, $userId);
    $allowed = $plan ? (bool)($plan[$column] ?? false) : false;
    if ($allowed) return ['allowed' => true, 'message' => null];
    $label = $labels[$featureKey] ?? 'This feature';
    return ['allowed' => false, 'message' => "$label isn't included in your current plan. Upgrade your plan to unlock it."];
}

/**
 * Renders a "this feature is locked" page in place of a feature page's normal content,
 * for the 4 plan-gated features (Bulk Pin Scheduler, Auto Website to Daily Pin, Auto
 * Article, Single Article Writer). Call this and `exit;` right after require_login() if
 * require_plan_feature() says the user's plan doesn't include the feature.
 */
function render_user_feature_locked(string $pageTitle, string $activePage, array $user, string $message): void
{
    global $pdo;
    include __DIR__ . '/../user/includes/user-header.php';
    ?>
    <div class="page-header"><h1><?= e($pageTitle) ?></h1></div>
    <div class="card" style="text-align:center; padding:50px 20px;">
        <div style="font-size:40px; margin-bottom:10px;">🔒</div>
        <h2><?= e($pageTitle) ?> is locked</h2>
        <p class="muted" style="max-width:420px; margin:0 auto 20px;"><?= e($message) ?></p>
        <a href="upgrade" class="btn-primary">Upgrade Your Plan</a>
    </div>
    <?php
    include __DIR__ . '/../user/includes/user-footer.php';
}

function payment_gateway_settings_get(PDO $pdo): array
{
    $keys = [
        'stripe_enabled', 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_enabled', 'paypal_client_id', 'paypal_secret', 'paypal_mode',
        'nowpayments_enabled', 'nowpayments_api_key', 'nowpayments_ipn_secret',
        'binance_enabled', 'binance_api_key', 'binance_secret_key',
    ];
    try {
        return [
            'stripe_enabled' => platform_setting($pdo, 'pg_stripe_enabled', '0') === '1',
            'stripe_publishable_key' => platform_setting($pdo, 'pg_stripe_publishable_key', ''),
            'stripe_secret_key' => platform_setting($pdo, 'pg_stripe_secret_key', ''),
            'stripe_webhook_secret' => platform_setting($pdo, 'pg_stripe_webhook_secret', ''),
            'paypal_enabled' => platform_setting($pdo, 'pg_paypal_enabled', '0') === '1',
            'paypal_client_id' => platform_setting($pdo, 'pg_paypal_client_id', ''),
            'paypal_secret' => platform_setting($pdo, 'pg_paypal_secret', ''),
            'paypal_mode' => platform_setting($pdo, 'pg_paypal_mode', 'live'),
            'nowpayments_enabled' => platform_setting($pdo, 'pg_nowpayments_enabled', '0') === '1',
            'nowpayments_api_key' => platform_setting($pdo, 'pg_nowpayments_api_key', ''),
            'nowpayments_ipn_secret' => platform_setting($pdo, 'pg_nowpayments_ipn_secret', ''),
            'binance_enabled' => platform_setting($pdo, 'pg_binance_enabled', '0') === '1',
            'binance_api_key' => platform_setting($pdo, 'pg_binance_api_key', ''),
            'binance_secret_key' => platform_setting($pdo, 'pg_binance_secret_key', ''),
        ];
    } catch (Throwable $e) {
        return array_fill_keys($keys, '');
    }
}

function payment_gateway_guide(string $which): string
{
    $base = rtrim(defined('APP_URL') ? APP_URL : '', '/');
    if ($which === 'stripe') {
        return '<p>Go to <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">dashboard.stripe.com/apikeys</a> and copy your <strong>Publishable key</strong> and <strong>Secret key</strong> into the fields below.</p>'
            . '<p>Then add a webhook at <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">dashboard.stripe.com/webhooks</a>: endpoint <code>' . $base . '/webhooks/stripe.php</code>, events <code>checkout.session.completed</code> and <code>checkout.session.async_payment_succeeded</code>, and paste its <strong>Signing secret</strong> below. Buyers are sent to Stripe Checkout and the plan activates automatically when they pay.</p>';
    }
    if ($which === 'paypal') {
        return '<p>Create an app at <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener">developer.paypal.com</a> and copy its <strong>Client ID</strong> and <strong>Secret</strong> below (Live app for real payments; Sandbox mode + a Sandbox app to test). Buyers approve the payment on PayPal and it is captured and activated when they come back — no webhook needed.</p>';
    }
    if ($which === 'nowpayments') {
        return '<p>NOWPayments accepts crypto payments. Get your <strong>API key</strong> from <a href="https://account.nowpayments.io/store-settings" target="_blank" rel="noopener">account.nowpayments.io → Settings → Payments</a>, generate an <strong>IPN secret</strong> there and paste both below. The IPN callback URL is sent with every invoice automatically: <code>' . $base . '/webhooks/nowpayments.php</code>. The plan activates when the payment is confirmed on the blockchain.</p>';
    }
    if ($which === 'binance') {
        return '<p>In <a href="https://merchant.binance.com" target="_blank" rel="noopener">Binance Merchant</a> → Developers → API keys, create a Binance Pay API key (payment permissions only — never withdrawals) and paste the <strong>API key</strong> and <strong>Secret key</strong> below. Payments are in USDT; the webhook URL <code>' . $base . '/webhooks/binance.php</code> is sent with every order, and each order is re-checked with Binance before the plan activates.</p>';
    }
    return '';
}
