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
'slug' => 'spoonflower', 'name' => 'Spoonflower Designers', 'short' => 'Spoonflower Shop', 'site' => 'Shop', 'accent' => '#f97316', 'noun' => 'designs', 'niche' => 'fabric, wallpaper and sewing projects',
'title' => 'Pinterest for Spoonflower Surface Designers', 'desc' => 'Pin your fabric, wallpaper and home decor designs automatically. AI designs pins for your patterns and schedules them for sewists and decorators in 1 click.',
'kw' => 'Spoonflower Pinterest, surface pattern design Pinterest, fabric design pins, wallpaper design Pinterest, promote Spoonflower shop, sewing fabric pins',
'badge' => '🧶 For Spoonflower Designers', 'h1' => 'Pinterest Automation for Spoonflower Designers', 'h1_accent' => 'Patterns People Sew With',
'sub' => 'Sewists, quilters and decorators plan projects on Pinterest. Pin your fabric, wallpaper and home decor designs so your patterns end up in their next project.',
'bullets' => [['🧵', 'Pins for Fabric, Wallpaper & Home Decor'], ['🖼️', 'Collages Show Pattern + Finished Project'], ['🔤', 'AI Writes Titles by Motif, Colour & Use'], ['⚡', 'Your Whole Collection Pinned in 1 Click'], ['🗂️', 'Boards by Theme and Project Type']],
'chips' => ['🧶 Pattern pinned', '💾 Saved to Sewing Projects', '🛒 Fabric order'],
'placeholder' => 'https://www.spoonflower.com/en/fabric/your-design',
'marquee' => ['Floral fabric', 'Kids prints', 'Quilting cotton', 'Wallpaper', 'Holiday fabric', 'Botanical patterns', 'Geometric prints', 'Nursery decor', 'Retro patterns', 'Sewing projects'],
'results' => ['Patterns That Inspire Projects', 'Project ideas get saved and revisited — and your patterns go along with them.'],
'features' => ['Why Surface Designers Automate Pinterest', 'Put your patterns into people’s project plans.', [
    ['🧵', 'Pattern pins', 'Clean templates that show the repeat.'],
    ['🖼️', 'Project collages', 'Pattern plus finished item in one pin.'],
    ['🔤', 'Motif-aware copy', 'Titles by motif, colour and use.'],
    ['🎄', 'Seasonal fabric', 'Holiday prints pinned months ahead for sewists.'],
    ['🗂️', 'Theme boards', 'Florals, kids, holiday — sorted.'],
    ['📊', 'Top patterns', 'See which designs get clicks.'],
]],
'playbook' => ['Pinterest Tips for Surface Designers', 'How patterns get into projects.', [
    ['Show a finished project', 'A dress or quilt sells the fabric.'],
    ['Name motif and colour', '“Mustard Floral Cotton Fabric”.'],
    ['Pin holiday prints early', 'Sewists start months before the holiday.'],
    ['Group collections', 'Coordinating prints get saved together.'],
    ['Pin wallpaper for rooms', 'Room mockups for decorators.'],
]],
'who' => ['surface designers', 'Pattern designers and illustrators.', [['Surface designers', 'Your patterns in people’s projects.'], ['Illustrators', 'Turn art into fabric sales.'], ['Collection designers', 'Pin coordinating collections together.']]],
'faq' => [
    ['Can I pin Spoonflower design pages?', 'Yes — paste your design links. Pages that can’t be read automatically show as skipped; you can pin from your own site or blog.'],
    ['Which templates suit patterns?', 'Clean, minimal templates that show the repeat, and collages with project photos.'],
    ['When should I pin holiday fabric?', 'Two to three months ahead — sewists start early.'],
],
'cta_red' => ['Patterns people sew with.', 'Pin every design automatically.'],
'cta_dark' => 'Ready to get your patterns into projects?',
]);
uc_render_page($pdo, $user, $uc);
