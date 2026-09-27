<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/oauth_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'settings';
$pageTitle = 'Settings';

$tab = $_GET['tab'] ?? 'account';
if (!in_array($tab, ['account', 'ai-api', 'image-models', 'team'], true)) $tab = 'account';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_name') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $errors[] = 'Please enter a name.';
            } else {
                $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $user['id']]);
                $success = 'Your name was updated.';
                $user['name'] = $name;
            }
            $tab = 'account';
        }

        if ($action === 'update_email') {
            $newEmail = trim($_POST['new_email'] ?? '');
            $password = $_POST['current_password'] ?? '';
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$newEmail, $user['id']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Another account already uses that email.';
                } else {
                    $oldEmail = $user['email'];
                    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $user['id']]);
                    $user['email'] = $newEmail;
                    log_event($pdo, 'system', "User changed their email from $oldEmail to $newEmail", $user['id']);
                    $emailSettings = email_settings_get($pdo);
                    if ($emailSettings['email_change_notify_enabled']) {
                        send_app_email($pdo, $oldEmail, 'Your email address was changed',
                            "<p>Hi " . e($user['name']) . ",</p><p>Your " . e(defined('APP_NAME') ? APP_NAME : 'account') . " login email was changed to <strong>" . e($newEmail) . "</strong>. If you didn't make this change, please contact support immediately.</p>");
                    }
                    $success = 'Your email was updated.';
                }
            }
            $tab = 'account';
        }

        if ($action === 'update_password') {
            $current = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (!password_verify($current, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirm) {
                $errors[] = 'New password and confirmation do not match.';
            } else {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
                log_event($pdo, 'system', 'User changed their password', $user['id']);
                $success = 'Your password was updated.';
            }
            $tab = 'account';
        }

        if ($action === 'disconnect_google') {
            oauth_disconnect($pdo, (int)$user['id'], 'google');
            $success = 'Google account disconnected.';
            $tab = 'account';
        }

        if ($action === 'update_auto_renew') {
            try {
                $pdo->prepare("UPDATE users SET plan_auto_renew = ? WHERE id = ?")->execute([!empty($_POST['plan_auto_renew']) ? 1 : 0, $user['id']]);
                $success = 'Auto-renew preference saved.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save this right now. Please try again later.';
            }
            $tab = 'account';
        }

        if ($action === 'save_ai_text') {
            try {
                $existing = get_user_ai_settings_row($pdo, (int)$user['id']);
                save_user_ai_settings($pdo, (int)$user['id'], [
                    'text_enabled' => !empty($_POST['text_enabled']),
                    'text_api_key' => $_POST['text_api_key'] ?? '',
                    'text_model' => $_POST['text_model'] ?? '',
                    'image_enabled' => $existing['image_enabled'] ?? 0,
                    'image_api_key' => $existing['image_api_key'] ?? '',
                    'image_model' => $existing['image_model'] ?? '',
                ]);
                $success = 'AI Api settings saved.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save this right now. Please try again later.';
            }
            $tab = 'ai-api';
        }

        if ($action === 'save_ai_image') {
            try {
                $existing = get_user_ai_settings_row($pdo, (int)$user['id']);
                save_user_ai_settings($pdo, (int)$user['id'], [
                    'text_enabled' => $existing['text_enabled'] ?? 0,
                    'text_api_key' => $existing['text_api_key'] ?? '',
                    'text_model' => $existing['text_model'] ?? '',
                    'image_enabled' => !empty($_POST['image_enabled']),
                    'image_api_key' => $_POST['image_api_key'] ?? '',
                    'image_model' => $_POST['image_model'] ?? '',
                ]);
                $success = 'Image Generation Models settings saved.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save this right now. Please try again later.';
            }
            $tab = 'image-models';
        }

        if ($action === 'team_invite') {
            $ownPlan = get_user_plan($pdo, (int)$user['id']);
            $inviteLimit = plan_limit_value($ownPlan, 'invite_team_members_limit'); // null = unlimited
            $activeCount = count(array_filter(get_owned_team_members($pdo, (int)$user['id']), fn($m) => $m['status'] !== 'removed'));
            if ($inviteLimit !== null && $inviteLimit <= 0) {
                $errors[] = 'Your current plan does not include team invites. Upgrade your plan to invite team members.';
            } elseif ($inviteLimit !== null && $activeCount >= $inviteLimit) {
                $errors[] = "Your plan allows up to $inviteLimit team member(s). Remove someone or upgrade your plan to invite more.";
            } else {
                $r = team_invite_member($pdo, (int)$user['id'], $_POST['invite_email'] ?? '');
                if (!$r['ok']) {
                    $errors[] = $r['error'];
                } else {
                    $success = $r['status'] === 'active' ? 'They now have access to your team.' : 'Invite saved — it activates automatically as soon as they create an account with that email.';
                }
            }
            $tab = 'team';
        }

        if ($action === 'team_remove') {
            team_remove_member($pdo, (int)$user['id'], (int)($_POST['row_id'] ?? 0));
            $success = 'Team member removed.';
            $tab = 'team';
        }

        if ($action === 'team_update_share') {
            $pct = max(0, min(100, (int)($_POST['limit_share_percent'] ?? 100)));
            try {
                $pdo->prepare("UPDATE team_members SET limit_share_percent = ? WHERE id = ? AND owner_id = ?")
                    ->execute([$pct, (int)($_POST['row_id'] ?? 0), $user['id']]);
                $success = 'Team member share updated.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save this right now. Please try again later.';
            }
            $tab = 'team';
        }

        if ($action === 'team_leave') {
            team_leave($pdo, (int)$user['id'], (int)($_POST['row_id'] ?? 0));
            $success = 'You left that team.';
            $tab = 'team';
        }
    }
}

