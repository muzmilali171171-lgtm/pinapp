<?php
require_once __DIR__ . '/ai_article_data_functions.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_functions.php';
require_once __DIR__ . '/website_functions.php';
require_once __DIR__ . '/pricing_functions.php';

/**
 * Auto Article — batches of AI-written, AI-illustrated articles published to
 * WordPress on a daily cadence, with optional automatic Pinterest pin creation
 * per published article (staggered per-article and rate-limited per-day).
 *
 * Reuses existing infrastructure rather than duplicating it:
 *   - ai_generate_text() / ai_generate_pin_image_with_retry() / compose_pin_image()
 *   - website_publish_post() (the PinScheduler Publisher WP plugin's REST API)
 *   - resolve_board_selection() / ai_generate_board_suggestion() / get_boards_for_account()
 *   - new_batch_id() / activate_batch() (pin_batches) so auto-generated pins show up
 *     in the existing Batches / Batch View pages alongside bulk-scheduled ones.
 */

/* ===================== Batches ===================== */

function get_user_article_batches(PDO $pdo, int $userId): array
{
    $sql = "SELECT b.*, w.site_name, w.site_url,
        (SELECT COUNT(*) FROM articles a WHERE a.batch_id = b.id) AS total_articles,
        (SELECT COUNT(*) FROM articles a WHERE a.batch_id = b.id AND a.status = 'published') AS published_articles,
        (SELECT COUNT(*) FROM articles a WHERE a.batch_id = b.id AND a.status = 'failed') AS failed_articles
        FROM article_batches b
        LEFT JOIN websites w ON w.id = b.website_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_article_batch(PDO $pdo, string $batchId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT b.*, w.site_name, w.site_url, w.site_key
        FROM article_batches b LEFT JOIN websites w ON w.id = b.website_id
        WHERE b.batch_id = ? AND b.user_id = ?");
    $stmt->execute([$batchId, $userId]);
    return $stmt->fetch() ?: null;
}

function get_article_batch_articles(PDO $pdo, int $batchDbId, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE batch_id = ? AND user_id = ? ORDER BY scheduled_for ASC, id ASC");
    $stmt->execute([$batchDbId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Creates a new article batch and one `articles` row per title, spreading
 * them across days at $dailyCount titles/day starting today.
 */
function create_article_batch(PDO $pdo, int $userId, array $batch, array $titles): array
{
    $batchToken = new_batch_id();
    $stmt = $pdo->prepare("INSERT INTO article_batches
        (user_id, batch_id, name, article_type, website_id, category, wp_author_id, tags_enabled, daily_count,
         feature_image_w, feature_image_h, ideas_image_size, recipe_image_count, image_quality,
         publish_mode, pin_settings_json, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([
        $userId, $batchToken, $batch['name'] ?: null, $batch['article_type'], $batch['website_id'] ?: null,
        $batch['category'] ?: null, $batch['wp_author_id'] ?: null, $batch['tags_enabled'] ? 1 : 0, max(1, (int)$batch['daily_count']),
        $batch['feature_image_w'] ?: 1200, $batch['feature_image_h'] ?: 630, $batch['ideas_image_size'] ?: '3:4',
        $batch['recipe_image_count'] ?: 3, $batch['image_quality'] ?: 'budget',
        $batch['publish_mode'] ?: 'now', $batch['pin_settings_json'] ?: null,
    ]);
    $batchDbId = (int)$pdo->lastInsertId();
    if (!empty($batch['image_category_id'])) {
        $pdo->prepare("UPDATE article_batches SET image_category_id = ? WHERE id = ?")->execute([(int)$batch['image_category_id'], $batchDbId]);
    }
    // Article length setting (auto / minimum words / random long-form)
    try {
        aad_ensure_schema($pdo);
        $pdo->prepare("UPDATE article_batches SET length_mode = ?, min_words = ? WHERE id = ?")
            ->execute([in_array($batch['length_mode'] ?? 'auto', ['auto', 'min', 'random'], true) ? $batch['length_mode'] : 'auto',
                !empty($batch['min_words']) ? (int)$batch['min_words'] : null, $batchDbId]);
    } catch (Throwable $e) { /* column added by migrate.php */ }

    $dailyCount = max(1, (int)$batch['daily_count']);
    $stmt = $pdo->prepare("INSERT INTO articles
        (user_id, batch_id, source_type, source_value, title, website_id, category, tags, scheduled_for, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued')");
    $today = new DateTime('today');
    foreach ($titles as $i => $t) {
        $dayOffset = intdiv($i, $dailyCount);
        $scheduledFor = (clone $today)->modify("+{$dayOffset} days")->format('Y-m-d');
        $stmt->execute([
            $userId, $batchDbId, $t['source_type'] ?? 'keyword', $t['source_value'] ?? null,
            $t['title'], $batch['website_id'] ?: null, $batch['category'] ?: null,
            $batch['tags_enabled'] ? '' : null, $scheduledFor,
        ]);
    }

    return ['ok' => true, 'batch_id' => $batchToken, 'batch_db_id' => $batchDbId, 'count' => count($titles)];
}

/* ===================== Title handling: competitor links + AI rewrite ===================== */

/** Given a mixed list of titles/links, resolves each link to its page title (unrewritten). */
function resolve_batch_title_line(string $line): array
{
    $line = trim($line);
    if (preg_match('#^https?://#i', $line)) {
        $fetched = extract_title_from_url($line);
        return ['title' => $fetched ?: $line, 'source_type' => 'competitor_url', 'source_value' => $line];
    }
    return ['title' => $line, 'source_type' => 'keyword', 'source_value' => null];
}

/**
 * Rewrites a competitor-sourced title into an original one via the configured text
 * model — same topic and intent, not a copy, safe to publish as the user's own.
 */
function ai_rewrite_competitor_title(PDO $pdo, string $title): string
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) return $title;

    $systemPrompt = 'You rewrite article titles found on competitor sites into original, non-plagiarized titles with the '
        . 'same topic and intent. Respond with ONLY the rewritten title — no quotes, no commentary. Keep any leading number.';
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, "Rewrite this title so it's original: $title", 60);
    if ($result['ok']) {
        $rewritten = trim($result['text'], "\"' \t\n\r.");
        if ($rewritten !== '') return $rewritten;
    }
    return $title;
}

/* ===================== Outline + draft generation ===================== */

/**
 * Generates a structured outline for one article. For 'ideas' articles this is an
 * intro plus one entry per idea (each gets its own image later); for 'recipe'
 * articles it's the standard recipe sections (intro, ingredients, instructions,
 * tips) with a handful of image slots placed through the content.
 */
/** Pulls the count a listicle title implies (e.g. "20 Plus Size Outfits" -> 20), so the outline asks for the right number of ideas instead of a generic default. */
function extract_idea_count_from_title(string $title): int
{
    if (preg_match('/^(\d{1,3})\b/', trim($title), $m)) {
        return max(1, min(40, (int)$m[1]));
    }
    return 10;
}

/** Shared writing rules for every article: intent first, genuinely useful, specific, original. */
function article_quality_rules(): string
{
    return 'Before writing, work out the niche and the reader\'s real search intent (what they want to see, do, cook or '
        . 'decide after reading) and serve that intent in every section. Be specific and practical: concrete details, '
        . 'measurements, names of pieces, techniques, times, swaps, mistakes to avoid — no filler, no vague generic lines, '
        . 'no repeating the same point in different words. Write for real people, in a warm, confident, natural voice. '
        . 'Use the main topic words naturally in headings and the opening, never stuff keywords.';
}

function ai_generate_article_outline(PDO $pdo, string $title, string $articleType, int $ideaCount = 12, array $ctx = []): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) {
        return ['ok' => false, 'sections' => [], 'error' => 'No AI text model is configured for Article Write yet.'];
    }
    $target = $ctx['target_words'] ?? null;
    $extra = "\n" . article_quality_rules();
    if ($target) $extra .= " The finished article will be about $target words, so plan enough substance (sections, detail, FAQs) to fill that length with genuinely useful content.";

    if ($articleType === 'recipe') {
        $systemPrompt = 'You are an expert recipe developer and food writer outlining a high-quality, genuinely useful recipe article '
            . 'that would perform well on Pinterest and in search. Respond with ONLY a JSON object, no markdown fences: '
            . '{"intent": "who is searching and what they need", "intro": "1-2 sentence hook", "servings": 4, "prep_time": "10 minutes", '
            . '"cook_time": "20 minutes", "why_it_works": ["reason 1", "reason 2", "reason 3"], '
            . '"ingredients": ["quantity + ingredient (+ note)", ...], '
            . '"steps": [{"heading": "Step title", "notes": "what this step covers, with times/temperatures/visual cues"}, ...], '
            . '"tips_heading": "e.g. Tips & Variations", "tips": ["tip 1", "tip 2", "tip 3"], '
            . '"storage": "how to store / reheat / make ahead", '
            . '"nutrition": {"calories": "kcal", "carbohydrates": "g", "protein": "g", "fat": "g", "saturated_fat": "g", "fiber": "g", "sugar": "g", "sodium": "mg"}, '
            . '"faqs": [{"question": "real reader question", "answer": "2-3 sentence answer"}, ...]}. '
            . 'Nutrition is an honest per-serving estimate from the ingredient list. 6-10 steps, 5-8 tips, 4-5 faqs.' . $extra;
        $userPrompt = "Recipe title: $title";
    } else {
        $systemPrompt = 'You are an expert Pinterest content writer outlining a high-quality, genuinely useful listicle '
            . 'article that would perform well on Pinterest and in search. Respond with ONLY a JSON object, no markdown '
            . 'fences: {"intent": "who is searching and what they need", "intro": "2-3 sentence hook introducing the roundup", '
            . '"items": [{"heading": "short idea title", "notes": "what makes this idea good, styling/how-to notes"}, ...], '
            . '"faqs": [{"question": "a real reader question about this topic", "answer": "2-3 sentence answer"}, ...], '
            . '"wrap_up": "2-3 sentence closing thought that ties the roundup together"}. '
            . "Generate exactly $ideaCount items — that's the count implied by the article title — and 5 faqs." . $extra;
        $userPrompt = "Article title: $title";
    }
    if (!empty($ctx['refs_brief'])) $userPrompt .= "\n\n" . $ctx['refs_brief'];
    if (!empty($ctx['avoid_titles'])) $userPrompt .= "\n\nAlready written on this site (take a fresh angle, don't repeat these):\n- " . implode("\n- ", $ctx['avoid_titles']);

    // room for every idea (reasoning models also spend tokens thinking)
    $maxTok = $articleType === 'recipe' ? 5000 : (int)min(10000, 3000 + $ideaCount * 260);
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, $maxTok);
    if (!$result['ok']) {
        return ['ok' => false, 'sections' => [], 'error' => $result['error']];
    }
    $json = extract_json_from_text($result['text']);
    if (!$json) {
        return ['ok' => false, 'sections' => [], 'error' => 'Could not parse the outline response' . (!empty($result['truncated']) ? ' (it was cut off).' : '.')];
    }
    if ($articleType !== 'recipe') {
        $got = count($json['items'] ?? []);
        if ($got < max(3, (int)floor($ideaCount * 0.8))) {
            return ['ok' => false, 'sections' => [], 'error' => "The outline had $got of $ideaCount ideas."];
        }
        $json['items'] = array_slice($json['items'], 0, $ideaCount);
    } elseif (empty($json['ingredients']) || empty($json['steps'])) {
        return ['ok' => false, 'sections' => [], 'error' => 'The recipe outline was incomplete.'];
    }
    return ['ok' => true, 'sections' => $json, 'error' => null];
}

/**
 * Expands an outline into full HTML article content. Image slots are left as
 * {{IMAGE:n}} placeholders for generate_article_content_images() to fill in later
 * (kept separate from generation so image failures don't force rewriting the text).
 * $ctx: target_words (int|null), refs_brief (string).
 */
function ai_generate_article_draft(PDO $pdo, string $title, array $outline, string $articleType, array $ctx = []): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) {
        return ['ok' => false, 'html' => '', 'image_slots' => 0, 'error' => 'No AI text model is configured for Article Write yet.'];
    }
    $target = $ctx['target_words'] ?? null;
    $lengthRule = $target
        ? " LENGTH: the article must be at least $target words of real, useful content (not padding) — give each section the depth it needs."
        : '';

    if ($articleType === 'recipe') {
        $ingredients = implode("\n", array_map(fn($i) => "- $i", $outline['ingredients'] ?? []));
        $stepsList = '';
        foreach (($outline['steps'] ?? []) as $i => $s) {
            $stepsList .= ($i + 1) . ". {$s['heading']}: {$s['notes']}\n";
        }
        $tips = implode("\n", array_map(fn($t) => "- $t", $outline['tips'] ?? []));
        $why = implode("\n", array_map(fn($t) => "- $t", $outline['why_it_works'] ?? []));
        $nut = '';
        foreach (($outline['nutrition'] ?? []) as $k => $v) $nut .= '- ' . ucwords(str_replace('_', ' ', (string)$k)) . ": $v\n";
        $faqs = '';
        foreach (($outline['faqs'] ?? []) as $f) $faqs .= "Q: {$f['question']}\nA: {$f['answer']}\n";
        $systemPrompt = 'You write warm, genuinely helpful recipe blog posts as clean HTML (h2/h3/p/ul/ol/li/strong only, '
            . 'no html/head/body tags, no inline styles, no images/img tags — images are added separately). Structure: '
            . '1) an engaging intro that answers why this recipe is worth making; '
            . '2) <h2>Why This Recipe Works</h2>; '
            . '3) the recipe card: a short <p> with Servings, Prep time, Cook time and Total time, then <h2>Ingredients</h2> as a <ul> '
            . 'and <h2>Instructions</h2> as an <ol> with clear, detailed steps (times, temperatures, what it should look like); '
            . '4) the tips / variations section; 5) <h2>Storage & Make-Ahead</h2>; '
            . '6) <h2>Nutrition Information</h2> as a <ul> per serving, followed by a one-line <p> noting the values are an estimate; '
            . '7) <h2>FAQs</h2> with each question as an <h3> followed by a <p> answer. '
            . 'Insert the token {{IMAGE:1}} on its own line right after the intro, and additional {{IMAGE:2}}, {{IMAGE:3}} etc. '
            . 'tokens spaced naturally through the Instructions section (one every 2-3 steps) — use exactly the number of image tokens requested. '
            . article_quality_rules() . $lengthRule;
        $userPrompt = "Recipe title: $title\nReader intent: " . ($outline['intent'] ?? '') . "\nIntro hook: " . ($outline['intro'] ?? '')
            . "\nServings: " . ($outline['servings'] ?? '') . "\nPrep time: " . ($outline['prep_time'] ?? '') . "\nCook time: " . ($outline['cook_time'] ?? '')
            . "\nWhy it works:\n$why\nIngredients:\n$ingredients\nSteps:\n$stepsList\nTips heading: " . ($outline['tips_heading'] ?? 'Tips & Variations')
            . "\nTips:\n$tips\nStorage: " . ($outline['storage'] ?? '') . "\nNutrition per serving (estimate):\n$nut\nFAQs:\n$faqs\nInclude 3 image tokens total.";
    } else {
        $items = '';
        foreach (($outline['items'] ?? []) as $i => $it) {
            $items .= ($i + 1) . ". {$it['heading']} — {$it['notes']}\n";
        }
        $faqs = '';
        foreach (($outline['faqs'] ?? []) as $f) {
            $faqs .= "Q: {$f['question']}\nA: {$f['answer']}\n";
        }
        $perIdea = $target && $target >= 1500
            ? 'Under each heading write 2-4 paragraphs: describe the idea/look and exactly what makes it work (specific pieces, colours, '
              . 'shapes, techniques), who it suits or when to use it, and finish with one direct, actionable tip to the reader '
              . '(e.g. "Ask your stylist for…", "Try pairing this with…", "Look for…"). '
            : 'Under each heading write exactly two short paragraphs: the first (2-3 sentences) describes the idea/look and what '
              . 'makes it good; the second (1 sentence) is a direct, actionable tip written as direct advice to the reader (e.g. '
              . '"Ask your stylist for…", "Try pairing this with…", "Look for…" — whatever direct-address phrasing fits the topic naturally). ';
        $systemPrompt = 'You write engaging, genuinely useful Pinterest-style listicle blog posts as clean HTML '
            . '(h2/h3/p/ul/li/strong only, no html/head/body tags, no inline styles, no img tags — images are added '
            . 'separately), following this exact structure: '
            . '1) An intro that speaks to the reader\'s intent and promises what they will get. '
            . '2) One <h2>N. Heading</h2> section per idea, numbered to match the list order exactly (e.g. "<h2>1. Cream '
            . 'Blonde Soft Crop</h2>"). Insert an {{IMAGE:n}} token on its own line immediately after each heading, '
            . 'numbered sequentially starting at 1. ' . $perIdea
            . '3) An <h2>FAQs</h2> section with each question as an <h3> immediately followed by a short <p> answer. '
            . '4) A closing <h2>Wrap Up</h2> section with 1-2 short paragraphs tying the roundup together. '
            . article_quality_rules() . $lengthRule;
        $userPrompt = "Article title: $title\nReader intent: " . ($outline['intent'] ?? '') . "\nIntro hook: " . ($outline['intro'] ?? '')
            . "\nIdeas (write one <h2> section for each, in this exact order and numbering):\n$items\nFAQs to include:\n$faqs\nWrap-up thought to close with: " . ($outline['wrap_up'] ?? '');
    }
    if (!empty($ctx['refs_brief'])) $userPrompt .= "\n\n" . $ctx['refs_brief'];

    $maxTokens = $target ? (int)min(16000, max(6000, $target * 2 + 2500)) : 7000;
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, $maxTokens);
    if (!$result['ok']) {
        return ['ok' => false, 'html' => '', 'image_slots' => 0, 'error' => $result['error']];
    }
    $html = trim($result['text']);
    $html = preg_replace('/^```(?:html)?\s*|\s*```$/', '', $html);
    if (!empty($result['truncated'])) {
        // cut off at the token limit — ask for the rest once, then join
        $more = ai_generate_text($pdo, $provider, $model, $systemPrompt . ' You are continuing an answer that was cut off: '
            . 'output ONLY the remaining HTML, starting exactly where it stopped, without repeating anything.', $userPrompt
            . "\n\nALREADY WRITTEN (continue from the end of this):\n" . mb_substr($html, -6000), $maxTokens);
        if ($more['ok'] && trim($more['text']) !== '') $html .= "\n" . preg_replace('/^```(?:html)?\s*|\s*```$/', '', trim($more['text']));
        else return ['ok' => false, 'html' => '', 'image_slots' => 0, 'error' => 'The article was cut off by the AI model and could not be finished.'];
    }

    // Too short for the length the user asked for? One expansion pass, keeping structure and image tokens.
    if ($target) {
        $words = str_word_count(strip_tags($html));
        if ($words < $target * 0.85) {
            $exp = ai_generate_text($pdo, $provider, $model,
                'You expand an HTML article to the required length. Keep every heading, the exact section order, every '
                . '{{IMAGE:n}} token in place and the same allowed tags. Add genuinely useful depth — specifics, examples, '
                . 'how-to detail, mistakes to avoid — never filler or repetition. Return ONLY the full expanded HTML.',
                "Required length: at least $target words (currently about $words).\n\nARTICLE HTML:\n$html", $maxTokens);
            if ($exp['ok']) {
                $bigger = preg_replace('/^```(?:html)?\s*|\s*```$/', '', trim($exp['text']));
                if (str_word_count(strip_tags($bigger)) > $words && substr_count($bigger, '{{IMAGE:') >= substr_count($html, '{{IMAGE:')) $html = $bigger;
            }
        }
    }
    preg_match_all('/\{\{IMAGE:(\d+)\}\}/', $html, $m);
    $imageSlots = !empty($m[1]) ? max(array_map('intval', $m[1])) : 0;

    return ['ok' => true, 'html' => $html, 'image_slots' => $imageSlots, 'error' => null];
}

