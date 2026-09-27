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
'slug' => 'digital-planners', 'name' => 'Digital Planner Shops', 'short' => 'Digital Planner Shop', 'site' => 'Shop', 'accent' => '#7c3aed', 'noun' => 'planners', 'niche' => 'planning, productivity and digital planners',
'title' => 'Pinterest for Digital Planner Shops', 'desc' => 'Pin your digital planners, templates and stickers for organized buyers. AI designs preview pins and schedules them in 1 click.',
'kw' => 'digital planner Pinterest, sell digital planners, GoodNotes planner pins, productivity templates Pinterest, digital stickers pins, planner shop marketing',
'badge' => '🗓️ For Digital Planner Creators', 'h1' => 'Pinterest Automation for Digital Planner Shops', 'h1_accent' => 'Planners People Plan to Buy',
'sub' => 'Digital planners are made for Pinterest: people save previews, compare layouts and come back to buy. Pin every planner, insert and sticker pack with previews that show what’s inside.',
'bullets' => [['📱', 'Preview Collages of Planner Pages'], ['📅', 'New-Year & Back-to-School Peaks Scheduled Ahead'], ['🤖', 'AI Writes Titles by App, Layout & Purpose'], ['⚡', 'Every Product Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Planning Guides']],
'chips' => ['🗓️ Planner pinned', '💾 Saved to 2027 Planner', '💳 Sale'],
'placeholder' => 'https://yourshop.com/products/2027-digital-planner',
'marquee' => ['GoodNotes planners', 'Notability', 'Digital stickers', 'Budget planners', 'Student planners', 'Fitness trackers', 'Undated planners', 'Weekly layouts', 'Notion templates', 'Journals'],
'results' => ['Peak Seasons, Planned Ahead', 'Planner searches peak before the new year and school year. Pinned early, your products ride both waves.'],
'features' => ['Why Planner Shops Automate Pinterest', 'Previews sell planners.', [
    ['📱', 'Preview collages', 'Several pages in one pin.'],
    ['📅', 'Peak timing', 'November–December and July–August pinned ahead.'],
    ['🔤', 'App-aware copy', 'GoodNotes, Notability, iPad — in titles where relevant.'],
    ['🔁', 'Pins per layout', 'Monthly, weekly, trackers — each its own pin.'],
    ['🗂️', 'Planner boards', 'Student, budget, fitness — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes planning guides that feature your planners.'],
]],
'playbook' => ['Pinterest Tips for Planner Shops', 'How digital planners sell.', [
    ['Show real pages', 'Filled-in pages sell better than covers.'],
    ['Name the app and device', '“GoodNotes Planner for iPad”.'],
    ['Pin before peaks', 'New-year planners from October.'],
    ['Pin each insert', 'Different trackers reach different buyers.'],
    ['Offer a freebie', 'A free sample page brings buyers back.'],
]],
'who' => ['planner creators', 'Solo creators to template studios.', [['Planner designers', 'Previews in front of planners.'], ['Sticker makers', 'Pin every sticker pack.'], ['Template studios', 'Keep a big catalogue visible.']]],
'faq' => [
    ['When do planner sales peak?', 'Before the new year and before the school year — start pinning 6–8 weeks ahead.'],
    ['Can pins show several planner pages?', 'Yes — collage templates show multiple previews.'],
    ['Do Etsy planner listings work?', 'Yes — paste listing links; if a page can’t be read it shows as skipped and you can pin from your own site.'],
],
'cta_red' => ['Planners people plan to buy.', 'Pin every planner automatically.'],
'cta_dark' => 'Ready to sell more digital planners?',
]);
uc_render_page($pdo, $user, $uc);
