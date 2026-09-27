<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/contact_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'contact-settings';
$pageTitle = 'Contact Settings';

$rowId = contact_ensure_settings_row($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'support_email' => mb_substr(trim($_POST['support_email'] ?? ''), 0, 150),
        'whatsapp_number' => mb_substr(trim($_POST['whatsapp_number'] ?? ''), 0, 50),
        'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? 1 : 0,
        'address' => trim($_POST['address'] ?? ''),
        'intro_text' => trim($_POST['intro_text'] ?? ''),
    ];
    $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
    $pdo->prepare("UPDATE contact_settings SET $set WHERE id = ?")->execute([...array_values($fields), $rowId]);
    log_event($pdo, 'system', 'Admin updated Contact page settings');
    redirect('contact-settings?saved=1');
}

$s = get_contact_settings($pdo);
$v = static fn(string $k, $default = '') => e((string)($s[$k] ?? $default));

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Contact Settings</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="card">
    <h2>Support Details</h2>
    <p class="muted">Shown to visitors on the public <a href="<?= e(rtrim(APP_URL, '/')) ?>/contact" target="_blank" rel="noopener">Contact page</a>.</p>
    <form method="POST">
        <div class="two-col">
            <div class="form-row">
                <label>Support Email</label>
                <input type="email" name="support_email" maxlength="150" value="<?= $v('support_email') ?>" placeholder="support@yourdomain.com">
            </div>
            <div class="form-row">
                <label>WhatsApp Support Number</label>
                <input type="text" name="whatsapp_number" maxlength="50" value="<?= $v('whatsapp_number') ?>" placeholder="+1 555 123 4567">
            </div>
        </div>
        <label class="checkbox-row">
            <input type="checkbox" name="whatsapp_enabled" value="1" <?= !empty($s['whatsapp_enabled']) ? 'checked' : '' ?>>
            Show the WhatsApp number on the Contact page
        </label>
        <div class="form-row" style="margin-top:14px;">
            <label>Address</label>
            <textarea name="address" rows="3" placeholder="123 Business Street, City, Country"><?= $v('address') ?></textarea>
        </div>
        <div class="form-row">
            <label>Intro Text <span class="muted">(shown above the contact form — optional)</span></label>
            <textarea name="intro_text" rows="2" placeholder="Have a question? Send us a message and we'll get back to you shortly."><?= $v('intro_text') ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
