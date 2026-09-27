<?php
/**
 * Use-case landing pages (/use-cases/{slug}/).
 *
 * Every page shares the same sections, animations and styles (assets/css/use-case.css,
 * assets/js/use-case.js); each page only supplies its own content array to uc_render_page().
 * Section order can differ per page via $uc['order'].
 */

require_once __DIR__ . '/pricing_functions.php';

/** Use-case groups, in display order. */
function uc_groups(): array
{
    return [
        'platforms' => 'Store & Website Platforms',
        'marketplaces' => 'Marketplaces & Print on Demand',
        'blogs' => 'Blogs & Content Niches',
        'shops' => 'Product Shops & Brands',
        'services' => 'Services & Agencies',
    ];
}

/**
 * Every use case: slug => [icon, title, summary, group, full].
 * full = true: hand-written page. full = false: page built by uc_generic_config() until its
 * own content is written (those pages are noindex until then).
 */
function uc_catalog(): array
{
    static $all = null;
    if ($all !== null) return $all;
    $rows = [
        // ---- hand-written pages ----
        ['shopify', '🛍️', 'Shopify Stores', 'Turn every product page into scheduled pins and send shoppers straight to your Shopify store.', 'platforms', true],
        ['woocommerce', '🛒', 'WooCommerce Stores', 'Auto-pin your WooCommerce products and categories for free, steady store traffic.', 'platforms', true],
        ['wordpress', '📝', 'WordPress Blogs', 'Pin every post on your WordPress site and keep old content earning traffic.', 'platforms', true],
        ['amazon', '📦', 'Amazon Sellers & Affiliates', 'Pin your product roundups, reviews and brand pages to drive buyers toward Amazon.', 'marketplaces', true],
        ['etsy', '🧵', 'Etsy Shops', 'Schedule pins for your Etsy listings so new buyers find your shop every day.', 'marketplaces', true],
        ['redbubble', '🎨', 'Redbubble Artists', 'Turn your artwork and product pages into pins that keep selling your designs.', 'marketplaces', true],
        ['printify', '👕', 'Printify Print-on-Demand', 'Pin every print-on-demand product from the store Printify publishes to.', 'marketplaces', true],
        ['food-website', '🍝', 'Food & Recipe Websites', 'Recipe pins that look like the ones people save — made for every recipe you publish.', 'blogs', true],
        ['home-decor', '🛋️', 'Home Decor Websites', 'Room ideas, decor finds and DIY projects, turned into scroll-stopping pins.', 'blogs', true],
        // ---- platforms ----
        ['wix-website', '🌐', 'Wix Websites', 'Pin the pages, posts and products on your Wix site automatically.', 'platforms', true],
        ['webflow-website', '🧱', 'Webflow Websites', 'Turn Webflow CMS pages and blog posts into scheduled Pinterest pins.', 'platforms', true],
        ['squarespace-commerce', '◼️', 'Squarespace Commerce', 'Auto-pin Squarespace products and blog posts for steady store traffic.', 'platforms', true],
        ['big-cartel', '🛒', 'Big Cartel Shops', 'Give every product in your Big Cartel shop its own Pinterest pins.', 'platforms', true],
        ['ghost-blog', '👻', 'Ghost Blogs', 'Pin every Ghost post and newsletter issue to grow readers and members.', 'platforms', true],
        ['medium-blog', '✒️', 'Medium Writers', 'Promote your Medium stories on Pinterest and reach new readers.', 'platforms', true],
        ['gumroad', '💾', 'Gumroad Creators', 'Pin your Gumroad products and digital downloads to find new buyers.', 'platforms', true],
        ['ecommerce-store', '🏬', 'E-commerce Stores', 'Pinterest automation for any online store, on any platform.', 'platforms', true],
        ['dropshipping-store', '🚚', 'Dropshipping Stores', 'Test products and drive free traffic with automatic product pins.', 'platforms', true],
        // ---- marketplaces & POD ----
        ['print-on-demand', '🖨️', 'Print-on-Demand Sellers', 'Pin every POD design across tees, mugs, posters and more.', 'marketplaces', true],
        ['printful', '👚', 'Printful Stores', 'Pin the Printful products in your connected store automatically.', 'marketplaces', true],
        ['teespring', '👕', 'Spring (Teespring) Creators', 'Promote your Spring merch and designs with scheduled pins.', 'marketplaces', true],
        ['teepublic', '🎽', 'TeePublic Artists', 'Get your TeePublic designs discovered on Pinterest.', 'marketplaces', true],
        ['society6', '🖼️', 'Society6 Artists', 'Turn your Society6 art and products into pins that sell.', 'marketplaces', true],
        ['spoonflower', '🧶', 'Spoonflower Designers', 'Pin your fabric, wallpaper and home decor designs.', 'marketplaces', true],
        ['zazzle', '🎁', 'Zazzle Designers', 'Promote your Zazzle products and custom designs on Pinterest.', 'marketplaces', true],
        ['creative-market', '🎨', 'Creative Market Shops', 'Pin your fonts, templates and graphics to reach more designers.', 'marketplaces', true],
        // ---- blogs & content niches ----
        ['recipe-blog', '🥘', 'Recipe Blogs', 'Recipe pins for every dish you publish, scheduled for the season.', 'blogs', true],
        ['drinks-website', '🍹', 'Drinks & Cocktail Websites', 'Cocktail, mocktail and coffee recipes turned into pins people save.', 'blogs', true],
        ['parenting-blog', '👨‍👩‍👧', 'Parenting Blogs', 'Pin parenting tips, activities and printables to reach more families.', 'blogs', true],
        ['mom-blog', '🤱', 'Mom Blogs', 'Turn mom-life tips, recipes and hacks into steady Pinterest traffic.', 'blogs', true],
        ['travel-website', '✈️', 'Travel Websites', 'Destination guides and travel tips pinned for trip planners.', 'blogs', true],
        ['travel-itineraries', '🗺️', 'Travel Itinerary Sites', 'Pin day-by-day itineraries to people planning their next trip.', 'blogs', true],
        ['nails-website', '💅', 'Nail Art Websites', 'Nail designs and manicure ideas pinned in bright, save-worthy styles.', 'blogs', true],
        ['hairstyles-website', '💇‍♀️', 'Hairstyle Websites', 'Haircut and hairstyle roundups turned into number-style pins.', 'blogs', true],
        ['beauty-and-skincare', '🧴', 'Beauty & Skincare', 'Pin skincare routines, makeup looks and product reviews.', 'blogs', true],
        ['diy-website', '🔨', 'DIY Websites', 'Step-by-step DIY projects pinned for makers and weekend builders.', 'blogs', true],
        ['craft-blog', '✂️', 'Craft Blogs', 'Craft tutorials and patterns turned into pins crafters save.', 'blogs', true],
        ['photography-blog', '📷', 'Photography Blogs', 'Show off your photos and tips to people who love great images.', 'blogs', true],
        ['pet-blog', '🐶', 'Pet Blogs', 'Pet care tips, recipes and product guides pinned for pet owners.', 'blogs', true],
        ['garden-blog', '🌱', 'Garden Blogs', 'Planting guides and garden ideas pinned before every season.', 'blogs', true],
        ['flowers-website', '💐', 'Flower Websites', 'Bouquets, arrangements and flower guides turned into lovely pins.', 'blogs', true],
        ['wedding-blog', '💍', 'Wedding Blogs', 'Wedding ideas and planning guides pinned for couples.', 'blogs', true],
        ['fitness-blog', '🏋️', 'Fitness Blogs', 'Workouts, plans and fitness tips pinned for motivated readers.', 'blogs', true],
        ['book-blog', '📚', 'Book Blogs', 'Reading lists and book reviews pinned for readers.', 'blogs', true],
        ['budget-lifestyle', '💰', 'Budget & Frugal Living', 'Money-saving tips and budget guides pinned for savers.', 'blogs', true],
        ['home-organization', '🧺', 'Home Organization', 'Organizing ideas and before-and-afters pinned for tidy homes.', 'blogs', true],
        ['home-organization-membership', '🗂️', 'Home Organization Memberships', 'Grow your membership with pins that lead to your free content.', 'blogs', true],
        ['affiliate-marketing-blog', '🔗', 'Affiliate Marketing Blogs', 'Pin reviews and roundups that send readers to your affiliate links.', 'blogs', true],
        ['infographic-blog', '📊', 'Infographic Blogs', 'Tall infographic pins that get saved and shared.', 'blogs', true],
        ['quote-graphics', '💬', 'Quote Graphics Sites', 'Quotes and sayings turned into shareable text pins.', 'blogs', true],
        // ---- product shops ----
        ['fashion-boutique', '👗', 'Fashion Boutiques', 'Outfit pins for every piece in your boutique.', 'shops', true],
        ['sustainable-fashion', '🌿', 'Sustainable Fashion Brands', 'Pin your eco-friendly collections for conscious shoppers.', 'shops', true],
        ['vintage-shop', '🕰️', 'Vintage Shops', 'Give one-of-a-kind vintage finds their own pins.', 'shops', true],
        ['sustainable-products', '♻️', 'Sustainable Product Shops', 'Reach eco-minded buyers with automatic product pins.', 'shops', true],
        ['jewelry-store', '💎', 'Jewelry Stores', 'Elegant pins for rings, necklaces and gift sets.', 'shops', true],
        ['kids-products', '🧸', 'Kids Product Shops', 'Pin toys, clothes and nursery items for parents.', 'shops', true],
        ['baby-gear-rental', '🍼', 'Baby Gear Rental', 'Reach traveling parents with pins for your rental gear.', 'shops', true],
        ['beauty-subscription-box', '📦', 'Beauty Subscription Boxes', 'Pin each month’s box and grow subscribers.', 'shops', true],
        ['woodworking-shop', '🪵', 'Woodworking Shops', 'Showcase handmade woodwork in warm, crafted pins.', 'shops', true],
        ['digital-planners', '🗓️', 'Digital Planner Shops', 'Pin planner pages and templates for organized buyers.', 'shops', true],
        ['printables-shop', '🖨️', 'Printables Shops', 'Printable pins for planners, wall art and worksheets.', 'shops', true],
        // ---- services & agencies ----
        ['marketing-agency', '📣', 'Marketing Agencies', 'Run Pinterest for every client from one dashboard.', 'services', true],
        ['seo-agency', '🔍', 'Search Marketing Agencies', 'Add Pinterest traffic to your client growth packages.', 'services', true],
        ['coaching', '🎯', 'Coaches', 'Pin your posts, freebies and programs to find new clients.', 'services', true],
        ['salon-spa', '💆', 'Salons & Spas', 'Pin looks, treatments and offers for local clients.', 'services', true],
        ['wedding-florist', '🌸', 'Wedding Florists', 'Bridal bouquets and wedding flowers pinned for engaged couples.', 'services', true],
        ['event-planning', '🎉', 'Event Planners', 'Pin party themes and event ideas that bring inquiries.', 'services', true],
        ['interior-design', '🏠', 'Interior Designers', 'Portfolio rooms pinned for people planning their homes.', 'services', true],
        ['real-estate', '🏡', 'Real Estate Agents', 'Listings, neighborhood guides and home tips pinned for buyers.', 'services', true],
    ];
    $all = [];
    foreach ($rows as $r) $all[$r[0]] = ['icon' => $r[1], 'title' => $r[2], 'summary' => $r[3], 'group' => $r[4], 'full' => $r[5]];
    return $all;
}

