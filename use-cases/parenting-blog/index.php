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
    'slug' => 'parenting-blog',
    'name' => 'Parenting Blogs',
    'accent' => '#5b8def',
    'order' => ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'posts',
    'autoblog_niche' => 'parenting tips, activities and printables',
    'meta' => [
        'title' => "Pinterest for Parenting Blogs: Auto-Pin Posts | $app",
        'description' => 'Grow your parenting blog with Pinterest on autopilot. AI turns tips, activities and printables into pins and schedules months of them in 1 click.',
        'keywords' => 'Pinterest for parenting blogs, parenting blog Pinterest strategy, kids activities pins, parenting tips Pinterest, printables for kids Pinterest, family blog traffic',
    ],
    'hero' => [
        'badge' => '👨‍👩‍👧 For Parenting Bloggers',
        'h1' => 'Pinterest Automation for Parenting Blogs',
        'h1_accent' => 'Reach More Families Every Day',
        'sub' => 'Parents search Pinterest for activities, routines, school tips and printables. Turn every post into warm, helpful pins and keep new families finding your blog while you’re busy parenting.',
        'bullets' => [['🧸', 'Pins for Activities, Tips & Printables'], ['🔢', 'List Templates for “30 Rainy Day Activities” Posts'], ['🏫', 'Back-to-School & Holiday Posts Pinned Early'], ['🤖', 'AI Writes Helpful, Search-Friendly Pin Copy'], ['⏱️', 'Set It Up Once — During Nap Time']],
        'cta' => 'Start Pinning My Parenting Blog — Free',
        'chips' => ['🧸 Activity pinned', '💾 Saved by a parent', '📈 New readers'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Parenting Post Link', 'text' => 'We turn your post into friendly, save-worthy pins.', 'placeholder' => 'https://yourblog.com/toddler-activities/'],
    'marquee' => ['Toddler activities', 'Bedtime routines', 'Kids printables', 'Chore charts', 'Screen-free ideas', 'Lunchbox ideas', 'Potty training', 'Back to school', 'Family travel', 'Birthday parties'],
    'features' => ['eyebrow' => 'MADE FOR PARENTING CONTENT', 'title' => 'Why Parenting Bloggers Automate Pinterest', 'text' => 'Parents plan on Pinterest — and they save helpful posts to come back to.', 'items' => [
        ['🧸', 'Activity & tip pins', 'Friendly templates for activities, routines and parenting tips.'],
        ['🖨️', 'Printable pins', 'Collage layouts show off charts, checklists and worksheets.'],
        ['🔢', 'List posts', 'Number templates for “25 Screen-Free Activities” style posts.'],
        ['🗓️', 'School-year calendar', 'Back-to-school, holidays and summer break posts pinned ahead.'],
        ['🤖', 'Warm, clear copy', 'AI writes titles by age and problem — “Bedtime Routine for Toddlers”.'],
        ['✍️', 'Auto Blog', 'AI writes new posts with images and pins them while you parent.'],
    ]],
    'results' => ['title' => 'See the Results: Evergreen Posts, Steady Readers', 'text' => 'Parenting questions come up every year for new parents. Pin your answers once and keep reaching families for months.', 'stats' => [[5, 'x', 'more traffic, up to'], [3, '', 'pins per post by default'], [500, '+', 'premium pin templates'], [365, '', 'days of pins in one run']], 'alt' => 'Pinterest traffic growth for a parenting blog'],
    'steps' => ['title' => 'From Parenting Post to', 'title_accent' => 'Scheduled Pin', 'text' => 'Ten minutes during nap time — then months of pins.', 'items' => [
        ['icon' => '📚', 'label' => 'Setup', 'title' => 'Scan Your Blog', 'alt' => 'Scanning a parenting blog', 'points' => ['Every post listed from your sitemap.', 'Select by age group, topic or season.']],
        ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick a Friendly Style', 'alt' => 'Choosing friendly pin templates', 'points' => ['Soft palettes and playful fonts.', 'Or your own design, made free in the built-in editor.']],
        ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Set a Gentle Pace', 'alt' => 'Scheduling parenting blog pins', 'points' => ['Warm-up mode for new accounts.', 'Pins per post spaced a month apart.']],
        ['icon' => '✅', 'label' => 'Approve', 'title' => 'Approve and Get Back to Family', 'alt' => 'Approving parenting pins', 'points' => ['Review, edit, approve.', 'Pins go out while you’re busy.']],
    ]],
    'playbook' => ['title' => 'Pinterest Tips for Parenting Bloggers', 'text' => 'What makes parenting pins get saved.', 'tips' => [
        ['Be specific about age', '“Activities for 3-Year-Olds” is searched far more than “kids activities”.'],
        ['Solve one problem per pin', 'Bedtime, picky eating, tantrums — a clear problem gets clicks.'],
        ['Pin printables with a preview', 'Show the printable itself so parents know what they’ll get.'],
        ['Plan by the school year', 'Back-to-school content starts trending in July.'],
        ['Refresh your top posts', 'New designs for proven posts are an easy win.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Parenting Pins', 'rows' => [['Pin design', 'Hours in Canva', 'Hundreds of posts in 1 click'], ['Consistency', 'Stops when life gets busy', 'Keeps publishing daily'], ['New posts', 'Written late at night', 'Auto Blog writes, publishes & pins'], ['Seasonal content', 'Often late', 'Scheduled ahead']]],
    'analytics' => ['title' => 'See What Parents Save', 'text' => 'Find your most helpful posts and write more like them.', 'cards' => ['Track clicks for every post you pin.', 'Remove weak pins to keep engagement strong.', 'Your top posts on Pinterest at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a parenting blog', 'Removing weak parenting pins', 'Top parenting pins', 'Parenting pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'parenting creators', 'text' => 'From new bloggers to family media sites.', 'cards' => [['Parent bloggers', 'Grow readers without giving up family time.'], ['Printable & activity sites', 'Show off worksheets and charts to parents who save them.'], ['Family publishers', 'Keep a big archive working on Pinterest.']]],
    'cta_red' => ['More families, less screen time for you.', 'Pin your parenting posts automatically.'],
    'faq_title' => 'Parenting Blogs + Pinterest — FAQ',
    'faq' => [
        ['Does this work for small parenting blogs?', 'Yes. Even a few dozen evergreen posts can bring steady Pinterest traffic when they’re pinned consistently.'],
        ['Can I pin printables?', 'Yes. Pins use the images in your post, so a preview of your printable becomes the pin.'],
        ['How much time does it take?', 'Setup takes minutes. After you approve, pins publish on their own.'],
        ['Can AI write parenting posts?', 'On plans that include Auto Blog, AI writes posts with images from your titles and publishes them. Review anything health- or safety-related before publishing.'],
        ['Is it safe for a new Pinterest account?', 'Yes — the warm-up mode starts at 1 pin a day and grows over five months.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to reach more families on Pinterest?', 'Pin your whole blog in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
