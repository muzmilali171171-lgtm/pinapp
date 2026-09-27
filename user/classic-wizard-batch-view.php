<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'classic-wizard-batches';

$batchId = trim($_GET['batch_id'] ?? '');
$batch = get_website_pin_batch($pdo, $batchId, $user['id']);
if (!$batch || $batch['wizard_source'] !== 'classic_wizard') redirect('classic-wizard-batches');
$pageTitle = $batch['name'] ?: 'Scheduled Pins';

$pages = get_website_pin_batch_pages($pdo, $batch['id']);
$total = count($pages);
$completed = 0; $remaining = 0; $failed = 0; $pendingApproval = 0;
foreach ($pages as $p) {
    if ($p['status'] === 'scheduled') $completed++;
    elseif ($p['status'] === 'failed') $failed++;
    elseif ($p['status'] === 'pending_approval') $pendingApproval++;
    else $remaining++;
}

$pinStats = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'published') AS published, SUM(status = 'pending') AS pending,
    COUNT(DISTINCT board_name) AS boards FROM scheduled_pins WHERE batch_id = ?");
$pinStats->execute([$batch['batch_id']]);
$pinStats = $pinStats->fetch();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1><?= e($batch['name'] ?: 'Untitled schedule') ?></h1>
    <div style="display:flex; gap:10px;">
        <?php if ($remaining > 0 && $batch['status'] === 'active'): ?>
            <button type="button" class="btn-primary" id="processNowBtn">▶ Process Now</button>
        <?php endif; ?>
        <a href="classic-wizard-batches" class="btn-secondary">← Your Scheduled Pins</a>
    </div>
</div>

<div id="alertBox"></div>
<div id="processStatusList" class="pi-status-list"></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= (int)$pinStats['published'] ?></div><div class="label">Published Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pinStats['total'] ?></div><div class="label">All Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pinStats['boards'] ?></div><div class="label">Boards Used</div></div>
    <div class="stat-card"><div class="num"><?= $pendingApproval ?></div><div class="label">Pending Your Approval</div></div>
    <div class="stat-card"><div class="num"><?= $completed ?>/<?= $total ?></div><div class="label">Pages Completed</div></div>
</div>

<?php if ($pendingApproval > 0): ?>
<div class="alert alert-info">
    <strong><?= $pendingApproval ?></strong> page(s) have pins ready for your review — approve to schedule them, or reject to discard.
    <button type="button" class="btn-secondary btn-small" id="approveAllBtn" style="margin-left:10px;">Approve All</button>
</div>
<?php endif; ?>

<div class="card">
    <h2>Schedule Settings</h2>
    <p class="muted">
        Website: <strong><?= e($batch['site_name'] ?: '—') ?></strong> ·
        Pins per page: <strong><?= (int)$batch['pins_per_page'] ?></strong> ·
        Gap between pins from the same URL: <strong><?= (int)$batch['page_gap_days'] ?> day(s)</strong> ·
        Pins per day: <strong><?= (int)$batch['daily_pin_count'] ?></strong> ·
        Boards: <strong>Multi-select — AI picks per pin</strong>
        <?php if ($batch['warmup_enabled']): ?> · <strong>Warmup on</strong><?php endif; ?>
        <?php if ($batch['no_link_pins']): ?> · <strong>No-link pins</strong><?php endif; ?>
    </p>
</div>

<div class="card">
    <h2>Pages (<?= $total ?>)</h2>
    <?php if (empty($pages)): ?>
        <div class="empty-state">No pages in this schedule.</div>
    <?php else: ?>
    <table>
        <tr><th>Page URL</th><th>Status</th><th>Board</th><th></th></tr>
        <?php foreach ($pages as $p): ?>
        <tr data-page-row="<?= (int)$p['id'] ?>">
            <td><a href="<?= e($p['page_url']) ?>" target="_blank"><?= e($p['page_url']) ?></a></td>
            <td>
                <span class="badge badge-<?= e($p['status'] === 'scheduled' ? 'published' : ($p['status'] === 'failed' ? 'failed' : ($p['status'] === 'pending_approval' ? 'pending' : 'pending'))) ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
                    <button type="button" class="btn-danger btn-small view-error-btn" data-error="<?= e($p['last_error']) ?>" style="margin-left:6px;">View Error</button>
                <?php endif; ?>
            </td>
            <td class="muted"><?= e($p['board_name'] ?: '—') ?></td>
            <td style="white-space:nowrap;">
                <?php if ($p['status'] === 'pending_approval'): ?>
                    <button type="button" class="btn-primary btn-small cw-approve-btn" data-page-id="<?= (int)$p['id'] ?>">✓ Approve</button>
                    <button type="button" class="btn-danger btn-small cw-reject-btn" data-page-id="<?= (int)$p['id'] ?>">✕ Reject</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
        <tr class="error-detail-row" id="errorRow<?= (int)$p['id'] ?>" style="display:none;">
            <td colspan="4"><div class="error-detail-box"><?= e($p['last_error']) ?></div></td>
        </tr>
        <?php endif; ?>
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
document.querySelectorAll('.view-error-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.closest('tr').dataset.pageRow;
        const detail = document.getElementById('errorRow' + id);
        if (detail) detail.style.display = detail.style.display === 'none' ? '' : 'none';
    });
});

