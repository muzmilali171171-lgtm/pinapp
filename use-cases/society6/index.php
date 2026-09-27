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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-hashtag-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'society6', 'name' => 'Society6 Artists', 'short' => 'Society6 Shop', 'site' => 'Shop', 'accent' => '#111111', 'noun' => 'artworks', 'niche' => 'art, home decor and your artistic style',
'title' => 'Pinterest for Society6 Artists & Art Shops', 'desc' => 'Turn your Society6 art and products into pins that sell. AI designs elegant pins for prints, decor and gifts and schedules them in 1 click.',
'kw' => 'Society6 Pinterest, promote Society6 art, Society6 marketing, art prints Pinterest, home decor art pins, sell art online Pinterest',
'badge' => '🖼️ For Society6 Artists', 'h1' => 'Pinterest Automation for Society6 Artists', 'h1_accent' => 'Art People Hang at Home',
'sub' => 'People decorate their homes from Pinterest boards. Pin your prints, wall art, pillows and decor pieces so your art ends up on their boards — and on their walls.',
'bullets' => [['🖼️', 'Elegant Pins for Prints & Wall Art'], ['🛋️', 'Decor Products Shown in Room Settings'], ['🔤', 'AI Writes Titles by Style, Room & Colour'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['🗂️', 'Boards by Style: Boho, Minimal, Botanical…']],
'chips' => ['🖼️ Print pinned', '💾 Saved to Living Room', '🛒 Sale'],
'placeholder' => 'https://society6.com/product/your-artwork_print',
'marquee' => ['Wall art', 'Art prints', 'Botanical prints', 'Abstract art', 'Throw pillows', 'Wall tapestries', 'Boho decor', 'Gallery walls', 'Minimalist art', 'Shower curtains'],
'results' => ['Art That Ends Up on Walls', 'Home decor pins get saved to room boards and revisited for months — steady discovery for your art.'],
'design_title' => 'Frame Your Art Beautifully', 'design_point' => 'Arch frames, gallery layouts and neutral palettes.',
'features' => ['Why Society6 Artists Automate Pinterest', 'Your art, on people’s decor boards.', [
    ['🖼️', 'Gallery templates', 'Minimal frames for prints and wall art.'],
    ['🛋️', 'Room settings', 'Room mockups shown in collages.'],
    ['🎨', 'Style-aware copy', 'Titles with style, colour and room.'],
    ['🗂️', 'Decor boards', 'Boho, minimal, botanical — sorted.'],
    ['🔁', 'Many pins per artwork', 'Print, pillow and tapestry versions.'],
    ['📊', 'Top artworks', 'See which pieces people love.'],
]],
'playbook' => ['Pinterest Tips for Art Sellers', 'How art gets saved to decor boards.', [
    ['Show art in a room', 'Mockups help people imagine it at home.'],
    ['Name the style and colours', '“Sage Green Botanical Print for Bedroom”.'],
    ['Pin gallery-wall sets', 'Collections get saved together.'],
    ['Pin decor seasonally', 'Autumn and holiday decor weeks ahead.'],
    ['Keep a cohesive palette', 'Your pins become recognisable.'],
]],
'who' => ['artists', 'Illustrators, painters and photographers.', [['Illustrators', 'Your art on people’s decor boards.'], ['Photographers', 'Sell photo prints to room planners.'], ['Pattern designers', 'Pin decor products with your patterns.']]],
'faq' => [
    ['Can I pin Society6 products?', 'Yes — paste your product links. Pages that can’t be read automatically show as skipped; you can pin the same art from your own site.'],
    ['Which templates suit art?', 'Minimal frames, arch frames and gallery collages.'],
    ['Do pins link to Society6?', 'Yes — each pin links to its page.'],
],
'cta_red' => ['Your art, on their walls.', 'Pin every artwork automatically.'],
'cta_dark' => 'Ready to sell more art with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