/** Free tools for internal links: slug => [icon, name, one-liner]. */
function uc_free_tools(): array
{
    return [
        'pinterest-pin-maker' => ['📌', 'Free Pinterest Pin Maker', 'Pins from any page with 70 templates — no sign-up.'],
        'ai-pinterest-pin-create' => ['✨', 'AI Pinterest Pin Creator', 'Design a single pin with AI from a title or link.'],
        'pinterest-title-description-generator' => ['📝', 'Pin Title & Description Generator', 'Keyword-rich pin titles and descriptions in seconds.'],
        'pinterest-keyword-research-tool' => ['🔎', 'Pinterest Keyword Research', 'Find the words people search on Pinterest.'],
        'pinterest-board-name-generator' => ['🗂️', 'Board Name Generator', 'Clear, searchable board names for your niche.'],
        'pinterest-alt-text-generator' => ['♿', 'Pin Alt Text Generator', 'Accessible alt text for every pin image.'],
        'pinterest-hashtag-generator' => ['#️⃣', 'Pinterest Hashtag Generator', 'Relevant hashtags for your pins.'],
        'pinterest-image-resizer' => ['📐', 'Pinterest Image Resizer', 'Resize any image to 2:3 and other pin sizes.'],
        'pinterest-pin-preview' => ['👀', 'Pin Preview Tool', 'See how your pin looks in the feed.'],
        'pinterest-color-palette-generator' => ['🎨', 'Colour Palette Generator', 'Brand colour palettes for your pins.'],
        'pinterest-font-generator' => ['🔤', 'Pinterest Font Generator', 'Stylish text for pins and profiles.'],
        'pinterest-bio-generator' => ['👤', 'Pinterest Bio Generator', 'A profile bio that explains what you pin.'],
        'pinterest-username-generator' => ['🏷️', 'Username Generator', 'Memorable Pinterest username ideas.'],
        'pinterest-character-counter' => ['🔢', 'Pin Character Counter', 'Keep titles and descriptions within limits.'],
        'ai-image-creater' => ['🖼️', 'AI Image Creator', 'Create images for pins and blog posts with AI.'],
        'etsy-title-description-generator' => ['🧾', 'Etsy Title & Description Generator', 'Listing copy that buyers search for.'],
        'etsy-tags-generator' => ['🏷️', 'Etsy Tags Generator', '13 tags for every Etsy listing.'],
        'etsy-keyword-tool' => ['🔍', 'Etsy Keyword Tool', 'Find buyer keywords for your listings.'],
        'etsy-fee-calculator' => ['🧮', 'Etsy Fee Calculator', 'See your real profit after Etsy fees.'],
        'etsy-shop-name-generator' => ['🏪', 'Etsy Shop Name Generator', 'Name ideas for your Etsy shop.'],
    ];
}

/** Which free tools to link from a page (Etsy-style shops also get the Etsy tools). */
function uc_tools_for(array $uc): array
{
    if (!empty($uc['tools'])) return $uc['tools'];
    $base = ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-image-resizer', 'pinterest-alt-text-generator', 'ai-image-creater'];
    $cat = uc_catalog()[$uc['slug'] ?? ''] ?? null;
    if (($uc['slug'] ?? '') === 'etsy' || ($cat && in_array($cat['group'], ['marketplaces', 'shops'], true))) {
        $base = ['pinterest-pin-maker', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'etsy-title-description-generator', 'etsy-tags-generator', 'etsy-keyword-tool', 'etsy-fee-calculator', 'pinterest-image-resizer'];
    }
    return $base;
}

/** Media shared by every use case (the site's own screenshots, videos and pins). */
function uc_media(): array
{
    $m = 'https://media.webtopin.com/cdn/uploads/';
    return [
        'hero_up' => [$m . '400d58cc5ce7ebdd29a047ef1a067f84.jpg', $m . 'de1a037f5ddbc8e09a5868cd18dcc017.jpg', $m . '7d6f0086f0b507f5a8774aa45f31cc5b.jpg', $m . '943bc5cba5e3f17c4f43cfc644c88fb8.jpg', $m . '41ebf7f73e2012fa9665bfefb6c8692f.webp', $m . '750ddf1e27ecf678e65d469193f11aab.jpg'],
        'hero_down' => [$m . '40823f2cbf9815b383521b191de0f617.jpg', $m . 'e6799acbbf40f64d64c615a870e4e5fe.jpg', $m . 'b86629b92576436c055ac8e255731601.jpg', $m . 'dca0ffa075f3350b9cda06ac7ea30e80.jpg', $m . 'e2ce204296c848c1ceff43350788abe5.jpg'],
        'steps' => [
            ['video', $m . '84ba7861b052a7343d6b85726607ef6b.mp4'],
            ['img', $m . '4d4de8cca5c28ab5a558b5c59e76f60e.png'],
            ['video', $m . '31dc461a452ed138d03398c26f504f14.mp4'],
            ['img', $m . '4e9b173701de9081c69da6ac1308a992.png'],
        ],
        'graph' => $m . 'd72cb96fc7491a58850b386689ee2c25.webp',
        'analytics' => [$m . 'c1b0f04f3c6bc98befbad834d372f223.webp', $m . '05e8efb18c2038f4f2d4196ff59fd45b.webp', $m . '6eac4dc69c7b24158bce5e50da1abccc.png', $m . 'ff77bc47f07ae1c08d16d68830c34241.png'],
        'results' => [$m . '4659437b03dec579da0aa8eccdfc492f.webp', $m . '141a3b8776146e196dc8e6b6b9501f3d.jpg', $m . 'f7aa8ce89a559b01079ae62a1094c260.jpg', $m . '1e7555dfe2c0f70a9c5068c13d570a6b.png', $m . 'a61952721844d42dc609ef70a5964344.jpg', $m . '387b5859b701d6b5f13ff8e07ac5aa7a.jpg'],
        'testimonials' => [
            ['img' => $m . '64fbbf3b1ad9e27e97a3326ff28cd134.jpg', 'name' => 'Sarah Mitchell', 'role' => 'Lifestyle Blogger', 'stars' => 5, 'quote' => 'Bulk Pin Scheduler alone saves me hours every week. I paste in a batch of blog links and it writes the titles, descriptions, and even picks the right board — I just review and hit schedule.'],
            ['img' => $m . 'a9143ea1b40f45ebca6ca0b4082fd84d.jpg', 'name' => 'Daniel Reyes', 'role' => 'Owner, Trail & Table', 'stars' => 5, 'quote' => "Connected my Pinterest account in under a minute and had pins scheduled for the whole month by lunchtime. Way less fiddly than anything else I've tried."],
            ['img' => $m . '0c4a6cb84b275adc08099da63d7de2cf.jpg', 'name' => 'Priya Kapoor', 'role' => 'Founder, The Home Edit Co.', 'stars' => 5, 'quote' => 'The AI pin designs actually look good out of the box. I barely touch the brand color setting and every pin already matches my site.'],
            ['img' => $m . 'a78180ea0be98a3f91c11e46dc184462.jpg', 'name' => 'Marcus Webb', 'role' => 'E-commerce Manager', 'stars' => 4, 'quote' => "Took a little while to get Auto Blog set up the way we wanted for our product pages, but once it clicked it's been running untouched for two months straight."],
            ['img' => $m . '18c7cc921b91b36f9055436aaa9dee71.jpg', 'name' => 'Emily Carter', 'role' => 'Content Creator', 'stars' => 5, 'quote' => 'Being able to hand my assistant a login through Team Management, without giving away my own account, was exactly what I needed.'],
            ['img' => $m . '790786a85eb7980953ef02c226eea0eb.jpg', 'name' => 'Ahmed Khan', 'role' => 'Affiliate Marketer', 'stars' => 5, 'quote' => 'Every new post I publish gets turned into pins automatically now. It\'s the closest thing to "set it and forget it" I\'ve found for Pinterest.'],
        ],
    ];
}