async function cwResolvePage(pageId, approve) {
    const fd = new FormData();
    fd.append('page_id', pageId);
    if (approve) fd.append('approve', '1');
    const res = await fetch('ajax-classic-wizard-approve', { method: 'POST', body: fd });
    return res.json();
}

document.querySelectorAll('.cw-approve-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        const result = await cwResolvePage(btn.dataset.pageId, true);
        if (result.ok) { showAlert('Approved — ' + result.scheduled + ' pin(s) scheduled.', 'success'); setTimeout(() => location.reload(), 800); }
        else { showAlert(result.error || 'Could not approve.', 'error'); btn.disabled = false; }
    });
});
document.querySelectorAll('.cw-reject-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Reject this page\'s pins? They will not be scheduled.')) return;
        btn.disabled = true;
        const result = await cwResolvePage(btn.dataset.pageId, false);
        if (result.ok) { showAlert('Rejected.', 'info'); setTimeout(() => location.reload(), 800); }
        else { showAlert(result.error || 'Could not reject.', 'error'); btn.disabled = false; }
    });
});
document.getElementById('approveAllBtn')?.addEventListener('click', async function () {
    this.disabled = true;
    this.textContent = 'Approving…';
    const ids = Array.from(document.querySelectorAll('.cw-approve-btn')).map(b => b.dataset.pageId);
    let ok = 0;
    for (const id of ids) {
        const result = await cwResolvePage(id, true);
        if (result.ok) ok++;
    }
    showAlert('Approved ' + ok + ' of ' + ids.length + ' page(s).', 'success');
    setTimeout(() => location.reload(), 800);
});

const processBtn = document.getElementById('processNowBtn');
if (processBtn) {
    const statusList = document.getElementById('processStatusList');
    processBtn.addEventListener('click', async () => {
        processBtn.disabled = true;
        let round = 0;
        let sawAny = false;
        while (round < 300) {
            round++;
            processBtn.textContent = 'Processing… (step ' + round + ')';
            const fd = new FormData();
            fd.append('batch_id', <?= json_encode($batch['batch_id']) ?>);
            let result;
            try {
                const res = await fetch('ajax-process-website-batch-now', { method: 'POST', body: fd });
                result = await res.json();
            } catch (e) {
                showAlert('Network error — this step may have taken too long. Click Process Now again to resume; progress made so far is saved.', 'error');
                break;
            }
            if (result.done) {
                if (!sawAny) showAlert(result.message || 'Nothing due to process right now.', 'info');
                break;
            }
            sawAny = true;
            const rowId = 'procStatus' + result.page_id;
            let row = document.getElementById(rowId);
            if (!row) {
                statusList.insertAdjacentHTML('beforeend', '<div class="pi-status-row pi-status-pending" id="' + rowId + '"><span class="pi-status-dot"></span><span class="pi-status-text"></span></div>');
                row = document.getElementById(rowId);
            }
            const textEl = row.querySelector('.pi-status-text');
            const safeUrl = result.url.replace(/</g, '&lt;');
            if (result.more) {
                row.className = 'pi-status-row pi-status-pending';
                textEl.textContent = '⏳ Working on: ' + safeUrl;
            } else if (result.ok) {
                row.className = 'pi-status-row pi-status-ok';
                textEl.textContent = '✓ Ready for review: ' + safeUrl;
            } else {
                row.className = 'pi-status-row pi-status-fail';
                textEl.textContent = '✗ Failed: ' + safeUrl + ' — ' + (result.error || 'unknown error');
                if (window.maybeShowUpgradePopup && window.maybeShowUpgradePopup(result.error, { cooldownKey: 'classic-wizard-batch' })) {
                    break;
                }
            }
        }
        processBtn.disabled = false;
        processBtn.textContent = '▶ Process Now';
        setTimeout(() => window.location.reload(), 1200);
    });
}
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
