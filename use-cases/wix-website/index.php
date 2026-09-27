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
'slug' => 'wix-website', 'name' => 'Wix Websites', 'short' => 'Wix Site', 'site' => 'Wix Site', 'accent' => '#0c6efc', 'noun' => 'pages', 'niche' => 'your Wix site’s topics',
'title' => 'Pinterest Automation for Wix Websites & Stores', 'desc' => 'Pin every Wix blog post, product and page automatically. AI designs pins, writes the copy and schedules months of pins in 1 click — no apps needed.',
'kw' => 'Wix Pinterest, Wix blog Pinterest, Wix store Pinterest, auto pin Wix, Wix website traffic, Wix ecommerce Pinterest marketing',
'badge' => '🌐 For Wix Sites & Stores', 'h1' => 'Pinterest Automation for Wix Websites', 'h1_accent' => 'Every Page Working for You',
'sub' => 'Wix makes building easy. We make promoting easy. Pin your Wix blog posts, store products and service pages automatically — and publish new AI-written posts to Wix with Auto Blog.',
'bullets' => [['🗺️', 'Reads Your Wix Sitemap Automatically'], ['🛍️', 'Blog Posts, Store Products & Service Pages'], ['✍️', 'Auto Blog Publishes to Wix and Pins the Posts'], ['⚡', 'Hundreds of Pages Pinned in 1 Click'], ['🔌', 'No Wix App Needed for Pinning']],
'chips' => ['🌐 Page pinned', '✍️ Post published', '📈 Visits up'],
'placeholder' => 'https://yoursite.com/post/your-post',
'marquee' => ['Wix blogs', 'Wix stores', 'Service pages', 'Portfolios', 'Restaurants', 'Salons', 'Coaches', 'Photographers', 'Small businesses', 'Online courses'],
'results' => ['Your Wix Site on Autopilot', 'Every page and product is another way for people to find you. Pinned consistently, they add up to steady traffic.'],
'features' => ['Why Wix Sites Automate Pinterest', 'Built for busy small business owners.', [
    ['🗺️', 'Automatic page discovery', 'Your Wix sitemap lists every page and product.'],
    ['✍️', 'Auto Blog for Wix', 'AI writes posts with images, publishes to your connected Wix site and pins them.'],
    ['🛍️', 'Store products', 'Pin products from Wix Stores with your photos.'],
    ['🎨', 'On-brand designs', 'Your colours and fonts on every pin.'],
    ['🗂️', 'Smart boards', 'AI places every pin.'],
    ['⏱️', 'Minutes a month', 'Set it up once and let it run.'],
]],
'playbook' => ['Pinterest Tips for Wix Sites', 'Simple habits that bring visitors.', [
    ['Pin your blog and products', 'Both bring different visitors.'],
    ['Use clear featured images', 'Good images make good pins.'],
    ['Keep publishing', 'Auto Blog keeps new posts coming on busy weeks.'],
    ['Pin seasonal offers early', 'Holiday products 6–8 weeks ahead.'],
    ['Check analytics monthly', 'Pin more of what gets clicks.'],
]],
'who' => ['Wix site owners', 'Small businesses and creators.', [['Small businesses', 'Bring local and online customers.'], ['Wix store owners', 'Keep every product visible on Pinterest.'], ['Wix bloggers', 'Pin posts and publish new ones automatically.']]],
'faq' => [
    ['Does it work with Wix sitemaps?', 'Yes — Wix generates a sitemap, and we read it to list your pages and products.'],
    ['Can Auto Blog publish to Wix?', 'Yes — connect your Wix site under Add Websites and Auto Blog can publish posts there on plans that include it.'],
    ['Do I need a Wix app?', 'No app is needed for pinning; connecting your site is only needed for Auto Blog publishing.'],
],
'cta_red' => ['Your Wix site, working harder.', 'Pin every page and product automatically.'],
'cta_dark' => 'Ready to grow your Wix site with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
