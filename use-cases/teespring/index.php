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
'slug' => 'teespring', 'name' => 'Spring (Teespring) Creators', 'short' => 'Spring Store', 'site' => 'Store', 'accent' => '#2563eb', 'noun' => 'products', 'niche' => 'your merch and fan community',
'title' => 'Pinterest for Spring (Teespring) Merch Creators', 'desc' => 'Promote your Spring merch on Pinterest automatically. AI designs pins for your products and schedules them in 1 click.',
'kw' => 'Teespring Pinterest, Spring merch Pinterest, promote creator merch, YouTuber merch marketing, creator store traffic, merch Pinterest',
'badge' => '👕 For Spring Merch Creators', 'h1' => 'Pinterest Automation for Spring (Teespring) Creators', 'h1_accent' => 'Merch Fans Can Find',
'sub' => 'Your fans aren’t only on your channel. Pin every product in your Spring store so fans — and new people who love your niche — discover your merch on Pinterest.',
'bullets' => [['👕', 'Pins for Every Product in Your Store'], ['🎨', 'Designs That Match Your Brand'], ['🤖', 'AI Writes Fan-Friendly Titles'], ['⚡', 'Your Whole Store Pinned in 1 Click'], ['📅', 'Launch & Holiday Drops Scheduled Ahead']],
'chips' => ['👕 Merch pinned', '💾 Saved by a fan', '🛒 Order'],
'placeholder' => 'https://your-store.creator-spring.com/listing/your-product',
'marquee' => ['Creator merch', 'Hoodies', 'Tees', 'Mugs', 'Stickers', 'Posters', 'Gaming merch', 'Podcast merch', 'Fan art', 'Limited drops'],
'results' => ['Merch Discovery That Lasts', 'Pins keep your merch visible long after the launch video.'],
'features' => ['Why Creators Pin Their Merch', 'Your merch, beyond your channel.', [
    ['👕', 'Every product', 'Pin the whole store, not just the launch item.'],
    ['🎨', 'Brand-matched pins', 'Your colours and fonts.'],
    ['📅', 'Drop timing', 'Schedule pins around launches and holidays.'],
    ['🔤', 'Fan-friendly copy', 'Titles fans and new buyers search.'],
    ['🗂️', 'Merch boards', 'Organised automatically.'],
    ['✍️', 'Auto Blog', 'AI writes posts about your drops and pins them.'],
]],
'playbook' => ['Pinterest Tips for Merch Creators', 'Turn pins into merch sales.', [
    ['Show merch being worn', 'Lifestyle photos beat flat mockups.'],
    ['Say who it’s for', '“Gift for Gamers” or your community name.'],
    ['Pin before launches', 'Build anticipation.'],
    ['Pin holiday bundles early', '6–8 weeks before the holidays.'],
    ['Link your channel on the page', 'Visitors can find your content too.'],
]],
'who' => ['creators', 'YouTubers, streamers and podcasters.', [['YouTubers', 'Merch sales beyond your videos.'], ['Streamers', 'Fans find your merch on Pinterest.'], ['Podcasters', 'Pin merch for your listeners.']]],
'faq' => [
    ['Can I pin my Spring store products?', 'Yes — paste your product links. If a page can’t be read automatically it shows as skipped.'],
    ['Do pins link to my store?', 'Yes — each pin links to its product page.'],
    ['Can I schedule around a merch drop?', 'Yes — set the first publish date and pace to match your launch.'],
],
'cta_red' => ['Merch fans can find.', 'Pin every product in your store automatically.'],
'cta_dark' => 'Ready to sell more merch with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
