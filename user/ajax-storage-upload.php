<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'No file received.']);
    exit;
}
$tmpPath = $_FILES['file']['tmp_name'];
$origName = $_FILES['file']['name'];
$mime = mime_content_type($tmpPath);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Only JPEG, PNG, WEBP or GIF images are allowed.']);
    exit;
}
$data = file_get_contents($tmpPath);
$result = storage_save_image($pdo, $user['id'], $data, $origName, $mime, 'upload');
echo json_encode($result);
