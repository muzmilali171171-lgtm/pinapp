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
'slug' => 'creative-market', 'name' => 'Creative Market Shops', 'short' => 'Creative Market Shop', 'site' => 'Shop', 'accent' => '#65a30d', 'noun' => 'products', 'niche' => 'design assets, fonts and templates',
'title' => 'Pinterest for Creative Market Shops', 'desc' => 'Pin your fonts, templates and graphics to reach more designers. AI designs preview pins for your Creative Market products and schedules them in 1 click.',
'kw' => 'Creative Market Pinterest, promote fonts Pinterest, design templates Pinterest, Creative Market shop marketing, graphic assets Pinterest, sell design assets',
'badge' => '🎨 For Creative Market Sellers', 'h1' => 'Pinterest Automation for Creative Market Shops', 'h1_accent' => 'Assets Designers Save',
'sub' => 'Designers collect fonts, templates and graphics on Pinterest. Pin every product preview in your shop and keep your assets in front of designers every day.',
'bullets' => [['🔤', 'Pins for Fonts, Templates, Graphics & Mockups'], ['🖼️', 'Preview Collages Show What’s Included'], ['🤖', 'AI Writes Titles by Style & Use Case'], ['⚡', 'Your Whole Shop Pinned in 1 Click'], ['🗂️', 'Boards by Asset Type & Style']],
'chips' => ['🔤 Font pinned', '💾 Saved to Design Resources', '🛒 Sale'],
'placeholder' => 'https://creativemarket.com/yourshop/12345-your-product',
'marquee' => ['Script fonts', 'Canva templates', 'Mockups', 'Procreate brushes', 'Social templates', 'Logo kits', 'Illustrations', 'Presets', 'Patterns', 'Branding kits'],
'results' => ['Assets That Stay on Designers’ Boards', 'Designers save resources and come back when they need them. Your products stay discoverable.'],
'features' => ['Why Creative Market Sellers Automate Pinterest', 'Your previews, in designers’ feeds.', [
    ['🖼️', 'Preview-first', 'Templates that show your product previews.'],
    ['🔤', 'Style copy', '“Modern Script Font for Wedding Invitations”.'],
    ['🔁', 'Several previews per product', 'Each preview image can lead a pin.'],
    ['🗂️', 'Asset boards', 'Fonts, templates, mockups — sorted.'],
    ['📊', 'Best sellers', 'See which products get clicks.'],
    ['✍️', 'Auto Blog', 'AI writes design-tip posts that feature your products.'],
]],
'playbook' => ['Pinterest Tips for Asset Sellers', 'How design assets get saved.', [
    ['Lead with the best preview', 'Show the asset in real use.'],
    ['Say the use case', 'Wedding, branding, social media.'],
    ['Pin every preview image', 'Different previews reach different designers.'],
    ['Create resource roundups', '“20 Best Script Fonts for Logos” posts link to many products.'],
    ['Refresh best sellers', 'New preview pins for top products.'],
]],
'who' => ['asset creators', 'Type designers and template makers.', [['Font designers', 'Your fonts in front of designers.'], ['Template creators', 'Pin every template preview.'], ['Illustrators', 'Sell graphics and patterns.']]],
'faq' => [
    ['Can I pin Creative Market product pages?', 'Yes — paste your product links. Pages that can’t be read automatically show as skipped; you can pin from your own site.'],
    ['Will pins use my preview images?', 'Yes — the images on each product page.'],
    ['Which pin size works for previews?', '2:3 and long 1:2.1 both work well.'],
],
'cta_red' => ['Assets designers save.', 'Pin every product automatically.'],
'cta_dark' => 'Ready to sell more design assets with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
