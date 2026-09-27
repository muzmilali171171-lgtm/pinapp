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
'slug' => 'gumroad', 'name' => 'Gumroad Creators', 'short' => 'Gumroad Shop', 'site' => 'Gumroad Shop', 'accent' => '#ff90e8', 'noun' => 'products', 'niche' => 'your digital products’ topics',
'title' => 'Pinterest for Gumroad Creators & Digital Products', 'desc' => 'Promote your Gumroad products on Pinterest. AI designs pins for your ebooks, templates, presets and courses and schedules them in 1 click.',
'kw' => 'Gumroad Pinterest, promote Gumroad products, digital products Pinterest, sell templates Pinterest, ebook marketing Pinterest, creator products traffic',
'badge' => '💾 For Gumroad Creators', 'h1' => 'Pinterest Automation for Gumroad Creators', 'h1_accent' => 'Digital Products, Real Sales',
'sub' => 'Templates, presets, ebooks, courses — digital products sell beautifully on Pinterest because people save them for later. Pin every product and every free resource that leads to them.',
'bullets' => [['📦', 'Pins for Templates, Presets, Ebooks & Courses'], ['🖼️', 'Collages That Preview What’s Inside'], ['🤖', 'AI Writes Titles Around the Outcome'], ['⚡', 'Every Product Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Posts That Lead to Your Products']],
'chips' => ['💾 Product pinned', '💾 Saved for later', '💳 Sale'],
'placeholder' => 'https://yourname.gumroad.com/l/your-product',
'marquee' => ['Notion templates', 'Lightroom presets', 'Ebooks', 'Courses', 'Canva templates', 'Planners', 'Fonts', 'Icon packs', 'Printables', 'Guides'],
'results' => ['Products People Save for Later', 'Digital products get saved and bought later. Consistent pinning keeps them in front of buyers every day.'],
'features' => ['Why Gumroad Creators Automate Pinterest', 'A free, long-lasting sales channel.', [
    ['🖼️', 'Preview collages', 'Show pages or screens from your product in one pin.'],
    ['🎯', 'Outcome-led copy', 'AI writes what the product helps people do.'],
    ['🔁', 'Several pins per product', 'Different previews and hooks over time.'],
    ['📝', 'Content + products', 'Pin blog posts and freebies that lead to your products.'],
    ['🗂️', 'Niche boards', 'Productivity, design, photography — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes helpful posts with images that point to your products.'],
]],
'playbook' => ['Pinterest Tips for Digital Product Creators', 'How digital products sell on Pinterest.', [
    ['Show what’s inside', 'Preview pages beat a plain cover.'],
    ['Sell the outcome', '“Plan Your Week in 10 Minutes” beats “Weekly Planner v2”.'],
    ['Pin free resources too', 'Freebies build trust and lead to paid products.'],
    ['Use tall pins', 'More room for previews.'],
    ['Refresh best sellers', 'New preview designs for top products.'],
]],
'who' => ['digital creators', 'Solo creators to small studios.', [['Template & preset creators', 'Show your products to people who save them.'], ['Writers & educators', 'Pin ebooks and courses.'], ['Designers', 'Pin fonts, icons and assets.']]],
'faq' => [
    ['Can I pin Gumroad product pages?', 'Yes — paste your product links. If a page can’t be read automatically it shows as skipped, and you can pin the same product from your own site.'],
    ['What images are used?', 'The images on the product page — your covers and previews.'],
    ['Do pins link to Gumroad?', 'Yes — each pin links to the page it was made from.'],
],
'cta_red' => ['Digital products, real sales.', 'Pin every Gumroad product automatically.'],
'cta_dark' => 'Ready to sell more on Gumroad with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