/* ===================== Images (content + feature) — plain photos, no text overlay ===================== */

/**
 * Generates one plain, text-free photographic image (no title/CTA overlay — these
 * are embedded inside article content or used as the featured image, not a pin)
 * at the given pixel size. Uses the same provider/retry/Cloudflare-rotation engine
 * as pin images, just without the compose_pin_image() text pass.
 */
function generate_plain_article_image(PDO $pdo, string $subject, int $width, int $height, string $provider, string $model, int $iterations, string $categoryPath = ''): array
{
    // Scene written for the subject + category, generated in the output's own aspect ratio.
    $brief = pin_prepare_image_brief($pdo, $subject, $categoryPath, '', false, true);
    $promptPair = build_pin_image_prompt($subject, '', $brief, pin_generation_dims_for($width, $height));
    $result = ai_generate_pin_image_with_retry($pdo, $provider, $model, $promptPair, $iterations);
    if (!$result['ok']) {
        return ['ok' => false, 'data' => null, 'error' => $result['error']];
    }
    $src = @imagecreatefromstring($result['image_data']);
    if (!$src) {
        return ['ok' => false, 'data' => null, 'error' => 'Could not decode the generated image.'];
    }
    $dst = imagecreatetruecolor($width, $height);
    pin_image_cover_resize($src, $dst, $width, $height);
    imagedestroy($src);
    ob_start();
    imagejpeg($dst, null, 88);
    $out = ob_get_clean();
    imagedestroy($dst);
    return ['ok' => true, 'data' => $out, 'error' => null];
}

