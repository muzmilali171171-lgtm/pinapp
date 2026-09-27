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
'slug' => 'jewelry-store', 'name' => 'Jewelry Stores', 'short' => 'Jewelry Store', 'site' => 'Store', 'accent' => '#a16207', 'noun' => 'pieces', 'niche' => 'jewelry styles, gifts and trends',
'title' => 'Pinterest for Jewelry Stores & Jewelry Brands', 'desc' => 'Elegant pins for rings, necklaces and gift sets. AI designs jewelry pins from your photos and schedules your whole collection in 1 click.',
'kw' => 'jewelry Pinterest marketing, jewelry store Pinterest, handmade jewelry pins, engagement ring pins, jewelry gift guide Pinterest, jewelry brand traffic',
'badge' => '💎 For Jewelry Brands & Stores', 'h1' => 'Pinterest Automation for Jewelry Stores', 'h1_accent' => 'Pieces People Dream About',
'sub' => 'Engagement rings, layered necklaces, birthstone gifts — jewelry is dreamed about on Pinterest long before it’s bought. Pin every piece in elegant designs and be there before every gifting season.',
'bullets' => [['💎', 'Elegant, Minimal Templates for Fine Details'], ['🎁', 'Valentine’s, Mother’s Day & Holidays Scheduled Early'], ['🤖', 'AI Writes Titles by Metal, Stone & Occasion'], ['⚡', 'Your Whole Collection Pinned in 1 Click'], ['🗂️', 'Boards by Style and Occasion']],
'chips' => ['💎 Piece pinned', '💾 Saved to Wishlist', '🛒 Order'],
'placeholder' => 'https://yourstore.com/products/gold-birthstone-necklace',
'marquee' => ['Engagement rings', 'Layered necklaces', 'Birthstone jewelry', 'Gold hoops', 'Personalized jewelry', 'Bridal jewelry', 'Stacking rings', 'Charm bracelets', 'Minimalist jewelry', 'Gift sets'],
'results' => ['On Every Wishlist Board', 'Jewelry pins get saved to wishlists and gift boards — and revisited before every occasion.'],
'design_title' => 'Keep It Elegant', 'design_point' => 'Minimal frames, arch windows and soft palettes that let the details shine.',
'features' => ['Why Jewelry Brands Automate Pinterest', 'Be on the wishlist before the occasion.', [
    ['💎', 'Elegant templates', 'Minimal frames and soft palettes.'],
    ['🎁', 'Gift-season timing', 'Pinned 6–8 weeks before each gifting moment.'],
    ['🔤', 'Detail-rich copy', 'Metal, stone and occasion in titles.'],
    ['🧩', 'Styling collages', 'Worn and close-up shots together.'],
    ['🗂️', 'Occasion boards', 'Bridal, birthday, everyday — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes jewelry guides that feature your pieces.'],
]],
'playbook' => ['Pinterest Tips for Jewelry Brands', 'How jewelry pins become orders.', [
    ['Show it worn', 'On-model shots get the most saves.'],
    ['Name metal and stone', '“14k Gold Emerald Birthstone Necklace”.'],
    ['Pin before gifting seasons', 'Valentine’s in December, Mother’s Day in March.'],
    ['Pin styling guides', '“How to Layer Necklaces” links to many pieces.'],
    ['Keep a cohesive look', 'Recognisable pins build a luxury feel.'],
]],
'who' => ['jewelry businesses', 'Makers, brands and jewelers.', [['Handmade jewelers', 'Your pieces on wishlist boards.'], ['Jewelry brands', 'Keep every collection visible.'], ['Local jewelers', 'Bring engagement and bridal clients.']]],
'faq' => [
    ['Which templates suit jewelry?', 'Minimal frames, arch windows and soft palettes.'],
    ['When should I pin for Valentine’s Day?', 'Start in December.'],
    ['Can I pin collections and guides?', 'Yes — collections, products and guides from one scan.'],
],
'cta_red' => ['Be on the wishlist before the occasion.', 'Pin every piece automatically.'],
'cta_dark' => 'Ready to grow your jewelry brand with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
