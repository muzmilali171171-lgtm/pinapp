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
'slug' => 'beauty-and-skincare', 'name' => 'Beauty & Skincare', 'short' => 'Beauty Blog', 'site' => 'Site', 'accent' => '#db2777', 'noun' => 'posts', 'niche' => 'skincare routines, makeup and beauty products',
'title' => 'Pinterest for Beauty & Skincare Sites', 'desc' => 'Pin every skincare routine, makeup look and product review automatically. Elegant beauty templates, AI-written titles and hundreds of pins in 1 click.',
'kw' => 'Pinterest for beauty blogs, skincare pins, makeup looks Pinterest, skincare routine pins, beauty product review Pinterest, beauty brand Pinterest marketing',
'badge' => '🧴 For Beauty Bloggers & Skincare Brands', 'h1' => 'Pinterest Automation for Beauty & Skincare', 'h1_accent' => 'Routines People Save',
'sub' => 'Skincare routines, makeup looks and product picks are saved by millions of beauty lovers. Turn every post and product into polished pins that match your brand — and keep them publishing.',
'bullets' => [['✨', 'Elegant Templates for Routines, Looks & Products'], ['🧴', 'Product Pages and Blog Posts Pinned Together'], ['🤖', 'AI Writes Titles by Skin Type, Concern & Look'], ['⚡', 'Hundreds of Pages Pinned in 1 Click'], ['🎨', 'Your Brand Palette on Every Pin']],
'chips' => ['🧴 Routine pinned', '💾 Saved to Skincare', '🛒 Product visit'],
'placeholder' => 'https://yoursite.com/skincare-routine-for-dry-skin/',
'marquee' => ['Skincare routines', 'Makeup looks', 'Dry skin', 'Acne tips', 'Glowy skin', 'Drugstore dupes', 'Eyeliner', 'Clean beauty', 'Anti-aging', 'Lip combos'],
'results' => ['Beauty Pins That Keep Glowing', 'Beauty routines and product picks get saved and revisited. Consistent pinning keeps readers and shoppers coming.'],
'features' => ['Why Beauty Brands & Bloggers Automate Pinterest', 'Polished pins, every day, without the design hours.', [
    ['✨', 'Elegant templates', 'Soft serif boxes, arch frames and editorial layouts.'],
    ['🧴', 'Product + content', 'Pin product pages and routines side by side.'],
    ['🔤', 'Concern-aware copy', 'AI writes by skin type, concern and look.'],
    ['🎨', 'On-brand colours', 'Use your brand palette on every pin.'],
    ['🗂️', 'Beauty boards', 'Skincare, makeup, hair and nails — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes beauty posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Beauty Sites', 'How beauty pins get saved.', [
    ['Be specific about skin type', '“Morning Skincare Routine for Oily Skin”.'],
    ['Show the look up close', 'Clear, well-lit photos win.'],
    ['Pin dupes and roundups', 'Comparison pins are huge in beauty.'],
    ['Stay accurate', 'Avoid medical claims your products can’t support.'],
    ['Pin seasonal routines', 'Winter skincare, summer SPF — ahead of the season.'],
]],
'who' => ['beauty creators', 'Bloggers, brands and shops.', [['Beauty bloggers', 'Keep every routine and review pinned.'], ['Skincare brands', 'Pin products and education together.'], ['Beauty retailers', 'Keep a large catalogue visible.']]],
'faq' => [
    ['Can I pin beauty products?', 'Yes — product pages and reviews both work. Keep product claims accurate.'],
    ['Which templates suit beauty?', 'Soft serif boxes, arch frames and editorial layouts with your brand palette.'],
    ['Can I use my brand colours?', 'Yes — set a custom palette once and every pin uses it.'],
],
'cta_red' => ['Polished pins, every day.', 'Your beauty content, pinned automatically.'],
'cta_dark' => 'Ready to grow your beauty brand on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
