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
'slug' => 'big-cartel', 'name' => 'Big Cartel Shops', 'short' => 'Big Cartel Shop', 'site' => 'Shop', 'accent' => '#f43f5e', 'noun' => 'products', 'niche' => 'your shop’s products',
'title' => 'Pinterest for Big Cartel Shops & Makers', 'desc' => 'Give every product in your Big Cartel shop its own Pinterest pins. AI designs pins from your product photos and schedules them in 1 click.',
'kw' => 'Big Cartel Pinterest, Big Cartel marketing, promote Big Cartel shop, Big Cartel products Pinterest, artist shop Pinterest, maker shop traffic',
'badge' => '🛒 For Big Cartel Artists & Makers', 'h1' => 'Pinterest Automation for Big Cartel Shops', 'h1_accent' => 'Your Art, In More Feeds',
'sub' => 'Big Cartel is made for artists and makers — and so is Pinterest. Turn every product in your shop into pins that show off your work and bring buyers straight to your store.',
'bullets' => [['🎨', 'Pins Made From Your Product Photos'], ['🧵', 'Artwork, Prints, Apparel & Handmade Goods'], ['🤖', 'AI Writes Titles Buyers Search'], ['⚡', 'Every Product Pinned in 1 Click'], ['🗂️', 'Boards by Collection and Style']],
'chips' => ['🎨 Product pinned', '💾 Saved to Wishlist', '🛒 Shop visit'],
'placeholder' => 'https://yourshop.bigcartel.com/product/your-product',
'marquee' => ['Art prints', 'Enamel pins', 'Zines', 'Band merch', 'Stickers', 'Ceramics', 'Screen prints', 'Tees', 'Patches', 'Handmade jewelry'],
'results' => ['Small Shop, Big Reach', 'Every product is another way into your shop. Pinned regularly, they add up to steady discovery.'],
'features' => ['Why Big Cartel Makers Automate Pinterest', 'More time making, more people finding you.', [
    ['🎨', 'Art-first templates', 'Minimal frames that let your work lead.'],
    ['🧩', 'Collages', 'Show several products or angles in one pin.'],
    ['🔤', 'Niche copy', 'AI writes titles like buyers search.'],
    ['🎁', 'Gift-season timing', 'Holiday products pinned 6–8 weeks early.'],
    ['🗂️', 'Collection boards', 'Pins sorted by collection.'],
    ['⏱️', 'Hands-off', 'Set it once, keep making.'],
]],
'playbook' => ['Pinterest Tips for Big Cartel Shops', 'How makers get discovered.', [
    ['Photograph in context', 'Prints on a wall, pins on a jacket.'],
    ['Describe the style', '“Retro Mushroom Enamel Pin” is searchable.'],
    ['Pin each product several times', 'Different photos over weeks.'],
    ['Plan for gift season', 'Pin in September and October.'],
    ['Pin new drops fast', 'Run the wizard on each new release.'],
]],
'who' => ['makers', 'Artists, bands and small brands.', [['Artists', 'Show your prints and originals to collectors.'], ['Bands & creators', 'Pin merch to fans.'], ['Small brands', 'Keep every product visible.']]],
'faq' => [
    ['Can I pin my Big Cartel products?', 'Yes — paste product links, or scan your shop if its sitemap is available. Pages that can’t be read show as skipped.'],
    ['Do pins link to my shop?', 'Yes — each pin links to its product page.'],
    ['Which templates suit artwork?', 'Minimal frames and collages keep the focus on your work.'],
],
'cta_red' => ['Your art deserves more eyes.', 'Pin every product automatically.'],
'cta_dark' => 'Ready to grow your Big Cartel shop?',
]);
uc_render_page($pdo, $user, $uc);
