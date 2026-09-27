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
'slug' => 'affiliate-marketing-blog', 'name' => 'Affiliate Marketing Blogs', 'short' => 'Affiliate Blog', 'site' => 'Blog', 'accent' => '#22c55e', 'noun' => 'posts', 'niche' => 'product reviews and buying guides',
'title' => 'Pinterest for Affiliate Marketing Blogs', 'desc' => 'Drive affiliate traffic with Pinterest on autopilot. AI turns reviews, roundups and buying guides into pins and schedules hundreds in 1 click.',
'kw' => 'Pinterest affiliate marketing, affiliate blog Pinterest, affiliate pins, product review pins, buying guide Pinterest, Pinterest affiliate traffic, affiliate marketing automation',
'badge' => '🔗 For Affiliate Marketers', 'h1' => 'Pinterest Automation for Affiliate Marketing Blogs', 'h1_accent' => 'Buyers Before They Buy',
'sub' => 'Pinterest users search with buying intent — “best”, “under $50”, “gift for”. Turn every review, roundup and buying guide into pins that bring ready-to-buy readers to your content.',
'bullets' => [['🏷️', 'Roundup Templates for “Best 10…” Posts'], ['🎯', 'AI Writes Buyer-Intent Titles'], ['⚡', 'Hundreds of Money Posts Pinned in 1 Click'], ['✍️', 'Auto Blog Writes New Buying Guides With Images'], ['📊', 'Analytics Show Which Posts Get Clicks']],
'chips' => ['🔗 Review pinned', '🛒 Click to guide', '💰 Commission'],
'placeholder' => 'https://yourblog.com/best-standing-desks/',
'marquee' => ['Best-of lists', 'Product reviews', 'Gift guides', 'Buying guides', 'Comparisons', 'Deals', 'Budget picks', 'Software reviews', 'Home gadgets', 'Seasonal guides'],
'results' => ['Evergreen Affiliate Traffic', 'A strong buying guide can get clicked for years. Pinning every money post consistently builds a stream of buyer-intent visitors.'],
'eyebrow' => 'BUILT FOR AFFILIATES',
'features' => ['Why Affiliate Marketers Automate Pinterest', 'More clicks to the posts that earn.', [
    ['🏷️', 'Roundup templates', 'Big numbers for “15 Best…” lists.'],
    ['🎯', 'Buyer-intent copy', 'AI writes around “best”, “under”, “for beginners”.'],
    ['🔁', 'Several pins per guide', 'Each featured product can lead a pin.'],
    ['🎁', 'Gift-season timing', 'Gift guides pinned 45–60 days early.'],
    ['📊', 'Know what earns', 'See which guides get the clicks.'],
    ['✍️', 'Auto Blog', 'AI writes new guides with images and pins them — you add your first-hand notes.'],
]],
'playbook' => ['Affiliate Pinterest Playbook', 'Traffic that lasts — and stays within the rules.', [
    ['Pin your content, not bare links', 'Guides convert better and hold your disclosure.'],
    ['Disclose clearly', 'Follow your affiliate programme terms and Pinterest’s rules for affiliate content.'],
    ['Use buyer-intent phrases', '“Best Budget Blender for Smoothies” matches real searches.'],
    ['Refresh top guides', 'Update picks and prices, then pin new designs.'],
    ['Plan for Q4', 'Gift guides go out in September and October.'],
]],
'who' => ['affiliate marketers', 'Niche sites to review publishers.', [['Niche site owners', 'More traffic to the pages that earn.'], ['Review publishers', 'Keep a large catalogue of guides pinned.'], ['Creators & influencers', 'Pin your recommendations and storefront pages.']]],
'faq' => [
    ['Are affiliate pins allowed on Pinterest?', 'Pinterest allows affiliate content that is clearly disclosed. Check both Pinterest’s rules and your affiliate programme’s terms.'],
    ['Should pins link to my post or directly to the product?', 'Linking to your guide usually converts better and keeps your disclosure in place; some programmes also restrict direct links.'],
    ['Which templates work for roundups?', 'Number templates and collages that show several products.'],
],
'cta_red' => ['More buyers for your best posts.', 'Pin every review and guide automatically.'],
'cta_dark' => 'Ready to grow affiliate traffic from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
