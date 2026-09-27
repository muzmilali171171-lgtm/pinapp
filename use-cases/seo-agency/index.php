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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'seo-agency', 'name' => 'Search Marketing Agencies', 'short' => 'Agency Client Site', 'site' => 'Client Sites', 'accent' => '#2563eb', 'noun' => 'client pages', 'niche' => 'each client’s niche',
'title' => 'Pinterest for Search Marketing Agencies', 'desc' => 'Add Pinterest traffic to your client growth packages. AI turns client pages into pins, schedules hundreds per client in 1 click and reports the clicks.',
'kw' => 'Pinterest for search marketing agencies, organic traffic agency Pinterest, Pinterest traffic for clients, content marketing agency Pinterest, Pinterest visual search traffic',
'badge' => '🔍 For Search & Organic Growth Agencies', 'h1' => 'Pinterest for Search Marketing Agencies', 'h1_accent' => 'A Second Search Engine for Clients',
'sub' => 'Pinterest is a visual search engine that sends clicks to your clients’ pages. Add it to your organic growth packages — pins for hundreds of client pages in one click, with analytics that show the clicks.',
'bullets' => [['🔎', 'Pinterest as a Second Search Channel for Clients'], ['⚡', 'Hundreds of Client Pages Pinned in 1 Click'], ['🤖', 'AI Writes Keyword-Rich Pin Titles & Descriptions'], ['✍️', 'Auto Blog Creates Content That Gets Pinned Too'], ['📊', 'Click Analytics for Client Reports']],
'chips' => ['🔍 Client pages pinned', '📈 Referral clicks up', '📊 Report ready'],
'placeholder' => 'https://clientsite.com/guide/',
'marquee' => ['Organic traffic', 'Content clusters', 'Keyword research', 'Evergreen guides', 'Client reporting', 'Referral traffic', 'Visual search', 'E-commerce clients', 'Local clients', 'Content briefs'],
'results' => ['More Organic Traffic for Clients', 'Pinterest traffic compounds like search traffic — and pins keep sending visits to the same pages you already optimise.'],
'stats' => [[5, 'x', 'more traffic, up to'], [365, '', 'days of pins per run'], [500, '+', 'premium pin templates'], [1, '', 'dashboard for all clients']],
'eyebrow' => 'BUILT FOR ORGANIC GROWTH AGENCIES',
'features' => ['Why Organic Growth Agencies Add Pinterest', 'Another search engine, the same content.', [
    ['🔎', 'Visual search', 'Pinterest users search with intent — your clients’ pages can answer.'],
    ['🔤', 'Keyword-rich pin copy', 'AI writes titles and descriptions around each page’s topic.'],
    ['⚡', 'Scale', 'Hundreds of pages per client in one run.'],
    ['✍️', 'Content + distribution', 'Auto Blog creates posts and pins them.'],
    ['👥', 'Team access', 'Invite your team without shared passwords.'],
    ['📊', 'Reporting', 'Clicks by board, URL, keyword and time.'],
]],
'playbook' => ['How Agencies Sell Pinterest Traffic', 'Package it with the work you already do.', [
    ['Pin the pages you already optimise', 'Guides and category pages make great pins.'],
    ['Use the same keyword research', 'Pin titles and descriptions follow the same topics.'],
    ['Report referral clicks', 'Show Pinterest traffic next to your other channels.'],
    ['Add it as a monthly line item', 'Pinning runs on autopilot once set up.'],
    ['Warm up new client accounts', 'Use the new-account pace to stay safe.'],
]],
'who' => ['organic growth agencies', 'Search, content and growth teams.', [['Search agencies', 'Add a new traffic channel to retainers.'], ['Content agencies', 'Distribute every piece you write.'], ['Freelance consultants', 'Offer Pinterest without extra hours.']]],
'faq' => [
    ['Does Pinterest traffic help clients?', 'Pinterest sends referral visits to the pages you pin — a separate channel you can report alongside search traffic.'],
    ['Can I manage multiple clients?', 'Yes — add many websites and Pinterest accounts, with Team Management for staff.'],
    ['Does AI use keywords in pins?', 'Yes — AI writes titles, descriptions and keywords from each page’s content; you can edit them before approving.'],
],
'cta_red' => ['A second search engine for every client.', 'Pin client pages automatically.'],
'cta_dark' => 'Ready to add Pinterest to your client packages?',
]);
uc_render_page($pdo, $user, $uc);
