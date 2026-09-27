<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(90);
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$user = current_user($pdo);

$prompt = trim($_POST['prompt'] ?? '');
$size = trim($_POST['size'] ?? '2:3');
$quality = in_array($_POST['quality'] ?? '', ['budget', 'high', 'ultra'], true) ? $_POST['quality'] : 'budget';

if ($prompt === '') { echo json_encode(['ok' => false, 'error' => 'Please enter a prompt.']); exit; }

$articleSettings = get_article_settings($pdo) ?: [];
$imgSettings = article_image_provider_settings($pdo, $articleSettings, $quality, 'content');

if (get_user_image_credits($pdo, $user['id']) < $imgSettings['cost']) {
    echo json_encode(['ok' => false, 'error' => 'Your image AI credits are low — upgrade your plan to get more.']);
    exit;
}

[$w, $h] = pin_image_size_dims($size);
$result = generate_plain_article_image($pdo, $prompt, $w, $h, $imgSettings['provider'], $imgSettings['model'], $imgSettings['iterations']);
if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

deduct_image_credits($pdo, $user['id'], $imgSettings['cost']);
$saveResult = storage_save_image($pdo, $user['id'], $result['data'], 'ai-generated.jpg', 'image/jpeg', 'ai', mb_substr($prompt, 0, 100));
log_event($pdo, 'ai', 'Storage AI Generation: ' . mb_substr($prompt, 0, 80), $user['id']);

echo json_encode($saveResult);
