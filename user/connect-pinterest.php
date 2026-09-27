<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'connect';
$pageTitle = 'Pinterest Accounts';

// Disconnect handler
if (isset($_GET['disconnect'])) {
    $accId = (int)$_GET['disconnect'];
    $stmt = $pdo->prepare("DELETE FROM pinterest_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accId, $user['id']]);
    log_event($pdo, 'oauth', "Account #$accId disconnected", $user['id']);
    redirect('connect-pinterest');
}

$accounts = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? ORDER BY connected_at DESC");
$accounts->execute([$user['id']]);
$accounts = $accounts->fetchAll();

$configured = pinterest_configured($pdo);
$authorizeUrl = null;
if ($configured) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['pinterest_oauth_state'] = $state;
    $authorizeUrl = pinterest_build_authorize_url($pdo, $state);
}

// The OAuth callback (oauth/pinterest-callback.php) redirects back here on any failure
// (expired/invalid state, Pinterest returned an error, token exchange failed, plan limit
// reached) and leaves the reason in $_SESSION['oauth_error'] — read it once and clear it,
// so a failed connection attempt actually tells the user why instead of silently landing
// back on an unchanged "no accounts connected" page.
$oauthError = $_SESSION['oauth_error'] ?? null;
unset($_SESSION['oauth_error']);
$oauthSuccess = $_SESSION['oauth_success'] ?? null;
unset($_SESSION['oauth_success']);

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Pinterest Accounts</h1>
    <?php if ($configured): ?>
        <a href="<?= e($authorizeUrl) ?>" class="btn-primary">Connect Pinterest</a>
    <?php endif; ?>
</div>

<?php if ($oauthSuccess): ?>
<div class="alert alert-success"><?= e($oauthSuccess) ?></div>
<?php endif; ?>
<?php if ($oauthError): ?>
<div class="alert alert-error"><?= e($oauthError) ?></div>
<?php endif; ?>

<?php if (!$configured): ?>
<div class="alert alert-info">Pinterest connection isn't set up on this site yet. Please check back soon.</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($accounts)): ?>
        <div class="empty-state">No Pinterest accounts connected yet. Click "Connect Pinterest" above to authorize your account.</div>
    <?php else: ?>
    <table>
        <tr><th>Account</th><th>Status</th><th>Connected</th><th></th></tr>
        <?php foreach ($accounts as $a): ?>
        <tr>
            <td><?= e($a['pinterest_username'] ?: ('Pinterest user #' . $a['pinterest_user_id'])) ?></td>
            <td><span class="badge badge-<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
            <td><?= format_datetime($a['connected_at']) ?></td>
            <td><a href="?disconnect=<?= (int)$a['id'] ?>" class="btn-secondary btn-small" onclick="return confirm('Disconnect this Pinterest account?');">Disconnect</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
