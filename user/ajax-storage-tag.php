<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$id = (int)($_POST['id'] ?? 0);
$tags = trim($_POST['tags'] ?? '');
$tags = implode(', ', array_filter(array_map('trim', explode(',', $tags))));

$stmt = $pdo->prepare("UPDATE storage_images SET tags = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$tags ?: null, $id, $user['id']]);

echo json_encode(['ok' => true]);
