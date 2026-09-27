<?php
/**
 * AJAX backend for user/batches.php — deletes a draft batch (a batch that
 * was never scheduled, so it has no scheduled_pins rows to worry about).
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

$batch = get_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    echo json_encode(['ok' => false, 'error' => 'Batch not found.']);
    exit;
}
if ($batch['status'] !== 'draft') {
    echo json_encode(['ok' => false, 'error' => 'Only draft batches can be deleted here.']);
    exit;
}

$pdo->prepare("DELETE FROM pin_batches WHERE id = ?")->execute([$batch['id']]);
echo json_encode(['ok' => true]);
