<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = uc_build([
'slug' => 'beauty-subscription-box', 'name' => 'Beauty Subscription Boxes', 'short' => 'Beauty Box', 'site' => 'Website', 'accent' => '#ec4899', 'noun' => 'pages', 'niche' => 'beauty, skincare and self-care',
'title' => 'Pinterest for Beauty Subscription Boxes', 'desc' => 'Pin each month’s box, product spotlights and beauty guides to grow subscribers. AI designs the pins and schedules them in 1 click.',
'kw' => 'subscription box Pinterest, beauty box marketing, grow subscribers Pinterest, beauty subscription pins, self care box Pinterest, subscription business traffic',
'badge' => '📦 For Beauty & Self-Care Boxes', 'h1' => 'Pinterest Automation for Beauty Subscription Boxes', 'h1_accent' => 'Every Box, a New Wave',
'sub' => 'Each month’s box is new content: an unboxing, product spotlights, routines. Pin all of it and turn Pinterest into a steady source of new subscribers.',
'bullets' => [['📦', 'Monthly Box Reveals & Product Spotlights'], ['✨', 'Elegant Beauty Templates in Your Brand Colours'], ['🤖', 'AI Writes Titles Around Products & Benefits'], ['⚡', 'All Your Pages Pinned in 1 Click'], ['🎁', 'Gift-Subscription Pins Before the Holidays']],
'chips' => ['📦 Box pinned', '💾 Saved to Self Care', '🎟️ New subscriber'],
'placeholder' => 'https://yourbox.com/september-box-reveal/',
'marquee' => ['Box reveals', 'Unboxing', 'Skincare minis', 'Self-care', 'Gift subscriptions', 'Clean beauty', 'Makeup samples', 'Monthly favourites', 'Spa at home', 'Beauty routines'],
'results' => ['Subscribers From Every Box', 'Each month adds new pins. Over time your box archive becomes a library that keeps bringing subscribers.'],
'features' => ['Why Subscription Boxes Automate Pinterest', 'New content every month — pinned automatically.', [
    ['📦', 'Box reveal pins', 'Collages of each month’s products.'],
    ['✨', 'Product spotlights', 'One pin per featured product.'],
    ['🎁', 'Gift timing', 'Gift subscriptions pinned before holidays.'],
    ['🎨', 'On-brand colours', 'Your palette on every pin.'],
    ['🗂️', 'Beauty boards', 'Skincare, makeup, self-care — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes monthly posts and routines with images.'],
]],
'playbook' => ['Pinterest Tips for Subscription Boxes', 'Turn each box into new subscribers.', [
    ['Pin every monthly reveal', 'Each reveal is fresh, saveable content.'],
    ['Spotlight hero products', 'Individual products get searched.'],
    ['Pin routines', '“Weekend Self-Care Routine” posts that feature your box.'],
    ['Push gift subscriptions early', 'November and December gifting starts in October.'],
    ['Link to signup', 'Every pinned page should make subscribing easy.'],
]],
'who' => ['subscription brands', 'Beauty, self-care and wellness boxes.', [['Beauty boxes', 'Grow subscribers every month.'], ['Self-care boxes', 'Reach people planning “me time”.'], ['Wellness brands', 'Pin products and routines together.']]],
'faq' => [
    ['Can I pin each month’s box?', 'Yes — pin reveal posts and product pages each month.'],
    ['When should I promote gift subscriptions?', 'From October onward.'],
    ['Can pins use my brand colours?', 'Yes — set your palette once.'],
],
'cta_red' => ['Every box, a new wave of subscribers.', 'Pin your boxes automatically.'],
'cta_dark' => 'Ready to grow your subscription box?',
]);
uc_render_page($pdo, $user, $uc);
