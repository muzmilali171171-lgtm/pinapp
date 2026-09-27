<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(60);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
$user = current_user($pdo);

function cw_owned_account(PDO $pdo, int $accountId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
    $stmt->execute([$accountId, $userId]);
    return $stmt->fetch() ?: null;
}

$action = trim($_POST['action'] ?? 'list');
$accountId = (int)($_POST['account_id'] ?? 0);
$account = cw_owned_account($pdo, $accountId, $user['id']);
if (!$account) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid connected Pinterest account.']);
    exit;
}

if ($action === 'list') {
    $rows = get_boards_for_account($pdo, $account);
    echo json_encode(['ok' => true, 'boards' => array_map(fn($b) => [
        'id' => (int)$b['id'], 'name' => $b['board_name'], 'description' => $b['board_description'],
        'status' => $b['status'],
    ], $rows)]);
    exit;
}

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $useAi = !empty($_POST['use_ai']);
    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Please enter a board name.']);
        exit;
    }
    if ($useAi && $description === '') {
        $suggestion = ai_generate_board_suggestion($pdo, $name);
        if ($suggestion['ok']) $description = $suggestion['description'];
    }
    $pdo->prepare("INSERT INTO pinterest_boards (pinterest_account_id, board_id, board_name, board_description, status) VALUES (?, NULL, ?, ?, 'pending_creation')")
        ->execute([$accountId, $name, $description]);
    $boardRowId = (int)$pdo->lastInsertId();
    log_event($pdo, 'system', "Classic Wizard: board \"$name\" queued for creation", $user['id']);
    echo json_encode(['ok' => true, 'board' => ['id' => $boardRowId, 'name' => $name, 'description' => $description, 'status' => 'pending_creation']]);
    exit;
}

if ($action === 'create_csv') {
    if (empty($_FILES['csv']['tmp_name'])) {
        echo json_encode(['ok' => false, 'error' => 'Please choose a CSV file.']);
        exit;
    }
    $text = file_get_contents($_FILES['csv']['tmp_name']);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', (string)$text);
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $created = [];
    $skipped = 0;
    foreach ($lines as $i => $line) {
        if (trim($line) === '') continue;
        $cols = str_getcsv($line);
        $name = trim($cols[0] ?? '');
        $description = trim($cols[1] ?? '');
        if ($i === 0 && strtolower($name) === 'name') continue; // header row
        if ($name === '') { $skipped++; continue; }
        $pdo->prepare("INSERT INTO pinterest_boards (pinterest_account_id, board_id, board_name, board_description, status) VALUES (?, NULL, ?, ?, 'pending_creation')")
            ->execute([$accountId, $name, $description]);
        $created[] = ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'description' => $description, 'status' => 'pending_creation'];
    }
    log_event($pdo, 'system', 'Classic Wizard: ' . count($created) . ' board(s) queued for creation via CSV', $user['id']);
    echo json_encode(['ok' => true, 'boards' => $created, 'skipped' => $skipped]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
