<?php
/**
 * Social login / "Connect account" — Google, Facebook, Microsoft (standard OAuth2
 * authorization-code flow) plus "Login with Pinterest" (reuses the existing Pinterest App
 * credentials in pinterest_settings — see oauth/pinterest-callback.php).
 *
 * All admin-configurable bits (enabled toggle, client id/secret, Firebase reference keys)
 * live in the generic platform_settings key-value store (see includes/platform_functions.php),
 * under keys auth_<provider>_enabled / auth_<provider>_client_id / auth_<provider>_client_secret,
 * auth_password_signup_enabled, auth_pinterest_login_enabled, and auth_firebase_*.
 */

function auth_settings_get(PDO $pdo): array
{
    return [
        'password_signup_enabled' => platform_setting($pdo, 'auth_password_signup_enabled', '1') === '1',
        'google_enabled' => platform_setting($pdo, 'auth_google_enabled', '0') === '1',
        'google_client_id' => platform_setting($pdo, 'auth_google_client_id', ''),
        'google_client_secret' => platform_setting($pdo, 'auth_google_client_secret', ''),
        'facebook_enabled' => platform_setting($pdo, 'auth_facebook_enabled', '0') === '1',
        'facebook_client_id' => platform_setting($pdo, 'auth_facebook_client_id', ''),
        'facebook_client_secret' => platform_setting($pdo, 'auth_facebook_client_secret', ''),
        'microsoft_enabled' => platform_setting($pdo, 'auth_microsoft_enabled', '0') === '1',
        'microsoft_client_id' => platform_setting($pdo, 'auth_microsoft_client_id', ''),
        'microsoft_client_secret' => platform_setting($pdo, 'auth_microsoft_client_secret', ''),
        'pinterest_login_enabled' => platform_setting($pdo, 'auth_pinterest_login_enabled', '0') === '1',
        'firebase_api_key' => platform_setting($pdo, 'auth_firebase_api_key', ''),
        'firebase_project_id' => platform_setting($pdo, 'auth_firebase_project_id', ''),
        'firebase_app_id' => platform_setting($pdo, 'auth_firebase_app_id', ''),
        'firebase_sender_id' => platform_setting($pdo, 'auth_firebase_sender_id', ''),
    ];
}

function oauth_provider_meta(string $provider): ?array
{
    switch ($provider) {
        case 'google':
            return [
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'userinfo_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
                'scope' => 'openid email profile',
            ];
        case 'facebook':
            return [
                'authorize_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
                'userinfo_url' => 'https://graph.facebook.com/me?fields=id,name,email',
                'scope' => 'email public_profile',
            ];
        case 'microsoft':
            return [
                'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'userinfo_url' => 'https://graph.microsoft.com/oidc/userinfo',
                'scope' => 'openid email profile',
            ];
        default:
            return null;
    }
}

function oauth_redirect_uri(string $provider): string
{
    return rtrim(APP_URL, '/') . '/oauth/' . $provider . '-callback.php';
}

/** $purpose is 'login' (visitor signing in / signing up with this provider) or 'connect'
 *  (an already-logged-in user linking the provider to their existing account). */
function oauth_build_authorize_url(PDO $pdo, string $provider, string $purpose): ?string
{
    $meta = oauth_provider_meta($provider);
    if (!$meta) return null;
    $settings = auth_settings_get($pdo);
    $clientId = $settings[$provider . '_client_id'] ?? '';
    if (!($settings[$provider . '_enabled'] ?? false) || $clientId === '') return null;

    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_' . $provider] = $token;
    $state = $token . '|' . $purpose;

    $params = [
        'client_id' => $clientId,
        'redirect_uri' => oauth_redirect_uri($provider),
        'response_type' => 'code',
        'scope' => $meta['scope'],
        'state' => $state,
    ];
    if ($provider === 'google') $params['access_type'] = 'online';
    return $meta['authorize_url'] . '?' . http_build_query($params);
}

/** Verifies the callback's state and returns the purpose ('login'/'connect'), or null if invalid/expired. */
function oauth_verify_state(string $provider, string $state): ?string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    [$token, $purpose] = array_pad(explode('|', $state, 2), 2, '');
    $expected = $_SESSION['oauth_state_' . $provider] ?? null;
    unset($_SESSION['oauth_state_' . $provider]);
    if (!$expected || !$token || !hash_equals($expected, $token)) return null;
    return in_array($purpose, ['login', 'connect'], true) ? $purpose : 'login';
}

