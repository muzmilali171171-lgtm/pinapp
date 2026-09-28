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

// Only one process works on a batch at a time. If its background worker is already on it,
// there is nothing to do here — the worker keeps going on its own.
$batchLock = website_pin_batch_lock_try((int)$batch['id']);
if (!$batchLock) {
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'This schedule is already creating pins in the background — refresh in a minute to see progress.']);
    exit;
}

$page = next_due_page_for_batch($pdo, (int)$batch['id']);
if (!$page) {
    website_pin_batch_lock_release($batchLock);
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'No pages left to process right now.']);
    exit;
}
// Each user can run only a set number of batches at once (Admin → All Pins Scheduled → Batch Limits).
if ($page['status'] === 'queued' && !website_pin_batch_allowed($pdo, (int)$batch['id'])) {
    website_pin_batch_lock_release($batchLock);
    $limit = website_pin_batch_limit_for_user(website_pin_batch_limits_all($pdo), (int)$batch['user_id']);
    echo json_encode(['ok' => true, 'done' => true, 'message' => "You already have $limit schedule(s) running at once — this one starts automatically as soon as one of them finishes."]);
    exit;
}

$currentPageId = $page['id'];
$result = process_website_pin_page_step($pdo, $page, $batch);
website_pin_batch_lock_release($batchLock);

echo json_encode([
    'ok' => $result['ok'],
    'done' => false,
    'more' => $result['more'] ?? false,
    'page_id' => $page['id'],
    'url' => $page['page_url'],
    'error' => $result['error'] ?? null,
]);
