<?php
/**
 * AJAX backend for user/bulk-schedule.php's autosave — persists the whole
 * builder state (settings + rows, minus nothing sensitive) as a JSON
 * snapshot on a 'draft' pin_batches row, so the user's work is never lost
 * and can be resumed later from the Batches list.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);

$batchId = trim($_POST['batch_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$state = $_POST['state'] ?? '';

$decoded = json_decode($state, true);
if (!is_array($decoded)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid draft data.']);
    exit;
}

// Don't bother persisting an entirely empty, untouched builder.
$hasRows = !empty($decoded['rows']) && is_array($decoded['rows']);
if (!$hasRows && $name === '') {
    echo json_encode(['ok' => true, 'batch_id' => $batchId ?: null, 'skipped' => true]);
    exit;
}

ensure_db_connection($pdo);

try {
    $savedBatchId = save_batch_draft($pdo, $user['id'], $batchId ?: null, $name, json_encode($decoded));
    echo json_encode(['ok' => true, 'batch_id' => $savedBatchId, 'saved_at' => date('c')]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Could not save draft right now.']);
}
