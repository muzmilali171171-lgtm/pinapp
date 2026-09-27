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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'event-planning', 'name' => 'Event Planners', 'short' => 'Event Planning Website', 'site' => 'Website', 'accent' => '#f59e0b', 'noun' => 'pages', 'niche' => 'party themes and event ideas',
'title' => 'Pinterest for Event Planners & Party Planners', 'desc' => 'Pin party themes and event ideas that bring enquiries. AI designs pins from your events and schedules them ahead of every season in 1 click.',
'kw' => 'event planner Pinterest, party planner marketing, party ideas pins, event decor Pinterest, birthday party themes pins, corporate event ideas Pinterest',
'badge' => '🎉 For Event & Party Planners', 'h1' => 'Pinterest Automation for Event Planners', 'h1_accent' => 'Ideas That Become Bookings',
'sub' => 'Birthdays, showers, corporate events, holidays — every event starts with a Pinterest board. Pin your themes, decor and real events so people planning one find you.',
'bullets' => [['🎈', 'Pins for Themes, Decor & Real Events'], ['📅', 'Seasonal Events Scheduled Weeks Ahead'], ['🤖', 'AI Writes Titles by Theme, Age & Occasion'], ['⚡', 'Your Whole Portfolio Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Party-Idea Posts']],
'chips' => ['🎉 Theme pinned', '💾 Saved to Party Ideas', '📩 Enquiry'],
'placeholder' => 'https://yoursite.com/boho-baby-shower-ideas/',
'marquee' => ['Birthday themes', 'Baby showers', 'Bridal showers', 'Corporate events', 'Balloon garlands', 'Table settings', 'Kids parties', 'Holiday parties', 'Graduation parties', 'Dessert tables'],
'results' => ['Ideas That Keep Bringing Enquiries', 'Party ideas get saved and revisited every time someone plans an event.'],
'features' => ['Why Event Planners Automate Pinterest', 'Be the inspiration behind the next event.', [
    ['🎈', 'Theme pins', 'Bright templates for party themes.'],
    ['🧩', 'Event collages', 'Decor, tables and details in one pin.'],
    ['📅', 'Season timing', 'Holiday and graduation parties pinned early.'],
    ['🔤', 'Occasion copy', 'By theme, age and event type.'],
    ['🗂️', 'Event boards', 'Sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes party-idea posts with images.'],
]],
'playbook' => ['Pinterest Tips for Event Planners', 'Turn saves into bookings.', [
    ['Name the theme', '“Boho Baby Shower Ideas”.'],
    ['Show details', 'Tables, signage and favours get saved.'],
    ['Pin seasonal events early', 'Holiday parties from October.'],
    ['Pin real events', 'With client permission.'],
    ['Link to enquiries', 'Every page should invite a quote request.'],
]],
'who' => ['event pros', 'Planners, stylists and rental companies.', [['Party planners', 'Enquiries from people planning.'], ['Event stylists', 'Show your decor style.'], ['Rental companies', 'Pin decor and rentals in use.']]],
'faq' => [
    ['Can I pin client events?', 'Yes, with your clients’ permission.'],
    ['When should I pin holiday party ideas?', 'From October for December events.'],
    ['Do pins link to my enquiry form?', 'Pins link to the page they were made from — add an enquiry button there.'],
],
'cta_red' => ['Ideas that become bookings.', 'Pin your events automatically.'],
'cta_dark' => 'Ready to book more events from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
