<?php
/**
 * AJAX backend for user/bulk-schedule.php's "Create Board" -> "Create with AI"
 * option — takes a keyword and returns a suggested board name + description.
 */
@set_time_limit(60);
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$mode = trim($_POST['mode'] ?? 'single');

if ($mode === 'batch_assign') {
    // "Multiple Boards" mode in the Bulk Pin Scheduler's "Create with AI" panel: given a
    // batch of pin titles and the account's existing board names, decide per-pin whether an
    // existing board fits or a brand-new one should be drafted.
    $titles = json_decode($_POST['titles'] ?? '[]', true);
    $existingBoards = json_decode($_POST['existing_boards'] ?? '[]', true);
    if (!is_array($titles)) $titles = [];
    if (!is_array($existingBoards)) $existingBoards = [];

    $result = ai_assign_boards_batch($pdo, $titles, $existingBoards);
    if ($result['ok']) {
        log_event($pdo, 'ai', 'Bulk pin AI assigned boards for ' . count($titles) . ' pin(s)', $user['id']);
    }
    echo json_encode($result);
    exit;
}

$keyword = trim($_POST['keyword'] ?? '');

$result = ai_generate_board_suggestion($pdo, $keyword);

if ($result['ok']) {
    log_event($pdo, 'ai', 'Bulk pin AI writer suggested a board name/description', $user['id']);
}

echo json_encode($result);
