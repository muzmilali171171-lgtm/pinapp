<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'affiliate-payouts';
$pageTitle = 'Affiliate — Payouts';

if (!affiliate_tables_ready($pdo)) {
    include __DIR__ . '/includes/user-header.php';
    ?>
    <div class="page-header"><h1>Affiliate Payouts</h1></div>
    <div class="card">
        <div class="alert alert-info">The Affiliate Program is not available right now. Please check back soon.</div>
    </div>
    <?php
    include __DIR__ . '/includes/user-footer.php';
    exit;
}

$settings = affiliate_settings_get($pdo);
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_method') {
        $method = $_POST['method'] ?? '';
        $result = affiliate_save_payout_method($pdo, (int)$user['id'], $method, $_POST['details'] ?? '');
        if ($result['ok']) {
            $success = 'Your ' . (AFFILIATE_METHOD_LABELS[$method] ?? $method) . ' payout details were saved.';
        } else {
            $errors[] = $result['error'];
        }
    } elseif ($action === 'request_payout') {
        $result = affiliate_request_payout($pdo, (int)$user['id'], $_POST['method'] ?? '');
        if ($result['ok']) {
            $success = 'Your payout request was submitted and is pending review.';
        } else {
            $errors[] = $result['error'];
        }
    }
}

$balance = affiliate_balance($pdo, (int)$user['id']);
$methods = affiliate_get_payout_methods($pdo, (int)$user['id']);
$methodsByKey = [];
foreach ($methods as $m) $methodsByKey[$m['method']] = $m;
$payouts = affiliate_get_user_payouts($pdo, (int)$user['id']);

$enabledMethods = array_filter(['crypto'], fn($m) => !empty($settings["{$m}_enabled"]));

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Affiliate Payouts</h1></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><div class="num">$<?= number_format($balance['total_earning'], 2) ?></div><div class="label">Total Earning</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($balance['total_paid'], 2) ?></div><div class="label">Total Paid</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($balance['to_pay'], 2) ?></div><div class="label">Next Payout Amount</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($balance['pending_payout'], 2) ?></div><div class="label">Future Payout Amount</div></div>
</div>

<div class="card">
    <p class="muted">Minimum payout threshold: <strong>$<?= number_format($settings['min_payout_threshold'], 2) ?></strong>.
    "Next Payout Amount" is your available balance you can request now; "Future Payout Amount" is what's already
    requested and awaiting approval and payment.</p>

    <?php if (empty($enabledMethods)): ?>
        <div class="alert alert-info">Payout methods are not available yet. Please check back soon.</div>
    <?php else: ?>
        <h2>Request a Payout</h2>
        <?php if ($balance['to_pay'] < $settings['min_payout_threshold']): ?>
            <p class="muted">You need at least $<?= number_format($settings['min_payout_threshold'], 2) ?> available to request a payout.</p>
        <?php else: ?>
        <form method="POST" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="action" value="request_payout">
            <div class="form-row" style="margin-bottom:0;">
                <label>Pay me via</label>
                <select name="method">
                    <?php foreach ($enabledMethods as $m): ?>
                        <option value="<?= e($m) ?>" <?= empty($methodsByKey[$m]) ? 'disabled' : '' ?>>
                            <?= e(AFFILIATE_METHOD_LABELS[$m]) ?><?= empty($methodsByKey[$m]) ? ' — add your details below first' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Request $<?= number_format($balance['to_pay'], 2) ?> Payout</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($enabledMethods)): ?>
<div class="card">
    <h2>Payout Methods</h2>
    <div class="two-col">
        <?php foreach ($enabledMethods as $m): $saved = $methodsByKey[$m] ?? null; ?>
        <div>
            <h3><?= e(AFFILIATE_METHOD_LABELS[$m]) ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_method">
                <input type="hidden" name="method" value="<?= e($m) ?>">
                <div class="form-row">
                    <label>USDT Wallet Address (TRC20 network only)</label>
                    <input type="text" name="details" value="<?= e($saved['details'] ?? '') ?>" placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" pattern="T[a-zA-Z0-9]{33}" maxlength="34">
                    <p class="muted" style="margin-top:6px;">Only the <strong>TRC20</strong> network is supported. Sending to an address on another network (ERC20, BEP20, etc.) can result in permanent loss of funds — double-check before saving.</p>
                </div>
                <button type="submit" class="btn-secondary btn-small"><?= $saved ? 'Update' : 'Save' ?></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>Payout History</h2>
    <?php if (empty($payouts)): ?>
        <p class="muted">No payout requests yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach ($payouts as $p): ?>
            <tr>
                <td><?= e(format_datetime($p['requested_at'])) ?></td>
                <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= e(AFFILIATE_METHOD_LABELS[$p['method']] ?? $p['method']) ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'paid' ? 'active' : ($p['status'] === 'rejected' ? 'error' : 'pending') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                <td><?= e($p['admin_note'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
