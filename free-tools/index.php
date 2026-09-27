<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/footer_functions.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user($pdo);

$pinterestTools = [
    ['Pinterest Pin Maker', 'Scan any page and turn its images into ready-made pins.', 'pinterest-pin-maker'],
    ['AI Pinterest Pin Create', 'Turn a title or keyword into a fully designed pin.', 'ai-pinterest-pin-create'],
    ['AI Image Creator', 'Generate images from a text prompt.', 'ai-image-creater'],
    ['Pinterest Hashtag Generator', 'Find relevant hashtags for any topic.', 'pinterest-hashtag-generator'],
    ['Pinterest Title, Description Generator', 'Write click-worthy titles and descriptions.', 'pinterest-title-description-generator'],
    ['Pinterest Bio Generator', 'Write an SEO-optimized profile bio.', 'pinterest-bio-generator'],
    ['Pinterest Board Name Generator', 'Catchy, searchable board names.', 'pinterest-board-name-generator'],
    ['Pinterest Username Generator', 'Unique, SEO-friendly usernames.', 'pinterest-username-generator'],
    ['Pinterest Alt Text Generator', 'Accessible, SEO alt text from an image.', 'pinterest-alt-text-generator'],
    ['Pinterest Font Generator', 'Stylized Unicode text for bios and boards.', 'pinterest-font-generator'],
    ['Pinterest Keyword Research Tool', 'Related search phrases for your niche.', 'pinterest-keyword-research-tool'],
    ['Pinterest Color Palette Generator', 'Extract or generate on-brand color palettes.', 'pinterest-color-palette-generator'],
    ['Pinterest Image Resizer', 'Resize any image to the right pin dimensions.', 'pinterest-image-resizer'],
    ['Pinterest Pin Preview', 'See how your pin looks before you post it.', 'pinterest-pin-preview'],
    ['Pinterest Character Counter', 'Check every field against Pinterest\'s limits.', 'pinterest-character-counter'],
];

$etsyTools = [
    ['Etsy Keyword Tool', 'Keyword ideas with demand and competition estimates.', 'etsy-keyword-tool'],
    ['Etsy Fee Calculator', 'See your true profit per order before you list.', 'etsy-fee-calculator'],
    ['Etsy Shop Bio Generator', 'Authentic, trust-building shop bios.', 'etsy-bio-generator'],
    ['Etsy Shop Announcement Generator', 'SEO-friendly banner announcements.', 'etsy-shop-announcement-generator'],
    ['Etsy Shop Name Generator', 'Unique, SEO-friendly store names.', 'etsy-shop-name-generator'],
    ['Etsy Tags Generator', '30 tag ideas to choose your best 13 from.', 'etsy-tags-generator'],
    ['Etsy Title & Description Generator', 'Listing copy that converts.', 'etsy-title-description-generator'],
    ['Etsy QR Code Generator', 'Scannable QR codes for your shop or listings.', 'etsy-qr-code-generator'],
];

$pageTitleText = 'Pinterest Free Tools';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Pinterest &amp; Etsy Tools — <?= e(APP_NAME) ?></title>
<meta name="description" content="Every free Pinterest and Etsy tool in one place — pin design, keyword research, bio and hashtag generators, calculators, and more. No sign-up required to try them.">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero" style="max-width:820px;">
        <h1><?= e($pageTitleText) ?></h1>
        <p class="ft-sub">Upgrade your Pinterest presence, zero cost. Discover the tools in our free toolkit — design pins, write copy, research keywords, and review your account, all without paying a cent.</p>
    </div>

    <h2 style="text-align:center;font-size:26px;margin:36px 0 18px;">Pinterest Tools</h2>
    <div class="ft-hub-grid">
        <?php foreach ($pinterestTools as [$name, $desc, $slug]): ?>
            <a class="ft-hub-card" href="<?= e($slug) ?>/">
                <div class="ft-hub-card-title"><?= e($name) ?></div>
                <div class="ft-hub-card-desc"><?= e($desc) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <h2 style="text-align:center;font-size:26px;margin:48px 0 18px;">Etsy Tools</h2>
    <div class="ft-hub-grid">
        <?php foreach ($etsyTools as [$name, $desc, $slug]): ?>
            <a class="ft-hub-card" href="<?= e($slug) ?>/">
                <div class="ft-hub-card-title"><?= e($name) ?></div>
                <div class="ft-hub-card-desc"><?= e($desc) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ft-marketing" style="margin-top:56px;">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want it all running on autopilot?</h2>
        <p>Sign up free for the full dashboard — design, write, and schedule Pinterest pins for your whole site or shop automatically.</p>
        <a href="../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>
</div>

<?php render_site_footer($pdo); ?>
</body>
</html>
