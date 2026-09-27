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
    'title' => 'About Us — ' . APP_NAME,
    'description' => 'Learn about ' . APP_NAME . ' and how it automates Pinterest marketing for your website.',
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="container" style="max-width:800px; padding:50px 20px;">
    <h1>About <?= e(APP_NAME) ?></h1>
    <p><?= e(APP_NAME) ?> helps website and shop owners put their Pinterest marketing on autopilot. Connect your
    Pinterest account, and we design, write and schedule pins for your content automatically — every day, on time.</p>

    <h2>What we do</h2>
    <p>From a single dashboard you can schedule pins manually, bulk-upload a batch, or point us at your website,
    WordPress blog or Shopify store and let our automation turn new pages and products into pins on a
    recurring schedule.</p>

    <h2>Why we built it</h2>
    <p>Keeping a Pinterest presence consistent takes real time every week. We built <?= e(APP_NAME) ?> so that
    time gets spent growing your business instead of manually designing and posting pins.</p>

    <p>Have a question about how it works? <a href="contact">Get in touch</a> — we're happy to help.</p>
</section>

<?php render_site_footer($pdo); ?>

</body>
</html>