/** The image category chosen in the batch's pin settings ("Main › Sub"), used for every image of the batch. */
function article_batch_category_path(PDO $pdo, array $batch, string $slot = 'article'): string
{
    $settings = json_decode($batch['pin_settings_json'] ?? '{}', true) ?: [];
    $id = (int)($settings['image_category_id'] ?? ($batch['image_category_id'] ?? 0));
    // Pin images can have their own category (Pin Settings → Select Category); Auto = the article's.
    if ($slot === 'pin' && !empty($settings['pin_image_category_id'])) $id = (int)$settings['pin_image_category_id'];
    return image_category_path($pdo, $id);
}

/** Resolves which provider/model/iterations/cost to use for article images, given the batch's chosen quality tier. */
function article_image_provider_settings(PDO $pdo, array $articleSettings, string $quality, string $slot): array
{
    // Per-quality model choice (Admin → Auto Article Pin → image settings for pins / featured image /
    // article images). Falls back to the legacy columns below until the admin saves that page.
    if ($quality !== 'cloudflare') {
        $feature = ['feature' => 'article_feature', 'pin' => 'article_pin'][$slot] ?? 'article_content';
        return image_model_for($pdo, $feature, $quality);
    }
    if ($slot === 'feature') {
        $provider = $articleSettings['feature_image_provider'] ?? 'deepinfra';
        $model = $articleSettings['feature_image_model'] ?? 'black-forest-labs/FLUX-1-schnell';
    } elseif ($slot === 'pin') {
        // Pins use the same image model as the Bulk Pin Scheduler feature, not the article-content one.
        $provider = $articleSettings['pin_image_provider'] ?? 'deepinfra';
        $model = $articleSettings['pin_image_model'] ?? 'black-forest-labs/FLUX-1-schnell';
    } else {
        $provider = $articleSettings['image_provider'] ?? 'deepinfra';
        $model = $articleSettings['image_model'] ?? 'black-forest-labs/FLUX-1-schnell';
    }
    // 'cloudflare' used to be a distinct "quality" value forcing a flat rate — now cost is
    // always driven purely by the user's quality tier (Plan Pricing → Setting), never by
    // which provider actually generates the image, so it just picks a sensible iteration
    // count for that provider and falls through to the same cost lookup as everyone else.
    if ($quality === 'cloudflare') {
        return ['provider' => 'cloudflare', 'model' => '', 'iterations' => 4, 'cost' => image_quality_cost($pdo, 'ultra')];
    }
    $iterations = ['budget' => 2, 'high' => 3, 'ultra' => 4][$quality] ?? 2;
    $cost = image_quality_cost($pdo, $quality);
    if ($provider === 'cloudflare') {
        return ['provider' => 'cloudflare', 'model' => '', 'iterations' => $iterations, 'cost' => $cost];
    }
    return ['provider' => $provider, 'model' => $model, 'iterations' => $iterations, 'cost' => $cost];
}

/**
 * Generates exactly ONE image for this article's queue (the featured image
 * first, then each {{IMAGE:n}} content slot in order), saves it, updates
 * image_progress_json, deducts that one image's credit cost on success, and
 * advances the article to 'ready' once nothing is left to generate. Doing
 * one image per call (instead of the whole batch in one request) is what
 * keeps each web/cron request short enough to never hit a server timeout,
 * and means a killed request only ever loses at most one image's progress.
 */
function generate_next_article_image(PDO $pdo, array $article, array $batch): array
{
    $articleSettings = get_article_settings($pdo) ?: [];
    $destDir = __DIR__ . '/../uploads/articles/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $progress = json_decode($article['image_progress_json'] ?? '', true);
    if (!is_array($progress)) {
        $progress = ['featured_done' => false, 'featured_path' => null, 'inline_next' => 1, 'inline' => []];
    }
    $slotsNeeded = (int)($article['image_slots_needed'] ?? 0);
    if ($batch['article_type'] === 'recipe') {
        $slotsNeeded = min($slotsNeeded, (int)$batch['recipe_image_count']);
    }

    if (!$progress['featured_done']) {
        $settings = article_image_provider_settings($pdo, $articleSettings, $batch['image_quality'], 'feature');
        if (get_user_image_credits($pdo, $article['user_id']) < $settings['cost']) {
            return article_images_no_credits($pdo, $article);
        }
        $img = generate_plain_article_image($pdo, $article['title'], (int)$batch['feature_image_w'], (int)$batch['feature_image_h'], $settings['provider'], $settings['model'], $settings['iterations'], article_batch_category_path($pdo, $batch));
        if ($img['ok']) {
            $filename = 'feat_' . bin2hex(random_bytes(8)) . '.jpg';
            file_put_contents($destDir . $filename, $img['data']);
            $progress['featured_path'] = 'uploads/articles/' . $filename;
            deduct_image_credits($pdo, $article['user_id'], $settings['cost']);
        }
        $progress['featured_done'] = true;
        $more = $progress['inline_next'] <= $slotsNeeded;
        save_article_image_progress($pdo, $article['id'], $progress, $more);
        return ['ok' => true, 'error' => $img['ok'] ? null : ('Feature image: ' . $img['error']), 'more' => $more];
    }

    if ($progress['inline_next'] <= $slotsNeeded) {
        $n = $progress['inline_next'];
        $settings = article_image_provider_settings($pdo, $articleSettings, $batch['image_quality'], 'content');
        if (get_user_image_credits($pdo, $article['user_id']) < $settings['cost']) {
            return article_images_no_credits($pdo, $article);
        }
        [$cw, $ch] = $batch['article_type'] === 'recipe' ? [1200, 900] : pin_image_size_dims($batch['ideas_image_size'] ?? '3:4');
        $img = generate_plain_article_image($pdo, $article['title'], $cw, $ch, $settings['provider'], $settings['model'], $settings['iterations'], article_batch_category_path($pdo, $batch));
        if ($img['ok']) {
            $filename = 'inline_' . bin2hex(random_bytes(8)) . '.jpg';
            file_put_contents($destDir . $filename, $img['data']);
            $progress['inline'][$n] = 'uploads/articles/' . $filename;
            deduct_image_credits($pdo, $article['user_id'], $settings['cost']);
        }
        $progress['inline_next'] = $n + 1;
        $more = $progress['inline_next'] <= $slotsNeeded;
        save_article_image_progress($pdo, $article['id'], $progress, $more);
        return ['ok' => true, 'error' => $img['ok'] ? null : ("Image $n: " . $img['error']), 'more' => $more];
    }

    save_article_image_progress($pdo, $article['id'], $progress, false);
    return ['ok' => true, 'error' => null, 'more' => false];
}

/** Not enough image credits: stop with a clear reason (it used to wait in "Imaging" forever). Retry resumes the images. */
function article_images_no_credits(PDO $pdo, array $article): array
{
    $msg = 'Image AI credits are low — top up / upgrade, then press Retry (the images already made are kept).';
    $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?")->execute([$msg, $article['id']]);
    return ['ok' => false, 'error' => $msg, 'more' => false];
}

function save_article_image_progress(PDO $pdo, int $articleId, array $progress, bool $more): void
{
    $status = $more ? 'imaging' : 'ready';
    $pdo->prepare("UPDATE articles SET image_progress_json = ?, status = ? WHERE id = ?")
        ->execute([json_encode($progress), $status, $articleId]);
}

/* ===================== Slug + meta description ===================== */

function compute_clean_slug(string $title): string
{
    $slug = preg_replace('/^\d+\s*[-:.]?\s*/', '', $title); // drop a leading number — slugs should be clean/numberless
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = preg_replace('/-{2,}/', '-', $slug);
    return $slug !== '' ? mb_substr($slug, 0, 75) : 'article';
}

function ai_generate_meta_description(PDO $pdo, string $title, string $intro): string
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) return mb_substr($intro, 0, 155);

    $systemPrompt = 'Write ONLY an SEO meta description, 140-160 characters, no quotes, no commentary — natural, compelling, includes the main keyword.';
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, "Title: $title\nIntro: $intro", 100);
    if ($result['ok']) {
        $desc = trim($result['text'], "\"' \t\n\r");
        if ($desc !== '') return mb_substr($desc, 0, 160);
    }
    return mb_substr($intro, 0, 155);
}

