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
'slug' => 'printables-shop', 'name' => 'Printables Shops', 'short' => 'Printables Shop', 'site' => 'Shop', 'accent' => '#0ea5e9', 'noun' => 'printables', 'niche' => 'printables, planners and printable art',
'title' => 'Pinterest for Printables Shops & Sellers', 'desc' => 'Printables are Pinterest favourites. Pin your planners, wall art, worksheets and party printables automatically — hundreds in 1 click.',
'kw' => 'printables Pinterest, sell printables, printable wall art pins, printable planner Pinterest, worksheets Pinterest, party printables pins, printables shop traffic',
'badge' => '🖨️ For Printables Sellers', 'h1' => 'Pinterest Automation for Printables Shops', 'h1_accent' => 'Printed, Saved, Sold',
'sub' => 'Planners, wall art, worksheets, invitations, party games — printables are among the most-saved pins on Pinterest. Pin every product with previews that show exactly what buyers get.',
'bullets' => [['🖨️', 'Preview Pins for Every Printable'], ['🎉', 'Holiday & Party Printables Scheduled Early'], ['🤖', 'AI Writes Titles by Use & Occasion'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['🎁', 'Free Printables That Lead to Paid Ones']],
'chips' => ['🖨️ Printable pinned', '💾 Saved to Party Ideas', '💳 Sale'],
'placeholder' => 'https://yourshop.com/products/baby-shower-games-printable',
'marquee' => ['Wall art', 'Party games', 'Invitations', 'Worksheets', 'Chore charts', 'Meal planners', 'Coloring pages', 'Gift tags', 'Budget sheets', 'Bible study'],
'results' => ['Printables That Keep Selling', 'Printables get saved for the next party, the next school week, the next holiday — and bought when the moment comes.'],
'features' => ['Why Printables Sellers Automate Pinterest', 'Your products are made for Pinterest.', [
    ['🖨️', 'Preview pins', 'Show the printable itself.'],
    ['🎉', 'Occasion timing', 'Holiday and party printables pinned early.'],
    ['🔤', 'Use-case copy', '“Printable Chore Chart for Kids”.'],
    ['🎁', 'Freebie funnels', 'Pin free printables that lead to your shop.'],
    ['🗂️', 'Category boards', 'Party, planning, kids, wall art — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes posts that feature your printables.'],
]],
'playbook' => ['Pinterest Tips for Printables Sellers', 'How printables sell on Pinterest.', [
    ['Show the printable in use', 'On the fridge, framed, at the party.'],
    ['Say the occasion', '“Baby Shower Games Printable”.'],
    ['Pin holidays 6–8 weeks early', 'Christmas printables in October.'],
    ['Offer free versions', 'Freebies build trust and lead to paid packs.'],
    ['Make several pins per product', 'Different previews reach different buyers.'],
]],
'who' => ['printables sellers', 'Etsy sellers and independent shops.', [['Etsy printables sellers', 'Your listings in front of savers.'], ['Independent shops', 'Pin products and freebies.'], ['Teachers & educators', 'Pin worksheets and classroom printables.']]],
'faq' => [
    ['Do printables work as pin images?', 'Yes — the preview images on your product page become the pins.'],
    ['When should I pin holiday printables?', 'About 6–8 weeks before the holiday.'],
    ['Can I pin free printables?', 'Yes — they’re a great way to lead people to paid products.'],
],
'cta_red' => ['Printed, saved, sold.', 'Pin every printable automatically.'],
'cta_dark' => 'Ready to sell more printables with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
