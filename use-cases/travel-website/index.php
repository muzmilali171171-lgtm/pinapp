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
    'slug' => 'travel-website',
    'name' => 'Travel Websites',
    'accent' => '#0ea5e9',
    'power_noun' => 'travel guides',
    'autoblog_niche' => 'destinations and travel tips',
    'meta' => [
        'title' => "Pinterest for Travel Blogs & Travel Websites | $app",
        'description' => 'Pin every destination guide on your travel site automatically. AI designs travel pins from your photos and schedules them for trip planners.',
        'keywords' => 'Pinterest for travel blogs, travel blog Pinterest strategy, travel pins, destination guide pins, travel website traffic, Pinterest travel marketing, travel tips pins',
    ],
    'hero' => [
        'badge' => '✈️ For Travel Bloggers & Travel Sites',
        'h1' => 'Pinterest Automation for Travel Websites',
        'h1_accent' => 'Get Found by Trip Planners',
        'sub' => 'Trips are planned on Pinterest months before anyone packs. Turn your destination guides, packing lists and travel tips into pins that trip planners save — and keep them going out all year.',
        'bullets' => [['🗺️', 'Pins From Your Own Travel Photography'], ['📍', 'Destination Guides, Itineraries & Packing Lists'], ['🌍', 'AI Writes Place-Specific Titles Planners Search'], ['🗓️', 'Pinned Ahead of Each Travel Season'], ['🗂️', 'Boards by Country, City and Trip Type']],
        'cta' => 'Start Pinning My Travel Guides — Free',
        'chips' => ['✈️ Guide pinned', '💾 Saved to Italy Trip', '📈 Guide visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Destination Guide Link', 'text' => 'We turn your travel photos into pins in seconds.', 'placeholder' => 'https://yourtravelblog.com/3-days-in-lisbon/'],
    'marquee' => ['City guides', 'Itineraries', 'Packing lists', 'Hidden gems', 'Road trips', 'Budget travel', 'Beach getaways', 'Where to stay', 'Food guides', 'Travel hacks'],
    'results' => ['title' => 'See the Results: Guides That Travel Far', 'text' => 'Travel guides stay useful for years. Pinned consistently, each one keeps sending planners your way, season after season.', 'stats' => [[5, 'x', 'more traffic, up to'], [500, '+', 'premium pin templates'], [365, '', 'days of pins in one run'], [10, '', 'pins per guide, max']], 'alt' => 'Pinterest traffic growth for a travel blog'],
    'steps' => ['title' => 'From Travel Guide to', 'title_accent' => 'Saved Pin', 'text' => 'Scan your guides, pick a style that shows off your photos, and schedule by season.', 'items' => [
        ['icon' => '🗺️', 'label' => 'Setup', 'title' => 'Scan Your Travel Site', 'alt' => 'Scanning a travel website', 'points' => ['Every guide and itinerary listed from your sitemap.', 'Search by country or city and select in bulk.']],
        ['icon' => '📸', 'label' => 'Design', 'title' => 'Let Your Photos Shine', 'alt' => 'Choosing travel pin templates', 'points' => ['Full-bleed and overlay templates for big scenery.', 'Collages combine several spots from one guide.']],
        ['icon' => '🗓️', 'label' => 'Schedule', 'title' => 'Pin Before Travel Season', 'alt' => 'Scheduling travel pins by season', 'points' => ['Summer trips pinned in spring, ski trips in autumn.', 'Several pins per guide, weeks apart.']],
        ['icon' => '🧳', 'label' => 'Approve', 'title' => 'Approve Before Your Next Trip', 'alt' => 'Approving travel pins', 'points' => ['Review and approve — pins publish while you travel.', 'Unapproved runs wait in Drafts.']],
    ]],
    'features' => ['eyebrow' => 'MADE FOR TRAVEL CONTENT', 'title' => 'Why Travel Bloggers Automate Pinterest', 'text' => 'Keep publishing pins even when you’re offline on the road.', 'items' => [
        ['🌄', 'Photo-first templates', 'Overlay and full-bleed designs that let scenery lead.'],
        ['📍', 'Place-specific copy', 'AI writes titles with the destination and trip type.'],
        ['🧳', 'Runs while you travel', 'Schedule months ahead, then go explore.'],
        ['🗂️', 'Destination boards', 'Pins sorted by country, city or trip style — or new boards created.'],
        ['🔁', 'More pins per guide', 'Different photos and angles from one long guide.'],
        ['✍️', 'Auto Blog', 'AI writes travel posts with images and pins them for you.'],
    ]],
    'playbook' => ['title' => 'Pinterest Strategy for Travel Sites', 'text' => 'How travel creators turn guides into traffic.', 'tips' => [
        ['Name the place and the trip', '“3 Days in Lisbon: First-Timer Itinerary” beats “Lisbon Diaries”.'],
        ['Pin 3–6 months ahead', 'People plan big trips well in advance.'],
        ['Make several pins per guide', 'Food, views and hotels from one guide reach different planners.'],
        ['Use destination boards', 'Pinterest understands “Portugal Travel” better than “My Trips”.'],
        ['Refresh evergreen guides', 'Update your top guides and give them fresh pins.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Travel Pins', 'rows' => [['Pin design', 'One guide at a time', 'Hundreds of guides in 1 click'], ['While travelling', 'Pinning stops', 'Keeps publishing'], ['Seasonal timing', 'Missed windows', 'Scheduled ahead'], ['New posts', 'Written on the road', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'See Which Destinations Planners Want', 'text' => 'Plan your next trip and posts around what Pinterest saves.', 'cards' => ['Track clicks for every guide.', 'Remove weak pins to keep engagement strong.', 'Your top destinations at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a travel site', 'Removing weak travel pins', 'Top travel pins', 'Travel pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'travel creators', 'text' => 'Bloggers, guides and tourism sites.', 'cards' => [['Travel bloggers', 'Keep traffic coming while you’re off exploring.'], ['Tour & tourism sites', 'Put your destination in front of planners early.'], ['Travel publishers', 'Pin a huge guide archive consistently.']]],
    'cta_red' => ['Be on the board before they book.', 'Pin your travel guides automatically.'],
    'faq_title' => 'Travel Websites + Pinterest — FAQ',
    'faq' => [
        ['Can I schedule pins while I travel?', 'Yes. Approve once and pins publish on schedule, even when you’re offline.'],
        ['Which templates suit travel?', 'Full-photo overlay and framed templates show scenery best; collages work for multi-stop guides.'],
        ['Can AI create pins for all my guides at once?', 'Yes — select hundreds of guides and approve once.'],
        ['Can Auto Blog write travel posts?', 'On plans that include Auto Blog, AI writes posts with images from your titles and publishes them. Add your own first-hand details where you can.'],
        ['How far ahead should I pin?', 'For big trips, 3–6 months before the season.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your travel guides saved?', 'Hundreds of travel pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
