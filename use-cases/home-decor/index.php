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
    'slug' => 'home-decor',
    'power_noun' => 'posts',
    'autoblog_niche' => 'home decor and DIY',
    'name' => 'Home Decor',
    'accent' => '#b08968',
    'order' => ['hero', 'start', 'marquee', 'features', 'results_graph', 'steps', 'playbook', 'analytics', 'compare', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Pinterest for Home Decor Blogs & Stores | $app",
        'description' => "Automate Pinterest for your home decor website. Turn room ideas, DIY projects and decor products into beautiful pins and schedule months of content in minutes.",
        'keywords' => 'Pinterest home decor, home decor Pinterest marketing, home decor blog Pinterest, interior design Pinterest pins, decor store Pinterest traffic, DIY home decor pins, room ideas Pinterest',
    ],
    'hero' => [
        'badge' => '🛋️ For Home Decor Blogs & Stores',
        'h1' => 'Pinterest Automation for Home Decor Websites',
        'h1_accent' => 'Rooms People Save',
        'sub' => 'Home decor is one of Pinterest’s biggest categories. Turn your room makeovers, decor finds, DIY projects and products into elegant pins that keep sending visitors to your site.',
        'bullets' => [
            ['🛋️', 'Pins From Your Room & Product Photos'],
            ['🏡', 'Elegant Templates: Arch Frames, Editorial & Collage'],
            ['🤖', 'AI Writes Style-Rich Titles (Boho, Japandi, Farmhouse…)'],
            ['📅', 'Seasonal Decor Scheduled Ahead'],
            ['🗂️', 'Boards by Room and Style'],
        ],
        'cta' => 'Start Pinning My Decor Site — Free',
        'chips' => ['🛋️ Room idea pinned', '💾 Saved to Living Room', '🛒 Decor store visit'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Room Idea or Product Link', 'text' => 'We turn your decor photos into polished, save-worthy pins.', 'placeholder' => 'https://yoursite.com/cozy-living-room-ideas/'],
    'marquee' => ['Living room ideas', 'Bedroom decor', 'Boho style', 'Japandi', 'Modern farmhouse', 'Small spaces', 'Kitchen makeovers', 'Wall decor', 'DIY projects', 'Holiday decor'],
    'features' => [
        'eyebrow' => 'MADE FOR DECOR',
        'title' => 'Why Home Decor Brands Win With Pinterest',
        'text' => 'People come to Pinterest to plan their homes. Be the inspiration they save.',
        'items' => [
            ['🏛️', 'Elegant templates', 'Arch frames, editorial layouts and soft palettes that suit interiors.'],
            ['🖼️', 'Room collages', 'Show several angles of a room or a product set in one pin.'],
            ['🎨', 'Palette-matched pins', 'Choose neutrals, earth tones or your brand colours for a cohesive feed.'],
            ['🔤', 'Style keywords', 'AI writes titles with the style and room words people search.'],
            ['🎄', 'Seasonal decor', 'Schedule fall, holiday and spring decor weeks before the season.'],
            ['🛒', 'Blogs and stores', 'Pin inspiration posts and product pages side by side.'],
        ],
    ],
    'results' => [
        'title' => 'See the Results: Inspiration That Keeps Working',
        'text' => 'Decor pins get saved to boards and resurface for months. Consistent pinning turns your room ideas into long-lasting traffic.',
        'stats' => [[100, '%', 'free design editor, no Canva Pro'], [500, '+', 'premium pin templates'], [5, 'x', 'more traffic, up to'], [1000, '+', 'font & colour combinations']],
        'alt' => 'Pinterest traffic growth for a home decor website',
    ],
    'steps' => [
        'title' => 'From Room Idea to', 'title_accent' => 'Scheduled Pin',
        'text' => 'Scan your site, choose a look that fits your style, and let it run.',
        'items' => [
            ['icon' => '🏡', 'label' => 'Setup', 'title' => 'Scan Your Decor Site', 'alt' => 'Scanning a home decor website', 'points' => ['We list your room posts, DIY projects and product pages from your sitemap.', 'Select by room, style or season.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Set Your Aesthetic', 'alt' => 'Choosing elegant pin templates for home decor', 'points' => ['Arch frames, editorial and collage templates.', 'Neutral palettes and elegant serif and script fonts.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Plan by Season', 'alt' => 'Scheduling seasonal home decor pins', 'points' => ['Several pins per post, spaced out over months.', 'Warm-up mode for new accounts.']],
            ['icon' => '✨', 'label' => 'Approve', 'title' => 'Approve and Style Your Next Room', 'alt' => 'Approving home decor pins', 'points' => ['Edit any pin, then approve the batch.', 'Pins publish on schedule.']],
        ],
    ],
    'playbook' => [
        'title' => 'Home Decor Pinterest Strategy',
        'text' => 'What makes decor pins get saved and clicked.',
        'tips' => [
            ['Show the whole room and the details', 'Mix wide room shots with close-ups of textures and styling.'],
            ['Name the style', 'Boho, Japandi, coastal, modern farmhouse — people search by style.'],
            ['Organise boards by room', 'Living Room, Bedroom, Kitchen — it’s how people plan.'],
            ['Pin seasonal decor early', 'Fall decor searches start in August, holiday decor in October.'],
            ['Keep a cohesive palette', 'Consistent colours make your pins recognisable in the feed.'],
        ],
    ],
    'analytics' => [
        'title' => 'See Which Rooms and Products Get Saved',
        'text' => 'Find the styles and products your audience loves, then create more of them.',
        'cards' => ['Track clicks for every decor pin.', 'Remove weak pins to keep engagement strong.', 'See your top rooms and products.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for a home decor site', 'Removing weak decor pins', 'Top home decor pins', 'Decor pin analytics breakdown'],
    ],
    'compare' => [
        'title' => 'Manual Decor Pinning vs Automation',
        'rows' => [
            ['Pin design', 'Designed one by one', 'Elegant templates, auto-filled'],
            ['Pin titles', 'Rewrite for each post', 'AI writes style-rich copy'],
            ['Seasonal content', 'Pinned too late', 'Scheduled ahead'],
            ['Consistency', 'Depends on free time', 'Daily, automatically'],
        ],
    ],
    'pricing_title' => 'Plans for Home Decor Brands',
    'who' => [
        'title' => 'Made for', 'accent' => 'home decor creators',
        'text' => 'Bloggers, stores and designers.',
        'cards' => [
            ['Decor & DIY bloggers', 'Turn every makeover and project into lasting Pinterest traffic.'],
            ['Home decor stores', 'Pin products and room inspiration that leads to your shop.'],
            ['Interior designers', 'Show your portfolio to people actively planning their homes.'],
        ],
    ],
    'cta_red' => ['Be the inspiration people save.', 'Pin your home decor content automatically.'],
    'faq_title' => 'Home Decor + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Which templates suit home decor?', 'Arch-frame, editorial, framed-photo and collage templates look best with interiors, especially with neutral palettes and serif or script fonts.'],
        ['Can I pin both blog posts and products?', 'Yes. Select room idea posts and product pages from the same scan.'],
        ['Will it use my room photos?', 'Yes. Pins are made from the photos on each page; tiny and banner images are skipped.'],
        ['Can I keep a consistent look?', 'Yes. Pick one palette and font combination, or build your own template in the free design editor and use it for every pin.'],
        ['When should I pin seasonal decor?', 'About 6–8 weeks before the season. Set the first publish date and the gap between pins to match.'],
        ['Can AI organise pins into boards by room?', 'Yes. AI picks the best board for each page and can create new ones, like “Boho Bedroom Ideas”.'],
        ['Is it free to try?', 'Yes. Use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to grow your home decor site with Pinterest?', 'Beautiful pins, scheduled for months, in minutes.', 'Pin My Decor Site →'],
];
uc_render_page($pdo, $user, $uc);
