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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'interior-design', 'name' => 'Interior Designers', 'short' => 'Interior Design Website', 'site' => 'Website', 'accent' => '#78716c', 'noun' => 'projects', 'niche' => 'interior design and room ideas',
'title' => 'Pinterest for Interior Designers: Win Clients', 'desc' => 'Portfolio rooms pinned for people planning their homes. AI designs elegant pins from your projects and schedules them in 1 click.',
'kw' => 'interior designer Pinterest, interior design marketing, room ideas pins, interior design portfolio Pinterest, home design Pinterest, e-design Pinterest',
'badge' => '🏠 For Interior Designers', 'h1' => 'Pinterest Automation for Interior Designers', 'h1_accent' => 'Rooms Clients Want',
'sub' => 'Homeowners plan every room on Pinterest. Pin your portfolio projects, design guides and e-design packages so they find your style — and your contact form.',
'bullets' => [['🛋️', 'Elegant Pins From Your Portfolio Rooms'], ['🧩', 'Room Collages: Wide Shots & Details'], ['🤖', 'AI Writes Titles by Room, Style & Palette'], ['⚡', 'Your Whole Portfolio Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Design Guides']],
'chips' => ['🏠 Room pinned', '💾 Saved to Living Room', '📩 Consultation'],
'placeholder' => 'https://yourstudio.com/projects/modern-farmhouse-kitchen/',
'marquee' => ['Living rooms', 'Kitchens', 'Bedrooms', 'Japandi', 'Modern farmhouse', 'Coastal', 'Small spaces', 'Home offices', 'Bathrooms', 'E-design'],
'results' => ['Your Style, on Their Boards', 'Room pins get saved and revisited through a whole renovation — your work stays in front of future clients.'],
'features' => ['Why Interior Designers Automate Pinterest', 'Be the style they’re pinning.', [
    ['🛋️', 'Editorial templates', 'Arch frames and minimal layouts.'],
    ['🧩', 'Room collages', 'Wide shots and details together.'],
    ['🔤', 'Style copy', 'By room, style and palette.'],
    ['🎨', 'Brand palette', 'Your studio colours on every pin.'],
    ['🗂️', 'Room boards', 'Sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes design guides with images.'],
]],
'playbook' => ['Pinterest Tips for Interior Designers', 'From saved room to signed client.', [
    ['Pin every room separately', 'Kitchen, bath and bedroom reach different searchers.'],
    ['Name the style', '“Warm Japandi Living Room”.'],
    ['Show details', 'Hardware, textiles and styling get saved.'],
    ['Pin design guides', 'Guides build trust before a consultation.'],
    ['Link to your contact page', 'Make enquiries easy.'],
]],
'who' => ['designers', 'Studios, e-designers and stylists.', [['Interior design studios', 'Consultations from homeowners.'], ['E-designers', 'Clients from anywhere.'], ['Home stylists', 'Show your styling work.']]],
'faq' => [
    ['Can I pin project photos?', 'Yes — use photos you own or have rights to, and credit photographers.'],
    ['Which templates suit interiors?', 'Arch frames, editorial and collage templates with neutral palettes.'],
    ['Can pins lead to consultations?', 'Pins link to your pages — add a clear consultation call to action there.'],
],
'cta_red' => ['Rooms clients want.', 'Pin your portfolio automatically.'],
'cta_dark' => 'Ready to win clients from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
