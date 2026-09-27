<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/page_crawler_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(60);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$url = trim($_POST['url'] ?? '');
$websiteId = (int)($_POST['website_id'] ?? 0) ?: null;

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid website URL.']);
    exit;
}
if ($websiteId) {
    $stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND user_id = ?");
    $stmt->execute([$websiteId, $user['id']]);
    if (!$stmt->fetch()) $websiteId = null;
}

$result = scan_website_for_pages($pdo, $user['id'], $url, $websiteId);
log_event($pdo, 'system', "Scanned $url — {$result['pages_added']} page(s) added", $user['id']);

echo json_encode($result);
