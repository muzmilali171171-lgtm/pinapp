<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Coupons';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Coupons</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_coupon') {
    $id = !empty($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : null;
    if (trim($_POST['name'] ?? '') === '' || trim($_POST['code'] ?? '') === '') {
        redirect('coupons?error=1');
    }
    save_coupon($pdo, $id, $_POST);
    redirect('coupons?saved=1');
}
if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("SELECT status FROM coupons WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    $status = $stmt->fetchColumn();
    if ($status !== false) {
        $pdo->prepare("UPDATE coupons SET status = ? WHERE id = ?")->execute([$status === 'active' ? 'inactive' : 'active', (int)$_GET['toggle']]);
    }
    redirect('coupons');
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([(int)$_GET['delete']]);
    redirect('coupons');
}

$coupons = get_all_coupons($pdo);
$plans = get_all_plans($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Coupons</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Coupon saved.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="alert alert-error">Name and code are required.</div><?php endif; ?>

<div class="tab-row">
    <a href="plans">All Plans</a>
    <a href="plan-create">Create Plan</a>
    <a href="payment-gateways">Payment Gateway Integration</a>
    <a href="coupons" class="active">Coupons</a>
    <a href="contact-sales">Contact Sales</a>
    <a href="plan-users">Users</a>
    <a href="plan-settings">Setting</a>
</div>

<div class="card">
    <h2 id="coupon-form-title">Create Coupon</h2>
    <form method="POST" id="coupon-form">
        <input type="hidden" name="action" value="save_coupon">
        <input type="hidden" name="coupon_id" id="coupon_id" value="">
        <div class="two-col">
            <div class="form-row"><label>Name</label><input type="text" name="name" id="coupon_name" required></div>
            <div class="form-row"><label>Coupon code</label><input type="text" name="code" id="coupon_code" style="text-transform:uppercase;" required></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>End date</label><input type="date" name="end_date" id="coupon_end_date"></div>
            <div class="form-row"><label>Discount %</label><input type="number" step="0.01" name="discount_percent" id="coupon_discount" required></div>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="apply_to_all_plans" id="coupon_all_plans" value="1" checked onchange="document.getElementById('coupon-plan-picker').style.display=this.checked?'none':'block';"> Apply to all plans</label>
        <div id="coupon-plan-picker" class="form-row" style="display:none;">
            <label>Or tick specific plans this coupon applies to</label>
            <?php foreach ($plans as $p): ?>
                <label class="checkbox-row"><input type="checkbox" name="plan_ids[]" value="<?= (int)$p['id'] ?>" class="coupon-plan-checkbox"> <?= e($p['name']) ?></label>
            <?php endforeach; ?>
            <?php if (empty($plans)): ?><p class="muted">No plans created yet.</p><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Status</label>
            <select name="status" id="coupon_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Save Coupon</button>
        <button type="button" class="btn-secondary" onclick="resetCouponForm();">+ New Coupon Instead</button>
    </form>
</div>

<div class="card">
    <h2>All Coupons</h2>
    <?php if (empty($coupons)): ?>
        <p class="muted">No coupons yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Code</th><th>Discount</th><th>Status</th><th>Used By</th><th>Link</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $c):
            $expired = $c['end_date'] && strtotime($c['end_date']) < strtotime(date('Y-m-d'));
            $link = coupon_public_link($c);
            $planIdsStmt = $pdo->prepare("SELECT plan_id FROM coupon_plans WHERE coupon_id = ?");
            $planIdsStmt->execute([$c['id']]);
            $c['plan_ids'] = array_map('intval', $planIdsStmt->fetchAll(PDO::FETCH_COLUMN));
        ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><code><?= e($c['code']) ?></code></td>
                <td><?= e($c['discount_percent']) ?>%</td>
                <td>
                    <span class="badge badge-<?= ($c['status'] === 'active' && !$expired) ? 'connected' : 'error' ?>">
                        <?= $expired ? 'Expired' : e(ucfirst($c['status'])) ?>
                    </span>
                </td>
                <td><?= (int)$c['total_used'] ?></td>
                <td><a href="<?= e($link) ?>" target="_blank" style="font-size:12px;"><?= e($link) ?></a></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn-secondary btn-small" onclick='editCoupon(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>Edit</button>
                    <a href="?toggle=<?= (int)$c['id'] ?>" class="btn-secondary btn-small"><?= $c['status'] === 'active' ? 'Deactivate' : 'Activate' ?></a>
                    <a href="?delete=<?= (int)$c['id'] ?>" class="btn-danger btn-small" onclick="return confirm('Delete this coupon?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function editCoupon(c) {
    document.getElementById('coupon-form-title').textContent = 'Edit Coupon';
    document.getElementById('coupon_id').value = c.id;
    document.getElementById('coupon_name').value = c.name;
    document.getElementById('coupon_code').value = c.code;
    document.getElementById('coupon_end_date').value = c.end_date || '';
    document.getElementById('coupon_discount').value = c.discount_percent;
    document.getElementById('coupon_all_plans').checked = c.apply_to_all_plans == 1;
    document.getElementById('coupon-plan-picker').style.display = c.apply_to_all_plans == 1 ? 'none' : 'block';
    document.querySelectorAll('.coupon-plan-checkbox').forEach(function (cb) {
        cb.checked = (c.plan_ids || []).map(String).indexOf(cb.value) !== -1;
    });
    document.getElementById('coupon_status').value = c.status;
    document.getElementById('coupon-form').scrollIntoView({behavior:'smooth'});
}
function resetCouponForm() {
    document.getElementById('coupon-form-title').textContent = 'Create Coupon';
    document.getElementById('coupon-form').reset();
    document.getElementById('coupon_id').value = '';
    document.getElementById('coupon_all_plans').checked = true;
    document.getElementById('coupon-plan-picker').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
