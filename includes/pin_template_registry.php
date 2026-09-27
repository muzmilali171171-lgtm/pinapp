<?php
require_once __DIR__ . '/pin_templates_60.php';
require_once __DIR__ . '/pin_templates_100.php';
require_once __DIR__ . '/pin_templates_300.php';
/**
 * One list of every pin template/style: key, display name, category and layout.
 * Drives the "Pin Templates & Styles" picker (pin-templates.php → assets/js/template-picker.js),
 * the preview images (pin-template-preview.php) and the "AI Auto" choice (pin_pick_auto_style()).
 *
 * layout: 'single' = designed for one photo, 'collage' = designed for several photos
 * (every template still works with any number of photos).
 */
function pin_template_registry(): array
{
    static $list = null;
    if ($list !== null) return $list;
    $rows = [
        // key, name, category, layout
        ['high_attractive_multi', 'High Attractive Multi Colored', 'General', 'single'],
        ['simple', 'Simply', 'General', 'single'],
        ['simple2', 'Simple 2', 'General', 'single'],
        ['unique_multi', 'Unique Multi Colored', 'General', 'single'],
        ['hairstyles_simple', 'Hairstyles Simple', 'Beauty & Hair', 'single'],
        ['recipe_food', 'Recipe Food', 'Food & Recipes', 'single'],
        ['recipe_food2', 'Recipe Food 2', 'Food & Recipes', 'single'],
        ['recipe_food3', 'Outlined Number Band', 'Food & Recipes', 'collage'],
        ['recipe_food4', 'Gold Number Split', 'Food & Recipes', 'collage'],
        ['recipe_food5', 'Green Block Title', 'Food & Recipes', 'single'],
        ['recipe_food6', 'Boxed Title Split', 'Food & Recipes', 'collage'],
        ['recipe_food7', 'Brush Label', 'Food & Recipes', 'single'],
        ['recipe_food8', 'Bold Outline Grid', 'Food & Recipes', 'collage'],
        ['recipe_food9', 'Black Band Label', 'Food & Recipes', 'collage'],
        ['recipe_food10', 'Framed Card', 'Food & Recipes', 'collage'],
        ['tpl_hero_row', 'Hero + Photo Row', 'Food & Recipes', 'collage'],
        ['pet_recipe', 'Pet Recipe Foods', 'Pets', 'single'],
        ['home_decor', 'Home Decor', 'Home Decor', 'single'],
        ['home_decor2', 'Home Decor 2', 'Home Decor', 'single'],
        ['home_decor3', 'Hand-Lettered Grid', 'Home Decor', 'collage'],
        ['home_decor4', 'Dark Badge Grid', 'Home Decor', 'collage'],
        ['home_decor5', 'Script Overlay', 'Home Decor', 'single'],
        ['home_decor6', 'Badge + Button Grid', 'Home Decor', 'collage'],
        ['tpl_mosaic', 'Asymmetric Mosaic', 'Home Decor', 'collage'],
        ['fashion_outfits', 'Fashion Outfits', 'Fashion', 'single'],
        ['fashion_outfits2', 'Fashion Outfits 2', 'Fashion', 'single'],
        ['fashion_outfits3', 'Fashion Outfits 3', 'Fashion', 'collage'],
        ['tpl_label_stack', 'Tilted Label Stack', 'Fashion', 'single'],
        ['tpl_highlight_lines', 'Highlight Lines', 'Fashion', 'single'],
        ['tpl_side_stack', 'Side Stack Pink', 'Fashion', 'single'],
        ['tpl_top_panel', 'Top Panel + Arrow', 'Fashion', 'single'],
        ['tpl_cream_band', 'Cream Band Collage', 'Fashion', 'collage'],
        ['tpl_half_circle', 'Half-Circle Number', 'Fashion', 'collage'],
        ['tpl_rounded_tiles', 'Rounded Tiles Scrapbook', 'Fashion', 'collage'],
        ['tpl_magazine_band', 'Magazine Grid Band', 'Fashion', 'collage'],
        ['tpl_film_strip', 'Film Strip', 'Fashion', 'collage'],
        ['tpl_soft_pills', 'Soft Pink Pills', 'Beauty & Hair', 'single'],
        ['tpl_three_strips', 'Three Strips + Pill', 'Beauty & Hair', 'collage'],
        ['tpl_postcard', 'Postcard', 'Travel', 'single'],
        ['tpl_polaroid_scatter', 'Polaroid Scatter', 'Travel', 'collage'],
        ['tpl_diagonal_band', 'Diagonal Band', 'Fitness', 'single'],
        ['tpl_slant_split', 'Slanted Split', 'Fitness', 'collage'],
        ['tpl_kraft_tag', 'Kraft Gift Tag', 'DIY & Crafts', 'single'],
        ['tpl_kraft_board', 'Kraft Board', 'DIY & Crafts', 'collage'],
        ['tpl_elegant_arch', 'Elegant Arch', 'Wedding', 'single'],
        ['tpl_framed_trio', 'Framed Trio', 'Wedding', 'collage'],
        ['tpl_ribbon_banner', 'Ribbon Banner', 'Holidays', 'single'],
        ['tpl_circle_trio', 'Circle Trio', 'Holidays', 'collage'],
        ['tpl_top_title_bar', 'Top Title Bar', 'Lifestyle', 'single'],
        ['tpl_nine_grid', 'Nine Grid', 'Lifestyle', 'collage'],
        ['tpl_sticky_note', 'Sticky Note', 'Tips & Business', 'single'],
        ['tpl_corner_card', 'Corner Card', 'Garden', 'single'],
        ['tpl_bubble_badge', 'Bubble Badge', 'Kids & Parenting', 'single'],
    ];
    // Photos each collage template is built from (one AI image per cell, all different).
    $photos = [
        'recipe_food3' => 2, 'recipe_food4' => 2, 'recipe_food6' => 2, 'recipe_food9' => 2, 'recipe_food10' => 2,
        'recipe_food8' => 4, 'home_decor3' => 4, 'home_decor4' => 4, 'home_decor6' => 4, 'fashion_outfits3' => 4,
        'tpl_cream_band' => 4, 'tpl_half_circle' => 4, 'tpl_rounded_tiles' => 9, 'tpl_magazine_band' => 9, 'tpl_film_strip' => 3,
        'tpl_polaroid_scatter' => 4, 'tpl_three_strips' => 3, 'tpl_slant_split' => 2, 'tpl_framed_trio' => 3, 'tpl_hero_row' => 4,
        'tpl_mosaic' => 3, 'tpl_kraft_board' => 4, 'tpl_circle_trio' => 3, 'tpl_nine_grid' => 8,
    ];
    $list = [];
    foreach ($rows as [$k, $n, $c, $l]) {
        $list[$k] = ['key' => $k, 'name' => $n, 'category' => $c, 'layout' => $l, 'photos' => $l === 'collage' ? ($photos[$k] ?? 4) : 1];
    }
    // 60 preset templates from pin_templates_60.php
    if (function_exists('pt2_presets')) {
        foreach (pt2_all_presets() as $k => [$n, $c, $l, $ph]) {
            $list[$k] = ['key' => $k, 'name' => $n, 'category' => $c, 'layout' => $l, 'photos' => $l === 'collage' ? $ph : 1];
        }
    }
    // older templates used a shorter category list — map them onto the current one
    $remap = ['Beauty & Hair' => 'Beauty', 'Pets' => 'Pets & Animals', 'Garden' => 'Gardening', 'Kids & Parenting' => 'Parenting',
        'Tips & Business' => 'Business', 'Holidays' => 'Seasonal & Holiday Ideas', 'General' => 'Lifestyle'];
    foreach ($list as $k => $t) {
        if (isset($remap[$t['category']])) $list[$k]['category'] = $remap[$t['category']];
    }
    $list['hairstyles_simple']['category'] = 'Hairstyles';
    return $list;
}