/** Signup link for visitors; the given dashboard tool for logged-in users. */
function uc_signup_or(?array $user, string $base, string $userPath): string
{
    return $base . ($user ? $userPath : 'auth/register');
}

function uc_cta(?array $user, string $base, string $label, string $class = 'btn-primary'): string
{
    $href = $user ? $base . 'user/classic-wizard' : $base . 'auth/register';
    $text = $user ? 'Open the Classic Wizard →' : $label;
    return '<a href="' . e($href) . '" class="' . e($class) . '">' . e($text) . '</a>';
}

/* ============================================================ */

function uc_render_page(PDO $pdo, ?array $user, array $uc): void
{
    $base = '../../';
    $v = fn($f) => @filemtime(__DIR__ . '/../' . $f) ?: time();
    $url = rtrim(APP_URL, '/') . '/use-cases/' . $uc['slug'] . '/';
    $order = $uc['order'] ?? ['hero', 'start', 'marquee', 'results_graph', 'steps', 'features', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'];
    // Core features on every page: power cards after the hero, Auto Blog after the steps,
    // free tools before the related links.
    $inject = function (array $o, string $after, string $name) {
        if (in_array($name, $o, true)) return $o;
        $i = array_search($after, $o, true);
        array_splice($o, $i === false ? count($o) : $i + 1, 0, [$name]);
        return $o;
    };
    $order = $inject($order, 'hero', 'power');
    $order = $inject($order, 'steps', 'autoblog');
    $order = $inject($order, 'faq', 'free_tools');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => $uc['meta']['title'],
    'description' => $uc['meta']['description'],
    'keywords' => $uc['meta']['keywords'],
    'canonical' => $url,
    'noindex' => !empty($uc['meta']['noindex']),
]); ?>
<link rel="preconnect" href="https://media.webtopin.com">
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css?v=<?= $v('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= $base ?>assets/css/use-case.css?v=<?= $v('assets/css/use-case.css') ?>">
<?php uc_render_schema($uc, $url); ?>
</head>
<body class="uc-page" style="--uc-accent: <?= e($uc['accent'] ?? '#12c464') ?>;">
<div class="uc-progress" aria-hidden="true"><span></span></div>

<?php render_site_header($pdo ?? null); ?>

<nav class="uc-breadcrumb" aria-label="Breadcrumb"><div class="container"><a href="<?= $base ?>">Home</a> / <a href="../">Use Cases</a> / <span><?= e($uc['name']) ?></span></div></nav>

<?php
    foreach ($order as $section) {
        $fn = 'uc_section_' . $section;
        if (function_exists($fn)) $fn($pdo, $user, $uc, $base);
    }
    render_site_footer($pdo);
    ?>
<script src="<?= $base ?>assets/js/use-case.js?v=<?= $v('assets/js/use-case.js') ?>" defer></script>
</body>
</html>
<?php
}

/** Breadcrumb + FAQ structured data (eligible for rich results in search). */
function uc_render_schema(array $uc, string $url): void
{
    $root = rtrim(APP_URL, '/');
    $graph = [
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $root . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Use Cases', 'item' => $root . '/use-cases/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $uc['name'], 'item' => $url],
            ],
        ],
        [
            '@type' => 'WebPage',
            'name' => $uc['meta']['title'],
            'description' => $uc['meta']['description'],
            'url' => $url,
        ],
    ];
    if (!empty($uc['faq'])) {
        $graph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($q) => [
                '@type' => 'Question', 'name' => $q[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]],
            ], $uc['faq']),
        ];
    }
    echo '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
}

/* ===================== Sections ===================== */

function uc_section_hero(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $h = $uc['hero'];
    $media = uc_media();
    ?>
<section class="uc-hero">
    <div class="uc-blob uc-blob-1" data-parallax="0.12" aria-hidden="true"></div>
    <div class="uc-blob uc-blob-2" data-parallax="-0.08" aria-hidden="true"></div>
    <div class="container uc-hero-grid">
        <div class="uc-hero-copy">
            <div class="uc-badge rg-border-glow rg-pop"><?= e($h['badge']) ?></div>
            <h1 class="uc-h1"><?= e($h['h1']) ?><br><span class="uc-grad-anim"><?= e($h['h1_accent']) ?></span></h1>
            <p class="uc-sub"><?= e($h['sub']) ?></p>
            <ul class="uc-feature-list">
                <?php foreach ($h['bullets'] as $i => $b): ?>
                    <li class="rg-reveal" style="--d: <?= $i * 80 ?>ms"><span class="uc-feature-ic"><?= $b[0] ?></span> <?= e($b[1]) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="uc-cta-row">
                <?= uc_cta($user, $base, $h['cta'], 'btn-primary rg-shine uc-btn-pulse') ?>
                <?php if (!$user): ?><a href="<?= $base ?>free-tools/pinterest-pin-maker/" class="btn-secondary uc-btn-outline">Try the free Pin Maker</a><?php endif; ?>
            </div>
            <div class="uc-core-pills">
                <span>⚡ Hundreds of pages → pins in 1 click</span>
                <span>✍️ AI Auto Blog + auto pins</span>
                <span>♾️ Unlimited AI pin designs</span>
            </div>
            <p class="uc-hero-note"><?= e($h['note'] ?? 'Free to start · No credit card needed · Official Pinterest API') ?></p>
        </div>
        <div class="uc-hero-visual" aria-hidden="true">
            <div class="uc-scroll-col uc-scroll-up"><div class="uc-scroll-track">
                <?php for ($i = 0; $i < 2; $i++) foreach ($media['hero_up'] as $k => $img): ?><img src="<?= e($img) ?>" alt="" width="220" height="330" decoding="async" <?= $i === 0 && $k < 3 ? ($k === 0 ? 'fetchpriority="high"' : '') : 'loading="lazy"' ?>><?php endforeach; ?>
            </div></div>
            <div class="uc-scroll-col uc-scroll-down"><div class="uc-scroll-track">
                <?php for ($i = 0; $i < 2; $i++) foreach ($media['hero_down'] as $k => $img): ?><img src="<?= e($img) ?>" alt="" width="220" height="330" decoding="async" <?= $i === 0 && $k < 3 ? '' : 'loading="lazy"' ?>><?php endforeach; ?>
            </div></div>
            <div class="uc-float-chip uc-float-chip-1"><?= e($h['chips'][0]) ?></div>
            <div class="uc-float-chip uc-float-chip-2"><?= e($h['chips'][1]) ?></div>
            <div class="uc-float-chip uc-float-chip-3"><?= e($h['chips'][2] ?? '📌 Auto-scheduled') ?></div>
        </div>
    </div>
</section>
<?php
}

