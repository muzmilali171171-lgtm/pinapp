<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';

$user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Terms and Conditions | ' . SITE_BRAND,
    'description' => 'The terms and conditions for using ' . SITE_BRAND . ': accounts, plans and billing, acceptable use, Pinterest integration, content ownership and liability.',
    'breadcrumbs' => [['Terms and Conditions', 'terms']],
    'schema' => false,
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="container" style="max-width:800px; padding:50px 20px;">
    <h1>Terms and Conditions</h1>
    <p class="muted">Last updated: <?= date('F Y') ?></p>

    <p>These Terms and Conditions ("Terms") govern your use of <?= e(SITE_BRAND) ?> (the "Service"). By creating an
    account or using the Service, you agree to these Terms.</p>

    <h2>1. Using the Service</h2>
    <p>You must provide accurate account information and are responsible for keeping your login credentials secure.
    You agree to use the Service only for lawful purposes and in line with Pinterest's own platform policies.</p>

    <h2>2. Your content</h2>
    <p>You retain ownership of the images, titles, descriptions and links you upload or generate. By scheduling a
    pin, you authorize us to publish that content to your connected Pinterest account at the time you choose.</p>

    <h2>3. Subscription plans and billing</h2>
    <p>Paid plans are billed on a recurring basis as described at checkout. You may upgrade, downgrade or cancel
    from your dashboard at any time; charges already processed are non-refundable except where required by law.</p>

    <h2>4. Third-party platforms</h2>
    <p>The Service connects to Pinterest and, optionally, platforms such as Shopify, Wix or your own website, via
    their official APIs. Your use of those platforms remains subject to their own terms.</p>

    <h2>5. Service availability</h2>
    <p>We aim to keep the Service available and publishing on schedule but do not guarantee uninterrupted access,
    including where a connected platform's own API is unavailable.</p>

    <h2>6. Termination</h2>
    <p>You may stop using the Service and delete your account at any time. We may suspend accounts that violate
    these Terms or Pinterest's platform policies.</p>

    <h2>7. Changes to these Terms</h2>
    <p>We may update these Terms from time to time. Continued use of the Service after a change means you accept
    the updated Terms.</p>

    <h2>8. Contact</h2>
    <p>Questions about these Terms can be sent to us via the <a href="contact">Contact page</a>.</p>
</section>

<?php render_site_footer($pdo); ?>

</body>
</html>
