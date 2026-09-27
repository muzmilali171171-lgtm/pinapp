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
    'slug' => 'drinks-website',
    'name' => 'Drinks & Cocktail Websites',
    'accent' => '#ff7a59',
    'power_noun' => 'drink recipes',
    'autoblog_niche' => 'cocktails, mocktails and coffee drinks',
    'meta' => [
        'title' => "Pinterest for Cocktail & Drink Recipe Sites | $app",
        'description' => 'Automate Pinterest for your drinks website. Turn cocktail, mocktail, smoothie and coffee recipes into pins people save, and schedule months of pins in 1 click.',
        'keywords' => 'Pinterest for cocktail blogs, drink recipe pins, cocktail Pinterest marketing, mocktail recipes Pinterest, coffee recipe pins, smoothie recipe Pinterest, drinks blog traffic',
    ],
    'hero' => [
        'badge' => '🍹 For Cocktail, Mocktail & Coffee Sites',
        'h1' => 'Pinterest Automation for Drinks & Cocktail Websites',
        'h1_accent' => 'Recipes Worth a Toast',
        'sub' => 'Cocktails, mocktails, smoothies, iced coffee — drink recipes are some of the most-saved pins for parties and holidays. Turn every recipe into bright, glass-clinking pins and schedule them ahead of every season.',
        'bullets' => [['🍸', 'Drink Pins Made From Your Own Recipe Photos'], ['🎉', 'Party & Holiday Drinks Scheduled Weeks Ahead'], ['🔢', 'Roundup Templates for “25 Summer Cocktails” Posts'], ['🤖', 'AI Writes Flavour-Rich Titles & Descriptions'], ['🗂️', 'Boards by Spirit, Season and Occasion']],
        'cta' => 'Start Pinning My Drink Recipes — Free',
        'chips' => ['🍹 Cocktail pinned', '💾 Saved to Party Drinks', '📈 Recipe visits up'],
    ],
    'start' => ['label' => '✨ FREE PIN MAKER · NO SIGN-UP', 'title' => 'Paste a Drink Recipe Link', 'text' => 'We use your drink photos to design pins in seconds.', 'placeholder' => 'https://yourdrinksblog.com/spicy-margarita/'],
    'marquee' => ['Summer cocktails', 'Mocktails', 'Iced coffee', 'Smoothies', 'Holiday punch', 'Margaritas', 'Spritz recipes', 'Batch cocktails', 'Hot chocolate', 'Wine pairings'],
    'results' => ['title' => 'See the Results: Drinks That Trend Every Season', 'text' => 'Drink searches spike before summer, holidays and game days — and come back every year. Pin early and consistently and your recipes ride each wave.', 'stats' => [[5, 'x', 'more traffic, up to'], [500, '+', 'premium pin templates'], [365, '', 'days of pins in one run'], [100, '%', 'free design editor, no Canva Pro']], 'alt' => 'Pinterest traffic growth for a cocktail recipe website'],
    'steps' => ['title' => 'From Drink Recipe to', 'title_accent' => 'Scheduled Pin', 'text' => 'Scan your recipes, pick a bright style, and schedule the season.', 'items' => [
        ['icon' => '🍸', 'label' => 'Setup', 'title' => 'Scan Your Drink Recipes', 'alt' => 'Scanning a drinks website for recipes', 'points' => ['Every cocktail, mocktail and coffee recipe listed from your sitemap.', 'Search “margarita” or “christmas” and select in bulk.']],
        ['icon' => '🎨', 'label' => 'Design', 'title' => 'Pick Bright, Party-Ready Designs', 'alt' => 'Choosing colourful pin templates for drinks', 'points' => ['Vivid palettes and bold fonts that pop in the feed.', 'Collages show the drink, the garnish and the batch.']],
        ['icon' => '📅', 'label' => 'Schedule', 'title' => 'Schedule Ahead of Every Season', 'alt' => 'Scheduling seasonal drink pins', 'points' => ['Set your first publish date 6–8 weeks before the season.', 'Several pins per recipe, spaced weeks apart.']],
        ['icon' => '🥂', 'label' => 'Approve', 'title' => 'Approve and Cheers', 'alt' => 'Approving drink recipe pins', 'points' => ['Edit any pin, then approve.', 'Pins publish while you shake the next recipe.']],
    ]],
    'features' => ['eyebrow' => 'MADE FOR DRINK RECIPES', 'title' => 'Why Drink Bloggers Automate Pinterest', 'text' => 'Pinterest is where people plan parties, brunches and holiday menus.', 'items' => [
        ['🍹', 'Glass-first designs', 'Templates that keep the drink the hero — bold title, clean frame.'],
        ['🔢', 'Roundup pins', 'Number templates for “20 Easy Mocktails” style posts.'],
        ['🎄', 'Season planner', 'Summer, Halloween, Christmas and New Year drinks pinned before demand peaks.'],
        ['🍋', 'Flavour-led copy', 'AI writes titles with the words people search: easy, refreshing, batch, 3-ingredient.'],
        ['🗂️', 'Occasion boards', 'Drinks sorted onto boards like Brunch Drinks or Holiday Cocktails.'],
        ['✍️', 'Auto Blog', 'AI writes new drink recipe posts with images and pins them for you.'],
    ]],
    'playbook' => ['title' => 'Pinterest Tips for Drink Recipe Sites', 'text' => 'Small details that make drink pins get saved.', 'tips' => [
        ['Shoot against a clean background', 'Colourful drinks read best on simple, light backgrounds.'],
        ['Name the drink and the vibe', '“Spicy Pineapple Margarita for Summer Parties” beats “My Favourite Marg”.'],
        ['Pin non-alcoholic versions too', 'Mocktail and zero-proof searches keep growing year-round.'],
        ['Pin seasonal drinks early', 'Holiday drink searches begin 6–8 weeks before the day.'],
        ['Make batch recipes shine', 'Pitcher and punch recipes are saved for parties again and again.'],
    ]],
    'compare' => ['title' => 'Manual vs Automated Drink Pins', 'rows' => [['Designing pins', 'One recipe at a time', 'Hundreds of recipes in 1 click'], ['Seasonal timing', 'Often too late', 'Scheduled weeks ahead'], ['Pin copy', 'Written by hand', 'AI writes flavour-rich copy'], ['New recipes', 'Write every post yourself', 'Auto Blog writes, publishes & pins']]],
    'analytics' => ['title' => 'See Which Drinks Pinterest Craves', 'text' => 'Know which recipes get saved so you can mix more of them.', 'cards' => ['Track clicks and saves for every drink pin.', 'Remove weak pins to keep engagement high.', 'Spot your most popular drinks.', 'Compare by board, URL, keyword, title and time.'], 'alts' => ['Pinterest analytics for a drinks site', 'Removing weak drink pins', 'Top drink recipe pins', 'Drink pin analytics breakdown']],
    'who' => ['title' => 'Made for', 'accent' => 'drink creators', 'text' => 'Home bartenders to beverage brands.', 'cards' => [['Cocktail bloggers', 'Keep every recipe in front of party planners, all year.'], ['Coffee & smoothie sites', 'Daily-drink recipes that people save for their routine.'], ['Beverage brands', 'Pin recipes that feature your products.']]],
    'cta_red' => ['Your drink recipes deserve a toast.', 'Pin them all — automatically, ahead of every season.'],
    'faq_title' => 'Drink Websites + Pinterest — FAQ',
    'faq' => [
        ['Can I promote cocktail recipes on Pinterest?', 'Yes. Alcohol-related recipes are allowed on Pinterest for adult audiences; follow Pinterest’s policies for alcohol content and your local rules.'],
        ['Which templates work best for drinks?', 'Clean framed layouts and bold title bands keep the glass the focus; number templates suit roundups.'],
        ['Can AI create pins for all my recipes at once?', 'Yes. Select hundreds of recipes and approve once — AI designs, writes and schedules every pin.'],
        ['Can Auto Blog write new drink recipes?', 'On plans that include Auto Blog, AI writes articles with images from your titles, publishes them and schedules pins. Always test and adjust recipes before publishing them as your own.'],
        ['When should I pin holiday drinks?', 'About 6–8 weeks before the holiday. Set your first publish date accordingly.'],
        ['Is it free to try?', 'Yes — use the free Pin Maker above or create a free account.'],
    ],
    'cta_dark' => ['Ready to get your drink recipes saved?', 'Hundreds of drink pins in one click.', 'Sign Up Free →'],
];
uc_render_page($pdo, $user, $uc);
