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
    'slug' => 'food-website',
    'power_noun' => 'recipes',
    'autoblog_niche' => 'recipes and cooking',
    'name' => 'Food & Recipe Websites',
    'accent' => '#f59e0b',
    'order' => ['hero', 'start', 'marquee', 'results_graph', 'features', 'steps', 'playbook', 'pricing', 'compare', 'analytics', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'meta' => [
        'title' => "Pinterest for Food Bloggers: Auto-Pin Recipes | $app",
        'description' => "Automate Pinterest for your recipe website. Turn every recipe into scroll-stopping recipe pins with AI-written titles, then schedule months of pins in minutes.",
        'keywords' => 'Pinterest for food bloggers, recipe pins, auto pin recipes, food blog Pinterest strategy, Pinterest recipe traffic, recipe pin templates, food website Pinterest automation',
    ],
    'hero' => [
        'badge' => '🍝 For Food Bloggers & Recipe Sites',
        'h1' => 'Pinterest Automation for Food & Recipe Websites',
        'h1_accent' => 'Recipes People Save',
        'sub' => 'Pinterest is the home of recipe search. Turn every recipe on your site into mouth-watering pins — top-and-bottom photo layouts, bold titles, the works — and schedule them for months.',
        'bullets' => [
            ['📸', 'Recipe Pins Made From Your Food Photos'],
            ['🥘', 'Recipe-Style Templates: Split Photos, Bands & Number Lists'],
            ['🤖', 'AI Writes Craveable, Searchable Recipe Titles'],
            ['🎃', 'Seasonal Recipes Scheduled Ahead of Time'],
            ['📚', 'Pin Your Whole Recipe Index in One Run'],
        ],
        'cta' => 'Start Pinning My Recipes — Free',
        'chips' => ['🍝 Recipe pinned', '💾 Saved 120 times', '📈 Blog traffic up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Recipe Link, Get Recipe Pins', 'text' => 'We grab your recipe photos and design pins the way food bloggers do.', 'placeholder' => 'https://yourfoodblog.com/creamy-chicken-penne/'],
    'marquee' => ['Easy dinners', 'Casserole recipes', 'Healthy meals', 'Desserts', 'Air fryer recipes', 'Meal prep', 'Slow cooker', 'Holiday baking', 'Keto recipes', 'Breakfast ideas'],
    'results' => [
        'title' => 'See the Results: Recipes That Rise Every Season',
        'text' => 'Recipe pins are among the most saved content on Pinterest, and seasonal recipes come back every year. Consistent pinning builds a library that keeps sending cooks your way.',
        'stats' => [[500, '+', 'premium pin templates incl. recipe styles'], [5, 'x', 'more traffic, up to'], [365, '', 'days of recipe pins at once'], [100, '%', 'free design editor, no Canva Pro']],
        'alt' => 'Pinterest traffic growth for a recipe website',
    ],
    'features' => [
        'eyebrow' => 'MADE FOR RECIPE PINS',
        'title' => 'Everything a Food Blog Needs on Pinterest',
        'text' => 'Designed around the pin styles that actually get saved in the food category.',
        'items' => [
            ['🥪', 'Recipe-style templates', 'Top-and-bottom photo layouts with a bold title band — the classic recipe pin look.'],
            ['🔢', 'Roundup templates', 'Big-number designs for “25 Dump-and-Bake Casseroles” style posts.'],
            ['📸', 'Your food photos', 'Pins use the photos in your recipe post, skipping tiny images and ads.'],
            ['🧂', 'Craveable copy', 'AI writes titles with the words cooks search: easy, quick, one-pan, healthy.'],
            ['🍂', 'Seasonal scheduling', 'Pin pumpkin, grilling and holiday recipes before the season starts.'],
            ['🗂️', 'Recipe boards', 'Recipes go to boards like Dinner Ideas, Desserts or Air Fryer — or AI creates them.'],
        ],
    ],
    'steps' => [
        'title' => 'From Recipe Post to', 'title_accent' => 'Recipe Pin',
        'text' => 'Scan your recipe index, pick your food-pin style and schedule the season.',
        'items' => [
            ['icon' => '🍳', 'label' => 'Setup', 'title' => 'Scan Your Recipe Site', 'alt' => 'Scanning a recipe website sitemap', 'points' => ['We list every recipe from your sitemap.', 'Search “chicken”, “dessert” or “air fryer” and select in bulk.']],
            ['icon' => '🎨', 'label' => 'Design', 'title' => 'Choose Recipe Pin Styles', 'alt' => 'Choosing recipe pin templates', 'points' => ['Split-photo and title-band templates made for food.', 'Collage layouts use two photos from the same recipe.']],
            ['icon' => '⚙️', 'label' => 'Schedule', 'title' => 'Plan Your Recipe Calendar', 'alt' => 'Scheduling recipe pins by season', 'points' => ['Several pins per recipe, spaced a month apart.', 'Warm-up mode for new accounts.']],
            ['icon' => '🍽️', 'label' => 'Approve', 'title' => 'Approve and Get Cooking', 'alt' => 'Approving scheduled recipe pins', 'points' => ['Edit any title or photo, then approve.', 'Recipes publish on schedule while you test the next one.']],
        ],
    ],
    'playbook' => [
        'title' => 'Pinterest Tips for Food Bloggers',
        'text' => 'The small details that make recipe pins get saved.',
        'tips' => [
            ['Lead with the best photo', 'Close-up, well-lit, texture you can almost taste. The first photo decides the save.'],
            ['Put the dish name on the pin', 'Clear, readable titles like “Creamy Chicken Penne” beat clever ones.'],
            ['Use words cooks search', 'Easy, quick, 30-minute, one-pan, healthy — include them where they’re true.'],
            ['Pin seasonal recipes early', 'Thanksgiving recipes start trending in September. Schedule ahead.'],
            ['Refresh your top recipes', 'New pin designs for proven recipes bring them back into feeds.'],
        ],
    ],
    'compare' => [
        'title' => 'Manual Recipe Pins vs Automation',
        'rows' => [
            ['Designing recipe pins', 'Photoshop or Canva per recipe', 'Recipe templates applied automatically'],
            ['Pin titles', 'Written by hand', 'AI writes searchable titles'],
            ['Seasonal timing', 'Often too late', 'Scheduled weeks ahead'],
            ['Your recipe archive', 'Only new recipes promoted', 'Every recipe pinned'],
        ],
    ],
    'analytics' => [
        'title' => 'Know Which Recipes Pinterest Craves',
        'text' => 'See which recipes get clicks and saves, and plan your next recipes around them.',
        'cards' => ['Track clicks for every recipe pin.', 'Remove weak pins to keep engagement strong.', 'See your most popular recipes on Pinterest.', 'Compare by board, URL, keyword, title and time.'],
        'alts' => ['Pinterest analytics for a recipe website', 'Removing weak recipe pins', 'Top recipe pins', 'Recipe pin analytics breakdown'],
    ],
    'pricing_title' => 'Plans for Food Bloggers',
    'who' => [
        'title' => 'Made for', 'accent' => 'food creators',
        'text' => 'From new recipe blogs to big food publishers.',
        'cards' => [
            ['Recipe bloggers', 'Turn every recipe into steady Pinterest traffic while you cook and shoot.'],
            ['Meal-plan & diet sites', 'Keto, vegan, meal prep — reach the exact people searching for your recipes.'],
            ['Food publishers', 'Pin thousands of recipes consistently without growing the team.'],
        ],
    ],
    'cta_red' => ['Your recipes belong on Pinterest.', 'Beautiful recipe pins, scheduled automatically.'],
    'faq_title' => 'Food Blogs + Pinterest — Frequently Asked Questions',
    'faq' => [
        ['Do the templates look like real recipe pins?', 'Yes. The template library includes food-style layouts — photos above and below a title band, framed title boxes and big-number roundup designs — the styles that get saved in the food category.'],
        ['Which photos are used?', 'The photos in your recipe post. Images that are too small or too wide (like banners and ads) are skipped automatically.'],
        ['Does it work with recipe card plugins?', 'Yes. We read the recipe page as a visitor sees it, so WP Recipe Maker, Tasty Recipes and other recipe cards work fine.'],
        ['Can I pin recipe roundups?', 'Yes — number templates like “25 Easy Casseroles” are made for roundup posts.'],
        ['How far ahead should I pin seasonal recipes?', 'About 45–60 days before the season or holiday. Set your first publish date and pin gap accordingly.'],
        ['Can I choose the board for each recipe?', 'Yes, or let AI pick — and create new boards like “Air Fryer Recipes” when needed.'],
        ['Is it free to try?', 'Yes. Use the free Pin Maker above, or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your recipes saved on Pinterest?', 'Scan your recipe index and schedule the season in minutes.', 'Pin My Recipes →'],
];
uc_render_page($pdo, $user, $uc);