/* ===================== WordPress publish ===================== */

/**
 * Assembles the final HTML (swapping {{IMAGE:n}} tokens for real <img> tags with
 * inline base64 placeholders the WP plugin replaces with real media-library URLs),
 * then publishes via the PinScheduler Publisher REST API. Updates the `articles`
 * row with the result either way.
 */
function publish_article_to_wordpress(PDO $pdo, array $article, array $batch, array $website, string $html, ?string $featuredPath, array $inlinePaths, string $metaDescription): array
{
    $content = $html;
    $inlineImagesPayload = [];
    foreach ($inlinePaths as $n => $path) {
        $placeholder = '{{REAL_IMAGE_URL_' . $n . '}}';
        $content = str_replace('{{IMAGE:' . $n . '}}', '<img src="' . $placeholder . '" alt="' . e(mb_substr($article['title'], 0, 100)) . '" style="max-width:100%;height:auto;">', $content);
        $fullPath = __DIR__ . '/../' . $path;
        if (is_file($fullPath) && ($website['platform'] ?? 'wordpress') === 'wordpress') {
            $inlineImagesPayload[] = ['placeholder' => $placeholder, 'base64' => base64_encode(file_get_contents($fullPath)), 'filename' => basename($path)];
        }
    }
    // Remove any leftover un-filled image tokens rather than publish a broken placeholder.
    $content = preg_replace('/\{\{IMAGE:\d+\}\}/', '', $content);

    $slug = compute_clean_slug($article['title']);
    $payload = [
        'title' => $article['title'],
        'content' => $content,
        'category' => $article['category'] ?: '',
        'tags' => $batch['tags_enabled'] ? ai_suggest_tags_csv($pdo, $article['title']) : '',
        'status' => 'publish',
        'slug' => $slug,
        'meta_description' => $metaDescription,
        'inline_images' => $inlineImagesPayload,
        'author_id' => (int)($batch['wp_author_id'] ?? 0),
    ];
    if ($featuredPath) {
        $fullPath = __DIR__ . '/../' . $featuredPath;
        if (is_file($fullPath)) {
            $payload['featured_image_base64'] = base64_encode(file_get_contents($fullPath));
            $payload['featured_image_filename'] = basename($featuredPath);
        }
    }

    // Shopify / Wix / custom webhook sites can't take base64 images through a plugin — hand them public
    // URLs to the images this app already hosts under /uploads instead.
    if (($website['platform'] ?? 'wordpress') !== 'wordpress') {
        foreach ($inlinePaths as $n => $path) {
            $payload['content'] = str_replace('{{REAL_IMAGE_URL_' . $n . '}}', public_upload_url($path), $payload['content']);
        }
        $content = $payload['content'];
        $payload['inline_images'] = [];
        unset($payload['featured_image_base64'], $payload['featured_image_filename']);
        if ($featuredPath) $payload['featured_image_url'] = public_upload_url($featuredPath);
    }

    $result = website_publish_dispatch($pdo, $website, $payload);

    if ($result['ok'] && !empty($result['data']['ok'])) {
        $stmt = $pdo->prepare("UPDATE articles SET status = 'published', content = ?, featured_image_path = ?, meta_description = ?, slug = ?, wp_post_id = ?, wp_post_url = ?, published_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $featuredPath, $metaDescription, $slug, $result['data']['id'], $result['data']['url'], $article['id']]);
        return ['ok' => true, 'wp_post_id' => $result['data']['id'], 'wp_post_url' => $result['data']['url']];
    }

    $error = $result['error'] ?: (is_array($result['data'] ?? null) ? json_encode($result['data']) : 'Publish failed.');
    $stmt = $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?");
    $stmt->execute([$error, $article['id']]);
    return ['ok' => false, 'error' => $error];
}

/** Two or three light tags for the post, derived from the title — used only when the batch has tags enabled. */
function ai_suggest_tags_csv(PDO $pdo, string $title): string
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) return '';
    $result = ai_generate_text($pdo, $provider, $model,
        'Respond with ONLY 2-3 short WordPress tags for this article title, comma-separated, lowercase, no quotes, no commentary.',
        $title, 40);
    return $result['ok'] ? trim($result['text']) : '';
}

/* ===================== Auto-pin scheduling for a published article ===================== */

/**
 * Schedules this article's Pinterest pins per the batch's pin_settings_json:
 * $pinsPerArticle pins, each spaced $articleGapDays apart (default 30 — "1
 * month"), each slotted into a day that hasn't exceeded that day's effective
 * daily pin quota (base + monthly ramp), with pins on the same day spread
 * automatically across the day (24h / quota). Pins are attached to a single
 * pin_batches row for this article batch so they show up in Batches/Batch View.
 */
