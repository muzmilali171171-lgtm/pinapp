<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$ids = array_filter(array_map('intval', json_decode($_POST['ids'] ?? '[]', true) ?: []));
if (empty($ids)) { echo json_encode(['ok' => false, 'error' => 'Nothing selected.']); exit; }

$ph = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM storage_images WHERE id IN ($ph) AND user_id = ?");
$stmt->execute([...array_values($ids), $user['id']]);
$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    storage_delete_image($pdo, $row);
}

echo json_encode(['ok' => true, 'deleted' => count($rows)]);
