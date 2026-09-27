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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-hashtag-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'zazzle', 'name' => 'Zazzle Designers', 'short' => 'Zazzle Store', 'site' => 'Store', 'accent' => '#0891b2', 'noun' => 'products', 'niche' => 'invitations, gifts and custom products',
'title' => 'Pinterest for Zazzle Designers & Stores', 'desc' => 'Promote your Zazzle invitations, cards and custom gifts on Pinterest. AI designs pins and schedules them ahead of every season in 1 click.',
'kw' => 'Zazzle Pinterest, promote Zazzle store, Zazzle designer marketing, wedding invitation pins, custom gift Pinterest, Zazzle traffic',
'badge' => '🎁 For Zazzle Designers', 'h1' => 'Pinterest Automation for Zazzle Designers', 'h1_accent' => 'Invitations & Gifts, Discovered',
'sub' => 'Wedding invitations, birthday cards, custom gifts — the things people plan on Pinterest. Pin your Zazzle designs ahead of every wedding season and holiday.',
'bullets' => [['💌', 'Pins for Invitations, Cards & Custom Gifts'], ['📅', 'Wedding Season & Holidays Scheduled Ahead'], ['🔤', 'AI Writes Titles by Occasion, Theme & Style'], ['⚡', 'Your Whole Store Pinned in 1 Click'], ['🗂️', 'Boards by Occasion']],
'chips' => ['💌 Invitation pinned', '💾 Saved to Wedding', '🛒 Order'],
'placeholder' => 'https://www.zazzle.com/your_design-123456',
'marquee' => ['Wedding invitations', 'Birthday invitations', 'Baby shower', 'Christmas cards', 'Custom mugs', 'Save the dates', 'Graduation', 'Business cards', 'Personalized gifts', 'Party supplies'],
'results' => ['On the Board Before the Event', 'Events are planned months ahead. Pinned early, your designs are there when people choose.'],
'features' => ['Why Zazzle Designers Automate Pinterest', 'Be there when people plan.', [
    ['💌', 'Invitation templates', 'Elegant frames for stationery.'],
    ['📅', 'Event timing', 'Weddings, graduations and holidays pinned early.'],
    ['🔤', 'Occasion copy', 'Titles by occasion and theme.'],
    ['🗂️', 'Occasion boards', 'Sorted automatically.'],
    ['🔁', 'Many pins per design', 'Different products, different pins.'],
    ['📊', 'Top designs', 'See what people choose.'],
]],
'playbook' => ['Pinterest Tips for Zazzle Designers', 'How invitation and gift designs sell.', [
    ['Name the theme', '“Boho Floral Bridal Shower Invitation”.'],
    ['Pin wedding designs in winter', 'Planning peaks early in the year.'],
    ['Show the product in use', 'Styled photos beat flat images.'],
    ['Pin holiday cards by October', 'Holiday card searches start early.'],
    ['Group matching suites', 'Invitation, RSVP and thank-you together.'],
]],
'who' => ['Zazzle designers', 'Stationery and gift designers.', [['Stationery designers', 'Invitations on planners’ boards.'], ['Gift designers', 'Custom products for every occasion.'], ['Small brands', 'Promote a big catalogue.']]],
'faq' => [
    ['Can I pin Zazzle product pages?', 'Yes — paste your product links. Pages that can’t be read automatically show as skipped.'],
    ['When should I pin wedding stationery?', 'From winter through spring, when planning peaks.'],
    ['Do pins link to my Zazzle products?', 'Yes — each pin links to its product page.'],
],
'cta_red' => ['On the board before the event.', 'Pin your Zazzle designs automatically.'],
'cta_dark' => 'Ready to sell more on Zazzle?',
]);
uc_render_page($pdo, $user, $uc);