function auto_schedule_pins_for_article(PDO $pdo, array $article, array $batch): array
{
    $settings = json_decode($batch['pin_settings_json'] ?? '{}', true) ?: [];
    $accountId = (int)($settings['pinterest_account_id'] ?? 0);
    if (!$accountId) {
        return ['ok' => false, 'error' => 'No Pinterest account selected for auto-pinning.'];
    }

    $pinsPerArticle = max(1, (int)($settings['pins_per_article'] ?? 3));
    $articleGapDays = max(1, (int)($settings['article_pin_gap_days'] ?? 30));
    $gapMinutesMode = ($settings['article_pin_gap_unit'] ?? 'days') === 'minutes' && !empty($settings['article_pin_gap_minutes']);
    $gapMinutes = $gapMinutesMode ? max(1, (int)$settings['article_pin_gap_minutes']) : 0;
    $dailyBase = max(1, (int)($settings['daily_pin_count'] ?? 2));
    $monthlyRamp = max(0, (int)($settings['daily_pin_ramp'] ?? 0));
    $sizeKey = $settings['pin_size'] ?? '2:3';
    $website = trim($settings['website'] ?? '');
    $ctaMode = $settings['cta_mode'] ?? 'auto';
    $ctaTextSetting = trim($settings['cta_text'] ?? '');
    $imageStyle = $settings['image_style'] ?? 'auto';
    $colorPalette = $settings['color_palette'] ?? null; // optional brand color palette (array, from pin_settings_json)
    $boardMode = $settings['board_mode'] ?? 'auto'; // 'separate_per_article' | 'auto'
    $collageEnabled = !empty($settings['collage_enabled']);
    $collageCountSetting = $settings['collage_count'] ?? 'auto'; // 2-6 or 'auto'

    // One pin_batches row represents "all auto-pins for this article batch" — created on first use.
    $articleSettings = get_article_settings($pdo) ?: [];
    $pinBatchId = $settings['pin_batch_token'] ?? null;
    if (!$pinBatchId) {
        $pinBatchId = new_batch_id();
        $pdo->prepare("INSERT INTO pin_batches (user_id, batch_id, name, pinterest_account_id, status, scheduled_at)
            VALUES (?, ?, ?, ?, 'active', NOW())")
            ->execute([$article['user_id'], $pinBatchId, 'Auto Article: ' . ($batch['name'] ?: $batch['batch_id']), $accountId]);
        $pdo->prepare("UPDATE article_batches SET pin_settings_json = JSON_SET(COALESCE(pin_settings_json, '{}'), '$.pin_batch_token', ?) WHERE id = ?")
            ->execute([$pinBatchId, $batch['id']]);
    }

    // Board resolution.
    $boardResolved = null;
    if ($boardMode === 'separate_per_article') {
        $suggestion = ai_generate_board_suggestion($pdo, $article['title']);
        $boardName = $suggestion['ok'] ? $suggestion['name'] : board_name_from_title($article['title']);
        $boardDesc = $suggestion['ok'] ? $suggestion['description'] : '';
        $boardResolved = resolve_board_selection($pdo, $accountId, '__new__', $boardName, $boardDesc);
    } else {
        // "AI auto" — try to match an existing board by name, else create one from the article's intent.
        $accountStmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ?");
        $accountStmt->execute([$accountId]);
        $accountRow = $accountStmt->fetch();
        $boards = $accountRow ? get_boards_for_account($pdo, $accountRow) : [];
        $matched = null;
        $titleLower = strtolower($article['title']);
        foreach ($boards as $b) {
            if (strlen($b['board_name']) > 3 && strpos($titleLower, strtolower($b['board_name'])) !== false) { $matched = $b; break; }
        }
        if ($matched) {
            $boardResolved = resolve_board_selection($pdo, $accountId, 'row:' . $matched['id'], '', '');
        } else {
            $suggestion = ai_generate_board_suggestion($pdo, $article['title']);
            $boardName = $suggestion['ok'] ? $suggestion['name'] : board_name_from_title($article['title']);
            $boardDesc = $suggestion['ok'] ? $suggestion['description'] : '';
            $boardResolved = resolve_board_selection($pdo, $accountId, '__new__', $boardName, $boardDesc);
        }
    }
    if (!$boardResolved || !$boardResolved['ok']) {
        return ['ok' => false, 'error' => 'Could not resolve a board for auto-pinning.'];
    }

    // AI writer for pin title/description/alt/keywords, tailored to this one article.
    $pinCopy = ai_generate_pin_batch($pdo, [$article['title']], false, $article['wp_post_url'] ?: '');
    $copyItem = $pinCopy['ok'] ? $pinCopy['items'][0] : ['title' => $article['title'], 'description' => '', 'alt_text' => '', 'keywords' => ''];

    $batchCreated = new DateTime($batch['created_at']);
    $scheduled = 0;
    $errors = [];

    $limitCheck = check_pin_scheduling_limit($pdo, (int)$article['user_id'], $pinsPerArticle);
    if (!$limitCheck['allowed']) {
        return ['ok' => false, 'scheduled' => 0, 'errors' => [$limitCheck['message']]];
    }

    $prevAt = null;
    $newPinIds = [];
    for ($i = 0; $i < $pinsPerArticle; $i++) {
        $targetDate = new DateTime('today');
        if (!$gapMinutesMode) $targetDate->modify('+' . ($i * $articleGapDays) . ' days');
        $earliest = ($gapMinutesMode && $prevAt) ? (clone $prevAt)->modify("+$gapMinutes minutes") : null;
        if ($earliest) $targetDate = (clone $earliest)->setTime(0, 0);

        // Find a day with room under that day's (ramped) quota, scoped to this article batch's pins.
        $slotIndex = 0;
        $quota = $dailyBase;
        for ($guard = 0; $guard < 60; $guard++) {
            $monthsElapsed = (int)floor($batchCreated->diff($targetDate)->days / 30);
            $quota = max(1, $dailyBase + $monthlyRamp * $monthsElapsed);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE batch_id = ? AND DATE(publish_at) = ?");
            $stmt->execute([$pinBatchId, $targetDate->format('Y-m-d')]);
            $used = (int)$stmt->fetchColumn();
            if ($used < $quota) { $slotIndex = $used; break; }
            $targetDate->modify('+1 day');
        }
        $gapHours = 24 / $quota;
        $publishAt = (clone $targetDate)->setTime(9, 0)->modify('+' . (int)round($slotIndex * $gapHours * 60) . ' minutes');
        if ($earliest && $publishAt < $earliest) $publishAt = clone $earliest;
        $prevAt = clone $publishAt;

        // Image: single or collage, styled per settings.
        $imgSettings = article_image_provider_settings($pdo, $articleSettings, $batch['image_quality'], 'pin');
        $ctaText = $ctaMode === 'none' ? '' : ($ctaMode === 'custom' && $ctaTextSetting !== '' ? $ctaTextSetting : auto_pick_cta($article['title']));
        $style = pin_resolve_style((string)$imageStyle, $article['title']);

        // Article titles are long — print a short, unique headline and base the photo on the category.
        $brief = pin_prepare_image_brief($pdo, $article['title'], article_batch_category_path($pdo, $batch, 'pin'));
        // Single vs collage (and how many different photos) comes from the chosen template.
        $gen = pin_generate_template_images($pdo, $style, $brief['headline'], '', $brief, $sizeKey, (string)$imgSettings['provider'], (string)$imgSettings['model'], (int)$imgSettings['iterations']);
        $genOk = $gen['ok'];
        $bgList = $gen['images'];
        if (!$genOk || empty($bgList)) {
            $errors[] = "Pin " . ($i + 1) . " image generation failed.";
            continue;
        }
        $composited = compose_pin_image($bgList, $brief['headline'], $website, $ctaText, $sizeKey, $style, $colorPalette);
        if (!$composited) {
            $errors[] = "Pin " . ($i + 1) . " composition failed.";
            continue;
        }

        $destDir = __DIR__ . '/../uploads/pins/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'autopin_' . bin2hex(random_bytes(8)) . '.jpg';
        file_put_contents($destDir . $filename, $composited);

        $stmt = $pdo->prepare("INSERT INTO scheduled_pins
            (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, source, batch_id, source_article_id, publish_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'auto_article', ?, ?, ?)");
        $stmt->execute([
            $article['user_id'], $accountId,
            $boardResolved['board_id'], $boardResolved['board_name'], $boardResolved['board_row_id'],
            'uploads/pins/' . $filename,
            $copyItem['title'], $copyItem['description'], $article['wp_post_url'] ?: null, $copyItem['alt_text'],
            $copyItem['keywords'], $pinBatchId, $article['id'],
            $publishAt->format('Y-m-d H:i:s'),
        ]);
        $newPinIds[] = (int)$pdo->lastInsertId();
        $scheduled++;
    }

    // Pins whose time has already passed are published right away; the rest stay scheduled.
    if ($newPinIds) publish_overdue_pins_now($pdo, ['ids' => $newPinIds, 'user_id' => (int)$article['user_id']], 10);

    $pdo->prepare("UPDATE pin_batches SET total_pins = total_pins + ? WHERE batch_id = ?")->execute([$scheduled, $pinBatchId]);
    $pdo->prepare("UPDATE articles SET pin_status = 'scheduled' WHERE id = ?")->execute([$article['id']]);

    return ['ok' => $scheduled > 0, 'scheduled' => $scheduled, 'errors' => $errors];
}

/* ===================== Full pipeline: one article, start to finish ===================== */

/**
 * Runs exactly ONE step of an article's pipeline (queued/legacy-stuck -> draft
 * text; drafted/imaging -> one image; ready -> publish (+ auto-pin)), and
 * always leaves the article in a well-defined status — even on a crash. This
 * is the fix for articles getting silently stuck: previously the whole
 * pipeline ran in one request, so a killed request (a shared-hosting web
 * server timing out a long-running request is the most common cause) could
 * leave an article claimed but with no error and nothing to retry it. Now
 * each request does one short step, and the whole thing is wrapped so even
 * an uncaught PHP error still marks the article failed with a real reason
 * instead of leaving it stuck forever.
 */
function process_article_step(PDO $pdo, array $article, array $batch): array
{
    try {
        switch ($article['status']) {
            case 'queued':
            case 'draft': // legacy stuck row from before this fix — safe to just restart
            case 'drafted': // outline done, writing the next part
            case 'drafting': // claimed but crashed before finishing — stale-lock recovery below (resumes from saved progress)
                return step_draft_article($pdo, $article, $batch);
            case 'imaging':
                return step_image_article($pdo, $article, $batch);
            case 'ready':
            case 'publishing': // claimed but crashed before finishing — stale-lock recovery below
                return step_publish_article($pdo, $article, $batch);
            default:
                return ['ok' => true, 'error' => null, 'more' => false];
        }
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?")
            ->execute(['Unexpected error: ' . $e->getMessage(), $article['id']]);
        return ['ok' => false, 'error' => $e->getMessage(), 'more' => false];
    }
}

/**
 * Step 1 — writing, split into small resumable steps so long articles (18, 25, 30+ ideas, 3,000+ words)
 * never hit a time-out or a cut-off answer:
 *   a) outline (with a plain-list fallback if the JSON can't be read)  → status 'drafted'
 *   b) one call per part: intro · ideas in groups of 6 · FAQs + wrap-up (or one call for short
 *      articles / recipes)                                             → 'drafted' … → 'imaging'
 * Progress is saved in sections_json after every part, so a crash or time-out only repeats that part.
 * A failing part is retried on the next runs; after 3 failed tries the article is marked failed.
 */
const ARTICLE_MAX_TRIES = 3;

function step_draft_article(PDO $pdo, array $article, array $batch): array
{
    // Claim: fresh queued/legacy row, a 'drafted' row between parts, or a stale 'drafting' row (>10 min, crashed).
    $claim = $pdo->prepare("UPDATE articles SET status = 'drafting' WHERE id = ?
        AND (status IN ('queued', 'draft', 'drafted') OR (status = 'drafting' AND updated_at < NOW() - INTERVAL 6 MINUTE))");
    $claim->execute([$article['id']]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Already being processed.', 'more' => true];
    }

    $website = null;
    if ($batch['website_id']) {
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$batch['website_id'], $article['user_id']]);
        $website = $stmt->fetch();
    }
    if (!$website || $website['status'] !== 'connected') {
        $pdo->prepare("UPDATE articles SET status = 'failed', last_error = 'No connected website for this batch.' WHERE id = ?")->execute([$article['id']]);
        return ['ok' => false, 'error' => 'No connected website for this batch.', 'more' => false];
    }

    $raw = (string)($article['sections_json'] ?? '');
    $prog = json_decode($raw, true);
    if (!is_array($prog) && strpos($raw, '{"v":2') === 0) {
        // saved progress exists but can't be read (cut off) — don't silently start over
        return article_step_failed($pdo, $article + ['sections_json' => null], null, 'Saved writing progress was damaged — restarting this article.');
    }
    if (!is_array($prog) || ($prog['v'] ?? 0) !== 2) $prog = null;

    // ---------- a) outline ----------
    if (!$prog) {
        if (get_user_text_credits($pdo, $article['user_id']) < 1) {
            $pdo->prepare("UPDATE articles SET status = 'failed', last_error = 'Text AI credits are low — upgrade your plan to get more.' WHERE id = ?")->execute([$article['id']]);
            return ['ok' => false, 'error' => 'Text AI credits are low — upgrade your plan to get more.', 'more' => false];
        }
        $ctx = ['target_words' => aad_target_words($batch), 'refs_brief' => '', 'avoid_titles' => []];
        try {
            $ctx['refs_brief'] = aad_reference_brief(aad_find_references($pdo, $batch['article_type'] === 'recipe' ? 'recipe' : 'ideas', (string)$article['title'], 2));
            $prev = $pdo->prepare("SELECT title FROM articles WHERE user_id = ? AND website_id <=> ? AND id <> ? AND status = 'published' ORDER BY id DESC LIMIT 25");
            $prev->execute([$article['user_id'], $article['website_id'], $article['id']]);
            $ctx['avoid_titles'] = array_values(array_filter(array_column($prev->fetchAll(), 'title')));
        } catch (Throwable $e) { /* references are optional */ }

        $ideaCount = extract_idea_count_from_title($article['title']);
        $outline = ai_generate_article_outline($pdo, $article['title'], $batch['article_type'], $ideaCount, $ctx);
        if (!$outline['ok'] && $batch['article_type'] !== 'recipe') {
            $outline = ai_generate_article_outline_plain($pdo, $article['title'], $ideaCount, $ctx);   // simpler format, parsed by us
        }
        if (!$outline['ok']) return article_step_failed($pdo, $article, null, 'Outline: ' . $outline['error']);

        $sec = $outline['sections'];
        $plan = article_draft_plan($batch['article_type'], $sec, $ctx['target_words']);
        $prog = ['v' => 2, 'outline' => $sec, 'plan' => $plan, 'done' => 0, 'parts' => [], 'fails' => 0,
            'ctx' => ['target_words' => $ctx['target_words'], 'refs_brief' => $ctx['refs_brief']]];
        article_save_progress($pdo, (int)$article['id'], $prog, 'drafted', null);
        return ['ok' => true, 'error' => null, 'more' => true];
    }

    // ---------- b) next part ----------
    $i = (int)$prog['done'];
    $plan = $prog['plan'];
    if ($i < count($plan)) {
        $r = article_write_part($pdo, (string)$article['title'], $batch['article_type'], $prog['outline'], $plan[$i], $prog['ctx']);
        if (!$r['ok'] && !empty($r['split']) && ($plan[$i]['type'] ?? '') === 'items' && $plan[$i]['to'] > $plan[$i]['from']) {
            // answer was cut off — split this group in two and carry on (not counted as a failure)
            $mid = intdiv($plan[$i]['from'] + $plan[$i]['to'], 2);
            array_splice($plan, $i, 1, [['type' => 'items', 'from' => $plan[$i]['from'], 'to' => $mid], ['type' => 'items', 'from' => $mid + 1, 'to' => $plan[$i]['to']]]);
            $prog['plan'] = $plan;
            article_save_progress($pdo, (int)$article['id'], $prog, 'drafted', null);
            return ['ok' => true, 'error' => null, 'more' => true];
        }
        if (!$r['ok']) return article_step_failed($pdo, $article, $prog, 'Draft part ' . ($i + 1) . '/' . count($plan) . ': ' . $r['error']);
        $prog['parts'][$i] = $r['html'];
        $prog['done'] = $i + 1;
        $prog['fails'] = 0;
        if ($prog['done'] < count($plan)) {
            article_save_progress($pdo, (int)$article['id'], $prog, 'drafted', null);
            return ['ok' => true, 'error' => null, 'more' => true];
        }
    }

    // ---------- all parts written → assemble ----------
    $html = trim(implode("\n\n", $prog['parts']));
    preg_match_all('/\{\{IMAGE:(\d+)\}\}/', $html, $m);
    $imageSlots = !empty($m[1]) ? max(array_map('intval', $m[1])) : 0;
    deduct_text_credits($pdo, $article['user_id'], 1);
    $pdo->prepare("UPDATE articles SET sections_json = ?, content = ?, image_slots_needed = ?, image_progress_json = ?, status = 'imaging', last_error = NULL WHERE id = ?")
        ->execute([json_encode($prog['outline'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), $html, $imageSlots, null, $article['id']]);
    return ['ok' => true, 'error' => null, 'more' => true];
}

function article_save_progress(PDO $pdo, int $articleId, array $prog, string $status, ?string $note): void
{
    // invalid UTF-8 from a model must never turn the saved progress into nothing (that restarted the article)
    $json = json_encode($prog, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    if ($json === false) throw new RuntimeException('Could not save writing progress: ' . json_last_error_msg());
    $pdo->prepare("UPDATE articles SET sections_json = ?, status = ?, last_error = ? WHERE id = ?")
        ->execute([$json, $status, $note, $articleId]);
    // read it back: a too-small column would cut the JSON off and silently restart the article
    $chk = $pdo->prepare("SELECT CHAR_LENGTH(sections_json) FROM articles WHERE id = ?");
    $chk->execute([$articleId]);
    if ((int)$chk->fetchColumn() < mb_strlen($json)) {
        try { $pdo->exec("ALTER TABLE articles MODIFY sections_json LONGTEXT NULL, MODIFY content LONGTEXT NULL"); } catch (Throwable $e) {}
        $pdo->prepare("UPDATE articles SET sections_json = ? WHERE id = ?")->execute([$json, $articleId]);
    }
}

/** A part failed: retry on the next run (up to ARTICLE_MAX_TRIES), then mark the article failed. */
function article_step_failed(PDO $pdo, array $article, ?array $prog, string $error): array
{
    $fails = (int)($prog['fails'] ?? ($article['_outline_fails'] ?? 0)) + 1;
    if ($prog === null) {
        // no progress yet (outline): remember tries in a tiny progress stub
        $stub = json_decode((string)($article['sections_json'] ?? ''), true);
        $fails = (int)(is_array($stub) && isset($stub['outline_fails']) ? $stub['outline_fails'] : 0) + 1;
        if ($fails >= ARTICLE_MAX_TRIES) {
            $pdo->prepare("UPDATE articles SET status = 'failed', sections_json = NULL, last_error = ? WHERE id = ?")
                ->execute([$error . " (after $fails tries)", $article['id']]);
            return ['ok' => false, 'error' => $error, 'more' => false];
        }
        $pdo->prepare("UPDATE articles SET status = 'queued', sections_json = ?, last_error = ? WHERE id = ?")
            ->execute([json_encode(['outline_fails' => $fails]), "Retrying ($fails/" . ARTICLE_MAX_TRIES . "): $error", $article['id']]);
        return ['ok' => false, 'error' => $error, 'more' => true];
    }
    $prog['fails'] = $fails;
    if ($fails >= ARTICLE_MAX_TRIES) {
        $pdo->prepare("UPDATE articles SET status = 'failed', sections_json = ?, last_error = ? WHERE id = ?")
            ->execute([json_encode($prog), $error . " (after $fails tries)", $article['id']]);
        return ['ok' => false, 'error' => $error, 'more' => false];
    }
    article_save_progress($pdo, (int)$article['id'], $prog, 'drafted', "Retrying ($fails/" . ARTICLE_MAX_TRIES . "): $error");
    return ['ok' => false, 'error' => $error, 'more' => true];
}

/** How to split the writing: one call for short articles/recipes; intro · ideas in 6s · FAQs+wrap-up otherwise. */
function article_draft_plan(string $type, array $outline, ?int $target): array
{
    if ($type === 'recipe') return [['type' => 'full']];
    $n = count($outline['items'] ?? []);
    if ($n <= 10 && (!$target || $target <= 1800)) return [['type' => 'full']];
    $plan = [['type' => 'intro']];
    $size = ($target && $target / max(1, $n) > 220) ? 4 : 6;
    for ($f = 1; $f <= $n; $f += $size) $plan[] = ['type' => 'items', 'from' => $f, 'to' => min($n, $f + $size - 1)];
    $plan[] = ['type' => 'end'];
    return $plan;
}

/** Writes one part of the article. Returns ['ok', 'html', 'error', 'split' => true when the answer was cut off]. */
function article_write_part(PDO $pdo, string $title, string $type, array $outline, array $part, array $ctx): array
{
    if ($part['type'] === 'full') {
        $d = ai_generate_article_draft($pdo, $title, $outline, $type, $ctx);
        return ['ok' => $d['ok'], 'html' => $d['html'] ?? '', 'error' => $d['error'] ?? null];
    }
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) return ['ok' => false, 'html' => '', 'error' => 'No AI text model is configured for Article Write yet.'];

    $items = $outline['items'] ?? [];
    $n = count($items);
    $target = $ctx['target_words'] ?? null;
    $perIdea = $target ? max(120, (int)(($target - 600) / max(1, $n))) : 170;
    $base = 'You are writing one part of a longer, genuinely useful Pinterest-style blog post. Output clean HTML only '
        . '(h2/h3/p/ul/li/strong, no html/head/body tags, no inline styles, no img tags, no markdown fences). '
        . article_quality_rules();
    $head = "Article title: $title\nReader intent: " . ($outline['intent'] ?? '') . "\n";

    if ($part['type'] === 'intro') {
        $sys = $base . ' Write ONLY the introduction: 2-4 <p> paragraphs, no headings. Speak to the reader\'s intent and '
            . 'promise what they will get from the list that follows.';
        $user = $head . 'Intro hook: ' . ($outline['intro'] ?? '') . "\nThe article lists $n ideas." . (!empty($ctx['refs_brief']) ? "\n\n" . $ctx['refs_brief'] : '');
        $tokens = 1500;
    } elseif ($part['type'] === 'items') {
        $list = '';
        for ($k = $part['from']; $k <= $part['to']; $k++) {
            $it = $items[$k - 1] ?? null;
            if ($it) $list .= "$k. " . ($it['heading'] ?? '') . ' — ' . ($it['notes'] ?? '') . "\n";
        }
        $sys = $base . ' Write ONLY the idea sections listed, in order. For each: <h2>N. Heading</h2> (keep the given number N), '
            . 'then the token {{IMAGE:N}} on its own line (same N), then about ' . $perIdea . ' words in 2-4 paragraphs: what the idea is, '
            . 'exactly what makes it work (specific pieces, colours, shapes, techniques), who it suits or when to use it, and one '
            . 'direct, actionable tip to the reader. Do not write an intro, FAQs or a conclusion.';
        $user = $head . "Write these sections (of $n in total):\n$list" . (!empty($ctx['refs_brief']) && $part['from'] === 1 ? "\n\n" . $ctx['refs_brief'] : '');
        $tokens = (int)min(9000, 900 + ($part['to'] - $part['from'] + 1) * ($perIdea * 2 + 150));
    } else { // end
        $faqs = '';
        foreach (($outline['faqs'] ?? []) as $f) $faqs .= 'Q: ' . ($f['question'] ?? '') . "\nA: " . ($f['answer'] ?? '') . "\n";
        $sys = $base . ' Write ONLY the ending: an <h2>FAQs</h2> section (each question as an <h3> followed by a <p> answer, 5 questions), '
            . 'then an <h2>Wrap Up</h2> section with 1-2 short paragraphs tying the list together.';
        $user = $head . ($faqs ? "FAQs to answer (improve them if needed):\n$faqs" : "Write 5 real reader questions about this topic and answer them.\n")
            . 'Closing thought: ' . ($outline['wrap_up'] ?? '');
        $tokens = 2500;
    }
    $r = ai_generate_text($pdo, $provider, $model, $sys, $user, $tokens);
    if (!$r['ok']) return ['ok' => false, 'html' => '', 'error' => $r['error']];
    $html = trim(preg_replace('/^```(?:html)?\s*|\s*```$/', '', trim($r['text'])));
    if ($html === '') return ['ok' => false, 'html' => '', 'error' => 'The AI returned an empty answer.'];
    if (!empty($r['truncated'])) return ['ok' => false, 'html' => '', 'error' => 'Answer was cut off (too long).', 'split' => true];
    if ($part['type'] === 'items') {
        // every idea of this group must be there, with its image token
        for ($k = $part['from']; $k <= $part['to']; $k++) {
            if (strpos($html, '{{IMAGE:' . $k . '}}') === false) {
                if (preg_match('#<h2[^>]*>\s*' . $k . '[\.\)]#', $html)) {
                    $html = preg_replace('#(<h2[^>]*>\s*' . $k . '[\.\)].*?</h2>)#s', "$1\n{{IMAGE:$k}}", $html, 1);
                } else {
                    return ['ok' => false, 'html' => '', 'error' => "Idea $k is missing from the answer.", 'split' => $part['to'] > $part['from']];
                }
            }
        }
    }
    return ['ok' => true, 'html' => $html, 'error' => null];
}

/** Fallback outline for listicles: numbered plain lines are far harder for a model to get wrong than JSON. */
function ai_generate_article_outline_plain(PDO $pdo, string $title, int $ideaCount, array $ctx): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['text_provider'] ?? null;
    $model = $settings['text_model'] ?? null;
    if (!$provider) return ['ok' => false, 'sections' => [], 'error' => 'No AI text model is configured for Article Write yet.'];
    $sys = 'You outline genuinely useful Pinterest-style list articles. ' . article_quality_rules()
        . " Reply in plain text only, exactly in this format:\nINTENT: who is searching and what they need\nINTRO: 2 sentence hook\n"
        . "1. Idea title — what makes it good, how to do/wear/use it\n2. …\n(exactly $ideaCount numbered ideas)\n"
        . "Q: reader question | A: short answer\n(5 of these)\nWRAP: closing thought";
    $r = ai_generate_text($pdo, $provider, $model, $sys, "Article title: $title" . (!empty($ctx['refs_brief']) ? "\n\n" . $ctx['refs_brief'] : ''), (int)min(9000, 1500 + $ideaCount * 160));
    if (!$r['ok']) return ['ok' => false, 'sections' => [], 'error' => $r['error']];
    $out = ['intent' => '', 'intro' => '', 'items' => [], 'faqs' => [], 'wrap_up' => ''];
    foreach (preg_split('/\R/', $r['text']) as $line) {
        $line = trim(strip_tags($line), " \t*#");
        if ($line === '') continue;
        if (preg_match('/^INTENT:\s*(.+)$/i', $line, $m)) $out['intent'] = $m[1];
        elseif (preg_match('/^INTRO:\s*(.+)$/i', $line, $m)) $out['intro'] = $m[1];
        elseif (preg_match('/^WRAP(?:[ _-]?UP)?:\s*(.+)$/i', $line, $m)) $out['wrap_up'] = $m[1];
        elseif (preg_match('/^Q:\s*(.+?)\s*\|\s*A:\s*(.+)$/i', $line, $m)) $out['faqs'][] = ['question' => $m[1], 'answer' => $m[2]];
        elseif (preg_match('/^\d{1,3}[\.\)]\s*(.+?)(?:\s+[—–-]\s+|:\s+)(.+)$/u', $line, $m)) $out['items'][] = ['heading' => trim($m[1], ' "'), 'notes' => $m[2]];
        elseif (preg_match('/^\d{1,3}[\.\)]\s*(.+)$/', $line, $m)) $out['items'][] = ['heading' => trim($m[1], ' "'), 'notes' => ''];
    }
    if (count($out['items']) < max(3, (int)floor($ideaCount * 0.6))) {
        return ['ok' => false, 'sections' => [], 'error' => 'Could not read the outline (got ' . count($out['items']) . " of $ideaCount ideas)."];
    }
    $out['items'] = array_slice($out['items'], 0, $ideaCount);
    if ($out['intro'] === '') $out['intro'] = $title;
    return ['ok' => true, 'sections' => $out, 'error' => null];
}

/** Step 2 (called once per image): generates the next needed image -> stays 'imaging' or advances to 'ready'. */
function step_image_article(PDO $pdo, array $article, array $batch): array
{
    // Re-read fresh in case a previous step in this same batch run already advanced it.
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$article['id']]);
    $article = $stmt->fetch();
    if (!$article || $article['status'] !== 'imaging') {
        return ['ok' => true, 'error' => null, 'more' => true];
    }
    $result = generate_next_article_image($pdo, $article, $batch);
    return ['ok' => $result['ok'], 'error' => $result['error'], 'more' => $result['more']];
}

