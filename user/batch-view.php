<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'batches';

$batchId = trim($_GET['batch_id'] ?? '');
$batch = get_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    redirect('batches');
}
$pageTitle = $batch['name'] ?: 'Batch';

$pinFilter = $_GET['pin_filter'] ?? 'all';
if (!in_array($pinFilter, ['all', 'published', 'unpublished'], true)) $pinFilter = 'all';

$sql = "SELECT * FROM scheduled_pins WHERE user_id = ? AND batch_id = ?";
$params = [$user['id'], $batchId];
if ($pinFilter === 'published') {
    $sql .= " AND status = 'published'";
} elseif ($pinFilter === 'unpublished') {
    $sql .= " AND status != 'published'";
}
$sql .= " ORDER BY publish_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pins = $stmt->fetchAll();

$totalStmt = $pdo->prepare("SELECT
    COUNT(*) AS total,
    SUM(status = 'published') AS published,
    SUM(status = 'pending') AS pending,
    SUM(status = 'failed') AS failed
    FROM scheduled_pins WHERE user_id = ? AND batch_id = ?");
$totalStmt->execute([$user['id'], $batchId]);
$totals = $totalStmt->fetch();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1><?= e($batch['name'] ?: 'Untitled batch') ?></h1>
    <div style="display:flex; gap:10px;">
        <a href="ajax-download-batch-csv?batch_id=<?= e($batchId) ?>" class="btn-secondary">⬇ Download CSV</a>
        <a href="batches" class="btn-secondary">← All Batches</a>
    </div>
</div>

<div id="alertBox"></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= (int)$totals['total'] ?></div><div class="label">Total Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$totals['published'] ?></div><div class="label">Published</div></div>
    <div class="stat-card"><div class="num"><?= (int)$totals['pending'] ?></div><div class="label">Unpublished</div></div>
    <div class="stat-card"><div class="num"><?= (int)$totals['failed'] ?></div><div class="label">Failed</div></div>
</div>

<div class="tabs">
    <a href="?batch_id=<?= e($batchId) ?>&pin_filter=all" class="tab <?= $pinFilter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?batch_id=<?= e($batchId) ?>&pin_filter=published" class="tab <?= $pinFilter === 'published' ? 'active' : '' ?>">Published</a>
    <a href="?batch_id=<?= e($batchId) ?>&pin_filter=unpublished" class="tab <?= $pinFilter === 'unpublished' ? 'active' : '' ?>">Unpublished</a>
</div>

<div class="card">
    <?php if (empty($pins)): ?>
        <div class="empty-state">No pins match this filter.</div>
    <?php else: ?>
    <table>
        <tr><th>Image</th><th>Title</th><th>Publish At</th><th>Status</th><th></th></tr>
        <?php foreach ($pins as $p): ?>
        <?php
            $daysLeft = null;
            if ($p['status'] === 'pending') {
                $diff = (strtotime($p['publish_at']) - time()) / 86400;
                $daysLeft = $diff >= 0 ? ceil($diff) : floor($diff);
            }
        ?>
        <tr data-pin-row="<?= (int)$p['id'] ?>">
            <td><img src="../<?= e($p['image_path']) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>
            <td><?= e($p['title'] ?: '(no title)') ?></td>
            <td>
                <?= format_datetime($p['publish_at']) ?>
                <?php if ($daysLeft !== null): ?>
                    <div class="muted"><?= $daysLeft > 0 ? $daysLeft . ' day(s) left' : ($daysLeft === 0 ? 'Publishing today' : abs($daysLeft) . ' day(s) overdue') ?></div>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
                    <div class="muted" title="<?= e(user_facing_error($p['last_error'])) ?>">error ⓘ</div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($p['status'] === 'pending'): ?>
                    <button type="button" class="btn-secondary btn-small" data-edit-pin="<?= (int)$p['id'] ?>">Edit</button>
                <?php endif; ?>
            </td>
        </tr>
        <tr class="pin-edit-row" id="editRow<?= (int)$p['id'] ?>" style="display:none;">
            <td colspan="5">
                <div class="pin-edit-form">
                    <div class="two-col">
                        <div class="form-row"><label>Title</label><input type="text" data-f="title" value="<?= e((string)$p['title']) ?>"></div>
                        <div class="form-row"><label>Link</label><input type="url" data-f="link" value="<?= e((string)$p['dest_link']) ?>"></div>
                    </div>
                    <div class="form-row"><label>Description</label><textarea data-f="description"><?= e((string)$p['description']) ?></textarea></div>
                    <div class="two-col">
                        <div class="form-row"><label>Alt Text</label><input type="text" data-f="alt" value="<?= e((string)$p['alt_text']) ?>"></div>
                        <div class="form-row"><label>Keywords</label><input type="text" data-f="keywords" value="<?= e((string)$p['keywords']) ?>"></div>
                    </div>
                    <div class="two-col">
                        <div class="form-row"><label>Product Link</label><input type="url" data-f="product_link" value="<?= e((string)$p['product_link']) ?>"></div>
                        <div class="form-row"><label>Publish At</label><input type="datetime-local" data-f="publish_at" value="<?= e(server_to_user_input($p['publish_at'])) ?>"></div>
                    </div>
                    <button type="button" class="btn-primary btn-small" data-save-pin="<?= (int)$p['id'] ?>">Save Changes</button>
                    <button type="button" class="btn-secondary btn-small" data-cancel-edit="<?= (int)$p['id'] ?>">Cancel</button>
                </div>
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
document.querySelectorAll('[data-edit-pin]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editRow' + btn.dataset.editPin).style.display = '';
    });
});
document.querySelectorAll('[data-cancel-edit]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editRow' + btn.dataset.cancelEdit).style.display = 'none';
    });
});
document.querySelectorAll('[data-save-pin]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const pinId = btn.dataset.savePin;
        const row = document.getElementById('editRow' + pinId);
        const fd = new FormData();
        fd.append('pin_id', pinId);
        row.querySelectorAll('[data-f]').forEach(el => fd.append(el.dataset.f, el.value));
        btn.disabled = true;
        btn.textContent = 'Saving…';
        try {
            const res = await fetch('ajax-batch-pin-update', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.ok) {
                showAlert('Pin updated.', 'success');
                setTimeout(() => window.location.reload(), 700);
            } else {
                showAlert('Could not save: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (e) {
            showAlert('Network error while saving.', 'error');
        }
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    });
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
