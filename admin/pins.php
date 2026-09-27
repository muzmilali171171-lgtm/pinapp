<?php
/**
 * Admin → All Pins Scheduled → All Pins.
 * Every pin of every user, with filters (status + date range), image, user, Pinterest account,
 * source and scheduled / published date. Published pins link to the pin on Pinterest.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'all-pins';
$pageTitle = 'All Pins';

$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'published', 'failed', 'scheduled'], true)) $status = 'all';
$range = $_GET['range'] ?? 'life';
if (!in_array($range, ['today', '30', 'life', 'custom'], true)) $range = 'life';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 48;

// the date that matters: publish time for published pins, scheduled time otherwise
$dateExpr = "COALESCE(sp.published_at, sp.publish_at)";
$where = [];
$args = [];
if ($status === 'published') $where[] = "sp.status = 'published'";
if ($status === 'failed') $where[] = "sp.status = 'failed'";
if ($status === 'scheduled') $where[] = "sp.status IN ('pending','processing')";
if ($range === 'today') { $where[] = "$dateExpr >= ? AND $dateExpr < ?"; $args[] = date('Y-m-d 00:00:00'); $args[] = date('Y-m-d 00:00:00', strtotime('+1 day')); }
if ($range === '30') { $where[] = "$dateExpr >= ?"; $args[] = date('Y-m-d H:i:s', strtotime('-30 days')); }
if ($range === 'custom') { $where[] = "$dateExpr >= ? AND $dateExpr < ?"; $args[] = $from . ' 00:00:00'; $args[] = date('Y-m-d 00:00:00', strtotime($to . ' +1 day')); }
if ($q !== '') { $where[] = "(sp.title LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR pa.pinterest_username LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$base = "FROM scheduled_pins sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN pinterest_accounts pa ON pa.id = sp.pinterest_account_id
    $whereSql";
$cnt = $pdo->prepare("SELECT COUNT(*), SUM(sp.status = 'published'), SUM(sp.status = 'failed'), SUM(sp.status IN ('pending','processing')) $base");
$cnt->execute($args);
[$total, $nPub, $nFail, $nSched] = array_map('intval', $cnt->fetch(PDO::FETCH_NUM));
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$st = $pdo->prepare("SELECT sp.*, u.name AS user_name, u.email AS user_email, pa.pinterest_username
    $base ORDER BY $dateExpr DESC, sp.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$st->execute($args);
$pins = $st->fetchAll();

$sourceLabels = ['manual' => 'Single pin', 'bulk' => 'Bulk', 'auto_article' => 'Auto Article', 'website_pin' => 'Website → Pin',
    'regenerate' => 'Regenerate', 'keyword' => 'Keyword'];
$qs = function (array $over) use ($status, $range, $from, $to, $q) {
    return '?' . http_build_query(array_merge(['status' => $status, 'range' => $range, 'from' => $from, 'to' => $to, 'q' => $q], $over));
};

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.ap-filters { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.ap-filters label { display:block; font-size:12px; font-weight:600; color:#4b5563; margin-bottom:4px; }
.ap-pills { display:flex; gap:6px; flex-wrap:wrap; }
.ap-pills a { padding:6px 13px; border:1px solid #d1d5db; border-radius:999px; text-decoration:none; color:#374151; font-size:13px; font-weight:600; background:#fff; }
.ap-pills a.on { background:#7c3aed; border-color:#7c3aed; color:#fff; }
.ap-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:14px; }
.ap-card { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; display:flex; flex-direction:column; }
.ap-card img { width:100%; aspect-ratio:2/3; object-fit:cover; background:#f3f4f6; display:block; }
.ap-body { padding:10px; font-size:12.5px; display:flex; flex-direction:column; gap:4px; flex:1; }
.ap-title { font-weight:700; color:#111827; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.ap-meta { color:#6b7280; }
.ap-meta b { color:#374151; font-weight:600; }
.ap-err { color:#b91c1c; font-size:11.5px; max-height:48px; overflow:auto; }
.ap-pager { display:flex; gap:6px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
.ap-pager a, .ap-pager span { padding:6px 11px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151; }
.ap-pager span { background:#7c3aed; color:#fff; border-color:#7c3aed; }
</style>
<div class="page-header"><h1>All Pins</h1></div>

<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= number_format($total) ?></div><div class="label">Pins in this view</div></div>
    <div class="stat-card"><div class="num"><?= number_format($nPub) ?></div><div class="label">Published</div></div>
    <div class="stat-card"><div class="num"><?= number_format($nSched) ?></div><div class="label">Scheduled</div></div>
    <div class="stat-card"><div class="num"><?= number_format($nFail) ?></div><div class="label">Failed</div></div>
</div>

<div class="card">
    <form method="get" class="ap-filters">
        <div>
            <label>Status</label>
            <div class="ap-pills">
                <?php foreach (['all' => 'All', 'published' => 'Published', 'scheduled' => 'Scheduled', 'failed' => 'Failed'] as $k => $l): ?>
                    <a href="<?= e($qs(['status' => $k, 'page' => 1])) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($l) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label>Date</label>
            <div class="ap-pills">
                <?php foreach (['today' => 'Today', '30' => 'Last 30 days', 'life' => 'Lifetime', 'custom' => '📅 Custom'] as $k => $l): ?>
                    <a href="<?= e($qs(['range' => $k, 'page' => 1])) ?>" class="<?= $range === $k ? 'on' : '' ?>"><?= e($l) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="range" value="<?= e($range) ?>">
        <?php if ($range === 'custom'): ?>
        <div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
        <div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
        <?php endif; ?>
        <div><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Title, user, @account"></div>
        <div><button type="submit" class="btn-primary">Apply</button></div>
    </form>
</div>

<div class="card">
    <?php if (!$pins): ?>
        <div class="empty-state">No pins match these filters.</div>
    <?php else: ?>
    <div class="ap-grid">
        <?php foreach ($pins as $p):
            $isPub = $p['status'] === 'published';
            $date = $isPub && $p['published_at'] ? $p['published_at'] : $p['publish_at'];
            $badge = $p['status'] === 'processing' ? 'processing' : $p['status'];
        ?>
        <div class="ap-card">
            <a href="<?= e(media_url((string)$p['image_path'], false)) ?>" target="_blank" rel="noopener"><img loading="lazy" src="<?= e(media_url((string)$p['image_path'], false)) ?>" alt=""></a>
            <div class="ap-body">
                <span class="badge badge-<?= e($badge) ?>" style="align-self:flex-start;"><?= e($p['status'] === 'pending' ? 'Scheduled' : ucfirst($p['status'])) ?></span>
                <div class="ap-title" title="<?= e($p['title']) ?>"><?= e($p['title'] ?: '(no title)') ?></div>
                <div class="ap-meta">👤 <b><?= e($p['user_name'] ?: $p['user_email']) ?></b></div>
                <div class="ap-meta">📌 <b><?= e($p['pinterest_username'] ? '@' . $p['pinterest_username'] : 'Account #' . $p['pinterest_account_id']) ?></b> · <?= e($p['board_name'] ?: '') ?></div>
                <div class="ap-meta">Source: <b><?= e($sourceLabels[$p['source']] ?? $p['source']) ?></b></div>
                <div class="ap-meta"><?= $isPub ? 'Published' : ($p['status'] === 'failed' ? 'Was due' : 'Scheduled') ?>: <b><?= e(format_datetime($date)) ?></b></div>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?><div class="ap-err" title="<?= e($p['last_error']) ?>"><?= e(mb_substr($p['last_error'], 0, 200)) ?></div><?php endif; ?>
                <?php if ($isPub && $p['pinterest_pin_id']): ?>
                    <a href="https://www.pinterest.com/pin/<?= e(rawurlencode($p['pinterest_pin_id'])) ?>/" target="_blank" rel="noopener" class="btn-secondary btn-small" style="margin-top:auto; text-align:center;">View pin ↗</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <div class="ap-pager">
        <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?>
            <?php if ($i === $page): ?><span><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
