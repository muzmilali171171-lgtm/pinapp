<?php
/**
 * Scheduled Pins — three views of the same pins, with the same filters (website, Pinterest account, status):
 *   List      — every pin, grouped by date (+ board and date-range filters)
 *   By Pages  — one row per page link with its pin totals; expand to see that page's pins
 *   Calendar  — a month grid: days with pins are coloured, hover shows the day's totals
 *               (scheduled / published / failed / articles not generated yet), click opens the day's pins
 * Every not-yet-published pin can be fully edited (image, title, description, link, board, account,
 * date & time) and stopped / resumed (see ajax-scheduled-pins.php).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/scheduled-pins-common.php';
require_login();

$user = current_user($pdo);
$activePage = 'list';
$pageTitle = 'Scheduled Pins';
$uid = (int)$user['id'];
sp_ensure_paused_status($pdo);

$view = in_array($_GET['view'] ?? '', ['list', 'pages', 'calendar'], true) ? $_GET['view'] : 'list';
$f = sp_filters($_GET);
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$tz = new DateTimeZone(user_tz());
$todayUser = (new DateTime('now', $tz))->format('Y-m-d');
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : substr($todayUser, 0, 7);

// ---- Filter options ----
$accounts = $pdo->prepare("SELECT id, pinterest_username FROM pinterest_accounts WHERE user_id = ? ORDER BY pinterest_username");
$accounts->execute([$uid]);
$accounts = $accounts->fetchAll();
$accountNames = [];
foreach ($accounts as $a) $accountNames[$a['id']] = $a['pinterest_username'] ?: 'Account #' . $a['id'];

$boards = $pdo->prepare("SELECT DISTINCT board_id, board_name FROM scheduled_pins WHERE user_id = ? AND board_id IS NOT NULL AND board_id <> '' ORDER BY board_name");
$boards->execute([$uid]);
$boards = $boards->fetchAll();

$sites = [];
$siteIdsByHost = [];
try {
    $st = $pdo->prepare("SELECT id, site_name, site_url FROM websites WHERE user_id = ?");
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $w) {
        $h = sp_norm_host((string)$w['site_url']);
        if ($h === '') continue;
        $sites[$h] = trim((string)$w['site_name']) !== '' ? $w['site_name'] . ' (' . $h . ')' : $h;
        $siteIdsByHost[$h][] = (int)$w['id'];
    }
} catch (Throwable $e) { /* websites table missing */ }
$st = $pdo->prepare("SELECT DISTINCT " . sp_host_expr() . " h FROM scheduled_pins WHERE user_id = ? AND dest_link LIKE 'http%'");
$st->execute([$uid]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $h) if ($h !== '' && !isset($sites[$h])) $sites[$h] = $h;
asort($sites, SORT_NATURAL | SORT_FLAG_CASE);

// ---- Totals for the current filters (all views) ----
[$where, $params] = sp_where($uid, $f, $view !== 'calendar');
$st = $pdo->prepare("SELECT status, COUNT(*) c FROM scheduled_pins WHERE $where GROUP BY status");
$st->execute($params);
$counts = ['pending' => 0, 'paused' => 0, 'published' => 0, 'failed' => 0];
foreach ($st->fetchAll() as $r) {
    $k = $r['status'] === 'processing' ? 'pending' : $r['status'];
    $counts[$k] = ($counts[$k] ?? 0) + (int)$r['c'];
}
$total = array_sum($counts);

$qs = function (array $over) use ($view, $f, $month) {
    return '?' . http_build_query(array_filter(array_merge([
        'view' => $view, 'site' => $f['site'], 'account' => $f['account'] ?: '', 'status' => $f['status'],
        'board_id' => $f['board_id'], 'range' => $f['range'],
        'from' => $f['range'] === 'custom' ? $f['from'] : '', 'to' => $f['range'] === 'custom' ? $f['to'] : '',
        'm' => $view === 'calendar' ? $month : '',
    ], $over), fn($v) => $v !== '' && $v !== null && $v !== 0));
};
$filtersOn = $f['site'] !== '' || $f['account'] || $f['status'] !== '' || $f['board_id'] !== '' || ($view !== 'calendar' && $f['range'] !== '');

