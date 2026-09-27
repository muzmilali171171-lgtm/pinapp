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
'slug' => 'sustainable-products', 'name' => 'Sustainable Product Shops', 'short' => 'Eco Product Shop', 'site' => 'Shop', 'accent' => '#15803d', 'noun' => 'products', 'niche' => 'zero-waste and sustainable living',
'title' => 'Pinterest for Eco & Zero-Waste Product Shops', 'desc' => 'Reach eco-minded buyers with automatic product pins. AI turns your sustainable products and zero-waste guides into pins and schedules them in 1 click.',
'kw' => 'zero waste Pinterest, eco products Pinterest, sustainable living pins, eco shop marketing, plastic free swaps pins, green products traffic',
'badge' => '♻️ For Eco & Zero-Waste Shops', 'h1' => 'Pinterest Automation for Sustainable Product Shops', 'h1_accent' => 'Swaps People Save',
'sub' => '“Plastic-free swaps”, “zero-waste kitchen”, “eco gifts” — people plan greener lives on Pinterest. Pin your products and guides so they find your shop when they’re ready to switch.',
'bullets' => [['♻️', 'Pins for Products, Swaps & Guides'], ['🔢', '“20 Easy Zero-Waste Swaps” Roundup Templates'], ['🤖', 'AI Writes Titles Around Swaps & Benefits'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Sustainable Living Guides']],
'chips' => ['♻️ Swap pinned', '💾 Saved to Zero Waste', '🛒 Order'],
'placeholder' => 'https://yourshop.com/products/bamboo-toothbrush-set',
'marquee' => ['Zero-waste swaps', 'Plastic-free kitchen', 'Eco gifts', 'Refillables', 'Beeswax wraps', 'Natural cleaning', 'Reusable bags', 'Compost', 'Eco beauty', 'Minimal living'],
'results' => ['Green Guides That Keep Selling', 'Swap guides get saved and shared. Pinned consistently, they keep bringing buyers to your products.'],
'features' => ['Why Eco Shops Automate Pinterest', 'Help more people switch.', [
    ['♻️', 'Swap roundups', 'Number templates for swap lists.'],
    ['🌿', 'Natural designs', 'Earthy palettes and clean layouts.'],
    ['🔤', 'Benefit-led copy', 'Titles around the swap and the benefit.'],
    ['🎁', 'Eco gift guides', 'Holiday guides pinned early.'],
    ['🗂️', 'Room boards', 'Kitchen, bathroom, travel — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes eco-living guides that feature your products.'],
]],
'playbook' => ['Pinterest Tips for Eco Shops', 'Educate first, sell second.', [
    ['Pin swap lists', 'They’re among the most-saved eco pins.'],
    ['Room by room', '“Zero-Waste Bathroom Swaps”.'],
    ['Back up claims', 'Keep environmental claims accurate.'],
    ['Pin gift guides early', 'Eco gift guides in October.'],
    ['Show products in use', 'Real kitchens and bathrooms, not white backgrounds.'],
]],
'who' => ['eco businesses', 'Shops, refilleries and brands.', [['Zero-waste shops', 'Reach people ready to switch.'], ['Eco brands', 'Pin products and education together.'], ['Refill stores', 'Bring local and online customers.']]],
'faq' => [
    ['Can I pin guides and products together?', 'Yes — select both from one scan.'],
    ['What about eco claims?', 'Keep environmental claims accurate and supported on your pages.'],
    ['Which templates fit eco brands?', 'Minimal, earthy templates and roundup numbers.'],
],
'cta_red' => ['Help more people make the switch.', 'Pin your eco products automatically.'],
'cta_dark' => 'Ready to grow your eco shop with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
