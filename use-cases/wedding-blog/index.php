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
'slug' => 'wedding-blog', 'name' => 'Wedding Blogs', 'short' => 'Wedding Blog', 'site' => 'Blog', 'accent' => '#d4a373', 'noun' => 'posts', 'niche' => 'wedding ideas and planning',
'title' => 'Pinterest for Wedding Blogs & Wedding Planning', 'desc' => 'Couples plan weddings on Pinterest. AI turns your wedding ideas and planning guides into elegant pins and schedules months of them in 1 click.',
'kw' => 'Pinterest for wedding blogs, wedding ideas pins, wedding planning Pinterest, bridal inspiration pins, wedding decor pins, wedding blog traffic',
'badge' => '💍 For Wedding Bloggers', 'h1' => 'Pinterest Automation for Wedding Blogs', 'h1_accent' => 'Be on Every Wedding Board',
'sub' => 'Almost every wedding starts with a Pinterest board. Turn your decor ideas, planning checklists, dresses and real weddings into elegant pins that couples save for months.',
'bullets' => [['💐', 'Elegant Templates With Script Fonts & Soft Palettes'], ['📋', 'Checklists, Ideas, Real Weddings & Budgets'], ['🤖', 'AI Writes Titles by Theme, Season & Budget'], ['⚡', 'Your Whole Blog Pinned in 1 Click'], ['🗂️', 'Boards for Decor, Dresses, Flowers & Planning']],
'chips' => ['💍 Idea pinned', '💾 Saved to Our Wedding', '📈 Visits up'],
'placeholder' => 'https://yourweddingblog.com/boho-wedding-decor-ideas/',
'marquee' => ['Boho weddings', 'Wedding checklists', 'Bridal hair', 'Table settings', 'Wedding cakes', 'Budget weddings', 'Bridesmaid dresses', 'Wedding flowers', 'Rustic weddings', 'Elopements'],
'results' => ['Couples Save and Come Back', 'Couples plan for 12–18 months and keep coming back to their boards. Your pins stay in front of them the whole way.'],
'design_title' => 'Choose an Elegant Look', 'design_point' => 'Arch frames, soft palettes and script accents made for weddings.',
'features' => ['Why Wedding Bloggers Automate Pinterest', 'Couples plan here. Be the inspiration they save.', [
    ['💐', 'Elegant templates', 'Arch frames, gallery layouts and script fonts.'],
    ['📋', 'Planning pins', 'Checklists and timelines couples save for later.'],
    ['🔢', 'Idea roundups', '“50 Rustic Wedding Ideas” number templates.'],
    ['🔤', 'Theme-aware copy', 'AI writes titles by style, season and budget.'],
    ['🗂️', 'Wedding boards', 'Decor, dresses, cakes, flowers — sorted for you.'],
    ['✍️', 'Auto Blog', 'AI writes wedding idea posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Wedding Bloggers', 'How to be on every couple’s board.', [
    ['Name the style', 'Boho, rustic, modern, garden — couples search by theme.'],
    ['Pin during engagement season', 'Most proposals happen in December; planning searches follow.'],
    ['Make checklists and printables', 'They get saved and revisited for months.'],
    ['Pin real weddings', 'Real events feel attainable and get saved.'],
    ['Give each idea its own pin', 'Different details reach different couples.'],
]],
'who' => ['wedding creators', 'Bloggers, planners and vendors.', [['Wedding bloggers', 'Stay on couples’ boards from engagement to big day.'], ['Planners & vendors', 'Bring enquiries from couples planning ahead.'], ['Bridal shops', 'Pin dresses and accessories for brides-to-be.']]],
'faq' => [
    ['Which templates suit weddings?', 'Arch frames, gallery layouts and soft palettes with script accents.'],
    ['Can I pin real wedding photos?', 'Yes, with permission from the couple and the photographer, and with credit.'],
    ['When is the best time to pin wedding content?', 'All year, with extra attention to engagement season from December to March.'],
],
'cta_red' => ['Be on every wedding board.', 'Your wedding ideas, pinned automatically.'],
'cta_dark' => 'Ready to reach more couples on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