/** How many AI photos a template needs (1 = single-photo template). */
function pin_template_image_count(string $key): int
{
    $reg = pin_template_registry();
    return $reg[$key]['photos'] ?? 1;
}

function pin_template_categories(): array
{
    $cats = [];
    // the main category list (same order as the user-facing list), then anything else in use
    if (function_exists('pt4_themes')) foreach (pt4_themes() as $t) $cats[$t[0]] = false;
    foreach (pin_template_registry() as $t) $cats[$t['category']] = true;
    return array_keys(array_filter($cats));
}

/** Which category a title belongs to (for "AI Auto"). */
function pin_template_category_for_title(string $title): string
{
    $t = ' ' . strtolower($title) . ' ';
    // most specific first
    $map = [
        'Tattoo Ideas' => ['tattoo'],
        'Bridal Ideas' => ['bridal', 'bride', 'bridesmaid'],
        'Wedding' => ['wedding', 'engagement', 'reception'],
        'Party Ideas' => ['party', 'birthday', 'baby shower', 'bachelorette'],
        'Gift Ideas' => ['gift', 'present', 'stocking stuffer'],
        'Seasonal & Holiday Ideas' => ['christmas', 'halloween', 'thanksgiving', 'easter', 'valentine', 'holiday', 'fall ', 'autumn', 'winter', 'summer', 'spring', 'new year', 'fourth of july'],
        'Desserts' => ['dessert', 'cake', 'cookie', 'brownie', 'cupcake', 'pie ', 'ice cream', 'cheesecake', 'fudge'],
        'Baking' => ['bake', 'baking', 'bread', 'sourdough', 'muffin', 'scone', 'biscuit'],
        'Recipes & Meal Planning' => ['meal plan', 'meal prep', 'weekly menu', 'grocery'],
        'Food & Recipes' => ['recipe', 'dinner', 'lunch', 'breakfast', 'casserole', 'pasta', 'chicken', 'soup', 'salad', 'meal', 'snack', 'crockpot', 'slow cooker', 'air fryer', 'smoothie', 'drink', 'cocktail', 'beef', 'shrimp'],
        'Hairstyles' => ['hair', 'bob ', 'haircut', 'braid', 'bangs', 'curls', 'ponytail'],
        'Makeup' => ['makeup', 'lipstick', 'eyeshadow', 'eyeliner', 'contour', 'foundation'],
        'Skincare' => ['skincare', 'skin care', 'serum', 'acne', 'moisturizer', 'glow'],
        'Beauty' => ['beauty', 'nails', 'manicure', 'eyebrow', 'perfume'],
        'Fitness & Workout' => ['workout', 'exercise', 'abs', 'hiit', 'squat', 'dumbbell'],
        'Fitness' => ['fitness', 'gym', 'running', 'weight loss', 'pilates', 'yoga'],
        'Health & Wellness' => ['health', 'wellness', 'self care', 'self-care', 'meditation', 'sleep', 'stress', 'mindful', 'detox', 'anxiety'],
        'Fashion' => ['outfit', 'wear', 'style', 'dress', 'jeans', 'pants', 'sweater', 'coat', 'shoes', 'boots', 'wardrobe', 'fashion', 'wool', 'jacket', 'skirt'],
        'Interior Design' => ['interior', 'design ideas', 'layout', 'color palette', 'minimalist home'],
        'Home Decor' => ['decor', 'living room', 'bedroom', 'kitchen', 'bathroom', 'home ', 'wall', 'shelf', 'porch', 'entryway'],
        'Organization & Productivity' => ['organiz', 'declutter', 'storage', 'productivity', 'planner', 'routine', 'habit', 'cleaning'],
        'Plants' => ['plant', 'succulent', 'houseplant', 'monstera', 'fern'],
        'Gardening' => ['garden', 'flower', 'backyard', 'patio', 'vegetable', 'grow '],
        'Pets & Animals' => [' dog', ' cat ', ' cats', 'puppy', 'kitten', ' pet', 'animal', 'bird', 'horse'],
        'Travel' => ['travel', 'trip', 'vacation', 'beach', 'itinerary', 'destinations', 'visit', 'road trip', 'hotel'],
        'Photography' => ['photo', 'camera', 'lighting', 'portrait', 'lens'],
        'Kids Activities' => ['kids activit', 'crafts for kids', 'toddler', 'preschool', 'rainy day'],
        'Parenting' => ['parenting', 'baby', 'mom ', 'moms', 'children', 'kids'],
        'Education' => ['learn', 'study', 'school', 'teacher', 'classroom', 'homeschool', 'student'],
        'Quotes & Motivation' => ['quote', 'motivation', 'inspiration', 'affirmation', 'mindset'],
        'Entrepreneurship' => ['entrepreneur', 'startup', 'side hustle', 'small business'],
        'Marketing' => ['marketing', 'seo', 'social media', 'instagram', 'pinterest', 'email list'],
        'Blogging' => ['blog', 'blogging', 'content creator'],
        'Money & Finance' => ['money', 'budget', 'saving', 'invest', 'debt', 'finance', 'frugal'],
        'Business' => ['business', 'career', 'resume', 'interview', 'leadership'],
        'Gadgets' => ['gadget', 'headphones', 'smartwatch', 'phone case'],
        'Technology' => ['tech', ' ai ', 'app', 'software', 'iphone', 'android', 'laptop', 'computer'],
        'Digital Art' => ['digital art', 'procreate', 'ipad art'],
        'Graphic Design' => ['graphic design', 'logo', 'font', 'typography', 'branding'],
        'Drawing & Sketching' => ['drawing', 'sketch', 'doodle', 'pencil'],
        'Art' => ['art', 'painting', 'watercolor', 'canvas'],
        'DIY & Crafts' => ['diy', 'craft', 'handmade', 'crochet', 'knit', 'sew', 'cricut'],
        'Cars & Automobiles' => [' car ', ' cars', 'truck', 'suv', 'automobile', 'vehicle'],
        'Architecture' => ['architecture', 'building', 'house design', 'facade'],
        'Books & Reading' => ['book', 'reading', 'novel', 'read '],
        'Movies & TV' => ['movie', 'film', ' tv ', 'series', 'netflix', 'show'],
        'Music' => ['music', 'song', 'playlist', 'concert', 'guitar', 'piano'],
        'Gaming' => ['game', 'gaming', 'minecraft', 'playstation', 'xbox', 'nintendo'],
    ];
    foreach ($map as $cat => $needles) foreach ($needles as $n) if (strpos($t, $n) !== false) return $cat;
    return 'Lifestyle';
}

