<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'users';
$pageTitle = 'Users';

// Suspend/reactivate toggle
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $current = $stmt->fetchColumn();
    if ($current) {
        $new = $current === 'active' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $uid]);
    }
    redirect('users');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_credits'])) {
    $uid = (int)$_POST['user_id'];
    $imageCredits = max(0, (float)($_POST['image_credits'] ?? 0));
    $textCredits = max(0, (float)($_POST['text_credits'] ?? 0));
    try {
        $pdo->prepare("UPDATE users SET image_credits_balance = ?, text_credits_balance = ? WHERE id = ?")->execute([$imageCredits, $textCredits, $uid]);
        log_event($pdo, 'system', "Admin set user #$uid credits to image=$imageCredits, text=$textCredits");
    } catch (Throwable $e) {
        // Split-credit columns not migrated yet — nothing to do.
    }
    redirect('users');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_plan'])) {
    $uid = (int)$_POST['user_id'];
    $planId = (int)($_POST['plan_id'] ?? 0);
    try {
        if ($planId > 0) {
            $plan = get_plan($pdo, $planId);
            if ($plan) {
                activate_plan_for_user($pdo, $uid, $planId, 'monthly');
                log_event($pdo, 'system', "Admin manually set user #$uid to plan '{$plan['name']}'");
                create_notification($pdo, $uid, 'plan', 'Your plan was updated', "You're now on the {$plan['name']} plan.", '/user/upgrade');
            }
        } else {
            $pdo->prepare("UPDATE users SET plan_id = NULL, plan_end_date = NULL WHERE id = ?")->execute([$uid]);
        }
    } catch (Throwable $e) {
        // Plan Pricing tables/columns aren't migrated yet — silently skip rather than 500.
    }
    redirect('users');
}

$users = $pdo->query("SELECT u.*,
        (SELECT COUNT(*) FROM pinterest_accounts pa WHERE pa.user_id = u.id) as accounts,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.user_id = u.id) as pins
    FROM users u ORDER BY u.created_at DESC")->fetchAll();
$allPlans = get_all_plans($pdo);
$plansById = array_column($allPlans, null, 'id');

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Users <span class="muted">(<?= count($users) ?>)</span></h1></div>

<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state">No users yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Name</th><th>Email</th><th>Plan</th><th>Connected Accounts</th><th>Scheduled Posts</th><th>Credits</th><th>Status</th><th>Joined</th><th></th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td>
                <form method="POST" style="display:flex; gap:6px; align-items:center;">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <select name="plan_id" style="max-width:140px;">
                        <option value="">No plan</option>
                        <?php foreach ($allPlans as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)($u['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="change_plan" value="1" class="btn-secondary btn-small">Set</button>
                </form>
            </td>
            <td><?= (int)$u['accounts'] ?></td>
            <td><?= (int)$u['pins'] ?></td>
            <td>
                <form method="POST" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="number" step="0.1" name="image_credits" value="<?= e((string)($u['image_credits_balance'] ?? 0)) ?>" style="width:70px;" title="Image AI credits">
                    <input type="number" step="0.1" name="text_credits" value="<?= e((string)($u['text_credits_balance'] ?? 0)) ?>" style="width:70px;" title="Text AI credits">
                    <button type="submit" name="set_credits" value="1" class="btn-secondary btn-small">Set</button>
                </form>
                <div class="muted" style="font-size:11px; margin-top:2px;">Image / Text</div>
            </td>
            <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
            <td><?= format_datetime($u['created_at']) ?></td>
            <td><a href="?toggle=<?= (int)$u['id'] ?>" class="btn-secondary btn-small"><?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
