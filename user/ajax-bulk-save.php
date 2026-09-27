<?php
/**
 * AJAX backend for user/bulk-schedule.php's "Schedule All Pins" button.
 * Resolves the account/board (creating a locally-drafted board row if the
 * user chose "+ Create New Board"), then inserts one scheduled_pins row
 * per pin in the batch, all tagged with the same batch_id, and upserts the
 * batch's pin_batches row (converting a draft into an active/scheduled batch
 * if the user was resuming one).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);

$payload = json_decode($_POST['payload'] ?? '', true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$accountId = (int)($payload['pinterest_account_id'] ?? 0);
$boardChoice = trim($payload['board_choice'] ?? '');
$newBoardName = trim($payload['new_board_name'] ?? '');
$newBoardDescription = trim($payload['new_board_description'] ?? '');
$batchName = trim($payload['batch_name'] ?? '');
$draftBatchId = trim($payload['batch_id'] ?? '');
$globalTags = trim($payload['tags'] ?? '');       // comma-separated, applied to every pin
$globalKeywords = trim($payload['keywords'] ?? ''); // comma-separated, applied to every pin unless a row has its own
$pins = $payload['pins'] ?? [];

if (!is_array($pins) || empty($pins)) {
    echo json_encode(['ok' => false, 'error' => 'No pins to schedule.']);
    exit;
}
if (count($pins) > 200) {
    echo json_encode(['ok' => false, 'error' => 'Please schedule at most 200 pins per batch.']);
    exit;
}

$limitCheck = check_pin_scheduling_limit($pdo, (int)$user['id'], count($pins));
if (!$limitCheck['allowed']) {
    echo json_encode(['ok' => false, 'error' => $limitCheck['message']]);
    exit;
}

// Confirm the account belongs to this user and is connected.
$stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
$stmt->execute([$accountId, $user['id']]);
$account = $stmt->fetch();
if (!$account) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid connected Pinterest account.']);
    exit;
}

// Batch-level ("default") board. It's OPTIONAL when every pin already has its own board
// (Multiple Boards with AI / "Board for this pin" select or create) — that was the cause of
// the "Please name the new board." error.
$pinHasOwnBoard = function (array $p): bool {
    $c = trim($p['board_choice'] ?? '');
    if ($c === '__pin_new__') return trim($p['new_board_name'] ?? '') !== '';
    return strpos($c, 'row:') === 0;
};
$allPinsHaveBoard = true;
foreach ($pins as $p) { if (!$pinHasOwnBoard($p)) { $allPinsHaveBoard = false; break; } }

$emptyBoard = ['ok' => true, 'board_row_id' => null, 'board_id' => null, 'board_name' => null, 'error' => null];
$batchDefaultUsable = $boardChoice !== '' && !($boardChoice === '__new__' && $newBoardName === '');
if ($batchDefaultUsable) {
    $boardResolved = resolve_board_selection($pdo, $accountId, $boardChoice, $newBoardName, $newBoardDescription);
    if (!$boardResolved['ok']) {
        if (!$allPinsHaveBoard) {
            echo json_encode(['ok' => false, 'error' => $boardResolved['error']]);
            exit;
        }
        $boardResolved = $emptyBoard;
    }
} elseif ($allPinsHaveBoard) {
    $boardResolved = $emptyBoard;
} else {
    $missing = [];
    foreach ($pins as $i => $p) { if (!$pinHasOwnBoard($p)) $missing[] = $i + 1; }
    echo json_encode(['ok' => false, 'error' => 'Pin(s) #' . implode(', #', array_slice($missing, 0, 10)) . ' have no board. Choose a batch board above, or set "Board for this pin" on them.']);
    exit;
}

// Validate every pin before inserting any of them.
$uploadsDir = realpath(__DIR__ . '/../uploads/pins/');
foreach ($pins as $i => $p) {
    $imagePath = trim($p['image_path'] ?? '');
    $publishAt = trim($p['publish_at'] ?? '');
    if ($imagePath === '') {
        echo json_encode(['ok' => false, 'error' => 'Pin #' . ($i + 1) . ' is missing an image.']);
        exit;
    }
    // Guard against a tampered/foreign path — every image must actually live in our own uploads/pins folder.
    $real = realpath(__DIR__ . '/../' . $imagePath);
    if (!$real || !$uploadsDir || strpos($real, $uploadsDir) !== 0) {
        echo json_encode(['ok' => false, 'error' => 'Pin #' . ($i + 1) . ' has an invalid image.']);
        exit;
    }
    if ($publishAt === '' || strtotime($publishAt) === false) {
        echo json_encode(['ok' => false, 'error' => 'Pin #' . ($i + 1) . ' is missing a valid publish time.']);
        exit;
    }
}

$batchId = null; // created after the first pin's board is known (so a batch with only per-pin boards still gets a board label)

$stmt = $pdo->prepare("INSERT INTO scheduled_pins
    (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, tags, keywords, product_link, publish_at, source, batch_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bulk', ?)");

// Per-pin board overrides can reference an EXISTING board (value "row:<id>") or, for pins
// the "Create with AI" -> "Multiple Boards" assignment couldn't match to anything, a
// brand-new board of their own ("__pin_new__", with that pin's own new_board_name/
// new_board_description) — unlike the batch-level "__new__", this can draft a different new
// board per pin since resolve_board_selection() inserts a fresh pending_creation row every
// time it's called with '__new__'. Existing-board lookups are cached by choice value so
// picking the same board for many rows doesn't re-query each time; per-pin new boards are
// never cached since each one is its own distinct board.
$boardCache = [];
function resolve_pin_board(PDO $pdo, int $accountId, array $pin, array $batchDefault, array &$cache): array
{
    $choice = trim($pin['board_choice'] ?? '');

    if ($choice === '__pin_new__') {
        $name = trim($pin['new_board_name'] ?? '');
        if ($name === '') return $batchDefault;
        $resolved = resolve_board_selection($pdo, $accountId, '__new__', $name, trim($pin['new_board_description'] ?? ''));
        return $resolved['ok'] ? $resolved : $batchDefault;
    }

    if ($choice === '' || $choice === '__new__' || strpos($choice, 'row:') !== 0) {
        return $batchDefault;
    }
    if (isset($cache[$choice])) return $cache[$choice];
    $resolved = resolve_board_selection($pdo, $accountId, $choice, '', '');
    $cache[$choice] = $resolved['ok'] ? $resolved : $batchDefault;
    return $cache[$choice];
}

$count = 0;
$insertedIds = [];
$resolvedBoards = [];
foreach ($pins as $i => $p) {
    $pinBoard = resolve_pin_board($pdo, $accountId, $p, $boardResolved, $boardCache);
    if (empty($pinBoard['board_row_id']) && empty($pinBoard['board_id'])) {
        echo json_encode(['ok' => false, 'error' => 'Pin #' . ($i + 1) . ' has no board. Choose a board for it and try again.']);
        exit;
    }
    $resolvedBoards[$i] = $pinBoard;
}
$labelBoard = $boardResolved['board_name'] ? $boardResolved : $resolvedBoards[0];
$distinctBoards = count(array_unique(array_map(fn($b) => (string)$b['board_name'], $resolvedBoards)));
$batchLabel = $boardResolved['board_name'] ?: ($distinctBoards > 1 ? $distinctBoards . ' boards' : $labelBoard['board_name']);
$batchId = activate_batch($pdo, $user['id'], $draftBatchId ?: null, $batchName, $accountId, $boardResolved['board_row_id'] ?: $labelBoard['board_row_id'], $batchLabel);

foreach ($pins as $i => $p) {
    $publishAt = date('Y-m-d H:i:s', strtotime(trim($p['publish_at'])));
    $rowKeywords = trim($p['keywords'] ?? '') ?: $globalKeywords;
    $pinBoard = $resolvedBoards[$i];
    $stmt->execute([
        $user['id'], $accountId,
        $pinBoard['board_id'], $pinBoard['board_name'], $pinBoard['board_row_id'],
        trim($p['image_path'] ?? ''),
        pin_enforce_max_chars(trim($p['title'] ?? ''), 100) ?: null,
        pin_enforce_max_chars(trim($p['description'] ?? ''), 500) ?: null,
        trim($p['link'] ?? '') ?: null,
        pin_enforce_max_chars(trim($p['alt'] ?? ''), 500) ?: null,
        $globalTags ?: null,
        $rowKeywords ?: null,
        trim($p['product_link'] ?? '') ?: null,
        $publishAt,
        $batchId,
    ]);
    $insertedIds[] = (int)$pdo->lastInsertId();
    $count++;
}

$pdo->prepare("UPDATE pin_batches SET total_pins = total_pins + ? WHERE batch_id = ?")->execute([$count, $batchId]);

log_event($pdo, 'system', "Bulk-scheduled $count pins to board '{$batchLabel}' (batch $batchId)", $user['id']);

// Pins whose time has ALREADY passed are published right now; the rest stay scheduled.
$overdue = ['published' => 0, 'failed' => 0, 'left' => 0];
$nowTs = time();
$hasOverdue = false;
foreach ($pins as $p) { if (strtotime(trim($p['publish_at'])) <= $nowTs) { $hasOverdue = true; break; } }
if ($hasOverdue) {
    ignore_user_abort(true);
    $overdue = publish_overdue_pins_now($pdo, ['ids' => $insertedIds, 'user_id' => (int)$user['id']], 10);
}

echo json_encode(['ok' => true, 'count' => $count, 'batch_id' => $batchId,
    'published_now' => $overdue['published'], 'failed_now' => $overdue['failed'], 'overdue_queued' => $overdue['left']]);