/**
 * "AI Auto": picks a template in the title's category (varied per title), optionally only from $pool.
 * $pool = the templates the user multi-selected; empty = all templates.
 */
function pin_pick_auto_style(string $title, array $pool = []): string
{
    $reg = pin_template_registry();
    $pool = array_values(array_filter($pool, fn($k) => isset($reg[$k])));
    $cat = pin_template_category_for_title($title);
    $candidates = [];
    foreach (($pool ?: array_keys($reg)) as $k) if ($reg[$k]['category'] === $cat) $candidates[] = $k;
    if (!$candidates && $pool) $candidates = $pool;
    if (!$candidates) foreach ($reg as $k => $t) if (in_array($t['category'], ['General', 'Lifestyle'], true)) $candidates[] = $k;
    return $candidates[abs(crc32($title)) % count($candidates)];
}

/**
 * Resolves a stored image_style value to ONE template for this pin:
 *   "auto"                      -> AI Auto over all templates
 *   "tpl_postcard"              -> that template
 *   "tpl_postcard,recipe_food3" -> a random pick from the user's multi-selection
 *   "auto,tpl_a,tpl_b"          -> AI Auto restricted to the selected templates
 */
function pin_resolve_style(string $value, string $title): string
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn($p) => $p !== ''));
    if (!$parts) return pin_pick_auto_style($title);
    $auto = in_array('auto', $parts, true);
    $keys = array_values(array_filter($parts, fn($p) => $p !== 'auto'));
    if ($auto) return pin_pick_auto_style($title, $keys);
    if (count($keys) === 1) return $keys[0];
    return $keys[mt_rand(0, count($keys) - 1)];
}

