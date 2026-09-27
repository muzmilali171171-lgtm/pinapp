<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/free_tool_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(90);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
$user = current_user($pdo);

$url = trim($_POST['url'] ?? '');
$templateOptions = free_tool_template_options();
$requestedStyles = array_values(array_intersect((array)($_POST['styles'] ?? []), array_keys($templateOptions)));
if (empty($requestedStyles)) {
    $settings = free_tool_get_settings($pdo);
    $requestedStyles = array_slice(array_keys($templateOptions), 0, max(1, min(6, $settings['template_set_count'] + 2)));
}

$settings = free_tool_get_settings($pdo);
$result = free_tool_generate_pins($pdo, $url, $settings, $requestedStyles);
if ($result['ok']) {
    log_event($pdo, 'ai', 'Classic Wizard: previewed pin templates for ' . $result['source_url'], $user['id']);
}
echo json_encode($result);
