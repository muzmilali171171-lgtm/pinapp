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
'slug' => 'infographic-blog', 'name' => 'Infographic Blogs', 'short' => 'Infographic Blog', 'site' => 'Blog', 'accent' => '#3b82f6', 'noun' => 'infographics', 'niche' => 'data, facts and how-to infographics',
'title' => 'Pinterest for Infographic Blogs & Data Content', 'desc' => 'Tall infographics are made for Pinterest. Pin every infographic and guide automatically with long pin sizes, AI-written titles and scheduling in 1 click.',
'kw' => 'infographic Pinterest, infographic pins, tall pins Pinterest, data visualization Pinterest, how-to infographic pins, infographic blog traffic',
'badge' => '📊 For Infographic Creators', 'h1' => 'Pinterest Automation for Infographic Blogs', 'h1_accent' => 'Tall Pins, Big Reach',
'sub' => 'Infographics are one of the most-shared formats on Pinterest — tall, useful and easy to save. Pin every infographic and guide on your site, in long pin sizes, and keep them going out daily.',
'bullets' => [['📐', 'Long 1:2.1 & 9:16 Pin Sizes for Tall Graphics'], ['🧠', 'Uses Your Infographic as the Pin Image'], ['🤖', 'AI Writes Clear, Searchable Titles'], ['⚡', 'Hundreds of Infographics Pinned in 1 Click'], ['🗂️', 'Boards by Topic, Automatically']],
'chips' => ['📊 Infographic pinned', '💾 Saved to Study Tips', '🔁 Re-shared'],
'placeholder' => 'https://yourblog.com/sleep-hygiene-infographic/',
'marquee' => ['Cheat sheets', 'Checklists', 'How-to graphics', 'Statistics', 'Timelines', 'Comparisons', 'Study guides', 'Health facts', 'Money tips', 'Marketing charts'],
'results' => ['Graphics That Get Re-Shared', 'Useful infographics get saved and re-saved across boards. Each one can keep sending visitors for years.'],
'stats' => [[5, 'x', 'more traffic, up to'], [2, '', 'tall pin sizes (1:2.1 & 9:16)'], [70, '', 'pin templates'], [365, '', 'days of pins in one run']],
'design_title' => 'Go Tall', 'design_point' => 'Choose the long 1:2.1 or 9:16 size so tall infographics fit.',
'features' => ['Why Infographic Creators Automate Pinterest', 'Your format is already Pinterest-native.', [
    ['📐', 'Tall pin sizes', '1000 × 2100 and 1080 × 1920 for long graphics.'],
    ['🧠', 'Graphic-first templates', 'Minimal templates that don’t cover your data.'],
    ['🔤', 'Search-friendly titles', 'AI turns each graphic’s topic into a clear title.'],
    ['♿', 'Alt text for every pin', 'AI describes each image for screen readers.'],
    ['🗂️', 'Topic boards', 'Pins sorted into the right boards.'],
    ['✍️', 'Auto Blog', 'AI writes supporting posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Infographic Creators', 'How to get graphics saved.', [
    ['Keep text readable on mobile', 'Big headings, short lines, high contrast.'],
    ['Use the tall format', 'Long pins get more space in the feed.'],
    ['Title the pin with the question', '“How Much Sleep Do You Need by Age?”'],
    ['Cite your sources on the page', 'It builds trust when people click through.'],
    ['Make several versions', 'A summary pin and a detailed pin reach different people.'],
]],
'who' => ['infographic creators', 'Bloggers, educators and brands.', [['Infographic bloggers', 'Put every graphic in front of people who share them.'], ['Educators', 'Pin study guides and cheat sheets.'], ['Brands & agencies', 'Turn research into shareable pins.']]],
'faq' => [
    ['Which pin size is best for infographics?', 'The long 1:2.1 (1000 × 2100) size fits tall graphics best.'],
    ['Will templates cover my infographic?', 'Choose a minimal template, or upload the infographic itself as the pin image in the pin editor.'],
    ['Can AI write alt text for infographics?', 'Yes — AI writes alt text for every pin; review it for accuracy on detailed graphics.'],
],
'cta_red' => ['Your infographics are made for Pinterest.', 'Pin every one of them automatically.'],
'cta_dark' => 'Ready to get your infographics shared?',
]);
uc_render_page($pdo, $user, $uc);
