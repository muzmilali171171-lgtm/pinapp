<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = APP_NAME;

$uc = [
    'slug' => 'craft-blog',
    'name' => 'Craft Blogs',
    'accent' => '#10b981',
    'order' => ['hero', 'start', 'marquee', 'features', 'steps', 'results_graph', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'craft tutorials',
    'autoblog_niche' => 'crafts, patterns and tutorials',
    'meta' => [
        'title' => "Pinterest for Craft Blogs: Pin Every Tutorial | $app",
        'description' => 'Pin every craft tutorial and pattern automatically. Cheerful templates, AI titles by craft and season, and hundreds of tutorials pinned in 1 click.',
        'keywords' => 'Pinterest for craft blogs, craft tutorial pins, crochet pattern Pinterest, kids crafts pins, Cricut project pins, craft blog traffic, holiday craft ideas Pinterest',
    ],
    'hero' => [
        'badge' => '✂️ For Craft Bloggers & Pattern Makers',
        'h1' => 'Pinterest Automation for Craft Blogs',
        'h1_accent' => 'Tutorials Crafters Save',
        'sub' => 'Crochet, sewing, Cricut, paper crafts, kids crafts — crafters plan every project on Pinterest. Turn each tutorial and pattern into cheerful pins and keep holiday crafts on boards before the season.',
        'bullets' => [['🧶', 'Pins for Tutorials, Patterns & Free Printables'], ['🎃', 'Holiday Crafts Scheduled Weeks Early'], ['🖼️', 'Collage Pins Show Materials, Steps & Results'], ['🤖', 'AI Writes Titles by Craft, Skill & Occasion'], ['⚡', 'Hundreds of Tutorials Pinned in 1 Click']],
        'cta' => 'Start Pinning My Crafts — Free',
        'chips' => ['✂️ Tutorial pinned', '💾 Saved to Christmas Crafts', '📈 Pattern visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Craft Tutorial Link', 'text' => 'Get cheerful craft pins from your project photos.', 'placeholder' => 'https://yourcraftblog.com/easy-crochet-blanket/'],
    'marquee' => ['Crochet patterns', 'Cricut projects', 'Kids crafts', 'Paper crafts', 'Sewing tutorials', 'Christmas crafts', 'Halloween crafts', 'Free printables', 'Knitting', 'Dollar store crafts'],
    'features' => ['eyebrow' => 'MADE FOR CRAFTERS', 'title' => 'Why Craft Bloggers Automate Pinterest', 'text' => 'More time crafting, more crafters finding you.', 'items' => [
        ['🧶', 'Pattern & tutorial pins', 'Cheerful templates for patterns, tutorials and printables.'],
        ['🖼️', 'Step collages', 'Materials, steps and the finished craft in one pin.'],
        ['🎄', 'Holiday calendar', 'Halloween, Christmas and Easter crafts pinned ahead.'],
        ['🔤', 'Crafter-friendly titles', 'AI writes by craft, skill level and occasion — “Easy Crochet Blanket for Beginners”.'],
        ['🗂️', 'Craft boards', 'Crochet, kids crafts, Cricut — sorted automatically.'],
        ['✍️', 'Auto Blog', 'AI writes tutorial posts with images and pins them.'],
    ]],
    'steps' => ['title' => 'From Craft Tutorial to', 'title_accent' => 'Saved Pin', 'text' => 'Four steps, then back to your craft table.', 'items' => [
        ['icon' => '✂️', 'label' => 'Setup', 'title' => 'Scan Your Craft Blog', 'alt' => 'Scanning a craft blog', 'points' => ['Every tutorial and pattern listed.', 'Select by craft type or holiday.']],
        ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick Cheerful Designs', 'alt' => 'Choosing craft pin templates', 'points' => ['Bright palettes and playful fonts.', 'Collages for steps and results.']],
        ['icon' => '🗓️', 'label' => 'Schedule', 'title' => 'Plan by Holiday', 'alt' => 'Scheduling holiday craft pins', 'points' => ['Start each holiday 6–8 weeks early.', 'Several pins per tutorial.']],
        ['icon' => '🧵', 'label' => 'Approve', 'title' => 'Approve and Keep Crafting', 'alt' => 'Approving craft pins', 'points' => ['Edit, approve once.', 'Pins publish all season.']],
    ]],
    'results' => ['title' => 'See the Results: Every Holiday, More Crafters', 'text' => 'Craft searches rise before every holiday and school break — and repeat each year. Pinned ahead, your tutorials catch every wave.', 'stats' => [[5, 'x', 'more traffic, up to'], [70, '', 'pin templates'], [365, '', 'days of pins in one run'], [56, '', 'colour palettes']], 'alt' => 'Pinterest traffic growth for a craft blog'],
    'playbook' => ['title' => 'Pinterest Tips for Craft Bloggers', 'text' => 'What gets craft pins saved.', 'tips' => [
        ['Show the finished craft big', 'The result is what people save.'],
        ['Say the skill level', '“Beginner”, “easy”, “no-sew” help the right crafters find you.'],
        ['Pin holidays early', 'Christmas craft searches start in October.'],
        ['Make kids-craft pins', 'Parents and teachers save them by the dozen.'],
        ['Pin free patterns and printables', 'Freebies are among the most-saved craft pins.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Craft Pins', 'rows' => [['Pin design', 'One tutorial at a time', 'Hundreds in 1 click'], ['Holiday timing', 'Last minute', 'Scheduled ahead'], ['Titles', 'Hand-written', 'AI writes crafter-friendly copy'], ['New tutorials', 'Written yourself', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'See Which Crafts People Make', 'text' => 'Plan new patterns around what gets saved.', 'cards' => ['Track clicks for every craft pin.', 'Remove weak pins to keep engagement strong.', 'Your top tutorials at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a craft blog', 'Removing weak craft pins', 'Top craft pins', 'Craft pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'crafters', 'text' => 'Bloggers, pattern designers and craft shops.', 'cards' => [['Craft bloggers', 'Keep every tutorial in front of crafters.'], ['Pattern designers', 'Pin free and paid patterns to grow sales.'], ['Craft supply shops', 'Pin projects that use your supplies.']]],
    'cta_red' => ['More crafters, less pin-making.', 'Pin every tutorial automatically.'],
    'faq_title' => 'Craft Blogs + Pinterest — FAQ',
    'faq' => [
        ['Can I pin free patterns and printables?', 'Yes — the images in your post become the pins, linking back to the pattern.'],
        ['Which templates suit crafts?', 'Cheerful collage and framed templates with bright palettes.'],
        ['Can AI create pins for all my tutorials?', 'Yes — select hundreds and approve once.'],
        ['Can Auto Blog write craft tutorials?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them. Check patterns and measurements before publishing.'],
        ['When should I pin holiday crafts?', 'About 6–8 weeks before each holiday.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your crafts saved?', 'Hundreds of craft pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
