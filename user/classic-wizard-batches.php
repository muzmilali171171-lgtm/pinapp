<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'classic-wizard-batches';
$pageTitle = 'Your Scheduled Pins';

if (isset($_GET['stop'])) {
    $pdo->prepare("UPDATE website_pin_batches SET status = 'stopped' WHERE batch_id = ? AND user_id = ? AND wizard_source = 'classic_wizard'")->execute([$_GET['stop'], $user['id']]);
    redirect('classic-wizard-batches');
}
if (isset($_GET['resume'])) {
    $pdo->prepare("UPDATE website_pin_batches SET status = 'active' WHERE batch_id = ? AND user_id = ? AND wizard_source = 'classic_wizard'")->execute([$_GET['resume'], $user['id']]);
    redirect('classic-wizard-batches');
}

$batches = get_user_website_pin_batches($pdo, $user['id'], 'classic_wizard');

// Runs approved in the current wizard (cw_projects), with live publish progress from scheduled_pins.
$runs = [];
try {
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.site_url, p.pin_batch_id, p.scheduled_at, a.pinterest_username,
            COUNT(sp.id) AS total, SUM(sp.status = 'published') AS published, SUM(sp.status = 'failed') AS failed,
            MIN(sp.publish_at) AS first_at, MAX(sp.publish_at) AS last_at
        FROM cw_projects p
        LEFT JOIN scheduled_pins sp ON sp.batch_id = p.pin_batch_id AND sp.user_id = p.user_id
        LEFT JOIN pinterest_accounts a ON a.id = p.pinterest_account_id
        WHERE p.user_id = ? AND p.status = 'scheduled'
        GROUP BY p.id ORDER BY p.scheduled_at DESC");
    $stmt->execute([$user['id']]);
    $runs = $stmt->fetchAll();
} catch (Throwable $e) {
    $runs = [];
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Your Scheduled Pins</h1>
    <a href="classic-wizard" class="btn-primary">+ Create New Schedule</a>
</div>

<div class="card">
    <?php if (empty($runs)): ?>
        <div class="empty-state">
            <span class="icon">🧙</span>
            No approved schedules yet. <a href="classic-wizard">Create one</a> — or finish one of your <a href="classic-wizard-drafts">drafts</a>.
        </div>
    <?php else: ?>
    <table>
        <tr><th>Schedule</th><th>Account</th><th>Pins</th><th>Published</th><th>First pin</th><th>Last pin</th><th></th></tr>
        <?php foreach ($runs as $r): ?>
        <tr>
            <td><strong><?= e($r['name'] ?: (parse_url((string)$r['site_url'], PHP_URL_HOST) ?: 'Schedule #' . $r['id'])) ?></strong>
                <div class="muted">Approved <?= e(format_datetime($r['scheduled_at'])) ?></div></td>
            <td><?= e($r['pinterest_username'] ?: '—') ?></td>
            <td><?= (int)$r['total'] ?></td>
            <td><?= (int)$r['published'] ?><?= (int)$r['failed'] ? ' <span class="badge badge-error">' . (int)$r['failed'] . ' failed</span>' : '' ?></td>
            <td><?= format_datetime($r['first_at']) ?></td>
            <td><?= format_datetime($r['last_at']) ?></td>
            <td><a href="batch-view?batch_id=<?= e($r['pin_batch_id']) ?>" class="btn-secondary btn-small">View pins</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php if (!empty($batches)): ?>
<h2 style="font-size:17px;margin-top:10px;">Earlier schedules</h2>
<div class="card">
    <?php if (empty($batches)): ?>
        <div class="empty-state">
            <span class="icon">🧙</span>
            No Classic Wizard schedules yet. <a href="classic-wizard">Create your first one</a>.
        </div>
    <?php else: ?>
    <table>
        <tr>
            <th>ID</th><th>Schedule Name</th><th>Website</th><th>First Pin</th><th>Last Pin</th>
            <th>Total Pages</th><th>Needs Approval</th><th>Status</th><th></th>
        </tr>
        <?php foreach ($batches as $b): ?>
        <tr>
            <td>#<?= (int)$b['id'] ?></td>
            <td><strong><?= e($b['name'] ?: 'Untitled schedule') ?></strong>
                <div class="muted"><?= (int)$b['completed_pages'] ?>/<?= (int)$b['total_pages'] ?> pages done<?= $b['failed_pages'] > 0 ? ' · ' . (int)$b['failed_pages'] . ' failed' : '' ?></div>
            </td>
            <td><?= e($b['site_name'] ?: '—') ?></td>
            <td><?= format_datetime($b['first_publish_at']) ?></td>
            <td><?= format_datetime($b['last_publish_at']) ?></td>
            <td><?= (int)$b['total_pages'] ?></td>
            <td><?php if ((int)$b['pending_approval_pages'] > 0): ?><span class="badge badge-pending"><?= (int)$b['pending_approval_pages'] ?> pending</span><?php else: ?>—<?php endif; ?></td>
            <td><span class="badge badge-<?= $b['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="classic-wizard-batch-view?batch_id=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">View More</a>
                <?php if ($b['status'] === 'active'): ?>
                    <a href="?stop=<?= e($b['batch_id']) ?>" class="btn-danger btn-small" onclick="return confirm('Stop this schedule? Pages not yet processed will not be generated.')">Stop</a>
                <?php else: ?>
                    <a href="?resume=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">Resume</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
