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
    'slug' => 'mom-blog',
    'name' => 'Mom Blogs',
    'accent' => '#a855f7',
    'order' => ['hero', 'start', 'marquee', 'results_graph', 'steps', 'features', 'playbook', 'compare', 'analytics', 'pricing', 'who', 'testimonials', 'real_results', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'posts',
    'autoblog_niche' => 'mom life, recipes, hacks and printables',
    'meta' => [
        'title' => "Pinterest for Mom Bloggers: Pin on Autopilot | $app",
        'description' => 'Mom blogger? Put Pinterest on autopilot. AI turns your posts, recipes and printables into pins and schedules months of them in 1 click — during nap time.',
        'keywords' => 'Pinterest for mom bloggers, mom blog Pinterest strategy, mom blog traffic, mom life pins, Pinterest scheduler for bloggers, printables pins, busy mom blogging',
    ],
    'hero' => [
        'badge' => '🤱 For Mom Bloggers',
        'h1' => 'Pinterest on Autopilot for Mom Bloggers',
        'h1_accent' => 'Grow While They Nap',
        'sub' => 'Between school runs, dinners and bedtime, there’s no time to design pins. Scan your blog once, and AI turns every post — recipes, hacks, printables, mom life — into pins that go out every day.',
        'bullets' => [['⏱️', 'Set Up in One Nap Time'], ['🍝', 'Recipes, Hacks, Printables & Mom-Life Posts'], ['🤖', 'AI Writes Every Title & Description'], ['✍️', 'Auto Blog Writes & Publishes New Posts for You'], ['📅', 'Months of Pins Scheduled in 1 Click']],
        'cta' => 'Start Pinning My Mom Blog — Free',
        'chips' => ['🤱 Post pinned', '💾 Saved by a mom', '📈 Blog traffic up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Blog Post Link', 'text' => 'See pins made from your post in seconds.', 'placeholder' => 'https://yourmomblog.com/easy-freezer-meals/'],
    'marquee' => ['Freezer meals', 'Mom hacks', 'Morning routines', 'Cleaning schedules', 'Kids crafts', 'Self-care', 'Budget tips', 'Family recipes', 'Printables', 'Postpartum tips'],
    'results' => ['title' => 'See the Results: Traffic That Keeps Up With You', 'text' => 'Your best posts help new moms every year. Pinned consistently, they keep bringing readers — and ad and affiliate income — while you’re busy.', 'stats' => [[5, 'x', 'more traffic, up to'], [365, '', 'days of pins in one run'], [3, '', 'pins per post by default'], [70, '', 'pin templates']], 'alt' => 'Pinterest traffic growth for a mom blog'],
    'steps' => ['title' => 'From Mom Blog to', 'title_accent' => 'Daily Pins', 'text' => 'Four quick steps. Then it runs without you.', 'items' => [
        ['icon' => '📚', 'label' => 'Setup', 'title' => 'Scan Your Blog', 'alt' => 'Scanning a mom blog', 'points' => ['Every post listed automatically.', 'Select all, or pick your favourites.']],
        ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick Your Blog’s Look', 'alt' => 'Choosing pin templates for a mom blog', 'points' => ['Soft, friendly palettes and fonts.', 'Or let AI pick a template per post.']],
        ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Choose Your Pace', 'alt' => 'Scheduling mom blog pins', 'points' => ['A few pins a day, every day.', 'Warm-up mode for new accounts.']],
        ['icon' => '☕', 'label' => 'Approve', 'title' => 'Approve With Your Coffee', 'alt' => 'Approving mom blog pins', 'points' => ['Quick review, one click.', 'Pins publish all month.']],
    ]],
    'features' => ['eyebrow' => 'MADE FOR BUSY MOMS', 'title' => 'Why Mom Bloggers Automate Pinterest', 'text' => 'The most time-saving thing you can do for your blog.', 'items' => [
        ['⏱️', 'Hours back every week', 'No more late-night Canva sessions.'],
        ['🍝', 'Every kind of post', 'Recipes, hacks, printables, routines and product picks.'],
        ['🤖', 'Copy done for you', 'AI writes pin titles, descriptions and alt text.'],
        ['✍️', 'Auto Blog', 'AI writes new posts with images, publishes them and pins them.'],
        ['🗂️', 'Boards handled', 'Pins go to the right board — or a new one.'],
        ['💰', 'More ad & affiliate views', 'More pageviews on the posts that already earn.'],
    ]],
    'playbook' => ['title' => 'A Simple Pinterest Routine for Mom Bloggers', 'text' => 'Minimum time, maximum traffic.', 'tips' => [
        ['Pin your top 20 posts first', 'Start with what already gets traffic.'],
        ['Solve one problem per pin', '“15-Minute Dinners for Busy Moms” beats “What I Ate Today”.'],
        ['Use seasonal moments', 'Back-to-school, holidays and summer break are big for moms.'],
        ['Keep a steady pace', 'A few pins every day beats a big batch once a month.'],
        ['Let Auto Blog fill gaps', 'Keep publishing on busy weeks with AI-written drafts you review.'],
    ]],
    'compare' => ['title' => 'Doing It Yourself vs Autopilot', 'rows' => [['Pin design', 'Late-night Canva', 'Hundreds in 1 click'], ['Posting', 'Whenever you remember', 'Every day, automatically'], ['New posts', 'Written between chores', 'Auto Blog writes, publishes & pins'], ['Your time', 'Hours a week', 'Minutes a month']]],
    'analytics' => ['title' => 'See What Other Moms Save', 'text' => 'Know which posts to update, expand or repeat.', 'cards' => ['Track clicks for every post.', 'Remove weak pins to keep engagement strong.', 'Your top posts at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a mom blog', 'Removing weak mom blog pins', 'Top mom blog pins', 'Mom blog pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'mom bloggers', 'text' => 'Whatever your blog covers.', 'cards' => [['New mom bloggers', 'Get traffic from day one without learning design.'], ['Full-time mom bloggers', 'Grow pageviews for ads and affiliates on autopilot.'], ['Mom-run shops', 'Pin your products and your posts together.']]],
    'cta_red' => ['Grow your blog while they nap.', 'Pins designed, written and scheduled for you.'],
    'faq_title' => 'Mom Blogs + Pinterest — FAQ',
    'faq' => [
        ['How long does setup take?', 'Usually 10–15 minutes: scan your blog, pick a look, set a pace and approve.'],
        ['Will it work for a small blog?', 'Yes. Even a few dozen good posts can bring steady Pinterest traffic when pinned consistently.'],
        ['Can AI write posts for my blog?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them. Review anything about health or safety first.'],
        ['Can I pin printables?', 'Yes — the printable preview in your post becomes the pin image.'],
        ['Is it safe for a new Pinterest account?', 'Yes. The warm-up mode starts slow and grows month by month.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to put your mom blog on autopilot?', 'One setup. Months of pins.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
