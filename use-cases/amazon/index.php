<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = [
    'slug' => 'amazon',
    'power_noun' => 'posts',
    'autoblog_niche' => 'Amazon product reviews and gift guides',
    'name' => 'Amazon',
    'accent' => '#ff9900',
    'order' => ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'compare', 'analytics', 'pricing', 'who', 'testimonials', 'real_results', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Pinterest for Amazon Affiliates & Sellers | $app",
        'description' => "Promote Amazon products on Pinterest on autopilot. Turn product reviews, gift guides and brand pages into AI-designed pins that send buyers toward Amazon.",
        'keywords' => 'Pinterest Amazon affiliate, Amazon affiliate Pinterest pins, Pinterest for Amazon sellers, promote Amazon products on Pinterest, Amazon Associates Pinterest, Amazon storefront Pinterest, Pinterest affiliate marketing automation',
    ],
    'hero' => [
        'badge' => '📦 For Amazon Associates & Brands',
        'h1' => 'Pinterest Marketing for Amazon Affiliates & Sellers',
        'h1_accent' => 'Pins That Point Buyers to Amazon',
        'sub' => 'Pinterest users search for products to buy. Turn your product reviews, “best of” roundups, gift guides and brand pages into scheduled pins that send ready-to-buy visitors toward Amazon.',
        'bullets' => [
            ['📝', 'Pin Your Reviews, Roundups & Gift Guides Automatically'],
            ['🏷️', 'Listicle Templates Built for “Best 10…” Style Posts'],
            ['🤖', 'AI Writes Buyer-Intent Titles & Descriptions'],
            ['📅', 'Schedule a Year of Affiliate Pins in Minutes'],
            ['📊', 'See Which Product Pins Drive Clicks'],
        ],
        'cta' => 'Start Pinning Amazon Content — Free',
        'chips' => ['📦 Roundup pinned', '🔗 Click to review', '💰 Affiliate traffic'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Review or Gift-Guide Link', 'text' => 'We pull the product photos from your post and design pins with buyer-focused headlines.', 'placeholder' => 'https://yourblog.com/best-air-fryers/'],
    'marquee' => ['Best-of roundups', 'Product reviews', 'Gift guides', 'Amazon finds', 'Deal posts', 'Comparison posts', 'Home gadgets', 'Kitchen must-haves', 'Brand landing pages', 'Seasonal buying guides'],
    'features' => [
        'eyebrow' => 'HOW IT FITS AMAZON',
        'title' => 'The Smart Way to Promote Amazon Products on Pinterest',
        'text' => 'Pins work best when they lead to a page that helps people decide — your review, your roundup, or your brand page — which then sends them to Amazon.',
        'items' => [
            ['🧾', 'Built for review content', 'Pins link to your review or roundup page, where your affiliate links and disclosures live.'],
            ['🔢', 'Listicle-friendly templates', 'Big-number templates like “15 Kitchen Gadgets Under $25” are made for Amazon roundups.'],
            ['🛒', 'Brand owners too', 'Selling on Amazon with your own brand site? Pin your product and brand pages and send traffic to your listings.'],
            ['🎯', 'Buyer-intent copy', 'AI writes titles and descriptions around what shoppers actually search: “best”, “under $50”, “for small spaces”.'],
            ['🔁', 'Many pins per post', 'Each roundup gets several pin designs over time, each highlighting a different product photo.'],
            ['🗂️', 'Niche boards', 'Products land on boards like “Kitchen Gadgets” or “Gifts for Him” — AI picks or creates them.'],
        ],
    ],
    'results' => [
        'title' => 'See the Results: Evergreen Affiliate Traffic',
        'text' => 'A good gift guide or review can get saved and clicked for years. Consistent pinning turns your best posts into a steady stream of shoppers.',
        'stats' => [[5, 'x', 'more traffic, up to'], [365, '', 'days of pins scheduled at once'], [500, '+', 'premium pin templates'], [47, '%', 'higher engagement after pruning']],
        'alt' => 'Pinterest traffic growth for an Amazon affiliate website',
    ],
    'steps' => [
        'title' => 'Affiliate Posts to', 'title_accent' => 'Pinterest Traffic in 4 Steps',
        'text' => 'Point us at your website. We find your reviews and roundups, design the pins and publish them on a schedule.',
        'items' => [
            ['icon' => '🔎', 'label' => 'Setup', 'title' => 'Scan Your Review Site', 'alt' => 'Scanning an Amazon affiliate blog for review posts', 'points' => ['We list every post and page from your sitemap.', 'Search for “best”, “review” or “gift” and select your money pages first.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Choose Buyer-Friendly Designs', 'alt' => 'Choosing listicle pin templates for Amazon roundups', 'points' => ['Number templates for roundups, clean product templates for single reviews.', 'Collages show several products from one post in one pin.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Pace It for Your Account', 'alt' => 'Pin scheduling pace for an affiliate Pinterest account', 'points' => ['Warm-up mode for new accounts, fixed daily pins for established ones.', 'Several pins per post, spaced weeks apart.']],
            ['icon' => '💸', 'label' => 'Publish', 'title' => 'Approve and Let It Run', 'alt' => 'Approving scheduled affiliate pins', 'points' => ['Review every pin and its copy, then approve.', 'Pins publish on schedule while you write your next review.']],
        ],
    ],
    'playbook' => [
        'title' => 'Pinterest + Amazon: A Playbook That Follows the Rules',
        'text' => 'Traffic is only useful if it lasts. These habits keep your affiliate pins effective and compliant.',
        'tips' => [
            ['Send pins to your own content first', 'Review and roundup pages convert better than bare product links, and they’re where your affiliate disclosure lives.'],
            ['Always disclose affiliate links', 'Keep a clear disclosure on the page and follow the Amazon Associates Operating Agreement and Pinterest’s rules for affiliate content.'],
            ['Use your own or permitted images', 'Use product photos from your own post (your own photos or images you’re allowed to use) rather than copying listing images.'],
            ['Match the season', 'Gift guides and holiday roundups should be pinned 45–60 days early — Pinterest shoppers plan ahead.'],
            ['Refresh your best posts', 'Update prices and picks in your top roundups, then give them fresh pin designs.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Affiliate Pinning vs Automation',
        'rows' => [
            ['Finding posts to pin', 'Scroll your own blog', 'Every post listed from your sitemap'],
            ['Pin images', 'Design each product collage', 'Collage and listicle templates, auto-filled'],
            ['Pin copy', 'Write buyer-intent copy by hand', 'AI writes it from your post'],
            ['Posting', 'Daily manual posting', 'Months scheduled in one run'],
            ['Tracking', 'Guess what works', 'Pin analytics built in'],
        ],
    ],
    'analytics' => [
        'title' => 'Know Which Product Pins Earn Clicks',
        'text' => 'See which roundups and reviews Pinterest sends traffic to, so you know what to write next.',
        'cards' => ['Track clicks to every review and roundup.', 'Remove weak pins to keep your engagement healthy.', 'Find your top affiliate posts on Pinterest.', 'Break results down by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for Amazon affiliate content', 'Removing underperforming affiliate pins', 'Top affiliate pins', 'Affiliate pin analytics breakdown'],
    ],
    'pricing_title' => 'Plans for Affiliates and Amazon Brands',
    'who' => [
        'title' => 'Perfect for', 'accent' => 'Amazon-focused creators',
        'text' => 'Anyone whose business depends on sending buyers to Amazon.',
        'cards' => [
            ['Amazon Associates bloggers', 'Turn every review and “best of” list into a steady Pinterest traffic source.'],
            ['Amazon FBA brands', 'Pin your brand site and product pages to build demand beyond Amazon search.'],
            ['Deal & gift-guide sites', 'Keep seasonal guides on Pinterest early and consistently, without extra work.'],
        ],
    ],
    'cta_red' => ['Stop leaving Pinterest traffic on the table.', 'Put your Amazon reviews and roundups in front of shoppers — daily.'],
    'faq_title' => 'Amazon + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Can I pin Amazon product pages directly?', 'Amazon blocks automated page reading, so pins are made from pages on your own site — your reviews, roundups, gift guides or brand pages — which then link to Amazon.'],
        ['Is it allowed to promote Amazon affiliate links on Pinterest?', 'Pinterest allows affiliate content when it is clearly disclosed. Keep a disclosure on your page and follow the Amazon Associates Operating Agreement; check both policies for the latest rules.'],
        ['Which templates work best for roundups?', 'Number templates (for example “15 Gadgets Under $25”) and collage templates that show several products perform well for listicle posts.'],
        ['I sell on Amazon with my own brand. Does this help?', 'Yes. Pin your brand website’s product and landing pages to build awareness and send shoppers to your listings.'],
        ['Where do the pin images come from?', 'From the images on your page. Use your own photos or images you have the right to use.'],
        ['Can I choose the board for each post?', 'Yes. Pick boards yourself, or let AI choose — and create new boards when none fit.'],
        ['How many pins per post can I schedule?', 'Up to 10 per page, each with a different design and headline, spaced out by the gap you choose.'],
        ['Is there a free trial?', 'Try the free Pin Maker on this page with no account, or create a free account to start scheduling.'],
    ],
    'cta_dark' => ['Make your Amazon content work on Pinterest.', 'Reviews and roundups, pinned and scheduled automatically.', 'Start Free →'],
];
uc_render_page($pdo, $user, $uc);
