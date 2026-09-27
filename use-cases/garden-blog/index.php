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
'slug' => 'garden-blog', 'name' => 'Garden Blogs', 'short' => 'Garden Blog', 'site' => 'Blog', 'accent' => '#16a34a', 'noun' => 'posts', 'niche' => 'gardening, planting and garden design',
'title' => 'Pinterest for Garden Blogs & Gardening Sites', 'desc' => 'Pin every gardening guide before planting season. AI turns your garden posts into pins, writes the copy and schedules months of pins in 1 click.',
'kw' => 'Pinterest for garden blogs, gardening Pinterest, garden ideas pins, vegetable garden pins, planting guide Pinterest, garden blog traffic, backyard ideas Pinterest',
'badge' => '🌱 For Gardening Bloggers', 'h1' => 'Pinterest Automation for Garden Blogs', 'h1_accent' => 'Planted Before the Season',
'sub' => 'Gardeners plan on Pinterest weeks before they dig. Turn your planting guides, garden ideas and grow-your-own posts into fresh, green pins scheduled ahead of every planting season.',
'bullets' => [['🌿', 'Pins for Planting Guides, Garden Ideas & Harvest Recipes'], ['🗓️', 'Scheduled Ahead of Every Planting Season'], ['🤖', 'AI Writes Titles by Plant, Zone & Season'], ['⚡', 'Your Whole Garden Archive Pinned in 1 Click'], ['🗂️', 'Boards for Vegetables, Flowers, Containers & Design']],
'chips' => ['🌱 Guide pinned', '💾 Saved to Veggie Garden', '📈 Spring traffic up'],
'placeholder' => 'https://yourgardenblog.com/raised-bed-vegetable-garden/',
'marquee' => ['Raised beds', 'Vegetable gardens', 'Container gardening', 'Companion planting', 'Flower beds', 'Seed starting', 'Backyard ideas', 'Herb gardens', 'Garden design', 'Houseplants'],
'results' => ['Traffic That Blooms Every Spring', 'Garden searches rise every spring and autumn — and repeat every year. Pinned ahead, your guides catch every season.'],
'schedule_title' => 'Schedule Before Planting Time', 'schedule_point' => 'Set your first publish date 6–8 weeks before each planting season.',
'features' => ['Why Garden Bloggers Automate Pinterest', 'Be on the board before the first seed goes in.', [
    ['🗓️', 'Season-first scheduling', 'Seed starting, spring planting and autumn bulbs pinned in time.'],
    ['🌿', 'Fresh, natural designs', 'Green and earthy palettes that suit garden photos.'],
    ['🔢', 'Idea roundups', 'Number templates for “30 Raised Bed Ideas”.'],
    ['🔤', 'Plant-aware copy', 'AI writes titles with plant names and seasons.'],
    ['🗂️', 'Garden boards', 'Vegetables, flowers, containers and design — sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes gardening posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Garden Bloggers', 'What makes garden pins grow.', [
    ['Pin 6–8 weeks before the season', 'Spring planting searches start in late winter.'],
    ['Show the result', 'A lush finished bed sells the guide.'],
    ['Be specific', '“Small Vegetable Garden Layout for Beginners” beats “My Garden”.'],
    ['Pin idea roundups', 'Garden design roundups get saved again and again.'],
    ['Refresh seasonal posts yearly', 'New pin designs for last year’s best guides.'],
]],
'who' => ['garden creators', 'Bloggers, nurseries and garden shops.', [['Garden bloggers', 'Keep every guide in front of planners.'], ['Nurseries & garden centres', 'Pin guides that bring local customers.'], ['Garden product brands', 'Pin projects that use your products.']]],
'faq' => [
    ['When should I pin spring gardening content?', 'About 6–8 weeks before planting time in your readers’ main region.'],
    ['Which templates suit garden photos?', 'Full-photo, framed and collage templates with green or earthy palettes.'],
    ['Can I pin houseplant content too?', 'Yes — houseplant posts work year-round.'],
],
'cta_red' => ['Plant your pins before the season.', 'Your garden guides, scheduled automatically.'],
'cta_dark' => 'Ready to grow your garden blog on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
