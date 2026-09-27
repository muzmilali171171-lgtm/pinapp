<?php
/**
 * Top Pins sync, one step per call so no request runs long:
 *   POST step=pins, page=N, bookmark=…   → one page (100) of the account's own pins
 *   POST step=finish                     → merge Pinterest's top-pins report, clean up
 */
@set_time_limit(120);
require_once __DIR__ . '/includes/pa-ajax.php';

$step = $_POST['step'] ?? 'pins';
if ($step === 'finish') {
    $r = pa_sync_top_pins_and_finish($pdo, $paAccount);
    pa_json(['ok' => true, 'done' => true, 'total' => $r['total']]);
}

$page = max(1, (int)($_POST['page'] ?? 1));
$bookmark = trim($_POST['bookmark'] ?? '') ?: null;
$r = pa_sync_pins_page($pdo, $paAccount, $bookmark, $page);
if (!$r['ok']) pa_json(['ok' => false, 'error' => $r['error']]);
pa_json(['ok' => true, 'done' => false, 'bookmark' => $r['bookmark'], 'count' => $r['count'], 'page' => $page]);
