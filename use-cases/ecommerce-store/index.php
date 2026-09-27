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
'slug' => 'ecommerce-store', 'name' => 'E-commerce Stores', 'short' => 'Online Store', 'site' => 'Store', 'accent' => '#e60023', 'noun' => 'products', 'niche' => 'your products and buying guides',
'title' => 'Pinterest Automation for E-commerce Stores', 'desc' => 'Pin every product in your online store automatically. AI designs product pins, writes buyer-intent copy and schedules your whole catalog in 1 click.',
'kw' => 'ecommerce Pinterest, Pinterest for online stores, product pins, ecommerce Pinterest marketing, auto pin products, online store traffic, Pinterest shopping traffic',
'badge' => '🏬 For Any Online Store', 'h1' => 'Pinterest Automation for E-commerce Stores', 'h1_accent' => 'Your Catalog, Pinned',
'sub' => 'Shopify, WooCommerce, Wix, Squarespace or a custom build — if your store has product pages, we can pin them. Turn your whole catalog into product pins and bring shoppers who are ready to buy.',
'bullets' => [['🛍️', 'Every Product Pinned — Whole Catalog in 1 Click'], ['🖼️', 'Pins From Your Real Product Photos'], ['🎯', 'AI Writes Buyer-Intent Titles & Descriptions'], ['✍️', 'Auto Blog Writes Buying Guides That Link to Products'], ['📊', 'See Which Products Pinterest Sells']],
'chips' => ['🛍️ Product pinned', '💾 Saved to Wishlist', '🛒 Order'],
'placeholder' => 'https://yourstore.com/products/your-product',
'marquee' => ['Product pins', 'Collections', 'New arrivals', 'Best sellers', 'Gift guides', 'Sales', 'Bundles', 'Seasonal drops', 'Buying guides', 'Restocks'],
'results' => ['A Catalog That Markets Itself', 'Every product page is a way in. Pinned consistently, even slow products get seen.'],
'eyebrow' => 'BUILT FOR ONLINE STORES',
'features' => ['Why Online Stores Automate Pinterest', 'Free, compounding traffic for every product.', [
    ['🛍️', 'Any platform', 'Works with any store that has public product pages.'],
    ['🖼️', 'Product photos', 'Pins use your product and gallery images.'],
    ['🎯', 'Buyer-intent copy', 'Titles written the way shoppers search.'],
    ['🔁', 'Multiple pins per product', 'New angles and designs over time.'],
    ['🎁', 'Q4 ready', 'Gift products pinned 6–8 weeks early.'],
    ['✍️', 'Auto Blog', 'AI writes buying guides with images that link to your products.'],
]],
'playbook' => ['E-commerce Pinterest Playbook', 'A routine that grows sales.', [
    ['Pin the whole catalog', 'Your long tail adds up.'],
    ['Use lifestyle photos', 'Products in use get more saves.'],
    ['Write like a shopper', '“Linen Summer Dress With Pockets”.'],
    ['Pin collections and guides', 'They catch broader searches.'],
    ['Plan Q4 in September', 'Gift searches start early.'],
]],
'who' => ['online stores', 'Any size, any platform.', [['New stores', 'Free traffic from day one.'], ['Growing brands', 'Keep every product visible.'], ['Agencies', 'Run Pinterest for many client stores.']]],
'faq' => [
    ['Which platforms are supported?', 'Any store with public product pages — Shopify, WooCommerce, Wix, Squarespace, BigCommerce or custom builds.'],
    ['Do I need to install an app?', 'No — we read your public product pages.'],
    ['Can I pin sale and collection pages?', 'Yes — select them alongside products.'],
],
'cta_red' => ['Every product deserves to be seen.', 'Pin your whole catalog automatically.'],
'cta_dark' => 'Ready to grow your store with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
