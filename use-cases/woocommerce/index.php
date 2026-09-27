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
    'slug' => 'woocommerce',
    'power_noun' => 'products',
    'autoblog_niche' => 'your WooCommerce store',
    'name' => 'WooCommerce',
    'accent' => '#7f54b3',
    'meta' => [
        'title' => "WooCommerce Pinterest Automation & Pin Scheduler | $app",
        'description' => "Pin every WooCommerce product automatically. We read your sitemap, design pins with AI and schedule them to Pinterest for free, steady store traffic.",
        'keywords' => 'WooCommerce Pinterest, Pinterest for WooCommerce, WooCommerce Pinterest automation, WooCommerce pin scheduler, auto pin WooCommerce products, WordPress store Pinterest marketing, WooCommerce product pins',
    ],
    'hero' => [
        'badge' => '🛒 Made for WooCommerce Stores',
        'h1' => 'Pinterest Automation for WooCommerce',
        'h1_accent' => 'Every Product, Pinned',
        'sub' => 'Your WooCommerce catalog already has everything Pinterest needs — product photos, names and details. We turn each product page into scheduled pins that bring shoppers back to your store.',
        'bullets' => [
            ['🗺️', 'Reads Your Product Sitemap (Yoast, Rank Math or WordPress)'],
            ['🖼️', 'Uses Your Real Product and Gallery Photos'],
            ['🤖', 'AI Writes Pin Titles, Descriptions, Alt Text & Keywords'],
            ['📅', 'Schedules Months of Product Pins in One Run'],
            ['🔌', 'No Plugin Needed on Your Store'],
        ],
        'cta' => 'Start Pinning WooCommerce Products — Free',
        'chips' => ['🛒 Product imported', '📌 Pin scheduled', '💜 WooCommerce ready'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Try It on a WooCommerce Product Page', 'text' => 'Paste one product link and see ready-to-post pins made from your own product photos.', 'placeholder' => 'https://yourstore.com/product/your-product/'],
    'marquee' => ['WooCommerce product pins', 'Product category pins', 'Variable products', 'Sale items', 'Gift guides', 'New arrivals', 'Digital downloads', 'Bundles', 'Seasonal collections', 'Best sellers'],
    'results' => [
        'title' => 'See the Results: A Catalog That Markets Itself',
        'text' => 'Most WooCommerce stores have hundreds of product pages nobody promotes. Pinned regularly, each one becomes a small, steady source of visitors.',
        'stats' => [[500, '+', 'premium pin templates'], [100, '%', 'free design editor, no Canva Pro'], [5, 'x', 'more traffic, up to'], [20, '/day', 'pins at full pace']],
        'alt' => 'Pinterest traffic growth for a WooCommerce store',
    ],
    'steps' => [
        'title' => 'From WooCommerce to', 'title_accent' => 'Pinterest in 4 Steps',
        'text' => 'Paste your store link. We handle the product list, the pin designs, the copy and the posting schedule.',
        'items' => [
            ['icon' => '🗺️', 'label' => 'Setup', 'title' => 'Scan Your WooCommerce Store', 'alt' => 'Scanning a WooCommerce product sitemap', 'points' => ['We read the sitemap your WordPress site already publishes — product pages, categories and posts.', 'Search by product name or URL and pick what to pin, or select everything.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick Your Pin Style', 'alt' => 'Choosing templates and colours for WooCommerce product pins', 'points' => ['Unlimited templates with a live preview on your own product photos.', 'Brand colours, unlimited fonts and a free Canva-style design editor.', 'Collage templates show several product photos in one pin.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Set How Fast Pins Go Out', 'alt' => 'Scheduling settings for WooCommerce product pins', 'points' => ['Fixed pins per day, or a warm-up plan for brand-new Pinterest accounts.', 'Several pins per product, spaced a month apart by default.', 'Choose boards yourself or let AI place each product.']],
            ['icon' => '✅', 'label' => 'Approve', 'title' => 'Review and Approve', 'alt' => 'Approving AI-designed WooCommerce product pins', 'points' => ['Every pin can be edited — text, photos, template, board.', 'Approve once and the whole batch is scheduled to Pinterest.']],
        ],
    ],
    'features' => [
        'eyebrow' => 'BUILT AROUND WOOCOMMERCE',
        'title' => 'Why WooCommerce Stores Automate Pinterest',
        'text' => 'WordPress gives you control of your store. We give you a hands-off way to promote it on Pinterest.',
        'items' => [
            ['🔌', 'Nothing to install', 'No plugin, no code, no API keys. We read your public product pages like a shopper would.'],
            ['🗺️', 'Works with your sitemap', 'Yoast, Rank Math, All in One and the built-in WordPress sitemap are all supported.'],
            ['🧩', 'Any theme, any builder', 'Storefront, Astra, Flatsome, Elementor — if the product page shows photos, we can pin it.'],
            ['🏷️', 'Categories and tags too', 'Pin category pages and buying guides alongside single products.'],
            ['📝', 'Blog + shop in one place', 'Pin your WordPress blog posts and your products from the same dashboard.'],
            ['🧠', 'AI that reads the product', 'Titles and descriptions are written from the product page — not a generic template.'],
        ],
    ],
    'playbook' => [
        'title' => 'How to Get WooCommerce Sales from Pinterest',
        'text' => 'A practical routine that works for small and large catalogs.',
        'tips' => [
            ['Give every product at least three pins', 'Different photo, different headline, spaced weeks apart. Each pin reaches different searchers.'],
            ['Make product photos pin-friendly', 'Clean, well-lit images at least 600px wide work best. Very small or banner images are skipped automatically.'],
            ['Use category pages for broad searches', 'A “Minimalist Wall Clocks” category pin catches people who aren’t looking for one specific product yet.'],
            ['Link blog posts to products', 'Pin your how-to and gift-guide posts too — they warm up buyers before they reach the product.'],
            ['Keep a steady daily pace', 'Consistency beats bursts. A few pins every day for months grows traffic more than 100 pins in one day.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Pins vs Automated WooCommerce Pins',
        'rows' => [
            ['Getting the product list', 'Copy links one by one', 'Read from your sitemap in seconds'],
            ['Making pin images', 'Design each in Canva', 'AI-designed from your product photos'],
            ['Pin copy', 'Write every title and description', 'Written by AI, editable'],
            ['Posting', 'Log in and post daily', 'Scheduled automatically'],
            ['Store changes', 'Plugins, code, API keys', 'Nothing to install'],
        ],
    ],
    'analytics' => [
        'title' => 'See Which WooCommerce Products Pinterest Loves',
        'text' => 'Find the products and pin styles that bring clicks, then give them more pins.',
        'cards' => ['Track clicks and impressions for every product pin.', 'Clear out pins that don’t perform so your account stays strong.', 'Spot your top products on Pinterest at a glance.', 'Compare by board, URL, keyword, title, description and time.'],
        'alts' => ['Pinterest analytics for a WooCommerce store', 'Removing low-performing WooCommerce pins', 'Top WooCommerce product pins', 'WooCommerce pin analytics breakdown'],
    ],
    'pricing_title' => 'Plans for WooCommerce Stores',
    'who' => [
        'title' => 'Made for', 'accent' => 'WooCommerce store owners',
        'text' => 'Whether you sell ten products or ten thousand.',
        'cards' => [
            ['Solo shop owners', 'Stop spending evenings in Canva. Scan the store once and let the pins roll out for months.'],
            ['Growing WooCommerce brands', 'Keep every product visible on Pinterest, not just the few you have time to promote.'],
            ['WordPress agencies', 'Offer Pinterest marketing to your WooCommerce clients without adding hours to your week.'],
        ],
    ],
    'cta_red' => ['Your WooCommerce products deserve an audience.', 'Put them in front of Pinterest shoppers — automatically.'],
    'faq_title' => 'WooCommerce + Pinterest — FAQ',
    'faq' => [
        ['Do I need to install a WooCommerce plugin?', 'No. We read your public product pages and your sitemap. Nothing is installed on your WordPress site.'],
        ['Which sitemaps are supported?', 'The built-in WordPress sitemap (wp-sitemap.xml) and the sitemaps made by Yoast, Rank Math and similar plugins, including sitemap index files.'],
        ['Will it pin variable products correctly?', 'Yes. We use the main product photos and gallery images shown on the product page, and the pin links to that product page.'],
        ['Can I pin product categories?', 'Yes. Category, tag and landing pages can be selected just like products.'],
        ['What happens to small product thumbnails?', 'Images that are too small or too wide for a pin are skipped automatically, so pins only use photos that look good.'],
        ['Can I edit pins before they go live?', 'Yes. Every pin can be edited or removed before you approve the schedule. Unapproved runs stay in Drafts.'],
        ['Does it work with Pinterest Business accounts?', 'Yes. You connect with Pinterest’s official login; a Business account is recommended for analytics.'],
        ['Can I also pin my WordPress blog posts?', 'Yes. Blog posts and product pages can be pinned from the same scan.'],
    ],
    'cta_dark' => ['Turn your WooCommerce catalog into Pinterest traffic.', 'Scan your store, pick a look, approve — and you’re done for months.', 'Pin My WooCommerce Store →'],
];
uc_render_page($pdo, $user, $uc);