/** Changes whenever template code changes, so cached previews refresh automatically. */
function pin_template_preview_version(): string
{
    $files = [__DIR__ . '/pin_templates_300.php', __DIR__ . '/pin_templates_100.php', __DIR__ . '/pin_templates_60.php', __DIR__ . '/pin_templates_more.php', __DIR__ . '/pin_styles_extra.php', __DIR__ . '/pin_template_registry.php', __DIR__ . '/ai_functions.php'];
    $m = 0;
    foreach ($files as $f) $m = max($m, (int)@filemtime($f));
    return substr(md5((string)$m), 0, 8);
}

/** Sample photo for template previews (downloaded once from the site's CDN and cached). */
function pin_template_sample_image(string $cacheDir): ?string
{
    $url = 'https://media.webtopin.com/cdn/uploads/a75be867c16cff3b35e3d4849775c9fc.png';
    $cache = rtrim($cacheDir, '/') . '/_sample.img';
    if (is_file($cache) && filesize($cache) > 1000) return file_get_contents($cache);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'AutomatedPin/1.0']);
    $bytes = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($bytes) && @imagecreatefromstring($bytes)) {
        @file_put_contents($cache, $bytes);
        return $bytes;
    }
    // Fallback: a soft gradient so previews still render if the CDN is unreachable.
    $im = imagecreatetruecolor(1024, 1536);
    for ($y = 0; $y < 1536; $y++) {
        $t = $y / 1536;
        imageline($im, 0, $y, 1024, $y, imagecolorallocate($im, (int)(214 - 40 * $t), (int)(196 - 30 * $t), (int)(178 - 20 * $t)));
    }
    ob_start(); imagejpeg($im, null, 90); $out = ob_get_clean(); imagedestroy($im);
    return $out;
}

