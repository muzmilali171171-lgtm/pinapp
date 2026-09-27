<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'list';
$pageTitle = 'Scheduled Pins';

$boardFilter = trim($_GET['board_id'] ?? '');

// Pins waiting on a board that hasn't been created on Pinterest yet have no board_id — skip those
// in the filter list (they would otherwise pass NULL into e() and crash the page).
$boards = $pdo->prepare("SELECT DISTINCT board_id, board_name FROM scheduled_pins WHERE user_id = ? AND board_id IS NOT NULL AND board_id <> '' ORDER BY board_name");
$boards->execute([$user['id']]);
$boards = $boards->fetchAll();

$sql = "SELECT * FROM scheduled_pins WHERE user_id = ?";
$params = [$user['id']];
if ($boardFilter !== '') {
    $sql .= " AND board_id = ?";
    $params[] = $boardFilter;
}
$sql .= " ORDER BY publish_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pins = $stmt->fetchAll();

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Scheduled Pins <span class="muted">(<?= count($pins) ?> total)</span></h1>
    <div style="display:flex; gap:10px;">
        <a href="bulk-schedule" class="btn-secondary">Bulk Scheduler</a>
        <a href="schedule-create" class="btn-primary">+ New Schedule</a>
    </div>
</div>

<div class="card">
    <form method="GET" class="form-row" style="max-width:320px;">
        <label>Filter by board</label>
        <select name="board_id" onchange="this.form.submit()">
            <option value="">All boards</option>
            <?php foreach ($boards as $b): ?>
                <option value="<?= e($b['board_id']) ?>" <?= $boardFilter === $b['board_id'] ? 'selected' : '' ?>>
                    <?= e((string)($b['board_name'] ?: $b['board_id'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <?php if (empty($pins)): ?>
        <div class="empty-state">No scheduled pins found.</div>
    <?php else: ?>
    <table>
        <tr><th>Image</th><th>Title</th><th>Board</th><th>Source</th><th>Publish At</th><th>Status</th></tr>
        <?php foreach ($pins as $p): ?>
        <tr>
            <td><img src="../<?= e($p['image_path']) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>
            <td><?= e((string)($p['title'] ?: '(no title)')) ?></td>
            <td><?= e((string)($p['board_name'] ?: ($p['board_id'] ?: 'Being created…'))) ?></td>
            <td><span class="muted"><?= e(ucfirst($p['source'] ?? 'manual')) ?></span></td>
            <td><?= format_datetime($p['publish_at']) ?></td>
            <td>
                <span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
                    <div class="muted" title="<?= e($p['last_error']) ?>">error ⓘ</div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
