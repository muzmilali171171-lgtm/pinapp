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
    'slug' => 'redbubble',
    'power_noun' => 'designs',
    'autoblog_niche' => 'your art niche',
    'name' => 'Redbubble',
    'accent' => '#e41321',
    'order' => ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'compare', 'pricing', 'analytics', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Redbubble Pinterest Marketing on Autopilot | $app",
        'description' => "Promote your Redbubble designs on Pinterest automatically. Turn artwork into scheduled pins, reach new fans and sell more stickers, tees and prints.",
        'keywords' => 'Redbubble Pinterest, Pinterest for Redbubble artists, promote Redbubble designs, Redbubble marketing, Redbubble traffic Pinterest, sell more on Redbubble, print on demand Pinterest marketing',
    ],
    'hero' => [
        'badge' => '🎨 For Redbubble Artists',
        'h1' => 'Pinterest Marketing for Redbubble Artists',
        'h1_accent' => 'Get Your Designs Discovered',
        'sub' => 'Redbubble search is crowded. Pinterest is where people look for sticker ideas, gift tees and wall art — pin your designs there consistently and let new fans find your shop.',
        'bullets' => [
            ['🖌️', 'Pins Made From Your Artwork & Product Mockups'],
            ['🧃', 'Stickers, Tees, Prints, Mugs — One Design, Many Pins'],
            ['🔤', 'AI Writes Niche Titles Fans Actually Search'],
            ['📅', 'Schedule Hundreds of Designs in One Run'],
            ['🎯', 'Board Placement by Theme and Niche'],
        ],
        'cta' => 'Start Pinning My Designs — Free',
        'chips' => ['🎨 Design pinned', '⭐ New fan', '🧃 Sticker sold'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Design or Shop Link', 'text' => 'See your artwork turned into ready-to-post pins in seconds.', 'placeholder' => 'https://www.redbubble.com/i/sticker/... or your art site'],
    'marquee' => ['Sticker designs', 'Graphic tees', 'Wall art prints', 'Phone cases', 'Fandom art', 'Cute animals', 'Funny quotes', 'Vintage aesthetic', 'Cottagecore', 'Gift ideas'],
    'features' => [
        'eyebrow' => 'FOR PRINT-ON-DEMAND ARTISTS',
        'title' => 'Built for Artists Who Sell on Redbubble',
        'text' => 'Your designs live on dozens of products. Pinterest is the best place to show them off.',
        'items' => [
            ['🧩', 'One design, many angles', 'Pin the sticker, the tee and the print of the same design as separate pins.'],
            ['🧑‍🎨', 'Artwork-first templates', 'Minimal frames and clean layouts that let your art stand out.'],
            ['🏷️', 'Niche keyword copy', '“Cute frog sticker for water bottle” style titles written by AI for each design.'],
            ['🗂️', 'Boards by theme', 'Designs are placed on boards by theme — animals, quotes, fandoms — or new boards are created.'],
            ['📈', 'Evergreen discovery', 'A saved pin keeps sending visitors long after your design drops off Redbubble’s “new” pages.'],
            ['⚡', 'Bulk friendly', 'Have 300 designs? Select them all and schedule in one sitting.'],
        ],
    ],
    'results' => [
        'title' => 'See the Results: Designs That Keep Getting Found',
        'text' => 'Pinterest rewards steady posting. A few pins a day across your whole portfolio adds up to steady discovery over months.',
        'stats' => [[20, '/day', 'pins at full pace'], [10, '', 'pins per design, max'], [500, '+', 'premium pin templates'], [5, 'x', 'more traffic, up to']],
        'alt' => 'Pinterest traffic growth for a Redbubble artist',
    ],
    'steps' => [
        'title' => 'From Redbubble Design to', 'title_accent' => 'Pinterest Pin',
        'text' => 'Add your designs, choose a style, and let the schedule run.',
        'items' => [
            ['icon' => '🖼️', 'label' => 'Setup', 'title' => 'Add Your Design Pages', 'alt' => 'Adding Redbubble design pages', 'points' => ['Paste product or design links, or scan your own art website.', 'Pick your best designs to start, then add the rest.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Choose Art-Friendly Templates', 'alt' => 'Choosing pin templates for artwork', 'points' => ['Minimal templates keep the focus on your artwork.', 'Collages show one design on several products.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Spread Pins Over Time', 'alt' => 'Scheduling Redbubble design pins', 'points' => ['Set pins per day and how long before each design is pinned again.', 'Warm-up mode keeps a new account safe.']],
            ['icon' => '🚀', 'label' => 'Approve', 'title' => 'Approve Your Portfolio', 'alt' => 'Approving Redbubble design pins', 'points' => ['Review and approve — your whole portfolio is scheduled.', 'Anything you don’t approve waits in Drafts.']],
        ],
    ],
    'playbook' => [
        'title' => 'How Redbubble Artists Grow With Pinterest',
        'text' => 'The artists who win on Pinterest are specific, consistent and patient.',
        'tips' => [
            ['Pin each product type separately', 'A sticker searcher and a t-shirt searcher are different people. Give each product its own pin.'],
            ['Use niche words in titles', '“Retro mushroom sticker” is easier to find than “cool sticker”.'],
            ['Group designs into themed boards', 'Boards like “Frog Stickers” or “Book Lover Gifts” help Pinterest understand your art.'],
            ['Pin trending themes early', 'Seasonal and holiday designs should go out a month or two before the season.'],
            ['Keep old designs alive', 'Designs from last year can still sell — give them fresh pins.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Pinning vs Automated Redbubble Pins',
        'rows' => [
            ['Pin images', 'Screenshot and edit each design', 'Pins made from your product images'],
            ['Titles & tags', 'Rewrite for every design', 'AI writes niche copy'],
            ['Large portfolios', 'Only a few get promoted', 'Every design gets pinned'],
            ['Consistency', 'Bursts, then silence', 'Daily, on schedule'],
        ],
    ],
    'pricing_title' => 'Plans for Print-on-Demand Artists',
    'analytics' => [
        'title' => 'Find Your Best-Performing Designs',
        'text' => 'See which designs Pinterest users click, so you know what to create next.',
        'cards' => ['Track clicks and saves for each design pin.', 'Clear weak pins to keep engagement strong.', 'Spot your top designs on Pinterest.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for Redbubble designs', 'Removing weak design pins', 'Top design pins', 'Design pin analytics breakdown'],
    ],
    'who' => [
        'title' => 'Made for', 'accent' => 'independent artists',
        'text' => 'From hobby illustrators to full-time print-on-demand sellers.',
        'cards' => [
            ['Illustrators & designers', 'Put your portfolio in front of people who save art ideas every day.'],
            ['Full-time POD sellers', 'Promote hundreds of designs without spending your whole day on Pinterest.'],
            ['Fandom & niche artists', 'Reach the exact communities that love your themes.'],
        ],
    ],
    'cta_red' => ['Your art deserves more than one page of search results.', 'Pin every design and let Pinterest do the discovering.'],
    'faq_title' => 'Redbubble + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Can I pin my Redbubble product pages?', 'Yes, paste your design or product links. If a page can’t be read automatically it shows as skipped — you can pin the same design from your own art site or blog instead.'],
        ['Do pins link back to Redbubble?', 'Yes. Each pin links to the page it was made from.'],
        ['Is it OK to promote Redbubble designs on Pinterest?', 'Yes — promoting your own designs is common. Only pin artwork you own the rights to.'],
        ['Can one design get several pins?', 'Yes. Set up to 10 pins per page, each with a different template and headline, spaced out over time.'],
        ['Which templates suit artwork?', 'Minimal and framed templates keep the focus on your art; collage templates show a design across several products.'],
        ['Do I need a Pinterest Business account?', 'It’s recommended for analytics. You connect through Pinterest’s official login.'],
        ['Can I try it for free?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your Redbubble designs discovered?', 'Pin your portfolio once. Keep getting found.', 'Start Free →'],
];
uc_render_page($pdo, $user, $uc);