function pin_template_sample_title(string $category): string
{
    $t = [
        'General' => '25 Simple Ideas You Will Love to Try',
        'Beauty & Hair' => '21 Soft Layered Haircuts for Every Face',
        'Food & Recipes' => '24 Easy Weeknight Dinner Recipes',
        'Pets' => '15 Healthy Homemade Dog Treats',
        'Home Decor' => '30 Cozy Living Room Decor Ideas',
        'Fashion' => '17 Wool Outfits for Women Over 50',
        'Travel' => '12 Dreamy Places to Visit This Summer',
        'Fitness' => '20 Minute Full Body Workout at Home',
        'DIY & Crafts' => '18 Easy DIY Crafts for the Weekend',
        'Wedding' => '25 Elegant Wedding Ideas on a Budget',
        'Holidays' => '20 Cozy Christmas Decor Ideas',
        'Lifestyle' => '15 Habits for a Calm Morning Routine',
        'Tips & Business' => '10 Money Saving Tips That Work',
        'Garden' => '16 Easy Garden Ideas for Small Spaces',
        'Kids & Parenting' => '22 Fun Rainy Day Activities for Kids',
    ];
    return $t[$category] ?? $t['General'];
}

/** Keeps only known template keys (and "auto") in a picker value; empty -> "auto". */
function pin_sanitize_style_value(string $value): string
{
    $reg = pin_template_registry();
    $out = [];
    foreach (explode(',', $value) as $p) {
        $p = trim($p);
        if ($p === 'auto' || isset($reg[$p])) $out[$p] = true;
    }
    $keys = array_keys($out);
    if (!$keys || $keys === ['auto']) return 'auto';
    return implode(',', $keys);
}
