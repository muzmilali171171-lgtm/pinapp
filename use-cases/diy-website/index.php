<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = [
    'slug' => 'diy-website',
    'name' => 'DIY Websites',
    'accent' => '#f59e0b',
    'power_noun' => 'projects',
    'autoblog_niche' => 'DIY projects and how-tos',
    'meta' => [
        'title' => "Pinterest for DIY Blogs & DIY Project Sites | $app",
        'description' => 'Pin every DIY project automatically. Step-by-step and before-and-after pin designs, AI-written titles, and hundreds of projects scheduled in 1 click.',
        'keywords' => 'Pinterest for DIY blogs, DIY project pins, DIY Pinterest strategy, before and after pins, home DIY ideas Pinterest, DIY website traffic, woodworking project pins',
    ],
    'hero' => [
        'badge' => '🔨 For DIY Bloggers & Project Sites',
        'h1' => 'Pinterest Automation for DIY Websites',
        'h1_accent' => 'Projects People Actually Build',
        'sub' => 'Pinterest is the world’s DIY idea board. Turn every tutorial — step-by-step builds, before-and-afters, weekend makeovers — into pins that makers save, and keep them publishing all year.',
        'bullets' => [['🛠️', 'Step-by-Step & Before-and-After Collage Pins'], ['🔢', 'Roundup Templates for “25 Weekend DIY Projects”'], ['🤖', 'AI Writes Titles With Budget, Time & Skill Level'], ['⚡', 'Hundreds of Projects Pinned in 1 Click'], ['✍️', 'Auto Blog Writes & Publishes New How-Tos']],
        'cta' => 'Start Pinning My DIY Projects — Free',
        'chips' => ['🔨 Project pinned', '💾 Saved to Weekend DIY', '📈 Tutorial visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a DIY Tutorial Link', 'text' => 'Get project pins from your step photos.', 'placeholder' => 'https://yourdiyblog.com/diy-floating-shelves/'],
    'marquee' => ['Weekend projects', 'Before & after', 'Budget makeovers', 'Furniture flips', 'Wall decor DIY', 'Outdoor projects', 'Storage ideas', 'Upcycling', 'Holiday DIY', 'Beginner woodworking'],
    'results' => ['title' => 'See the Results: Tutorials That Keep Getting Built', 'text' => 'DIY tutorials stay useful for years. Pin each project several times and makers keep finding it, season after season.', 'stats' => [[5, 'x', 'more traffic, up to'], [500, '+', 'premium pin templates'], [365, '', 'days of pins in one run'], [10, '', 'pins per project, max']], 'alt' => 'Pinterest traffic growth for a DIY website'],
    'steps' => ['title' => 'From DIY Tutorial to', 'title_accent' => 'Saved Project', 'text' => 'Scan, design, schedule, approve.', 'items' => [
        ['icon' => '🔨', 'label' => 'Setup', 'title' => 'Scan Your Projects', 'alt' => 'Scanning a DIY website', 'points' => ['Every tutorial listed from your sitemap.', 'Select by room, material or season.']],
        ['icon' => '🧩', 'label' => 'Design', 'title' => 'Show the Process', 'alt' => 'Choosing collage templates for DIY pins', 'points' => ['Collages for steps and before-and-afters.', 'Bold roundup templates for project lists.']],
        ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Match the Building Season', 'alt' => 'Scheduling DIY pins', 'points' => ['Outdoor builds in spring, holiday DIY in autumn.', 'Several pins per project.']],
        ['icon' => '✅', 'label' => 'Approve', 'title' => 'Approve and Get Building', 'alt' => 'Approving DIY pins', 'points' => ['Edit, approve, done.', 'Pins publish while you build.']],
    ]],
    'features' => ['eyebrow' => 'MADE FOR MAKERS', 'title' => 'Why DIY Creators Automate Pinterest', 'text' => 'Spend time building — not designing pins.', 'items' => [
        ['🧩', 'Process collages', 'Two to four step photos in one pin.'],
        ['↔️', 'Before & after', 'Split-photo templates made for transformations.'],
        ['💲', 'Practical titles', 'AI adds budget, time and skill level where your post mentions them.'],
        ['🔁', 'Several pins per project', 'Finished shot, steps and details each get a pin.'],
        ['🗂️', 'Project boards', 'By room, material or occasion — sorted automatically.'],
        ['✍️', 'Auto Blog', 'AI writes how-to posts with images and pins them.'],
    ]],
    'playbook' => ['title' => 'Pinterest Tips for DIY Sites', 'text' => 'What makes DIY pins get saved.', 'tips' => [
        ['Show the finished project first', 'The result sells the tutorial.'],
        ['Add the practical details', '“$30 DIY Floating Shelves in One Afternoon” gets clicks.'],
        ['Pin the process, too', 'Step collages attract makers who plan to build.'],
        ['Use before-and-afters', 'Transformations are some of the most-saved DIY pins.'],
        ['Follow the building season', 'Outdoor projects in spring, cosy projects in autumn.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated DIY Pins', 'rows' => [['Pin design', 'One project at a time', 'Hundreds in 1 click'], ['Step collages', 'Built by hand', 'Templates do it'], ['Titles', 'Rewritten each time', 'AI writes practical copy'], ['New tutorials', 'Written yourself', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'See Which Projects Makers Save', 'text' => 'Build more of what your audience loves.', 'cards' => ['Track clicks for every project pin.', 'Remove weak pins to keep engagement strong.', 'Your top projects at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a DIY site', 'Removing weak DIY pins', 'Top DIY pins', 'DIY pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'DIY creators', 'text' => 'Bloggers, makers and home improvers.', 'cards' => [['DIY bloggers', 'Keep every tutorial in front of makers.'], ['Home improvement sites', 'Pin projects and guides for homeowners.'], ['Tool & supply brands', 'Pin projects that use your products.']]],
    'cta_red' => ['Your projects deserve to be built again and again.', 'Pin every tutorial automatically.'],
    'faq_title' => 'DIY Websites + Pinterest — FAQ',
    'faq' => [
        ['Can I make before-and-after pins?', 'Yes. Pick two photos from your post in the pin editor and choose a split or collage template.'],
        ['Can AI create pins for all my projects?', 'Yes — select hundreds of tutorials and approve once.'],
        ['Can Auto Blog write DIY tutorials?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them. Check any safety steps before publishing.'],
        ['Which pin size is best for tutorials?', '2:3 works well; long 1:2.1 pins suit step-by-step collages.'],
        ['Does it pick boards?', 'Yes — AI matches each project to a board or creates one.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your projects built?', 'Hundreds of DIY pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
