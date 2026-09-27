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
'slug' => 'home-organization-membership', 'name' => 'Home Organization Memberships', 'short' => 'Organization Membership Site', 'site' => 'Site', 'accent' => '#14b8a6', 'noun' => 'pages', 'niche' => 'home organization and decluttering',
'title' => 'Pinterest for Home Organization Memberships', 'desc' => 'Grow your organizing membership with Pinterest. Pin free guides, checklists and challenges that lead to your membership — hundreds of pins in 1 click.',
'kw' => 'Pinterest for membership sites, home organization membership, decluttering challenge pins, organizing checklist Pinterest, membership site marketing, grow membership Pinterest',
'badge' => '🗂️ For Organizing Memberships & Programs', 'h1' => 'Pinterest Automation for Home Organization Memberships', 'h1_accent' => 'Free Guides In, Members Out',
'sub' => 'People find organizers on Pinterest through free checklists, challenges and before-and-afters. Pin every free resource that leads to your membership and keep new members arriving every day.',
'bullets' => [['📋', 'Pins for Checklists, Challenges & Free Guides'], ['↔️', 'Before-and-After Collages That Get Saved'], ['🎯', 'Every Pin Leads to Your Free Content & Membership'], ['⚡', 'Hundreds of Pages Pinned in 1 Click'], ['📅', 'January & Spring-Cleaning Peaks Scheduled Ahead']],
'chips' => ['📋 Checklist pinned', '💾 Saved to Declutter', '🎟️ New member'],
'placeholder' => 'https://yoursite.com/30-day-declutter-challenge/',
'marquee' => ['Declutter challenges', 'Pantry organization', 'Closet makeovers', 'Cleaning schedules', 'Printable checklists', 'Minimalism', 'Kids rooms', 'Garage organization', 'Paper clutter', 'Small spaces'],
'results' => ['Free Guides That Fill Your Membership', 'Checklists and challenges get saved and revisited. Pinned consistently, they build a steady path from Pinterest to your email list and membership.'],
'features' => ['Why Membership Sites Automate Pinterest', 'Pinterest is a free funnel for your membership.', [
    ['📋', 'Lead-magnet pins', 'Checklists and printables that lead to your opt-in.'],
    ['↔️', 'Transformation pins', 'Before-and-after collages from member or client projects (with permission).'],
    ['🏁', 'Challenge pins', 'Number templates for “30-Day Declutter Challenge”.'],
    ['🗓️', 'Peak-season timing', 'January reset and spring cleaning pinned early.'],
    ['🔤', 'Room-by-room copy', 'AI writes titles by room and problem.'],
    ['✍️', 'Auto Blog', 'AI writes organizing posts with images and pins them.'],
]],
'playbook' => ['Pinterest Strategy for Organizing Memberships', 'Turn pins into members.', [
    ['Pin free content, not the sales page', 'Guides and checklists get saved; they then lead to your offer.'],
    ['Go room by room', '“Small Pantry Organization Ideas” is a real search.'],
    ['Show transformations', 'Before-and-afters are the most-saved organizing pins.'],
    ['Plan around resets', 'January and spring are peak seasons.'],
    ['Link to an opt-in', 'Make sure every pinned page offers a free signup.'],
]],
'who' => ['organizing businesses', 'Memberships, courses and pro organizers.', [['Membership owners', 'A steady free funnel from Pinterest to your program.'], ['Course creators', 'Pin lessons and freebies that lead to your course.'], ['Professional organizers', 'Bring local and virtual clients.']]],
'faq' => [
    ['Can I pin members-only pages?', 'Pin public pages — free guides, blog posts and sales pages. Members-only pages can’t be read or opened by Pinterest users.'],
    ['Do before-and-after pins work?', 'Yes — pick two photos in the pin editor and use a split or collage template. Use photos you have permission to share.'],
    ['When should I pin decluttering content?', 'Start in December for January, and February for spring cleaning.'],
],
'cta_red' => ['Turn pins into members.', 'Pin your free guides and challenges automatically.'],
'cta_dark' => 'Ready to grow your membership with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
