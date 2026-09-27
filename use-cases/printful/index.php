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
'slug' => 'printful', 'name' => 'Printful Stores', 'short' => 'Printful Store', 'site' => 'Store', 'accent' => '#ff6a4d', 'noun' => 'products', 'niche' => 'your Printful product niche',
'title' => 'Pinterest Automation for Printful Stores', 'desc' => 'Pin every Printful product in your store automatically. AI designs pins from your mockups, writes niche copy and schedules months of pins in 1 click.',
'kw' => 'Printful Pinterest, Printful marketing, promote Printful products, Printful Shopify Pinterest, POD Pinterest traffic, Printful store growth',
'badge' => '👚 For Printful Sellers', 'h1' => 'Pinterest Automation for Printful Stores', 'h1_accent' => 'Mockups That Sell',
'sub' => 'Printful fulfils your orders — we help you get them. Pin every product Printful syncs to your store, from embroidered hats to all-over-print hoodies, and keep Pinterest shoppers coming daily.',
'bullets' => [['🔗', 'Works With the Store Printful Syncs To'], ['🖼️', 'Pins From Your Printful Mockups'], ['🤖', 'AI Writes Product Titles & Descriptions'], ['⚡', 'Your Whole Catalog Pinned in 1 Click'], ['🎯', 'Boards by Niche, Chosen by AI']],
'chips' => ['👚 Product pinned', '💾 Saved', '🛒 Order'],
'placeholder' => 'https://yourstore.com/products/embroidered-hat',
'marquee' => ['Embroidered hats', 'Hoodies', 'All-over print', 'Mugs', 'Posters', 'Canvas prints', 'Tote bags', 'Phone cases', 'Leggings', 'Stickers'],
'results' => ['Mockups That Keep Selling', 'Every product becomes a steady source of Pinterest shoppers, without ad spend.'],
'features' => ['Why Printful Sellers Automate Pinterest', 'Premium products deserve premium promotion.', [
    ['🔗', 'Store-based', 'Pins come from your Shopify, WooCommerce, Etsy or other store.'],
    ['🖼️', 'Mockup-friendly', 'Clean templates and collages for lifestyle mockups.'],
    ['🔤', 'Niche copy', 'AI writes titles for your audience.'],
    ['🎁', 'Gift timing', 'Holiday products pinned early.'],
    ['📊', 'Winner tracking', 'Analytics show your best products.'],
    ['✍️', 'Auto Blog', 'AI writes gift guides that feature your products.'],
]],
'playbook' => ['Pinterest Tips for Printful Sellers', 'Grow orders without ads.', [
    ['Use Printful’s lifestyle mockups', 'They get more saves than flat images.'],
    ['Pin each product type', 'Hat buyers and poster buyers search differently.'],
    ['Write for the recipient', '“Gift for Hiking Dad” style titles.'],
    ['Pin new products fast', 'Run the wizard after each launch.'],
    ['Double down on winners', 'More pins for products that get clicks.'],
]],
'who' => ['Printful sellers', 'Solo to multi-store.', [['New sellers', 'Free traffic from day one.'], ['Multi-store sellers', 'Manage all stores in one place.'], ['Merch brands', 'Keep every product visible.']]],
'faq' => [
    ['Does it connect to Printful?', 'No connection is needed — we pin products from the store Printful syncs to.'],
    ['Will my mockups be used?', 'Yes — pins use the images on each product page.'],
    ['Which stores work best?', 'Shopify and WooCommerce, because their sitemaps list every product.'],
],
'cta_red' => ['Premium products, promoted daily.', 'Pin every Printful product automatically.'],
'cta_dark' => 'Ready to grow your Printful store?',
]);
uc_render_page($pdo, $user, $uc);