/** Step 3: publish to WordPress, then (if Pin Auto) schedule this article's pins. */
function step_publish_article(PDO $pdo, array $article, array $batch): array
{
    $claim = $pdo->prepare("UPDATE articles SET status = 'publishing' WHERE id = ?
        AND (status = 'ready' OR (status = 'publishing' AND updated_at < NOW() - INTERVAL 10 MINUTE))");
    $claim->execute([$article['id']]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Already being processed.', 'more' => true];
    }

    $website = null;
    if ($batch['website_id']) {
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$batch['website_id'], $article['user_id']]);
        $website = $stmt->fetch();
    }
    if (!$website || $website['status'] !== 'connected') {
        $pdo->prepare("UPDATE articles SET status = 'failed', last_error = 'No connected website for this batch.' WHERE id = ?")->execute([$article['id']]);
        return ['ok' => false, 'error' => 'No connected website for this batch.', 'more' => false];
    }

    $sections = json_decode($article['sections_json'] ?? '', true) ?: [];
    $progress = json_decode($article['image_progress_json'] ?? '', true) ?: ['featured_path' => null, 'inline' => []];
    $intro = $sections['intro'] ?? $article['title'];
    $metaDescription = ai_generate_meta_description($pdo, $article['title'], $intro);

    $publishResult = publish_article_to_wordpress($pdo, $article, $batch, $website, $article['content'] ?? '', $progress['featured_path'] ?? null, $progress['inline'] ?? [], $metaDescription);
    log_event($pdo, 'ai', "Auto Article: '{$article['title']}' " . ($publishResult['ok'] ? 'published' : 'failed to publish'), $article['user_id']);

    if (!$publishResult['ok']) {
        return ['ok' => false, 'error' => $publishResult['error'], 'more' => false];
    }

    if ($batch['publish_mode'] === 'pin_auto') {
        try {
            $article['wp_post_url'] = $publishResult['wp_post_url'];
            $pinResult = auto_schedule_pins_for_article($pdo, $article, $batch);
            if (!$pinResult['ok']) {
                log_event($pdo, 'system', "Auto Article: pin scheduling failed for article #{$article['id']}: " . ($pinResult['error'] ?? 'unknown'), $article['user_id']);
            }
        } catch (Throwable $e) {
            // The article itself already published successfully — a pin-scheduling problem
            // shouldn't undo that; just log it so it's visible without failing the article.
            log_event($pdo, 'system', "Auto Article: pin scheduling crashed for article #{$article['id']}: " . $e->getMessage(), $article['user_id']);
        }
    }

    return ['ok' => true, 'error' => null, 'more' => false, 'wp_post_url' => $publishResult['wp_post_url']];
}

