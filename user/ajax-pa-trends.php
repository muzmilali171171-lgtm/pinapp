<?php
/**
 * Pinterest Analytics → Trends.
 *   GET  period=3|7|30|90          → per-pin current vs previous window (top 200 pins)
 *   POST action=sync, offset=N     → one step of refreshing per-pin daily history
 */
@set_time_limit(120);
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = pa_trends_sync_step($pdo, $paAccount, max(0, (int)($_POST['offset'] ?? 0)));
    pa_json(['ok' => $r['ok'], 'error' => $r['error'], 'next' => $r['next'], 'total' => $r['total']]);
}

$state = pa_sync_state($pdo, $accountId);
if (empty($state['pins_synced_at'])) {
    pa_json(['ok' => true, 'needs_pins_sync' => true]);
}
$period = in_array((int)($_GET['period'] ?? 7), [3, 7, 30, 90], true) ? (int)$_GET['period'] : 7;
$data = pa_trends_compute($pdo, $accountId, $period);
pa_json([
    'ok' => true,
    'needs_pins_sync' => false,
    'needs_trend_sync' => pa_trends_needs_sync($pdo, $accountId) && empty($_GET['no_sync']),
    'period' => $period,
    'window' => $data['window'],
    'history_days' => $data['history_days'],
    'prev_complete' => $data['prev_complete'],
    'pins' => $data['pins'],
]);
