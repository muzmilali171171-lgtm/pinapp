<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(30);
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$url = trim($_POST['url'] ?? $_POST['image_url'] ?? '');
$tags = trim($_POST['tags'] ?? '');
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid image URL.']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36']);
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($data === false || $httpCode >= 300) {
    echo json_encode(['ok' => false, 'error' => 'Could not download that image.']);
    exit;
}
if (!$contentType || stripos($contentType, 'image/') !== 0) {
    echo json_encode(['ok' => false, 'error' => 'That URL does not point to an image.']);
    exit;
}

$filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg';
$result = storage_save_image($pdo, $user['id'], $data, $filename, $contentType, 'url', $tags ?: null);
echo json_encode($result);
