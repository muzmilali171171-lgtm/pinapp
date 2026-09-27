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
$activePage = 'affiliate-dashboard';
$pageTitle = 'Affiliate — Dashboard';

if (!affiliate_tables_ready($pdo)) {
    include __DIR__ . '/includes/user-header.php';
    ?>
    <div class="page-header"><h1>Affiliate Dashboard</h1></div>
    <div class="card">
        <div class="alert alert-info">The Affiliate Program is not available right now. Please check back soon.</div>
    </div>
    <?php
    include __DIR__ . '/includes/user-footer.php';
    exit;
}

$settings = affiliate_settings_get($pdo);
$errors = [];
$linkSaved = false;

if (!$settings['program_enabled']) {
    include __DIR__ . '/includes/user-header.php';
    ?>
    <div class="page-header"><h1>Affiliate Dashboard</h1></div>
    <div class="card"><div class="alert alert-info">The affiliate program isn't currently active. Please check back later.</div></div>
    <?php
    include __DIR__ . '/includes/user-footer.php';
    exit;
}

$code = affiliate_get_or_create_code($pdo, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_link') {
    $result = affiliate_set_custom_code($pdo, (int)$user['id'], trim($_POST['custom_link'] ?? ''));
    if ($result['ok']) {
        $code = affiliate_get_or_create_code($pdo, (int)$user['id']);
        $linkSaved = true;
    } else {
        $errors[] = $result['error'];
    }
}

$stats = affiliate_get_stats($pdo, (int)$user['id']);
$referrals = affiliate_get_recent_referrals($pdo, (int)$user['id'], 20);
$sales = affiliate_get_sales($pdo, (int)$user['id'], 50);
$affiliateLink = affiliate_link_url($code);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Affiliate Dashboard</h1></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($linkSaved): ?><div class="alert alert-success">Your affiliate link was updated.</div><?php endif; ?>

<div class="card">
    <h2>Your Affiliate Link</h2>
    <p class="muted">Share this link. Anyone who signs up through it — and any plan they buy — earns you
    <?= e(rtrim(rtrim(number_format($settings['commission_percent'], 2), '0'), '.')) ?>%
    commission<?= $settings['duration_type'] === 'lifetime' ? ' for life.' : (' for ' . (int)$settings['duration_months'] . ' month(s) after they sign up.') ?></p>
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="text" id="affLinkInput" readonly value="<?= e($affiliateLink) ?>" style="flex:1; min-width:220px; padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
        <button type="button" class="btn-secondary" onclick="copyAffLink()">Copy Link</button>
    </div>
    <form method="POST" style="margin-top:14px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="action" value="edit_link">
        <div class="form-row" style="margin-bottom:0;">
            <label>Edit your link (letters &amp; numbers only)</label>
            <input type="text" name="custom_link" value="<?= e($code) ?>" placeholder="yourname">
        </div>
        <button type="submit" class="btn-primary">Save Link</button>
    </form>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="num">$<?= number_format($stats['total_earning'], 2) ?></div><div class="label">Total Earning</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_sales'] ?></div><div class="label">Total Sell</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_clicks'] ?></div><div class="label">Total Clicks</div></div>
    <div class="stat-card"><div class="num"><?= (int)$stats['total_customers'] ?></div><div class="label">Total Customers</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($stats['to_pay'], 2) ?></div><div class="label">To Pay</div></div>
</div>

<div class="card">
    <h2>Recent Referrals</h2>
    <?php if (empty($referrals)): ?>
        <p class="muted">No one has signed up through your link yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($referrals as $r): ?>
            <tr>
                <td><?= e(format_datetime($r['created_at'])) ?></td>
                <td><?= e($r['name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
                <td><span class="badge badge-<?= $r['status'] === 'customer' ? 'active' : 'pending' ?>"><?= $r['status'] === 'customer' ? 'Customer' : 'Signed up' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Affiliate Sales</h2>
    <?php if (empty($sales)): ?>
        <p class="muted">No commissioned sales yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>User Handle</th><th>Plan</th><th>Revenue</th><th>Earn %</th><th>Earnings</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <tr>
                <td><?= e(format_datetime($s['created_at'])) ?></td>
                <td><?= e($s['name']) ?><br><span class="muted"><?= e($s['email']) ?></span></td>
                <td><?= e($s['plan_name'] ?? '—') ?></td>
                <td>$<?= number_format((float)$s['revenue_amount'], 2) ?></td>
                <td><?= number_format((float)$s['commission_percent'], 2) ?>%</td>
                <td>$<?= number_format((float)$s['commission_amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function copyAffLink() {
    var el = document.getElementById('affLinkInput');
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard && navigator.clipboard.writeText(el.value).catch(function () { document.execCommand('copy'); });
}
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
