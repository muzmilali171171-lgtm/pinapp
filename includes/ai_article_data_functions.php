<?php
/**
 * Admin → AI Article Data: reference articles (Ideas + Recipes/Food) organised by category and
 * sub category. Before Auto Article writes a new article, it looks up the closest reference
 * articles and studies their structure, depth and tone (never copying text), then writes an
 * original, detailed, intent-focused article at the length the user set.
 */

const AAD_IDEAS_CATEGORIES = [
    'Women Fashion', 'Outfits Over 50', 'Men Fashion', 'Hairstyles', 'Hair Color', 'Short Hairstyles', 'Nail Designs', 'Makeup',
    'Skincare', 'Beauty Tips', 'Tattoos', 'Jewelry', 'Home Decor', 'Living Room Ideas', 'Bedroom Ideas', 'Kitchen Ideas',
    'Bathroom Ideas', 'Small Spaces', 'DIY Crafts', 'Home Organization', 'Garden Ideas', 'Backyard & Patio', 'Wedding Ideas',
    'Party Ideas', 'Holiday Decor', 'Christmas Ideas', 'Halloween Ideas', 'Fall Ideas', 'Spring & Summer Ideas', 'Kids Activities',
    'Parenting', 'Baby & Nursery', 'Pets', 'Dog Care', 'Travel Destinations', 'Travel Tips', 'Fitness & Workouts', 'Yoga & Wellness',
    'Self Care', 'Mental Health', 'Personal Finance', 'Saving Money', 'Side Hustles', 'Productivity', 'Relationships', 'Quotes',
    'Tech & Gadgets', 'Photography', 'Art & Drawing', 'Books & Reading',
];
const AAD_RECIPE_CATEGORIES = [
    'Pasta', 'Chicken', 'Beef', 'Pork', 'Seafood', 'Vegetarian', 'Vegan', 'Soups & Stews', 'Salads', 'Breakfast & Brunch',
    'Desserts', 'Cakes & Cupcakes', 'Cookies & Bars', 'Bread & Baking', 'Appetizers & Snacks', 'Drinks & Smoothies',
    'Slow Cooker', 'Air Fryer', 'Instant Pot', 'One Pan & Sheet Pan', 'Healthy & Low Calorie', 'Keto & Low Carb',
    'Holiday Recipes', 'Asian', 'Mexican', 'Italian', 'Indian & Pakistani', 'Middle Eastern', 'Sauces & Dressings', 'Kids & Family Meals',
];

function aad_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_aad_v1';
    if (is_file($flag)) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_article_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('ideas','recipe') NOT NULL,
            parent_id INT DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cat (type, parent_id, name),
            KEY idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_reference_articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('ideas','recipe') NOT NULL,
            category_id INT DEFAULT NULL,
            subcategory_id INT DEFAULT NULL,
            title VARCHAR(500) NOT NULL,
            content LONGTEXT NOT NULL,
            word_count INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_type_cat (type, category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // article length setting on Auto Article batches
        $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'article_batches' AND column_name = ?");
        foreach ([['length_mode', "length_mode VARCHAR(10) NOT NULL DEFAULT 'auto'"], ['min_words', 'min_words INT DEFAULT NULL']] as [$c, $def]) {
            $chk->execute([$c]);
            if ((int)$chk->fetchColumn() === 0) $pdo->exec("ALTER TABLE article_batches ADD COLUMN $def");
        }
        // seed the starting categories once
        if ((int)$pdo->query("SELECT COUNT(*) FROM ai_article_categories")->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT IGNORE INTO ai_article_categories (type, parent_id, name) VALUES (?, NULL, ?)");
            foreach (AAD_IDEAS_CATEGORIES as $n) $ins->execute(['ideas', $n]);
            foreach (AAD_RECIPE_CATEGORIES as $n) $ins->execute(['recipe', $n]);
        }
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* retried next request */ }
}

function aad_categories(PDO $pdo, string $type): array
{
    aad_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM ai_reference_articles r WHERE r.category_id = c.id OR r.subcategory_id = c.id) AS articles
        FROM ai_article_categories c WHERE c.type = ? ORDER BY c.parent_id IS NOT NULL, c.name");
    $st->execute([$type]);
    $top = []; $subs = [];
    foreach ($st->fetchAll() as $c) {
        if ($c['parent_id']) $subs[(int)$c['parent_id']][] = $c; else $top[(int)$c['id']] = $c + ['subs' => []];
    }
    foreach ($subs as $pid => $list) if (isset($top[$pid])) $top[$pid]['subs'] = $list;
    return array_values($top);
}

