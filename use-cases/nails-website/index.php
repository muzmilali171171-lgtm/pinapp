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
    'slug' => 'nails-website',
    'name' => 'Nail Art Websites',
    'accent' => '#ec4899',
    'order' => ['hero', 'start', 'marquee', 'features', 'steps', 'results_graph', 'playbook', 'analytics', 'compare', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'nail posts',
    'autoblog_niche' => 'nail art and manicure ideas',
    'meta' => [
        'title' => "Pinterest for Nail Art Websites & Nail Blogs | $app",
        'description' => 'Pin every nail art roundup automatically. Bright number-style pin templates, AI-written titles and seasonal scheduling for nail design websites.',
        'keywords' => 'Pinterest for nail blogs, nail art pins, nail design Pinterest, nail ideas pins, manicure ideas Pinterest, nail salon Pinterest marketing, seasonal nail pins',
    ],
    'hero' => [
        'badge' => '💅 For Nail Art Blogs & Nail Sites',
        'h1' => 'Pinterest Automation for Nail Art Websites',
        'h1_accent' => 'Designs People Screenshot',
        'sub' => 'Nail ideas are one of Pinterest’s most-searched beauty topics — and they change every season. Turn every nail roundup into bright, bold pins and schedule each season’s trends before they peak.',
        'bullets' => [['💅', '“35 Fall Nail Ideas” Number Templates'], ['🌸', 'Seasonal Trends Pinned Before They Peak'], ['🖼️', 'Collages Show Several Designs in One Pin'], ['🤖', 'AI Writes Titles by Colour, Shape & Season'], ['⚡', 'Hundreds of Roundups Pinned in 1 Click']],
        'cta' => 'Start Pinning My Nail Designs — Free',
        'chips' => ['💅 Design pinned', '💾 Saved to Fall Nails', '📈 Roundup visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Nail Roundup Link', 'text' => 'Get bright, save-worthy nail pins from your photos.', 'placeholder' => 'https://yournailblog.com/fall-nail-ideas/'],
    'marquee' => ['Fall nails', 'French tips', 'Almond nails', 'Short nails', 'Chrome nails', 'Wedding nails', 'Christmas nails', 'Summer nails', 'Nail art ideas', 'Gel nails'],
    'features' => ['eyebrow' => 'MADE FOR NAIL CONTENT', 'title' => 'Why Nail Sites Win With Automation', 'text' => 'Nail trends move fast. Your pins need to keep up.', 'items' => [
        ['🔢', 'Number templates', 'Bold “40 Summer Nail Ideas” designs modelled on top nail pins.'],
        ['🖼️', 'Design collages', 'Four designs in one pin — great for roundups.'],
        ['🎨', 'Colour-rich palettes', 'Pink, nude, chrome and seasonal palettes to match your brand.'],
        ['🔤', 'Trend-aware copy', 'AI writes titles with shape, length, colour and season.'],
        ['🗓️', 'Season calendar', 'Pin fall nails in August, holiday nails in October.'],
        ['✍️', 'Auto Blog', 'AI writes nail roundup posts with images and pins them.'],
    ]],
    'steps' => ['title' => 'From Nail Roundup to', 'title_accent' => 'Trending Pin', 'text' => 'Scan, pick a bold style, schedule the season.', 'items' => [
        ['icon' => '💅', 'label' => 'Setup', 'title' => 'Scan Your Nail Posts', 'alt' => 'Scanning a nail art website', 'points' => ['Every roundup listed from your sitemap.', 'Filter by season or style and select in bulk.']],
        ['icon' => '🎨', 'label' => 'Design', 'title' => 'Choose Bold Nail Templates', 'alt' => 'Choosing nail pin templates', 'points' => ['Number and collage templates for roundups.', 'Pink and nude palettes, or your brand colours.']],
        ['icon' => '🗓️', 'label' => 'Schedule', 'title' => 'Beat the Trend Curve', 'alt' => 'Scheduling seasonal nail pins', 'points' => ['Start each season’s pins 6–8 weeks early.', 'Several pins per roundup, spaced out.']],
        ['icon' => '✨', 'label' => 'Approve', 'title' => 'Approve and Polish Off', 'alt' => 'Approving nail pins', 'points' => ['Edit any pin, then approve.', 'Pins publish on autopilot.']],
    ]],
    'results' => ['title' => 'See the Results: Every Season, a New Wave', 'text' => 'Nail searches reset every season. With pins scheduled ahead, you ride each new wave instead of chasing it.', 'stats' => [[5, 'x', 'more traffic, up to'], [4, '', 'seasons planned ahead'], [500, '+', 'premium pin templates'], [100, '%', 'free design editor, no Canva Pro']], 'alt' => 'Pinterest traffic growth for a nail art website'],
    'playbook' => ['title' => 'Pinterest Tips for Nail Art Sites', 'text' => 'How nail creators get saved.', 'tips' => [
        ['Lead with the best close-up', 'Sharp, well-lit nail photos get saved more.'],
        ['Include shape and length', '“Short Almond Nails” is how people search.'],
        ['Pin each season early', 'Fall nail searches begin in August.'],
        ['Make roundups', 'Big roundups get more saves than single designs.'],
        ['Use several pins per post', 'Different designs from one roundup reach different searchers.'],
    ]],
    'analytics' => ['title' => 'See Which Nail Designs Trend', 'text' => 'Spot the colours and shapes your audience loves.', 'cards' => ['Track clicks for every nail pin.', 'Remove weak pins to keep engagement high.', 'Your top designs at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a nail site', 'Removing weak nail pins', 'Top nail pins', 'Nail pin analytics breakdown']],
    'compare' => ['title' => 'Manual vs Automated Nail Pins', 'rows' => [['Pin design', 'One roundup at a time', 'Hundreds in 1 click'], ['Trend timing', 'Pinned after the peak', 'Scheduled before it'], ['Titles', 'Written by hand', 'AI writes trend-aware copy'], ['New roundups', 'Written one by one', 'Auto Blog writes, publishes & pins']]],
    'who' => ['title' => 'Made for', 'accent' => 'nail creators', 'text' => 'Nail artists, bloggers and salons.', 'cards' => [['Nail art bloggers', 'Keep every roundup in front of people planning their next set.'], ['Nail salons', 'Show your work to local clients looking for ideas.'], ['Beauty publishers', 'Pin big nail archives season after season.']]],
    'cta_red' => ['Your nail designs deserve the spotlight.', 'Pin every roundup — before the trend peaks.'],
    'faq_title' => 'Nail Websites + Pinterest — FAQ',
    'faq' => [
        ['Which templates are best for nail roundups?', 'Number templates and four-photo collages — the styles most nail roundup pins use.'],
        ['Can I use photos from other creators?', 'Only use photos you own or have permission to use, and credit creators as required.'],
        ['Can AI create pins for all my roundups?', 'Yes — select hundreds of posts and approve once.'],
        ['Can Auto Blog write nail posts?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them for you.'],
        ['When should I pin seasonal nails?', 'About 6–8 weeks before each season or holiday.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to trend on Pinterest?', 'Hundreds of nail pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