/* ===================== Shared step-loop (used by cron, the manual button, and the background ticks) ===================== */

/**
 * Every batch runs on its own: one batch's articles never wait for another batch to finish.
 *   - Each active batch with due work gets its own background worker (cron/article-worker.php),
 *     so several users' batches are written / illustrated / published at the same time.
 *   - A per-batch lock file makes sure only one process ever works on a batch at once
 *     (the worker, the cron fallback loop, or the "Process Now" button).
 *   - The cron / runner fallback loop (run_due_article_steps) goes round-robin across batches,
 *     one step per batch in turn, so even without workers no batch is stuck behind another.
 */
if (!defined('ARTICLE_MAX_PARALLEL_BATCHES')) define('ARTICLE_MAX_PARALLEL_BATCHES', 20);
if (!defined('ARTICLE_DEFAULT_BATCHES_PER_USER')) define('ARTICLE_DEFAULT_BATCHES_PER_USER', 5);

/**
 * Admin → Articles Schedule → Batch Limits. Table article_batch_limits:
 *   user_id = 0   → default "batches at once" for every user (5)
 *   user_id = -1  → total batches at once on the whole server (ARTICLE_MAX_PARALLEL_BATCHES)
 *   user_id > 0   → that user's own limit (overrides the default)
 */
function article_batch_limits_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_batch_limits (
            user_id INT NOT NULL PRIMARY KEY,
            max_batches INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* created by migrate.php */ }
}

/** All saved limits: [user_id => max_batches] (includes the 0 / -1 rows). */
function article_batch_limits_all(PDO $pdo): array
{
    article_batch_limits_ensure_schema($pdo);
    try {
        return array_map('intval', $pdo->query("SELECT user_id, max_batches FROM article_batch_limits")->fetchAll(PDO::FETCH_KEY_PAIR));
    } catch (Throwable $e) {
        return [];
    }
}

function article_batch_limit_default(array $limits): int
{
    return max(1, (int)($limits[0] ?? ARTICLE_DEFAULT_BATCHES_PER_USER));
}

function article_batch_limit_total(array $limits): int
{
    return max(1, (int)($limits[-1] ?? ARTICLE_MAX_PARALLEL_BATCHES));
}

/** How many of this user's batches may run at the same time. */
function article_batch_limit_for_user(array $limits, int $userId): int
{
    return isset($limits[$userId]) && $limits[$userId] > 0 ? (int)$limits[$userId] : article_batch_limit_default($limits);
}

