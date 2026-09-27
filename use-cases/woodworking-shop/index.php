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
'slug' => 'woodworking-shop', 'name' => 'Woodworking Shops', 'short' => 'Woodworking Shop', 'site' => 'Shop', 'accent' => '#92400e', 'noun' => 'products', 'niche' => 'woodworking projects and handmade furniture',
'title' => 'Pinterest for Woodworking Shops & Makers', 'desc' => 'Showcase handmade woodwork in warm, crafted pins. AI designs pins for your pieces, plans and projects and schedules them in 1 click.',
'kw' => 'woodworking Pinterest, handmade furniture Pinterest, woodworking shop marketing, woodworking plans pins, cutting board pins, woodworker traffic',
'badge' => '🪵 For Woodworkers & Furniture Makers', 'h1' => 'Pinterest Automation for Woodworking Shops', 'h1_accent' => 'Handmade, Seen Widely',
'sub' => 'From cutting boards to dining tables to downloadable plans — woodworking is loved on Pinterest. Pin every piece and project so buyers and makers find your shop.',
'bullets' => [['🪵', 'Pins for Furniture, Decor & Woodworking Plans'], ['🧩', 'Detail Collages: Grain, Joinery & Finish'], ['🤖', 'AI Writes Titles by Wood, Style & Room'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['🎁', 'Gift Items Scheduled Before the Holidays']],
'chips' => ['🪵 Piece pinned', '💾 Saved to Dream Home', '🛒 Order'],
'placeholder' => 'https://yourshop.com/products/walnut-cutting-board',
'marquee' => ['Cutting boards', 'Dining tables', 'Floating shelves', 'Woodworking plans', 'Farmhouse furniture', 'Wood signs', 'Charcuterie boards', 'Beginner projects', 'Wood toys', 'Custom furniture'],
'results' => ['Craftsmanship That Gets Saved', 'Handmade pieces and plans get saved by buyers and makers alike — steady traffic for your shop.'],
'features' => ['Why Woodworkers Automate Pinterest', 'Spend time in the shop, not on pins.', [
    ['🪵', 'Warm designs', 'Earthy palettes that suit wood.'],
    ['🧩', 'Detail collages', 'Grain, joinery and finish in one pin.'],
    ['📐', 'Plans & projects', 'Pin downloadable plans and tutorials too.'],
    ['🔤', 'Material-aware copy', 'Walnut, oak, farmhouse, modern.'],
    ['🎁', 'Gift timing', 'Boards and small items pinned before holidays.'],
    ['✍️', 'Auto Blog', 'AI writes project posts that feature your work.'],
]],
'playbook' => ['Pinterest Tips for Woodworkers', 'How handmade woodwork gets found.', [
    ['Show the piece in a room', 'Context sells furniture.'],
    ['Name the wood and style', '“Walnut Mid-Century Side Table”.'],
    ['Pin close-ups', 'Grain and joinery show quality.'],
    ['Sell plans too', 'Makers save plans by the thousands.'],
    ['Pin gifts early', 'Cutting boards and décor before the holidays.'],
]],
'who' => ['woodworkers', 'Makers, studios and plan sellers.', [['Furniture makers', 'Custom orders from room planners.'], ['Small-goods makers', 'Boards, signs and décor as gifts.'], ['Plan sellers', 'Plans in front of other makers.']]],
'faq' => [
    ['Can I pin woodworking plans?', 'Yes — pin plan pages and tutorials alongside products.'],
    ['Which templates suit woodwork?', 'Warm palettes, framed photos and detail collages.'],
    ['Can I take custom orders through pins?', 'Pins link to your pages — make sure they explain how to order.'],
],
'cta_red' => ['Handmade, seen widely.', 'Pin every piece automatically.'],
'cta_dark' => 'Ready to grow your woodworking shop?',
]);
uc_render_page($pdo, $user, $uc);
