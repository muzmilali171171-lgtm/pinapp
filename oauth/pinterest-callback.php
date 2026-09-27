<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/oauth_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/../includes/auth.php';

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

// "Login with Pinterest" (Admin → User Setting toggle) shares this same registered
// redirect URI but uses its own state prefix, so it's handled separately here — the
// visitor doesn't need to already be logged in for this branch.
if ($state && strpos((string)$state, 'login:') === 0) {
    $token = substr((string)$state, 6);
    if (session_status() === PHP_SESSION_NONE) session_start();
    $expected = $_SESSION['pinterest_login_oauth_state'] ?? null;
    unset($_SESSION['pinterest_login_oauth_state']);
    $loginUrl = rtrim(APP_URL, '/') . '/auth/login';

    if ($error || !$expected || !hash_equals($expected, $token)) {
        $_SESSION['oauth_error'] = $error ? "Pinterest returned an error: $error" : 'Invalid or expired sign-in attempt. Please try again.';
        redirect($loginUrl);
    }

    $result = pinterest_exchange_code($pdo, (string)$code);
    if (!$result['ok'] || empty($result['data']['access_token'])) {
        $_SESSION['oauth_error'] = 'Pinterest sign-in failed. Please try again.';
        redirect($loginUrl);
    }
    $profileResp = pinterest_http_request('GET', PINTEREST_API_BASE . '/user_account', ['Authorization: Bearer ' . $result['data']['access_token']]);
    $pinterestUserId = $profileResp['data']['id'] ?? null;
    $username = $profileResp['data']['username'] ?? null;
    if (!$pinterestUserId) {
        $_SESSION['oauth_error'] = 'Could not read your Pinterest profile. Please try again.';
        redirect($loginUrl);
    }
    $loggedIn = oauth_login_or_register($pdo, 'pinterest', ['id' => (string)$pinterestUserId, 'email' => null, 'name' => $username]);

    // The sign-in already granted boards/pins scopes — connect this same Pinterest account
    // (tokens saved, status 'connected') so it shows under Pinterest Accounts immediately.
    $store = pinterest_store_connected_account($pdo, (int)$loggedIn['id'], $result['data'], (string)$pinterestUserId, $username, true);
    if ($store['ok']) {
        log_event($pdo, 'oauth', 'Pinterest account connected via Pinterest login (' . ($username ?: $pinterestUserId) . ')', (int)$loggedIn['id']);
        if ($store['new']) {
            $_SESSION['oauth_success'] = 'Your Pinterest account' . ($username ? ' @' . $username : '') . ' is connected and ready for scheduling.';
            redirect(rtrim(APP_URL, '/') . '/user/connect-pinterest');
        }
    } else {
        log_event($pdo, 'oauth', 'Pinterest login OK but account not connected: ' . $store['error'], (int)$loggedIn['id']);
        $_SESSION['oauth_error'] = $store['error'];
        redirect(rtrim(APP_URL, '/') . '/user/connect-pinterest');
    }
    redirect(rtrim(APP_URL, '/') . '/user/dashboard');
}

require_login();
$user = current_user($pdo);

function fail_and_redirect(PDO $pdo, int $userId, string $message) {
    log_event($pdo, 'oauth', $message, $userId);
    $_SESSION['oauth_error'] = $message;
    redirect(rtrim(APP_URL, '/') . '/user/connect-pinterest');
}

if ($error) {
    fail_and_redirect($pdo, $user['id'], "Pinterest returned an error: $error");
}

if (!$code || !$state || empty($_SESSION['pinterest_oauth_state']) || $state !== $_SESSION['pinterest_oauth_state']) {
    fail_and_redirect($pdo, $user['id'], 'Invalid or expired OAuth state. Please try connecting again.');
}
unset($_SESSION['pinterest_oauth_state']);

$result = pinterest_exchange_code($pdo, $code);

if (!$result['ok'] || empty($result['data']['access_token'])) {
    fail_and_redirect($pdo, $user['id'], 'Token exchange failed: ' . json_encode($result['data'] ?? $result['error']));
}

$accessToken = $result['data']['access_token'];

// Fetch the Pinterest account's own profile so we can show a friendly name.
$profile = pinterest_http_request('GET', PINTEREST_API_BASE . '/user_account', [
    'Authorization: Bearer ' . $accessToken,
]);
$pinterestUserId = $profile['data']['id'] ?? null;
$pinterestUsername = $profile['data']['username'] ?? null;

if (!$pinterestUserId) {
    // A NULL pinterest_user_id would create a broken "connected" account — stop here instead.
    fail_and_redirect($pdo, $user['id'], 'Could not read your Pinterest profile after authorizing. Please make sure the app has the "user_accounts:read" scope and try connecting again.');
}

// Insert or refresh (re-authorizing an existing account updates the same row; plan limit only for NEW accounts).
$store = pinterest_store_connected_account($pdo, (int)$user['id'], $result['data'], (string)$pinterestUserId, $pinterestUsername, false);
if (!$store['ok']) {
    fail_and_redirect($pdo, $user['id'], $store['error']);
}
$_SESSION['oauth_success'] = 'Pinterest account' . ($pinterestUsername ? ' @' . $pinterestUsername : '') . ' connected.';

log_event($pdo, 'oauth', "Pinterest account connected (" . ($pinterestUsername ?: $pinterestUserId) . ")", $user['id']);

redirect(rtrim(APP_URL, '/') . '/user/connect-pinterest');
