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
'slug' => 'sustainable-fashion', 'name' => 'Sustainable Fashion Brands', 'short' => 'Sustainable Fashion Brand', 'site' => 'Store', 'accent' => '#4d7c0f', 'noun' => 'products', 'niche' => 'sustainable fashion and slow living',
'title' => 'Pinterest for Sustainable Fashion Brands', 'desc' => 'Reach conscious shoppers on Pinterest. AI turns your eco-friendly collections and slow-fashion guides into pins and schedules them in 1 click.',
'kw' => 'sustainable fashion Pinterest, eco fashion brand marketing, slow fashion pins, ethical clothing Pinterest, capsule wardrobe pins, conscious fashion traffic',
'badge' => '🌿 For Sustainable Fashion Brands', 'h1' => 'Pinterest Automation for Sustainable Fashion', 'h1_accent' => 'Conscious Shoppers, Found',
'sub' => 'Capsule wardrobes, slow fashion and ethical brands are growing Pinterest topics. Pin your collections and your guides so conscious shoppers find you when they plan their wardrobe.',
'bullets' => [['🌿', 'Natural, Earthy Templates & Palettes'], ['👚', 'Collections, Capsule Guides & Care Tips'], ['🤖', 'AI Writes Titles Around Materials & Values'], ['⚡', 'Your Whole Store Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Slow-Fashion Guides']],
'chips' => ['🌿 Collection pinned', '💾 Saved to Capsule', '🛒 Order'],
'placeholder' => 'https://yourbrand.com/products/organic-linen-shirt',
'marquee' => ['Capsule wardrobe', 'Organic cotton', 'Linen', 'Slow fashion', 'Ethical brands', 'Minimalist style', 'Clothing care', 'Upcycled fashion', 'Neutral outfits', 'Wardrobe essentials'],
'results' => ['Values-Driven Discovery', 'Conscious shoppers research before they buy. Your guides and products stay on their boards while they decide.'],
'features' => ['Why Sustainable Brands Automate Pinterest', 'Grow without shouting.', [
    ['🌿', 'Earthy designs', 'Neutral palettes and minimal templates.'],
    ['📖', 'Education pins', 'Care guides and capsule wardrobes.'],
    ['🔤', 'Material-aware copy', 'Organic cotton, linen, recycled — in titles where true.'],
    ['🧩', 'Capsule collages', 'Several pieces in one pin.'],
    ['🗂️', 'Wardrobe boards', 'Organised automatically.'],
    ['✍️', 'Auto Blog', 'AI writes slow-fashion guides with images.'],
]],
'playbook' => ['Pinterest Tips for Sustainable Brands', 'Earn trust, then sales.', [
    ['Lead with education', 'Capsule guides and care tips get saved.'],
    ['Name the material', '“Organic Linen Shirt for Summer”.'],
    ['Be accurate about claims', 'Only make sustainability claims you can back up.'],
    ['Show pieces in capsules', 'Outfit combinations sell multiple items.'],
    ['Pin seasonal capsules', 'Spring and autumn capsules ahead of season.'],
]],
'who' => ['conscious brands', 'Eco labels and slow-fashion shops.', [['Eco fashion labels', 'Reach shoppers who share your values.'], ['Slow-fashion boutiques', 'Keep your curation visible.'], ['Upcycling brands', 'Show one-of-a-kind pieces.']]],
'faq' => [
    ['Can I pin guides as well as products?', 'Yes — pin both from one scan.'],
    ['What about sustainability claims?', 'Keep claims accurate and backed up on your pages.'],
    ['Which templates suit eco brands?', 'Minimal and editorial templates with earthy palettes.'],
],
'cta_red' => ['Conscious shoppers are looking for you.', 'Pin your collections automatically.'],
'cta_dark' => 'Ready to grow your sustainable brand?',
]);
uc_render_page($pdo, $user, $uc);
