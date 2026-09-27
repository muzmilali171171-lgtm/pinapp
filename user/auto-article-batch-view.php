<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'auto-article-batches';

$batchId = trim($_GET['batch_id'] ?? '');
$batch = get_article_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    redirect('auto-article-batches');
}
$pageTitle = $batch['name'] ?: 'Batch';

$articles = get_article_batch_articles($pdo, $batch['id'], $user['id']);

$total = count($articles);
$published = 0; $remaining = 0; $failed = 0;
foreach ($articles as $a) {
    if ($a['status'] === 'published') $published++;
    elseif ($a['status'] === 'failed') $failed++;
    else $remaining++;
}

// Pins created from this batch's articles (via the pin_batches row the auto-pin scheduler created).
$pinSettings = json_decode($batch['pin_settings_json'] ?? '{}', true) ?: [];
$pinBatchToken = $pinSettings['pin_batch_token'] ?? null;
$pinStats = ['total' => 0, 'published' => 0, 'pending' => 0];
if ($pinBatchToken) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'published') AS published, SUM(status = 'pending') AS pending
        FROM scheduled_pins WHERE batch_id = ?");
    $stmt->execute([$pinBatchToken]);
    $row = $stmt->fetch();
    if ($row) $pinStats = ['total' => (int)$row['total'], 'published' => (int)$row['published'], 'pending' => (int)$row['pending']];
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1><?= e($batch['name'] ?: 'Untitled batch') ?></h1>
    <div style="display:flex; gap:10px;">
        <?php if ($remaining > 0 && $batch['status'] === 'active'): ?>
            <button type="button" class="btn-primary" id="processNowBtn">▶ Process Now</button>
        <?php endif; ?>
        <a href="auto-article-batches" class="btn-secondary">← Your Batches</a>
    </div>
</div>

<div id="alertBox"></div>
<div id="processStatusList" class="pi-status-list"></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= $published ?></div><div class="label">Published Articles</div></div>
    <div class="stat-card"><div class="num"><?= $remaining ?></div><div class="label">Remaining</div></div>
    <div class="stat-card"><div class="num"><?= $pinStats['published'] ?></div><div class="label">Pins Published</div></div>
    <div class="stat-card"><div class="num"><?= $pinStats['pending'] ?></div><div class="label">Pins Scheduled</div></div>
</div>

<div class="card">
    <h2>Batch Settings</h2>
    <p class="muted">
        Type: <strong><?= $batch['article_type'] === 'recipe' ? 'Recipe' : 'Ideas' ?></strong> ·
        Website: <strong><?= e($batch['site_name'] ?: '—') ?></strong> ·
        Category: <strong><?= e($batch['category'] ?: '—') ?></strong> ·
        Daily: <strong><?= (int)$batch['daily_count'] ?> article<?= $batch['daily_count'] == 1 ? '' : 's' ?>/day</strong> ·
        Pin Auto: <strong><?= $batch['publish_mode'] === 'pin_auto' ? 'On' : 'Off' ?></strong>
    </p>
    <?php if ($remaining > 0): ?>
        <p class="muted">Articles are normally generated automatically by the <code>cron/article-scheduler.php</code>
        scheduled task. If nothing is publishing and you haven't set that cron job up on your host yet, use
        <strong>Process Now</strong> above to run due articles immediately from here instead.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Titles (<?= $total ?>)</h2>
    <?php if (empty($articles)): ?>
        <div class="empty-state">No titles in this batch.</div>
    <?php else: ?>
    <table>
        <tr><th>Title</th><th>Scheduled For</th><th>Status</th><th>Pin Status</th><th></th></tr>
        <?php foreach ($articles as $a): ?>
        <tr data-article-row="<?= (int)$a['id'] ?>">
            <td><?= e($a['title']) ?></td>
            <td><?= $a['scheduled_for'] ? date('d M Y', strtotime($a['scheduled_for'])) : '—' ?></td>
            <td>
                <span class="badge badge-<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span>
                <?php if ($a['status'] === 'failed' && $a['last_error']): ?>
                    <button type="button" class="btn-danger btn-small view-error-btn" data-error="<?= e($a['last_error']) ?>" style="margin-left:6px;">View Error</button>
                <?php endif; ?>
            </td>
            <td><?= e(ucfirst($a['pin_status'])) ?></td>
            <td><?php if ($a['wp_post_url']): ?><a href="<?= e($a['wp_post_url']) ?>" target="_blank" class="btn-secondary btn-small">View Post</a><?php endif; ?></td>
        </tr>
        <?php if ($a['status'] === 'failed' && $a['last_error']): ?>
        <tr class="error-detail-row" id="errorRow<?= (int)$a['id'] ?>" style="display:none;">
            <td colspan="5"><div class="error-detail-box"><?= e($a['last_error']) ?></div></td>
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
        const row = btn.closest('tr');
        const id = row.dataset.articleRow;
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
                const res = await fetch('ajax-process-batch-now', { method: 'POST', body: fd });
                result = await res.json();
            } catch (e) {
                showAlert('Network error while processing — this step may have taken too long for the server to respond in time. Click Process Now again to resume; progress made so far is saved.', 'error');
                break;
            }
            if (result.done) {
                if (!sawAny) showAlert(result.message || 'Nothing due to process right now.', 'info');
                break;
            }
            sawAny = true;
            const rowId = 'procStatus' + result.article_id;
            let row = document.getElementById(rowId);
            if (!row) {
                statusList.insertAdjacentHTML('beforeend', '<div class="pi-status-row pi-status-pending" id="' + rowId + '"><span class="pi-status-dot"></span><span class="pi-status-text"></span></div>');
                row = document.getElementById(rowId);
            }
            const textEl = row.querySelector('.pi-status-text');
            const safeTitle = result.title.replace(/</g, '&lt;');
            if (result.more) {
                row.className = 'pi-status-row pi-status-pending';
                textEl.textContent = '⏳ Working on: ' + result.title;
            } else if (result.ok) {
                row.className = 'pi-status-row pi-status-ok';
                textEl.textContent = '✓ Published: ' + result.title;
            } else {
                row.className = 'pi-status-row pi-status-fail';
                textEl.textContent = '✗ Failed: ' + result.title + ' — ' + (result.error || 'unknown error');
                // Low/zero credits will fail every remaining due article the same way —
                // show the upgrade popup once and stop looping instead of failing through all of them.
                if (window.maybeShowUpgradePopup && window.maybeShowUpgradePopup(result.error, { cooldownKey: 'auto-article-batch' })) {
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
