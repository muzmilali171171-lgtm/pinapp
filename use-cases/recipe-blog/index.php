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
    'slug' => 'recipe-blog',
    'name' => 'Recipe Blogs',
    'accent' => '#e76f51',
    'order' => ['hero', 'start', 'marquee', 'steps', 'results_graph', 'features', 'playbook', 'compare', 'analytics', 'pricing', 'testimonials', 'real_results', 'who', 'cta_red', 'faq', 'related', 'cta_dark'],
    'power_noun' => 'recipes',
    'autoblog_niche' => 'your recipe niche',
    'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-image-resizer', 'pinterest-alt-text-generator', 'ai-image-creater'],
    'meta' => [
        'title' => "Recipe Blog Pinterest Automation & Recipe Pins | $app",
        'description' => 'Pin every recipe on your blog automatically. Recipe-style pin templates, AI-written titles and a seasonal schedule — hundreds of recipes pinned in 1 click.',
        'keywords' => 'recipe blog Pinterest, recipe pins, Pinterest recipe templates, auto pin recipes, food blogger Pinterest scheduler, recipe blog traffic, seasonal recipe pins',
    ],
    'hero' => [
        'badge' => '🥘 For Recipe Bloggers',
        'h1' => 'Recipe Blog Pinterest Automation',
        'h1_accent' => 'Every Recipe, Pinned Like a Pro',
        'sub' => 'You spend hours testing, shooting and writing each recipe. Let Pinterest send cooks to it for years. We turn every recipe post into classic recipe pins — photo, title band, photo — and schedule your whole index.',
        'bullets' => [['🥪', 'Classic Recipe Pin Layouts — Photo, Title Band, Photo'], ['📚', 'Your Whole Recipe Index Pinned in 1 Click'], ['🍂', 'Seasonal Recipes Scheduled Before They Trend'], ['🤖', 'AI Writes Titles With the Words Cooks Search'], ['🔁', 'Several Fresh Pins per Recipe, Weeks Apart']],
        'cta' => 'Start Pinning My Recipes — Free',
        'chips' => ['🥘 Recipe pinned', '💾 Saved to Dinner Ideas', '📈 Pageviews up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Recipe Post Link', 'text' => 'Get recipe pins from your own photos in seconds.', 'placeholder' => 'https://yourrecipeblog.com/one-pan-lemon-chicken/'],
    'marquee' => ['One-pan dinners', 'Air fryer', 'Slow cooker', 'Meal prep', 'Casseroles', 'Holiday baking', 'Healthy lunches', 'Soups', 'Sheet pan', 'Desserts'],
    'steps' => ['title' => 'From Recipe Index to', 'title_accent' => 'Pinterest Calendar', 'text' => 'Four steps, and your recipe archive keeps working all year.', 'items' => [
        ['icon' => '📚', 'label' => 'Setup', 'title' => 'Scan Your Recipe Index', 'alt' => 'Scanning a recipe blog sitemap', 'points' => ['Every recipe post listed from your sitemap.', 'Search by ingredient, course or season.']],
        ['icon' => '🥪', 'label' => 'Design', 'title' => 'Choose Recipe Pin Styles', 'alt' => 'Choosing recipe pin templates', 'points' => ['Split-photo and title-band templates made for food.', 'Two photos from the same recipe in one pin.']],
        ['icon' => '🗓️', 'label' => 'Schedule', 'title' => 'Build Your Seasonal Calendar', 'alt' => 'Scheduling recipe pins by season', 'points' => ['Start dates set weeks before each season.', 'Pins per recipe spaced a month apart.']],
        ['icon' => '🍽️', 'label' => 'Approve', 'title' => 'Approve and Get Back to Testing', 'alt' => 'Approving recipe pins', 'points' => ['Edit any title or photo.', 'Approve — pins publish on schedule.']],
    ]],
    'results' => ['title' => 'See the Results: A Recipe Archive That Earns', 'text' => 'Recipe pins keep getting saved into boards and resurfacing for years. The more recipes you pin, the more cooks find you.', 'stats' => [[5, 'x', 'more traffic, up to'], [365, '', 'days of recipe pins at once'], [70, '', 'pin templates'], [20, '/day', 'pins at full pace']], 'alt' => 'Pinterest traffic growth for a recipe blog'],
    'features' => ['eyebrow' => 'BUILT FOR RECIPE BLOGGERS', 'title' => 'Everything a Recipe Blog Needs on Pinterest', 'text' => 'Designed around the pins that win in the food category.', 'items' => [
        ['🥪', 'Recipe pin templates', 'Photo-band-photo, framed titles and roundup numbers.'],
        ['🧾', 'Works with recipe cards', 'WP Recipe Maker, Tasty Recipes and others — we read the page like a visitor.'],
        ['📸', 'Your best shots', 'Uses your recipe photos and skips ads, icons and tiny images.'],
        ['🔤', 'Search-first titles', 'Easy, quick, one-pan, healthy — AI uses the words cooks type.'],
        ['🔁', 'Fresh pins, same recipe', 'New designs for proven recipes keep them in feeds.'],
        ['✍️', 'Auto Blog', 'AI writes recipe posts with images from your titles and pins them.'],
    ]],
    'playbook' => ['title' => 'Recipe Blog Pinterest Strategy', 'text' => 'What top recipe bloggers do on Pinterest.', 'tips' => [
        ['Pin every recipe, not just new ones', 'Your archive is your biggest traffic asset.'],
        ['Use a vertical, close-up hero shot', 'Texture sells — melty, crispy, saucy.'],
        ['Put the dish name on the pin', '“Creamy Tuscan Chicken” should be readable at a glance.'],
        ['Create roundups', '“25 Easy Weeknight Dinners” pins link to one post that links to many.'],
        ['Pin seasonally, 45–60 days early', 'Soups in September, cookies in October, grilling in April.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Recipe Pins', 'rows' => [['Pin design', 'Photoshop per recipe', 'Hundreds of recipes in 1 click'], ['Titles', 'Rewritten by hand', 'AI writes cook-friendly titles'], ['Seasonal timing', 'Too late', 'Scheduled ahead'], ['New recipe posts', 'Written one by one', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'Know Your Most-Saved Recipes', 'text' => 'Plan your next recipes around what Pinterest cooks love.', 'cards' => ['Track clicks for every recipe pin.', 'Remove weak pins to keep engagement strong.', 'Your top recipes at a glance.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a recipe blog', 'Removing weak recipe pins', 'Top recipe pins', 'Recipe pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'recipe bloggers', 'text' => 'New blogs to big recipe sites.', 'cards' => [['New recipe blogs', 'Get Pinterest traffic from your first 20 recipes.'], ['Established food bloggers', 'Keep hundreds of recipes pinned consistently.'], ['Recipe publishers', 'Pin thousands of recipes without a bigger team.']]],
    'cta_red' => ['Let Pinterest send cooks to every recipe.', 'Your whole recipe index, pinned automatically.'],
    'faq_title' => 'Recipe Blogs + Pinterest — FAQ',
    'faq' => [
        ['How is this different from the food website page?', 'This page is for recipe bloggers pinning a recipe archive; the templates and steps are the same, tuned for recipe posts and recipe cards.'],
        ['Does it work with recipe card plugins?', 'Yes. We read the page as visitors see it, so recipe cards from WP Recipe Maker, Tasty Recipes and others work.'],
        ['Can I pin hundreds of recipes at once?', 'Yes — select them all and approve. AI designs, writes and schedules every pin.'],
        ['Can Auto Blog write recipes?', 'On plans with Auto Blog, AI writes posts with images from your titles and publishes them. Test recipes yourself before presenting them as tested.'],
        ['Which pin sizes work best for recipes?', '2:3 (1000 × 1500) is the safe default; long 1:2.1 pins also work well for recipes.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to pin your whole recipe index?', 'Hundreds of recipe pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
