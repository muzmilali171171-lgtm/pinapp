<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/oauth_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'user-settings';
$pageTitle = 'User Setting';

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_setting_set($pdo, 'auth_password_signup_enabled', isset($_POST['password_signup_enabled']) ? '1' : '0');

    foreach (['google', 'facebook', 'microsoft'] as $p) {
        platform_setting_set($pdo, "auth_{$p}_enabled", isset($_POST["{$p}_enabled"]) ? '1' : '0');
        platform_setting_set($pdo, "auth_{$p}_client_id", trim($_POST["{$p}_client_id"] ?? ''));
        $secret = trim($_POST["{$p}_client_secret"] ?? '');
        if ($secret !== '') platform_setting_set($pdo, "auth_{$p}_client_secret", $secret);
    }
    platform_setting_set($pdo, 'auth_pinterest_login_enabled', isset($_POST['pinterest_login_enabled']) ? '1' : '0');

    platform_setting_set($pdo, 'auth_firebase_api_key', trim($_POST['firebase_api_key'] ?? ''));
    platform_setting_set($pdo, 'auth_firebase_project_id', trim($_POST['firebase_project_id'] ?? ''));
    platform_setting_set($pdo, 'auth_firebase_app_id', trim($_POST['firebase_app_id'] ?? ''));
    platform_setting_set($pdo, 'auth_firebase_sender_id', trim($_POST['firebase_sender_id'] ?? ''));

    log_event($pdo, 'system', 'Admin updated User Setting (login/signup methods)');
    $saved = true;
}

$s = auth_settings_get($pdo);
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>User Setting</h1></div>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<form method="POST">
    <div class="card">
        <h2>Sign-up &amp; Login Methods</h2>
        <p class="muted">Turn any of these on to show them as sign-up/login options on the public Sign Up and Log In pages.
        Leave a provider off if you don't want to configure its app credentials yet.</p>

        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Signup with password and email</div>
                <div class="settings-toggle-desc">The classic email + password form.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="password_signup_enabled" value="1" <?= $s['password_signup_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>

        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Signup / Login with Pinterest</div>
                <div class="settings-toggle-desc">Reuses the Pinterest App credentials already set under Pinterest Settings — no extra keys needed here.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="pinterest_login_enabled" value="1" <?= $s['pinterest_login_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>

        <div class="settings-toggle-row">
            <div>
                <div class="settings-toggle-label">Google login</div>
                <div class="settings-toggle-desc">Requires a Google Client ID/Secret below.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="google_enabled" value="1" <?= $s['google_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Google Client ID</label>
                <input type="text" name="google_client_id" value="<?= e($s['google_client_id']) ?>">
            </div>
            <div class="form-row">
                <label>Google Client Secret</label>
                <input type="password" name="google_client_secret" placeholder="<?= $s['google_client_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Redirect URI (add this exact URL in Google Cloud Console → Credentials)</label>
            <input type="text" readonly value="<?= e(oauth_redirect_uri('google')) ?>" onclick="this.select();">
        </div>

        <div class="settings-toggle-row" style="margin-top:10px;">
            <div>
                <div class="settings-toggle-label">Facebook login</div>
                <div class="settings-toggle-desc">Requires a Facebook App ID/Secret below.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="facebook_enabled" value="1" <?= $s['facebook_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Facebook App ID</label>
                <input type="text" name="facebook_client_id" value="<?= e($s['facebook_client_id']) ?>">
            </div>
            <div class="form-row">
                <label>Facebook App Secret</label>
                <input type="password" name="facebook_client_secret" placeholder="<?= $s['facebook_client_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Redirect URI (add under Facebook Login → Settings → Valid OAuth Redirect URIs)</label>
            <input type="text" readonly value="<?= e(oauth_redirect_uri('facebook')) ?>" onclick="this.select();">
        </div>

        <div class="settings-toggle-row" style="margin-top:10px;">
            <div>
                <div class="settings-toggle-label">Microsoft login</div>
                <div class="settings-toggle-desc">Requires a Microsoft (Azure AD) Application ID/Secret below.</div>
            </div>
            <label class="toggle-pill"><input type="checkbox" name="microsoft_enabled" value="1" <?= $s['microsoft_enabled'] ? 'checked' : '' ?>><span class="toggle-pill-slider"></span></label>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Microsoft Application (client) ID</label>
                <input type="text" name="microsoft_client_id" value="<?= e($s['microsoft_client_id']) ?>">
            </div>
            <div class="form-row">
                <label>Microsoft Client Secret</label>
                <input type="password" name="microsoft_client_secret" placeholder="<?= $s['microsoft_client_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Redirect URI (add under App registrations → Authentication → Redirect URIs)</label>
            <input type="text" readonly value="<?= e(oauth_redirect_uri('microsoft')) ?>" onclick="this.select();">
        </div>
    </div>

    <div class="card">
        <h2>Firebase Setup Guide</h2>
        <p class="muted">If you'd rather run authentication through Firebase instead of (or alongside) the built-in
        providers above, these reference fields save your Firebase Web App config for use in any custom integration
        you add later — they aren't required for the Google/Facebook/Microsoft/Pinterest logins above, which work
        standalone.</p>
        <ol class="muted">
            <li>Create a project at <a href="https://console.firebase.google.com/" target="_blank" rel="noopener">console.firebase.google.com</a>.</li>
            <li>Go to <strong>Project settings → General</strong>, scroll to "Your apps", and add a Web app.</li>
            <li>Copy the <code>apiKey</code>, <code>projectId</code>, <code>appId</code> and <code>messagingSenderId</code> from the shown config object into the fields below.</li>
            <li>Under <strong>Authentication → Sign-in method</strong>, enable the providers you want (Google, Facebook, etc.) — note Facebook/Microsoft sign-in inside Firebase still needs their own App ID/Secret from Meta/Azure, same as above.</li>
        </ol>
        <div class="two-col">
            <div class="form-row">
                <label>Firebase API Key</label>
                <input type="text" name="firebase_api_key" value="<?= e($s['firebase_api_key']) ?>">
            </div>
            <div class="form-row">
                <label>Firebase Project ID</label>
                <input type="text" name="firebase_project_id" value="<?= e($s['firebase_project_id']) ?>">
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Firebase App ID</label>
                <input type="text" name="firebase_app_id" value="<?= e($s['firebase_app_id']) ?>">
            </div>
            <div class="form-row">
                <label>Firebase Messaging Sender ID</label>
                <input type="text" name="firebase_sender_id" value="<?= e($s['firebase_sender_id']) ?>">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-primary">Save User Setting</button>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
