<?php
/**
 * Shared bootstrap for the Pinterest Analytics AJAX endpoints (user/ajax-pa-*.php):
 * JSON output, login check, CSRF check on POST, and the selected account ($paAccount),
 * which must belong to the logged-in user.
 */
ini_set('display_errors', '0');
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/platform_functions.php';
require_once __DIR__ . '/../../includes/pricing_functions.php';
require_once __DIR__ . '/../../includes/team_functions.php';
require_once __DIR__ . '/../../includes/pinterest_analytics_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (empty($paSkipJsonHeader)) header('Content-Type: application/json');

function pa_json(array $data): void
{
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    pa_json(['ok' => false, 'error' => 'Please log in again.']);
}
$user = current_user($pdo);
if (!$user) {
    http_response_code(401);
    pa_json(['ok' => false, 'error' => 'Please log in again.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(403);
    pa_json(['ok' => false, 'error' => 'Your session expired. Please reload the page and try again.']);
}

$paAccountId = (int)($_REQUEST['account_id'] ?? 0);
$paAccount = $paAccountId ? pa_get_account($pdo, (int)$user['id'], $paAccountId) : null;
if (!$paAccount) {
    pa_json(['ok' => false, 'error' => 'Please select one of your connected Pinterest accounts.']);
}
if ($paAccount['status'] !== 'connected') {
    pa_json(['ok' => false, 'error' => 'This Pinterest account needs to be reconnected (Pinterest Accounts page).']);
}
