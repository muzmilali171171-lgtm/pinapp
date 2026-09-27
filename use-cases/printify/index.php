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
    'slug' => 'printify',
    'power_noun' => 'products',
    'autoblog_niche' => 'your print-on-demand niche',
    'name' => 'Printify',
    'accent' => '#39b75d',
    'order' => ['hero', 'start', 'marquee', 'steps', 'results_graph', 'features', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Printify Pinterest Automation for POD Stores | $app",
        'description' => "Promote Printify print-on-demand products on Pinterest. Pin products from your Shopify, WooCommerce, Etsy or Pop-Up store on autopilot.",
        'keywords' => 'Printify Pinterest, Pinterest for Printify, print on demand Pinterest marketing, promote POD products Pinterest, Printify store traffic, POD Pinterest automation, Printify Shopify Pinterest',
    ],
    'hero' => [
        'badge' => '👕 For Printify Print-on-Demand Sellers',
        'h1' => 'Pinterest Automation for Printify Stores',
        'h1_accent' => 'Mockups In, Traffic Out',
        'sub' => 'You design it, Printify prints it — we make sure people see it. Pin every print-on-demand product from the store Printify publishes to, and keep a steady flow of Pinterest shoppers coming.',
        'bullets' => [
            ['🔗', 'Works With the Store Printify Publishes To'],
            ['🖼️', 'Pins Made From Your Product Mockups'],
            ['🤖', 'AI Writes Titles & Descriptions for Every Product'],
            ['📅', 'Schedule Your Whole POD Catalog at Once'],
            ['🧪', 'Test Designs to Find Winners Faster'],
        ],
        'cta' => 'Start Pinning My POD Store — Free',
        'chips' => ['👕 Mockup pinned', '📌 Scheduled', '🛒 Order placed'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Product Link From Your POD Store', 'text' => 'We use your product mockups to design pins in seconds.', 'placeholder' => 'https://yourstore.com/products/your-tee'],
    'marquee' => ['T-shirts', 'Hoodies', 'Mugs', 'Tote bags', 'Posters', 'Phone cases', 'Pillows', 'Blankets', 'Stickers', 'Hats'],
    'steps' => [
        'title' => 'From Printify Product to', 'title_accent' => 'Pinterest Pin',
        'text' => 'Printify publishes your products to your store. We pin them from there.',
        'items' => [
            ['icon' => '🔗', 'label' => 'Setup', 'title' => 'Scan the Store Printify Publishes To', 'alt' => 'Scanning a print-on-demand store connected to Printify', 'points' => ['Shopify, WooCommerce, Etsy or your Printify Pop-Up Store — paste the store link.', 'Select products in bulk, including brand-new designs.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Turn Mockups Into Pins', 'alt' => 'Turning product mockups into pin designs', 'points' => ['Collages show one design on several products.', 'Brand colours, unlimited fonts and your own templates from the free editor.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Pace Your Launches', 'alt' => 'Scheduling print-on-demand product pins', 'points' => ['Pins per day, per-product gap and warm-up mode for new accounts.', 'AI places each product on the right board.']],
            ['icon' => '📈', 'label' => 'Approve', 'title' => 'Approve and Watch the Data', 'alt' => 'Approving POD product pins', 'points' => ['Approve the batch and it publishes on schedule.', 'Use analytics to spot winning designs.']],
        ],
    ],
    'results' => [
        'title' => 'See the Results: Find Your Winning Designs',
        'text' => 'Print-on-demand is a numbers game. Pinning every product consistently shows you which designs people actually want.',
        'stats' => [[5, 'x', 'more traffic, up to'], [47, '%', 'higher engagement after pruning'], [500, '+', 'premium pin templates'], [4, '', 'store platforms supported']],
        'alt' => 'Pinterest traffic growth for a print-on-demand store',
    ],
    'features' => [
        'eyebrow' => 'BUILT FOR POD',
        'title' => 'Why Printify Sellers Automate Pinterest',
        'text' => 'Low-cost products, many designs, thin margins — you need free traffic that scales.',
        'items' => [
            ['🧪', 'Design testing at scale', 'Pin every new design and let the clicks tell you which ones to push.'],
            ['🖼️', 'Mockup-friendly templates', 'Clean layouts that show off lifestyle and flat-lay mockups.'],
            ['🏪', 'Any storefront', 'Works with the store your Printify products live in — no separate integration.'],
            ['🔤', 'Niche-specific copy', 'AI writes titles like “Funny Teacher Shirt for Back to School” from each product page.'],
            ['🎯', 'Board placement', 'Products go to niche boards — teachers, nurses, dog moms — chosen or created by AI.'],
            ['💸', 'Free traffic, better margins', 'Every sale from an organic pin is a sale without ad spend.'],
        ],
    ],
    'playbook' => [
        'title' => 'A Pinterest Playbook for Print-on-Demand',
        'text' => 'How successful POD sellers use Pinterest to validate designs and grow sales.',
        'tips' => [
            ['Go niche, then go deeper', '“Gifts for nurses” is crowded; “funny night shift nurse mug” finds buyers.'],
            ['Use lifestyle mockups', 'Pins that show the product worn or in a room get more saves than flat product images.'],
            ['Pin every new design', 'Let Pinterest data show which designs deserve more mockups and variants.'],
            ['Time seasonal designs', 'Halloween, Christmas and back-to-school designs should be pinned 45–60 days early.'],
            ['Double down on winners', 'Give your best designs more pins with fresh templates every few weeks.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual POD Promotion vs Automation',
        'rows' => [
            ['Promoting new designs', 'Only when you remember', 'Every design, every run'],
            ['Pin creation', 'Edit each mockup', 'Templates applied automatically'],
            ['Copywriting', 'Rewrite for each product', 'AI writes niche copy'],
            ['Finding winners', 'Guess', 'Pin analytics show you'],
        ],
    ],
    'analytics' => [
        'title' => 'Spot Winning POD Designs Early',
        'text' => 'See which products and designs Pinterest users click, then make more of them.',
        'cards' => ['Track clicks for every product pin.', 'Remove weak pins to keep engagement high.', 'See your top designs at a glance.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for a Printify store', 'Removing weak POD pins', 'Top print-on-demand pins', 'POD pin analytics breakdown'],
    ],
    'pricing_title' => 'Plans for Print-on-Demand Stores',
    'who' => [
        'title' => 'Made for', 'accent' => 'print-on-demand sellers',
        'text' => 'Whether you’re testing your first 20 designs or running a POD brand.',
        'cards' => [
            ['New POD sellers', 'Get free traffic from day one while you learn which designs sell.'],
            ['Multi-store sellers', 'Run Pinterest for your Shopify, Etsy and WooCommerce POD stores from one dashboard.'],
            ['POD brands', 'Keep hundreds of products visible on Pinterest without a content team.'],
        ],
    ],
    'cta_red' => ['More eyes on every design you publish.', 'Pin your Printify products automatically.'],
    'faq_title' => 'Printify + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Does this connect directly to Printify?', 'No connection is needed. Printify publishes your products to a store — Shopify, WooCommerce, Etsy or a Printify Pop-Up Store — and we make pins from that store’s product pages.'],
        ['Which stores work best?', 'Shopify and WooCommerce stores work best because their sitemaps list every product. Etsy and Pop-Up Store product links can be pasted individually.'],
        ['Will my mockups be used in the pins?', 'Yes. Pins use the images on each product page — your mockups — and skip images that are too small or too wide.'],
        ['Can I pin new designs automatically?', 'Run the wizard again for new products, or use Auto website-to-daily-pin on plans that include it.'],
        ['Is it allowed to promote POD products on Pinterest?', 'Yes, as long as you own the rights to your designs and follow Pinterest’s community guidelines.'],
        ['How many pins per product?', 'Up to 10, each with a different template and headline, spaced out by the gap you choose.'],
        ['Is there a free plan?', 'Yes — try the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Turn your Printify catalog into Pinterest traffic.', 'Free to start. Scale when you find your winners.', 'Pin My POD Store →'],
];
uc_render_page($pdo, $user, $uc);
