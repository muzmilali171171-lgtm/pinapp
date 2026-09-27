<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'website-settings';
$pageTitle = 'Website Settings';

$tab = ($_GET['tab'] ?? 'shopify') === 'wix' ? 'wix' : 'shopify';
$saved = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $which = ($_POST['tab'] ?? '') === 'wix' ? 'wix' : 'shopify';
    $tab = $which;

    if ($which === 'shopify') {
        platform_setting_set($pdo, 'shopify_client_id', trim($_POST['client_id'] ?? ''));
        // Blank secret = keep the one already saved.
        $secret = trim($_POST['client_secret'] ?? '');
        if ($secret !== '') platform_setting_set($pdo, 'shopify_client_secret', $secret);
        platform_setting_set($pdo, 'shopify_scopes', preg_replace('/\s+/', '', $_POST['scopes'] ?? '') ?: 'read_products,read_content,write_content');
        $ver = trim($_POST['api_version'] ?? '');
        platform_setting_set($pdo, 'shopify_api_version', preg_match('/^\d{4}-\d{2}$/', $ver) ? $ver : '2026-04');
    }

    // Guide text (both tabs). "Reset" drops the saved text so the built-in default shows again.
    if (!empty($_POST['reset_guide'])) {
        platform_setting_set($pdo, 'guide_' . $which, null);
    } else {
        platform_setting_set($pdo, 'guide_' . $which, (string)($_POST['guide'] ?? ''));
    }
    log_event($pdo, 'system', 'Admin updated ' . $which . ' website settings');
    $saved = $which;
}

$creds = shopify_app_credentials($pdo);
$guideText = platform_setting($pdo, 'guide_' . $tab, null);
if ($guideText === null) $guideText = platform_default_guide($tab);

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Website Settings</h1></div>

<?php if ($saved): ?><div class="alert alert-success"><?= e(ucfirst($saved)) ?> settings saved.</div><?php endif; ?>

<div class="tab-row">
    <a href="?tab=shopify" class="btn-secondary btn-small <?= $tab === 'shopify' ? 'active' : '' ?>">Shopify Setup &amp; Guide</a>
    <a href="?tab=wix" class="btn-secondary btn-small <?= $tab === 'wix' ? 'active' : '' ?>">Wix Setup &amp; Guide</a>
</div>

<form method="POST">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">

    <?php if ($tab === 'shopify'): ?>
    <div class="card">
        <h2>Shopify app (for the "Connect with Shopify" button)</h2>
        <p class="muted">Create an app in the <strong>Shopify Dev Dashboard</strong>, then paste its Client ID and Client secret here. Users then connect their store with one click.
        Leave these empty if you only want users to connect with their own credentials.</p>

        <div class="two-col">
            <div class="form-row">
                <label>Client ID</label>
                <input type="text" name="client_id" value="<?= e($creds['client_id']) ?>" autocomplete="off">
            </div>
            <div class="form-row">
                <label>Client secret</label>
                <input type="password" name="client_secret" placeholder="<?= $creds['client_secret'] !== '' ? '•••••••• saved — leave blank to keep' : '' ?>" autocomplete="off">
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Scopes</label>
                <input type="text" name="scopes" value="<?= e(shopify_scopes($pdo)) ?>">
                <p class="muted" style="margin-bottom:0;">Comma separated. Needed: <code>read_products</code> (product list), <code>read_content</code> + <code>write_content</code> (blogs and publishing articles).</p>
            </div>
            <div class="form-row">
                <label>Admin API version</label>
                <input type="text" name="api_version" value="<?= e(shopify_api_version($pdo)) ?>" placeholder="2026-04">
                <p class="muted" style="margin-bottom:0;">Format YYYY-MM. Shopify retires each version after about a year — update this when needed.</p>
            </div>
        </div>
        <div class="form-row">
            <label>Redirect URL</label>
            <input type="text" readonly value="<?= e(shopify_redirect_uri()) ?>" onclick="this.select();">
            <p class="muted" style="margin-bottom:0;">Add this exact URL under <strong>Allowed redirection URL(s)</strong> in your Shopify app's configuration.</p>
        </div>
        <p class="muted" style="margin-bottom:0;">Status: <?= shopify_oauth_configured($pdo) ? '<span class="badge badge-connected">One-click connect is ON</span>' : '<span class="badge badge-error">Not configured</span>' ?></p>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $tab === 'shopify' ? 'Shopify' : 'Wix' ?> setup guide shown to users</h2>
        <p class="muted">Shown on the user's <?= $tab === 'shopify' ? 'Shopify Stores' : 'Wix Sites' ?> page. Basic HTML is allowed:
        <code>&lt;p&gt; &lt;h4&gt; &lt;ol&gt; &lt;ul&gt; &lt;li&gt; &lt;strong&gt; &lt;em&gt; &lt;code&gt; &lt;a href&gt;</code> — anything else is removed.</p>
        <div class="form-row">
            <textarea name="guide" rows="16" style="font-family:monospace; font-size:13px;"><?= e($guideText) ?></textarea>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="reset_guide" value="1"> Reset this guide to the built-in default</label>

        <h3 style="margin-top:22px; font-size:15px;">Preview</h3>
        <div class="guide-box"><?= sanitize_guide_html($guideText) ?></div>
    </div>

    <button type="submit" class="btn-primary">Save Settings</button>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
