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
'slug' => 'fashion-boutique', 'name' => 'Fashion Boutiques', 'short' => 'Fashion Boutique', 'site' => 'Boutique', 'accent' => '#be185d', 'noun' => 'products', 'niche' => 'outfits, style guides and fashion trends',
'title' => 'Pinterest for Fashion Boutiques & Clothing Shops', 'desc' => 'Turn every piece in your boutique into outfit pins shoppers save. AI designs fashion pins, writes the copy and schedules your whole store in 1 click.',
'kw' => 'Pinterest for boutiques, fashion boutique Pinterest, clothing store Pinterest marketing, outfit pins, boutique traffic, women’s fashion Pinterest',
'badge' => '👗 For Online Fashion Boutiques', 'h1' => 'Pinterest Automation for Fashion Boutiques', 'h1_accent' => 'Outfits Shoppers Save',
'sub' => 'Shoppers build outfit boards before they buy. Turn every dress, top and accessory in your boutique into outfit-style pins — the kind modelled on the most-saved fashion pins — and keep them publishing.',
'bullets' => [['👗', 'Outfit-Style Templates Modelled on Top Fashion Pins'], ['🛍️', 'Every Product & Collection Pinned in 1 Click'], ['🤖', 'AI Writes Titles by Occasion, Season & Body Type'], ['📅', 'Seasonal Drops Scheduled Ahead'], ['✍️', 'Auto Blog Writes Style Guides That Link to Products']],
'chips' => ['👗 Outfit pinned', '💾 Saved to Fall Outfits', '🛒 Order'],
'placeholder' => 'https://yourboutique.com/products/linen-midi-dress',
'marquee' => ['Fall outfits', 'Wedding guest dresses', 'Work outfits', 'Plus size fashion', 'Petite style', 'Date night', 'Vacation outfits', 'Capsule wardrobe', 'Boho dresses', 'Accessories'],
'results' => ['Outfits That Keep Selling', 'Outfit pins get saved to boards and revisited each season. Your products stay in shoppers’ plans.'],
'design_title' => 'Style It Like a Fashion Pin', 'design_point' => 'Serif highlight boxes, outlined headlines and outfit collages.',
'eyebrow' => 'BUILT FOR BOUTIQUES',
'features' => ['Why Boutiques Automate Pinterest', 'Every piece, styled and pinned.', [
    ['👗', 'Fashion templates', 'Modelled on popular outfit pins.'],
    ['🧩', 'Outfit collages', 'Several pieces or angles in one pin.'],
    ['🔤', 'Shopper-first copy', 'By occasion, season and fit.'],
    ['📅', 'Drop timing', 'New collections pinned ahead of launch.'],
    ['🗂️', 'Style boards', 'Workwear, date night, vacation — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes style guides that feature your products.'],
]],
'playbook' => ['Pinterest Tips for Boutiques', 'How fashion pins turn into orders.', [
    ['Show the full outfit', 'Styled looks beat single products.'],
    ['Say the occasion', '“What to Wear to a Fall Wedding”.'],
    ['Include fit words', 'Petite, plus size, tall — shoppers search them.'],
    ['Pin seasons early', 'Fall outfits in August, holiday party looks in October.'],
    ['Write style guides', 'Guides link to many products at once.'],
]],
'who' => ['boutique owners', 'Online and local boutiques.', [['Online boutiques', 'Every product in front of shoppers.'], ['Local boutiques with web stores', 'Bring online and in-store customers.'], ['Fashion brands', 'Keep every collection visible.']]],
'faq' => [
    ['Are there templates like popular outfit pins?', 'Yes — several templates are modelled on popular fashion pins: serif highlight boxes, outlined headlines and outfit collages.'],
    ['Can I pin whole collections?', 'Yes — collection and category pages can be selected alongside products.'],
    ['Does it work with Shopify boutiques?', 'Yes — any store with public product pages.'],
],
'cta_red' => ['Outfits shoppers save.', 'Pin your whole boutique automatically.'],
'cta_dark' => 'Ready to grow your boutique with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
