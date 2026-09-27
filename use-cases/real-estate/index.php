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
'slug' => 'real-estate', 'name' => 'Real Estate Agents', 'short' => 'Real Estate Website', 'site' => 'Website', 'accent' => '#0f766e', 'noun' => 'pages', 'niche' => 'home buying, neighborhoods and home tips',
'title' => 'Pinterest for Real Estate Agents & Realtors', 'desc' => 'Listings, neighborhood guides and home tips pinned for buyers. AI designs real estate pins and schedules them in 1 click.',
'kw' => 'real estate Pinterest, realtor Pinterest marketing, listing pins, neighborhood guide Pinterest, home buying tips pins, real estate lead generation',
'badge' => '🏡 For Agents, Brokers & Realtors', 'h1' => 'Pinterest Automation for Real Estate Agents', 'h1_accent' => 'Buyers Who Plan Ahead',
'sub' => 'Home buyers plan on Pinterest — moving checklists, neighborhood guides, home tips and dream homes. Pin your guides and listings so buyers in your area find you first.',
'bullets' => [['🏡', 'Pins for Listings, Neighborhood Guides & Tips'], ['📍', 'Local Titles With City & Neighborhood'], ['📋', 'Buyer & Seller Checklists That Get Saved'], ['⚡', 'Your Whole Site Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Local Guides']],
'chips' => ['🏡 Guide pinned', '💾 Saved to Moving', '📩 Lead'],
'placeholder' => 'https://yourrealtysite.com/living-in-austin-guide/',
'marquee' => ['Neighborhood guides', 'First-time buyers', 'Moving checklists', 'Home staging', 'Curb appeal', 'Open houses', 'Relocation', 'Home tips', 'Market updates', 'Dream homes'],
'results' => ['Leads From Local Planners', 'Relocation and buyer guides get saved months before a move — your name stays with them.'],
'features' => ['Why Agents Automate Pinterest', 'Be there before the house hunt.', [
    ['📍', 'Local guides', 'Neighborhood and city pins for relocators.'],
    ['📋', 'Checklists', 'Buyer, seller and moving checklists.'],
    ['🏡', 'Listing pins', 'Pin listings from your own site.'],
    ['🔤', 'Local copy', 'City and neighborhood in titles.'],
    ['🗂️', 'Area boards', 'Sorted by city and topic.'],
    ['✍️', 'Auto Blog', 'AI drafts local guides — you add local knowledge.'],
]],
'playbook' => ['Pinterest Tips for Real Estate', 'Turn pins into leads.', [
    ['Write neighborhood guides', 'Relocators search “living in…”.'],
    ['Pin checklists', 'Moving checklists get saved and revisited.'],
    ['Follow listing rules', 'Pin listings in line with your brokerage and MLS rules and fair-housing guidelines.'],
    ['Use local words', 'City and neighborhood names in titles.'],
    ['Link to lead forms', 'Every guide should offer a consultation.'],
]],
'who' => ['real estate pros', 'Agents, teams and brokerages.', [['Solo agents', 'Leads from buyers planning ahead.'], ['Teams', 'Pin every area you serve.'], ['Brokerages', 'Consistent pins across your site.']]],
'faq' => [
    ['Can I pin listings?', 'Yes, from your own website — follow your brokerage, MLS and fair-housing rules.'],
    ['Do neighborhood guides work?', 'Yes — relocation and area guides are some of the most-saved real estate pins.'],
    ['Can pins bring local leads?', 'Pins bring visitors to your guides; add clear contact options on those pages.'],
],
'cta_red' => ['Be there before the house hunt.', 'Pin your guides and listings automatically.'],
'cta_dark' => 'Ready to get real estate leads from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
