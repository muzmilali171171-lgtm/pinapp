<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'batches';
$pageTitle = 'Batches';

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'draft', 'scheduled', 'completed'], true)) $filter = 'all';

$allBatches = get_user_batches($pdo, $user['id'], 'all');
$counts = ['all' => count($allBatches), 'draft' => 0, 'scheduled' => 0, 'completed' => 0];
foreach ($allBatches as $b) { $counts[$b['computed_status']]++; }

$batches = $filter === 'all' ? $allBatches : array_values(array_filter($allBatches, fn($b) => $b['computed_status'] === $filter));

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Batches</h1>
    <a href="bulk-schedule" class="btn-primary">+ New Batch</a>
</div>

<div id="alertBox"></div>

<div class="tabs">
    <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">All <span class="tab-count"><?= $counts['all'] ?></span></a>
    <a href="?filter=draft" class="tab <?= $filter === 'draft' ? 'active' : '' ?>">Drafts <span class="tab-count"><?= $counts['draft'] ?></span></a>
    <a href="?filter=scheduled" class="tab <?= $filter === 'scheduled' ? 'active' : '' ?>">Scheduled <span class="tab-count"><?= $counts['scheduled'] ?></span></a>
    <a href="?filter=completed" class="tab <?= $filter === 'completed' ? 'active' : '' ?>">Completed <span class="tab-count"><?= $counts['completed'] ?></span></a>
</div>

<div class="card">
    <?php if (empty($batches)): ?>
        <div class="empty-state">
            <span class="icon">📦</span>
            No batches here yet. <a href="bulk-schedule">Start a new bulk batch</a>.
        </div>
    <?php else: ?>
    <table class="batches-table">
        <tr>
            <th>Batch</th>
            <th>Pinterest Account</th>
            <th>Board</th>
            <th>Progress</th>
            <th>Created</th>
            <th>First Pin</th>
            <th>Last Pin</th>
            <th>Status</th>
            <th></th>
        </tr>
        <?php foreach ($batches as $b): ?>
        <?php
            $pct = $b['pin_count'] > 0 ? round(($b['published_count'] / $b['pin_count']) * 100) : 0;
        ?>
        <tr>
            <td><strong><?= e($b['name'] ?: 'Untitled batch') ?></strong></td>
            <td><?= e($b['pinterest_username'] ?: '—') ?></td>
            <td><?= e($b['board_name'] ?: '—') ?></td>
            <td style="min-width:150px;">
                <?php if ($b['computed_status'] === 'draft'): ?>
                    <span class="muted">Not scheduled yet</span>
                <?php else: ?>
                    <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $pct ?>%;"></div></div>
                    <span class="muted"><?= (int)$b['published_count'] ?>/<?= (int)$b['pin_count'] ?> published<?= $b['failed_count'] > 0 ? ' · ' . (int)$b['failed_count'] . ' failed' : '' ?></span>
                <?php endif; ?>
            </td>
            <td><?= format_datetime($b['created_at']) ?></td>
            <td><?= format_datetime($b['first_publish_at']) ?></td>
            <td><?= format_datetime($b['last_publish_at']) ?></td>
            <td><span class="badge badge-batch-<?= e($b['computed_status']) ?>"><?= e(ucfirst($b['computed_status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($b['computed_status'] === 'draft'): ?>
                    <a href="bulk-schedule?draft=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">Edit</a>
                    <button type="button" class="btn-danger btn-small" data-delete-batch="<?= e($b['batch_id']) ?>">Delete</button>
                <?php else: ?>
                    <a href="batch-view?batch_id=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">View Full</a>
                    <a href="ajax-download-batch-csv?batch_id=<?= e($b['batch_id']) ?>" class="btn-secondary btn-small">⬇ CSV</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<script>
const alertBox = document.getElementById('alertBox');
function showAlert(message, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
document.querySelectorAll('[data-delete-batch]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const batchId = btn.dataset.deleteBatch;
        if (!confirm('Delete this draft batch? This cannot be undone.')) return;
        const fd = new FormData();
        fd.append('batch_id', batchId);
        try {
            const res = await fetch('ajax-batch-delete', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.ok) {
                btn.closest('tr').remove();
            } else {
                showAlert('Could not delete: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (e) {
            showAlert('Network error while deleting.', 'error');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
