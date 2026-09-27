<?php
/**
 * Admin → Articles Schedule → Batch Limits.
 * How many Auto Article batches each user can have writing / publishing at the same time
 * (default 5 per user), with an own limit per user, and a total for the whole server.
 * A user's extra batches wait and start automatically as soon as one of their running batches finishes.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'article-batch-limits';
$pageTitle = 'Batch Limits';
$msg = null;

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$backUrl = 'article-batch-limits' . ($q !== '' || $page > 1 ? '?' . http_build_query(array_filter(['q' => $q, 'p' => $page > 1 ? $page : null])) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_defaults'])) {
        article_batch_limit_save($pdo, 0, max(1, min(100, (int)($_POST['default_per_user'] ?? ARTICLE_DEFAULT_BATCHES_PER_USER))));
        article_batch_limit_save($pdo, -1, max(1, min(100, (int)($_POST['server_total'] ?? ARTICLE_MAX_PARALLEL_BATCHES))));
        log_event($pdo, 'system', 'Admin updated Auto Article batch limits (default per user / server total)');
        $_SESSION['abl_msg'] = 'Defaults saved.';
    } elseif (isset($_POST['save_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $val = trim((string)($_POST['max_batches'] ?? ''));
        if ($uid > 0) {
            article_batch_limit_save($pdo, $uid, $val === '' ? null : max(1, min(100, (int)$val)));
            log_event($pdo, 'system', "Admin set Auto Article batches at once for user #$uid to " . ($val === '' ? 'default' : (int)$val));
            $_SESSION['abl_msg'] = $val === '' ? 'User set back to the default.' : 'User limit saved.';
        }
    } elseif (isset($_POST['reset_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) article_batch_limit_save($pdo, $uid, null);
        $_SESSION['abl_msg'] = 'User set back to the default.';
    }
    // Start any batches that now have a free slot.
    try { article_batch_workers_kick($pdo); } catch (Throwable $e) { /* optional */ }
    redirect($backUrl);
}
if (!empty($_SESSION['abl_msg'])) { $msg = $_SESSION['abl_msg']; unset($_SESSION['abl_msg']); }

$limits = article_batch_limits_all($pdo);
$defaultPerUser = article_batch_limit_default($limits);
$serverTotal = article_batch_limit_total($limits);

// Live state: due batches per user, and how many are running right now.
$due = due_article_batches($pdo);
$allowed = array_flip(allowed_article_batch_ids($pdo));
$dueByUser = []; $runningByUser = []; $waitingByUser = []; $runningTotal = 0;
foreach ($due as $batchId => $userId) {
    $dueByUser[$userId] = ($dueByUser[$userId] ?? 0) + 1;
    if (article_batch_busy($batchId)) { $runningByUser[$userId] = ($runningByUser[$userId] ?? 0) + 1; $runningTotal++; }
    if (!isset($allowed[$batchId])) $waitingByUser[$userId] = ($waitingByUser[$userId] ?? 0) + 1;
}

// Users: search + pages; users with an own limit or batches in progress first.
$perPage = 50;
$where = '';
$params = [];
if ($q !== '') { $where = 'WHERE u.name LIKE ? OR u.email LIKE ?'; $params = ["%$q%", "%$q%"]; }
$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$cnt->execute($params);
$totalUsers = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $pages);
$busyIds = array_map('intval', array_unique(array_merge(array_keys($dueByUser), array_filter(array_keys($limits), fn($k) => $k > 0))));
$busyOrder = $busyIds ? 'u.id IN (' . implode(',', $busyIds) . ') DESC, ' : '';
$stmt = $pdo->prepare("SELECT u.id, u.name, u.email,
        (SELECT COUNT(*) FROM article_batches b WHERE b.user_id = u.id AND b.status = 'active') AS active_batches
    FROM users u $where
    ORDER BY {$busyOrder}u.created_at DESC
    LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Batch Limits</h1></div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
    <h2>Batches at once</h2>
    <p class="muted">Every Auto Article batch runs on its own — a new batch starts writing and publishing right away, without
        waiting for other batches. Here you choose how many batches <strong>one user</strong> can run at the same time.
        A user's extra batches wait and start automatically as soon as one of their running batches finishes.</p>
    <form method="POST" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-row" style="margin:0;">
            <label>Default batches at once per user</label>
            <input type="number" name="default_per_user" min="1" max="100" value="<?= (int)$defaultPerUser ?>" style="width:120px;">
        </div>
        <div class="form-row" style="margin:0;">
            <label>Total batches at once (whole server)</label>
            <input type="number" name="server_total" min="1" max="100" value="<?= (int)$serverTotal ?>" style="width:120px;">
        </div>
        <button type="submit" name="save_defaults" value="1" class="btn-primary">Save</button>
    </form>
    <p class="muted" style="margin-top:10px;">Right now: <strong><?= (int)$runningTotal ?></strong> batch(es) running,
        <strong><?= count($due) ?></strong> with articles due. Keep the server total within what your hosting can handle
        (each running batch is one background PHP process).</p>
</div>

<div class="card">
    <h2>Per-user limit</h2>
    <form method="GET" style="display:flex; gap:8px; margin-bottom:12px;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email" style="max-width:280px;">
        <button type="submit" class="btn-secondary">Search</button>
        <?php if ($q !== ''): ?><a href="article-batch-limits" class="btn-secondary">Clear</a><?php endif; ?>
    </form>
    <?php if (empty($users)): ?>
        <div class="empty-state">No users found.</div>
    <?php else: ?>
    <table>
        <tr><th>User</th><th>Active batches</th><th>Running now</th><th>Waiting for a slot</th><th>Batches at once</th></tr>
        <?php foreach ($users as $u): $uid = (int)$u['id']; $own = isset($limits[$uid]) && $limits[$uid] > 0; ?>
        <tr>
            <td><?= e($u['name']) ?><div class="muted" style="font-size:12px;"><?= e($u['email']) ?></div></td>
            <td><?= (int)$u['active_batches'] ?></td>
            <td><?= (int)($runningByUser[$uid] ?? 0) ?></td>
            <td><?= (int)($waitingByUser[$uid] ?? 0) ?></td>
            <td>
                <form method="POST" style="display:flex; gap:6px; align-items:center;">
                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                    <input type="number" name="max_batches" min="1" max="100" value="<?= $own ? (int)$limits[$uid] : '' ?>"
                           placeholder="<?= (int)$defaultPerUser ?> (default)" style="width:120px;">
                    <button type="submit" name="save_user" value="1" class="btn-secondary btn-small">Save</button>
                    <?php if ($own): ?><button type="submit" name="reset_user" value="1" class="btn-secondary btn-small">Use default</button><?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($pages > 1): ?>
        <div style="display:flex; gap:6px; margin-top:12px; flex-wrap:wrap;">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?<?= e(http_build_query(array_filter(['q' => $q, 'p' => $i]))) ?>" class="<?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-small"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
