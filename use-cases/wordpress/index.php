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
    'slug' => 'wordpress',
    'power_noun' => 'posts',
    'autoblog_niche' => 'your blog',
    'name' => 'WordPress',
    'accent' => '#21759b',
    'meta' => [
        'title' => "WordPress to Pinterest: Auto-Pin Every Blog Post | $app",
        'description' => "Automatically pin every WordPress post. We read your sitemap, design pins with AI, write the copy and schedule it all to Pinterest. No plugin needed.",
        'keywords' => 'WordPress Pinterest automation, auto pin WordPress posts, WordPress to Pinterest, Pinterest for bloggers, Pinterest blog traffic, WordPress Pinterest scheduler, pin old blog posts',
    ],
    'hero' => [
        'badge' => '📝 For WordPress Bloggers',
        'h1' => 'Auto-Pin Every WordPress Post to Pinterest',
        'h1_accent' => 'Old Posts, New Traffic',
        'sub' => 'Your archive is full of great posts that barely get visits. We read your WordPress sitemap, turn every post into multiple pins and schedule them — so old content keeps earning traffic.',
        'bullets' => [
            ['🗺️', 'Reads Your WordPress, Yoast or Rank Math Sitemap'],
            ['📚', 'Pin Your Whole Archive — Not Just New Posts'],
            ['🤖', 'AI Writes Pin Titles, Descriptions, Alt Text & Keywords'],
            ['✍️', 'Auto Blog: Write, Publish and Pin Automatically'],
            ['🔌', 'No Plugin, No Code, No Slowdown'],
        ],
        'cta' => 'Start Pinning My Blog — Free',
        'chips' => ['📝 Post pinned', '📈 Old post trending', '🔁 3 pins per post'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Blog Post Link', 'text' => 'See three ready-to-post pins made from your post in seconds.', 'placeholder' => 'https://yourblog.com/your-best-post/'],
    'marquee' => ['How-to posts', 'Listicles', 'Recipes', 'Travel guides', 'DIY tutorials', 'Product reviews', 'Checklists', 'Printables', 'Gift guides', 'Evergreen content'],
    'results' => [
        'title' => 'See the Results: Your Archive, Working Again',
        'text' => 'Search traffic fades after a post is published. Pinterest traffic compounds — every pin you schedule adds to a library that keeps getting discovered.',
        'stats' => [[5, 'x', 'more traffic, up to'], [365, '', 'days of pins in one run'], [3, '', 'pins per post by default'], [500, '+', 'premium pin templates']],
        'alt' => 'Pinterest traffic growth for a WordPress blog over 12 months',
    ],
    'steps' => [
        'title' => 'From WordPress Blog to', 'title_accent' => 'Scheduled Pins',
        'text' => 'Paste your blog link. We find every post, design every pin and schedule a year of content.',
        'items' => [
            ['icon' => '🗺️', 'label' => 'Setup', 'title' => 'Scan Your Blog', 'alt' => 'Scanning a WordPress blog sitemap', 'points' => ['We read your WordPress sitemap and list every post and page.', 'Search your archive and select posts in bulk.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick a Style for Your Blog', 'alt' => 'Choosing pin templates for WordPress blog posts', 'points' => ['Unlimited templates with a live preview using your own post images.', 'Brand colours, unlimited fonts and a free Canva-style editor.', 'AI picks the right template for each post’s topic.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Spread Pins Over the Year', 'alt' => 'Scheduling WordPress post pins over a year', 'points' => ['Several pins per post, a month apart by default.', 'Warm-up mode for new accounts, steady daily pins for established ones.']],
            ['icon' => '🚀', 'label' => 'Approve', 'title' => 'Approve and Keep Writing', 'alt' => 'Approving scheduled blog pins', 'points' => ['Review, edit or remove any pin, then approve.', 'Your pins publish on schedule while you write the next post.']],
        ],
    ],
    'features' => [
        'eyebrow' => 'MADE FOR WORDPRESS',
        'title' => 'Why WordPress Bloggers Automate Pinterest',
        'text' => 'Pinterest is one of the biggest traffic sources for blogs. Automation makes it effortless.',
        'items' => [
            ['🗺️', 'Sitemap-based discovery', 'Works with the built-in WordPress sitemap and popular sitemap plugins — no copy-pasting links.'],
            ['🔌', 'Nothing to install', 'No plugin to slow your site or break on updates.'],
            ['✍️', 'Auto Blog', 'On supported plans, AI can write and publish posts to your WordPress site and pin them automatically.'],
            ['🧠', 'Content-aware copy', 'AI reads each post and writes pin text that matches what readers search.'],
            ['🔁', 'Multiple pins per post', 'Different angles and designs for the same post reach different readers.'],
            ['📈', 'Built for ad & affiliate income', 'More pageviews on the posts that already earn — ads, affiliate links or products.'],
        ],
    ],
    'playbook' => [
        'title' => 'The WordPress Blogger’s Pinterest Routine',
        'text' => 'What consistently grows blog traffic from Pinterest.',
        'tips' => [
            ['Pin your archive first', 'Your older evergreen posts are the easiest wins — they already rank, they just need more visibility.'],
            ['Create several pins per post', 'Three or more designs with different headlines give every post more chances to be found.'],
            ['Use keyword-rich headlines', 'Put the search phrase on the pin image and in the pin title.'],
            ['Keep your boards focused', 'Boards named after your categories help Pinterest understand your blog.'],
            ['Stay consistent', 'Daily pins for months beat big bursts. Schedule once, then keep writing.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Pinning vs Automated WordPress Pins',
        'rows' => [
            ['Finding posts', 'Dig through your archive', 'Every post from your sitemap'],
            ['Pin graphics', 'Design several per post', 'AI-designed from your images'],
            ['Pin copy', 'Write every description', 'AI writes it for you'],
            ['Scheduling', 'Queue by hand each week', 'A year scheduled in one run'],
            ['Site impact', 'Plugins and maintenance', 'Nothing installed'],
        ],
    ],
    'analytics' => [
        'title' => 'See Which Posts Pinterest Readers Love',
        'text' => 'Know which posts get clicks so you can write more of what works.',
        'cards' => ['Track clicks to every post you pin.', 'Remove weak pins to keep engagement strong.', 'Find your top posts on Pinterest.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for a WordPress blog', 'Removing weak blog pins', 'Top blog post pins', 'Blog pin analytics breakdown'],
    ],
    'pricing_title' => 'Plans for Bloggers and Publishers',
    'who' => [
        'title' => 'Made for', 'accent' => 'WordPress publishers',
        'text' => 'Anyone who publishes on WordPress and wants more readers.',
        'cards' => [
            ['Niche bloggers', 'Turn every post into long-lasting Pinterest traffic without extra work.'],
            ['Affiliate & ad-supported sites', 'Send more pageviews to the posts that already earn money.'],
            ['Content agencies', 'Manage Pinterest for multiple WordPress sites with Team Management.'],
        ],
    ],
    'cta_red' => ['Give every WordPress post a second life.', 'Pin your whole archive — automatically.'],
    'faq_title' => 'WordPress + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Do I need a WordPress plugin?', 'No. We read your public posts and sitemap. Nothing is installed on your site.'],
        ['Which sitemaps work?', 'The built-in WordPress sitemap and sitemaps from Yoast, Rank Math, All in One and similar plugins, including sitemap index files.'],
        ['Can I pin old posts, not only new ones?', 'Yes — that’s one of the best uses. Select any posts from your archive and schedule them over the coming months.'],
        ['How many pins can I make per post?', 'Up to 10 per post, each with a different design and headline, spaced out by the gap you choose (30 days by default).'],
        ['What is Auto Blog?', 'On plans that include it, AI writes and publishes articles to your WordPress site and schedules pins for them automatically.'],
        ['Will it pick the right images from my post?', 'It uses your featured image and in-post images, skipping icons, logos and images that are too small or too wide.'],
        ['Can I edit pins before they publish?', 'Yes. Every pin can be edited or removed before you approve. Unapproved runs are saved in Drafts.'],
        ['Is there a free way to try it?', 'Use the free Pin Maker above with no account, or create a free account to schedule.'],
    ],
    'cta_dark' => ['Ready to turn your WordPress blog into a Pinterest traffic machine?', 'Scan once. Pin your whole archive. Keep writing.', 'Start Pinning My Blog →'],
];
uc_render_page($pdo, $user, $uc);
