<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$stmt = $pdo->prepare("SELECT * FROM storage_images WHERE user_id = ? AND source = 'website_scan' ORDER BY source_page_url ASC, created_at DESC");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$groups = [];
foreach ($rows as $row) {
    $page = $row['source_page_url'] ?: 'Unknown page';
    if (!isset($groups[$page])) $groups[$page] = ['page_url' => $page, 'images' => []];
    $groups[$page]['images'][] = ['id' => (int)$row['id'], 'url' => storage_image_url($row)];
}

echo json_encode(['ok' => true, 'groups' => array_values($groups)]);
