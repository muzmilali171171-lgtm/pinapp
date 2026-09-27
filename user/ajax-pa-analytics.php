<?php
/**
 * Pinterest Analytics → Analytics tab data.
 * GET account_id, range (7|14|30|90|custom), start, end (Y-m-d, custom only), refresh=1
 * Returns the day-by-day series for the range + the same-length previous period, totals,
 * and % change for the stat cards.
 */
@set_time_limit(60);
require_once __DIR__ . '/includes/pa-ajax.php';

$range = $_GET['range'] ?? '30';
$today = date('Y-m-d');

if ($range === 'custom') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        pa_json(['ok' => false, 'error' => 'Please pick a start and end date.']);
    }
    if ($start > $end) [$start, $end] = [$end, $start];
    if ($end > $today) $end = $today;
    $days = (int)round((strtotime($end) - strtotime($start)) / 86400) + 1;
    if ($days > 366) pa_json(['ok' => false, 'error' => 'Please pick a range of one year or less.']);
} else {
    $days = in_array((int)$range, [7, 14, 30, 90], true) ? (int)$range : 30;
    $end = $today;
    $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
}
$prevEnd = date('Y-m-d', strtotime($start . ' -1 day'));
$prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));

$refresh = pa_refresh_account_daily($pdo, $paAccount, !empty($_GET['refresh']));

$current = pa_account_series($pdo, (int)$paAccount['id'], $start, $end);
$previous = pa_account_series($pdo, (int)$paAccount['id'], $prevStart, $prevEnd);
$curT = pa_series_totals($current);
$prevT = pa_series_totals($previous);

if (!$refresh['ok'] && $curT['coverage'] == 0) {
    pa_json(['ok' => false, 'error' => $refresh['error']]);
}

// Counts: relative % change. Rates: percentage-point difference. Only when the previous
// period has enough history to compare against (Pinterest keeps 90 days; older days only
// exist here if this page was used back then).
$hasPrev = $prevT['coverage'] >= 0.8;
$change = function (string $key, bool $isRate) use ($curT, $prevT, $hasPrev) {
    if (!$hasPrev) return null;
    if ($isRate) return round($curT[$key] - $prevT[$key], 2);
    if ($prevT[$key] == 0) return $curT[$key] > 0 ? null : 0.0;
    return round(($curT[$key] - $prevT[$key]) / $prevT[$key] * 100, 1);
};

$state = pa_sync_state($pdo, (int)$paAccount['id']);
pa_json([
    'ok' => true,
    'range' => ['start' => $start, 'end' => $end, 'days' => $days, 'prev_start' => $prevStart, 'prev_end' => $prevEnd],
    'current' => $current,
    'previous' => $previous,
    'totals' => $curT,
    'previous_totals' => $prevT,
    'has_previous' => $hasPrev,
    'change' => [
        'impressions' => $change('impressions', false),
        'pin_clicks' => $change('pin_clicks', false),
        'outbound_clicks' => $change('outbound_clicks', false),
        'saves' => $change('saves', false),
        'outbound_click_rate' => $change('outbound_click_rate', true),
        'save_rate' => $change('save_rate', true),
    ],
    'warning' => $refresh['ok'] ? null : $refresh['error'],
    'synced_at' => $state['daily_synced_at'] ? format_datetime($state['daily_synced_at']) : null,
]);