/** Saves one limit row; $max = null removes a user's own limit (back to the default). */
function article_batch_limit_save(PDO $pdo, int $userId, ?int $max): void
{
    article_batch_limits_ensure_schema($pdo);
    if ($max === null || $max <= 0) {
        if ($userId > 0) $pdo->prepare("DELETE FROM article_batch_limits WHERE user_id = ?")->execute([$userId]);
        return;
    }
    $pdo->prepare("INSERT INTO article_batch_limits (user_id, max_batches) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE max_batches = VALUES(max_batches)")->execute([$userId, min(100, $max)]);
}

/** SQL condition for "this article has work to do right now" (table alias a). */
function article_due_condition_sql(): string
{
    return "(
            (a.status = 'queued' AND a.scheduled_for <= ?)
            OR a.status IN ('drafting', 'drafted', 'imaging', 'ready', 'publishing', 'draft')
        )
        AND NOT (a.status = 'drafting' AND a.updated_at > NOW() - INTERVAL 6 MINUTE)   -- being written right now
        AND NOT (COALESCE(a.last_error, '') LIKE 'Retrying%' AND a.updated_at > NOW() - INTERVAL 3 MINUTE)";   // short pause between retries (COALESCE: a NULL here used to hide every article for 3 min after each step)
}

/** Active batches that have an article due right now, oldest work first: [batch_id => user_id]. */
function due_article_batches(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT a.batch_id, b.user_id, MIN(a.scheduled_for) AS first_due, MIN(a.id) AS first_id FROM articles a
        JOIN article_batches b ON b.id = a.batch_id
        WHERE b.status = 'active' AND " . article_due_condition_sql() . "
        GROUP BY a.batch_id, b.user_id
        ORDER BY first_due ASC, first_id ASC");
    $stmt->execute([date('Y-m-d')]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[(int)$r['batch_id']] = (int)$r['user_id'];
    return $out;
}

function due_article_batch_ids(PDO $pdo): array
{
    return array_keys(due_article_batches($pdo));
}

/**
 * Due batches that may run right now: each user's oldest N due batches (N = that user's
 * "batches at once" limit from Admin → Batch Limits). A user's other batches wait for a slot.
 */
function allowed_article_batch_ids(PDO $pdo): array
{
    $limits = article_batch_limits_all($pdo);
    $perUser = [];
    $out = [];
    foreach (due_article_batches($pdo) as $batchId => $userId) {
        $perUser[$userId] = ($perUser[$userId] ?? 0) + 1;
        if ($perUser[$userId] <= article_batch_limit_for_user($limits, $userId)) $out[] = $batchId;
    }
    return $out;
}

/** May this batch run right now (inside its user's "batches at once" limit)? Batches with nothing due count as allowed. */
function article_batch_allowed(PDO $pdo, int $batchDbId): bool
{
    $due = due_article_batches($pdo);
    if (!isset($due[$batchDbId])) return true;
    return in_array($batchDbId, allowed_article_batch_ids($pdo), true);
}

/** The next article to step in one batch (articles already mid-pipeline first, then the oldest due one). */
function next_due_article_for_batch(PDO $pdo, int $batchDbId, array $skipIds = []): ?array
{
    $skip = $skipIds ? ' AND a.id NOT IN (' . implode(',', array_map('intval', $skipIds)) . ')' : '';
    $stmt = $pdo->prepare("SELECT a.* FROM articles a
        WHERE a.batch_id = ? AND " . article_due_condition_sql() . "
        $skip
        ORDER BY (a.status = 'queued') ASC, a.scheduled_for ASC, a.id ASC
        LIMIT 1");
    $stmt->execute([$batchDbId, date('Y-m-d')]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function article_batch_lock_path(int $batchDbId): string
{
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/.article_batch_' . $batchDbId . '.lock';
}

/** Takes this batch's lock without waiting. Returns the handle, or null if someone else is working on the batch. */
function article_batch_lock_try(int $batchDbId)
{
    $h = @fopen(article_batch_lock_path($batchDbId), 'c');
    if (!$h) return null;
    if (!flock($h, LOCK_EX | LOCK_NB)) { fclose($h); return null; }
    return $h;
}

function article_batch_lock_release($h): void
{
    if (!$h) return;
    flock($h, LOCK_UN);
    fclose($h);
}

/** Is a process working on this batch right now? */
function article_batch_busy(int $batchDbId): bool
{
    $h = article_batch_lock_try($batchDbId);
    if (!$h) return true;
    article_batch_lock_release($h);
    return false;
}

/** Runs one step for one article and returns its log entry. */
function article_run_one_step(PDO $pdo, array $article): ?array
{
    $batchStmt = $pdo->prepare("SELECT * FROM article_batches WHERE id = ?");
    $batchStmt->execute([$article['batch_id']]);
    $batch = $batchStmt->fetch();
    if (!$batch || $batch['status'] !== 'active') return null;

    $GLOBALS['currentArticleId'] = (int)$article['id'];
    $result = process_article_step($pdo, $article, $batch);
    $GLOBALS['currentArticleId'] = null;
    return ['article_id' => $article['id'], 'title' => $article['title'], 'ok' => $result['ok'], 'more' => $result['more'], 'error' => $result['error'] ?? null];
}

/**
 * Runs up to $maxSteps pipeline steps across ALL users' due/in-progress articles, system-wide,
 * going round-robin over the batches (one step for batch A, one for batch B, …) so no batch
 * waits for another batch to finish (only each user's "batches at once" limit applies). Batches that a background worker is already running are
 * skipped here (the worker handles them). Used by cron/article-scheduler.php, the built-in runner
 * and Admin → Articles Schedule.
 */
function run_due_article_steps(PDO $pdo, int $maxSteps = 10): array
{
    $processed = 0;
    $log = [];
    $seenFail = [];   // an article that failed a step this run waits for the next run (so retries are spread out)
    $doneBatches = []; // batches with nothing more to do in this run (or busy in a worker)

    while ($processed < $maxSteps) {
        ensure_db_connection($pdo);
        $batchIds = array_values(array_diff(allowed_article_batch_ids($pdo), $doneBatches));
        if (!$batchIds) break;

        foreach ($batchIds as $batchDbId) {
            if ($processed >= $maxSteps) break;
            $lock = article_batch_lock_try($batchDbId);
            if (!$lock) { $doneBatches[] = $batchDbId; continue; }   // its own worker is on it
            try {
                $article = next_due_article_for_batch($pdo, $batchDbId, $seenFail);
                if (!$article) { $doneBatches[] = $batchDbId; continue; }
                $entry = article_run_one_step($pdo, $article);
                if (!$entry) { $doneBatches[] = $batchDbId; continue; }
                $processed++;
                if (!$entry['ok']) $seenFail[] = (int)$article['id'];
                $log[] = $entry;
            } finally {
                article_batch_lock_release($lock);
            }
        }
    }

    return ['steps_run' => $processed, 'log' => $log];
}

/**
 * Works through ONE batch until it has nothing due right now or $seconds run out.
 * The caller must already hold this batch's lock (see cron/article-worker.php).
 * Returns ['steps_run', 'log', 'more' => bool (time ran out with work still waiting)].
 */
function run_batch_article_steps(PDO $pdo, int $batchDbId, int $seconds = 540): array
{
    $end = time() + $seconds;
    $processed = 0;
    $log = [];
    $seenFail = [];
    while (true) {
        ensure_db_connection($pdo);
        $article = next_due_article_for_batch($pdo, $batchDbId, $seenFail);
        if (!$article) return ['steps_run' => $processed, 'log' => $log, 'more' => false];
        // Over the user's "batches at once" limit (e.g. the admin lowered it): wait for a free slot.
        // Only between articles, so an article that was started is always finished first.
        if ($article['status'] === 'queued' && !article_batch_allowed($pdo, $batchDbId)) return ['steps_run' => $processed, 'log' => $log, 'more' => false];
        if (time() >= $end) return ['steps_run' => $processed, 'log' => $log, 'more' => true];
        $entry = article_run_one_step($pdo, $article);
        if (!$entry) return ['steps_run' => $processed, 'log' => $log, 'more' => false];   // batch stopped
        $processed++;
        if (!$entry['ok']) $seenFail[] = (int)$article['id'];
        $log[] = $entry;
    }
}

/** Starts the background worker for one batch (returns right away; the worker keeps going on the server). */
function article_batch_worker_start(int $batchDbId): bool
{
    if (!function_exists('curl_init') || !function_exists('scheduler_web_key')) return false;
    $url = rtrim(APP_URL, '/') . '/cron/article-worker.php?batch=' . $batchDbId . '&key=' . scheduler_web_key() . '&t=' . time();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 1500, CURLOPT_CONNECTTIMEOUT_MS => 1200,
        CURLOPT_NOSIGNAL => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'AutomatedPin-ArticleWorker',
    ]);
    curl_exec($ch);   // times out on purpose — the worker keeps working after we hang up
    curl_close($ch);
    return true;
}

/**
 * Makes sure every batch with due work has its own worker running, so all users' batches are
 * written and published at the same time. Limits (Admin → Articles Schedule → Batch Limits):
 * each user runs at most their "batches at once" (default 5), and the whole server at most
 * the "total at once" number.
 * $onlyBatch: start just this batch (e.g. right after it was created), if its user has a free slot.
 * Returns how many workers were started.
 */
function article_batch_workers_kick(PDO $pdo, ?int $onlyBatch = null): int
{
    if (function_exists('scheduler_runner_enabled') && !scheduler_runner_enabled()) return 0;
    $allowed = allowed_article_batch_ids($pdo);
    $total = article_batch_limit_total(article_batch_limits_all($pdo));
    $running = 0;
    $idle = [];
    foreach (due_article_batch_ids($pdo) as $id) {
        if (article_batch_busy($id)) $running++;
        elseif (in_array($id, $allowed, true) && ($onlyBatch === null || $id === $onlyBatch)) $idle[] = $id;
    }
    $started = 0;
    foreach ($idle as $id) {
        if ($running + $started >= $total) break;
        if (article_batch_worker_start($id)) $started++;
    }
    return $started;
}