// ---- View data ----
$pins = [];
$groups = [];
$pages = 1;
$perPage = $view === 'pages' ? 30 : 50;
if ($view === 'list') {
    $pages = max(1, (int)ceil($total / $perPage));
    $pageNum = min($pageNum, $pages);
    $asc = in_array($f['status'], ['pending', 'paused'], true);
    $stmt = $pdo->prepare("SELECT * FROM scheduled_pins WHERE $where ORDER BY publish_at " . ($asc ? 'ASC' : 'DESC') . " LIMIT $perPage OFFSET " . (($pageNum - 1) * $perPage));
    $stmt->execute($params);
    $pins = $stmt->fetchAll();
} elseif ($view === 'pages') {
    $st = $pdo->prepare("SELECT COUNT(DISTINCT COALESCE(dest_link, '')) FROM scheduled_pins WHERE $where");
    $st->execute($params);
    $pages = max(1, (int)ceil((int)$st->fetchColumn() / $perPage));
    $pageNum = min($pageNum, $pages);
    $stmt = $pdo->prepare("SELECT COALESCE(dest_link, '') AS link, COUNT(*) AS total,
            SUM(status IN ('pending', 'processing')) AS s_pending, SUM(status = 'paused') AS s_paused,
            SUM(status = 'published') AS s_published, SUM(status = 'failed') AS s_failed,
            MIN(CASE WHEN status = 'pending' THEN publish_at END) AS next_at, MAX(publish_at) AS last_at,
            MAX(image_path) AS img, MAX(title) AS title
        FROM scheduled_pins WHERE $where GROUP BY COALESCE(dest_link, '')
        ORDER BY last_at DESC LIMIT $perPage OFFSET " . (($pageNum - 1) * $perPage));
    $stmt->execute($params);
    $groups = $stmt->fetchAll();
}

// Calendar: per-day counts for the month (days in the user's time zone).
$cal = [];
$calArticles = [];
if ($view === 'calendar') {
    $first = new DateTime($month . '-01', $tz);
    $daysInMonth = (int)$first->format('t');
    [$a, $b] = user_day_range_server($first->format('Y-m-d'), $daysInMonth);
    // Seconds to add to a server time to get the user's local time (for grouping by the user's day).
    $mid = new DateTime($month . '-15 12:00:00', $tz);
    $offset = $tz->getOffset($mid) - (new DateTimeZone(date_default_timezone_get()))->getOffset($mid);
    $st = $pdo->prepare("SELECT DATE(DATE_ADD(publish_at, INTERVAL $offset SECOND)) d, status, COUNT(*) c
        FROM scheduled_pins WHERE $where AND publish_at >= ? AND publish_at < ? GROUP BY d, status");
    $st->execute(array_merge($params, [$a, $b]));
    foreach ($st->fetchAll() as $r) {
        $k = $r['status'] === 'processing' ? 'pending' : $r['status'];
        $cal[$r['d']][$k] = ($cal[$r['d']][$k] ?? 0) + (int)$r['c'];
    }
    // Auto Article (Pin Auto) articles due that day that aren't written yet — their pins come after publishing.
    if (in_array($f['status'], ['', 'pending'], true) && ($f['site'] === '' || !empty($siteIdsByHost[$f['site']]))) {
        try {
            $aw = "a.user_id = ? AND a.status NOT IN ('published', 'failed', 'draft') AND b.status = 'active' AND b.publish_mode = 'pin_auto'
                AND a.scheduled_for >= ? AND a.scheduled_for <= ?";
            $ap = [$uid, $first->format('Y-m-01'), $first->format('Y-m-t')];
            if ($f['site'] !== '') {
                $ids = $siteIdsByHost[$f['site']];
                $aw .= ' AND a.website_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $ap = array_merge($ap, $ids);
            }
            if ($f['account']) {
                $aw .= " AND JSON_UNQUOTE(JSON_EXTRACT(b.pin_settings_json, '$.pinterest_account_id')) = ?";
                $ap[] = (string)$f['account'];
            }
            $st = $pdo->prepare("SELECT a.scheduled_for d, COUNT(*) c FROM articles a JOIN article_batches b ON b.id = a.batch_id WHERE $aw GROUP BY a.scheduled_for");
            $st->execute($ap);
            foreach ($st->fetchAll() as $r) $calArticles[$r['d']] = (int)$r['c'];
        } catch (Throwable $e) { /* articles tables missing */ }
    }
}

include __DIR__ . '/includes/user-header.php';
?>
<style>
.sl-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
.sl-filters .form-row { margin: 0; }
.sl-views { display: inline-flex; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
.sl-views a { padding: 8px 16px; font-weight: 600; font-size: 13px; color: var(--dark); background: #fff; border-right: 1px solid var(--border); }
.sl-views a:last-child { border-right: none; }
.sl-views a:hover { text-decoration: none; background: var(--light); }
.sl-views a.on { background: var(--red); color: #fff; }
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
.sp-date-row td { background: var(--light); font-weight: 700; font-size: 13px; padding-top: 10px; }
.badge-paused { background: #f3f4f6; color: var(--gray); }
/* By Pages */
.spg { border: 1px solid var(--border); border-radius: 12px; margin-bottom: 10px; overflow: hidden; }
.spg-head { display: flex; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; }
.spg-head:hover { background: var(--light); }
.spg-head img { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
.spg-info { flex: 1; min-width: 0; }
.spg-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.spg-link { font-size: 12px; color: var(--gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.spg-stats { display: flex; gap: 6px; flex-wrap: wrap; font-size: 12px; }
.spg-stats span { background: var(--light); border-radius: 6px; padding: 3px 8px; }
.spg-total { font-weight: 700; font-size: 15px; min-width: 64px; text-align: right; }
.spg-caret { font-size: 18px; transition: transform .15s; color: var(--gray); }
.spg.open .spg-caret { transform: rotate(90deg); }
.spg-body { display: none; border-top: 1px solid var(--border); padding: 8px; overflow-x: auto; }
.spg.open .spg-body { display: block; }
/* Calendar */
.cal-nav { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.cal-nav h2 { margin: 0; font-size: 18px; }
.cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
.cal-dow { font-size: 12px; font-weight: 700; color: var(--gray); text-align: center; padding: 4px 0; }
.cal-day { position: relative; min-height: 86px; border: 1px solid var(--border); border-radius: 10px; padding: 6px; background: #fff; font-size: 12px; }
.cal-day.empty { background: transparent; border-style: dashed; opacity: .4; }
.cal-day.today { box-shadow: 0 0 0 2px var(--red) inset; }
.cal-day.has { cursor: pointer; }
.cal-day.has:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.cal-day.c-scheduled { background: #eff6ff; border-color: #bfdbfe; }
.cal-day.c-published { background: #f0fdf4; border-color: #bbf7d0; }
.cal-day.c-failed { background: #fef2f2; border-color: #fecaca; }
.cal-day.c-articles { background: #fffbeb; border-color: #fde68a; }
.cal-num { font-weight: 700; font-size: 13px; }
.cal-chips { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; }
.cal-chip { border-radius: 5px; padding: 1px 5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chip-s { background: #dbeafe; color: #1d4ed8; }
.chip-p { background: #dcfce7; color: var(--green); }
.chip-f { background: #fee2e2; color: #b91c1c; }
.chip-x { background: #f3f4f6; color: var(--gray); }
.chip-a { background: #fef3c7; color: var(--amber); }
.cal-tip { display: none; position: absolute; z-index: 20; left: 50%; top: 100%; transform: translate(-50%, 6px); min-width: 210px;
    background: #111827; color: #fff; border-radius: 10px; padding: 10px 12px; font-size: 12px; line-height: 1.7; box-shadow: 0 8px 24px rgba(0,0,0,.25); pointer-events: none; }
.cal-day.has:hover .cal-tip { display: block; }
/* the hovered day (and its tooltip) sits above the next rows' days */
.cal-day.has:hover, .cal-day.has:focus { z-index: 30; }
/* last row: open the tooltip upwards so it isn't cut off at the bottom */
.cal-day.tip-up .cal-tip { top: auto; bottom: 100%; transform: translate(-50%, -6px); }
.cal-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; font-size: 12px; }
.cal-chip i { font-style: normal; }
.cal-legend .cal-chip { white-space: normal; }
@media (max-width: 700px) {
    .cal-grid { gap: 3px; }
    .cal-day { min-height: 58px; padding: 3px; }
    .cal-chip { font-size: 11px; padding: 0 3px; text-align: center; text-overflow: clip; }
    .cal-day .cal-chip i { display: none; }   /* colour shows the status; number only */
    .cal-tip { display: none !important; }
}
/* Modals */
.sp-modal .modal-box { max-width: 980px; max-height: 88vh; overflow-y: auto; }
#spEditOverlay .modal-box { max-width: 720px; }
.sp-edit-grid { display: grid; grid-template-columns: 200px 1fr; gap: 18px; }
.sp-edit-grid img { width: 100%; aspect-ratio: 2 / 3; border-radius: 10px; object-fit: cover; background: var(--light); border: 1px solid var(--border); max-height: 320px; }
.sp-act .btn-small { font-size: 12px; padding: 5px 10px; }
@media (max-width: 640px) { .sp-edit-grid { grid-template-columns: 1fr; } }
[data-theme="dark"] .sl-dates a, [data-theme="dark"] .sl-views a { background: #1e2025; border-color: #33353c; color: #e5e7eb; }
[data-theme="dark"] .sl-dates a.on, [data-theme="dark"] .sl-views a.on { background: var(--red); border-color: var(--red); color: #fff; }
[data-theme="dark"] .sl-sum span, [data-theme="dark"] .spg-stats span, [data-theme="dark"] .sp-date-row td { background: #2a2c33; }
[data-theme="dark"] .cal-day { background: #1e2025; border-color: #33353c; }
[data-theme="dark"] .cal-day.c-scheduled { background: #172554; }
[data-theme="dark"] .cal-day.c-published { background: #14532d; }
[data-theme="dark"] .cal-day.c-failed { background: #450a0a; }
[data-theme="dark"] .cal-day.c-articles { background: #422006; }
</style>

<div class="page-header">
    <h1>Scheduled Pins <span class="muted">(<?= number_format($total) ?> <?= $filtersOn ? 'found' : 'total' ?>)</span></h1>
    <div style="display:flex; gap:10px;">
        <a href="bulk-schedule" class="btn-secondary">Bulk Scheduler</a>
        <a href="schedule-create" class="btn-primary">+ New Schedule</a>
    </div>
</div>
<div id="alertBox"></div>

<div class="card">
    <div class="sl-views">
        <a href="<?= e($qs(['view' => 'list', 'p' => '', 'm' => ''])) ?>" class="<?= $view === 'list' ? 'on' : '' ?>">☰ List</a>
        <a href="<?= e($qs(['view' => 'pages', 'p' => '', 'm' => ''])) ?>" class="<?= $view === 'pages' ? 'on' : '' ?>">📄 By Pages</a>
        <a href="<?= e($qs(['view' => 'calendar', 'p' => '', 'range' => '', 'from' => '', 'to' => '', 'm' => $month])) ?>" class="<?= $view === 'calendar' ? 'on' : '' ?>">📅 Calendar</a>
    </div>
    <form method="GET" id="slForm">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <?php if ($view === 'calendar'): ?><input type="hidden" name="m" value="<?= e($month) ?>"><?php endif; ?>
        <div class="sl-filters">
            <div class="form-row">
                <label>Website</label>
                <select name="site" onchange="this.form.submit()">
                    <option value="">All websites</option>
                    <?php foreach ($sites as $h => $label): ?>
                        <option value="<?= e($h) ?>" <?= $f['site'] === $h ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Pinterest account</label>
                <select name="account" onchange="this.form.submit()">
                    <option value="">All accounts</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $f['account'] === (int)$a['id'] ? 'selected' : '' ?>><?= e((string)$accountNames[$a['id']]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Status</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (SP_STATUSES as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($view !== 'calendar'): ?>
            <div class="form-row">
                <label>Board</label>
                <select name="board_id" onchange="this.form.submit()">
                    <option value="">All boards</option>
                    <?php foreach ($boards as $b): ?>
                        <option value="<?= e($b['board_id']) ?>" <?= $f['board_id'] === $b['board_id'] ? 'selected' : '' ?>><?= e((string)($b['board_name'] ?: $b['board_id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($view !== 'calendar'): ?>
        <input type="hidden" name="range" id="slRange" value="<?= e($f['range']) ?>">
        <div class="sl-dates">
            <strong style="font-size:13px; margin-right:4px;">Date:</strong>
            <?php foreach (['' => 'All time', 'today' => 'Today', 'yesterday' => 'Yesterday', 'last30' => 'Last 30 days', 'next30' => 'Next 30 days'] as $rk => $rl): ?>
                <a href="<?= e($qs(['range' => $rk, 'from' => '', 'to' => '', 'p' => ''])) ?>" class="<?= $f['range'] === $rk ? 'on' : '' ?>"><?= $rl ?></a>
            <?php endforeach; ?>
            <a href="#" id="slCustomBtn" class="<?= $f['range'] === 'custom' ? 'on' : '' ?>">Custom</a>
            <?php if ($filtersOn): ?><a href="<?= e('schedule-list?view=' . $view) ?>" style="border-style:dashed;">✕ Clear filters</a><?php endif; ?>
        </div>
        <div class="sl-custom" id="slCustom" <?= $f['range'] === 'custom' ? '' : 'hidden' ?>>
            <label for="slFrom" class="muted" style="font-size:13px;">From</label>
            <input type="date" name="from" id="slFrom" value="<?= e($f['from']) ?>">
            <label for="slTo" class="muted" style="font-size:13px;">To</label>
            <input type="date" name="to" id="slTo" value="<?= e($f['to']) ?>">
            <button type="submit" class="btn-primary btn-small" onclick="document.getElementById('slRange').value='custom';">Apply</button>
        </div>
        <?php elseif ($filtersOn): ?>
            <div class="sl-dates"><a href="<?= e('schedule-list?view=calendar&m=' . $month) ?>" style="border-style:dashed;">✕ Clear filters</a></div>
        <?php endif; ?>
    </form>
    <div class="sl-sum">
        <span>⏳ Scheduled: <strong><?= number_format($counts['pending']) ?></strong></span>
        <span>⏸ Stopped: <strong><?= number_format($counts['paused']) ?></strong></span>
        <span>✅ Published: <strong><?= number_format($counts['published']) ?></strong></span>
        <span>⚠️ Failed: <strong><?= number_format($counts['failed']) ?></strong></span>
        <span class="muted">Times in <?= e(str_replace('_', ' ', user_tz())) ?> (<?= e(tz_offset_label(user_tz())) ?>)</span>
    </div>
</div>

<?php if ($view === 'list'): ?>
<div class="card">
    <?php if (empty($pins)): ?>
        <div class="empty-state"><?= $filtersOn ? 'No pins match these filters.' : 'No scheduled pins found.' ?></div>
    <?php else: ?>
    <div class="sl-table-wrap">
    <table>
        <?= sp_table_head() ?>
        <?php $lastDay = null; foreach ($pins as $p):
            $d = server_to_user_dt($p['publish_at']);
            $day = $d ? $d->format('Y-m-d') : '';
            if ($day !== $lastDay): $lastDay = $day; ?>
            <tr class="sp-date-row"><td colspan="8">📅 <?= e($d ? $d->format('l, F j, Y') : 'No date') ?><?= $day === $todayUser ? ' · Today' : '' ?></td></tr>
        <?php endif; echo sp_row_html($p, $accountNames); endforeach; ?>
    </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="sl-pages">
            <?php for ($i = max(1, $pageNum - 4); $i <= min($pages, $pageNum + 4); $i++): ?>
                <a href="<?= e($qs(['p' => $i > 1 ? $i : ''])) ?>" class="<?= $i === $pageNum ? 'btn-primary' : 'btn-secondary' ?> btn-small"><?= $i ?></a>
            <?php endfor; ?>
            <span class="muted" style="align-self:center; font-size:13px;">Page <?= $pageNum ?> of <?= $pages ?></span>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php elseif ($view === 'pages'): ?>
<div class="card">
    <?php if (empty($groups)): ?>
        <div class="empty-state"><?= $filtersOn ? 'No pins match these filters.' : 'No scheduled pins found.' ?></div>
    <?php else: ?>
        <p class="muted" style="margin-top:0;">Each page with the number of pins pointing to it. Click a page to see all its pins.</p>
        <?php foreach ($groups as $g):
            $link = (string)$g['link'];
            $next = $g['next_at'] ? server_to_user_dt($g['next_at']) : null; ?>
        <div class="spg" data-link="<?= e($link) ?>">
            <div class="spg-head" role="button" tabindex="0">
                <span class="spg-caret">▸</span>
                <img src="<?= e(sp_img((string)$g['img'])) ?>" alt="" loading="lazy">
                <div class="spg-info">
                    <div class="spg-title"><?= e((string)($g['title'] ?: ($link !== '' ? $link : 'Pins without a link'))) ?></div>
                    <div class="spg-link"><?= $link !== '' ? e($link) : 'No link' ?><?= $next ? ' · next pin ' . e($next->format('M j, g:i A')) : '' ?></div>
                    <div class="spg-stats" style="margin-top:4px;">
                        <?php if ($g['s_pending']): ?><span>⏳ <?= (int)$g['s_pending'] ?> scheduled</span><?php endif; ?>
                        <?php if ($g['s_paused']): ?><span>⏸ <?= (int)$g['s_paused'] ?> stopped</span><?php endif; ?>
                        <?php if ($g['s_published']): ?><span>✅ <?= (int)$g['s_published'] ?> published</span><?php endif; ?>
                        <?php if ($g['s_failed']): ?><span>⚠️ <?= (int)$g['s_failed'] ?> failed</span><?php endif; ?>
                    </div>
                </div>
                <div class="spg-total"><?= number_format((int)$g['total']) ?> <span class="muted" style="font-size:12px; font-weight:400;">pins</span></div>
            </div>
            <div class="spg-body"><div class="muted" style="padding:10px;">Loading…</div></div>
        </div>
        <?php endforeach; ?>
        <?php if ($pages > 1): ?>
            <div class="sl-pages">
                <?php for ($i = max(1, $pageNum - 4); $i <= min($pages, $pageNum + 4); $i++): ?>
                    <a href="<?= e($qs(['p' => $i > 1 ? $i : ''])) ?>" class="<?= $i === $pageNum ? 'btn-primary' : 'btn-secondary' ?> btn-small"><?= $i ?></a>
                <?php endfor; ?>
                <span class="muted" style="align-self:center; font-size:13px;">Page <?= $pageNum ?> of <?= $pages ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php else: /* calendar */
    $first = new DateTime($month . '-01', $tz);
    $prevM = (clone $first)->modify('-1 month')->format('Y-m');
    $nextM = (clone $first)->modify('+1 month')->format('Y-m');
    $lead = (int)$first->format('N') - 1;   // Monday first
    $daysInMonth = (int)$first->format('t');
?>
<div class="card">
    <div class="cal-nav">
        <a href="<?= e($qs(['m' => $prevM])) ?>" class="btn-secondary btn-small">‹ <?= e((new DateTime($prevM . '-01'))->format('M')) ?></a>
        <h2><?= e($first->format('F Y')) ?></h2>
        <div style="display:flex; gap:6px;">
            <?php if ($month !== substr($todayUser, 0, 7)): ?><a href="<?= e($qs(['m' => substr($todayUser, 0, 7)])) ?>" class="btn-secondary btn-small">Today</a><?php endif; ?>
            <a href="<?= e($qs(['m' => $nextM])) ?>" class="btn-secondary btn-small"><?= e((new DateTime($nextM . '-01'))->format('M')) ?> ›</a>
        </div>
    </div>
    <div class="cal-grid">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?><div class="cal-dow"><?= $dow ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $lead; $i++): ?><div class="cal-day empty"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $ymd = sprintf('%s-%02d', $month, $d);
            $c = $cal[$ymd] ?? [];
            $s = (int)($c['pending'] ?? 0); $pb = (int)($c['published'] ?? 0); $fl = (int)($c['failed'] ?? 0); $ps = (int)($c['paused'] ?? 0);
            $ar = (int)($calArticles[$ymd] ?? 0);
            $has = ($s + $pb + $fl + $ps + $ar) > 0;
            $cls = $fl ? 'c-failed' : ($s || $ps ? 'c-scheduled' : ($pb ? 'c-published' : ($ar ? 'c-articles' : '')));
        ?>
        <div class="cal-day <?= $has ? 'has ' . $cls : '' ?> <?= $ymd === $todayUser ? 'today' : '' ?> <?= ($lead + $d - 1) >= intdiv($lead + $daysInMonth - 1, 7) * 7 ? 'tip-up' : '' ?>" <?= $has ? 'data-day="' . e($ymd) . '" tabindex="0" data-sum="' . e("⏳ $s scheduled · ✅ $pb published · ⚠️ $fl failed" . ($ps ? " · ⏸ $ps stopped" : '') . " · 📝 $ar articles not generated yet") . '"' : '' ?>>
            <div class="cal-num"><?= $d ?></div>
            <?php if ($has): ?>
            <div class="cal-chips">
                <?php if ($s): ?><span class="cal-chip chip-s"><i>⏳</i> <?= $s ?></span><?php endif; ?>
                <?php if ($pb): ?><span class="cal-chip chip-p"><i>✅</i> <?= $pb ?></span><?php endif; ?>
                <?php if ($fl): ?><span class="cal-chip chip-f"><i>⚠️</i> <?= $fl ?></span><?php endif; ?>
                <?php if ($ps): ?><span class="cal-chip chip-x"><i>⏸</i> <?= $ps ?></span><?php endif; ?>
                <?php if ($ar): ?><span class="cal-chip chip-a"><i>📝</i> <?= $ar ?></span><?php endif; ?>
            </div>
            <div class="cal-tip">
                <strong><?= e((new DateTime($ymd))->format('l, M j')) ?></strong><br>
                ⏳ Total scheduled: <strong><?= $s ?></strong><br>
                ✅ Total published: <strong><?= $pb ?></strong><br>
                ⚠️ Failed: <strong><?= $fl ?></strong><br>
                <?php if ($ps): ?>⏸ Stopped: <strong><?= $ps ?></strong><br><?php endif; ?>
                📝 Articles not generated yet: <strong><?= $ar ?></strong>
                <?php if ($s + $pb + $fl + $ps): ?><br><span style="opacity:.75;">Click to see the pins</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
    <div class="cal-legend">
        <span class="cal-chip chip-s">⏳ Scheduled</span><span class="cal-chip chip-p">✅ Published</span>
        <span class="cal-chip chip-f">⚠️ Failed</span><span class="cal-chip chip-x">⏸ Stopped</span>
        <span class="cal-chip chip-a">📝 Articles scheduled, not generated yet (their pins are made after publishing)</span>
    </div>
</div>
<?php endif; ?>

<!-- Day popup (calendar) -->
<div class="modal-overlay sp-modal" id="spDayOverlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header"><h2 id="spDayTitle">Pins</h2><button type="button" class="modal-close" data-close="spDayOverlay">✕</button></div>
        <div id="spDayNote" class="muted" style="font-size:13px; margin-bottom:8px;"></div>
        <div id="spDayBody" style="overflow-x:auto;"></div>
    </div>
</div>

<!-- Edit popup -->
<div class="modal-overlay sp-modal" id="spEditOverlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header"><h2>Edit Pin</h2><button type="button" class="modal-close" data-close="spEditOverlay">✕</button></div>
        <div id="spEditError"></div>
        <form id="spEditForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="pin_id" id="spEditId">
            <div class="sp-edit-grid">
                <div>
                    <img id="spEditImg" src="" alt="">
                    <label class="btn-secondary btn-small" style="display:block; text-align:center; margin-top:8px; cursor:pointer;">
                        🖼 Replace image<input type="file" name="image" id="spEditFile" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                    </label>
                    <p class="muted" id="spEditStatus" style="font-size:12px; margin-top:8px;"></p>
                </div>
                <div>
                    <div class="form-row"><label>Title <span class="muted" id="spTitleCount"></span></label><input type="text" name="title" id="spEditTitle" maxlength="100"></div>
                    <div class="form-row"><label>Description <span class="muted" id="spDescCount"></span></label><textarea name="description" id="spEditDesc" rows="4" maxlength="500"></textarea></div>
                    <div class="form-row"><label>Destination link</label><input type="url" name="link" id="spEditLink" placeholder="https://"></div>
                    <div class="two-col">
                        <div class="form-row"><label>Pinterest account</label><select name="account_id" id="spEditAccount"></select></div>
                        <div class="form-row"><label>Board</label><select name="board" id="spEditBoard"></select></div>
                    </div>
                    <div class="form-row" id="spNewBoardWrap" style="display:none;"><label>New board name</label><input type="text" name="new_board_name" id="spNewBoardName" maxlength="50" placeholder="e.g. White Jeans Outfits"></div>
                    <div class="form-row"><label>Publish date &amp; time <span class="muted">(<?= e(str_replace('_', ' ', user_tz())) ?>)</span></label><input type="datetime-local" name="publish_at" id="spEditWhen"></div>
                    <details><summary class="muted" style="cursor:pointer;">Alt text &amp; keywords</summary>
                        <div class="form-row" style="margin-top:8px;"><label>Alt text</label><input type="text" name="alt" id="spEditAlt" maxlength="500"></div>
                        <div class="form-row"><label>Keywords <span class="muted">(comma-separated)</span></label><input type="text" name="keywords" id="spEditKw" maxlength="500"></div>
                    </details>
                    <div style="display:flex; gap:8px; margin-top:14px;">
                        <button type="submit" class="btn-primary" id="spEditSave">Save</button>
                        <button type="button" class="btn-secondary" data-close="spEditOverlay">Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const FILTERS = <?= json_encode(['site' => $f['site'], 'account' => $f['account'], 'status' => $f['status'], 'board_id' => $f['board_id'], 'range' => $f['range'], 'from' => $f['from'], 'to' => $f['to']]) ?>;
    const $ = id => document.getElementById(id);
    function showAlert(msg, type) {
        $('alertBox').innerHTML = '<div class="alert alert-' + type + '">' + msg.replace(/</g, '&lt;') + '</div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    async function post(data, isForm) {
        const fd = isForm ? data : new FormData();
        if (!isForm) Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        try {
            const res = await fetch('ajax-scheduled-pins', { method: 'POST', body: fd });
            return await res.json();
        } catch (e) { return { ok: false, error: 'Network error.' }; }
    }
    const open = id => { $(id).style.display = 'flex'; };
    const close = id => { $(id).style.display = 'none'; };
    document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => close(b.dataset.close)));
    ['spDayOverlay', 'spEditOverlay'].forEach(id => $(id).addEventListener('click', e => { if (e.target.id === id) close(id); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { close('spEditOverlay'); close('spDayOverlay'); } });

    const custom = $('slCustomBtn');
    if (custom) custom.addEventListener('click', e => { e.preventDefault(); $('slCustom').hidden = false; $('slFrom').focus(); });

    /* ---- Stop / Resume / Edit buttons (also inside loaded popups) ---- */
    document.addEventListener('click', async e => {
        const act = e.target.closest('[data-sp-act]');
        if (act) {
            act.disabled = true;
            const r = await post({ action: act.dataset.spAct, pin_id: act.dataset.id });
            if (r.ok) location.reload(); else { act.disabled = false; alert(r.error || 'Could not update the pin.'); }
            return;
        }
        const ed = e.target.closest('[data-sp-edit]');
        if (ed) openEdit(ed.dataset.spEdit);
    });

    /* ---- By Pages: expand a page to load its pins ---- */
    document.querySelectorAll('.spg-head').forEach(h => {
        const toggle = async () => {
            const g = h.parentElement;
            g.classList.toggle('open');
            if (!g.classList.contains('open') || g.dataset.loaded) return;
            const r = await post(Object.assign({ action: 'page_pins', link: g.dataset.link }, FILTERS));
            g.querySelector('.spg-body').innerHTML = r.ok ? (r.html || '<div class="muted" style="padding:10px;">No pins.</div>') : '<div class="alert alert-error">' + (r.error || 'Error') + '</div>';
            if (r.ok) g.dataset.loaded = '1';
        };
        h.addEventListener('click', toggle);
        h.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });

    /* ---- Calendar: click a day ---- */
    document.querySelectorAll('.cal-day[data-day]').forEach(d => {
        const show = async () => {
            const day = d.dataset.day;
            $('spDayTitle').textContent = new Date(day + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            $('spDayNote').textContent = d.dataset.sum || '';
            $('spDayBody').innerHTML = '<div class="muted">Loading…</div>';
            open('spDayOverlay');
            const r = await post(Object.assign({ action: 'day', date: day }, FILTERS));
            $('spDayBody').innerHTML = r.ok ? (r.html || '<div class="muted">No pins on this day — only articles that are not generated yet.</div>') : '<div class="alert alert-error">' + (r.error || 'Error') + '</div>';
        };
        d.addEventListener('click', show);
        d.addEventListener('keydown', e => { if (e.key === 'Enter') show(); });
    });

    /* ---- Edit popup ---- */
    let currentBoard = 0;
    async function loadBoards(accountId, selectRowId, refresh) {
        const sel = $('spEditBoard');
        sel.innerHTML = '<option value="">Loading…</option>';
        const r = await post({ action: 'boards', account_id: accountId, refresh: refresh ? 1 : '' });
        const boards = r.ok ? r.boards : [];
        sel.innerHTML = boards.map(b => '<option value="row:' + b.id + '">' + b.name.replace(/</g, '&lt;') + (b.pending ? ' (will be created)' : '') + '</option>').join('')
            + '<option value="__new__">+ Create a new board…</option>';
        if (selectRowId && boards.some(b => b.id === selectRowId)) sel.value = 'row:' + selectRowId;
        else if (!boards.length) sel.value = '__new__';
        $('spNewBoardWrap').style.display = sel.value === '__new__' ? '' : 'none';
    }
    $('spEditBoard').addEventListener('change', function () { $('spNewBoardWrap').style.display = this.value === '__new__' ? '' : 'none'; });
    $('spEditAccount').addEventListener('change', function () { loadBoards(this.value, 0); });
    const counter = (inp, out, max) => { const u = () => { $(out).textContent = '(' + $(inp).value.length + '/' + max + ')'; }; $(inp).addEventListener('input', u); return u; };
    const uTitle = counter('spEditTitle', 'spTitleCount', 100);
    const uDesc = counter('spEditDesc', 'spDescCount', 500);
    $('spEditFile').addEventListener('change', function () {
        if (this.files[0]) $('spEditImg').src = URL.createObjectURL(this.files[0]);
    });

    async function openEdit(id) {
        $('spEditError').innerHTML = '';
        $('spEditForm').reset();
        const r = await post({ action: 'get', pin_id: id });
        if (!r.ok) { alert(r.error || 'Could not load the pin.'); return; }
        const p = r.pin;
        $('spEditId').value = p.id;
        $('spEditImg').src = p.image;
        $('spEditTitle').value = p.title;
        $('spEditDesc').value = p.description;
        $('spEditLink').value = p.link;
        $('spEditAlt').value = p.alt;
        $('spEditKw').value = p.keywords;
        $('spEditWhen').value = p.publish_at;
        $('spEditStatus').textContent = p.status === 'paused' ? 'This pin is stopped — it stays stopped after saving. Use Resume to publish it.'
            : (p.status === 'failed' ? 'Failed: ' + (p.last_error || 'unknown error') + ' — saving schedules it again.' : 'Scheduled.');
        $('spEditAccount').innerHTML = r.accounts.map(a => '<option value="' + a.id + '">' + a.name.replace(/</g, '&lt;') + '</option>').join('');
        $('spEditAccount').value = p.account_id;
        uTitle(); uDesc();
        close('spDayOverlay');
        open('spEditOverlay');
        currentBoard = p.board_row_id;
        loadBoards(p.account_id, currentBoard);
    }

    $('spEditForm').addEventListener('submit', async e => {
        e.preventDefault();
        const btn = $('spEditSave');
        btn.disabled = true; btn.textContent = 'Saving…';
        const r = await post(new FormData($('spEditForm')), true);
        btn.disabled = false; btn.textContent = 'Save';
        if (r.ok) { close('spEditOverlay'); showAlert(r.message || 'Saved.', 'success'); setTimeout(() => location.reload(), 700); }
        else $('spEditError').innerHTML = '<div class="alert alert-error">' + (r.error || 'Could not save.').replace(/</g, '&lt;') + '</div>';
    });
})();
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