/** Free Pin Maker box: paste a link, jump straight into the tool with it. */
function uc_section_start(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $s = $uc['start'];
    ?>
<section class="uc-start">
    <div class="container">
        <div class="uc-start-card rg-border-glow rg-reveal">
            <div class="uc-eyebrow rg-text-blink"><?= e($s['label']) ?></div>
            <h2><?= e($s['title']) ?></h2>
            <p class="uc-muted"><?= e($s['text']) ?></p>
            <form class="uc-start-form" action="<?= $base ?>free-tools/pinterest-pin-maker/" method="get">
                <label class="uc-sr" for="ucStartUrl">Page link</label>
                <input type="url" id="ucStartUrl" name="url" required placeholder="<?= e($s['placeholder']) ?>">
                <input type="hidden" name="auto" value="1">
                <button type="submit" class="btn-primary rg-shine">Create Pins Free →</button>
            </form>
            <div class="uc-start-meta">3 free generations · 70 templates · No sign-up needed</div>
        </div>
    </div>
</section>
<?php
}

function uc_section_marquee(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $words = $uc['marquee'] ?? [];
    if (!$words) return;
    ?>
<section class="uc-marquee" aria-label="Topics">
    <div class="uc-marquee-track">
        <?php for ($i = 0; $i < 2; $i++) foreach ($words as $w): ?><span><?= e($w) ?></span><?php endforeach; ?>
    </div>
</section>
<?php
}

/** "See the Results" — growth curve plus animated counters. */
function uc_section_results_graph(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $r = $uc['results'];
    ?>
<section class="uc-graph" id="results">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// SEE THE RESULTS</div>
            <h2><?= e($r['title']) ?></h2>
            <p class="uc-muted"><?= e($r['text']) ?></p>
        </div>
        <div class="uc-counters">
            <?php foreach ($r['stats'] as $i => $st): ?>
                <div class="uc-counter rg-reveal" style="--d: <?= $i * 90 ?>ms">
                    <b><span data-count="<?= (int)$st[0] ?>">0</span><?= e($st[1]) ?></b>
                    <span><?= e($st[2]) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="uc-graph-chart rg-border-glow rg-reveal">
            <img src="<?= e(uc_media()['graph']) ?>" alt="<?= e($r['alt']) ?>" loading="lazy">
            <p class="uc-graph-note">Illustrative growth curve — actual results vary by niche, content volume and consistency.</p>
        </div>
        <div class="uc-center">
            <div class="uc-graph-label rg-text-blink">Pin &amp; Grow Traffic</div>
            <?= uc_cta($user, $base, 'Start Free →', 'btn-primary rg-shine') ?>
        </div>
    </div>
</section>
<?php
}

function uc_section_steps(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $s = $uc['steps'];
    $media = uc_media()['steps'];
    ?>
<section class="uc-steps" id="how-it-works">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-pill-badge"><span class="uc-dot"></span> Simple 4-Step Process</div>
            <h2><?= e($s['title']) ?> <span class="uc-grad-text"><?= e($s['title_accent']) ?></span></h2>
            <p class="uc-muted"><?= e($s['text']) ?></p>
        </div>
        <div class="uc-steps-list">
            <?php foreach ($s['items'] as $i => $st): $rev = $i % 2 === 1; $m = $media[$i % 4]; ?>
            <div class="uc-step-row <?= $rev ? 'uc-step-rev' : '' ?>">
                <div class="uc-step-copy">
                    <div class="uc-step-icon <?= $rev ? 'uc-ic-green' : 'uc-ic-red' ?>"><?= $st['icon'] ?></div>
                    <div class="uc-step-num">Step <?= $i + 1 ?> · <?= e($st['label']) ?></div>
                    <h3><?= e($st['title']) ?></h3>
                    <ul class="uc-step-points">
                        <?php foreach ($st['points'] as $pt): ?><li><span class="uc-check <?= $rev ? 'uc-check-red' : 'uc-check-green' ?>">✓</span> <?= e($pt) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <div class="uc-step-media <?= $rev ? 'uc-glow-green' : 'uc-glow-red' ?>">
                    <?php if ($m[0] === 'video'): ?>
                        <video muted loop playsinline preload="none" data-lazy-video aria-label="<?= e($st['alt']) ?>"><source data-src="<?= e($m[1]) ?>" type="video/mp4"></video>
                    <?php else: ?>
                        <img src="<?= e($m[1]) ?>" alt="<?= e($st['alt']) ?>" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="uc-center uc-steps-cta">
            <?= uc_cta($user, $base, 'Start Scheduling Free →', 'btn-primary rg-shine uc-btn-pulse') ?>
            <a href="<?= $base ?>tutorials" class="btn-secondary uc-btn-outline">See It In Action</a>
        </div>
    </div>
</section>
<?php
}

