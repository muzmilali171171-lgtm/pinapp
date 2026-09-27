<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'list';
$pageTitle = 'Scheduled Pins';
$uid = (int)$user['id'];

// ---- Filters: board, website, Pinterest account, status, date (in the user's own time zone) ----
$boardFilter = trim((string)($_GET['board_id'] ?? ''));
$siteFilter = strtolower(trim((string)($_GET['site'] ?? '')));
$accountFilter = (int)($_GET['account'] ?? 0);
$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'published', 'failed'], true) ? $_GET['status'] : '';
$range = in_array($_GET['range'] ?? '', ['today', 'yesterday', 'last30', 'custom'], true) ? $_GET['range'] : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : '';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;

// Host of a pin's link, without "www." — used for the website filter.
$hostExpr = "TRIM(LEADING 'www.' FROM LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(dest_link, '://', -1), '/', 1), '?', 1)))";
$normHost = fn(string $url) => preg_replace('/^www\./', '', strtolower((string)parse_url(preg_match('~^https?://~i', $url) ? $url : 'https://' . $url, PHP_URL_HOST)));

// Pins waiting on a board that hasn't been created on Pinterest yet have no board_id — skip those
// in the filter list (they would otherwise pass NULL into e() and crash the page).
$boards = $pdo->prepare("SELECT DISTINCT board_id, board_name FROM scheduled_pins WHERE user_id = ? AND board_id IS NOT NULL AND board_id <> '' ORDER BY board_name");
$boards->execute([$uid]);
$boards = $boards->fetchAll();

$accounts = $pdo->prepare("SELECT id, pinterest_username FROM pinterest_accounts WHERE user_id = ? ORDER BY pinterest_username");
$accounts->execute([$uid]);
$accounts = $accounts->fetchAll();

// Websites: the user's connected sites + every site their pins link to.
$sites = [];
try {
    $st = $pdo->prepare("SELECT site_name, site_url FROM websites WHERE user_id = ?");
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $w) {
        $h = $normHost((string)$w['site_url']);
        if ($h !== '') $sites[$h] = trim((string)$w['site_name']) !== '' ? $w['site_name'] . ' (' . $h . ')' : $h;
    }
} catch (Throwable $e) { /* websites table missing */ }
$st = $pdo->prepare("SELECT DISTINCT $hostExpr h FROM scheduled_pins WHERE user_id = ? AND dest_link LIKE 'http%'");
$st->execute([$uid]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $h) if ($h !== '' && !isset($sites[$h])) $sites[$h] = $h;
asort($sites, SORT_NATURAL | SORT_FLAG_CASE);

// Date range → server-time bounds.
$todayUser = (new DateTime('now', new DateTimeZone(user_tz())))->format('Y-m-d');
$dateFrom = $dateTo = null;
if ($range === 'today') {
    [$dateFrom, $dateTo] = user_day_range_server($todayUser);
} elseif ($range === 'yesterday') {
    [$dateFrom, $dateTo] = user_day_range_server(date('Y-m-d', strtotime($todayUser . ' -1 day')));
} elseif ($range === 'last30') {
    [$dateFrom, $dateTo] = user_day_range_server(date('Y-m-d', strtotime($todayUser . ' -29 days')), 30);
} elseif ($range === 'custom' && ($from !== '' || $to !== '')) {
    if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
    if ($from !== '') $dateFrom = user_day_range_server($from)[0];
    if ($to !== '') $dateTo = user_day_range_server($to)[1];
}

$where = "user_id = ?";
$params = [$uid];
if ($boardFilter !== '') { $where .= " AND board_id = ?"; $params[] = $boardFilter; }
if ($siteFilter !== '') { $where .= " AND $hostExpr = ?"; $params[] = $siteFilter; }
if ($accountFilter > 0) { $where .= " AND pinterest_account_id = ?"; $params[] = $accountFilter; }
if ($statusFilter !== '') { $where .= " AND status = ?"; $params[] = $statusFilter; }
if ($dateFrom) { $where .= " AND publish_at >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND publish_at < ?"; $params[] = $dateTo; }

