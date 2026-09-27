<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = APP_NAME;

$uc = [
    'slug' => 'shopify',
    'power_noun' => 'products',
    'autoblog_niche' => 'your Shopify store',
    'name' => 'Shopify',
    'meta' => [
        'title' => "Shopify Pinterest Automation: Auto-Pin Products | $app",
        'description' => "Automate Pinterest for your Shopify store. Turn every product page into AI-designed, scheduled pins with keyword-rich titles and get free store traffic.",
        'keywords' => 'Pinterest for Shopify, Shopify Pinterest automation, Shopify pin scheduler, auto pin Shopify products, Pinterest marketing for Shopify, Shopify Pinterest traffic, Shopify product pins, Pinterest app for Shopify',
    ],
    'hero' => [
        'badge' => '🛍️ Built for Shopify Sellers',
        'h1' => 'Pinterest Automation for Shopify Stores',
        'h1_accent' => 'Turn Products Into Traffic',
        'sub' => 'Put your Shopify store on Pinterest autopilot. Every product page becomes a scheduled, on-brand pin — no design work, no manual posting, just shoppers finding your products every day.',
        'bullets' => [
            ['🛒', 'Auto-Pin Every Shopify Product — New Arrivals Included'],
            ['🤖', 'AI Writes Keyword-Rich Titles, Descriptions & Alt Text for Every Pin'],
            ['🕔', 'Schedule Pins for Your Whole Catalog in One Click'],
            ['🎨', 'Pin Designs Matched to Your Store Colours and Fonts'],
            ['💯', 'AI Picks (or Creates) the Right Board for Every Product'],
        ],
        'cta' => 'Start Pinning Your Shopify Store — Free',
        'chips' => ['📌 Auto-scheduled', '🛍️ Product pinned', '📈 Store visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Shopify Product Link, Get Pins in Seconds', 'text' => 'Try it on any product page from your store — we pull the product photos, write the pin copy and design the pins for you.', 'placeholder' => 'https://yourstore.com/products/your-product'],
    'marquee' => ['Shopify product pins', 'Collection pins', 'New arrival pins', 'Holiday gift guides', 'Best sellers', 'Bundles & sets', 'Restock alerts', 'Seasonal drops', 'Brand story pins', 'Sale announcements'],
    'results' => [
        'title' => 'See the Results: Pins That Keep Selling',
        'text' => 'A Pinterest pin keeps getting saved and clicked for months — so every product you pin keeps sending shoppers to your Shopify store long after you schedule it.',
        'stats' => [[5, 'x', 'more traffic, up to'], [47, '%', 'higher engagement after pruning weak pins'], [70, '', 'pin templates to match your brand'], [1, '-click', 'scheduling for your whole catalog']],
        'alt' => 'Pinterest traffic growth for a Shopify store over 12 months',
    ],
    'steps' => [
        'title' => 'From Shopify Store to', 'title_accent' => 'Scheduled Pins in Minutes',
        'text' => 'No design skills, no Pinterest expertise. Scan your store, choose a look, set your pace — AI designs every pin, writes the copy, picks the board and publishes it all automatically.',
        'items' => [
            ['icon' => '🛍️', 'label' => 'Setup', 'title' => 'Scan Your Shopify Store', 'alt' => 'Scanning a Shopify store sitemap to import product pages', 'points' => ['Paste your store link — we read your Shopify sitemap and list every product and collection page.', 'Search and select the products you want, or select them all in one click.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Match Pins to Your Brand', 'alt' => 'Choosing pin templates, colour palettes and fonts for Shopify product pins', 'points' => ['70 templates, 56 colour palettes and 130+ Google fonts — with a live preview on your own product photos.', 'Import your own Canva design as SVG, or let AI pick the best template for each product.', 'Single photo, collage, or a mix of both — tiny and banner images are skipped automatically.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Set a Safe Publishing Pace', 'alt' => 'Pin publishing pace settings for a Shopify store', 'points' => ['Pick pins per day — the gap between pins is set automatically.', 'New Pinterest account? The warm-up mode grows from 1 pin a day to 20 over five months.', 'Choose pins per product and how long to wait before a product is pinned again.']],
            ['icon' => '🚀', 'label' => 'Review', 'title' => 'Review, Approve, Done', 'alt' => 'Reviewing AI-generated Shopify product pins before scheduling', 'points' => ['Edit any pin’s text, photos or template before it goes out — or remove it.', 'Click approve and your whole catalog is scheduled. Anything you don’t approve stays in Drafts.']],
        ],
    ],
    'features' => [
        'eyebrow' => 'WHY SHOPIFY SELLERS USE ' . strtoupper($app),
        'title' => 'Everything a Shopify Store Needs to Win on Pinterest',
        'text' => 'Pinterest users come to plan purchases. These features make sure your products are there when they do.',
        'items' => [
            ['🧾', 'Product-aware pin copy', 'AI reads each product page and writes a title, description, alt text and keywords that match what shoppers search for.'],
            ['🖼️', 'Uses your real product photos', 'We pull the featured image and gallery photos from each product page — no stock images.'],
            ['🗂️', 'Smart board matching', 'Products go to the board that fits them best. No fitting board? AI can create one.'],
            ['🔁', 'Multiple pins per product', 'Each product gets several different pins — new angle, new headline, new template — spaced weeks apart.'],
            ['🏷️', 'Collection & sale pages', 'Pin collection pages, gift guides and sale pages as easily as single products.'],
            ['👥', 'Team access', 'Give staff or your agency access through Team Management instead of sharing your login.'],
        ],
    ],
    'playbook' => [
        'title' => 'A Simple Pinterest Strategy for Shopify Stores',
        'text' => 'You don’t need to post all day. You need steady, relevant pins that lead to product pages.',
        'tips' => [
            ['Pin products, not just your homepage', 'Every product and collection page is a separate entry point. The more pages you pin, the more searches you show up in.'],
            ['Use vertical 2:3 images', 'Tall pins take up more of the feed. 1000 × 1500 is the safe default for product pins.'],
            ['Write for search, not for slogans', 'Pinterest works like a search engine. “Linen summer dress with pockets” beats “Our new favourite”.'],
            ['Re-pin with fresh designs', 'Give each product a new pin every few weeks with a different photo and headline instead of repeating the same image.'],
            ['Plan seasonal products early', 'Pinterest users plan ahead — pin holiday and seasonal products 30–60 days before the season.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Pinning vs Automated Shopify Pins',
        'rows' => [
            ['Designing pins', 'Open Canva for every product', '70 templates applied automatically'],
            ['Writing titles & descriptions', 'Written one by one', 'AI writes keyword-rich copy per pin'],
            ['Choosing boards', 'Pick manually each time', 'AI picks or creates the right board'],
            ['Posting schedule', 'Remember to post daily', 'Set once, publishes on autopilot'],
            ['New products', 'Often forgotten', 'Pinned on your next run in minutes'],
        ],
    ],
    'analytics' => [
        'title' => 'Analytics That Show Which Products Sell on Pinterest',
        'text' => 'See exactly which product pins send shoppers to your store, so you can double down on what sells.',
        'cards' => ['Understand which product pins send Shopify traffic and make data-driven decisions.', 'Delete underperforming product pins to raise your overall engagement rate.', 'See which product pins drive the most clicks, saves and store visits.', 'Filter and compare product pin performance across every angle that matters.'],
        'alts' => ['Pinterest analytics dashboard showing Shopify store traffic growth', 'Deleting underperforming Shopify product pins', 'Top pin performance for a Shopify catalog', 'Pin analytics by board, URL, keyword, title and time'],
    ],
    'pricing_title' => 'Pricing for Shopify Stores of Every Size',
    'testimonials_title' => 'What Our Users Say',
    'who' => [
        'title' => 'Built for every kind of', 'accent' => 'Shopify seller',
        'text' => "From a single-product store to a multi-brand catalog — $app keeps your Pinterest marketing running.",
        'cards' => [
            ['New Shopify stores', 'Get on Pinterest from day one — every product you list becomes ongoing search traffic without touching a design tool.'],
            ['Growing DTC brands', 'Keep pace with a fast catalog — new arrivals, restocks and seasonal collections pinned on schedule.'],
            ['Agencies with Shopify clients', 'Run Pinterest for every Shopify store you manage from one dashboard, with Team Management instead of shared logins.'],
        ],
    ],
    'cta_red' => ['Make Pinterest the easy part of Shopify.', 'Auto-pin your products and bring buyers straight to your store.'],
    'faq_title' => 'Shopify + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Does this work with any Shopify plan or theme?', 'Yes. We read your public product pages and your store sitemap, so it works with any Shopify plan and theme — no app install, no theme edits.'],
        ['How do new products get pinned?', 'Run the Classic Wizard again and select the new products — or use Auto website-to-daily-pin on plans that include it to keep pinning new pages automatically.'],
        ['Does it use my product photos and descriptions?', 'Yes. Pins are made from the photos on each product page, and AI writes fresh pin titles, descriptions, alt text and keywords from the page content. You can edit anything before approving.'],
        ['Can I choose which products get pinned?', 'Yes. After scanning your store you can search, select single products or collections, or select everything.'],
        ['Will it slow down my store?', 'No. Nothing is installed on your storefront. We only read your public pages when you create pins.'],
        ['Do I need a Pinterest Business account?', 'A Business account is recommended because it unlocks Pinterest analytics, but you connect through Pinterest’s official login either way.'],
        ['Can pins match my store branding?', 'Yes — choose from 56 colour palettes or set your own brand colours, pick fonts, choose templates, or import your own design from Canva.'],
        ['What if a product sells out?', 'You stay in control of every scheduled pin and can remove pins for products that are no longer available.'],
        ['Is there a free way to try it?', 'Yes. Use the free Pin Maker on this page with no account, or create a free account to scan your store and schedule pins.'],
    ],
    'cta_dark' => ['Ready to put your Shopify store’s Pinterest on autopilot?', 'Free to start. Pay only when you want more pins live on Pinterest.', 'Start Pinning My Store →'],
];
uc_render_page($pdo, $user, $uc);
