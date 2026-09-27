<?php
/**
 * Run this ONCE to correct the 5 default plans (Free, Starter, Pro, Growth, Agency)
 * on a site that already ran migrate.php before this pricing update — migrate.php only
 * ever seeds pricing_plans the very first time (when the table is empty), so an already-
 * seeded site keeps its old numbers even after the code is updated. This script instead
 * UPDATEs each plan by name (or INSERTs it if somehow missing), and replaces its
 * plan_feature_rows bullets — nothing else in your database is touched.
 *
 * If you already customized one of these 5 plans' pricing/limits/features by hand from
 * Admin → Plan Pricing, running this will overwrite those edits back to the values below.
 * Any OTHER plan you created yourself (a 6th plan, a renamed duplicate, etc.) is left alone.
 *
 * Visit https://yourdomain.com/update-pricing-plans.php in your browser once, then
 * delete this file — running it again is harmless (it just re-applies the same values)
 * but there's no reason to leave it sitting on the server.
 */

require_once __DIR__ . '/includes/db.php';

// name, is_free, price_monthly, disc_monthly, disc_yearly, description, tag, tag_color, btn_text, btn_bg, btn_text_color, btn_border, btn_pos,
// image_credits, text_credits, pin_monthly, pin_daily, pinterest_accts, websites, upload_pins, bulk, team_invites, auto_web_pin, auto_article, single_article, storage_mb, sort, status
$plans = [
    ['Free', 1, 0, 0, 0, 'Perfect for trying things out', null, 'red', 'Start Free', '#6b7280', '#ffffff', '#6b7280', 'bottom',
        5, 10000, 100, 15, null, null, null, 0, 2, 0, 0, 1, 100, 1, 'active'],
    ['Starter', 0, 20.00, 5, 15, 'Perfect plan for starting out', null, 'blue', 'Choose Plan', '#2563eb', '#ffffff', '#2563eb', 'bottom',
        300, 1000000, 2100, 70, null, null, null, 1, 2, 1, 1, 1, 1024, 2, 'active'],
    ['Pro', 0, 39.00, 7, 19, 'Great for one growing Pinterest account', 'popular', 'red', 'Choose Plan', '#e60023', '#ffffff', '#e60023', 'bottom',
        600, 2000000, 4200, 140, null, null, null, 1, 4, 1, 1, 1, 2048, 3, 'active'],
    ['Growth', 0, 75.00, 10, 20, 'Works well for multiple sites', 'recommended', 'blue', 'Choose Plan', '#2563eb', '#ffffff', '#2563eb', 'bottom',
        1200, 4000000, 8500, 300, null, null, null, 1, 8, 1, 1, 1, 5120, 4, 'active'],
    ['Agency', 0, 150.00, 15, 30, 'For large businesses and agencies', 'best_value', 'purple', 'Choose Plan', '#7c3aed', '#ffffff', '#7c3aed', 'bottom',
        2500, 10000000, 20000, 650, null, null, null, 1, 15, 1, 1, 1, 10240, 5, 'active'],
];

// Bulk scheduling / Auto Website-to-Daily-Pin / Auto Article / Single Article Writer are NOT
// repeated here — pricing.php and upgrade.php already render those 4 automatically (as a ✓/✕
// line) straight from the plan's own DB columns above, so listing them again would duplicate them.
$featureRows = [
    'Free' => ['5 Image AI credits/month', '10,000 Text AI credits/month', 'Unlimited Pinterest accounts', 'Unlimited websites', 'Unlimited pin uploads', '100 pins/month (15/day)', '2 team members', 'Analytics', 'Delete 2 underperforming pins/day', '100MB cloud storage', 'Email support'],
    'Starter' => ['300 Image AI credits/month', '1,000,000 Text AI credits/month', 'Unlimited Pinterest accounts', 'Unlimited websites', 'Unlimited pin uploads', '2,100 pins/month (70/day)', '2 team members', 'Analytics', 'Delete 5 underperforming pins/day', '1GB cloud storage', 'Email support'],
    'Pro' => ['600 Image AI credits/month', '2,000,000 Text AI credits/month', '4,200 pins/month (140/day)', 'All from Starter', '4 team members', 'Analytics', 'Delete 10 underperforming pins/day', '2GB cloud storage', 'Priority support'],
    'Growth' => ['1,200 Image AI credits/month', '4,000,000 Text AI credits/month', '8,500 pins/month (300/day)', 'All from Pro', '8 team members', 'Analytics', 'Delete 15 underperforming pins/day', '5GB cloud storage', 'Priority support'],
    'Agency' => ['2,500 Image AI credits/month', '10,000,000 Text AI credits/month', '20,000 pins/month (650/day)', 'All from Growth', '15 team members', 'Analytics', 'Delete 20 underperforming pins/day', '10GB cloud storage', 'Urgent support'],
];

