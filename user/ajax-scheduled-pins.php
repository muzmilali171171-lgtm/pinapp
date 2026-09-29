<?php
/**
 * AJAX backend for user/schedule-list.php:
 *   get        — one pin's full details for the Edit popup (+ the user's accounts)
 *   boards     — boards of a Pinterest account (for the Edit popup's board list)
 *   update     — full edit of a not-yet-published pin: image, title, description, link, alt text,
 *                keywords, account, board (existing or a new one), date & time
 *   pause      — Stop a scheduled pin (the publisher skips it)
 *   resume     — Resume a stopped pin, or retry a failed one
 *   day        — pins of one calendar day (the calendar popup), filters applied
 *   page_pins  — pins of one page link (the "By Pages" view), filters applied
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/scheduled-pins-common.php';

header('Content-Type: application/json');
@set_time_limit(60);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
$user = current_user($pdo);
$uid = (int)$user['id'];
sp_ensure_paused_status($pdo);

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$out = fn(array $a) => exit(json_encode($a));

$loadPin = function (int $id) use ($pdo, $uid): ?array {
    $st = $pdo->prepare("SELECT * FROM scheduled_pins WHERE id = ? AND user_id = ?");
    $st->execute([$id, $uid]);
    return $st->fetch() ?: null;
};
$ownedAccount = function (int $accountId) use ($pdo, $uid): ?array {
    $st = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ? AND user_id = ?");
    $st->execute([$accountId, $uid]);
    return $st->fetch() ?: null;
};
$accountNames = function () use ($pdo, $uid): array {
    $st = $pdo->prepare("SELECT id, pinterest_username FROM pinterest_accounts WHERE user_id = ?");
    $st->execute([$uid]);
    $names = [];
    foreach ($st->fetchAll() as $a) $names[$a['id']] = $a['pinterest_username'] ?: 'Account #' . $a['id'];
    return $names;
};

if ($action === 'get') {
    $pin = $loadPin((int)($_POST['pin_id'] ?? $_GET['pin_id'] ?? 0));
    if (!$pin) $out(['ok' => false, 'error' => 'Pin not found.']);
    $st = $pdo->prepare("SELECT id, pinterest_username, status FROM pinterest_accounts WHERE user_id = ? ORDER BY pinterest_username");
    $st->execute([$uid]);
    $out(['ok' => true, 'pin' => [
        'id' => (int)$pin['id'], 'status' => $pin['status'], 'image' => sp_img((string)$pin['image_path']),
        'title' => (string)$pin['title'], 'description' => (string)$pin['description'], 'link' => (string)$pin['dest_link'],
        'alt' => (string)$pin['alt_text'], 'keywords' => (string)$pin['keywords'],
        'account_id' => (int)$pin['pinterest_account_id'], 'board_row_id' => (int)$pin['board_row_id'],
        'board_name' => (string)$pin['board_name'], 'publish_at' => server_to_user_input($pin['publish_at']),
        'last_error' => $pin['status'] === 'failed' ? user_facing_error((string)$pin['last_error']) : '',
    ], 'accounts' => array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['pinterest_username'] ?: 'Account #' . $a['id'], 'status' => $a['status']], $st->fetchAll())]);
}

if ($action === 'boards') {
    $acc = $ownedAccount((int)($_POST['account_id'] ?? 0));
    if (!$acc) $out(['ok' => false, 'error' => 'Account not found.']);
    // Cached boards first (fast); the live list is fetched only when nothing is cached yet.
    $st = $pdo->prepare("SELECT id, board_name, status FROM pinterest_boards WHERE pinterest_account_id = ? AND status <> 'create_failed' ORDER BY board_name");
    $st->execute([$acc['id']]);
    $rows = $st->fetchAll();
    if (!$rows || !empty($_POST['refresh'])) {
        try {
            $rows = array_values(array_filter(get_boards_for_account($pdo, $acc), fn($b) => $b['status'] !== 'create_failed'));
        } catch (Throwable $e) { /* keep cached */ }
    }
    $out(['ok' => true, 'boards' => array_map(fn($b) => ['id' => (int)$b['id'], 'name' => $b['board_name'], 'pending' => $b['status'] === 'pending_creation'], $rows)]);
}

