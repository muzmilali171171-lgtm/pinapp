<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'upgrade';
$pageTitle = 'Contact Sales';

$pricingSettings = credit_pricing_get($pdo);
if (!$pricingSettings['contact_sales_enabled']) redirect('upgrade');

$done = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') $errors[] = 'Name, email and message are required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';

    if (empty($errors)) {
        save_contact_sales_submission($pdo, (int)$user['id'], $name, $whatsapp, $email, $budget, $message);
        $done = true;
    }
}

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Contact Sales</h1></div>

<?php if ($done): ?>
    <div class="card"><div class="alert alert-success">Thanks! Our team will reach out to you shortly.</div><a href="upgrade" class="btn-secondary">Back to Plans</a></div>
<?php else: ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="card" style="max-width:560px;">
    <h2>Tell us what you need</h2>
    <p class="muted">Select custom limits and AI credits — tell us your budget and we'll put together a plan for you.</p>
    <form method="POST">
        <div class="two-col">
            <div class="form-row"><label>Name</label><input type="text" name="name" value="<?= e($_POST['name'] ?? $user['name']) ?>" required></div>
            <div class="form-row"><label>WhatsApp number</label><input type="text" name="whatsapp" value="<?= e($_POST['whatsapp'] ?? '') ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Email</label><input type="email" name="email" value="<?= e($_POST['email'] ?? $user['email']) ?>" required></div>
            <div class="form-row"><label>Budget</label><input type="text" name="budget" value="<?= e($_POST['budget'] ?? '') ?>" placeholder="e.g. $200/month"></div>
        </div>
        <div class="form-row">
            <label>Message</label>
            <textarea name="message" rows="5" placeholder="Explain the limits/credits you need and your budget" required><?= e($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Send Request</button>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
