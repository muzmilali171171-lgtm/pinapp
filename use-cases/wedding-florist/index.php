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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'wedding-florist', 'name' => 'Wedding Florists', 'short' => 'Wedding Florist', 'site' => 'Website', 'accent' => '#f472b6', 'noun' => 'pages', 'niche' => 'wedding flowers and floral design',
'title' => 'Pinterest for Wedding Florists: Get Enquiries', 'desc' => 'Bridal bouquets and wedding flowers pinned for engaged couples. AI designs elegant pins from your work and schedules them in 1 click.',
'kw' => 'wedding florist Pinterest, bridal bouquet pins, wedding flowers Pinterest, florist marketing, wedding centerpiece pins, floral designer Pinterest',
'badge' => '🌸 For Wedding Florists', 'h1' => 'Pinterest Automation for Wedding Florists', 'h1_accent' => 'On Every Bride’s Board',
'sub' => 'Couples plan their wedding flowers on Pinterest months before they book. Pin your bouquets, centrepieces and real weddings so your work is on their board — and your contact form gets the enquiry.',
'bullets' => [['💐', 'Elegant Pins From Your Real Wedding Work'], ['📍', 'Location & Venue Titles Written by AI'], ['🗓️', 'Engagement-Season Planning Pinned Early'], ['⚡', 'Your Whole Portfolio Pinned in 1 Click'], ['🗂️', 'Boards by Style, Colour & Season']],
'chips' => ['🌸 Bouquet pinned', '💾 Saved to Our Wedding', '📩 Enquiry'],
'placeholder' => 'https://yourfloristsite.com/real-weddings/garden-wedding/',
'marquee' => ['Bridal bouquets', 'Centerpieces', 'Ceremony arches', 'Boutonnieres', 'Blush weddings', 'Boho florals', 'Garden weddings', 'Winter weddings', 'Flower crowns', 'Real weddings'],
'results' => ['Enquiries From Planning Couples', 'Couples save florals for months. Your work stays on their board through the whole planning journey.'],
'features' => ['Why Wedding Florists Automate Pinterest', 'Be discovered while couples plan.', [
    ['💐', 'Elegant templates', 'Arch frames, soft palettes and script fonts.'],
    ['📍', 'Local & venue copy', 'City and venue names where your pages mention them.'],
    ['🗓️', 'Planning-season timing', 'Pinned heavily from December to March.'],
    ['🧩', 'Wedding collages', 'Bouquet, centrepiece and arch in one pin.'],
    ['🗂️', 'Style boards', 'By colour, style and season.'],
    ['✍️', 'Auto Blog', 'AI writes wedding flower guides with images.'],
]],
'playbook' => ['Pinterest Tips for Wedding Florists', 'From saved pin to booked wedding.', [
    ['Pin real weddings', 'With the couple’s and photographer’s permission, and credit.'],
    ['Name flowers and colours', '“Blush Peony Bridal Bouquet”.'],
    ['Add your area', 'Local couples search by location.'],
    ['Pin in engagement season', 'December to March is peak planning.'],
    ['Make enquiries easy', 'Every pinned page should link to your contact form.'],
]],
'who' => ['wedding florists', 'Studios and freelance designers.', [['Wedding florists', 'Enquiries from planning couples.'], ['Floral designers', 'Show your style to the right couples.'], ['Venues & planners', 'Pin florals from your events.']]],
'faq' => [
    ['Can I pin real wedding photos?', 'Yes, with permission from the couple and photographer, and with credit.'],
    ['When do couples plan wedding flowers?', 'Most planning starts in engagement season, December to March.'],
    ['Which templates suit florals?', 'Arch frames, soft palettes and script accents.'],
],
'cta_red' => ['On every bride’s board.', 'Pin your wedding work automatically.'],
'cta_dark' => 'Ready to book more weddings from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
