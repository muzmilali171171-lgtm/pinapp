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
'slug' => 'baby-gear-rental', 'name' => 'Baby Gear Rental', 'short' => 'Baby Gear Rental Business', 'site' => 'Website', 'accent' => '#38bdf8', 'noun' => 'pages', 'niche' => 'travelling with babies and toddlers',
'title' => 'Pinterest for Baby Gear Rental Businesses', 'desc' => 'Reach travelling parents on Pinterest. AI turns your rental pages and family travel guides into pins and schedules them in 1 click.',
'kw' => 'baby gear rental marketing, baby equipment rental Pinterest, travelling with baby pins, family travel Pinterest, stroller rental marketing, local business Pinterest',
'badge' => '🍼 For Baby Gear Rental Businesses', 'h1' => 'Pinterest Automation for Baby Gear Rentals', 'h1_accent' => 'Reach Parents Before They Pack',
'sub' => 'Parents plan family trips on Pinterest — packing lists, destination guides, “travelling with a baby” tips. Pin your rental pages and local guides so parents find you before they fly.',
'bullets' => [['🧳', 'Pins for Rental Pages & Family Travel Guides'], ['📍', 'Destination-Specific Titles Written by AI'], ['🗓️', 'Holiday & Summer Travel Peaks Scheduled Ahead'], ['⚡', 'All Your Pages Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Family Travel Guides']],
'chips' => ['🍼 Guide pinned', '💾 Saved to Family Trip', '📩 Booking'],
'placeholder' => 'https://yourrental.com/stroller-rental-orlando/',
'marquee' => ['Travel with baby', 'Stroller rental', 'Crib rental', 'Car seat rental', 'Family vacation', 'Packing lists', 'Disney with toddler', 'Beach with baby', 'Toddler travel', 'Family-friendly hotels'],
'results' => ['Bookings From Trip Planners', 'Family trips are planned weeks or months ahead. Your guides and rental pages are there when parents plan.'],
'features' => ['Why Rental Businesses Use Pinterest', 'Meet parents where they plan trips.', [
    ['🧳', 'Guide-led pins', 'Packing lists and destination tips that lead to rentals.'],
    ['📍', 'Local copy', 'AI adds your city and destination.'],
    ['🗓️', 'Travel-season timing', 'Summer and holiday travel pinned early.'],
    ['🧩', 'Gear collages', 'Show your gear sets.'],
    ['🗂️', 'Destination boards', 'Sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes family travel guides with images.'],
]],
'playbook' => ['Pinterest Tips for Rental Businesses', 'Turn trip planning into bookings.', [
    ['Write destination guides', '“Orlando With a Toddler: What to Rent” gets saved.'],
    ['Pin packing lists', 'Parents save them for every trip.'],
    ['Name your city', 'Location words connect you to local trips.'],
    ['Pin before peak season', 'Summer trips are planned in spring.'],
    ['Link guides to booking', 'Every guide should lead to your rental page.'],
]],
'who' => ['rental businesses', 'Local and multi-city services.', [['Baby gear rental companies', 'Bookings from planners.'], ['Family travel services', 'Reach parents early.'], ['Local businesses', 'Show up in family trip plans.']]],
'faq' => [
    ['Does Pinterest work for a local service?', 'Yes — destination guides and packing lists reach parents planning trips to your area.'],
    ['Can I pin my rental pages?', 'Yes — rental, product and guide pages can all be pinned.'],
    ['When should I pin for summer travel?', 'Around March to May.'],
],
'cta_red' => ['Reach parents before they pack.', 'Pin your guides and rentals automatically.'],
'cta_dark' => 'Ready to get more bookings from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
