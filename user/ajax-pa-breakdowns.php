<?php
/**
 * Pinterest Analytics → Breakdowns. Returns the account's own pins (with lifetime and
 * last-90-day metrics) plus board names; the page groups them by Board / URL / Keyword /
 * Title length / Description length / Time in the browser, so switching tabs, periods,
 * sorting and the "Min Total Pins" filter is instant.
 *   GET refresh_boards=1 → also re-reads board names from Pinterest
 */
@set_time_limit(90);
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];
$state = pa_sync_state($pdo, $accountId);
if (empty($state['pins_synced_at'])) pa_json(['ok' => true, 'needs_pins_sync' => true]);

$stmt = $pdo->prepare("SELECT pin_id, title, description, link, image_url, board_id, pin_created_at,
        imp_90, clicks_90, outbound_90, saves_90, imp_life, clicks_life, outbound_life, saves_life
    FROM pa_pins WHERE pinterest_account_id = ? AND is_own = 1 ORDER BY imp_90 DESC LIMIT 10000");
$stmt->execute([$accountId]);
$rows = $stmt->fetchAll();

// Board names come from the local board cache; read them from Pinterest when asked, or
// when pins point at boards we have never seen.
$boardNames = function () use ($pdo, $accountId) {
    $b = $pdo->prepare("SELECT board_id, board_name FROM pinterest_boards WHERE pinterest_account_id = ? AND board_id IS NOT NULL");
    $b->execute([$accountId]);
    return array_column($b->fetchAll(), 'board_name', 'board_id');
};
$boards = $boardNames();
$missing = false;
foreach ($rows as $r) {
    if ($r['board_id'] && !isset($boards[$r['board_id']])) { $missing = true; break; }
}
if (!empty($_GET['refresh_boards']) || ($missing && empty($_SESSION['pa_boards_fetched'][$accountId]))) {
    pinterest_fetch_boards($pdo, $paAccount);
    $_SESSION['pa_boards_fetched'][$accountId] = 1; // don't hammer the API on every load
    $boards = $boardNames();
}

$toUtc = function (?string $local) {
    if (!$local) return null;
    try {
        $dt = new DateTime($local, new DateTimeZone(date_default_timezone_get()));
        return $dt->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
};
$n = fn($v) => $v === null ? null : (int)$v;

pa_json([
    'ok' => true,
    'needs_pins_sync' => false,
    'synced_at' => format_datetime($state['pins_synced_at']),
    'username' => (string)($paAccount['pinterest_username'] ?? ''),
    'boards' => $boards,
    'pins' => array_map(fn($p) => [
        'pin_id' => $p['pin_id'],
        'title' => (string)$p['title'],
        'description' => (string)$p['description'],
        'link' => (string)$p['link'],
        'image_url' => (string)$p['image_url'],
        'board_id' => (string)$p['board_id'],
        'created_ts' => $toUtc($p['pin_created_at']),
        'url' => 'https://www.pinterest.com/pin/' . rawurlencode($p['pin_id']) . '/',
        // [views, pin clicks, outbound clicks, saves] — lifetime is null for pins Pinterest has no lifetime stats for
        'm90' => [(int)$p['imp_90'], (int)$p['clicks_90'], (int)$p['outbound_90'], $n($p['saves_90'])],
        'mlife' => $p['imp_life'] === null ? null : [(int)$p['imp_life'], $n($p['clicks_life']), $n($p['outbound_life']), $n($p['saves_life'])],
    ], $rows),
]);
