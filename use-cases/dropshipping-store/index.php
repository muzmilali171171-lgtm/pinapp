<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = APP_NAME;

$uc = uc_build([
'slug' => 'dropshipping-store', 'name' => 'Dropshipping Stores', 'short' => 'Dropshipping Store', 'site' => 'Store', 'accent' => '#f97316', 'noun' => 'products', 'niche' => 'your product niche',
'title' => 'Pinterest for Dropshipping Stores', 'desc' => 'Test products and drive free traffic with Pinterest. AI designs product pins for your dropshipping store and schedules them in 1 click.',
'kw' => 'dropshipping Pinterest, Pinterest dropshipping traffic, free traffic dropshipping, product testing Pinterest, dropshipping marketing, Shopify dropshipping Pinterest',
'badge' => '🚚 For Dropshipping Stores', 'h1' => 'Pinterest Automation for Dropshipping Stores', 'h1_accent' => 'Free Traffic to Test Products',
'sub' => 'Ads are expensive; Pinterest traffic is free and keeps growing. Pin every product in your store, see which ones get clicks, and put your ad budget behind the winners.',
'bullets' => [['🧪', 'Pin Every Product and Find Winners'], ['🖼️', 'Lifestyle & Collage Product Templates'], ['🎯', 'AI Writes Niche, Buyer-Intent Copy'], ['⚡', 'Your Whole Store Pinned in 1 Click'], ['📊', 'Analytics Show Which Products Get Clicks']],
'chips' => ['🚚 Product pinned', '📈 Clicks rising', '🛒 Order'],
'placeholder' => 'https://yourstore.com/products/your-product',
'marquee' => ['Home gadgets', 'Pet products', 'Kitchen tools', 'Beauty tools', 'Phone accessories', 'Fitness gear', 'Baby products', 'Car accessories', 'Decor', 'Outdoor gear'],
'results' => ['Free Traffic That Grows', 'Pinterest traffic compounds instead of stopping when a campaign ends — and shows you which products people want.'],
'features' => ['Why Dropshippers Automate Pinterest', 'Test more products for less.', [
    ['🧪', 'Product testing', 'Pin everything and let clicks show demand.'],
    ['🖼️', 'Lifestyle templates', 'Show products in use.'],
    ['🎯', 'Niche copy', 'Titles for specific problems and buyers.'],
    ['💸', 'Lower ad spend', 'Organic clicks cost nothing.'],
    ['🗂️', 'Niche boards', 'Products sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes buying guides that lead to your products.'],
]],
'playbook' => ['Pinterest Playbook for Dropshippers', 'Turn free clicks into a product strategy.', [
    ['Use your own photos where possible', 'Original lifestyle photos stand out and avoid copying supplier images you don’t have rights to.'],
    ['Solve a problem in the title', '“Cordless Milk Frother for Latte Art at Home”.'],
    ['Pin every product', 'Let data pick winners.'],
    ['Watch the analytics', 'Push the products that get clicks.'],
    ['Be honest about shipping', 'Clear delivery times reduce refunds and bad reviews.'],
]],
'who' => ['dropshippers', 'Solo sellers to brands.', [['New dropshippers', 'Free traffic while you validate.'], ['Growing stores', 'Find winners before spending on ads.'], ['Brand builders', 'Build organic demand for your brand.']]],
'faq' => [
    ['Can I use supplier photos?', 'Only if your supplier allows it. Your own photos usually perform better and avoid rights issues.'],
    ['Is Pinterest good for testing products?', 'Yes — pinning every product shows which ones get clicks, for free.'],
    ['Does it work with Shopify dropshipping stores?', 'Yes — any store with public product pages.'],
],
'cta_red' => ['Free traffic, real data.', 'Pin every product and find your winners.'],
'cta_dark' => 'Ready to grow your dropshipping store with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
