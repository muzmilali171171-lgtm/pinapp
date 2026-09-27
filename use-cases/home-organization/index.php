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
'slug' => 'home-organization', 'name' => 'Home Organization', 'short' => 'Home Organization Blog', 'site' => 'Blog', 'accent' => '#0d9488', 'noun' => 'posts', 'niche' => 'home organization and cleaning',
'title' => 'Pinterest for Home Organization & Cleaning Blogs', 'desc' => 'Before-and-afters, storage ideas and cleaning schedules get saved by millions. AI turns your organizing posts into pins and schedules them in 1 click.',
'kw' => 'home organization Pinterest, organizing ideas pins, decluttering Pinterest, cleaning schedule pins, pantry organization pins, storage ideas Pinterest, organizing blog traffic',
'badge' => '🧺 For Organizing & Cleaning Bloggers', 'h1' => 'Pinterest Automation for Home Organization Blogs', 'h1_accent' => 'Tidy Pins, Steady Traffic',
'sub' => 'Organizing is one of Pinterest’s favourite topics — pantries, closets, small spaces, cleaning routines. Turn every idea and makeover into satisfying pins and keep them publishing all year.',
'bullets' => [['↔️', 'Before-and-After Collages That Get Saved'], ['🔢', 'Idea Roundups: “25 Small Closet Ideas”'], ['🤖', 'AI Writes Titles by Room & Problem'], ['⚡', 'Your Whole Blog Pinned in 1 Click'], ['🗓️', 'January & Spring-Cleaning Peaks Scheduled Ahead']],
'chips' => ['🧺 Idea pinned', '💾 Saved to Organize', '📈 Visits up'],
'placeholder' => 'https://yourblog.com/small-pantry-organization-ideas/',
'marquee' => ['Pantry organization', 'Closet ideas', 'Small spaces', 'Cleaning schedules', 'Decluttering', 'Kitchen storage', 'Laundry rooms', 'Kids toys', 'Bathroom storage', 'Garage organization'],
'results' => ['Satisfying Pins, Steady Visits', 'Organizing ideas get saved and revisited every time someone tackles a room — and every January and spring.'],
'features' => ['Why Organizing Bloggers Automate Pinterest', 'Keep your content as organized as your shelves.', [
    ['↔️', 'Before & after', 'Split templates for transformations.'],
    ['🔢', 'Idea roundups', 'Number templates for storage idea lists.'],
    ['🧼', 'Cleaning routines', 'Checklists and schedules pinned as printables.'],
    ['🔤', 'Room-aware copy', 'AI writes titles by room and problem.'],
    ['🗂️', 'Room boards', 'Kitchen, closet, bathroom, garage — sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes organizing posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Organizing Blogs', 'What makes organizing pins get saved.', [
    ['Show the after — then the before', 'Transformations get the most saves.'],
    ['Say the room and size', '“Tiny Pantry Organization on a Budget”.'],
    ['Pin printable checklists', 'Cleaning schedules get saved and printed.'],
    ['Plan for January and spring', 'The two biggest organizing peaks.'],
    ['Use roundups', 'Idea lists beat single projects for saves.'],
]],
'who' => ['organizing creators', 'Bloggers, pro organizers and storage brands.', [['Organizing bloggers', 'Keep every idea in front of people tackling a room.'], ['Professional organizers', 'Bring local and virtual clients.'], ['Storage product brands', 'Pin ideas that use your products.']]],
'faq' => [
    ['Can I make before-and-after pins?', 'Yes — choose two photos in the pin editor and a split or collage template.'],
    ['Which pin size is best?', '2:3 for most ideas; long 1:2.1 for step-by-step makeovers.'],
    ['When should I pin decluttering content?', 'From early December for New Year, and February for spring cleaning.'],
],
'cta_red' => ['Tidy pins. Steady traffic.', 'Every organizing idea, pinned automatically.'],
'cta_dark' => 'Ready to grow your organizing blog on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
