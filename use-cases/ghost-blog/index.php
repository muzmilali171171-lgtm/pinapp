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
'slug' => 'ghost-blog', 'name' => 'Ghost Blogs', 'short' => 'Ghost Blog', 'site' => 'Ghost Site', 'accent' => '#15171a', 'noun' => 'posts', 'niche' => 'your publication’s topics',
'title' => 'Pinterest for Ghost Blogs & Newsletters', 'desc' => 'Pin every Ghost post automatically. We read your Ghost sitemap, design pins with AI and schedule them to grow readers and paid members.',
'kw' => 'Ghost blog Pinterest, Ghost CMS Pinterest, Ghost newsletter growth, Ghost membership marketing, auto pin Ghost posts, Ghost publication traffic',
'badge' => '👻 For Ghost Publishers', 'h1' => 'Pinterest Automation for Ghost Blogs', 'h1_accent' => 'More Readers, More Members',
'sub' => 'Ghost is built for independent publishers — Pinterest is built for discovery. Pin every post from your Ghost site automatically and turn new visitors into subscribers and paying members.',
'bullets' => [['🗺️', 'Reads Your Ghost Sitemap Automatically'], ['📨', 'Pins That Lead to Posts With Your Signup Form'], ['🤖', 'AI Writes Titles, Descriptions & Alt Text'], ['⚡', 'Your Whole Archive Pinned in 1 Click'], ['🔌', 'No Integration or Code Needed']],
'chips' => ['👻 Post pinned', '📨 New subscriber', '💳 New member'],
'placeholder' => 'https://yourghostsite.com/your-post/',
'marquee' => ['Newsletters', 'Independent media', 'Tech writing', 'Creator economy', 'Personal essays', 'Guides', 'Podcast notes', 'Paid memberships', 'Tutorials', 'Deep dives'],
'results' => ['A Discovery Channel for Your Publication', 'Ghost posts are evergreen. Pinned consistently, they bring a steady stream of new readers to your signup form.'],
'features' => ['Why Ghost Publishers Automate Pinterest', 'Discovery without an algorithm deciding your reach.', [
    ['🗺️', 'Sitemap scanning', 'Ghost’s built-in sitemap lists every post for you.'],
    ['📨', 'Subscriber growth', 'Pins lead to posts with your Ghost signup form.'],
    ['🔌', 'No integration', 'We read public posts — no API keys.'],
    ['🔤', 'Editorial templates', 'Clean designs that suit independent publications.'],
    ['🔁', 'Several pins per post', 'Different angles for the same piece.'],
    ['✍️', 'Auto Blog', 'Where your site supports it, AI can draft posts with images from your titles and pin them.'],
]],
'playbook' => ['Pinterest Tips for Ghost Publishers', 'Turn pins into members.', [
    ['Pin free posts', 'Public posts can be read and linked; paid posts can’t be previewed by visitors.'],
    ['Lead with the idea, not the brand', 'Specific headlines earn clicks.'],
    ['Keep the signup form visible', 'Every pinned post should invite readers to subscribe.'],
    ['Pin your best archive posts', 'Older evergreen posts are your easiest win.'],
    ['Be consistent', 'A few pins a day beats occasional bursts.'],
]],
'who' => ['Ghost publishers', 'Newsletters, blogs and media.', [['Newsletter writers', 'Grow your list from Pinterest.'], ['Membership publications', 'Turn readers into paying members.'], ['Independent media', 'Promote a large archive consistently.']]],
'faq' => [
    ['Does it work with Ghost’s sitemap?', 'Yes — Ghost publishes a sitemap automatically, and we read it to list your posts.'],
    ['Can I pin members-only posts?', 'Pin public posts. Members-only content can’t be read by us or opened by Pinterest visitors.'],
    ['Do I need to install anything?', 'No — nothing is installed on your Ghost site.'],
],
'cta_red' => ['More readers, more members.', 'Pin every Ghost post automatically.'],
'cta_dark' => 'Ready to grow your Ghost publication with Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