// Re-fetch fresh data for display.
$aiSettings = get_user_ai_settings_row($pdo, (int)$user['id']);
$isTeamMember = team_effective_owner_id($pdo, (int)$user['id']) !== (int)$user['id'];
$ownedMembers = get_owned_team_members($pdo, (int)$user['id']);
$memberOfTeams = get_teams_user_belongs_to($pdo, (int)$user['id']);
$connections = get_user_oauth_connections($pdo, (int)$user['id']);
$authSettings = auth_settings_get($pdo);
$imageCredits = get_user_image_credits($pdo, (int)$user['id']);
$textCredits = get_user_text_credits($pdo, (int)$user['id']);
try {
    $freshUser = $pdo->prepare("SELECT plan_auto_renew, plan_id, plan_end_date FROM users WHERE id = ?");
    $freshUser->execute([$user['id']]);
    $freshUser = $freshUser->fetch() ?: [];
} catch (Throwable $e) {
    $freshUser = [];
}
$currentPlan = get_user_plan($pdo, (int)$user['id']);

// If Plan Pricing's tables/columns aren't migrated yet, most of this page still works —
// just show a heads-up instead of silently hiding plan/billing info.
$migrationReady = true;
try {
    $pdo->query("SELECT plan_id FROM users LIMIT 1");
    $pdo->query("SELECT id FROM user_ai_settings LIMIT 1");
} catch (Throwable $e) {
    $migrationReady = false;
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Settings</h1></div>

<?php if (!$migrationReady): ?><div class="alert alert-info">Some settings are not available right now. Please try again later.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="settings-tabs">
    <a href="?tab=account" class="<?= $tab === 'account' ? 'active' : '' ?>">Account</a>
    <a href="?tab=ai-api" class="<?= $tab === 'ai-api' ? 'active' : '' ?>">AI Api</a>
    <a href="?tab=image-models" class="<?= $tab === 'image-models' ? 'active' : '' ?>">Image Generation Models</a>
    <a href="?tab=team" class="<?= $tab === 'team' ? 'active' : '' ?>">Team Management</a>
</div>

<?php if ($tab === 'account'): ?>

    <div class="card">
        <h2>Profile</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_name">
            <div class="two-col">
                <div class="form-row">
                    <label>Full name</label>
                    <input type="text" name="name" value="<?= e($user['name']) ?>" required>
                </div>
                <div class="form-row">
                    <label>Email (current)</label>
                    <input type="email" value="<?= e($user['email']) ?>" disabled>
                </div>
            </div>
            <button type="submit" class="btn-primary">Save Name</button>
        </form>
    </div>

    <div class="card">
        <h2>Update Email</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_email">
            <div class="two-col">
                <div class="form-row">
                    <label>New email</label>
                    <input type="email" name="new_email" required>
                </div>
                <div class="form-row">
                    <label>Current password (to confirm)</label>
                    <input type="password" name="current_password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Update Email</button>
        </form>
    </div>

    <div class="card">
        <h2>Update Password</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_password">
            <div class="form-row">
                <label>Current password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="two-col">
                <div class="form-row">
                    <label>New password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-row">
                    <label>Confirm new password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
        </form>
    </div>

    <div class="card">
        <h2>Connected Accounts</h2>
        <div class="social-connect-row">
            <div>
                <div class="team-member-name">Google</div>
                <div class="team-member-email"><?= isset($connections['google']) ? e($connections['google']['provider_email'] ?: 'Connected') : 'Not connected' ?></div>
            </div>
            <?php if (isset($connections['google'])): ?>
                <form method="POST" onsubmit="return confirm('Disconnect your Google account?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disconnect_google">
                    <button type="submit" class="btn-secondary btn-small">Disconnect</button>
                </form>
            <?php elseif ($authSettings['google_enabled']): ?>
                <a href="<?= e(oauth_build_authorize_url($pdo, 'google', 'connect') ?: '#') ?>" class="btn-secondary btn-small">Connect Google Account</a>
            <?php else: ?>
                <span class="muted">Not available right now</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2>Billing</h2>
        <p class="muted">Current plan: <strong><?= e($currentPlan['name'] ?? 'No plan') ?></strong>
        <?php if (!empty($freshUser['plan_end_date'])): ?> — renews/expires <?= e(format_datetime($freshUser['plan_end_date'])) ?><?php endif; ?></p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_auto_renew">
            <label class="checkbox-row"><input type="checkbox" name="plan_auto_renew" value="1" <?= !empty($freshUser['plan_auto_renew']) ? 'checked' : '' ?>> Auto-renew my plan automatically at the end of each billing period</label>
            <p class="muted" style="margin:6px 0 10px;">On by default. Turn this off to let your plan expire instead of being charged again.</p>
            <button type="submit" class="btn-secondary btn-small">Save</button>
        </form>
        <a href="upgrade" class="btn-primary btn-small" style="margin-top:10px; display:inline-block;">Manage Plan</a>
    </div>

<?php elseif ($tab === 'ai-api'): ?>

    <?php if ($isTeamMember): ?>
        <div class="alert alert-info">You're on a team — AI Api settings are shared with (and managed by) your team owner. Changes here affect the whole team.</div>
    <?php endif; ?>

    <div class="card">
        <h2>Use your own AI model (OpenRouter)</h2>
        <p class="muted">Runs pin text / article text generation on <strong>your own</strong> OpenRouter account and model
        instead of <?= e(defined('APP_NAME') ? APP_NAME : 'the platform') ?>'s models. If your key hits a limit or errors out,
        generation automatically falls back to the platform's own model so your request still completes.</p>
        <p class="muted">Status:
            <?php if (!empty($aiSettings['text_enabled']) && !empty($aiSettings['text_api_key'])): ?>
                <span class="badge badge-connected">Configured</span>
            <?php else: ?>
                <span class="badge badge-error">Not configured</span>
            <?php endif; ?>
        </p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_ai_text">
            <label class="checkbox-row">
                <input type="checkbox" name="text_enabled" value="1" <?= !empty($aiSettings['text_enabled']) ? 'checked' : '' ?>>
                Use my own OpenRouter API key for pin/article text
            </label>
            <div class="form-row" style="margin-top:14px;">
                <label>OpenRouter API key</label>
                <input type="text" name="text_api_key" value="<?= e($aiSettings['text_api_key'] ?? '') ?>" placeholder="sk-or-...">
            </div>
            <div class="form-row">
                <label>Model</label>
                <input type="text" name="text_model" value="<?= e($aiSettings['text_model'] ?? '') ?>" placeholder="enter model example: anthropic/claude-sonnet-4.5">
                <p class="muted" style="margin-bottom:0;">Any model on <a href="https://openrouter.ai/models" target="_blank" rel="noopener">openrouter.ai/models</a> — leave empty for automatic.</p>
            </div>
            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>

<?php elseif ($tab === 'image-models'): ?>

    <?php if ($isTeamMember): ?>
        <div class="alert alert-info">You're on a team — Image Generation Models settings are shared with (and managed by) your team owner.</div>
    <?php endif; ?>

    <div class="card">
        <h2>Use your own AI model (OpenRouter)</h2>
        <p class="muted">Runs AI image generation on <strong>your own</strong> OpenRouter account and model instead of
        <?= e(defined('APP_NAME') ? APP_NAME : 'the platform') ?>'s models. Falls back automatically to the platform's own
        image model if your key hits a limit or errors out.</p>
        <p class="muted">Status:
            <?php if (!empty($aiSettings['image_enabled']) && !empty($aiSettings['image_api_key'])): ?>
                <span class="badge badge-connected">Configured</span>
            <?php else: ?>
                <span class="badge badge-error">Not configured</span>
            <?php endif; ?>
        </p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_ai_image">
            <label class="checkbox-row">
                <input type="checkbox" name="image_enabled" value="1" <?= !empty($aiSettings['image_enabled']) ? 'checked' : '' ?>>
                Use my own OpenRouter API key for AI image generation
            </label>
            <div class="form-row" style="margin-top:14px;">
                <label>OpenRouter API key</label>
                <input type="text" name="image_api_key" value="<?= e($aiSettings['image_api_key'] ?? '') ?>" placeholder="sk-or-...">
            </div>
            <div class="form-row">
                <label>Model</label>
                <input type="text" name="image_model" value="<?= e($aiSettings['image_model'] ?? '') ?>" placeholder="enter an image-capable model, e.g. google/gemini-2.5-flash-image-preview">
                <p class="muted" style="margin-bottom:0;">Any image-generation-capable model on <a href="https://openrouter.ai/models" target="_blank" rel="noopener">openrouter.ai/models</a> — leave empty for automatic.</p>
            </div>
            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>

<?php elseif ($tab === 'team'): ?>

    <div class="card">
        <h2>Your Team <span class="muted" style="font-weight:400;">(<?= number_format($imageCredits, 1) ?> image / <?= number_format($textCredits, 1) ?> text credits available to share)</span></h2>
        <p class="muted">Invite people by email to use your account's resources — credits and your own AI Api / Image
        Generation Models keys. Team members sign in with their own login and never see your data, pins, websites or
        articles, or any other member's data — only shared resource usage. Your plan allows
        <strong><?= plan_limit_value($currentPlan ?: null, 'invite_team_members_limit') === null ? 'unlimited' : (int)plan_limit_value($currentPlan ?: null, 'invite_team_members_limit') ?></strong> team member(s). Once someone
        is active, set what % of your feature limits they can use with the field next to their name.</p>
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="team_invite">
            <div class="form-row" style="flex:1; min-width:220px; margin-bottom:0;">
                <label>Invite by email</label>
                <input type="email" name="invite_email" placeholder="teammate@example.com" required>
            </div>
            <button type="submit" class="btn-primary">Send Invite</button>
        </form>

        <?php if (empty($ownedMembers)): ?>
            <p class="muted" style="margin-top:18px;">No team members yet.</p>
        <?php else: ?>
            <div style="margin-top:18px;">
                <?php foreach ($ownedMembers as $m): ?>
                <div class="team-member-row">
                    <div>
                        <div class="team-member-name"><?= e($m['member_name'] ?: $m['invited_email']) ?></div>
                        <div class="team-member-email"><?= e($m['invited_email']) ?></div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="badge badge-<?= $m['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($m['status'])) ?></span>
                        <?php if ($m['status'] === 'active'): ?>
                        <form method="POST" style="display:flex; align-items:center; gap:4px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="team_update_share">
                            <input type="hidden" name="row_id" value="<?= (int)$m['id'] ?>">
                            <input type="number" name="limit_share_percent" min="0" max="100" value="<?= (int)$m['limit_share_percent'] ?>" style="width:56px;" title="% of your feature limits this member can use">%
                            <button type="submit" class="btn-secondary btn-small">Save</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Remove this team member?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="team_remove">
                            <input type="hidden" name="row_id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn-danger btn-small">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Teams You're Part Of</h2>
        <?php if (empty($memberOfTeams)): ?>
            <p class="muted">You're not on anyone else's team.</p>
        <?php else: ?>
            <?php foreach ($memberOfTeams as $t): ?>
            <div class="team-member-row">
                <div>
                    <div class="team-member-name"><?= e($t['owner_name']) ?></div>
                    <div class="team-member-email"><?= e($t['owner_email']) ?> — joined <?= e(format_datetime($t['joined_at'])) ?></div>
                </div>
                <form method="POST" onsubmit="return confirm('Leave this team? You will lose access to their shared credits and AI keys.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="team_leave">
                    <input type="hidden" name="row_id" value="<?= (int)$t['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small">Leave Team</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
