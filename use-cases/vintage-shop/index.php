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
'slug' => 'vintage-shop', 'name' => 'Vintage Shops', 'short' => 'Vintage Shop', 'site' => 'Shop', 'accent' => '#b45309', 'noun' => 'items', 'niche' => 'vintage fashion, decor and collectibles',
'title' => 'Pinterest for Vintage Shops & Resellers', 'desc' => 'Give every one-of-a-kind vintage find its own Pinterest pins. AI designs pins from your photos and schedules them fast — in 1 click.',
'kw' => 'vintage shop Pinterest, vintage reseller marketing, vintage clothing pins, vintage decor Pinterest, thrift reseller traffic, retro fashion pins',
'badge' => '🕰️ For Vintage Sellers & Resellers', 'h1' => 'Pinterest Automation for Vintage Shops', 'h1_accent' => 'One-of-a-Kind, Found Fast',
'sub' => 'Vintage items are one of a kind — they need to be seen quickly. Pin every find as soon as it’s listed and let collectors and retro lovers discover your shop on Pinterest.',
'bullets' => [['🕰️', 'Pins for Clothing, Decor & Collectibles'], ['⚡', 'New Listings Pinned in 1 Click'], ['🤖', 'AI Writes Titles by Era, Style & Brand'], ['🎨', 'Warm, Retro Palettes & Fonts'], ['🗂️', 'Boards by Decade and Category']],
'chips' => ['🕰️ Find pinned', '💾 Saved to 70s Style', '🛒 Sold'],
'placeholder' => 'https://yourvintageshop.com/products/70s-suede-jacket',
'marquee' => ['70s fashion', 'Mid-century decor', 'Vintage denim', 'Retro kitchen', 'Vintage jewelry', 'Y2K', 'Band tees', 'Vintage dresses', 'Collectibles', 'Antiques'],
'results' => ['Unique Items, Found Quickly', 'Collectors save and search by era and style. Pinning each find gives it more chances to sell.'],
'features' => ['Why Vintage Sellers Automate Pinterest', 'Speed matters when every item is unique.', [
    ['⚡', 'Pin new stock fast', 'Run the wizard on each new batch.'],
    ['🕰️', 'Era-aware copy', 'Titles by decade, style and brand.'],
    ['🎨', 'Retro designs', 'Warm palettes and vintage fonts.'],
    ['🧩', 'Detail collages', 'Labels, textures and full view in one pin.'],
    ['🗂️', 'Decade boards', 'Organised automatically.'],
    ['📊', 'What sells', 'See which eras and categories get clicks.'],
]],
'playbook' => ['Pinterest Tips for Vintage Sellers', 'Get one-of-a-kind items seen.', [
    ['Name the era', '“1970s Suede Fringe Jacket”.'],
    ['Show details', 'Labels, stitching and condition build trust.'],
    ['Style it', 'Styled photos beat hangers.'],
    ['Pin sold items as inspiration', 'Guides and style posts keep traffic flowing.'],
    ['Pin new stock right away', 'Unique items sell faster when seen early.'],
]],
'who' => ['vintage sellers', 'Resellers, curators and antique shops.', [['Vintage resellers', 'Every find seen fast.'], ['Curated vintage shops', 'Keep your aesthetic visible.'], ['Antique dealers', 'Reach collectors online.']]],
'faq' => [
    ['What happens to pins when an item sells?', 'You can remove scheduled pins for sold items, or keep pins that lead to similar items or your shop.'],
    ['Can I pin new stock quickly?', 'Yes — scan and approve new listings in minutes.'],
    ['Which templates suit vintage?', 'Warm palettes, framed photos and collages.'],
],
'cta_red' => ['One of a kind, found fast.', 'Pin every vintage find automatically.'],
'cta_dark' => 'Ready to sell more vintage with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
