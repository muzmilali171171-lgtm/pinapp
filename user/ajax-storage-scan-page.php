<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(60);
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$pageUrl = trim($_POST['page_url'] ?? '');
if ($pageUrl === '' || !filter_var($pageUrl, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid page URL.']);
    exit;
}

$scan = scan_page_for_images($pageUrl);
if (!$scan['ok']) {
    echo json_encode(['ok' => false, 'error' => $scan['error']]);
    exit;
}
if (empty($scan['images'])) {
    echo json_encode(['ok' => false, 'error' => 'No images found on that page.']);
    exit;
}

$added = 0;
foreach (array_slice($scan['images'], 0, 60) as $imgUrl) {
    if (get_user_storage_used($pdo, $user['id']) >= STORAGE_QUOTA_BYTES) break;
    $ch = curl_init($imgUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36']);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($data === false || $httpCode >= 300 || !$contentType || stripos($contentType, 'image/') !== 0) continue;

    $filename = basename(parse_url($imgUrl, PHP_URL_PATH)) ?: 'image.jpg';
    $result = storage_save_image($pdo, $user['id'], $data, $filename, $contentType, 'website_scan', null, $pageUrl);
    if ($result['ok']) $added++;
}

log_event($pdo, 'system', "Scanned $pageUrl for images — $added added", $user['id']);
echo json_encode(['ok' => $added > 0, 'added' => $added, 'error' => $added > 0 ? null : 'Could not download any images from that page.']);