/** Plain text of pasted content (HTML or text) and its word count. */
function aad_plain(string $content): string
{
    $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', $content);
    $t = preg_replace('#<(br|/p|/h[1-6]|/li|/tr)[^>]*>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/[ \t]+/", ' ', $t));
}
function aad_word_count(string $content): int
{
    return count(preg_split('/\s+/u', aad_plain($content), -1, PREG_SPLIT_NO_EMPTY));
}

/** Headline-like lines of a reference article: numbered items, short title-case lines, questions. */
function aad_outline_of(string $content, int $max = 45): array
{
    $lines = preg_split('/\R+/', aad_plain($content));
    $out = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l === '' || mb_strlen($l) > 110) continue;
        $words = str_word_count($l);
        $isNumbered = preg_match('/^\d{1,3}[\.\)]\s+\S/', $l);
        $isQuestion = preg_match('/\?$/', $l) && $words <= 16;
        $isHeading = $words >= 2 && $words <= 12 && !preg_match('/[.,;:]$/', $l) && preg_match('/^[A-Z0-9]/', $l);
        if ($isNumbered || $isQuestion || $isHeading) {
            if (!in_array($l, $out, true)) $out[] = $l;
        }
        if (count($out) >= $max) break;
    }
    return $out;
}

/**
 * The reference articles closest to a new title (same type): scored by shared words with the
 * reference title and its category / sub category names.
 */
function aad_find_references(PDO $pdo, string $type, string $title, int $limit = 2): array
{
    aad_ensure_schema($pdo);
    $stop = array_flip(['the', 'and', 'for', 'with', 'you', 'your', 'that', 'this', 'from', 'will', 'are', 'how', 'what', 'best', 'ideas', 'easy', 'over', 'into', 'to', 'of', 'a', 'in', 'on', 'an', 'is', 'it', 'ways', 'tips', 'recipe', 'recipes']);
    $tok = function (string $s) use ($stop) {
        preg_match_all('/[a-z0-9]+/', strtolower($s), $m);
        $w = [];
        foreach ($m[0] as $x) {
            if (strlen($x) < 3 || isset($stop[$x]) || ctype_digit($x)) continue;
            $w[rtrim($x, 's')] = true;
        }
        return $w;
    };
    $want = $tok($title);
    if (!$want) return [];
    try {
        $st = $pdo->prepare("SELECT r.id, r.title, r.content, r.word_count, c.name AS cat, s.name AS sub
            FROM ai_reference_articles r
            LEFT JOIN ai_article_categories c ON c.id = r.category_id
            LEFT JOIN ai_article_categories s ON s.id = r.subcategory_id
            WHERE r.type = ? ORDER BY r.id DESC LIMIT 800");
        $st->execute([$type]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { return []; }
    $scored = [];
    foreach ($rows as $r) {
        $t = $tok($r['title']);
        $c = $tok(($r['cat'] ?? '') . ' ' . ($r['sub'] ?? ''));
        $score = count(array_intersect_key($want, $t)) * 2 + count(array_intersect_key($want, $c)) * 3;
        if ($score > 0) $scored[] = [$score, $r];
    }
    usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
    return array_map(fn($x) => $x[1], array_slice($scored, 0, $limit));
}

/** Compact brief of the reference articles for the AI prompt — structure and depth, not text to copy. */
function aad_reference_brief(array $refs): string
{
    if (!$refs) return '';
    $b = "REFERENCE ARTICLES from our library (same niche). Study them first: their structure, section types, depth, "
        . "practical detail and reader-first tone. Do NOT copy or closely paraphrase any sentence — write fully original text.\n";
    foreach ($refs as $i => $r) {
        $outline = aad_outline_of($r['content'], 30);
        $excerpt = mb_substr(aad_plain($r['content']), 0, 900);
        $b .= "\n--- Reference " . ($i + 1) . ': "' . $r['title'] . '"' . ($r['cat'] ? ' [' . $r['cat'] . ($r['sub'] ? ' › ' . $r['sub'] : '') . ']' : '')
            . " — about " . (int)$r['word_count'] . " words\nSection outline:\n- " . implode("\n- ", $outline)
            . "\nOpening (for tone only):\n" . $excerpt . "\n";
    }
    return $b;
}

/** Target word count for a batch: null = let the AI decide. */
function aad_target_words(array $batch): ?int
{
    $mode = $batch['length_mode'] ?? 'auto';
    if ($mode === 'min' && (int)($batch['min_words'] ?? 0) > 0) return max(300, min(8000, (int)$batch['min_words']));
    if ($mode === 'random') return random_int(1800, 3500);
    return null;
}
