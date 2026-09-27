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
'slug' => 'marketing-agency', 'name' => 'Marketing Agencies', 'short' => 'Agency Client Site', 'site' => 'Client Sites', 'accent' => '#e60023', 'noun' => 'client pages', 'niche' => 'each client’s niche',
'title' => 'Pinterest Automation for Marketing Agencies', 'desc' => 'Run Pinterest for every client from one dashboard. AI designs and schedules hundreds of pins per client in 1 click, with Team Management and analytics.',
'kw' => 'Pinterest for agencies, Pinterest marketing agency tool, manage client Pinterest, Pinterest agency software, social media agency Pinterest, Pinterest client reporting',
'badge' => '📣 For Marketing & Social Agencies', 'h1' => 'Pinterest Automation for Marketing Agencies', 'h1_accent' => 'More Clients, Same Team',
'sub' => 'Pinterest is a service clients ask for but few agencies can deliver profitably by hand. Run Pinterest for every client site from one dashboard — hundreds of pins per client in one click, with shared team access.',
'bullets' => [['🏢', 'Every Client Site in One Dashboard'], ['⚡', 'Hundreds of Pins per Client in 1 Click'], ['👥', 'Team Management — No Shared Logins'], ['✍️', 'Auto Blog for Clients That Need Content'], ['📊', 'Analytics to Show Clients What Works']],
'chips' => ['🏢 Client added', '📌 1,200 pins scheduled', '📊 Report ready'],
'placeholder' => 'https://clientsite.com/blog/post',
'marquee' => ['Client onboarding', 'Content calendars', 'E-commerce clients', 'Blog clients', 'Local businesses', 'Reporting', 'Team access', 'Brand templates', 'Bulk scheduling', 'Auto blogging'],
'results' => ['A Profitable Pinterest Service', 'Automation turns Pinterest from hours of manual work into a scalable, recurring service.'],
'stats' => [[1, '', 'dashboard for all clients'], [500, '+', 'premium pin templates'], [365, '', 'days of pins per run'], [5, 'x', 'more traffic, up to']],
'eyebrow' => 'BUILT FOR AGENCIES',
'features' => ['Why Agencies Automate Pinterest', 'Deliver more, spend less time.', [
    ['🏢', 'Multi-site', 'Add every client website to one account.'],
    ['👥', 'Team Management', 'Invite your team by email — no password sharing.'],
    ['🎨', 'Brand per client', 'Each client’s palette, fonts or custom template.'],
    ['⚡', 'Bulk scheduling', 'Hundreds of pins per client in one run.'],
    ['✍️', 'Auto Blog', 'Offer content creation plus pinning on supported plans.'],
    ['📊', 'Client reporting', 'Pin analytics you can share with clients.'],
]],
'playbook' => ['How Agencies Package Pinterest', 'Turn Pinterest into a recurring service.', [
    ['Start with a pin audit', 'Show clients what their pages could become.'],
    ['Bundle content + pins', 'Auto Blog plus pinning is an easy upsell.'],
    ['Set a warm-up for new accounts', 'Protect client accounts with a safe pace.'],
    ['Report monthly', 'Clicks and top pins justify the retainer.'],
    ['Template per client', 'Consistent branding builds client trust.'],
]],
'who' => ['agencies', 'Social, content and growth agencies.', [['Social media agencies', 'Add Pinterest without adding staff.'], ['Content agencies', 'Write, publish and pin for clients.'], ['E-commerce agencies', 'Pin client catalogs at scale.']]],
'faq' => [
    ['Can I manage many client sites?', 'Yes — add multiple websites and Pinterest accounts, and use Team Management for your staff.'],
    ['Do clients need to share passwords?', 'No — clients connect Pinterest through the official login, and your team works through shared access.'],
    ['Can each client have its own branding?', 'Yes — pick a palette, fonts or a custom template (made in the free editor) per run.'],
],
'cta_red' => ['More clients, same team.', 'Run Pinterest for every client automatically.'],
'cta_dark' => 'Ready to add Pinterest to your agency services?',
]);
uc_render_page($pdo, $user, $uc);
