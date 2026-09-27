<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = uc_build([
'slug' => 'webflow-website', 'name' => 'Webflow Websites', 'short' => 'Webflow Site', 'site' => 'Webflow Site', 'accent' => '#4353ff', 'noun' => 'CMS pages', 'niche' => 'your Webflow site’s topics',
'title' => 'Pinterest Automation for Webflow Websites', 'desc' => 'Pin every Webflow CMS item and blog post automatically. We read your Webflow sitemap, design pins with AI and schedule them in 1 click.',
'kw' => 'Webflow Pinterest, Webflow blog Pinterest, Webflow CMS marketing, auto pin Webflow, Webflow site traffic, Webflow ecommerce Pinterest',
'badge' => '🧱 For Webflow Sites', 'h1' => 'Pinterest Automation for Webflow Websites', 'h1_accent' => 'Your CMS, Pinned',
'sub' => 'You built a beautiful Webflow site. Now let Pinterest send visitors to it. Every CMS item — blog posts, products, case studies, resources — becomes on-brand pins scheduled for months.',
'bullets' => [['🗺️', 'Reads Your Webflow Sitemap Automatically'], ['🧩', 'Blog, Products, Case Studies & Resources'], ['🎨', 'Pins in Your Brand Colours & Fonts'], ['⚡', 'Hundreds of CMS Pages Pinned in 1 Click'], ['🔌', 'No Custom Code or Integrations']],
'chips' => ['🧱 CMS item pinned', '📌 Scheduled', '📈 Site visits up'],
'placeholder' => 'https://yoursite.webflow.io/blog/your-post',
'marquee' => ['Blog posts', 'Case studies', 'Portfolio', 'Templates', 'Resources', 'Products', 'Guides', 'Landing pages', 'Design tips', 'SaaS content'],
'results' => ['Your Webflow Site, Discovered', 'Every CMS page is another way in. Pinned consistently, they add a steady, free traffic source to your site.'],
'design_title' => 'Match Your Design System', 'design_point' => 'Set your brand palette and fonts once, or build your own template in the free editor.',
'features' => ['Why Webflow Sites Automate Pinterest', 'Design-quality pins for a design-quality site.', [
    ['🗺️', 'Sitemap scanning', 'Webflow’s auto-generated sitemap lists every page.'],
    ['🎨', 'Brand-true pins', 'Your palette and fonts on every pin.'],
    ['🧩', 'Any collection', 'Blog, products, case studies, resources.'],
    ['🔌', 'No code', 'No embeds, no API keys.'],
    ['🗂️', 'Smart boards', 'AI picks or creates the right board.'],
    ['👥', 'Team access', 'Share access with your team or clients.'],
]],
'playbook' => ['Pinterest Tips for Webflow Sites', 'Turn a great site into a traffic source.', [
    ['Pin resource pages', 'Guides and templates get saved the most.'],
    ['Use one visual style', 'Consistent pins build recognition.'],
    ['Give each CMS item several pins', 'Different images and headlines over time.'],
    ['Add Open Graph images', 'Clear featured images make better pins.'],
    ['Keep publishing', 'New CMS items can be pinned on your next run.'],
]],
'who' => ['Webflow teams', 'Designers, startups and agencies.', [['Webflow designers', 'Offer Pinterest growth to clients.'], ['Startups & SaaS', 'Pin resources and posts that bring signups.'], ['Webflow agencies', 'Run Pinterest for many client sites.']]],
'faq' => [
    ['Does it work with Webflow’s sitemap?', 'Yes — Webflow generates a sitemap automatically, and we read it to list your pages.'],
    ['Do I need to add code?', 'No — we read your public pages; nothing is added to your site.'],
    ['Can agencies manage client Webflow sites?', 'Yes — add several websites and use Team Management for shared access.'],
],
'cta_red' => ['Your Webflow site deserves traffic.', 'Pin every CMS page automatically.'],
'cta_dark' => 'Ready to grow your Webflow site with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
