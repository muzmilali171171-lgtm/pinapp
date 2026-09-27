<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/oauth_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seo_functions.php';
require_once __DIR__ . '/../includes/affiliate_functions.php';
require_once __DIR__ . '/../includes/auth_layout.php';

if (current_user($pdo)) {
    redirect(rtrim(APP_URL, '/') . '/user/dashboard');
}

// A shared affiliate link can also point straight at this signup page
// (?ref=CODE) rather than the homepage — log that click too.
if (!empty($_GET['ref'])) {
    affiliate_track_click($pdo, trim((string)$_GET['ref']));
}

$authSettings = auth_settings_get($pdo);
$errors = [];

if (session_status() === PHP_SESSION_NONE) session_start();

// A pricing-page CTA can link here with ?plan=ID&cycle=monthly|yearly — remember the choice
// across the signup so the redirect below can send a new user straight into checkout for it.
if (!empty($_GET['plan'])) {
    $_SESSION['pending_checkout_plan'] = (int)$_GET['plan'];
    $_SESSION['pending_checkout_cycle'] = ($_GET['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
}
if (!empty($_SESSION['oauth_error'])) {
    $errors[] = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$authSettings['password_signup_enabled']) {
        $errors[] = 'Signing up with a password is currently disabled. Please use one of the options above instead.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'All fields are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if (empty($errors)) {
            $emailSettings = email_settings_get($pdo);
            $verifiedNow = $emailSettings['verification_enabled'] ? null : date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $verifiedNow]);
            $userId = (int)$pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            log_event($pdo, 'system', "New user registered: $email", $userId);
            team_activate_pending_invites($pdo, $userId, $email);
            assign_free_plan_to_new_user($pdo, $userId);
            affiliate_attach_referral($pdo, $userId);

            if ($emailSettings['verification_enabled']) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("INSERT INTO user_tokens (user_id, type, token, expires_at) VALUES (?, 'email_verify', ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))")
                    ->execute([$userId, $token]);
                $link = rtrim(APP_URL, '/') . '/auth/verify-email?token=' . $token;
                send_app_email($pdo, $email, 'Verify your email',
                    "<p>Hi " . e($name) . ",</p><p>Please confirm your email address:</p><p><a href=\"" . e($link) . "\">Verify Email</a></p>");
            }
            // Free Tools -> Pinterest Pin Maker handoff: the pins this visitor already generated
            // (see includes/free_tool_functions.php, FREE_TOOL_SESSION_KEY) stay in $_SESSION
            // across this signup. TODO(Classic Wizard): once the wizard exists, redirect here
            // into its Step 1 pre-filled with $_SESSION['free_tool_pending'] instead of dashboard.
            $freeToolFlag = !empty($_SESSION['free_tool_pending']) ? '?from_freetool=1' : '';
            if (!empty($_SESSION['pending_checkout_plan'])) {
                $planId = (int)$_SESSION['pending_checkout_plan'];
                $cycle = $_SESSION['pending_checkout_cycle'] ?? 'monthly';
                unset($_SESSION['pending_checkout_plan'], $_SESSION['pending_checkout_cycle']);
                redirect(rtrim(APP_URL, '/') . '/user/checkout?plan=' . $planId . '&cycle=' . $cycle);
            }
            if (!empty($_SESSION['free_tool_pending']['wizard'])) {
                // Came from the Pin Maker's "Schedule these pins": open the Classic Wizard with that design.
                redirect(rtrim(APP_URL, '/') . '/user/classic-wizard?from_freetool=1');
            }
            redirect(rtrim(APP_URL, '/') . '/user/dashboard' . $freeToolFlag);
        }
    }
}
auth_layout_start($pdo, 'Sign up free — ' . APP_NAME, 'register');
$hasSocial = $authSettings['google_enabled'] || $authSettings['facebook_enabled'] || $authSettings['microsoft_enabled'] || $authSettings['pinterest_login_enabled'];
?>
<h1>Start growing on Pinterest — <span>free</span></h1>
<p class="au-lead">Create your account in 30 seconds and schedule your first pins today.</p>
<div class="au-perks"><span>✓ Free plan</span><span>✓ No credit card</span><span>✓ Cancel anytime</span></div>
<?php foreach ($errors as $err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

<?php auth_social_buttons($pdo, $authSettings); ?>

<?php if ($authSettings['password_signup_enabled']): ?>
    <?php if ($hasSocial): ?><div class="au-or">or sign up with email</div><?php endif; ?>
    <form method="POST" class="au-form">
        <div class="au-field">
            <label for="au-name">Full name</label>
            <div class="au-input"><span class="au-ic" aria-hidden="true">👤</span><input type="text" id="au-name" name="name" value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name" placeholder="Your name" required autofocus></div>
        </div>
        <div class="au-field">
            <label for="au-email">Email</label>
            <div class="au-input"><span class="au-ic" aria-hidden="true">✉️</span><input type="email" id="au-email" name="email" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" placeholder="you@example.com" required></div>
        </div>
        <div class="au-field">
            <label for="au-password">Password</label>
            <div class="au-input"><span class="au-ic" aria-hidden="true">🔒</span><input type="password" id="au-password" name="password" minlength="6" autocomplete="new-password" placeholder="At least 6 characters" required><button type="button" class="au-pw-toggle" data-toggle-pw="au-password" aria-pressed="false">Show</button></div>
            <div class="au-meter" data-s="0" aria-live="polite"><i></i><i></i><i></i><i></i><small></small></div>
        </div>
        <button type="submit" class="au-submit">Create my free account →</button>
    </form>
<?php endif; ?>
<div class="au-switch">Already have an account? <a href="login">Log in</a></div>
<?php auth_layout_end('register'); ?>