function oauth_exchange_code(PDO $pdo, string $provider, string $code): array
{
    $meta = oauth_provider_meta($provider);
    $settings = auth_settings_get($pdo);
    $body = [
        'client_id' => $settings[$provider . '_client_id'] ?? '',
        'client_secret' => $settings[$provider . '_client_secret'] ?? '',
        'code' => $code,
        'redirect_uri' => oauth_redirect_uri($provider),
        'grant_type' => 'authorization_code',
    ];
    $result = platform_http('POST', $meta['token_url'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query($body));
    $data = is_array($result['data']) ? $result['data'] : [];
    return ['ok' => $result['ok'] && !empty($data['access_token']), 'access_token' => $data['access_token'] ?? null, 'error' => $data['error_description'] ?? $data['error'] ?? ($result['ok'] ? null : ($result['error'] ?? 'Token exchange failed'))];
}

/** Returns ['id'=>string,'email'=>?string,'name'=>?string] or null. */
function oauth_fetch_userinfo(string $provider, string $accessToken): ?array
{
    $meta = oauth_provider_meta($provider);
    $result = platform_http('GET', $meta['userinfo_url'], ['Authorization: Bearer ' . $accessToken]);
    $data = $result['data'];
    if (!$result['ok'] || !is_array($data) || empty($data['id'])) return null;
    return [
        'id' => (string)$data['id'],
        'email' => $data['email'] ?? null,
        'name' => $data['name'] ?? $data['given_name'] ?? null,
    ];
}

/**
 * Finds or creates the local user for a social-login profile, links the connection, logs
 * them in (sets $_SESSION['user_id']), and returns the user row. Matches an existing user
 * by email if one exists (so "sign up with Google" on an email that already has a password
 * account just links + logs into that same account); otherwise creates a new user with a
 * random password (the account is only ever accessed via this provider going forward, unless
 * the user later sets a password from Settings).
 */
function oauth_login_or_register(PDO $pdo, string $provider, array $profile): array
{
    $stmt = $pdo->prepare("SELECT user_id FROM user_oauth_connections WHERE provider = ? AND provider_user_id = ?");
    $stmt->execute([$provider, $profile['id']]);
    $userId = $stmt->fetchColumn();

    if (!$userId && !empty($profile['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$profile['email']]);
        $userId = $stmt->fetchColumn();
    }

    if (!$userId) {
        $email = $profile['email'] ?: (strtolower($provider) . '_' . $profile['id'] . '@no-email.invalid');
        $name = $profile['name'] ?: ucfirst($provider) . ' User';
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();
        if (function_exists('team_activate_pending_invites')) {
            team_activate_pending_invites($pdo, (int)$userId, $email);
        }
        if (function_exists('assign_free_plan_to_new_user')) {
            assign_free_plan_to_new_user($pdo, (int)$userId);
        }
        if (function_exists('affiliate_attach_referral')) {
            affiliate_attach_referral($pdo, (int)$userId);
        }
        log_event($pdo, 'system', "New user registered via $provider login: $email", (int)$userId);
    }

    $pdo->prepare("INSERT INTO user_oauth_connections (user_id, provider, provider_user_id, provider_email)
        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider_user_id = VALUES(provider_user_id), provider_email = VALUES(provider_email)")
        ->execute([$userId, $provider, $profile['id'], $profile['email']]);

    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user_id'] = (int)$userId;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/** Connected social accounts for a logged-in user (for the Settings → Account tab). */
function get_user_oauth_connections(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT provider, provider_email, connected_at FROM user_oauth_connections WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $byProvider = [];
    foreach ($rows as $r) $byProvider[$r['provider']] = $r;
    return $byProvider;
}

function oauth_disconnect(PDO $pdo, int $userId, string $provider): void
{
    try {
        $pdo->prepare("DELETE FROM user_oauth_connections WHERE user_id = ? AND provider = ?")->execute([$userId, $provider]);
    } catch (Throwable $e) {
        // no-op — nothing to disconnect if the table isn't there
    }
}

/**
 * "Login with Pinterest" reuses the existing Pinterest App credentials (pinterest_settings —
 * the same ones used for pin publishing) but its own state key, so it never collides with the
 * "Connect Pinterest" flow used from user/connect-pinterest.php. Requires
 * includes/functions.php (pinterest_build_authorize_url) to already be loaded.
 */
function pinterest_login_authorize_url(PDO $pdo): ?string
{
    $authSettings = auth_settings_get($pdo);
    if (!$authSettings['pinterest_login_enabled'] || !function_exists('pinterest_build_authorize_url')) return null;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = bin2hex(random_bytes(16));
    $_SESSION['pinterest_login_oauth_state'] = $token;
    return pinterest_build_authorize_url($pdo, 'login:' . $token);
}

/**
 * Shared logic for oauth/{google,facebook,microsoft}-callback.php — handles both
 * 'login' (sign in / sign up via this provider) and 'connect' (link to the currently
 * logged-in user's account, from Settings → Account) and redirects when done.
 */
function oauth_handle_callback(PDO $pdo, string $provider): void
{
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;
    $loginUrl = rtrim(APP_URL, '/') . '/auth/login';

    $fail = function (string $message) use ($loginUrl) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['oauth_error'] = $message;
        redirect($loginUrl);
    };

    $purpose = $state ? oauth_verify_state($provider, (string)$state) : null;

    if ($error) $fail(ucfirst($provider) . " returned an error: $error");
    if (!$code || !$purpose) $fail('Invalid or expired sign-in attempt. Please try again.');

    $tokenResult = oauth_exchange_code($pdo, $provider, (string)$code);
    if (!$tokenResult['ok']) $fail(ucfirst($provider) . ' sign-in failed: ' . ($tokenResult['error'] ?? 'unknown error'));

    $profile = oauth_fetch_userinfo($provider, (string)$tokenResult['access_token']);
    if (!$profile) $fail("Could not read your $provider profile. Please try again.");

    if ($purpose === 'connect') {
        require_login();
        $user = current_user($pdo);
        $pdo->prepare("INSERT INTO user_oauth_connections (user_id, provider, provider_user_id, provider_email)
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider_user_id = VALUES(provider_user_id), provider_email = VALUES(provider_email)")
            ->execute([$user['id'], $provider, $profile['id'], $profile['email']]);
        log_event($pdo, 'oauth', ucfirst($provider) . ' account connected', $user['id']);
        redirect(rtrim(APP_URL, '/') . '/user/settings?tab=account');
    }

    oauth_login_or_register($pdo, $provider, $profile);
    redirect(rtrim(APP_URL, '/') . '/user/dashboard');
}
