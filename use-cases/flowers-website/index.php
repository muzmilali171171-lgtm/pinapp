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
    'slug' => 'flowers-website',
    'name' => 'Flower Websites',
    'accent' => '#f472b6',
    'order' => ['hero', 'start', 'marquee', 'results_graph', 'features', 'steps', 'playbook', 'compare', 'pricing', 'analytics', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'flower posts and products',
    'autoblog_niche' => 'flowers, bouquets and arrangements',
    'meta' => [
        'title' => "Pinterest for Flower Shops & Flower Blogs | $app",
        'description' => 'Pin your bouquets, arrangements and flower guides automatically. Elegant pin templates and holiday scheduling for florists and flower websites.',
        'keywords' => 'Pinterest for florists, flower shop Pinterest, bouquet pins, flower arrangement ideas Pinterest, flower blog traffic, Valentines flowers pins, florist marketing',
    ],
    'hero' => [
        'badge' => '💐 For Flower Shops & Flower Blogs',
        'h1' => 'Pinterest Automation for Flower Websites',
        'h1_accent' => 'Bouquets People Fall For',
        'sub' => 'Flowers are made for Pinterest — bouquets, arrangements, garden ideas and gift inspiration. Turn your flower photos and guides into elegant pins and have them ready before every flower holiday.',
        'bullets' => [['💐', 'Elegant Pins From Your Bouquet Photos'], ['❤️', 'Valentine’s & Mother’s Day Scheduled Early'], ['🌷', 'Arch Frames, Soft Palettes & Script Fonts'], ['🤖', 'AI Writes Titles by Flower, Colour & Occasion'], ['🛒', 'Products and Guides Pinned Together']],
        'cta' => 'Start Pinning My Flowers — Free',
        'chips' => ['💐 Bouquet pinned', '💾 Saved to Wedding Flowers', '🛒 Order enquiry'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Bouquet or Guide Link', 'text' => 'Get elegant flower pins in seconds.', 'placeholder' => 'https://yourflowershop.com/spring-bouquet/'],
    'marquee' => ['Spring bouquets', 'Peonies', 'Wedding flowers', 'Valentine’s roses', 'Dried flowers', 'Flower arranging', 'Mother’s Day', 'Table centerpieces', 'Wildflowers', 'Flower care'],
    'results' => ['title' => 'See the Results: Blooming Every Holiday', 'text' => 'Flower searches peak around Valentine’s Day, Mother’s Day and wedding season. Pin ahead and your shop is on the boards when buyers decide.', 'stats' => [[5, 'x', 'more traffic, up to'], [100, '%', 'free design editor, no Canva Pro'], [500, '+', 'premium pin templates'], [1000, '+', 'font & colour combinations']], 'alt' => 'Pinterest traffic growth for a flower website'],
    'features' => ['eyebrow' => 'MADE FOR FLOWERS', 'title' => 'Why Florists & Flower Blogs Automate Pinterest', 'text' => 'Beautiful photos plus perfect timing.', 'items' => [
        ['🌷', 'Elegant templates', 'Arch frames, soft palettes and script accents that suit florals.'],
        ['❤️', 'Holiday timing', 'Valentine’s, Mother’s Day and wedding season pinned weeks ahead.'],
        ['🛒', 'Shop + blog', 'Pin bouquets for sale and flower guides side by side.'],
        ['🔤', 'Flower-aware copy', 'AI writes titles with flower names, colours and occasions.'],
        ['🗂️', 'Occasion boards', 'Weddings, birthdays, sympathy, home decor — sorted for you.'],
        ['✍️', 'Auto Blog', 'AI writes flower guides with images and pins them.'],
    ]],
    'steps' => ['title' => 'From Bouquet to', 'title_accent' => 'Scheduled Pin', 'text' => 'Four steps before the next flower holiday.', 'items' => [
        ['icon' => '💐', 'label' => 'Setup', 'title' => 'Scan Your Site', 'alt' => 'Scanning a flower shop website', 'points' => ['Products, bouquets and guides listed from your sitemap.', 'Select by flower, occasion or season.']],
        ['icon' => '🌷', 'label' => 'Design', 'title' => 'Choose an Elegant Look', 'alt' => 'Choosing elegant flower pin templates', 'points' => ['Soft palettes and serif or script fonts.', 'Collages show several arrangements.']],
        ['icon' => '🗓️', 'label' => 'Schedule', 'title' => 'Plan Around Flower Holidays', 'alt' => 'Scheduling flower pins for holidays', 'points' => ['Start 6–8 weeks before each holiday.', 'Several pins per bouquet or guide.']],
        ['icon' => '✅', 'label' => 'Approve', 'title' => 'Approve and Get Arranging', 'alt' => 'Approving flower pins', 'points' => ['Edit anything, approve once.', 'Pins publish on schedule.']],
    ]],
    'playbook' => ['title' => 'Pinterest Tips for Flower Websites', 'text' => 'What makes floral pins get saved.', 'tips' => [
        ['Show the whole arrangement', 'Plus one close-up of the petals for a second pin.'],
        ['Name the flowers', '“Blush Peony & Eucalyptus Bouquet” is searchable; “Our Favourite” isn’t.'],
        ['Pin before each flower holiday', 'Valentine’s pins go out in December, Mother’s Day in March.'],
        ['Create occasion boards', 'People browse by wedding, birthday or sympathy.'],
        ['Pin care guides too', 'Flower-care tips keep buyers coming back.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Flower Pins', 'rows' => [['Pin design', 'One bouquet at a time', 'Hundreds in 1 click'], ['Holiday timing', 'Rushed at the last minute', 'Scheduled weeks ahead'], ['Pin copy', 'Written by hand', 'AI writes flower-rich copy'], ['Guides & blog', 'Written yourself', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'See Which Flowers People Love', 'text' => 'Know what to stock and photograph next.', 'cards' => ['Track clicks for every flower pin.', 'Remove weak pins to keep engagement strong.', 'Your top bouquets at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a flower shop', 'Removing weak flower pins', 'Top flower pins', 'Flower pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'flower businesses', 'text' => 'Florists, flower farms and flower bloggers.', 'cards' => [['Local florists', 'Bring order enquiries from people planning ahead.'], ['Flower farms & online shops', 'Keep every product visible on Pinterest.'], ['Flower & garden bloggers', 'Turn guides into steady traffic.']]],
    'cta_red' => ['Let your flowers bloom on Pinterest.', 'Elegant pins, scheduled before every flower holiday.'],
    'faq_title' => 'Flower Websites + Pinterest — FAQ',
    'faq' => [
        ['Does this work for local florists?', 'Yes. Pins link to your product and guide pages, so local customers planning events can find and contact you.'],
        ['Which templates suit flowers?', 'Arch frames, framed photos and soft palettes with serif or script fonts.'],
        ['Can I pin all my products at once?', 'Yes — select hundreds of pages and approve once.'],
        ['Can Auto Blog write flower guides?', 'On plans with Auto Blog, AI writes guides with images from your titles and publishes them.'],
        ['When should I pin for Valentine’s Day?', 'Start in December, about 6–8 weeks before.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to grow your flower business on Pinterest?', 'Hundreds of flower pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
