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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-hashtag-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'teepublic', 'name' => 'TeePublic Artists', 'short' => 'TeePublic Store', 'site' => 'Store', 'accent' => '#0f172a', 'noun' => 'designs', 'niche' => 'your design niche',
'title' => 'Pinterest for TeePublic Artists & Designers', 'desc' => 'Get your TeePublic designs discovered on Pinterest. AI designs pins for your artwork and products and schedules hundreds in 1 click.',
'kw' => 'TeePublic Pinterest, promote TeePublic designs, TeePublic marketing, sell more on TeePublic, t-shirt design Pinterest, artist merch traffic',
'badge' => '🎽 For TeePublic Artists', 'h1' => 'Pinterest Automation for TeePublic Artists', 'h1_accent' => 'Designs Beyond the Marketplace',
'sub' => 'TeePublic search is crowded. Pinterest is where people look for gift tees, fandom art and funny shirts — pin your designs there consistently and let new fans find your store.',
'bullets' => [['🎨', 'Pins From Your Design & Product Pages'], ['🧩', 'One Design, Many Products, Many Pins'], ['🔤', 'AI Writes Niche Titles Fans Search'], ['⚡', 'Your Portfolio Pinned in 1 Click'], ['🗂️', 'Theme Boards Created Automatically']],
'chips' => ['🎽 Design pinned', '⭐ New fan', '🛒 Sale'],
'placeholder' => 'https://www.teepublic.com/t-shirt/your-design',
'marquee' => ['Funny tees', 'Fandom art', 'Retro designs', 'Pun shirts', 'Animal designs', 'Gaming tees', 'Book lover gifts', 'Science shirts', 'Holiday tees', 'Stickers'],
'results' => ['Designs That Keep Getting Found', 'Saved pins keep sending visitors long after a design leaves the marketplace’s front pages.'],
'features' => ['Why TeePublic Artists Automate Pinterest', 'Discovery you control.', [
    ['🎨', 'Artwork-first', 'Minimal frames that let the design shine.'],
    ['🧩', 'Product variety', 'Tee, hoodie, sticker, mug — each can be a pin.'],
    ['🔤', 'Niche copy', 'Titles fans type.'],
    ['🎄', 'Seasonal designs', 'Holiday designs pinned early.'],
    ['🗂️', 'Theme boards', 'Organised by theme.'],
    ['📊', 'Top designs', 'See what gets clicks.'],
]],
'playbook' => ['Pinterest Tips for TeePublic Artists', 'Grow beyond marketplace search.', [
    ['Pin each product type', 'Different buyers, different pins.'],
    ['Use niche words', '“Retro Cat Astronaut Tee”.'],
    ['Group by theme', 'Boards help Pinterest understand your art.'],
    ['Pin seasonal early', 'Holiday designs a month or two ahead.'],
    ['Only pin your own work', 'Avoid designs you don’t have rights to.'],
]],
'who' => ['independent artists', 'Illustrators and designers.', [['Illustrators', 'Your art in front of new fans.'], ['Full-time sellers', 'Promote your whole portfolio.'], ['Niche artists', 'Reach the communities that love your themes.']]],
'faq' => [
    ['Can I pin TeePublic pages?', 'Yes — paste your design links. Pages that can’t be read automatically show as skipped; you can pin the same design from your own site.'],
    ['Do pins link back to TeePublic?', 'Yes — each pin links to the page it was made from.'],
    ['Is promoting on Pinterest allowed?', 'Yes — promoting your own designs is common. Only pin artwork you own.'],
],
'cta_red' => ['Designs beyond the marketplace.', 'Pin your whole portfolio automatically.'],
'cta_dark' => 'Ready to get your TeePublic designs discovered?',
]);
uc_render_page($pdo, $user, $uc);