$st = $pdo->prepare("SELECT status, COUNT(*) c FROM scheduled_pins WHERE $where GROUP BY status");
$st->execute($params);
$counts = ['pending' => 0, 'published' => 0, 'failed' => 0];
foreach ($st->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];
$total = array_sum($counts);
if ($statusFilter !== '') $total = $counts[$statusFilter] ?? 0;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare("SELECT * FROM scheduled_pins WHERE $where ORDER BY publish_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$stmt->execute($params);
$pins = $stmt->fetchAll();

$accountNames = array_column($accounts, 'pinterest_username', 'id');
$filtersOn = $boardFilter !== '' || $siteFilter !== '' || $accountFilter || $statusFilter !== '' || $range !== '';
$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge([
    'board_id' => $boardFilter, 'site' => $siteFilter, 'account' => $accountFilter ?: '', 'status' => $statusFilter,
    'range' => $range, 'from' => $range === 'custom' ? $from : '', 'to' => $range === 'custom' ? $to : '',
], $over), fn($v) => $v !== '' && $v !== null && $v !== 0));

include __DIR__ . '/includes/user-header.php';
?>
<style>
.sl-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
.sl-filters .form-row { margin: 0; }
.sl-dates { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; align-items: center; }
.sl-dates a { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--border); font-size: 13px; font-weight: 600; color: var(--dark); background: #fff; }
.sl-dates a:hover { text-decoration: none; background: var(--light); }
.sl-dates a.on { background: var(--red); border-color: var(--red); color: #fff; }
.sl-custom { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; }
.sl-custom input[type=date] { padding: 8px 10px; border: 1px solid var(--border); border-radius: 10px; font: inherit; }
.sl-custom[hidden] { display: none; }
.sl-sum { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; font-size: 13px; }
.sl-sum span { background: var(--light); border-radius: 8px; padding: 6px 10px; }
.sl-pages { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 14px; }
.sl-table-wrap { overflow-x: auto; }
[data-theme="dark"] .sl-dates a { background: #1e2025; border-color: #33353c; color: #e5e7eb; }
[data-theme="dark"] .sl-dates a.on { background: var(--red); border-color: var(--red); color: #fff; }
[data-theme="dark"] .sl-sum span { background: #2a2c33; }
</style>
<div class="page-header">
    <h1>Scheduled Pins <span class="muted">(<?= number_format($total) ?> <?= $filtersOn ? 'found' : 'total' ?>)</span></h1>
    <div style="display:flex; gap:10px;">
        <a href="bulk-schedule" class="btn-secondary">Bulk Scheduler</a>
        <a href="schedule-create" class="btn-primary">+ New Schedule</a>
    </div>
</div>

<div class="card">
    <form method="GET" id="slForm">
        <div class="sl-filters">
            <div class="form-row">
                <label>Website</label>
                <select name="site" onchange="this.form.submit()">
                    <option value="">All websites</option>
                    <?php foreach ($sites as $h => $label): ?>
                        <option value="<?= e($h) ?>" <?= $siteFilter === $h ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Pinterest account</label>
                <select name="account" onchange="this.form.submit()">
                    <option value="">All accounts</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $accountFilter === (int)$a['id'] ? 'selected' : '' ?>><?= e((string)($a['pinterest_username'] ?: 'Account #' . $a['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Board</label>
                <select name="board_id" onchange="this.form.submit()">
                    <option value="">All boards</option>
                    <?php foreach ($boards as $b): ?>
                        <option value="<?= e($b['board_id']) ?>" <?= $boardFilter === $b['board_id'] ? 'selected' : '' ?>><?= e((string)($b['board_name'] ?: $b['board_id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Status</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach (['pending' => 'Scheduled', 'published' => 'Published', 'failed' => 'Failed'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <input type="hidden" name="range" id="slRange" value="<?= e($range) ?>">
        <div class="sl-dates">
            <strong style="font-size:13px; margin-right:4px;">Date:</strong>
            <a href="<?= e($qs(['range' => '', 'from' => '', 'to' => '', 'p' => ''])) ?>" class="<?= $range === '' ? 'on' : '' ?>">All time</a>
            <a href="<?= e($qs(['range' => 'today', 'from' => '', 'to' => '', 'p' => ''])) ?>" class="<?= $range === 'today' ? 'on' : '' ?>">Today</a>
            <a href="<?= e($qs(['range' => 'yesterday', 'from' => '', 'to' => '', 'p' => ''])) ?>" class="<?= $range === 'yesterday' ? 'on' : '' ?>">Yesterday</a>
            <a href="<?= e($qs(['range' => 'last30', 'from' => '', 'to' => '', 'p' => ''])) ?>" class="<?= $range === 'last30' ? 'on' : '' ?>">Last 30 days</a>
            <a href="#" id="slCustomBtn" class="<?= $range === 'custom' ? 'on' : '' ?>">Custom</a>
            <?php if ($filtersOn): ?><a href="schedule-list" style="border-style:dashed;">✕ Clear filters</a><?php endif; ?>
        </div>
        <div class="sl-custom" id="slCustom" <?= $range === 'custom' ? '' : 'hidden' ?>>
            <label for="slFrom" class="muted" style="font-size:13px;">From</label>
            <input type="date" name="from" id="slFrom" value="<?= e($from) ?>">
            <label for="slTo" class="muted" style="font-size:13px;">To</label>
            <input type="date" name="to" id="slTo" value="<?= e($to) ?>">
            <button type="submit" class="btn-primary btn-small" onclick="document.getElementById('slRange').value='custom';">Apply</button>
        </div>
    </form>
    <div class="sl-sum">
        <span>⏳ Scheduled: <strong><?= number_format($counts['pending']) ?></strong></span>
        <span>✅ Published: <strong><?= number_format($counts['published']) ?></strong></span>
        <span>⚠️ Failed: <strong><?= number_format($counts['failed']) ?></strong></span>
        <span class="muted">Times in <?= e(str_replace('_', ' ', user_tz())) ?> (<?= e(tz_offset_label(user_tz())) ?>)</span>
    </div>
</div>

<div class="card">
    <?php if (empty($pins)): ?>
        <div class="empty-state"><?= $filtersOn ? 'No pins match these filters.' : 'No scheduled pins found.' ?></div>
    <?php else: ?>
    <div class="sl-table-wrap">
    <table>
        <tr><th>Image</th><th>Title</th><th>Website</th><th>Account</th><th>Board</th><th>Source</th><th>Publish At</th><th>Status</th></tr>
        <?php foreach ($pins as $p): $host = $p['dest_link'] ? $normHost((string)$p['dest_link']) : ''; ?>
        <tr>
            <td><img src="../<?= e($p['image_path']) ?>" alt="" loading="lazy" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>
            <td><?= e((string)($p['title'] ?: '(no title)')) ?></td>
            <td><?= $host !== '' ? '<a href="' . e($qs(['site' => $host, 'p' => ''])) . '" class="muted">' . e($host) . '</a>' : '<span class="muted">—</span>' ?></td>
            <td><span class="muted"><?= e((string)($accountNames[$p['pinterest_account_id']] ?? '—')) ?></span></td>
            <td><?= e((string)($p['board_name'] ?: ($p['board_id'] ?: 'Being created…'))) ?></td>
            <td><span class="muted"><?= e(ucfirst(str_replace('_', ' ', (string)($p['source'] ?? 'manual')))) ?></span></td>
            <td><?= e(format_datetime($p['publish_at'])) ?></td>
            <td>
                <span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span>
                <?php if ($p['status'] === 'failed' && $p['last_error']): ?>
                    <div class="muted" title="<?= e(user_facing_error($p['last_error'])) ?>">error ⓘ</div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="sl-pages">
            <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?>
                <a href="<?= e($qs(['p' => $i > 1 ? $i : ''])) ?>" class="<?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-small"><?= $i ?></a>
            <?php endfor; ?>
            <span class="muted" style="align-self:center; font-size:13px;">Page <?= $page ?> of <?= $pages ?></span>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.getElementById('slCustomBtn').addEventListener('click', function (e) {
    e.preventDefault();
    var box = document.getElementById('slCustom');
    box.hidden = false;
    document.getElementById('slFrom').focus();
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