function uc_section_features(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $f = $uc['features'];
    ?>
<section class="uc-features">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// <?= e($f['eyebrow']) ?></div>
            <h2><?= e($f['title']) ?></h2>
            <p class="uc-muted"><?= e($f['text']) ?></p>
        </div>
        <div class="uc-feature-grid">
            <?php foreach ($f['items'] as $i => $it): ?>
                <article class="uc-fcard rg-reveal" style="--d: <?= ($i % 3) * 90 ?>ms">
                    <div class="uc-fcard-ic"><?= $it[0] ?></div>
                    <h3><?= e($it[1]) ?></h3>
                    <p><?= e($it[2]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
}

/** Niche strategy tips — genuinely useful, keyword-rich copy for the page. */
function uc_section_playbook(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $p = $uc['playbook'] ?? null;
    if (!$p) return;
    ?>
<section class="uc-playbook">
    <div class="container uc-playbook-grid">
        <div class="uc-playbook-intro rg-reveal">
            <div class="uc-eyebrow">// PINTEREST PLAYBOOK</div>
            <h2><?= e($p['title']) ?></h2>
            <p class="uc-muted"><?= e($p['text']) ?></p>
            <?= uc_cta($user, $base, 'Put It on Autopilot →', 'btn-primary rg-shine') ?>
        </div>
        <ol class="uc-tips">
            <?php foreach ($p['tips'] as $i => $t): ?>
                <li class="rg-reveal" style="--d: <?= $i * 70 ?>ms"><span class="uc-tip-n"><?= $i + 1 ?></span><div><h3><?= e($t[0]) ?></h3><p><?= e($t[1]) ?></p></div></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php
}

function uc_section_compare(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $c = $uc['compare'] ?? null;
    if (!$c) return;
    ?>
<section class="uc-compare">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// MANUAL VS AUTOMATED</div>
            <h2><?= e($c['title']) ?></h2>
        </div>
        <div class="uc-compare-wrap rg-reveal">
            <table class="uc-compare-table">
                <thead><tr><th scope="col">Task</th><th scope="col">Doing it by hand</th><th scope="col" class="uc-col-us">With <?= e(APP_NAME) ?></th></tr></thead>
                <tbody>
                <?php foreach ($c['rows'] as $r): ?>
                    <tr><th scope="row"><?= e($r[0]) ?></th><td><span class="uc-x">✕</span> <?= e($r[1]) ?></td><td class="uc-col-us"><span class="uc-ok">✓</span> <?= e($r[2]) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php
}

function uc_section_analytics(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $a = $uc['analytics'];
    $img = uc_media()['analytics'];
    ?>
<section class="uc-analytics">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// DATA-DRIVEN GROWTH</div>
            <h2><?= e($a['title']) ?></h2>
            <p class="uc-muted"><?= e($a['text']) ?></p>
        </div>
        <div class="uc-analytics-grid">
            <div class="uc-acard rg-reveal"><div class="uc-astat">Up to 5x more traffic</div><img src="<?= e($img[0]) ?>" alt="<?= e($a['alts'][0]) ?>" loading="lazy"><h3>Analytics</h3><p><?= e($a['cards'][0]) ?></p></div>
            <div class="uc-acard rg-reveal" style="--d: 90ms"><div class="uc-astat">47% higher engagement</div><img src="<?= e($img[1]) ?>" alt="<?= e($a['alts'][1]) ?>" loading="lazy"><h3>Delete Underperforming Pins</h3><p><?= e($a['cards'][1]) ?></p></div>
            <div class="uc-acard rg-reveal" style="--d: 180ms"><img src="<?= e($img[2]) ?>" alt="<?= e($a['alts'][2]) ?>" loading="lazy"><h3>Top Pin Performance</h3><p><?= e($a['cards'][2]) ?></p></div>
            <div class="uc-acard uc-acard-wide rg-reveal">
                <img src="<?= e($img[3]) ?>" alt="<?= e($a['alts'][3]) ?>" loading="lazy">
                <div><h3>Break It Down Any Way You Want</h3><p><?= e($a['cards'][3]) ?></p>
                    <div class="uc-chips"><span>Boards</span><span>URLs</span><span>Keywords</span><span>Titles</span><span>Descriptions</span><span>Time</span></div></div>
            </div>
        </div>
    </div>
</section>
<?php
}

/** Same plans, prices and monthly/annual toggle as the home page. */
function uc_section_pricing(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $plans = plan_pricing_tables_ready($pdo) ? get_all_plans($pdo, true) : [];
    if (!$plans) return;
    $popularId = null;
    $maxYearly = 0;
    foreach ($plans as $p) {
        if ($p['tag'] === 'popular') $popularId = (int)$p['id'];
        $maxYearly = max($maxYearly, (float)$p['discount_yearly']);
    }
    $ctaUrl = function (array $plan, string $cycle) use ($user, $base) {
        if ($plan['is_free']) return $base . ($user ? 'user/upgrade' : 'auth/register');
        return $base . ($user ? 'user/checkout?plan=' : 'auth/register?plan=') . (int)$plan['id'] . '&cycle=' . $cycle;
    };
    $toggleFeatures = [
        'bulk_scheduling_enabled' => 'Bulk scheduling',
        'auto_website_daily_pin_enabled' => 'Auto website-to-daily-pin',
        'auto_article_enabled' => 'Auto article',
        'single_article_writer_enabled' => 'Single article writer',
    ];
    ?>
<section class="uc-pricing" id="pricing">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// PRICING</div>
            <h2><?= e($uc['pricing_title'] ?? 'Simple, transparent pricing') ?></h2>
            <p class="uc-muted">Start free, upgrade any time. Every plan includes AI credits for pin design, copywriting and scheduling.</p>
        </div>
        <div class="uc-billing">
            <span>Monthly</span>
            <label class="toggle-pill" style="width:50px;height:28px;"><input type="checkbox" class="uc-billing-toggle" aria-label="Show annual prices"><span class="toggle-pill-slider" style="border-radius:28px;"></span></label>
            <span>Annual</span>
            <?php if ($maxYearly > 0): ?><span class="uc-save">Save up to <?= (int)$maxYearly ?>% with annual</span><?php endif; ?>
        </div>
        <div class="uc-plan-grid">
            <?php foreach ($plans as $i => $p):
                $rows = get_plan_feature_rows($pdo, (int)$p['id']);
                $tagColor = PLAN_TAG_COLORS[$p['tag_color']] ?? '#e60023';
                $popular = (int)$p['id'] === $popularId;
                $mBase = (float)$p['price_monthly'];
                $mDisc = (float)$p['discount_monthly'];
                $mFinal = $mDisc > 0 ? $mBase * (1 - $mDisc / 100) : $mBase;
                $yBase = $mBase * 12;
                $yDisc = (float)$p['discount_yearly'];
                $yFinal = $yDisc > 0 ? $yBase * (1 - $yDisc / 100) : $yBase;
            ?>
            <div class="uc-plan <?= $popular ? 'uc-plan-popular rg-border-glow' : '' ?> rg-reveal" style="--d: <?= $i * 80 ?>ms"
                 data-monthly-base="<?= e(number_format($mBase, 2)) ?>" data-monthly-final="<?= e(number_format($mFinal, 2)) ?>" data-monthly-discount="<?= (int)$mDisc ?>"
                 data-yearly-base="<?= e(number_format($yBase, 2)) ?>" data-yearly-final="<?= e(number_format($yFinal, 2)) ?>" data-yearly-monthly-equiv="<?= e(number_format($yFinal / 12, 2)) ?>" data-yearly-discount="<?= (int)$yDisc ?>"
                 data-checkout-monthly="<?= e($ctaUrl($p, 'monthly')) ?>" data-checkout-yearly="<?= e($ctaUrl($p, 'yearly')) ?>">
                <?php if ($popular): ?><div class="uc-plan-ribbon">Popular</div><?php endif; ?>
                <?php if ($p['tag'] && !$popular): ?><div class="uc-plan-tag" style="background:<?= e($tagColor) ?>;"><?= e(PLAN_TAG_OPTIONS[$p['tag']] ?? $p['tag']) ?></div><?php endif; ?>
                <h3><?= e($p['name']) ?></h3>
                <?php if ($p['short_description']): ?><p class="uc-plan-desc"><?= e($p['short_description']) ?></p><?php endif; ?>
                <div class="uc-plan-price">
                    <?php if ($mDisc > 0): ?><span class="uc-plan-off js-off"><?= (int)$mDisc ?>% OFF</span><br><?php endif; ?>
                    <span class="uc-plan-amount">$<span class="js-price"><?= number_format($mFinal, 2) ?></span></span><span class="uc-muted">/month</span>
                    <?php if ($mDisc > 0): ?><div class="uc-plan-was js-was">Was $<?= number_format($mBase, 2) ?>/month</div><?php endif; ?>
                    <div class="uc-plan-note js-note" hidden></div>
                </div>
                <a href="<?= e($ctaUrl($p, 'monthly')) ?>" class="uc-plan-btn js-buy rg-shine" style="background:<?= e($p['pay_button_bg']) ?>;color:<?= e($p['pay_button_text_color']) ?>;border-color:<?= e($p['pay_button_border_color']) ?>;"><?= e($p['pay_button_text']) ?></a>
                <ul class="uc-plan-features">
                    <?php foreach ($rows as $r): ?>
                        <li style="font-size:<?= e($r['text_size']) ?>;color:<?= e($r['text_color']) ?>;<?= $r['font'] ? 'font-family:' . e($r['font']) . ';' : '' ?>" title="<?= e($r['tooltip'] ?? '') ?>">
                            <span style="font-size:<?= e($r['checkmark_size']) ?>;color:<?= e($r['checkmark_color']) ?>;"><?= PLAN_CHECKMARK_TYPES[$r['checkmark_type']] ?? '✓' ?></span> <?= e($r['text']) ?>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($toggleFeatures as $k => $label): $has = (bool)($p[$k] ?? false); ?>
                        <li class="<?= $has ? 'uc-yes' : 'uc-no' ?>"><span><?= $has ? '✓' : '✕' ?></span> <?= e($label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="uc-center"><a href="<?= $base ?>pricing" class="btn-primary rg-shine">Compare All Plans →</a></div>
    </div>
</section>
<?php
}

function uc_section_testimonials(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $list = $uc['testimonials'] ?? uc_media()['testimonials'];
    ?>
<section class="uc-testimonials">
    <div class="container">
        <h2 class="uc-section-title rg-reveal"><?= e($uc['testimonials_title'] ?? 'What Our Users Say') ?></h2>
        <div class="uc-track-wrap">
            <div class="uc-track uc-track-slow">
                <?php foreach (array_merge($list, $list) as $t): ?>
                <figure class="uc-tcard">
                    <div class="uc-thead"><img src="<?= e($t['img']) ?>" alt="<?= e($t['name']) ?>" width="44" height="44" loading="lazy" decoding="async"><div><div class="uc-tname"><?= e($t['name']) ?></div><div class="uc-trole"><?= e($t['role']) ?></div></div></div>
                    <div class="uc-stars" aria-label="<?= (int)$t['stars'] ?> out of 5 stars"><?= str_repeat('★', $t['stars']) . str_repeat('☆', 5 - $t['stars']) ?></div>
                    <blockquote><?= e($t['quote']) ?></blockquote>
                </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php
}

function uc_section_real_results(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $imgs = uc_media()['results'];
    ?>
<section class="uc-real-results">
    <div class="container">
        <h2 class="uc-section-title rg-reveal">Check Real Results</h2>
        <div class="uc-track-wrap">
            <div class="uc-track">
                <?php foreach (array_merge($imgs, $imgs) as $img): ?>
                    <div class="uc-result-card"><img src="<?= e($img) ?>" alt="Real Pinterest results screenshot from a <?= e(APP_NAME) ?> user" width="260" height="320" loading="lazy" decoding="async"></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php
}

function uc_section_who(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $w = $uc['who'];
    ?>
<section class="uc-who">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// WHO IT'S FOR</div>
            <h2><?= e($w['title']) ?> <span class="uc-red"><?= e($w['accent']) ?></span></h2>
            <p class="uc-muted"><?= e($w['text']) ?></p>
        </div>
        <div class="uc-who-grid">
            <?php foreach ($w['cards'] as $i => $c): ?>
                <div class="uc-who-card <?= $i === 1 ? 'uc-who-hl rg-border-glow' : '' ?> rg-reveal" style="--d: <?= $i * 90 ?>ms"><h3><?= e($c[0]) ?></h3><p><?= e($c[1]) ?></p></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
}

function uc_section_cta_red(PDO $pdo, ?array $user, array $uc, string $base): void
{
    ?>
<section class="uc-cta-red">
    <div class="container uc-cta-red-inner rg-reveal">
        <div><h2><?= e($uc['cta_red'][0]) ?></h2><p><?= e($uc['cta_red'][1]) ?></p></div>
        <?= uc_cta($user, $base, 'Try ' . APP_NAME . ' Free →', 'uc-cta-pill rg-shine') ?>
    </div>
</section>
<?php
}

function uc_section_faq(PDO $pdo, ?array $user, array $uc, string $base): void
{
    ?>
<section class="uc-faq" id="faq">
    <div class="container">
        <h2 class="uc-section-title rg-reveal"><?= e($uc['faq_title']) ?></h2>
        <div class="uc-faq-grid">
            <?php foreach ($uc['faq'] as $i => $q): ?>
                <details class="rg-reveal" style="--d: <?= ($i % 2) * 70 ?>ms"><summary><?= e($q[0]) ?></summary><p><?= e($q[1]) ?></p></details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
}

/** Internal links: other use cases from the same group first, then a few from other groups. */
function uc_section_related(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $all = uc_catalog();
    $group = $all[$uc['slug']]['group'] ?? 'platforms';
    unset($all[$uc['slug']]);
    $same = array_filter($all, fn($c) => $c['group'] === $group);
    $other = array_filter($all, fn($c) => $c['group'] !== $group && $c['full']);
    $pick = array_slice($same, 0, 8, true) + array_slice($other, 0, 4, true);
    $hub = $uc['hub_path'] ?? '../';
    ?>
<section class="uc-related">
    <div class="container">
        <h2 class="uc-section-title rg-reveal">More Pinterest automation use cases</h2>
        <div class="uc-related-grid">
            <?php $i = 0; foreach ($pick as $slug => $c): ?>
                <a href="<?= e($hub . $slug) ?>/" class="uc-rcard rg-reveal" style="--d: <?= ($i++ % 4) * 60 ?>ms"><span class="uc-rcard-ic"><?= $c['icon'] ?></span><b><?= e($c['title']) ?></b><small><?= e($c['summary']) ?></small></a>
            <?php endforeach; ?>
        </div>
        <div class="uc-center"><a href="<?= e($hub) ?>" class="btn-secondary uc-btn-outline">See all <?= count(uc_catalog()) ?> use cases →</a></div>
    </div>
</section>
<?php
}

/** The three headline features, shown right after every hero. */
function uc_section_power(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $what = $uc['power_noun'] ?? 'pages';
    ?>
<section class="uc-power">
    <div class="container">
        <div class="uc-power-grid">
            <article class="uc-power-card uc-power-red rg-reveal">
                <div class="uc-power-ic">⚡</div>
                <h2>AI Auto-Creates Pin Images for Hundreds of <?= e(ucfirst($what)) ?> — in 1 Click</h2>
                <p>Select hundreds of <?= e($what) ?> at once. AI designs every pin from your own photos, writes the title, description, alt text and keywords, picks the board and schedules it all.</p>
                <a href="<?= e(uc_signup_or($user, $base, 'user/website-pages')) ?>" class="uc-power-link"><?= $user ? 'Pin your pages now →' : 'Create a free account →' ?></a>
            </article>
            <article class="uc-power-card uc-power-dark rg-reveal" style="--d: 90ms">
                <div class="uc-power-ic">✍️</div>
                <h2>AI Auto Blog: Create, Publish &amp; Pin</h2>
                <p>Paste a list of titles. AI writes each article with images, publishes it to your site on a daily schedule, and schedules pins for every post automatically.</p>
                <a href="<?= e(uc_signup_or($user, $base, 'user/auto-article-create')) ?>" class="uc-power-link"><?= $user ? 'Start an Auto Blog batch →' : 'Sign up free to auto blog →' ?></a>
            </article>
            <article class="uc-power-card uc-power-green rg-reveal" style="--d: 180ms">
                <div class="uc-power-ic">♾️</div>
                <h2>Unlimited AI Pin Designs</h2>
                <p>70 templates × 56 colour palettes × 130+ fonts, plus your own Canva designs — AI picks the best look for every page, so no two pins look the same.</p>
                <a href="<?= e(uc_signup_or($user, $base, 'user/classic-wizard')) ?>" class="uc-power-link"><?= $user ? 'Design your pins →' : 'Get started free →' ?></a>
            </article>
        </div>
        <div class="uc-power-strip rg-reveal">
            <span>📈 Build real growth</span><span>⏱️ Save hours every week</span><span>🔁 Runs on autopilot</span><span>✅ Official Pinterest API</span>
        </div>
    </div>
</section>
<?php
}

/** Auto Blog: titles in → articles with images published → pins scheduled. */
function uc_section_autoblog(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $niche = $uc['autoblog_niche'] ?? 'your niche';
    ?>
<section class="uc-autoblog" id="auto-blog">
    <div class="uc-autoblog-glow" aria-hidden="true"></div>
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow uc-eyebrow-light">// NEW · AUTO BLOG + AUTO PIN</div>
            <h2>Create Hundreds of Blog Posts With Images, Publish Them and <span class="uc-grad-anim">Schedule Pins — in 1 Click</span></h2>
            <p>Give AI a list of titles for <?= e($niche) ?>. It writes every article with images, publishes to your website at the pace you choose, and schedules Pinterest pins for each post — all in the background.</p>
        </div>
        <ol class="uc-flow">
            <li class="rg-reveal"><span class="uc-flow-n">1</span><div class="uc-flow-ic">📋</div><h3>Paste your titles</h3><p>One title per line — or paste competitor links and let AI turn them into your own original titles.</p></li>
            <li class="rg-reveal" style="--d: 90ms"><span class="uc-flow-n">2</span><div class="uc-flow-ic">🤖</div><h3>AI writes with images</h3><p>Full articles with a featured image and in-post images, categories, authors and optional tags.</p></li>
            <li class="rg-reveal" style="--d: 180ms"><span class="uc-flow-n">3</span><div class="uc-flow-ic">🌐</div><h3>Published for you</h3><p>Posts go live on your WordPress, Shopify, Wix or custom site — as many per day as you set.</p></li>
            <li class="rg-reveal" style="--d: 270ms"><span class="uc-flow-n">4</span><div class="uc-flow-ic">📌</div><h3>Pins scheduled</h3><p>Several pins per article, spaced weeks apart, on boards AI matches or creates for each topic.</p></li>
        </ol>
        <div class="uc-autoblog-stats rg-reveal">
            <div><b>100s</b><span>articles per batch</span></div>
            <div><b>1–20</b><span>posts published per day</span></div>
            <div><b>Up to 20</b><span>pins per article</span></div>
            <div><b>1</b><span>setup — then autopilot</span></div>
        </div>
        <div class="uc-center">
            <a href="<?= e(uc_signup_or($user, $base, 'user/auto-article-create')) ?>" class="btn-primary rg-shine uc-btn-pulse"><?= $user ? 'Create an Auto Blog Batch →' : 'Sign Up Free & Start Auto Blogging →' ?></a>
            <small class="uc-autoblog-note">Auto Blog is available on plans that include it — see pricing below.</small>
        </div>
    </div>
</section>
<?php
}

/** Links to the free tools (internal linking). */
function uc_section_free_tools(PDO $pdo, ?array $user, array $uc, string $base): void
{
    $tools = uc_free_tools();
    $list = array_values(array_filter(uc_tools_for($uc), fn($t) => isset($tools[$t])));
    ?>
<section class="uc-tools">
    <div class="container">
        <div class="uc-head rg-reveal">
            <div class="uc-eyebrow">// FREE TOOLS</div>
            <h2>Free Pinterest Tools — No Sign-Up Needed</h2>
            <p class="uc-muted">Try these free tools first, then put everything on autopilot.</p>
        </div>
        <div class="uc-tools-grid">
            <?php foreach ($list as $i => $slug): $t = $tools[$slug]; ?>
                <a href="<?= $base ?>free-tools/<?= e($slug) ?>/" class="uc-tool rg-reveal" style="--d: <?= ($i % 4) * 60 ?>ms"><span class="uc-tool-ic"><?= $t[0] ?></span><b><?= e($t[1]) ?></b><small><?= e($t[2]) ?></small></a>
            <?php endforeach; ?>
        </div>
        <div class="uc-center"><a href="<?= $base ?>free-tools/" class="btn-secondary uc-btn-outline">All <?= count($tools) ?>+ free tools →</a></div>
    </div>
</section>
<?php
}

function uc_section_cta_dark(PDO $pdo, ?array $user, array $uc, string $base): void
{
    ?>
<section class="uc-cta-dark">
    <div class="uc-cta-dark-glow" aria-hidden="true"></div>
    <div class="container uc-cta-dark-inner rg-reveal">
        <h2><?= e($uc['cta_dark'][0]) ?></h2>
        <p><?= e($uc['cta_dark'][1]) ?></p>
        <div class="uc-cta-dark-actions">
            <?= uc_cta($user, $base, $uc['cta_dark'][2], 'btn-primary rg-shine rg-border-glow') ?>
            <a href="#pricing" class="uc-cta-dark-link rg-text-blink">See pricing</a>
        </div>
    </div>
</section>
<?php
}


/* ===================== Generic pages (until a page gets its own content) ===================== */

/**
 * Builds a complete page config from the catalog entry and its group, so every listed use case
 * has a working page right away. Replace with hand-written content (and drop noindex) later.
 */
function uc_generic_config(string $slug): array
{
    $c = uc_catalog()[$slug];
    $app = APP_NAME;
    $name = $c['title'];
    $lower = mb_strtolower($name);
    $g = $c['group'];

    $by = [
        'platforms' => [
            'noun' => 'pages', 'aud' => 'site owners', 'thing' => 'pages, posts and products',
            'bullets' => [['🗺️', 'Reads Your Sitemap — Every Page Found Automatically'], ['🖼️', 'Pins Made From Your Own Photos'], ['🤖', 'AI Writes Titles, Descriptions, Alt Text & Keywords'], ['📅', 'A Year of Pins Scheduled in One Run'], ['🔌', 'Nothing to Install on Your Site']],
            'features' => [['🗺️', 'Automatic page discovery', 'Paste your site link and every page in your sitemap is listed, ready to select.'], ['🔌', 'No plugin or code', 'We read your public pages — nothing is installed on your site.'], ['🎨', 'On-brand designs', '70 templates, 56 palettes, 130+ fonts and your own Canva designs.'], ['🧠', 'Page-aware copy', 'AI reads each page and writes pin text that matches real searches.'], ['🗂️', 'Smart boards', 'Every pin lands on the best board — or a new one AI creates.'], ['📊', 'Built-in analytics', 'See which pages Pinterest sends traffic to.']],
        ],
        'marketplaces' => [
            'noun' => 'listings', 'aud' => 'sellers and artists', 'thing' => 'listings and designs',
            'bullets' => [['🎨', 'Pins Made From Your Designs & Mockups'], ['🧩', 'One Design, Many Pins Across Products'], ['🔤', 'AI Writes Niche Titles Buyers Search'], ['📅', 'Schedule Your Whole Portfolio at Once'], ['🎯', 'Boards by Theme and Niche']],
            'features' => [['🖼️', 'Mockup-friendly templates', 'Clean layouts and collages that show off your products.'], ['🔤', 'Niche keyword copy', 'AI writes titles like shoppers type them.'], ['🧪', 'Find winning designs', 'Pin every design and let clicks show what sells.'], ['🎄', 'Seasonal timing', 'Schedule holiday designs weeks ahead.'], ['🗂️', 'Themed boards', 'Designs grouped on boards AI picks or creates.'], ['💸', 'Free traffic', 'Every sale from a pin is a sale without ad spend.']],
        ],
        'blogs' => [
            'noun' => 'posts', 'aud' => 'bloggers and creators', 'thing' => 'posts',
            'bullets' => [['📚', 'Pin Your Whole Archive — Not Just New Posts'], ['🎨', 'Niche Templates People Actually Save'], ['🤖', 'AI Writes Titles, Descriptions & Keywords'], ['✍️', 'Auto Blog: AI Writes, Publishes & Pins Posts'], ['📅', 'A Year of Pins Scheduled in Minutes']],
            'features' => [['📚', 'Your archive, working again', 'Old evergreen posts get fresh pins and new readers.'], ['🎨', 'Save-worthy designs', 'Templates modelled on the pins people save in your niche.'], ['🔁', 'Several pins per post', 'Different photos and headlines, spaced weeks apart.'], ['✍️', 'Auto Blog', 'AI writes and publishes posts with images, then pins them.'], ['🗂️', 'Focused boards', 'Posts placed on the right board, or a new one.'], ['📈', 'Traffic that compounds', 'Pins keep getting saved and clicked for months.']],
        ],
        'shops' => [
            'noun' => 'products', 'aud' => 'shop owners', 'thing' => 'products and collections',
            'bullets' => [['🛍️', 'Every Product Pinned — New Arrivals Included'], ['🖼️', 'Pins From Your Real Product Photos'], ['🤖', 'AI Writes Buyer-Intent Titles & Descriptions'], ['📅', 'Months of Product Pins in One Run'], ['💯', 'AI Picks or Creates the Right Board']],
            'features' => [['🖼️', 'Real product photos', 'Pins use the photos from your product pages.'], ['🧾', 'Product-aware copy', 'Titles and descriptions written around what shoppers search.'], ['🔁', 'Multiple pins per product', 'New designs and headlines over time.'], ['🎁', 'Gift-season ready', 'Schedule holiday products 6–8 weeks early.'], ['🗂️', 'Collection boards', 'Products organised on the right boards.'], ['📊', 'Know what sells', 'Analytics show which products get clicks.']],
        ],
        'services' => [
            'noun' => 'pages', 'aud' => 'service businesses', 'thing' => 'services, portfolio and posts',
            'bullets' => [['🖼️', 'Pins From Your Portfolio & Posts'], ['📣', 'Bring Inquiries From People Planning Ahead'], ['🤖', 'AI Writes Every Pin Title & Description'], ['👥', 'Team Access Without Sharing Logins'], ['📅', 'Consistent Posting on Autopilot']],
            'features' => [['🖼️', 'Portfolio pins', 'Show your best work to people planning a purchase.'], ['📍', 'Guides that attract clients', 'Pin your tips and guides that lead to your services.'], ['👥', 'Team Management', 'Invite staff or clients without sharing passwords.'], ['🗂️', 'Multiple sites', 'Manage several websites from one dashboard.'], ['🤖', 'Hands-off copy', 'AI writes pin text for every page.'], ['📊', 'Reporting', 'Pin analytics you can share with clients.']],
        ],
    ];
    $d = $by[$g];

    return [
        'slug' => $slug,
        'name' => $name,
        'power_noun' => $d['noun'],
        'autoblog_niche' => $lower,
        'meta' => [
            'title' => (function () use ($name, $app) {
                foreach (["Pinterest for $name: Auto-Pin & Schedule", "Pinterest Automation for $name", "Pinterest for $name"] as $t) {
                    if (mb_strlen($t) <= 52) return "$t | $app";
                }
                return "Pinterest for $name";
            })(),
            'description' => mb_substr("Pinterest automation for $lower. AI turns your {$d['thing']} into pins, writes the copy and schedules months of pins in one click. Start free.", 0, 158),
            'keywords' => "Pinterest for $lower, $lower Pinterest marketing, Pinterest automation, auto pin, Pinterest pin scheduler, AI pin maker",
            'noindex' => true, // generic content — remove once this page has its own copy
        ],
        'hero' => [
            'badge' => $c['icon'] . ' For ' . $name,
            'h1' => "Pinterest Automation for $name",
            'h1_accent' => 'More Traffic on Autopilot',
            'sub' => $c['summary'] . " Turn your {$d['thing']} into AI-designed pins and schedule months of Pinterest content in minutes.",
            'bullets' => $d['bullets'],
            'cta' => 'Start Free →',
            'chips' => ['📌 Auto-scheduled', $c['icon'] . ' Pin published', '📈 New visitor'],
        ],
        'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Link, Get Pins in Seconds', 'text' => 'Try it on any page from your site — we pull the photos, write the pin copy and design the pins.', 'placeholder' => 'https://yourwebsite.com/your-page'],
        'marquee' => [],
        'results' => [
            'title' => 'See the Results: Pins That Keep Working',
            'text' => 'A pin keeps getting saved and clicked for months. Consistent pinning turns your ' . $d['thing'] . ' into steady traffic.',
            'stats' => [[5, 'x', 'more traffic, up to'], [70, '', 'pin templates'], [365, '', 'days of pins in one run'], [56, '', 'colour palettes']],
            'alt' => "Pinterest traffic growth for $lower",
        ],
        'steps' => [
            'title' => 'From Your Site to', 'title_accent' => 'Scheduled Pins',
            'text' => 'Scan, design, schedule, approve — AI does the rest.',
            'items' => [
                ['icon' => '🗺️', 'label' => 'Setup', 'title' => 'Add Your Website', 'alt' => 'Scanning a website for Pinterest pins', 'points' => ["We list your {$d['thing']} automatically.", 'Select in bulk — or everything at once.']],
                ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick Your Look', 'alt' => 'Choosing pin templates and colours', 'points' => ['70 templates, 56 palettes, 130+ fonts.', 'Import your own Canva design, or let AI choose.']],
                ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Set Your Pace', 'alt' => 'Pin scheduling settings', 'points' => ['Pins per day, or the new-account warm-up.', 'Several pins per page, spaced weeks apart.']],
                ['icon' => '🚀', 'label' => 'Approve', 'title' => 'Approve and Relax', 'alt' => 'Approving scheduled pins', 'points' => ['Edit anything, then approve.', 'Pins publish on autopilot.']],
            ],
        ],
        'features' => ['eyebrow' => 'WHY IT WORKS', 'title' => "Why $name Automate Pinterest", 'text' => "Everything $lower need to grow on Pinterest without spending hours on it.", 'items' => $d['features']],
        'compare' => [
            'title' => 'Manual Pinning vs Automation',
            'rows' => [['Designing pins', 'One at a time in Canva', 'Hundreds designed in 1 click'], ['Pin copy', 'Written by hand', 'AI writes it for you'], ['Posting', 'Daily manual work', 'A year scheduled at once'], ['Blog content', 'Write every post yourself', 'Auto Blog writes, publishes and pins']],
        ],
        'analytics' => [
            'title' => 'See What Pinterest Loves', 'text' => 'Find the pins that bring clicks and make more of them.',
            'cards' => ['Track clicks and impressions for every pin.', 'Remove weak pins to keep engagement strong.', 'See your top pins at a glance.', 'Compare by board, URL, keyword, title and time.'],
            'alts' => ['Pinterest analytics dashboard', 'Removing underperforming pins', 'Top pin performance', 'Pin analytics breakdown'],
        ],
        'who' => [
            'title' => 'Made for', 'accent' => $lower, 'text' => "From solo $d[aud] to growing teams.",
            'cards' => [['Just starting out', 'Get on Pinterest from day one without learning design tools.'], ['Growing fast', 'Keep everything you publish visible on Pinterest automatically.'], ['Teams & agencies', 'Share access with Team Management — no shared logins.']],
        ],
        'cta_red' => ["Make Pinterest the easy part for $lower.", 'Pins designed, written and scheduled automatically.'],
        'faq_title' => "$name + Pinterest — FAQ",
        'faq' => [
            ["Does this work for $lower?", "Yes. Any site with public pages works — paste your link, select your {$d['thing']} and schedule pins."],
            ['How many pins can I create at once?', 'Hundreds. Select hundreds of pages in one run and AI designs and schedules several pins for each.'],
            ['What is Auto Blog?', 'On plans that include it, AI writes articles with images from your list of titles, publishes them to your site at your daily pace, and schedules pins for every post.'],
            ['How many pin designs are there?', '70 templates, 56 colour palettes and 130+ fonts — plus your own Canva designs — so the combinations are practically unlimited.'],
            ['Is it safe for a new Pinterest account?', 'Yes. The warm-up mode starts at 1 pin a day and grows to 20 a day by month five.'],
            ['Is there a free way to try it?', 'Yes — use the free Pin Maker and other free tools with no account, or create a free account.'],
        ],
        'cta_dark' => ["Ready to grow your $lower with Pinterest?", 'Free to start. Hundreds of pins in one click.', 'Start Free →'],
    ];
}

/* ===================== Page builder (hand-written niche content + shared structure) ===================== */

/**
 * Turns a page's own content into a full page config. The page supplies everything that
 * makes it unique (meta, hero, features, playbook, FAQ, CTAs); steps, results, analytics and
 * the comparison table are filled in with the page's own nouns.
 */
function uc_build(array $p): array
{
    $app = APP_NAME;
    $noun = $p['noun'];             // what gets pinned: "posts", "products", "listings"
    $site = $p['site'] ?? 'Site';   // "Blog", "Shop", "Website"
    $short = $p['short'];           // "Pet Blog"
    $orders = [
        ['hero', 'start', 'marquee', 'results_graph', 'steps', 'features', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
        ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'analytics', 'compare', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
        ['hero', 'start', 'marquee', 'steps', 'results_graph', 'features', 'playbook', 'compare', 'pricing', 'analytics', 'who', 'testimonials', 'real_results', 'cta_red', 'faq', 'related', 'cta_dark'],
        ['hero', 'start', 'marquee', 'features', 'steps', 'playbook', 'results_graph', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    ];
    $faq = $p['faq'];
    $faq[] = ["Can AI create pins for all my $noun at once?", "Yes. Select hundreds of $noun in one run and approve once — AI designs every pin from your own images, writes the copy, picks the boards and schedules everything."];
    $faq[] = ['Can AI write and publish new posts too?', 'On plans that include Auto Blog, AI writes articles with images from your list of titles, publishes them to your WordPress, Shopify, Wix or custom site at your daily pace, and schedules pins for every post. Review anything factual before it goes live.'];
    $faq[] = ['Is there a free way to try it?', 'Yes — use the free Pin Maker and other free tools with no account, or create a free account to start scheduling.'];

    return [
        'slug' => $p['slug'],
        'name' => $p['name'],
        'accent' => $p['accent'] ?? '#12c464',
        'order' => $orders[crc32($p['slug']) % count($orders)],
        'power_noun' => $noun,
        'autoblog_niche' => $p['niche'],
        'tools' => $p['tools'] ?? null,
        'meta' => ['title' => $p['title'] . " | $app", 'description' => $p['desc'], 'keywords' => $p['kw']],
        'hero' => [
            'badge' => $p['badge'], 'h1' => $p['h1'], 'h1_accent' => $p['h1_accent'], 'sub' => $p['sub'],
            'bullets' => $p['bullets'], 'cta' => $p['cta'] ?? 'Start Free →', 'chips' => $p['chips'],
        ],
        'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => $p['start_title'] ?? "Paste a Link From Your $site", 'text' => "We pull the images from your page, write the pin copy and design the pins in seconds.", 'placeholder' => $p['placeholder']],
        'marquee' => $p['marquee'],
        'results' => [
            'title' => 'See the Results: ' . $p['results'][0], 'text' => $p['results'][1],
            'stats' => $p['stats'] ?? [[5, 'x', 'more traffic, up to'], [70, '', 'pin templates'], [365, '', 'days of pins in one run'], [56, '', 'colour palettes']],
            'alt' => "Pinterest traffic growth for a $short",
        ],
        'steps' => [
            'title' => "From Your $site to", 'title_accent' => 'Scheduled Pins',
            'text' => $p['steps_text'] ?? 'Scan, design, schedule, approve — AI handles the rest.',
            'items' => [
                ['icon' => '🗺️', 'label' => 'Setup', 'title' => "Scan Your $site", 'alt' => "Scanning a $short for Pinterest pins", 'points' => ["Every one of your $noun listed automatically from your sitemap — or paste single links.", 'Search, then select in bulk or select everything.']],
                ['icon' => '🎨', 'label' => 'Design', 'title' => $p['design_title'] ?? 'Pick Your Pin Style', 'alt' => "Choosing pin templates for a $short", 'points' => [$p['design_point'] ?? '70 templates, 56 palettes and 130+ fonts with a live preview.', 'Import your own Canva design, or let AI pick the template per page.']],
                ['icon' => '⚙️', 'label' => 'Schedule', 'title' => $p['schedule_title'] ?? 'Set a Safe Pace', 'alt' => "Scheduling pins for a $short", 'points' => [$p['schedule_point'] ?? 'Pins per day with automatic gaps, or the new-account warm-up.', "Several pins per $noun, spaced weeks apart. AI picks or creates the boards."]],
                ['icon' => '🚀', 'label' => 'Approve', 'title' => 'Approve Once', 'alt' => "Approving pins for a $short", 'points' => ['Edit any pin, then approve the batch.', 'Pins publish on autopilot; anything unapproved waits in Drafts.']],
            ],
        ],
        'features' => ['eyebrow' => $p['eyebrow'] ?? 'WHY IT WORKS', 'title' => $p['features'][0], 'text' => $p['features'][1], 'items' => $p['features'][2]],
        'playbook' => ['title' => $p['playbook'][0], 'text' => $p['playbook'][1], 'tips' => $p['playbook'][2]],
        'compare' => [
            'title' => "Manual vs Automated $short Pins",
            'rows' => array_merge([
                ['Pin design', "One at a time", "Hundreds of $noun in 1 click"],
                ['Pin copy', 'Written by hand', 'AI writes titles, descriptions & alt text'],
                ['Posting', 'Daily manual work', 'Months scheduled in one run'],
            ], $p['compare'] ?? [['New content', 'Write every post yourself', 'Auto Blog writes, publishes & pins']]),
        ],
        'analytics' => [
            'title' => $p['analytics_title'] ?? 'See What Pinterest Users Save', 'text' => "Find the $noun that bring clicks and make more of them.",
            'cards' => ["Track clicks and saves for all your $noun.", 'Remove weak pins to keep engagement strong.', 'See your top pins at a glance.', 'Compare by board, URL, keyword, title and time.'],
            'alts' => ["Pinterest analytics for a $short", 'Removing underperforming pins', 'Top pin performance', 'Pin analytics breakdown'],
        ],
        'who' => ['title' => 'Made for', 'accent' => $p['who'][0], 'text' => $p['who'][1], 'cards' => $p['who'][2]],
        'cta_red' => [$p['cta_red'][0], $p['cta_red'][1]],
        'faq_title' => $p['faq_title'] ?? $p['name'] . ' + Pinterest — FAQ',
        'faq' => $faq,
        'cta_dark' => [$p['cta_dark'], 'Hundreds of pins in one click. Free to start.', 'Sign Up Free →'],
    ];
}
