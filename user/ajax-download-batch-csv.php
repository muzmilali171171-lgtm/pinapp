<?php
/**
 * Downloads all of one batch's pins as Pinterest's own bulk-upload CSV
 * format — lets the user upload directly to Pinterest's native bulk pin
 * tool as a backup/alternative to this app's own auto-publishing.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$batchId = trim($_GET['batch_id'] ?? '');

$batch = get_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    http_response_code(404);
    die('Batch not found.');
}

$stmt = $pdo->prepare("SELECT title, image_path, board_name, description, dest_link, publish_at, keywords
    FROM scheduled_pins WHERE batch_id = ? AND user_id = ? ORDER BY publish_at ASC");
$stmt->execute([$batchId, $user['id']]);
$pins = $stmt->fetchAll();

$filename = 'pins-' . preg_replace('/[^a-z0-9]+/i', '-', $batch['name'] ?: $batchId) . '.csv';
output_pinterest_bulk_csv($pins, $filename);
