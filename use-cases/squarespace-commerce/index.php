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
'slug' => 'squarespace-commerce', 'name' => 'Squarespace Commerce', 'short' => 'Squarespace Store', 'site' => 'Squarespace Site', 'accent' => '#1f2937', 'noun' => 'products', 'niche' => 'your store and blog topics',
'title' => 'Pinterest for Squarespace Stores & Sites', 'desc' => 'Pin every Squarespace product and blog post automatically. We read your sitemap, design on-brand pins with AI and schedule them in 1 click.',
'kw' => 'Squarespace Pinterest, Squarespace commerce Pinterest, Squarespace store marketing, auto pin Squarespace, Squarespace blog Pinterest, Squarespace traffic',
'badge' => '◼️ For Squarespace Commerce', 'h1' => 'Pinterest Automation for Squarespace Commerce', 'h1_accent' => 'Beautiful Store, Busier Store',
'sub' => 'Your Squarespace store already looks great. Pinterest is where shoppers discover it. Turn every product and blog post into polished pins that match your brand — scheduled for months.',
'bullets' => [['🗺️', 'Reads Your Squarespace Sitemap'], ['🛍️', 'Products, Collections & Blog Posts'], ['🎨', 'Minimal, Elegant Templates That Suit Squarespace'], ['⚡', 'Your Whole Store Pinned in 1 Click'], ['🔌', 'Nothing to Install']],
'chips' => ['◼️ Product pinned', '💾 Saved to Wishlist', '🛒 Store visit'],
'placeholder' => 'https://yourstore.com/shop/p/your-product',
'marquee' => ['Home goods', 'Candles', 'Ceramics', 'Apparel', 'Stationery', 'Wellness', 'Art prints', 'Jewelry', 'Beauty', 'Services'],
'results' => ['A Polished Store, Found Every Day', 'Every product and post is a way in. Pinned consistently, they bring steady shoppers.'],
'features' => ['Why Squarespace Stores Automate Pinterest', 'Pins that look as good as your site.', [
    ['🗺️', 'Sitemap scanning', 'Squarespace’s sitemap lists every product and post.'],
    ['◼️', 'Minimal templates', 'Clean, editorial layouts that suit your brand.'],
    ['🎨', 'Brand palette', 'Your colours and fonts on every pin.'],
    ['🛍️', 'Products + blog', 'Pin both from one scan.'],
    ['🎁', 'Seasonal timing', 'Holiday collections pinned early.'],
    ['🗂️', 'Smart boards', 'AI places every pin.'],
]],
'playbook' => ['Pinterest Tips for Squarespace Stores', 'Turn a beautiful store into a busy one.', [
    ['Pin every product', 'Not just best sellers.'],
    ['Use lifestyle photos', 'Products in use get more saves.'],
    ['Pin blog posts that feature products', 'Guides warm up buyers.'],
    ['Keep a consistent look', 'Recognisable pins build a brand.'],
    ['Plan for Q4', 'Gift products in September and October.'],
]],
'who' => ['Squarespace sellers', 'Brands, creators and studios.', [['Product brands', 'Keep every product visible.'], ['Creators', 'Pin courses, prints and digital products.'], ['Studios & services', 'Pin your portfolio and services.']]],
'faq' => [
    ['Does it work with Squarespace sitemaps?', 'Yes — Squarespace publishes a sitemap, and we read it to list products and posts.'],
    ['Do I need to install anything?', 'No — we read your public pages.'],
    ['Can pins match my site design?', 'Yes — set your brand palette and fonts, or design your own template in the free editor.'],
],
'cta_red' => ['A beautiful store deserves shoppers.', 'Pin every product automatically.'],
'cta_dark' => 'Ready to grow your Squarespace store?',
]);
uc_render_page($pdo, $user, $uc);
