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
    'slug' => 'etsy',
    'power_noun' => 'listings',
    'autoblog_niche' => 'your Etsy niche',
    'name' => 'Etsy',
    'accent' => '#f1641e',
    'order' => ['hero', 'start', 'marquee', 'results_graph', 'features', 'steps', 'playbook', 'analytics', 'compare', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Etsy Pinterest Automation: Pin Your Listings | $app",
        'description' => "Get more Etsy sales from Pinterest. Turn listings into AI-designed pins with search-friendly titles and schedule months of pins in minutes.",
        'keywords' => 'Pinterest for Etsy, Etsy Pinterest marketing, pin Etsy listings, Etsy Pinterest scheduler, Etsy shop traffic from Pinterest, Etsy seller Pinterest strategy, handmade shop Pinterest pins',
    ],
    'hero' => [
        'badge' => '🧵 For Etsy Sellers & Makers',
        'h1' => 'Pinterest Automation for Etsy Shops',
        'h1_accent' => 'More Eyes on Your Listings',
        'sub' => 'Pinterest is where people plan gifts, weddings, home makeovers and parties — exactly what Etsy sells. Turn your listings into beautiful pins and keep new buyers finding your shop every single day.',
        'bullets' => [
            ['🧶', 'Pins Made From Your Own Listing Photos'],
            ['✍️', 'AI Writes Titles & Descriptions Around Buyer Searches'],
            ['🎁', 'Templates Made for Gifts, Handmade & Vintage'],
            ['📅', 'Schedule Months of Pins While You Make'],
            ['🗂️', 'Boards Chosen or Created by AI'],
        ],
        'cta' => 'Start Pinning My Etsy Shop — Free',
        'chips' => ['🧵 Listing pinned', '🎁 Saved as gift idea', '🛒 New shop visit'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Listing or Shop Page Link', 'text' => 'We turn your listing photos into ready-to-post pins with gift-friendly headlines.', 'placeholder' => 'https://www.etsy.com/listing/... or https://yourshop.com/product'],
    'marquee' => ['Handmade gifts', 'Personalized jewelry', 'Wedding decor', 'Printable wall art', 'Digital planners', 'Vintage finds', 'Party supplies', 'Custom portraits', 'Crochet patterns', 'Invitation templates'],
    'results' => [
        'title' => 'See the Results: Your Shop, Found Every Day',
        'text' => 'Etsy search shows you for a few days. A pin can keep sending buyers for months — especially for gifts and seasonal items people save for later.',
        'stats' => [[70, '', 'pin templates'], [130, '+', 'fonts, script to bold'], [56, '', 'colour palettes'], [5, 'x', 'more traffic, up to']],
        'alt' => 'Pinterest traffic growth for an Etsy shop',
    ],
    'features' => [
        'eyebrow' => 'MADE FOR MAKERS',
        'title' => 'Why Etsy Sellers Love Pinterest Automation',
        'text' => 'You make the products. We keep them in front of the people planning to buy them.',
        'items' => [
            ['🖼️', 'Your photos, beautifully framed', 'Soft, script-font and collage templates that suit handmade and vintage products.'],
            ['🔤', 'Search-first pin copy', 'AI writes titles like “Personalized Birth Flower Necklace for Mom” — the way buyers search.'],
            ['🎄', 'Seasonal planning', 'Schedule holiday, wedding and Mother’s Day listings weeks ahead, when Pinterest users start planning.'],
            ['📄', 'Great for digital products', 'Printables, planners and templates look great with collage and number templates.'],
            ['🔁', 'Fresh pins, same listing', 'Each listing gets several pin designs over time, so your best sellers never go stale.'],
            ['⏱️', 'Hours back every week', 'No more designing pins at midnight. Set it once, get back to making.'],
        ],
    ],
    'steps' => [
        'title' => 'From Etsy Listing to', 'title_accent' => 'Scheduled Pin',
        'text' => 'Add your listing or shop pages, pick a look, set your pace — and get back to your craft.',
        'items' => [
            ['icon' => '🧵', 'label' => 'Setup', 'title' => 'Add Your Listings', 'alt' => 'Adding Etsy listing pages to create pins', 'points' => ['Paste listing or shop links — or your own website if you sell there too.', 'Select the listings you want to promote first, like best sellers and seasonal items.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Make It Look Like Your Shop', 'alt' => 'Choosing pin templates and fonts for Etsy listings', 'points' => ['Pick templates, colours and fonts that match your shop branding.', 'Import a pin design you made in Canva and reuse it for every listing.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Pin at a Natural Pace', 'alt' => 'Pinterest scheduling pace for an Etsy seller', 'points' => ['Start slow on a new account and build up month by month.', 'Several pins per listing, spaced out so your feed stays fresh.']],
            ['icon' => '🎉', 'label' => 'Approve', 'title' => 'Approve and Get Back to Making', 'alt' => 'Approving Etsy listing pins', 'points' => ['Check the pins, tweak anything you like, approve.', 'Pins publish on schedule while you work on orders.']],
        ],
    ],
    'playbook' => [
        'title' => 'Pinterest Tips Every Etsy Seller Should Know',
        'text' => 'Small shops win on Pinterest by being consistent and specific.',
        'tips' => [
            ['Describe the product, not the vibe', '“Handmade ceramic mug with speckled glaze” gets found. “My favourite piece” doesn’t.'],
            ['Show the product in use', 'Lifestyle photos — the mug on a desk, the necklace being worn — get saved more than plain white-background shots.'],
            ['Plan gifts 6–8 weeks ahead', 'Christmas, Valentine’s and Mother’s Day searches start early on Pinterest.'],
            ['Create boards by occasion and recipient', 'Boards like “Gifts for Grandma” or “Boho Wedding Decor” match how people browse.'],
            ['Keep pinning your best sellers', 'New pin designs for proven listings are the easiest win on Pinterest.'],
        ],
    ],
    'analytics' => [
        'title' => 'See Which Listings Pinterest Shoppers Want',
        'text' => 'Find the products that get clicks and saves, then make more of what sells.',
        'cards' => ['Track clicks and saves for every listing pin.', 'Remove pins that don’t perform to keep your account healthy.', 'Spot your top listings on Pinterest instantly.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for an Etsy shop', 'Removing low-performing Etsy pins', 'Top Etsy listing pins', 'Etsy pin analytics breakdown'],
    ],
    'compare' => [
        'title' => 'Pinning Etsy Listings by Hand vs Automatically',
        'rows' => [
            ['Designing pins', 'Canva, one listing at a time', 'Templates applied to every listing'],
            ['Pin titles & descriptions', 'Rewrite for every pin', 'AI writes search-friendly copy'],
            ['Seasonal timing', 'Easy to miss', 'Scheduled weeks ahead'],
            ['Consistency', 'Stops when you get busy', 'Keeps publishing daily'],
        ],
    ],
    'pricing_title' => 'Plans for Etsy Shops',
    'who' => [
        'title' => 'Made for', 'accent' => 'Etsy sellers',
        'text' => 'Handmade, vintage or digital — if it’s on Etsy, it belongs on Pinterest.',
        'cards' => [
            ['Handmade makers', 'Spend your time making, not designing pins. Your listings get promoted while you work.'],
            ['Digital product sellers', 'Printables, planners and templates are some of the most-saved items on Pinterest.'],
            ['Growing Etsy brands', 'Keep hundreds of listings visible on Pinterest without hiring a social media manager.'],
        ],
    ],
    'cta_red' => ['Let Pinterest send buyers to your Etsy shop.', 'Your listings, pinned beautifully and consistently.'],
    'faq_title' => 'Etsy + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Can I pin my Etsy listings directly?', 'Yes, paste your listing links. Etsy sometimes limits automated page reading; if a listing can’t be read it shows as skipped, and you can pin the same product from your own website or blog instead.'],
        ['Will pins link back to my Etsy listing?', 'Yes. Each pin links to the page it was made from, so a listing pin sends people straight to that listing.'],
        ['Do I need an Etsy API connection?', 'No. There is nothing to connect on Etsy — only your Pinterest account.'],
        ['What about digital downloads and printables?', 'They work great. Collage and number templates are ideal for showing several pages of a printable or planner.'],
        ['Can I use my own pin design from Canva?', 'Yes. Export your design from Canva as SVG and import it — headlines and photos are filled in for every listing.'],
        ['How many pins should I post per day?', 'New accounts should start slowly — the warm-up mode begins at 1 pin a day and grows to 20 by month five.'],
        ['Does AI write in my shop’s style?', 'AI writes from each listing’s content, and you can edit any title or description before approving.'],
        ['Is it free to try?', 'Yes. Use the free Pin Maker above, or create a free account to schedule pins.'],
    ],
    'cta_dark' => ['Ready to grow your Etsy shop with Pinterest?', 'Pin your listings once — keep getting found for months.', 'Pin My Etsy Shop →'],
];
uc_render_page($pdo, $user, $uc);