if ($action === 'update') {
    $pin = $loadPin((int)($_POST['pin_id'] ?? 0));
    if (!$pin) $out(['ok' => false, 'error' => 'Pin not found.']);
    if (!in_array($pin['status'], ['pending', 'paused', 'failed'], true)) {
        $out(['ok' => false, 'error' => $pin['status'] === 'published' ? 'This pin is already published on Pinterest.' : 'This pin is publishing right now — try again in a minute.']);
    }

    $title = pin_enforce_max_chars(trim((string)($_POST['title'] ?? '')), 100);
    $description = pin_enforce_max_chars(trim((string)($_POST['description'] ?? '')), 500);
    $link = trim((string)($_POST['link'] ?? ''));
    if ($link !== '' && !preg_match('~^https?://~i', $link)) $link = 'https://' . $link;
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) $out(['ok' => false, 'error' => 'Please enter a valid link.']);
    $alt = pin_enforce_max_chars(trim((string)($_POST['alt'] ?? '')), 500);
    $keywords = pin_enforce_max_chars(trim((string)($_POST['keywords'] ?? '')), 500);
    $publishAt = user_input_to_server($_POST['publish_at'] ?? '');
    if ($publishAt === '') $out(['ok' => false, 'error' => 'Please choose a valid publish date and time.']);

    // Account + board (an existing board "row:<id>", or a new board created on Pinterest before the pin publishes).
    $accountId = (int)($_POST['account_id'] ?? $pin['pinterest_account_id']);
    if (!$ownedAccount($accountId)) $out(['ok' => false, 'error' => 'Please choose one of your Pinterest accounts.']);
    $boardChoice = (string)($_POST['board'] ?? '');
    if ($boardChoice === '' && $accountId === (int)$pin['pinterest_account_id'] && $pin['board_row_id']) $boardChoice = 'row:' . (int)$pin['board_row_id'];
    $board = resolve_board_selection($pdo, $accountId, $boardChoice, (string)($_POST['new_board_name'] ?? ''), (string)($_POST['new_board_description'] ?? ''));
    if (!$board['ok']) $out(['ok' => false, 'error' => $board['error'] ?: 'Please choose a board.']);

    // Optional new image.
    $imagePath = (string)$pin['image_path'];
    if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) $out(['ok' => false, 'error' => 'The image could not be uploaded (too large?).']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        if (!isset($allowed[$mime])) $out(['ok' => false, 'error' => 'Unsupported image type. Please upload JPG, PNG, WEBP or GIF.']);
        if ($_FILES['image']['size'] > 20 * 1048576) $out(['ok' => false, 'error' => 'Please upload an image under 20 MB.']);
        $destDir = __DIR__ . '/../uploads/pins/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'pin_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destDir . $filename)) $out(['ok' => false, 'error' => 'Failed to save the image.']);
        @chmod($destDir . $filename, 0644);
        $imagePath = 'uploads/pins/' . $filename;
    }

    // A failed pin that is edited goes back into the schedule; a stopped pin stays stopped.
    $newStatus = $pin['status'] === 'failed' ? 'pending' : $pin['status'];
    // (MySQL applies SET left to right: attempts / last_error must read the OLD status, so they come before it.)
    $st = $pdo->prepare("UPDATE scheduled_pins SET
            attempts = IF(status = 'failed', 0, attempts), last_error = IF(status = 'failed', NULL, last_error),
            image_path = ?, title = ?, description = ?, dest_link = ?, alt_text = ?, keywords = ?,
            pinterest_account_id = ?, board_id = ?, board_name = ?, board_row_id = ?, publish_at = ?, status = ?,
            next_retry_at = NULL
        WHERE id = ? AND user_id = ? AND status IN ('pending', 'paused', 'failed')");
    $st->execute([$imagePath, $title ?: null, $description ?: null, $link ?: null, $alt ?: null, $keywords ?: null,
        $accountId, $board['board_id'], $board['board_name'], $board['board_row_id'], $publishAt, $newStatus,
        $pin['id'], $uid]);
    if ($st->rowCount() === 0 && $imagePath === $pin['image_path']) {
        $cur = $loadPin((int)$pin['id']);
        if (!$cur || !in_array($cur['status'], ['pending', 'paused', 'failed'], true)) $out(['ok' => false, 'error' => 'This pin started publishing — it can no longer be edited.']);
    }
    log_event($pdo, 'system', "Scheduled pin #{$pin['id']} edited", $uid);
    $out(['ok' => true, 'message' => $pin['status'] === 'failed' ? 'Saved — the pin is scheduled again.' : 'Saved.']);
}

if ($action === 'pause' || $action === 'resume') {
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [$_POST['pin_id'] ?? 0]))));
    if (!$ids) $out(['ok' => false, 'error' => 'No pin selected.']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    if ($action === 'pause') {
        $st = $pdo->prepare("UPDATE scheduled_pins SET status = 'paused' WHERE user_id = ? AND status = 'pending' AND id IN ($in)");
    } else {
        $st = $pdo->prepare("UPDATE scheduled_pins SET status = 'pending', attempts = IF(status = 'failed', 0, attempts),
                last_error = IF(status = 'failed', NULL, last_error), next_retry_at = NULL, claimed_at = NULL
            WHERE user_id = ? AND status IN ('paused', 'failed') AND id IN ($in)");
    }
    $st->execute(array_merge([$uid], $ids));
    $n = $st->rowCount();
    if ($n === 0) $out(['ok' => false, 'error' => $action === 'pause' ? 'This pin is no longer scheduled (it may be publishing or published).' : 'This pin is not stopped.']);
    log_event($pdo, 'system', ($action === 'pause' ? 'Stopped' : 'Resumed') . " $n scheduled pin(s)", $uid);
    $out(['ok' => true, 'count' => $n]);
}

if ($action === 'day' || $action === 'page_pins') {
    $f = sp_filters($_POST + $_GET);
    [$where, $params] = sp_where($uid, $f, $action === 'page_pins');
    if ($action === 'day') {
        $date = (string)($_POST['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $out(['ok' => false, 'error' => 'Invalid date.']);
        [$a, $b] = user_day_range_server($date);
        $where .= ' AND publish_at >= ? AND publish_at < ?';
        array_push($params, $a, $b);
        $order = 'publish_at ASC';
    } else {
        $link = (string)($_POST['link'] ?? '');
        if ($link === '') { $where .= " AND (dest_link IS NULL OR dest_link = '')"; }
        else { $where .= ' AND dest_link = ?'; $params[] = $link; }
        $order = 'publish_at DESC';
    }
    $st = $pdo->prepare("SELECT * FROM scheduled_pins WHERE $where ORDER BY $order LIMIT 500");
    $st->execute($params);
    $pins = $st->fetchAll();
    $names = $accountNames();
    $html = '';
    foreach ($pins as $p) $html .= sp_row_html($p, $names, $action === 'page_pins');
    $out(['ok' => true, 'count' => count($pins), 'html' => $html === '' ? '' : '<table>' . sp_table_head() . $html . '</table>']);
}

$out(['ok' => false, 'error' => 'Unknown action.']);
