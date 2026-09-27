<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = uc_build([
'slug' => 'travel-itineraries', 'name' => 'Travel Itinerary Sites', 'short' => 'Travel Itinerary Site', 'site' => 'Site', 'accent' => '#0284c7', 'noun' => 'itineraries', 'niche' => 'day-by-day travel itineraries',
'title' => 'Pinterest for Travel Itineraries & Trip Plans', 'desc' => 'Pin every day-by-day itinerary for trip planners. AI turns your itineraries into save-worthy pins and schedules them ahead of travel season in 1 click.',
'kw' => 'travel itinerary Pinterest, itinerary pins, trip planning Pinterest, 3 days in city pins, road trip itinerary Pinterest, travel planner traffic',
'badge' => '🗺️ For Itinerary Writers & Trip Planners', 'h1' => 'Pinterest Automation for Travel Itineraries', 'h1_accent' => 'Planned Trips, Saved Pins',
'sub' => '“3 Days in Rome”, “7-Day Iceland Road Trip” — itineraries are some of the most-saved travel pins because they solve the planning problem. Pin every one of yours and have them ready months before travel season.',
'bullets' => [['📅', 'Number Templates Built for “X Days in…” Itineraries'], ['🧭', 'Collages Show Several Stops in One Pin'], ['🤖', 'AI Writes Titles With Days, Place & Trip Type'], ['⚡', 'Every Itinerary Pinned in 1 Click'], ['🗓️', 'Scheduled 3–6 Months Before Travel Season']],
'chips' => ['🗺️ Itinerary pinned', '💾 Saved to Japan Trip', '📈 Planner visits'],
'placeholder' => 'https://yoursite.com/3-days-in-rome-itinerary/',
'marquee' => ['3 days in Paris', 'Road trips', 'Weekend getaways', '7-day itineraries', 'First-timer guides', 'Family itineraries', 'Budget trips', 'National parks', 'Island hopping', 'City breaks'],
'results' => ['Itineraries Get Saved and Followed', 'People save an itinerary and come back to it while booking and travelling — so each pin keeps sending visits long after it’s published.'],
'design_title' => 'Show the Journey', 'design_point' => 'Number templates for day counts, collages for multiple stops.',
'features' => ['Why Itinerary Sites Automate Pinterest', 'Itineraries are made for Pinterest planners.', [
    ['📅', 'Day-count templates', 'Big numbers for “5 Days in…” pins.'],
    ['🧭', 'Multi-stop collages', 'Several highlights of the trip in one pin.'],
    ['🗓️', 'Early scheduling', 'Summer trips pinned in spring, winter trips in autumn.'],
    ['🔤', 'Planner-friendly titles', 'AI adds days, destination and trip style.'],
    ['🗂️', 'Destination boards', 'Pins sorted by country and region.'],
    ['✍️', 'Auto Blog', 'AI writes itinerary posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Itinerary Writers', 'How to get itineraries saved.', [
    ['Put the day count in the title', '“4 Days in Lisbon” is exactly what planners search.'],
    ['Say who it’s for', 'First-timers, families, couples, budget travellers.'],
    ['Pin several stops per itinerary', 'Each stop can lead its own pin.'],
    ['Pin 3–6 months ahead', 'Big trips are planned early.'],
    ['Update and re-pin', 'Refresh prices and hours, then give it new pins.'],
]],
'who' => ['itinerary creators', 'Travel bloggers, guides and tour sites.', [['Travel bloggers', 'Turn itineraries into long-lasting traffic.'], ['Local guides', 'Bring bookings from planners.'], ['Tour operators', 'Pin sample itineraries that lead to tours.']]],
'faq' => [
    ['Which templates suit itineraries?', 'Number templates for day counts and collages for multi-stop trips.'],
    ['How early should I pin?', 'Around 3–6 months before the main travel season.'],
    ['Can I pin one itinerary many times?', 'Yes — up to 10 pins per page with different photos and headlines.'],
],
'cta_red' => ['Be the plan they follow.', 'Every itinerary, pinned before travel season.'],
'cta_dark' => 'Ready to get your itineraries saved?',
]);
uc_render_page($pdo, $user, $uc);
