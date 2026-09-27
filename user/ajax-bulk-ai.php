<?php
/**
 * AJAX backend for user/bulk-schedule.php's "Create with AI" panel — takes a
 * list of main keywords (one per pin) and returns a generated title +
 * description for each, in order.
 */
@set_time_limit(300);
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);

$keywordsJson = $_POST['keywords'] ?? '[]';
$keywords = json_decode($keywordsJson, true);
if (!is_array($keywords)) $keywords = [];
$withTags = ($_POST['with_tags'] ?? '0') === '1';
$destLink = trim($_POST['dest_link'] ?? '');
$customPrompt = trim($_POST['custom_prompt'] ?? '');

// Cap batch size to keep any single request well inside shared-hosting time limits.
// ai_generate_pin_batch() itself now chunks internally in groups of 10 (see
// includes/ai_functions.php), so this is just an outer safety ceiling — the
// "Create with AI" panel in bulk-schedule also chunks its own calls in
// groups of 10 for live progress, so in normal use this limit is never hit.
if (count($keywords) > 200) {
    $keywords = array_slice($keywords, 0, 200);
}

$result = ai_generate_pin_batch($pdo, $keywords, $withTags, $destLink, $customPrompt, (int)$user['id']);

if ($result['ok']) {
    log_event($pdo, 'ai', 'Bulk pin AI writer generated ' . count($result['items']) . ' title/description pairs', $user['id']);
}

echo json_encode($result);