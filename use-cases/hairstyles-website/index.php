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
    'slug' => 'hairstyles-website',
    'name' => 'Hairstyle Websites',
    'accent' => '#c084fc',
    'order' => ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'analytics', 'compare', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'hairstyle posts',
    'autoblog_niche' => 'haircuts, hairstyles and hair colour ideas',
    'meta' => [
        'title' => "Pinterest for Hairstyle Websites & Hair Blogs | $app",
        'description' => 'Pin every hairstyle roundup automatically. Number-style hair pin templates, AI titles by length, texture and age, and hundreds of posts pinned in 1 click.',
        'keywords' => 'Pinterest for hair blogs, hairstyle pins, haircut ideas Pinterest, bob hairstyles pins, hair salon Pinterest marketing, hairstyle website traffic, short hair ideas Pinterest',
    ],
    'hero' => [
        'badge' => '💇‍♀️ For Hair Blogs & Hairstyle Sites',
        'h1' => 'Pinterest Automation for Hairstyle Websites',
        'h1_accent' => 'Roundups Everyone Saves',
        'sub' => 'Before every haircut, people search Pinterest. Turn your hairstyle roundups into the big-number pins that dominate the hair category — “35 Bob Hairstyles for Women Over 40” — and schedule hundreds at once.',
        'bullets' => [['🔢', 'Big-Number Templates Modelled on Top Hair Pins'], ['💇‍♀️', 'Titles by Length, Texture, Face Shape & Age'], ['🖼️', 'Four-Photo Collages for Roundups'], ['⚡', 'Hundreds of Roundups Pinned in 1 Click'], ['✍️', 'Auto Blog Writes New Hair Posts With Images']],
        'cta' => 'Start Pinning My Hair Posts — Free',
        'chips' => ['💇‍♀️ Roundup pinned', '💾 Saved to Short Hair', '📈 Visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Hairstyle Roundup Link', 'text' => 'Get number-style hair pins from your photos.', 'placeholder' => 'https://yourhairblog.com/bob-hairstyles-over-40/'],
    'marquee' => ['Bob hairstyles', 'Pixie cuts', 'Shag haircuts', 'Curtain bangs', 'Balayage', 'Hair over 50', 'Fine hair', 'Curly hair', 'Braids', 'Wedding hair'],
    'features' => ['eyebrow' => 'MADE FOR HAIR CONTENT', 'title' => 'Why Hair Sites Automate Pinterest', 'text' => 'The hair category runs on roundups — and roundups run on consistent pinning.', 'items' => [
        ['🔢', 'Number templates', 'Big “24 Bob Hairstyles for Fine Hair” designs like the pins that win in hair.'],
        ['🖼️', 'Four-look collages', 'Show several styles from one roundup in one pin.'],
        ['🔤', 'Search-precise titles', 'AI writes by length, texture, age and face shape.'],
        ['🔁', 'Many pins per roundup', 'Each style photo can lead its own pin.'],
        ['🗂️', 'Hair boards', 'Short hair, curly hair, over 50 — sorted automatically.'],
        ['✍️', 'Auto Blog', 'AI writes hair roundups with images and pins them.'],
    ]],
    'results' => ['title' => 'See the Results: Evergreen Hair Traffic', 'text' => 'Haircut searches never stop. Pin every roundup and keep a steady stream of readers planning their next cut.', 'stats' => [[5, 'x', 'more traffic, up to'], [10, '', 'pins per roundup, max'], [500, '+', 'premium pin templates'], [20, '/day', 'pins at full pace']], 'alt' => 'Pinterest traffic growth for a hairstyle website'],
    'steps' => ['title' => 'From Hair Roundup to', 'title_accent' => 'Top Pin', 'text' => 'Scan, pick number templates, schedule, approve.', 'items' => [
        ['icon' => '💇‍♀️', 'label' => 'Setup', 'title' => 'Scan Your Hair Posts', 'alt' => 'Scanning a hairstyle website', 'points' => ['Every roundup listed from your sitemap.', 'Search “bob”, “over 50” or “curly” and select.']],
        ['icon' => '🔢', 'label' => 'Design', 'title' => 'Use Hair-Style Templates', 'alt' => 'Choosing number templates for hair pins', 'points' => ['Number and outlined-text templates.', 'Collages for multi-look roundups.']],
        ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Spread Pins Over Months', 'alt' => 'Scheduling hair pins', 'points' => ['Several pins per roundup, weeks apart.', 'Warm-up mode for new accounts.']],
        ['icon' => '✨', 'label' => 'Approve', 'title' => 'Approve and Style On', 'alt' => 'Approving hair pins', 'points' => ['Review and approve.', 'Pins publish on autopilot.']],
    ]],
    'playbook' => ['title' => 'Pinterest Tips for Hairstyle Sites', 'text' => 'How hair creators win the feed.', 'tips' => [
        ['Lead with the number', '“35 Bob Hairstyles” signals value at a glance.'],
        ['Be specific about who it’s for', 'Fine hair, thick hair, over 40, round face — people search that way.'],
        ['Use clear face-and-hair photos', 'The cut should be the obvious focus.'],
        ['Give each look its own pin', 'Different styles reach different searchers.'],
        ['Refresh seasonal colour posts', 'Hair colour trends change with seasons.'],
    ]],
    'analytics' => ['title' => 'See Which Cuts People Want', 'text' => 'Find the styles your audience saves most.', 'cards' => ['Track clicks for every hair pin.', 'Remove weak pins to keep engagement strong.', 'Your top roundups at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a hair website', 'Removing weak hair pins', 'Top hairstyle pins', 'Hair pin analytics breakdown']],
    'compare' => ['title' => 'Manual vs Automated Hair Pins', 'rows' => [['Pin design', 'One roundup at a time', 'Hundreds in 1 click'], ['Titles', 'Hand-written', 'AI writes precise titles'], ['Consistency', 'On and off', 'Every day'], ['New roundups', 'Written one by one', 'Auto Blog writes, publishes & pins']]],
    'who' => ['title' => 'Made for', 'accent' => 'hair creators', 'text' => 'Hair blogs, salons and stylists.', 'cards' => [['Hair & beauty bloggers', 'Keep every roundup pinned all year.'], ['Salons & stylists', 'Show your work to people choosing their next cut.'], ['Beauty publishers', 'Pin big hair archives consistently.']]],
    'cta_red' => ['Be the pin they bring to the salon.', 'Hundreds of hairstyle pins in one click.'],
    'faq_title' => 'Hairstyle Websites + Pinterest — FAQ',
    'faq' => [
        ['Are there templates like the top hair pins?', 'Yes. Several templates are modelled on popular hair pins — big numbers, outlined text and bold bands.'],
        ['Can I use other people’s hair photos?', 'Only use photos you own or have permission to use, and credit as required.'],
        ['Can AI create pins for all my roundups?', 'Yes — select hundreds of posts and approve once.'],
        ['Can Auto Blog write hairstyle posts?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them for you.'],
        ['What pin size is best?', '2:3 (1000 × 1500) works well; long 1:2.1 pins are also popular for roundups.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your hairstyles saved?', 'Hundreds of hair pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
