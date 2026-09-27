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
'slug' => 'kids-products', 'name' => 'Kids Product Shops', 'short' => 'Kids Shop', 'site' => 'Shop', 'accent' => '#f59e0b', 'noun' => 'products', 'niche' => 'kids products, toys and parenting',
'title' => 'Pinterest for Kids Product & Toy Shops', 'desc' => 'Pin toys, kids clothes and nursery items for parents planning purchases. AI designs product pins and schedules your whole shop in 1 click.',
'kw' => 'kids products Pinterest, toy shop Pinterest, kids clothing pins, nursery decor Pinterest, gifts for kids pins, kids brand marketing',
'badge' => '🧸 For Kids & Toy Brands', 'h1' => 'Pinterest Automation for Kids Product Shops', 'h1_accent' => 'Parents Plan Here',
'sub' => 'Parents plan birthdays, nurseries, wardrobes and holiday gifts on Pinterest. Pin your toys, clothes and nursery items so they’re on the board when parents decide.',
'bullets' => [['🧸', 'Pins for Toys, Clothes & Nursery Items'], ['🎁', 'Birthday & Holiday Gift Guides Scheduled Early'], ['🤖', 'AI Writes Titles by Age & Occasion'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Gift & Activity Guides']],
'chips' => ['🧸 Toy pinned', '💾 Saved to Gift Ideas', '🛒 Order'],
'placeholder' => 'https://yourshop.com/products/wooden-stacking-toy',
'marquee' => ['Gifts for toddlers', 'Montessori toys', 'Nursery decor', 'Kids clothes', 'Birthday gifts', 'Stocking stuffers', 'Educational toys', 'Baby essentials', 'Kids room ideas', 'Outdoor toys'],
'results' => ['On Every Gift Board', 'Parents save gift ideas months ahead. Pinned early, your products are there when they buy.'],
'features' => ['Why Kids Brands Automate Pinterest', 'Parents plan ahead — be there.', [
    ['🎁', 'Gift-guide pins', 'Number templates for “25 Gifts for 2-Year-Olds”.'],
    ['🧸', 'Playful designs', 'Bright palettes and friendly fonts.'],
    ['🔤', 'Age-aware copy', 'Titles by age and occasion.'],
    ['🎄', 'Q4 timing', 'Holiday gifts pinned in September and October.'],
    ['🗂️', 'Age boards', 'Baby, toddler, big kid — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes gift and activity guides.'],
]],
'playbook' => ['Pinterest Tips for Kids Brands', 'How parents find products.', [
    ['Always say the age', '“Toys for 3-Year-Olds” is how parents search.'],
    ['Show products in play', 'Kids using the toy beats a white background.'],
    ['Pin gift guides early', 'Holiday searches start in September.'],
    ['Pin nursery ideas', 'Expecting parents plan whole rooms.'],
    ['Keep safety info accurate', 'Age ranges and safety notes on your pages.'],
]],
'who' => ['kids brands', 'Toy, clothing and nursery brands.', [['Toy shops', 'On every gift board.'], ['Kids clothing brands', 'Seasonal wardrobes pinned ahead.'], ['Nursery brands', 'Reach expecting parents.']]],
'faq' => [
    ['When should I pin holiday gifts for kids?', 'From September — parents plan early.'],
    ['Can I pin gift guides and products together?', 'Yes — select both from one scan.'],
    ['Which templates suit kids products?', 'Bright, playful templates and roundup numbers.'],
],
'cta_red' => ['Be on the board when parents decide.', 'Pin your whole shop automatically.'],
'cta_dark' => 'Ready to grow your kids brand with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
