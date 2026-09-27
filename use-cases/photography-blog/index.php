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
'slug' => 'photography-blog', 'name' => 'Photography Blogs', 'short' => 'Photography Blog', 'site' => 'Blog', 'accent' => '#6366f1', 'noun' => 'posts', 'niche' => 'photography tips and photo stories',
'title' => 'Pinterest for Photography Blogs & Photographers', 'desc' => 'Show your photos to people who save great images. AI turns your photography posts and galleries into pins and schedules months of them in 1 click.',
'kw' => 'Pinterest for photographers, photography blog Pinterest, photo tips pins, photography portfolio Pinterest, photography blog traffic, photo editing tips pins',
'badge' => '📷 For Photographers & Photo Blogs', 'h1' => 'Pinterest Automation for Photography Blogs', 'h1_accent' => 'Let Your Photos Travel',
'sub' => 'Pinterest is a visual search engine — the perfect home for your photographs. Turn every tutorial, gallery and gear guide into pins that let your images do the talking, and keep them publishing all year.',
'bullets' => [['🖼️', 'Full-Bleed Templates That Keep Your Photos the Hero'], ['📚', 'Tutorials, Galleries, Presets & Gear Guides'], ['🤖', 'AI Writes Titles for Photographers and Clients'], ['⚡', 'Your Whole Archive Pinned in 1 Click'], ['🗂️', 'Boards by Genre: Portrait, Wedding, Landscape…']],
'chips' => ['📷 Gallery pinned', '💾 Saved to Photo Tips', '📩 New booking enquiry'],
'placeholder' => 'https://yourphotoblog.com/golden-hour-portrait-tips/',
'marquee' => ['Photography tips', 'Posing guides', 'Lightroom presets', 'Golden hour', 'Wedding photography', 'Newborn photos', 'Landscape shots', 'Camera gear', 'Editing tutorials', 'Photo locations'],
'results' => ['Photos That Keep Being Discovered', 'Great images get saved and re-saved for years. Consistent pinning turns your portfolio and tutorials into long-lasting visits and enquiries.'],
'design_title' => 'Let the Photo Lead', 'design_point' => 'Full-photo and minimal frame templates with clean, small text.',
'features' => ['Why Photographers Automate Pinterest', 'More eyes on your work — without spending evenings making pins.', [
    ['🖼️', 'Photo-first templates', 'Minimal frames and overlays that never cover the shot.'],
    ['🎓', 'Tutorial pins', 'Posing, lighting and editing tips pinned for learners.'],
    ['💼', 'Client-ready pins', 'Session and wedding galleries pinned for people planning a shoot.'],
    ['🎨', 'Preset & product pins', 'Promote presets, courses and prints alongside your posts.'],
    ['🗂️', 'Genre boards', 'Pins sorted by portrait, wedding, travel, product and more.'],
    ['✍️', 'Auto Blog', 'AI writes photography tips posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Photographers', 'What gets photo pins saved and clicked.', [
    ['Lead with your strongest frame', 'One stunning image beats a busy collage.'],
    ['Teach something', '“5 Posing Tips for Couples” gets saved far more than “Session Recap”.'],
    ['Pin location guides', 'Photo spot guides attract photographers and clients.'],
    ['Watermark lightly or not at all', 'Big watermarks lower saves; your website shows on the pin anyway.'],
    ['Pin galleries seasonally', 'Fall family sessions in August, weddings in winter planning season.'],
]],
'who' => ['photographers', 'Hobbyists to studios.', [['Photography bloggers', 'Grow readers for tutorials, presets and courses.'], ['Portrait & wedding photographers', 'Show galleries to people planning a session.'], ['Photo educators', 'Keep every lesson in front of learners.']]],
'faq' => [
    ['Will pins crop my photos badly?', 'Templates crop around the centre and upper part of the image; you can choose different photos or templates for any pin before approving.'],
    ['Can I pin client galleries?', 'Yes, if you have your clients’ permission to share the photos publicly.'],
    ['Which templates suit photography?', 'Minimal frame, clean top-photo and overlay templates keep text small and the photo large.'],
],
'cta_red' => ['Let your photos be seen.', 'Pin your whole portfolio and every tutorial automatically.'],
'cta_dark' => 'Ready to get your photography discovered?',
]);
uc_render_page($pdo, $user, $uc);
