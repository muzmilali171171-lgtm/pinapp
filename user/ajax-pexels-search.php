<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }

$query = trim($_POST['query'] ?? '');
$page = max(1, (int)($_POST['page'] ?? 1));
if ($query === '') { echo json_encode(['ok' => false, 'error' => 'Please enter a search term.']); exit; }

$result = pexels_search($pdo, $query, $page, 10);
echo json_encode($result);
