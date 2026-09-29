<?php
/**
 * Shared by user/schedule-list.php (List / By Pages / Calendar views) and user/ajax-scheduled-pins.php:
 * filters → SQL, the pin row markup, and the 'paused' status (Stop / Resume).
 */

/** Adds the 'paused' status to scheduled_pins once (a paused pin is skipped by the publisher until resumed). */
function sp_ensure_paused_status(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../../uploads/.schema_pin_paused_v1';
    if (is_file($flag)) return;
    try {
        $type = (string)$pdo->query("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE()
            AND table_name = 'scheduled_pins' AND column_name = 'status'")->fetchColumn();
        if ($type !== '' && stripos($type, "'paused'") === false) {
            $pdo->exec("ALTER TABLE scheduled_pins MODIFY status ENUM('pending','processing','published','failed','paused') NOT NULL DEFAULT 'pending'");
        }
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* retried next request */ }
}

/** SQL: a pin link's host without "www." (for the website filter). */
function sp_host_expr(string $col = 'dest_link'): string
{
    return "TRIM(LEADING 'www.' FROM LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX($col, '://', -1), '/', 1), '?', 1)))";
}

function sp_norm_host(string $url): string
{
    return preg_replace('/^www\./', '', strtolower((string)parse_url(preg_match('~^https?://~i', $url) ? $url : 'https://' . $url, PHP_URL_HOST)));
}

const SP_STATUSES = ['pending' => 'Scheduled', 'paused' => 'Stopped', 'published' => 'Published', 'failed' => 'Failed'];

/** Reads the filters from GET/POST. */
function sp_filters(array $src): array
{
    $range = in_array($src['range'] ?? '', ['today', 'yesterday', 'last30', 'next30', 'custom'], true) ? $src['range'] : '';
    return [
        'site' => strtolower(trim((string)($src['site'] ?? ''))),
        'account' => max(0, (int)($src['account'] ?? 0)),
        'status' => array_key_exists((string)($src['status'] ?? ''), SP_STATUSES) ? (string)$src['status'] : '',
        'board_id' => trim((string)($src['board_id'] ?? '')),
        'range' => $range,
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($src['from'] ?? '')) ? $src['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($src['to'] ?? '')) ? $src['to'] : '',
    ];
}

/** WHERE clause + params for the filters. $withDates = false ignores the date range (calendar / day popup). */
function sp_where(int $uid, array $f, bool $withDates = true): array
{
    $where = 'user_id = ?';
    $params = [$uid];
    if ($f['board_id'] !== '') { $where .= ' AND board_id = ?'; $params[] = $f['board_id']; }
    if ($f['site'] !== '') { $where .= ' AND ' . sp_host_expr() . ' = ?'; $params[] = $f['site']; }
    if ($f['account'] > 0) { $where .= ' AND pinterest_account_id = ?'; $params[] = $f['account']; }
    if ($f['status'] === 'pending') $where .= " AND status IN ('pending', 'processing')";
    elseif ($f['status'] !== '') { $where .= ' AND status = ?'; $params[] = $f['status']; }
    if ($withDates && $f['range'] !== '') {
        $todayUser = (new DateTime('now', new DateTimeZone(user_tz())))->format('Y-m-d');
        $a = $b = null;
        if ($f['range'] === 'today') [$a, $b] = user_day_range_server($todayUser);
        elseif ($f['range'] === 'yesterday') [$a, $b] = user_day_range_server(date('Y-m-d', strtotime($todayUser . ' -1 day')));
        elseif ($f['range'] === 'last30') [$a, $b] = user_day_range_server(date('Y-m-d', strtotime($todayUser . ' -29 days')), 30);
        elseif ($f['range'] === 'next30') [$a, $b] = user_day_range_server($todayUser, 30);
        elseif ($f['range'] === 'custom') {
            $from = $f['from']; $to = $f['to'];
            if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
            if ($from !== '') $a = user_day_range_server($from)[0];
            if ($to !== '') $b = user_day_range_server($to)[1];
        }
        if ($a) { $where .= ' AND publish_at >= ?'; $params[] = $a; }
        if ($b) { $where .= ' AND publish_at < ?'; $params[] = $b; }
    }
    return [$where, $params];
}

/** Image URL for a pin (hosting path — redirects to the external copy once the hosting copy is removed). */
function sp_img(string $path): string
{
    return preg_match('~^https?://~i', $path) ? $path : '../' . ltrim($path, '/');
}

/** One pin as a table row, with its Edit / Stop / Resume actions. */
function sp_row_html(array $p, array $accountNames, bool $showDate = false): string
{
    $status = (string)$p['status'];
    $host = !empty($p['dest_link']) ? sp_norm_host((string)$p['dest_link']) : '';
    $label = $status === 'processing' ? 'Publishing…' : (SP_STATUSES[$status] ?? ucfirst($status));
    $badge = $status === 'paused' ? 'queued' : $status;
    $when = server_to_user_dt($p['publish_at']);
    $time = $when ? ($showDate ? $when->format('M j, Y · g:i A') : $when->format('g:i A')) : '—';
    $id = (int)$p['id'];

    $actions = [];
    if (in_array($status, ['pending', 'paused', 'failed'], true)) {
        $actions[] = '<button type="button" class="btn-secondary btn-small" data-sp-edit="' . $id . '">✏️ Edit</button>';
    }
    if ($status === 'pending') {
        $actions[] = '<button type="button" class="btn-secondary btn-small" data-sp-act="pause" data-id="' . $id . '">⏸ Stop</button>';
    } elseif ($status === 'paused' || $status === 'failed') {
        $actions[] = '<button type="button" class="btn-secondary btn-small" data-sp-act="resume" data-id="' . $id . '">▶ ' . ($status === 'failed' ? 'Retry' : 'Resume') . '</button>';
    }
    if ($status === 'published' && !empty($p['pinterest_pin_id'])) {
        $actions[] = '<a class="btn-secondary btn-small" target="_blank" rel="noopener" href="https://www.pinterest.com/pin/' . e((string)$p['pinterest_pin_id']) . '/">View ↗</a>';
    }

    $err = '';
    if ($status === 'failed' && !empty($p['last_error'])) {
        $err = '<div class="muted" style="font-size:12px;" title="' . e(user_facing_error((string)$p['last_error'])) . '">error ⓘ</div>';
    }

    return '<tr data-pin-row="' . $id . '">'
        . '<td><img src="' . e(sp_img((string)$p['image_path'])) . '" alt="" loading="lazy" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>'
        . '<td style="min-width:180px;">' . e((string)($p['title'] ?: '(no title)')) . '</td>'
        . '<td>' . ($host !== '' ? '<span class="muted">' . e($host) . '</span>' : '<span class="muted">—</span>') . '</td>'
        . '<td><span class="muted">' . e((string)($accountNames[$p['pinterest_account_id']] ?? '—')) . '</span></td>'
        . '<td>' . e((string)($p['board_name'] ?: ($p['board_id'] ?: 'Being created…'))) . '</td>'
        . '<td style="white-space:nowrap;">' . e($time) . '</td>'
        . '<td><span class="badge badge-' . e($badge) . '">' . e($label) . '</span>' . $err . '</td>'
        . '<td class="sp-act" style="white-space:nowrap;">' . implode(' ', $actions) . '</td>'
        . '</tr>';
}

/** Table header matching sp_row_html(). */
function sp_table_head(): string
{
    return '<tr><th>Image</th><th>Title</th><th>Website</th><th>Account</th><th>Board</th><th>Time</th><th>Status</th><th></th></tr>';
}
