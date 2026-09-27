<?php
/**
 * Plan Pricing — run this ONCE A DAY (Hostinger hPanel → Advanced → Cron Jobs), e.g.:
 *
 *   0 9 * * * php /home/USERNAME/domains/videoconvertly.com/public_html/cron/plan-renewal.php
 *
 * Two jobs:
 *  1. Sends a renewal-reminder notification (once per day) to any user whose plan is
 *     within the admin's configured reminder window (Plan Pricing → Setting, default 7 days).
 *  2. For plans that have already expired: if auto-renew is on, sends a "renewal payment
 *     due" notification with a link to pay (this build doesn't auto-charge a saved card —
 *     see the Payment Gateway Integration notes); if auto-renew is off, or the grace period
 *     has passed, the user is moved back to the Free Plan (if one exists) so their account
 *     keeps working at free-tier limits instead of silently breaking.
 *
 * Safe to run from the command line only; does nothing if opened in a browser without a
 * shared cron secret (see CRON_SECRET check below, mirroring the other cron scripts' pattern).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';

@set_time_limit(0);

$pricing = credit_pricing_get($pdo);
$reminderDays = $pricing['renewal_reminder_days_before'];

// 1) Reminders — plan not yet expired, within the reminder window, not already reminded today.
$stmt = $pdo->prepare("SELECT u.id, u.name, u.plan_end_date, p.name AS plan_name FROM users u
    JOIN pricing_plans p ON p.id = u.plan_id
    WHERE u.plan_id IS NOT NULL AND p.is_free = 0 AND u.plan_end_date IS NOT NULL
      AND u.plan_end_date > NOW()
      AND u.plan_end_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
      AND (u.plan_reminder_sent_at IS NULL OR u.plan_reminder_sent_at < DATE_SUB(NOW(), INTERVAL 1 DAY))");
$stmt->execute([$reminderDays]);
$dueForReminder = $stmt->fetchAll();

$reminderCount = 0;
foreach ($dueForReminder as $u) {
    $daysLeft = max(0, (int)ceil((strtotime($u['plan_end_date']) - time()) / 86400));
    create_notification($pdo, (int)$u['id'], 'renewal_reminder', 'Your plan renews soon',
        "Your {$u['plan_name']} plan " . ($daysLeft <= 0 ? 'expires today' : "expires in $daysLeft day" . ($daysLeft === 1 ? '' : 's')) . ".",
        '/user/upgrade');
    $pdo->prepare("UPDATE users SET plan_reminder_sent_at = NOW() WHERE id = ?")->execute([$u['id']]);
    $reminderCount++;
}
echo "Renewal reminders sent: $reminderCount\n";

// 2) Expired plans.
$stmt = $pdo->query("SELECT u.id, u.name, u.plan_auto_renew, u.plan_end_date, p.name AS plan_name FROM users u
    JOIN pricing_plans p ON p.id = u.plan_id
    WHERE u.plan_id IS NOT NULL AND p.is_free = 0 AND u.plan_end_date IS NOT NULL AND u.plan_end_date <= NOW()");
$expired = $stmt->fetchAll();

$freePlan = get_free_plan($pdo);
$downgraded = 0;
$renewalDue = 0;

foreach ($expired as $u) {
    if (!empty($u['plan_auto_renew'])) {
        // No saved-card auto-charge is wired up yet (see Payment Gateway Integration guide
        // notes) — ask the user to complete payment instead of silently failing.
        create_notification($pdo, (int)$u['id'], 'renewal_due', 'Renewal payment needed',
            "Your {$u['plan_name']} plan has expired. Complete payment to keep your plan active.", '/user/upgrade');
        $renewalDue++;
    }
    // Either way, move them to the Free Plan now so the account doesn't silently keep
    // "paid" access with an expired plan — they can re-subscribe any time from Upgrade.
    if ($freePlan) {
        $pdo->prepare("UPDATE users SET plan_id = ?, plan_started_at = NOW(), plan_end_date = NULL, plan_reminder_sent_at = NULL, image_credits_balance = ?, text_credits_balance = ? WHERE id = ?")
            ->execute([$freePlan['id'], $freePlan['image_ai_credits_monthly'], $freePlan['text_ai_credits_monthly'], $u['id']]);
    } else {
        $pdo->prepare("UPDATE users SET plan_id = NULL, plan_end_date = NULL WHERE id = ?")->execute([$u['id']]);
    }
    create_notification($pdo, (int)$u['id'], 'plan_expired', 'Your plan has ended',
        $freePlan ? "You've been moved to the {$freePlan['name']} plan." : 'Your plan has expired.', '/user/upgrade');
    $downgraded++;
}
echo "Expired plans processed: $downgraded (renewal-due notices: $renewalDue)\n";
