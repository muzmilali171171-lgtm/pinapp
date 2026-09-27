<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/contact_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';

$user = current_user($pdo);
$settings = get_contact_settings($pdo);
$errors = [];
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 150);
    $email = mb_substr(trim($_POST['email'] ?? ''), 0, 150);
    $subject = mb_substr(trim($_POST['subject'] ?? ''), 0, 255);
    $message = trim($_POST['message'] ?? '');

    if ($name === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($message === '') $errors[] = 'Please enter a message.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO contact_submissions (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $email, $subject ?: null, $message, $_SERVER['REMOTE_ADDR'] ?? null]);
        log_event($pdo, 'system', 'New contact form submission from ' . $email);
        redirect('contact?sent=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Contact Us: Support & Sales | ' . SITE_BRAND,
    'description' => 'Questions about Pinterest automation, pricing or your account? Contact the ' . SITE_BRAND . ' team by email, WhatsApp or the contact form. We reply fast.',
    'breadcrumbs' => [['Contact Us', 'contact']],
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container" style="max-width:1000px;">
    <div style="text-align:center;max-width:640px;margin:50px auto 0;">
        <h1>Contact Us</h1>
        <p class="muted" style="font-size:15px;">
            <?= !empty($settings['intro_text']) ? nl2br(e($settings['intro_text'])) : "Have a question? Send us a message and we'll get back to you shortly." ?>
        </p>
    </div>

    <div class="contact-grid">
        <div>
            <?php if (!empty($settings['support_email'])): ?>
            <div class="contact-info-item">
                <div class="contact-info-icon">✉️</div>
                <div>
                    <h4>Email</h4>
                    <a href="mailto:<?= e($settings['support_email']) ?>"><?= e($settings['support_email']) ?></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($settings['whatsapp_enabled']) && !empty($settings['whatsapp_number'])): ?>
            <div class="contact-info-item">
                <div class="contact-info-icon">💬</div>
                <div>
                    <h4>WhatsApp</h4>
                    <a href="<?= e(contact_whatsapp_link($settings['whatsapp_number'])) ?>" target="_blank" rel="noopener"><?= e($settings['whatsapp_number']) ?></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($settings['address'])): ?>
            <div class="contact-info-item">
                <div class="contact-info-icon">📍</div>
                <div>
                    <h4>Address</h4>
                    <p><?= nl2br(e($settings['address'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <?php if ($sent): ?>
                <div class="alert alert-success">Thanks — your message has been sent. We'll get back to you soon.</div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

            <form method="POST">
                <div class="two-col">
                    <div class="form-row">
                        <label>Your Name</label>
                        <input type="text" name="name" maxlength="150" required value="<?= e($_POST['name'] ?? ($user['name'] ?? '')) ?>">
                    </div>
                    <div class="form-row">
                        <label>Your Email</label>
                        <input type="email" name="email" maxlength="150" required value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <label>Subject <span class="muted">(optional)</span></label>
                    <input type="text" name="subject" maxlength="255" value="<?= e($_POST['subject'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Message</label>
                    <textarea name="message" rows="6" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-primary">Send Message</button>
            </form>
        </div>
    </div>
</div>

<?php render_site_footer($pdo); ?>

</body>
</html>