$planCols = ['name', 'is_free', 'price_monthly', 'discount_monthly', 'discount_yearly', 'short_description', 'tag', 'tag_color',
    'pay_button_text', 'pay_button_bg', 'pay_button_text_color', 'pay_button_border_color', 'buy_button_position',
    'image_ai_credits_monthly', 'text_ai_credits_monthly', 'pin_scheduling_monthly_limit', 'pin_scheduling_daily_limit',
    'pinterest_accounts_limit', 'websites_limit', 'upload_pins_limit', 'bulk_scheduling_enabled', 'invite_team_members_limit',
    'auto_website_daily_pin_enabled', 'auto_article_enabled', 'single_article_writer_enabled', 'cloud_storage_mb', 'sort_order', 'status'];

$updated = [];
$inserted = [];
$errors = [];

try {
    $selectStmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE name = ? LIMIT 1");
    $updateStmt = $pdo->prepare("UPDATE pricing_plans SET " . implode(', ', array_map(fn($c) => "$c = ?", array_slice($planCols, 1))) . " WHERE id = ?");
    $insertStmt = $pdo->prepare("INSERT INTO pricing_plans (" . implode(', ', $planCols) . ") VALUES (" . implode(',', array_fill(0, count($planCols), '?')) . ")");
    $deleteRowsStmt = $pdo->prepare("DELETE FROM plan_feature_rows WHERE plan_id = ?");
    $insertRowStmt = $pdo->prepare("INSERT INTO plan_feature_rows (plan_id, text, checkmark_type, text_size, text_color, checkmark_size, checkmark_color, sort_order) VALUES (?,?,?,?,?,?,?,?)");

    foreach ($plans as $p) {
        $name = $p[0];
        $selectStmt->execute([$name]);
        $existingId = $selectStmt->fetchColumn();

        if ($existingId) {
            $updateStmt->execute([...array_slice($p, 1), (int)$existingId]);
            $planId = (int)$existingId;
            $updated[] = $name;
        } else {
            $insertStmt->execute($p);
            $planId = (int)$pdo->lastInsertId();
            $inserted[] = $name;
        }

        $deleteRowsStmt->execute([$planId]);
        $order = 0;
        foreach ($featureRows[$name] as $text) {
            $insertRowStmt->execute([$planId, $text, 'check', '14px', '#1a1a1a', '16px', '#16a34a', $order++]);
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pricing Plans Update</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card" style="max-width:620px;">
    <h1>Pricing Plans Update</h1>
    <?php if (empty($errors)): ?>
        <div class="alert alert-success">
            Done.
            <?php if ($updated): ?>Updated: <strong><?= htmlspecialchars(implode(', ', $updated)) ?></strong>.<br><?php endif; ?>
            <?php if ($inserted): ?>Created (didn't exist yet): <strong><?= htmlspecialchars(implode(', ', $inserted)) ?></strong>.<br><?php endif; ?>
            <br>
            Free, Starter, Pro, Growth and Agency now have the corrected prices, AI credit amounts, pin limits,
            team-member limits, cloud storage and feature bullets. Pricing (<code>/pricing.php</code>), the
            homepage plan section, and each user's Upgrade page all read from the same plans, so they'll all show
            the updated numbers immediately — no cache to clear.<br><br>
            <strong>Delete this file (update-pricing-plans.php) now for security.</strong>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="pricing" class="btn-primary">View Pricing Page</a>
</div>
</body>
</html>
