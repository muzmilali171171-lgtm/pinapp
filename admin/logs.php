<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'logs';
$pageTitle = 'Logs';

$typeFilter = trim($_GET['type'] ?? '');
$sql = "SELECT l.*, u.name as user_name FROM logs l LEFT JOIN users u ON u.id = l.user_id";
$params = [];
if (in_array($typeFilter, ['oauth', 'api', 'publish', 'system'], true)) {
    $sql .= " WHERE l.type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY l.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Logs</h1></div>

<div class="card">
    <form method="GET" class="form-row" style="max-width:260px;">
        <label>Type</label>
        <select name="type" onchange="this.form.submit()">
            <option value="">All types</option>
            <option value="oauth" <?= $typeFilter === 'oauth' ? 'selected' : '' ?>>OAuth events</option>
            <option value="api" <?= $typeFilter === 'api' ? 'selected' : '' ?>>API responses/errors</option>
            <option value="publish" <?= $typeFilter === 'publish' ? 'selected' : '' ?>>Publishing history</option>
            <option value="system" <?= $typeFilter === 'system' ? 'selected' : '' ?>>System</option>
        </select>
    </form>
</div>

<div class="card">
    <?php if (empty($logs)): ?>
        <div class="empty-state">No logs yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Time</th><th>Type</th><th>User</th><th>Message</th></tr>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td class="muted"><?= format_datetime($l['created_at']) ?></td>
            <td><span class="badge badge-pending"><?= e($l['type']) ?></span></td>
            <td><?= e($l['user_name'] ?? '-') ?></td>
            <td><?= e($l['message']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
