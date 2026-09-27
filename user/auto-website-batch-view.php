<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'auto-website-batches';

$batchId = trim($_GET['batch_id'] ?? '');
$batch = get_website_pin_batch($pdo, $batchId, $user['id']);
if (!$batch) redirect('auto-website-batches');
$pageTitle = $batch['name'] ?: 'Scheduled Website';

$pages = get_website_pin_batch_pages($pdo, $batch['id']);
$total = count($pages);
$completed = 0; $remaining = 0; $failed = 0;
foreach ($pages as $p) {
    if ($p['status'] === 'scheduled') $completed++;
    elseif ($p['status'] === 'failed') $failed++;
    else $remaining++;
}

$pinStats = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'published') AS published, SUM(status = 'pending') AS pending,
    COUNT(DISTINCT board_name) AS boards FROM scheduled_pins WHERE batch_id = ?");
$pinStats->execute([$batch['batch_id']]);
$pinStats = $pinStats->fetch();

// Publish date & time of every pin, per page
$pagePins = [];
$pp = $pdo->prepare("SELECT source_website_page_id, publish_at, published_at, status FROM scheduled_pins WHERE batch_id = ? ORDER BY publish_at ASC");
$pp->execute([$batch['batch_id']]);
foreach ($pp->fetchAll() as $row) $pagePins[(int)$row['source_website_page_id']][] = $row;

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1><?= e($batch['name'] ?: 'Untitled schedule') ?></h1>
    <div style="display:flex; gap:10px;">
        <?php if ($remaining > 0 && $batch['status'] === 'active'): ?>
            <button type="button" class="btn-primary" id="processNowBtn">▶ Process Now</button>
        <?php endif; ?>
        <a href="auto-website-batches" class="btn-secondary">← Your Scheduled Websites</a>
    </div>
</div>

<div id="alertBox"></div>
<div id="processStatusList" class="pi-status-list"></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= (int)$pinStats['published'] ?></div><div class="label">Published Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pinStats['total'] ?></div><div class="label">All Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pinStats['pending'] ?></div><div class="label">Remaining Pins</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pinStats['boards'] ?></div><div class="label">Boards Created</div></div>
    <div class="stat-card"><div class="num"><?= $completed ?>/<?= $total ?></div><div class="label">Pages Completed</div></div>
</div>

<div class="card">
    <h2>Schedule Settings</h2>
    <p class="muted">
        Website: <strong><?= e($batch['site_name'] ?: '—') ?></strong> ·
        Pins per page: <strong><?= (int)$batch['pins_per_page'] ?></strong> ·
        Gap between a page's own pins: <strong><?= (($batch['page_gap_unit'] ?? '') === 'minutes' && !empty($batch['page_gap_minutes'])) ? (int)$batch['page_gap_minutes'] . ' minute(s)' : (int)$batch['page_gap_days'] . ' day(s)' ?></strong> ·
        Pins per day: <strong><?= (int)$batch['daily_pin_count'] ?></strong> ·
        Board mode: <strong><?= $batch['board_mode'] === 'existing' ? 'One existing board' : 'AI — separate board per page' ?></strong>
    </p>
</div>

<div class="card">
    <h2>Pages (<?= $total ?>)</h2>
    <?php if (empty($pages)): ?>
        <div class="empty-state">No pages in this schedule.</div>
    <?php else: ?>
    <table>
        <tr><th>Page URL</th><th>Status</th><th>Board</th><th>Publish date &amp; time</th></tr>
        <?php foreach ($pages as $p): ?>
        <tr data-page-row="<?= (int)$p['id'] ?>">
            <td><a href="<?= e($p['page_url']) ?>" target="_blank"><?= e($p['page_url']) ?></a></td>
            <td>
                <span class="badge badge-<?= e($p['status'] === 'scheduled' ? 'published' : ($p['status'] === 'failed' ? 'failed' : 'pending')) ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
                    <button type="button" class="btn-danger btn-small view-error-btn" data-error="<?= e(user_facing_error($p['last_error'])) ?>" style="margin-left:6px;">View Error</button>
                <?php endif; ?>
            </td>
            <td class="muted"><?= e($p['board_name'] ?: '—') ?></td>
            <td style="font-size:13px; white-space:nowrap;">
                <?php $list = $pagePins[(int)$p['id']] ?? []; ?>
                <?php if (!$list): ?><span class="muted">—</span><?php endif; ?>
                <?php foreach ($list as $i => $pin): ?>
                    <div title="<?= e(ucfirst($pin['status'])) ?>">
                        <?= $pin['status'] === 'published' ? '✅' : ($pin['status'] === 'failed' ? '❌' : '🕒') ?>
                        Pin <?= $i + 1 ?>: <?= e(server_to_user_dt($pin['status'] === 'published' && $pin['published_at'] ? $pin['published_at'] : $pin['publish_at'])?->format('M j, Y · g:i A') ?? '-') ?>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
        <tr class="error-detail-row" id="errorRow<?= (int)$p['id'] ?>" style="display:none;">
            <td colspan="4"><div class="error-detail-box"><?= e(user_facing_error($p['last_error'])) ?></div></td>
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
                textEl.textContent = '⏳ Working on: ' + result.url;
            } else if (result.ok) {
                row.className = 'pi-status-row pi-status-ok';
                textEl.textContent = '✓ Scheduled: ' + result.url;
            } else {
                row.className = 'pi-status-row pi-status-fail';
                textEl.textContent = '✗ Failed: ' + result.url + ' — ' + (result.error || 'unknown error');
                // Low/zero image credits will fail every remaining due page the same way —
                // show the upgrade popup once and stop looping instead of failing through all of them.
                if (window.maybeShowUpgradePopup && window.maybeShowUpgradePopup(result.error, { cooldownKey: 'auto-website-batch' })) {
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
