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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'coaching', 'name' => 'Coaches', 'short' => 'Coaching Website', 'site' => 'Website', 'accent' => '#7c3aed', 'noun' => 'posts', 'niche' => 'your coaching topic',
'title' => 'Pinterest for Coaches: Get Clients on Autopilot', 'desc' => 'Life, business, health and career coaches: pin your posts, freebies and programs automatically. AI designs pins and schedules them in 1 click.',
'kw' => 'Pinterest for coaches, coaching marketing Pinterest, life coach Pinterest, business coach Pinterest, get coaching clients, coach lead generation Pinterest',
'badge' => '🎯 For Coaches & Course Creators', 'h1' => 'Pinterest Automation for Coaches', 'h1_accent' => 'Clients Who Find You First',
'sub' => 'People searching Pinterest for “how to change careers” or “morning routine for productivity” are your future clients. Pin your posts, freebies and programs so they find you — and book a call.',
'bullets' => [['🎁', 'Freebie & Lead-Magnet Pins That Grow Your List'], ['📝', 'Blog Posts & Podcast Episodes Pinned Automatically'], ['🤖', 'AI Writes Titles Around Your Clients’ Problems'], ['⚡', 'Your Whole Site Pinned in 1 Click'], ['✍️', 'Auto Blog Keeps Fresh Posts Coming']],
'chips' => ['🎯 Post pinned', '📩 New subscriber', '📅 Call booked'],
'placeholder' => 'https://yourcoachingsite.com/free-goal-setting-workbook/',
'marquee' => ['Life coaching', 'Business coaching', 'Career change', 'Mindset', 'Productivity', 'Health coaching', 'Relationship coaching', 'Goal setting', 'Free workbooks', 'Online courses'],
'results' => ['A Steady Flow of Leads', 'Helpful posts and freebies get saved and revisited — building your list while you coach.'],
'features' => ['Why Coaches Automate Pinterest', 'Marketing that runs while you coach.', [
    ['🎁', 'Lead-magnet pins', 'Workbooks and checklists that grow your list.'],
    ['📝', 'Content pins', 'Posts and episodes pinned automatically.'],
    ['🔤', 'Problem-first copy', 'Titles around your clients’ goals.'],
    ['🎨', 'Brand pins', 'Your colours and fonts.'],
    ['🗂️', 'Topic boards', 'Sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI drafts posts with images — you add your expertise.'],
]],
'playbook' => ['Pinterest Tips for Coaches', 'Turn pins into clients.', [
    ['Pin freebies first', 'A free workbook turns visitors into subscribers.'],
    ['Solve one problem per pin', '“How to Stop Procrastinating Today”.'],
    ['Link posts to your offer', 'Every post should point to a call or program.'],
    ['Use your face and brand', 'Recognition builds trust.'],
    ['Stay consistent', 'Daily pins beat occasional bursts.'],
]],
'who' => ['coaches', 'Every kind of coach.', [['Life & mindset coaches', 'Leads from people ready to change.'], ['Business coaches', 'Reach entrepreneurs searching for answers.'], ['Health & fitness coaches', 'Pin programs and helpful posts.']]],
'faq' => [
    ['Does Pinterest bring coaching clients?', 'Pinterest brings visitors to your posts and freebies; your pages turn them into subscribers and clients.'],
    ['Should I pin my sales page?', 'Pin helpful posts and freebies first — they get saved more and lead to your offer.'],
    ['Can AI write in my voice?', 'AI drafts copy from your pages; edit anything before approving to keep your voice.'],
],
'cta_red' => ['Clients who find you first.', 'Pin your content automatically.'],
'cta_dark' => 'Ready to grow your coaching business with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
