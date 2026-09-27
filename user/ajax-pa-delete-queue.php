<?php
/**
 * Delete Underperforming Pins → Deletion Queue.
 *   GET                              → queued/failed pins + recent deletion history
 *   POST action=queue,  pin_ids=[…]  → add pins to the queue
 *   POST action=remove, ids=[…]      → take queue rows out (pins stay on Pinterest)
 *   POST action=delete, id=N         → permanently delete one queued pin on Pinterest
 *   POST action=clear_history        → forget the "deleted" history rows
 */
@set_time_limit(60);
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];
$uid = (int)$user['id'];

function pa_json_ids(string $key): array
{
    $v = json_decode((string)($_POST[$key] ?? '[]'), true);
    return is_array($v) ? array_values(array_filter(array_map('strval', $v), fn($x) => $x !== '')) : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q = $pdo->prepare("SELECT * FROM pa_delete_queue WHERE pinterest_account_id = ? AND status IN ('queued','failed') ORDER BY queued_at DESC");
    $q->execute([$accountId]);
    $h = $pdo->prepare("SELECT * FROM pa_delete_queue WHERE pinterest_account_id = ? AND status = 'deleted' ORDER BY deleted_at DESC LIMIT 100");
    $h->execute([$accountId]);
    $map = fn($r) => [
        'id' => (int)$r['id'], 'pin_id' => $r['pin_id'], 'title' => $r['title'] ?: '', 'image_url' => $r['image_url'] ?: '',
        'link' => $r['link'] ?: '', 'impressions' => $r['impressions'] === null ? null : (int)$r['impressions'],
        'outbound' => $r['outbound_clicks'] === null ? null : (int)$r['outbound_clicks'], 'saves' => $r['saves'] === null ? null : (int)$r['saves'],
        'status' => $r['status'], 'error' => $r['last_error'],
        'queued_at' => format_datetime($r['queued_at']), 'deleted_at' => $r['deleted_at'] ? format_datetime($r['deleted_at']) : null,
        'url' => 'https://www.pinterest.com/pin/' . rawurlencode($r['pin_id']) . '/',
    ];
    pa_json(['ok' => true, 'queue' => array_map($map, $q->fetchAll()), 'history' => array_map($map, $h->fetchAll())]);
}

$action = $_POST['action'] ?? '';

if ($action === 'queue') {
    $ids = array_slice(pa_json_ids('pin_ids'), 0, 5000);
    if (!$ids) pa_json(['ok' => false, 'error' => 'Select at least one pin.']);
    $sel = $pdo->prepare("SELECT * FROM pa_pins WHERE pinterest_account_id = ? AND pin_id = ? AND is_own = 1");
    $ins = $pdo->prepare("INSERT INTO pa_delete_queue (user_id, pinterest_account_id, pin_id, title, image_url, link, impressions, outbound_clicks, saves, status, last_error, queued_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', NULL, ?)
        ON DUPLICATE KEY UPDATE status = IF(status = 'deleted', 'deleted', 'queued'), last_error = NULL, queued_at = VALUES(queued_at)");
    $now = date('Y-m-d H:i:s');
    $added = 0;
    foreach ($ids as $pinId) {
        $sel->execute([$accountId, $pinId]);
        $p = $sel->fetch();
        if (!$p) continue; // only the account's own, known pins can be queued
        $ins->execute([$uid, $accountId, $pinId, $p['title'], $p['image_url'], $p['link'],
            $p['imp_life'] ?? $p['imp_90'], $p['outbound_life'] ?? $p['outbound_90'], $p['saves_life'] ?? $p['saves_90'], $now]);
        $added++;
    }
    log_event($pdo, 'system', "Pinterest Analytics: $added pin(s) added to the deletion queue", $uid);
    pa_json(['ok' => true, 'added' => $added, 'queue_count' => pa_delete_queue_count($pdo, $accountId)]);
}

if ($action === 'remove') {
    $ids = array_map('intval', pa_json_ids('ids'));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM pa_delete_queue WHERE pinterest_account_id = ? AND status IN ('queued','failed') AND id IN ($in)")
            ->execute(array_merge([$accountId], $ids));
    }
    pa_json(['ok' => true, 'queue_count' => pa_delete_queue_count($pdo, $accountId)]);
}

if ($action === 'clear_history') {
    $pdo->prepare("DELETE FROM pa_delete_queue WHERE pinterest_account_id = ? AND status = 'deleted'")->execute([$accountId]);
    pa_json(['ok' => true]);
}

if ($action === 'delete') {
    $stmt = $pdo->prepare("SELECT * FROM pa_delete_queue WHERE id = ? AND pinterest_account_id = ? AND status IN ('queued','failed')");
    $stmt->execute([(int)($_POST['id'] ?? 0), $accountId]);
    $row = $stmt->fetch();
    if (!$row) pa_json(['ok' => false, 'error' => 'This pin is no longer in the queue.']);

    $r = pa_delete_pin_api($pdo, $paAccount, $row['pin_id']);
    ensure_db_connection($pdo);
    if ($r['ok']) {
        $pdo->prepare("UPDATE pa_delete_queue SET status = 'deleted', deleted_at = ?, last_error = NULL WHERE id = ?")->execute([date('Y-m-d H:i:s'), $row['id']]);
        $pdo->prepare("DELETE FROM pa_pins WHERE pinterest_account_id = ? AND pin_id = ?")->execute([$accountId, $row['pin_id']]);
        $pdo->prepare("DELETE FROM pa_pin_daily WHERE pinterest_account_id = ? AND pin_id = ?")->execute([$accountId, $row['pin_id']]);
        log_event($pdo, 'system', "Pinterest Analytics: deleted pin {$row['pin_id']} from Pinterest", $uid);
        pa_json(['ok' => true, 'queue_count' => pa_delete_queue_count($pdo, $accountId)]);
    }
    $pdo->prepare("UPDATE pa_delete_queue SET status = 'failed', last_error = ? WHERE id = ?")->execute([$r['error'], $row['id']]);
    pa_json(['ok' => false, 'error' => $r['error']]);
}

pa_json(['ok' => false, 'error' => 'Unknown action.']);
