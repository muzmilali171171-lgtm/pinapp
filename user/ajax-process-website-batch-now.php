<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';

@set_time_limit(150);
ini_set('display_errors', '0');
header('Content-Type: application/json');

register_shutdown_function(function () {
    global $pdo, $currentPageId;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!empty($currentPageId) && isset($pdo)) {
            try {
                $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")
                    ->execute(['Server error: ' . $error['message'], $currentPageId]);
            } catch (Throwable $e) { /* best-effort */ }
        }
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message'], 'done' => false]);
    }
});

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$batchId = trim($_POST['batch_id'] ?? '');

$batch = get_website_pin_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    echo json_encode(['ok' => false, 'error' => 'Schedule not found.']);
    exit;
}
if ($batch['status'] !== 'active') {
    echo json_encode(['ok' => false, 'error' => 'This schedule is stopped.', 'done' => true]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM website_pin_pages WHERE batch_id = ? AND status IN ('queued','generating_text','generating_images','ready') ORDER BY id ASC LIMIT 1");
$stmt->execute([$batch['id']]);
$page = $stmt->fetch();

if (!$page) {
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'No pages left to process right now.']);
    exit;
}

$currentPageId = $page['id'];
$result = process_website_pin_page_step($pdo, $page, $batch);

echo json_encode([
    'ok' => $result['ok'],
    'done' => false,
    'more' => $result['more'] ?? false,
    'page_id' => $page['id'],
    'url' => $page['page_url'],
    'error' => $result['error'] ?? null,
]);
