<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cw_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'classic-wizard-drafts';
$pageTitle = 'Classic Wizard Drafts';

$stmt = $pdo->prepare("SELECT p.*,
        (SELECT COUNT(*) FROM cw_pins c WHERE c.project_id = p.id) AS pin_count,
        a.pinterest_username
    FROM cw_projects p
    LEFT JOIN pinterest_accounts a ON a.id = p.pinterest_account_id
    WHERE p.user_id = ? AND p.status = 'draft'
    ORDER BY p.updated_at DESC");
$stmt->execute([$user['id']]);
$drafts = $stmt->fetchAll();

$thumbStmt = $pdo->prepare("SELECT image_path FROM cw_pins WHERE project_id = ? ORDER BY id LIMIT 4");

include __DIR__ . '/includes/user-header.php';
?>
<link rel="stylesheet" href="../assets/css/classic-wizard.css?v=<?= @filemtime(__DIR__ . '/../assets/css/classic-wizard.css') ?: time() ?>">
<div class="page-header cw-head">
    <h1>Drafts</h1>
    <a href="classic-wizard" class="btn-primary">+ Create new schedule</a>
</div>
<p class="muted">Wizard runs you haven't approved yet. Nothing in a draft is published — open one to finish reviewing, then approve to schedule its pins.</p>

<div id="cwAlert" class="cw-alert"></div>
<div class="card">
    <?php if (empty($drafts)): ?>
        <div class="empty-state">
            <span class="icon">📝</span>
            No drafts. Runs you leave before approving show up here.
        </div>
    <?php else: ?>
    <table>
        <tr><th>Pins</th><th>Schedule</th><th>Pages</th><th>Account</th><th>Last edited</th><th></th></tr>
        <?php foreach ($drafts as $d):
            $pages = json_decode($d['pages_json'] ?? '', true) ?: [];
            $left = count(array_filter($pages, fn($p) => ($p['status'] ?? 'pending') === 'pending'));
            $thumbStmt->execute([$d['id']]);
            $thumbs = $thumbStmt->fetchAll(PDO::FETCH_COLUMN);
        ?>
        <tr id="draft-<?= (int)$d['id'] ?>">
            <td><div class="cw-table-thumbs"><?php foreach ($thumbs as $t): ?><img src="../<?= e($t) ?>" alt="" loading="lazy"><?php endforeach; ?><?php if (!$thumbs): ?><span class="muted">—</span><?php endif; ?></div></td>
            <td><strong><?= e($d['name'] ?: (parse_url((string)$d['site_url'], PHP_URL_HOST) ?: 'Untitled run')) ?></strong>
                <div class="muted"><?= (int)$d['pin_count'] ?> pin(s) ready<?= $left ? ' · ' . $left . ' page(s) still to generate' : '' ?></div></td>
            <td><?= count($pages) ?></td>
            <td><?= e($d['pinterest_username'] ?: '—') ?></td>
            <td><?= e(format_datetime($d['updated_at'])) ?></td>
            <td style="white-space:nowrap;">
                <a href="classic-wizard?draft=<?= (int)$d['id'] ?>" class="btn-primary btn-small">Continue</a>
                <button type="button" class="btn-danger btn-small" data-delete="<?= (int)$d['id'] ?>">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<script>
document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-delete]');
    if (!b || !confirm('Delete this draft and all its pins? This cannot be undone.')) return;
    const fd = new FormData(); fd.append('action', 'project_delete'); fd.append('project_id', b.dataset.delete);
    const r = await (await fetch('ajax-cw', { method: 'POST', body: fd })).json().catch(() => ({ ok: false, error: 'Network error.' }));
    if (r.ok) document.getElementById('draft-' + b.dataset.delete).remove();
    else document.getElementById('cwAlert').innerHTML = '<div class="alert alert-error">' + (r.error || 'Could not delete.') + '</div>';
});
</script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
