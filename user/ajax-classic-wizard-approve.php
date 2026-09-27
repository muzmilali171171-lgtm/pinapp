<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
$user = current_user($pdo);

$pageId = (int)($_POST['page_id'] ?? 0);
$approve = !empty($_POST['approve']);

$result = approve_website_pin_page($pdo, $pageId, (int)$user['id'], $approve);
echo json_encode($result);
