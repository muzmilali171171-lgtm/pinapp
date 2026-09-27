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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-hashtag-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'print-on-demand', 'name' => 'Print-on-Demand Sellers', 'short' => 'Print-on-Demand Store', 'site' => 'Store', 'accent' => '#8b5cf6', 'noun' => 'designs', 'niche' => 'your print-on-demand niche',
'title' => 'Pinterest Automation for Print-on-Demand', 'desc' => 'Pin every print-on-demand design across tees, mugs, posters and more. AI designs pins from your mockups and schedules hundreds in 1 click.',
'kw' => 'print on demand Pinterest, POD Pinterest marketing, promote POD designs, t-shirt designs Pinterest, POD store traffic, print on demand free traffic',
'badge' => '🖨️ For Print-on-Demand Sellers', 'h1' => 'Pinterest Automation for Print-on-Demand', 'h1_accent' => 'Every Design, Every Product',
'sub' => 'Print-on-demand is a numbers game: many designs, many products, thin margins. Pinterest gives you free, compounding traffic — pin every design on every product and let the clicks show you your winners.',
'bullets' => [['👕', 'Tees, Mugs, Posters, Totes — Every Product Pinned'], ['🖼️', 'Mockup Collages Show One Design on Many Products'], ['🔤', 'AI Writes Niche Titles Buyers Search'], ['⚡', 'Hundreds of Designs Pinned in 1 Click'], ['🧪', 'Analytics Reveal Your Winning Designs']],
'chips' => ['👕 Design pinned', '📈 Winner found', '🛒 Order'],
'placeholder' => 'https://yourstore.com/products/funny-teacher-shirt',
'marquee' => ['Funny tees', 'Teacher gifts', 'Dog mom mugs', 'Posters', 'Tote bags', 'Hoodies', 'Holiday designs', 'Family shirts', 'Nurse gifts', 'Stickers'],
'results' => ['Find Winners Without Ad Spend', 'Pin every design and the data tells you which to push. Traffic compounds instead of stopping when ads stop.'],
'design_title' => 'Show Off Your Mockups', 'design_point' => 'Collage templates show one design on several products.',
'features' => ['Why POD Sellers Automate Pinterest', 'Free traffic that scales with your catalog.', [
    ['🧪', 'Design testing', 'Pin everything; clicks reveal demand.'],
    ['🖼️', 'Mockup collages', 'One design across products in one pin.'],
    ['🔤', 'Niche copy', '“Funny Night Shift Nurse Mug” style titles.'],
    ['🎄', 'Seasonal designs', 'Holiday designs pinned 45–60 days early.'],
    ['🏪', 'Any storefront', 'Shopify, Etsy, WooCommerce or marketplace pages.'],
    ['💸', 'Better margins', 'Organic sales without ad costs.'],
]],
'playbook' => ['A Pinterest Playbook for POD', 'How POD sellers grow on Pinterest.', [
    ['Go niche, then deeper', 'Specific audiences convert better.'],
    ['Use lifestyle mockups', 'Worn or in-room mockups get more saves.'],
    ['Pin every new design', 'Let data pick the winners.'],
    ['Time seasonal drops', 'Pin holiday designs well before the season.'],
    ['Only pin designs you own', 'Avoid trademarks and designs you don’t have rights to.'],
]],
'who' => ['POD sellers', 'Beginners to POD brands.', [['New POD sellers', 'Free traffic while you learn what sells.'], ['Multi-store sellers', 'Run Pinterest for all your stores in one dashboard.'], ['POD brands', 'Keep hundreds of designs visible.']]],
'faq' => [
    ['Which POD platforms work?', 'Any storefront with public product pages — your own store or marketplace product pages. Pages that can’t be read show as skipped.'],
    ['Can one design get several pins?', 'Yes — up to 10 pins per page with different templates and headlines.'],
    ['Are there rules about designs?', 'Only promote designs you own the rights to, and follow Pinterest’s guidelines.'],
],
'cta_red' => ['Every design deserves a chance.', 'Pin your whole POD catalog automatically.'],
'cta_dark' => 'Ready to find your winning designs?',
]);
uc_render_page($pdo, $user, $uc);
