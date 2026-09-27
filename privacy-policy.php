<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/seo_functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Privacy Policy — ' . APP_NAME,
    'description' => 'How ' . APP_NAME . ' collects, uses and protects your data.',
    'schema' => false,
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>
<?php render_site_header($pdo ?? null); ?>

<section class="container" style="max-width:800px; padding:50px 20px;">
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: <?= date('F Y') ?></p>

    <p>This Privacy Policy explains how <?= e(APP_NAME) ?> ("we", "our", "the Service") collects, uses and protects
    your information when you use our Pinterest scheduling tool.</p>

    <h2>1. Information we collect</h2>
    <ul>
        <li><strong>Account information:</strong> your name, email address, and a securely hashed password when you register.</li>
        <li><strong>Pinterest connection data:</strong> when you connect your Pinterest account, Pinterest shares an OAuth
        access token and refresh token with us via their official authorization flow. We never see or store your
        Pinterest password.</li>
        <li><strong>Content you upload:</strong> images, titles, descriptions, links and board selections you provide
        when scheduling pins.</li>
        <li><strong>Usage logs:</strong> technical logs of API calls and publishing activity, used for troubleshooting.</li>
    </ul>

    <h2>2. How we use your information</h2>
    <ul>
        <li>To authenticate you and operate your account.</li>
        <li>To publish the pins you schedule to your connected Pinterest board(s) at the time you choose.</li>
        <li>To display your scheduling history and troubleshoot failed publishes.</li>
    </ul>

    <h2>3. How we store and protect your data</h2>
    <p>Pinterest access and refresh tokens are stored on our secured server and are only ever transmitted to
    Pinterest's official API over encrypted HTTPS connections. Passwords are stored using one-way hashing and are
    never stored or transmitted in plain text.</p>

    <h2>4. Sharing of information</h2>
    <p>We do not sell your personal information. Your scheduled pin content is shared only with Pinterest, as
    directed by you, in order to publish it to your board.</p>

    <h2>5. Disconnecting Pinterest</h2>
    <p>You may disconnect your Pinterest account at any time from your dashboard. This removes your stored access
    tokens from our database. You can also revoke access directly from your Pinterest account settings at any time.</p>

    <h2>6. Data retention</h2>
    <p>We retain your account and scheduling history for as long as your account remains active, or until you
    request deletion.</p>

    <h2>7. Your rights</h2>
    <p>You may request access to, correction of, or deletion of your personal data at any time by contacting us.</p>

    <h2>8. Contact</h2>
    <p>Questions about this policy can be sent to the site owner via the contact details listed on <?= e(APP_URL) ?>.</p>
</section>

<?php render_site_footer($pdo); ?>
</body>
</html>
