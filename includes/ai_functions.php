<?php
/**
 * Multi-provider AI helpers for the Article Writer feature.
 *
 * Text providers : chatgpt (OpenAI), claude (Anthropic), openrouter, deepinfra, google (Gemini)
 * Image providers : deepinfra (FLUX-1 schnell/dev, FLUX-2 klein-9b/pro/max, Nano Banana 2 Lite), cloudflare (free, via rotating Worker accounts)
 */

require_once __DIR__ . '/image_model_functions.php';
require_once __DIR__ . '/image_category_functions.php';
require_once __DIR__ . '/pin_styles_extra.php';
require_once __DIR__ . '/pin_templates_more.php';
require_once __DIR__ . '/pin_templates_60.php';
require_once __DIR__ . '/pin_templates_100.php';
require_once __DIR__ . '/pin_templates_300.php';
require_once __DIR__ . '/pin_template_registry.php';

function get_ai_provider_row(PDO $pdo, string $provider, string $modelType): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE provider = ? AND model_type = ?");
    $stmt->execute([$provider, $modelType]);
    return $stmt->fetch() ?: null;
}

/**
 * BYOK ("Use your own AI model") — Settings → AI Api / Image Generation Models.
 * A team member's BYOK is the TEAM OWNER's (shared resource, like credits — see
 * team_functions.php). Returns null if the user hasn't enabled + saved a key for $type
 * ('text' or 'image'), in which case callers fall back to the admin's configured model.
 */
function get_user_ai_credentials(PDO $pdo, int $userId, string $type): ?array
{
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("SELECT * FROM user_ai_settings WHERE user_id = ?");
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$row) return null;
    $col = $type === 'image' ? 'image' : 'text';
    $enabled = (bool)($row["{$col}_enabled"] ?? 0);
    $key = trim((string)($row["{$col}_api_key"] ?? ''));
    if (!$enabled || $key === '') return null;
    return [
        'provider' => $row["{$col}_provider"] ?: 'openrouter',
        'api_key' => $key,
        'model' => trim((string)($row["{$col}_model"] ?? '')),
    ];
}

function get_user_ai_settings_row(PDO $pdo, int $userId): array
{
    $defaults = [
        'text_enabled' => 0, 'text_api_key' => '', 'text_model' => '',
        'image_enabled' => 0, 'image_api_key' => '', 'image_model' => '',
    ];
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("SELECT * FROM user_ai_settings WHERE user_id = ?");
        $stmt->execute([$ownerId]);
        return $stmt->fetch() ?: $defaults;
    } catch (Throwable $e) {
        return $defaults;
    }
}

function save_user_ai_settings(PDO $pdo, int $userId, array $fields): void
{
    $stmt = $pdo->prepare("INSERT INTO user_ai_settings (user_id, text_enabled, text_api_key, text_model, image_enabled, image_api_key, image_model)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE text_enabled = VALUES(text_enabled), text_api_key = VALUES(text_api_key), text_model = VALUES(text_model),
            image_enabled = VALUES(image_enabled), image_api_key = VALUES(image_api_key), image_model = VALUES(image_model)");
    $stmt->execute([
        $userId,
        !empty($fields['text_enabled']) ? 1 : 0, trim($fields['text_api_key'] ?? ''), trim($fields['text_model'] ?? ''),
        !empty($fields['image_enabled']) ? 1 : 0, trim($fields['image_api_key'] ?? ''), trim($fields['image_model'] ?? ''),
    ]);
}

function get_article_settings(PDO $pdo): ?array
{
    return $pdo->query("SELECT * FROM article_settings ORDER BY id DESC LIMIT 1")->fetch() ?: null;
}

/** Load one of a user's articles, with sections_json decoded into a 'sections' array. */
function load_article(PDO $pdo, int $id, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if ($row) $row['sections'] = json_decode($row['sections_json'] ?? '[]', true) ?: [];
    return $row ?: null;
}

/** Generic HTTP JSON POST used by every text/image provider below. */
function ai_http_post(string $url, array $headers, array $body, int $timeout = 90): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'data' => null, 'error' => $curlError];
    }
    $decoded = json_decode($response, true);
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'data' => $decoded, 'error' => $httpCode >= 300 ? $response : null];
}

/**
 * Ask the configured text model to generate text. Returns ['ok'=>bool, 'text'=>string, 'error'=>?string].
 *
 * If $userId is given and that user (or their team owner) has enabled their own OpenRouter
 * key under Settings → AI Api, it's tried FIRST, on their own model choice (or an automatic
 * OpenRouter model if left blank). If that call fails or errors (rate limit, invalid key,
 * etc.) it automatically falls back to the site's own $provider/$model — the user's request
 * still completes, it just uses the platform's model for that one call.
 */
function ai_generate_text(PDO $pdo, string $provider, string $model, string $systemPrompt, string $userPrompt, int $maxTokens = 3000, ?int $userId = null): array
{
    if ($userId) {
        $byok = get_user_ai_credentials($pdo, $userId, 'text');
        if ($byok) {
            $byokResult = ai_generate_text_raw($pdo, $byok['provider'], $byok['model'] ?: 'openrouter/auto', $systemPrompt, $userPrompt, $maxTokens, $byok['api_key']);
            if ($byokResult['ok']) {
                $byokResult['ai_source'] = 'user';
                return $byokResult;
            }
            log_event($pdo, 'system', 'User\'s own AI text key failed (' . ($byokResult['error'] ?? 'unknown error') . ') — falling back to the site model.', $userId);
        }
    }
    $result = ai_generate_text_raw($pdo, $provider, $model, $systemPrompt, $userPrompt, $maxTokens);
    $result['ai_source'] = 'admin';
    return $result;
}


/** Removes "thinking" blocks some reasoning models put before the answer. */
function ai_strip_thinking(string $text): string
{
    $text = preg_replace('#<think(?:ing)?>.*?</think(?:ing)?>#si', '', $text);
    // an unclosed <think> (answer cut off) — keep only what follows the last closing tag, if any
    if (stripos($text, '<think') !== false && stripos($text, '</think') === false) $text = preg_replace('#<think(?:ing)?>.*$#si', '', $text);
    return trim($text);
}

/** Does the actual provider call. $apiKeyOverride bypasses the ai_providers (admin) lookup — used for BYOK. */
function ai_generate_text_raw(PDO $pdo, string $provider, string $model, string $systemPrompt, string $userPrompt, int $maxTokens = 3000, ?string $apiKeyOverride = null): array
{
    if ($apiKeyOverride !== null) {
        $apiKey = $apiKeyOverride;
    } else {
        $row = get_ai_provider_row($pdo, $provider, 'text');
        if (!$row || empty($row['api_key'])) {
            return ['ok' => false, 'text' => '', 'error' => "No API key configured for text provider '$provider'."];
        }
        $apiKey = $row['api_key'];
    }

    // Long answers take longer: allow up to ~5 minutes for big outputs instead of a flat 90 s.
    $timeout = (int)max(90, min(300, 60 + $maxTokens / 45));

    switch ($provider) {
        case 'chatgpt':
            $result = ai_http_post('https://api.openai.com/v1/chat/completions',
                ["Authorization: Bearer $apiKey"],
                ['model' => $model, 'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ], 'max_tokens' => $maxTokens], $timeout);
            if ($result['ok']) {
                return ['ok' => true, 'text' => ai_strip_thinking($result['data']['choices'][0]['message']['content'] ?? ''), 'error' => null,
                    'truncated' => ($result['data']['choices'][0]['finish_reason'] ?? '') === 'length'];
            }
            break;

        case 'openrouter':
            $result = ai_http_post('https://openrouter.ai/api/v1/chat/completions',
                ["Authorization: Bearer $apiKey", "HTTP-Referer: " . APP_URL, "X-Title: " . SITE_BRAND],
                ['model' => $model, 'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ], 'max_tokens' => $maxTokens], $timeout);
            if ($result['ok']) {
                return ['ok' => true, 'text' => ai_strip_thinking($result['data']['choices'][0]['message']['content'] ?? ''), 'error' => null,
                    'truncated' => ($result['data']['choices'][0]['finish_reason'] ?? '') === 'length'];
            }
            break;

        case 'deepinfra':
            $result = ai_http_post('https://api.deepinfra.com/v1/openai/chat/completions',
                ["Authorization: Bearer $apiKey"],
                ['model' => $model, 'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ], 'max_tokens' => $maxTokens], $timeout);
            if ($result['ok']) {
                return ['ok' => true, 'text' => ai_strip_thinking($result['data']['choices'][0]['message']['content'] ?? ''), 'error' => null,
                    'truncated' => ($result['data']['choices'][0]['finish_reason'] ?? '') === 'length'];
            }
            break;

        case 'claude':
            $result = ai_http_post('https://api.anthropic.com/v1/messages',
                ["x-api-key: $apiKey", "anthropic-version: 2023-06-01"],
                ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $systemPrompt,
                 'messages' => [['role' => 'user', 'content' => $userPrompt]]], $timeout);
            if ($result['ok']) {
                $text = '';
                foreach (($result['data']['content'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'text') $text .= $block['text'];
                }
                return ['ok' => true, 'text' => $text, 'error' => null, 'truncated' => ($result['data']['stop_reason'] ?? '') === 'max_tokens'];
            }
            break;

        case 'google':
            $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$apiKey";
            $result = ai_http_post($url, [], [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['parts' => [['text' => $userPrompt]]]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
            ], $timeout);
            if ($result['ok']) {
                $text = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
                return ['ok' => true, 'text' => ai_strip_thinking($text), 'error' => null,
                    'truncated' => ($result['data']['candidates'][0]['finishReason'] ?? '') === 'MAX_TOKENS'];
            }
            break;

        default:
            return ['ok' => false, 'text' => '', 'error' => "Unknown text provider '$provider'."];
    }

    return ['ok' => false, 'text' => '', 'error' => is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'Unknown error')];
}

/**
 * Generate an image. Returns ['ok'=>bool, 'image_data'=>?string (raw binary), 'error'=>?string].
 *
 * Same BYOK-first, auto-fallback-to-admin behaviour as ai_generate_text() — see its docblock.
 * The user's own key (Settings → Image Generation Models) is tried via OpenRouter first.
 */
function ai_generate_image(PDO $pdo, string $provider, string $model, string $prompt, ?int $userId = null): array
{
    if ($userId) {
        $byok = get_user_ai_credentials($pdo, $userId, 'image');
        if ($byok) {
            $byokResult = ai_generate_image_raw($pdo, $byok['provider'], $byok['model'] ?: '', $prompt, $byok['api_key']);
            if ($byokResult['ok']) {
                $byokResult['ai_source'] = 'user';
                return $byokResult;
            }
            log_event($pdo, 'system', 'User\'s own AI image key failed (' . ($byokResult['error'] ?? 'unknown error') . ') — falling back to the site model.', $userId);
        }
    }
    $result = ai_generate_image_raw($pdo, $provider, $model, $prompt);
    $result['ai_source'] = 'admin';
    return $result;
}

/** Does the actual provider call. $apiKeyOverride bypasses the ai_providers (admin) lookup — used for BYOK. */
function ai_generate_image_raw(PDO $pdo, string $provider, string $model, string $prompt, ?string $apiKeyOverride = null): array
{
    if ($apiKeyOverride !== null) {
        $apiKey = $apiKeyOverride;
    } else {
        $row = get_ai_provider_row($pdo, $provider, 'image');
        if (!$row || empty($row['api_key'])) {
            return ['ok' => false, 'image_data' => null, 'error' => "No API key configured for image provider '$provider'."];
        }
        $apiKey = $row['api_key'];
    }

    if ($provider === 'deepinfra') {
        $model = $model ?: 'black-forest-labs/FLUX-1-schnell';
        $r = deepinfra_image_openai($apiKey, $model, $prompt, '1024x1024');
        if (!$r['ok']) {
            $info = image_model_catalog()[$model] ?? ['api' => 'native', 'steps' => true, 'default_steps' => 4];
            if (($info['api'] ?? 'native') === 'native') {
                $n = deepinfra_image_native($apiKey, $model, $prompt, 1024, 1024, (int)($info['default_steps'] ?? 4));
                if ($n['ok']) return $n;
            }
        }
        return $r;
    }

    // OpenRouter has no dedicated image-generation endpoint — some chat models (e.g.
    // Gemini's image-preview models) return an image inline in the chat response instead.
    // Best-effort implementation; verify against OpenRouter's current docs for your chosen
    // model if this doesn't work (their image-output response shape is still evolving).
    if ($provider === 'openrouter') {
        $result = ai_http_post('https://openrouter.ai/api/v1/chat/completions',
            ["Authorization: Bearer $apiKey", "HTTP-Referer: " . (defined('APP_URL') ? APP_URL : ''), "X-Title: " . (defined('SITE_BRAND') ? SITE_BRAND : '')],
            ['model' => $model ?: 'google/gemini-2.5-flash-image-preview', 'modalities' => ['image', 'text'],
             'messages' => [['role' => 'user', 'content' => $prompt]]], 60);
        $imgUrl = $result['data']['choices'][0]['message']['images'][0]['image_url']['url'] ?? null;
        if ($result['ok'] && $imgUrl && strpos($imgUrl, 'base64,') !== false) {
            return ['ok' => true, 'image_data' => base64_decode(substr($imgUrl, strpos($imgUrl, 'base64,') + 7)), 'error' => null];
        }
        return ['ok' => false, 'image_data' => null, 'error' => is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'OpenRouter did not return an image. Try a model that supports image output (e.g. google/gemini-2.5-flash-image-preview).')];
    }

    if ($provider === 'google') {
        // Not implemented yet — Google's image-generation API requires a different
        // (Vertex AI / billing-linked) setup than the simple API-key text endpoint.
        return ['ok' => false, 'image_data' => null, 'error' => 'Google image generation is not implemented yet. Please use DeepInfra for AI image generation for now.'];
    }

    return ['ok' => false, 'image_data' => null, 'error' => "Unknown image provider '$provider'."];
}



/** Download an image from a URL and save it under uploads/articles/. Returns the relative path or null. */
function save_remote_image(string $url, string $destDir): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true]);
    $data = curl_exec($ch);
    curl_close($ch);
    if (!$data) return null;
    return save_binary_image($data, $destDir);
}

/** Save raw binary image data (e.g. from AI generation) under uploads/articles/. Returns the relative path or null. */
function save_binary_image(string $binaryData, string $destDir): ?string
{
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = 'img_' . bin2hex(random_bytes(8)) . '.jpg';
    if (file_put_contents($destDir . $filename, $binaryData) === false) return null;
    return $filename;
}

/**
 * Bulk Pin Scheduler "Create with AI": given a list of main keywords (one per
 * pin), generate a Pinterest-style title + description for each, in the same
 * order. $withTags adds a handful of relevant hashtags at the end of the
 * description. $destLink, if given, is used for the "visit the website" CTA
 * appended to every description.
 *
 * Returns ['ok'=>bool, 'items'=>[['title'=>string,'description'=>string], ...], 'error'=>?string].
 * Uses the site's Bulk Pin Scheduler AI settings (article_settings.pin_text_provider/model),
 * falling back to the Write Article default text model if none is set specifically for pins.
 */
function ai_generate_pin_batch(PDO $pdo, array $keywords, bool $withTags, string $destLink = '', string $customPrompt = '', ?int $userId = null): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? null;
    $model = $settings['pin_text_model'] ?? null;
    if (!$provider) {
        $provider = $settings['text_provider'] ?? null;
        $model = $settings['text_model'] ?? null;
    }
    if (!$provider) {
        return ['ok' => false, 'items' => [], 'error' => 'AI writing is not available right now. Please try again later.'];
    }

    $keywords = array_values(array_filter(array_map('trim', $keywords), fn($k) => $k !== ''));
    if (empty($keywords)) {
        return ['ok' => false, 'items' => [], 'error' => 'Please enter at least one keyword.'];
    }

    // Asking a single AI call to write 20+ full pins (title + description + alt text +
    // keywords each) reliably overruns the model's output-token budget, so the response
    // comes back cut off mid-JSON — this is what used to surface to the user as "The AI
    // response could not be parsed. Please try again." above ~20 pins. Splitting into
    // chunks of 10 keeps every single call comfortably inside budget; results are merged
    // back in the original order. The Bulk Pin Scheduler's "Create with AI" panel also
    // calls this in 10-item batches itself (so the user sees live progress as each batch
    // finishes), so this loop mainly acts as a safety net for that and for any other caller
    // that passes a bigger array straight through.
    $CHUNK_SIZE = 10;
    if (count($keywords) > $CHUNK_SIZE) {
        $items = [];
        $chunkErrors = [];
        foreach (array_chunk($keywords, $CHUNK_SIZE) as $chunk) {
            $chunkResult = ai_generate_pin_batch($pdo, $chunk, $withTags, $destLink, $customPrompt, $userId);
            $items = array_merge($items, $chunkResult['items']);
            if (!$chunkResult['ok'] && !empty($chunkResult['error'])) {
                $chunkErrors[] = $chunkResult['error'];
            }
        }
        return ['ok' => empty($chunkErrors), 'items' => $items, 'error' => $chunkErrors ? implode(' | ', array_unique($chunkErrors)) : null];
    }

    // The link goes on the pin itself (Pinterest's link field) — never inside the description text.
    $ctaLine = pin_description_cta_rules();
    $tagsLine = $withTags
        ? "After the CTA, add 3-5 relevant Pinterest hashtags (e.g. #hashtag), space-separated, as part of the description text."
        : "Do not include any hashtags in the description.";
    $noCopyLine = 'The title must always be a brand-new sentence you write yourself, in your own words — never the input '
        . 'keyword/topic copied or barely reworded. Two people reading the keyword and the title side by side should be able '
        . 'to tell the title was freshly written, not lifted. Make it genuinely catchy and scroll-stopping, matching the same '
        . 'topic and intent as the keyword.';
    $numberLine = 'If a given keyword/topic explicitly contains a number (e.g. "20 winter outfit ideas", "5 easy recipes"), '
        . 'the generated title MUST include that exact same number, written as a digit, somewhere in the title — never drop it, '
        . 'never spell it out as a word, and never change it to a different number. If the keyword/topic has no number in it, '
        . 'do NOT invent or add one to the title.';
    $customLine = trim($customPrompt) !== ''
        ? "Additional instructions from the user (follow these closely): " . trim($customPrompt)
        : '';

    $systemPrompt = "You are an expert Pinterest marketer. Respond with ONLY a JSON array, no markdown fences, no commentary. "
        . 'Shape: [{"title": "...", "description": "...", "alt_text": "...", "keywords": "..."}, ...] — exactly one object per keyword, in the same order as given. '
        . 'Titles: catchy, keyword-rich, STRICTLY under 100 characters (Pinterest hard-rejects longer titles) — never truncate mid-word, write a shorter title instead. '
        . 'Descriptions: 2-4 sentences, natural and engaging, STRICTLY under 500 characters (Pinterest hard limit). '
        . 'alt_text: a descriptive image alt text for accessibility and SEO, describing what the pin image would show, STRICTLY under 500 characters (Pinterest hard limit) — concise is still better than padding it out. '
        . 'keywords: 5-8 relevant SEO keywords/phrases for this pin, comma-separated, lowercase. '
        . $noCopyLine . ' ' . $numberLine . ' ' . $ctaLine . ' ' . $tagsLine . ' ' . $customLine;
    $userPrompt = "Write a Pinterest pin title, description, alt text and keywords for each of these keywords/topics, one per line:\n" . implode("\n", $keywords);

    // Scale the output-token budget with how much this particular call is asking for,
    // instead of a flat 3000 that was too tight for chunks pushing toward 20+ items.
    $maxTokens = min(4000, 900 + (count($keywords) * 320));

    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, $maxTokens, $userId);
    $json = $result['ok'] ? extract_json_from_text($result['text']) : null;

    if (!$result['ok'] || !$json || !is_array($json)) {
        // Instead of giving up and handing back the raw keyword as a fake "title", retry by
        // splitting the batch in half — a smaller ask is far less likely to hit the same
        // token/parsing failure, and this keeps recursing down to single items if needed.
        if (count($keywords) > 1) {
            $mid = (int) ceil(count($keywords) / 2);
            $left = ai_generate_pin_batch($pdo, array_slice($keywords, 0, $mid), $withTags, $destLink, $customPrompt, $userId);
            $right = ai_generate_pin_batch($pdo, array_slice($keywords, $mid), $withTags, $destLink, $customPrompt, $userId);
            return [
                'ok' => $left['ok'] && $right['ok'],
                'items' => array_merge($left['items'], $right['items']),
                'error' => $left['error'] ?: $right['error'],
            ];
        }
        // Last resort for a single keyword that still fails: a plain-text (non-JSON) call is
        // far less likely to fail to parse, so the row still gets a genuinely fresh title
        // instead of the raw keyword with a blank description.
        $fallback = ai_generate_single_pin_fallback($pdo, $provider, $model, $keywords[0], $userId);
        return [
            'ok' => true,
            'items' => [$fallback],
            'error' => $result['ok'] ? 'The AI response could not be parsed for one pin; a simplified fallback was used instead.' : $result['error'],
        ];
    }

    // extract_json_from_text may hand back an associative array if the model wrapped the
    // array in an object (e.g. {"pins": [...]}) — dig one level in if so.
    $isList = array_keys($json) === range(0, count($json) - 1);
    if (!$isList) {
        $json = $json['pins'] ?? $json['items'] ?? $json['results'] ?? array_values($json);
    }

    $items = [];
    foreach ($keywords as $i => $kw) {
        $row = $json[$i] ?? null;
        $title = pin_enforce_max_chars(is_array($row) ? trim((string)($row['title'] ?? $kw)) : $kw, 100);

        // Safety net for the "always write a new title" instruction above: if the model just
        // echoed the keyword back (or something close enough to count as the same text), ask
        // it once more, specifically, for a genuinely different, attractive title.
        $title = ai_ensure_fresh_pin_title($pdo, $provider, $model, $kw, $title);

        // Safety net for the number-preservation instruction above: if the keyword had a
        // number in it but the model dropped it anyway, splice it back in rather than
        // silently losing it (e.g. "20 winter outfit ideas" -> title must keep the "20").
        if (preg_match('/\d+/', $kw, $kwNum) && !preg_match('/\d+/', $title)) {
            $title = pin_enforce_max_chars($kwNum[0] . ' ' . $title, 100);
        }

        $items[] = [
            // Hard-truncate regardless of what the model actually returned — Pinterest's API
            // rejects a pin outright if these are over its limits, so this must never rely on
            // the model reliably following the prompt's character-count instructions alone.
            'title' => $title,
            'description' => pin_enforce_max_chars(pin_description_clean(is_array($row) ? trim((string)($row['description'] ?? '')) : ''), 500),
            'alt_text' => pin_enforce_max_chars(is_array($row) ? trim((string)($row['alt_text'] ?? '')) : '', 500),
            'keywords' => pin_enforce_max_chars(is_array($row) ? trim((string)($row['keywords'] ?? '')) : '', 500),
        ];
    }

    return ['ok' => true, 'items' => $items, 'error' => null];
}

/**
 * $count different pins for ONE article/page (Auto Article and Auto Website to Daily Pin).
 * Every pin keeps the same search intent, the same main keyword and — if the source title has
 * one — the same number, but gets its own freshly written title, description, alt text and a
 * different set of related keywords, so no two pins of the same article read alike.
 * $provider/$model default to the pin text model (then the article text model).
 * Returns ['ok'=>bool, 'items'=>[['title','description','alt_text','keywords'], ...], 'error'=>?string].
 */
function ai_generate_pin_variations(PDO $pdo, string $sourceTitle, int $count, string $destLink = '', ?string $provider = null, ?string $model = null): array
{
    $count = max(1, $count);
    $sourceTitle = trim($sourceTitle);
    if (!$provider) {
        $settings = get_article_settings($pdo);
        $provider = $settings['pin_text_provider'] ?? null;
        $model = $settings['pin_text_model'] ?? null;
        if (!$provider) {
            $provider = $settings['text_provider'] ?? null;
            $model = $settings['text_model'] ?? null;
        }
    }
    if (!$provider) {
        return ['ok' => false, 'items' => [], 'error' => 'AI writing is not available right now. Please try again later.'];
    }

    $number = preg_match('/\d+/', $sourceTitle, $m) ? $m[0] : null;
    $normalize = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string)$s)));
    // The link goes on the pin itself (Pinterest's link field) — never inside the description text.
    $ctaLine = pin_description_cta_rules();
    $numberLine = $number !== null
        ? "The source title contains the number $number: EVERY title must contain that exact number $number, written as a digit — never drop it, spell it out or change it."
        : 'The source title has no number: do NOT add any number to the titles.';

    $items = [];
    $usedKeys = [$normalize($sourceTitle) => true];
    $mainKeyword = '';
    $lastError = null;
    // Small rounds keep each call inside the output-token budget; each round is told which
    // titles already exist so it never repeats them.
    for ($round = 0; $round < 5 && count($items) < $count; $round++) {
        $need = min(8, $count - count($items));
        $existing = array_map(fn($it) => $it['title'], $items);
        $systemPrompt = 'You are an expert Pinterest marketer. Respond with ONLY a JSON object, no markdown fences, no commentary. '
            . 'Shape: {"main_keyword": "...", "pins": [{"title": "...", "description": "...", "alt_text": "...", "keywords": "..."}, ...]} '
            . "with exactly $need objects in \"pins\". All pins promote the SAME article, so they must keep the SAME search intent and topic "
            . 'as the source title. main_keyword: the core keyword phrase of the source title (2-5 words, as written in it). '
            . 'EVERY title must contain that main keyword, but otherwise be a brand-new sentence with a different angle, hook and wording — '
            . 'never the source title copied or lightly reworded, and never a near-duplicate of another pin or of an already used title. '
            . 'Each pin must also work in DIFFERENT related/secondary keywords (synonyms, long-tail variations, related searches) in its title '
            . 'and description, so the pins rank for different searches. ' . $numberLine . ' '
            . 'Titles: catchy, STRICTLY under 100 characters. Descriptions: 2-4 natural sentences with the main keyword plus that pin\'s related '
            . 'keywords, STRICTLY under 500 characters, each one written differently. alt_text: describes the pin image, under 500 characters. '
            . 'keywords: 5-8 comma-separated lowercase keywords — the main keyword first, then that pin\'s own related keywords. ' . $ctaLine;
        $userPrompt = "Source article title: $sourceTitle\n"
            . ($mainKeyword !== '' ? "Main keyword: $mainKeyword\n" : '')
            . ($existing ? "Titles already used (do NOT repeat or paraphrase these):\n- " . implode("\n- ", $existing) . "\n" : '')
            . "Write $need different Pinterest pins for this article.";

        $result = ai_generate_text($pdo, $provider, (string)$model, $systemPrompt, $userPrompt, min(4000, 700 + $need * 350));
        if (!$result['ok']) { $lastError = $result['error']; continue; }
        $json = extract_json_from_text($result['text']);
        if (!is_array($json)) { $lastError = 'Could not parse the AI response.'; continue; }
        if ($mainKeyword === '' && !empty($json['main_keyword']) && is_string($json['main_keyword'])) {
            $mainKeyword = trim($json['main_keyword']);
        }
        $pins = $json['pins'] ?? $json['items'] ?? (array_keys($json) === range(0, count($json) - 1) ? $json : []);
        foreach ((array)$pins as $row) {
            if (!is_array($row) || count($items) >= $count) continue;
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') continue;
            // Same number as the source title: splice it in if the model dropped or changed it.
            if ($number !== null && !preg_match('/(?<!\d)' . $number . '(?!\d)/', $title)) {
                $title = preg_match('/^\d+/', $title) ? preg_replace('/^\d+/', $number, $title) : $number . ' ' . $title;
            } elseif ($number === null) {
                $title = trim(preg_replace('/^\d+\s+/', '', $title));
            }
            $title = pin_enforce_max_chars($title, 100);
            $key = $normalize($title);
            if ($key === '' || isset($usedKeys[$key])) continue; // duplicate of the source or of another pin
            $usedKeys[$key] = true;
            $items[] = [
                'title' => $title,
                'description' => pin_enforce_max_chars(pin_description_clean(trim((string)($row['description'] ?? ''))), 500),
                'alt_text' => pin_enforce_max_chars(trim((string)($row['alt_text'] ?? '')), 500),
                'keywords' => pin_enforce_max_chars(trim((string)($row['keywords'] ?? '')), 500),
            ];
        }
    }

    if (empty($items) && $lastError !== null) {
        return ['ok' => false, 'items' => [], 'error' => $lastError];
    }
    // Last resort for any pin the rounds above could not fill: a plain-text single-pin call.
    for ($guard = 0; count($items) < $count && $guard < $count * 2; $guard++) {
        $fallback = ai_generate_single_pin_fallback($pdo, $provider, (string)$model, $sourceTitle);
        $key = $normalize($fallback['title']);
        if (isset($usedKeys[$key]) && $guard < $count) continue;
        $usedKeys[$key] = true;
        $items[] = $fallback;
    }

    return ['ok' => true, 'items' => $items, 'error' => null];
}

/**
 * Safety net for ai_generate_pin_batch(): if the model returned a title that's just the
 * keyword/topic copied (or trivially reworded — same words, ignoring case/punctuation),
 * ask once more for a genuinely fresh, attractive title with the same intent, keeping any
 * number the keyword had. Falls back to the original title if the rewrite call fails.
 */
function ai_ensure_fresh_pin_title(PDO $pdo, string $provider, string $model, string $keyword, string $title): string
{
    $normalize = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
    if ($title === '' || $normalize($title) !== $normalize($keyword)) {
        return $title;
    }
    $systemPrompt = 'You write Pinterest pin titles. Respond with ONLY the new title text — no quotes, no commentary. '
        . 'Write a brand-new, highly attractive, scroll-stopping Pinterest title, in different wording than the topic given '
        . '(never just the topic copied), that keeps the exact same topic and intent. If the topic contains a number, keep '
        . 'that exact number somewhere in the title; if it has no number, do not add one. Strictly under 100 characters.';
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, "Topic: $keyword", 60);
    if ($result['ok']) {
        $rewritten = trim($result['text'], "\"' \t\n\r.");
        if ($rewritten !== '' && $normalize($rewritten) !== $normalize($keyword)) {
            return pin_enforce_max_chars($rewritten, 100);
        }
    }
    return $title;
}

/**
 * Last-resort fallback used by ai_generate_pin_batch() when even a single-keyword JSON
 * call fails to parse: a plain-text (non-JSON) request for one title + description, which
 * is far less likely to fail, so the pin still gets real freshly-written content instead of
 * the raw keyword as its "title".
 */
function ai_generate_single_pin_fallback(PDO $pdo, string $provider, string $model, string $keyword, ?int $userId = null): array
{
    $systemPrompt = 'You are an expert Pinterest marketer. Given one topic/keyword, write ONE catchy, keyword-rich Pinterest '
        . 'pin title (never the topic copied verbatim — always a brand-new sentence with the same intent) under 100 characters, '
        . 'and a 2-4 sentence engaging description under 500 characters. If the topic contains a number, keep that exact number '
        . "in the title; otherwise do not add one. Respond in exactly this format and nothing else:\n"
        . "TITLE: <title>\nDESCRIPTION: <description>";
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, "Topic: $keyword", 400, $userId);

    $title = $keyword;
    $description = '';
    if ($result['ok']) {
        if (preg_match('/TITLE:\s*(.+)/i', $result['text'], $m)) $title = trim($m[1]);
        if (preg_match('/DESCRIPTION:\s*(.+)/is', $result['text'], $m)) $description = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    if (preg_match('/\d+/', $keyword, $kwNum) && !preg_match('/\d+/', $title)) {
        $title = $kwNum[0] . ' ' . $title;
    }

    return [
        'title' => pin_enforce_max_chars($title, 100),
        'description' => pin_enforce_max_chars(pin_description_clean($description), 500),
        'alt_text' => '',
        'keywords' => '',
    ];
}

/**
 * Bulk Pin Scheduler "Create Board" — given a short keyword/topic, ask the AI
 * for a catchy Pinterest board name + a short board description.
 * Returns ['ok'=>bool, 'name'=>string, 'description'=>string, 'error'=>?string].
 */
function ai_generate_board_suggestion(PDO $pdo, string $keyword): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? ($settings['text_provider'] ?? null);
    $model = $settings['pin_text_model'] ?? ($settings['text_model'] ?? null);
    if (!$provider) {
        return ['ok' => false, 'name' => '', 'description' => '', 'error' => 'AI writing is not available right now. Please try again later.'];
    }
    $keyword = trim($keyword);
    if ($keyword === '') {
        return ['ok' => false, 'name' => '', 'description' => '', 'error' => 'Please enter a keyword for the board.'];
    }

    $systemPrompt = 'You are an expert Pinterest marketer. Respond with ONLY a JSON object, no markdown fences, no commentary. '
        . 'Shape: {"name": "...", "description": "..."}. '
        . 'name: the Pinterest board name for the given pin title/keyword. ' . board_name_ai_rules() . ' '
        . 'description: 1-2 sentences (under 500 characters, no numbers needed) describing what the board is about, written to attract followers and include relevant keywords.';
    $userPrompt = "Pin title / keyword: $keyword";

    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, 400);
    $json = $result['ok'] ? extract_json_from_text($result['text']) : null;
    if (!$json || !is_array($json)) {
        // No usable AI answer: still return a rule-based name that follows the same rules.
        return ['ok' => true, 'name' => board_name_from_title($keyword), 'description' => '',
            'error' => $result['ok'] ? 'AI response could not be parsed; a name was built from the title.' : $result['error']];
    }
    return [
        'ok' => true,
        'name' => board_name_clean(trim((string)($json['name'] ?? '')), $keyword),
        'description' => pin_enforce_max_chars(trim((string)($json['description'] ?? '')), 500),
        'error' => null,
    ];
}

/**
 * Auto Article "AI select existing board" / multi-board "Custom board" — picks the ONE board from
 * $boardNames that best fits the article's intent. Never invents a new board: the answer is always
 * an index into $boardNames. Falls back to keyword overlap when AI is unavailable or unparseable.
 * Returns ['ok'=>bool, 'index'=>int, 'error'=>?string].
 */
function ai_pick_existing_board(PDO $pdo, string $title, array $boardNames): array
{
    $boardNames = array_values($boardNames);
    if (empty($boardNames)) {
        return ['ok' => false, 'index' => -1, 'error' => 'No boards to choose from.'];
    }
    if (count($boardNames) === 1) {
        return ['ok' => true, 'index' => 0, 'error' => null];
    }

    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? ($settings['text_provider'] ?? null);
    $model = $settings['pin_text_model'] ?? ($settings['text_model'] ?? null);
    if ($provider) {
        $list = [];
        foreach ($boardNames as $i => $name) $list[] = ($i + 1) . '. ' . $name;
        $systemPrompt = 'You are an expert Pinterest marketer. Given an article title and a numbered list of EXISTING Pinterest boards, '
            . 'choose the single board whose topic best matches the article\'s search intent and audience. You MUST choose one of the listed '
            . 'boards — never invent, rename or suggest a new board. If nothing fits well, choose the closest broader topic. '
            . 'Respond with ONLY a JSON object, no markdown fences, no commentary. Shape: {"board": <number from the list>}.';
        $userPrompt = "Article title: $title\n\nExisting boards:\n" . implode("\n", $list);
        $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, 100);
        if ($result['ok']) {
            $json = extract_json_from_text($result['text']);
            $num = is_array($json) && isset($json['board']) ? (int)$json['board'] : 0;
            if ($num < 1 && preg_match('/\d+/', $result['text'], $m)) $num = (int)$m[0];
            if ($num >= 1 && $num <= count($boardNames)) {
                return ['ok' => true, 'index' => $num - 1, 'error' => null];
            }
        }
    }

    // Rule-based fallback: the board sharing the most words with the title (first board on a tie).
    $titleWords = array_filter(explode(' ', board_name_key($title)), fn($w) => strlen($w) > 2);
    $best = 0;
    $bestScore = -1;
    foreach ($boardNames as $i => $name) {
        $score = count(array_intersect($titleWords, explode(' ', board_name_key((string)$name))));
        if ($score > $bestScore) { $best = $i; $bestScore = $score; }
    }
    return ['ok' => true, 'index' => $best, 'error' => $provider ? 'AI answer unusable; matched by keywords.' : 'AI unavailable; matched by keywords.'];
}

/** A "new board" answer, turned into a match when the cleaned name equals an existing board (keeps names unique). */
function board_assign_item_for_name(string $name, string $description, array $existingBoards): array
{
    $key = board_name_key($name);
    foreach ($existingBoards as $b) {
        if (board_name_key($b) === $key) return ['match' => $b, 'new_name' => null, 'new_description' => null];
    }
    return ['match' => null, 'new_name' => $name, 'new_description' => $description];
}

/**
 * Bulk Pin Scheduler "Create with AI" -> "Multiple Boards" — given a batch of pin titles
 * and the account's existing board names, decides per-pin whether an existing board is a
 * good fit or a brand-new board should be drafted for it.
 * Returns ['ok'=>bool, 'items'=>[{'match'=>?string, 'new_name'=>?string, 'new_description'=>?string}, ...], 'error'=>?string]
 * — 'items' is in the same order as the given $titles.
 */
function ai_assign_boards_batch(PDO $pdo, array $titles, array $existingBoards): array
{
    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? ($settings['text_provider'] ?? null);
    $model = $settings['pin_text_model'] ?? ($settings['text_model'] ?? null);
    if (!$provider) {
        return ['ok' => false, 'items' => [], 'error' => 'AI writing is not available right now. Please try again later.'];
    }

    $titles = array_values(array_filter(array_map('trim', $titles), fn($t) => $t !== ''));
    if (empty($titles)) {
        return ['ok' => false, 'items' => [], 'error' => 'No pin titles to assign boards for.'];
    }
    $existingBoards = array_values(array_filter(array_map('trim', $existingBoards), fn($b) => $b !== ''));

    // Same reasoning as ai_generate_pin_batch(): keep each call to a manageable chunk so the
    // JSON response can't overrun the model's output budget and come back truncated.
    $CHUNK_SIZE = 10;
    if (count($titles) > $CHUNK_SIZE) {
        $items = [];
        $chunkErrors = [];
        foreach (array_chunk($titles, $CHUNK_SIZE) as $chunk) {
            $chunkResult = ai_assign_boards_batch($pdo, $chunk, $existingBoards);
            $items = array_merge($items, $chunkResult['items']);
            if (!$chunkResult['ok'] && !empty($chunkResult['error'])) $chunkErrors[] = $chunkResult['error'];
        }
        return ['ok' => empty($chunkErrors), 'items' => $items, 'error' => $chunkErrors ? implode(' | ', array_unique($chunkErrors)) : null];
    }

    $boardsList = $existingBoards ? implode("\n", $existingBoards) : '(none yet — every pin needs a new board)';
    $systemPrompt = "You are an expert Pinterest board organizer. Respond with ONLY a JSON array, no markdown fences, no commentary. "
        . 'Shape: [{"match": "..."|null, "new_name": "..."|null, "new_description": "..."|null}, ...] — exactly one object per pin title, in the same order given. '
        . 'For each pin title, decide if it clearly fits the theme of one of the EXISTING BOARDS listed below. '
        . 'If yes: set "match" to that board\'s name EXACTLY as written in the list, and leave "new_name"/"new_description" null. '
        . 'If no good fit exists: set "match" to null, and set "new_name" and "new_description" '
        . '(1-2 sentences, under 500 characters, written to attract followers) for a brand-new board that fits this pin\'s topic. '
        . 'Pins in this list that share the same main topic must get the SAME new_name so they go to one board. '
        . board_name_ai_rules() . ' '
        . "EXISTING BOARDS:\n" . $boardsList;
    $userPrompt = "Pin titles, one per line:\n" . implode("\n", $titles);
    $maxTokens = min(4000, 700 + (count($titles) * 260));

    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, $maxTokens);
    $json = $result['ok'] ? extract_json_from_text($result['text']) : null;

    if (!$result['ok'] || !$json || !is_array($json)) {
        if (count($titles) > 1) {
            $mid = (int) ceil(count($titles) / 2);
            $left = ai_assign_boards_batch($pdo, array_slice($titles, 0, $mid), $existingBoards);
            $right = ai_assign_boards_batch($pdo, array_slice($titles, $mid), $existingBoards);
            return [
                'ok' => $left['ok'] && $right['ok'],
                'items' => array_merge($left['items'], $right['items']),
                'error' => $left['error'] ?: $right['error'],
            ];
        }
        // Single-title last resort: don't leave the pin with no board at all — draft a new
        // one named after the title's own topic rather than failing the whole assignment.
        return [
            'ok' => true,
            'items' => [board_assign_item_for_name(board_name_from_title($titles[0]), '', $existingBoards)],
            'error' => $result['ok'] ? 'The AI response could not be parsed for one pin; a fallback board name was used instead.' : $result['error'],
        ];
    }

    $isList = array_keys($json) === range(0, count($json) - 1);
    if (!$isList) $json = $json['pins'] ?? $json['items'] ?? $json['results'] ?? array_values($json);

    $items = [];
    foreach ($titles as $i => $title) {
        $row = $json[$i] ?? null;
        $match = is_array($row) ? trim((string)($row['match'] ?? '')) : '';
        if ($match !== '') {
            // Only trust a match that actually names one of the boards we gave it — never let
            // the model invent a board name that doesn't exist.
            $found = null;
            foreach ($existingBoards as $b) { if (strcasecmp($b, $match) === 0) { $found = $b; break; } }
            if ($found) { $items[] = ['match' => $found, 'new_name' => null, 'new_description' => null]; continue; }
        }
        $newName = is_array($row) ? trim((string)($row['new_name'] ?? '')) : '';
        $newName = $newName !== '' ? board_name_clean($newName, $title) : board_name_from_title($title);
        $items[] = board_assign_item_for_name($newName, pin_enforce_max_chars(is_array($row) ? trim((string)($row['new_description'] ?? '')) : '', 500), $existingBoards);
    }

    return ['ok' => true, 'items' => $items, 'error' => null];
}

/**
 * Best-effort extraction of a JSON object/array from an AI text response
 * (strips ```json fences and any leading/trailing chatter around it).
 */
function extract_json_from_text(string $text): ?array
{
    $text = ai_strip_thinking(trim($text));
    // take the fenced block if there is one anywhere in the reply
    if (preg_match('/```(?:json)?\s*(.*?)```/si', $text, $m)) $text = $m[1];
    $text = preg_replace('/^```(json)?/i', '', trim($text));
    $text = trim(preg_replace('/```$/', '', $text));

    $try = function (string $t) {
        $d = json_decode($t, true);
        if (is_array($d)) return $d;
        $t2 = preg_replace('/,\s*([}\]])/', '$1', $t);                 // trailing commas
        $t2 = str_replace(["\u{201C}", "\u{201D}"], '\"', $t2);          // smart quotes used as JSON quotes? (rare)
        $d = json_decode($t2, true);
        return is_array($d) ? $d : null;
    };
    if ($d = $try($text)) return $d;

    $start = strcspn($text, '{[');
    if ($start >= strlen($text)) return null;
    $lastCurly = strrpos($text, '}');
    $lastSquare = strrpos($text, ']');
    $end = max($lastCurly === false ? -1 : $lastCurly, $lastSquare === false ? -1 : $lastSquare);
    if ($end > $start && ($d = $try(substr($text, $start, $end - $start + 1)))) return $d;

    // Cut-off answer: close any open string / arrays / objects and try again.
    return json_repair_truncated(substr($text, $start));
}

/** Best-effort repair of JSON that was cut off mid-way (keeps every complete item). */
function json_repair_truncated(string $t): ?array
{
    $stack = []; $inStr = false; $esc = false; $lastSafe = 0;
    $len = strlen($t);
    for ($i = 0; $i < $len; $i++) {
        $c = $t[$i];
        if ($inStr) {
            if ($esc) { $esc = false; continue; }
            if ($c === '\\') { $esc = true; continue; }
            if ($c === '"') $inStr = false;
            continue;
        }
        if ($c === '"') { $inStr = true; continue; }
        if ($c === '{' || $c === '[') $stack[] = $c;
        elseif ($c === '}' || $c === ']') { array_pop($stack); $lastSafe = $i + 1; }
        elseif ($c === ',') $lastSafe = $i;
    }
    // drop the incomplete tail after the last complete value, then close what's still open
    $cut = rtrim(substr($t, 0, $lastSafe), ", \n\r\t");
    $stack = []; $inStr = false; $esc = false;
    for ($i = 0, $n = strlen($cut); $i < $n; $i++) {
        $c = $cut[$i];
        if ($inStr) { if ($esc) { $esc = false; continue; } if ($c === '\\') { $esc = true; continue; } if ($c === '"') $inStr = false; continue; }
        if ($c === '"') { $inStr = true; continue; }
        if ($c === '{' || $c === '[') $stack[] = $c;
        elseif ($c === '}' || $c === ']') array_pop($stack);
    }
    for ($i = count($stack) - 1; $i >= 0; $i--) $cut .= $stack[$i] === '{' ? '}' : ']';
    $d = json_decode($cut, true);
    return is_array($d) ? $d : null;
}

/**
 * Fetch a competitor article page and strip it down to a plain-text outline
 * (title + heading list + a text excerpt) to use as inspiration for the AI prompt.
 * We only ever pass a short extracted outline to the AI, never republish the page.
 */
function fetch_competitor_outline(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ArticleWriterBot/1.0)',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        return ['ok' => false, 'title' => '', 'headings' => [], 'excerpt' => ''];
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();

    $titleNode = $doc->getElementsByTagName('title')->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : '';

    $headings = [];
    foreach (['h1', 'h2', 'h3'] as $tag) {
        foreach ($doc->getElementsByTagName($tag) as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($text !== '') $headings[] = $text;
        }
    }
    $headings = array_slice(array_unique($headings), 0, 25);

    $bodyText = '';
    $paragraphs = $doc->getElementsByTagName('p');
    foreach ($paragraphs as $p) {
        $bodyText .= trim($p->textContent) . ' ';
        if (strlen($bodyText) > 2000) break;
    }

    return ['ok' => true, 'title' => $title, 'headings' => $headings, 'excerpt' => mb_substr(trim($bodyText), 0, 1500)];
}

/* ===================== Bulk Pin Scheduler — AI pin-image generator ===================== */

function pin_image_size_dims(string $sizeKey): array
{
    $sizes = [
        '2:3' => [1000, 1500],
        '9:16' => [1080, 1920],
        '1:2.1' => [1000, 2100],
        '1:1' => [1000, 1000],
        '4:5' => [1000, 1250],
    ];
    return $sizes[$sizeKey] ?? $sizes['2:3'];
}

/** Best-effort page-title fetch (og:title, else <title>) for the "paste a link" pin-image input. */
function extract_title_from_url(string $url): ?string
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PinImageBot/1.0)',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    if (!$html) return null;
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES));
    }
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
    }
    return null;
}

/** Pulls the page title out of raw HTML (og:title in either attribute order, then twitter:title, then <title>). */
function extract_title_from_html(string $html): ?string
{
    $patterns = [
        '/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*property=["\']og:title["\']/i',
        '/<meta[^>]+name=["\']twitter:title["\'][^>]*content=["\']([^"\']+)["\']/i',
        '/<title[^>]*>(.*?)<\/title>/is',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $html, $m)) {
            $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($t !== '') return $t;
        }
    }
    return null;
}

/** True for titles of bot-check / error pages ("Just a moment...", "Access Denied", "404 Not Found"…) — never real content. */
function title_looks_like_block_page(string $title): bool
{
    return (bool)preg_match('/^(just a moment|attention required|access denied|client challenge|security check|please wait|403|404|forbidden|not found|page not found|error|are you a robot|verify you are human|one more step|ddos protection)/i', trim($title));
}

/** Readable fallback title from a URL's last path segment ("/easy-honey-butter-chicken/" -> "Easy Honey Butter Chicken"). */
function title_from_url_slug(string $url): string
{
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    $seg = $path !== '' ? basename($path) : (string)parse_url($url, PHP_URL_HOST);
    $seg = preg_replace('/\.(html?|php|aspx?)$/i', '', urldecode($seg));
    $seg = trim(preg_replace('/[-_+]+/', ' ', $seg));
    $seg = preg_replace('/^\d{3,}\s+/', '', $seg); // drop leading post IDs like "12345-..."
    return $seg !== '' ? ucwords($seg) : $url;
}

/**
 * Fetches page titles for many URLs at once (curl_multi, $concurrency at a time), so 100+ links
 * finish in seconds instead of timing out one-by-one. Returns [url => title|null] in input order.
 */
function extract_titles_from_urls(array $urls, int $concurrency = 10, int $timeout = 15): array
{
    $out = [];
    foreach ($urls as $u) $out[$u] = null;
    $valid = array_values(array_filter(array_keys($out), fn($u) => filter_var($u, FILTER_VALIDATE_URL)));
    if (!function_exists('curl_multi_init')) {
        foreach ($valid as $u) $out[$u] = extract_title_from_url($u);
        return $out;
    }
    foreach (array_chunk($valid, max(1, $concurrency)) as $group) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($group as $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$u] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $u => $ch) {
            $html = curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code < 400 && is_string($html) && $html !== '') {
                $t = extract_title_from_html($html);
                if ($t !== null && !title_looks_like_block_page($t)) $out[$u] = $t;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $out;
}

/**
 * For long titles, asks the configured text model for a shorter, punchier version
 * (keeping any leading number) before generating the pin image — long titles are
 * what force small illegible fonts or truncation on the finished pin. Falls back
 * to the original title unchanged if it's already short, or if no text model is
 * configured, or if the request fails.
 */
function ai_shorten_pin_title(PDO $pdo, string $title): string
{
    $title = pin_title_make_unique($title);
    if (str_word_count($title) <= 6 && mb_strlen($title) <= 42) {
        return $title;
    }
    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? ($settings['text_provider'] ?? null);
    $model = $settings['pin_text_model'] ?? ($settings['text_model'] ?? null);
    if (!$provider) {
        return $title;
    }
    $systemPrompt = 'You shorten Pinterest pin titles so they fit cleanly on a pin image. Respond with ONLY the shortened '
        . 'title text — no quotes, no commentary, no explanation. Keep any leading number. Keep the same meaning and '
        . 'intent, punchy and under 6 words and 40 characters.';
    $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, "Shorten this pin title: $title", 60);
    if ($result['ok']) {
        $short = trim($result['text'], "\"' \t\n\r.");
        if ($short !== '') return $short;
    }
    return $title;
}

/** A short list of curated CTA phrases used when the user leaves CTA text blank but wants one added automatically. */
function auto_pick_cta(string $title): string
{
    $t = strtolower($title);
    $map = [
        'recipe' => 'Get the Recipe', 'diy' => 'See How', 'how to' => 'See How',
        'workout' => 'Try This Workout', 'outfit' => 'Shop the Look', 'tips' => 'Read the Tips',
        'ideas' => 'Explore All Ideas', 'guide' => 'Read the Guide',
    ];
    foreach ($map as $needle => $cta) {
        if (strpos($t, $needle) !== false) return $cta;
    }
    $defaults = ['Explore Now', 'Visit Site', 'Explore All Ideas', 'Learn More', 'See More', 'Discover More'];
    return $defaults[array_rand($defaults)];
}

/** One of a few curated "attractive" color palettes, picked at random for automatic styling. */
/** Uppercases safely even if the mbstring extension isn't enabled (falls back to ASCII-only). */
function pin_image_upper(string $text): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}

function pin_design_palette(): array
{
    $palettes = [
        ['accent' => [237, 30, 90],  'accent2' => [255, 235, 59], 'dark' => [15, 15, 15]],   // hot pink / yellow (yourgirlknows style)
        ['accent' => [229, 57, 53],  'accent2' => [255, 255, 255], 'dark' => [20, 20, 20]],   // red / white
        ['accent' => [255, 179, 0],  'accent2' => [229, 57, 53], 'dark' => [35, 25, 10]],     // amber / red
        ['accent' => [92, 107, 192], 'accent2' => [255, 202, 40], 'dark' => [15, 15, 30]],    // indigo / gold
        ['accent' => [0, 150, 136],  'accent2' => [255, 138, 101], 'dark' => [12, 25, 22]],   // teal / coral
        ['accent' => [216, 27, 96],  'accent2' => [255, 255, 255], 'dark' => [20, 10, 15]],   // magenta / white
    ];
    return $palettes[array_rand($palettes)];
}

/**
 * Draws $text with a thick solid outline (for legibility over any photo, like real
 * Pinterest pin templates) by stamping the text at points around a circle before the fill.
 */
function pin_image_draw_outlined_text($canvas, int $size, string $font, string $text, int $x, int $y, int $fillColor, int $outlineColor, int $outlineWidth = 5): void
{
    $steps = 20;
    for ($i = 0; $i < $steps; $i++) {
        $angle = 2 * M_PI * $i / $steps;
        $ox = (int)round(cos($angle) * $outlineWidth);
        $oy = (int)round(sin($angle) * $outlineWidth);
        imagettftext($canvas, $size, 0, $x + $ox, $y + $oy, $outlineColor, $font, $text);
    }
    imagettftext($canvas, $size, 0, $x, $y, $fillColor, $font, $text);
}

/**
 * Builds a single Stable-Diffusion/FLUX-style prompt for a pin background image.
 * If $customPrompt is given it's used almost verbatim; otherwise a generic
 * "attractive Pinterest background" prompt is built from the title.
 */
/**
 * Strips listicle scaffolding (leading numbers, "ideas"/"tips"/"ways" etc.) from a
 * title before it goes into an image prompt. Diffusion models strongly associate
 * that scaffolding — and Pinterest-pin framing generally — with pin TEMPLATES,
 * which is a major reason they hallucinate baked-in caption text otherwise.
 */
function pin_scene_keywords(string $title): string
{
    $t = preg_replace('/^\d+\s*[-:.]?\s*/', '', $title);
    // "how to" / "step by step" style openers are the strongest trigger for the model
    // rendering an instructional-graphic look (which is exactly when it starts drawing
    // its own wall art / lettering / signage into the scene), so these go first.
    $stripWords = ['how to', 'step by step', 'ideas', 'idea', 'tips', 'ways to', 'ways', 'way to', 'guide', 'list', 'recipe:'];
    foreach ($stripWords as $w) {
        $t = preg_replace('/\b' . preg_quote($w, '/') . '\b/i', '', $t);
    }
    $t = trim(preg_replace('/\s+/', ' ', $t));
    if ($t === '') $t = $title;

    // A long, wordy scene description is the #1 cause of hallucinated on-image text on
    // fast/distilled models like FLUX-schnell — the longer and more "captiony" the prompt
    // reads, the more likely the model bakes some version of it into the photo (often as
    // giant wall-art lettering in room/closet scenes), which then visually duplicates the
    // real title we draw on top. Keep the scene phrase short regardless of title length.
    $words = preg_split('/\s+/', $t);
    if (count($words) > 8) {
        $t = implode(' ', array_slice($words, 0, 8));
    }
    return $t;
}

/**
 * Cleans a title before it is printed on a pin or used for an image: removes emoji (the pin
 * fonts can't draw them), hashtags, a trailing " | Site Name" / " – Site Name" segment,
 * repeated segments, and repeated words/phrases ("for Spring for Spring" → "for Spring").
 * Duplicated words in the title are one of the ways the same text ends up on a pin twice.
 */
function pin_title_make_unique(string $title): string
{
    $t = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Emoji / pictographs / variation selectors.
    $t = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', '', $t);
    $t = preg_replace('/(^|\s)#[\p{L}\p{N}_]+/u', '$1', $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t));

    // "Post title | Site Name" / "Post title – Site Name": drop short trailing segments.
    $parts = preg_split('/\s+(?:\||–|—|-|::|»)\s+/u', $t);
    if (count($parts) > 1) {
        $parts = array_values(array_unique(array_map('trim', $parts)));
        while (count($parts) > 1 && str_word_count(end($parts)) <= 4 && str_word_count($parts[0]) >= 3) {
            array_pop($parts);
        }
        $t = implode(' - ', $parts);
    }

    // Repeated phrases of up to 4 words, then repeated single words (case-insensitive).
    for ($i = 0; $i < 3; $i++) {
        $t = preg_replace('/\b((?:[\p{L}\p{N}\'’&]+\s+){0,3}[\p{L}\p{N}\'’&]+)(?:\s+\1\b)+/iu', '$1', $t);
    }
    $t = trim($t, " \t\n\r\0\x0B-–—|:,;");
    return $t !== '' ? $t : trim($title);
}

/**
 * Words that describe things carrying writing. They're removed from any AI-written scene so
 * the image model is never asked to draw an object that usually has text on it.
 */
function pin_scene_sanitize(string $scene): string
{
    $risky = 'text|texts|words?|letters?|lettering|typography|fonts?|captions?|titles?|headlines?|quotes?|quotation|'
        . 'signs?|signage|signboards?|posters?|labels?|logos?|banners?|watermarks?|chalkboards?|neon signs?|magazines?|'
        . 'newspapers?|book covers?|menus?|handwriting|handwritten|calligraphy|printed|printables?|infographics?|slogans?|brand names?';
    // "…that says Home Sweet Home" / "reading 'Welcome'" / quoted strings: drop the whole clause.
    $s = preg_replace('/\b(?:that|which)\s+(?:says|reads|spells)\b[^,.;]*/iu', '', $scene);
    $s = preg_replace('/\b(?:saying|reading|spelling)\b[^,.;]*/iu', '', $s);
    $s = preg_replace('/["“”][^"“”]*["“”]/u', '', $s);
    // Then the risky nouns themselves, and any small words they leave dangling.
    $s = preg_replace('/\b(?:' . $risky . ')\b/iu', '', $s);
    for ($i = 0; $i < 3; $i++) {
        $s = preg_replace('/\b(?:with|featuring|showing|and|of|a|an|the)\s+(?=(?:on|in|at|under|near|beside|and|with)\b|[,.;]|$)/iu', '', $s);
        $s = preg_replace('/\s+(?:and|with|of|a|an|the)\s*$/iu', '', trim($s));
    }
    $s = preg_replace('/\s+([,.;])/', '$1', $s);
    $s = preg_replace('/([,;])\s*[,;]+/', '$1', $s);
    return trim(preg_replace('/\s{2,}/', ' ', $s), " ,;.");
}

/**
 * One text-AI call that prepares everything the image step needs:
 *   headline — the short, unique text printed on the pin (≤ 6 words / 40 characters, keeps a leading number)
 *   scene    — a description of ONE photo that fits the title AND the category's search intent,
 *              written so it contains nothing that carries writing
 * $categoryPath is "Main › Sub" from the user's "Select category" choice ('' = let the AI infer).
 * Falls back to rule-based cleanup when no text model is configured or the call fails.
 */
function pin_prepare_image_brief(PDO $pdo, string $title, string $categoryPath = '', string $customPrompt = '', bool $shortenHeadline = true, bool $wantScene = true): array
{
    $clean = pin_title_make_unique($title);
    $needsShort = $shortenHeadline && !(str_word_count($clean) <= 6 && mb_strlen($clean) <= 42);
    $needsScene = $wantScene && trim($customPrompt) === '';
    $headline = $clean;
    $scene = '';

    $settings = get_article_settings($pdo);
    $provider = $settings['pin_text_provider'] ?? ($settings['text_provider'] ?? null);
    $model = $settings['pin_text_model'] ?? ($settings['text_model'] ?? null);

    if ($provider && ($needsShort || $needsScene)) {
        $system = 'You prepare the image for a Pinterest pin. Reply with ONLY a JSON object: {"headline": "...", "scene": "..."}.' . "\n"
            . 'headline: the words printed on the pin. At most 6 words and 40 characters, keep any leading number, same meaning '
            . 'as the title, punchy, Title Case. Every word appears once — never repeat a word or phrase. No site names, emoji or hashtags.' . "\n"
            . 'scene: ONE realistic photograph (15-30 words) that fits the title and the search intent of the category: the main '
            . 'subject, setting, composition, lighting and colors. Choose subjects that naturally carry no writing. Never describe '
            . 'signs, posters, book covers, labels, packaging, menus, screens, printed paper, jerseys with numbers or anything with '
            . 'words on it. If the topic is itself something with writing (quotes, printables, planners, invitations, typography, '
            . 'calligraphy, resumes, logos), show it blank and unprinted, or show the place where it is used. Do not mention text, '
            . 'letters, words or typography anywhere in the scene.';
        $user = 'Title: ' . $clean . "\n" . 'Category: ' . ($categoryPath !== '' ? $categoryPath : 'not given — infer it from the title');
        if (!$needsScene) $user .= "\n" . 'The scene is provided by the user; still return a scene field (it will be ignored).';
        $r = ai_generate_text($pdo, $provider, $model, $system, $user, 300);
        if ($r['ok']) {
            $json = extract_json_from_text($r['text']);
            if (is_array($json)) {
                $h = pin_title_make_unique(trim((string)($json['headline'] ?? ''), "\"' \t\n\r."));
                if ($needsShort && $h !== '' && mb_strlen($h) <= 60) $headline = $h;
                $sc = pin_scene_sanitize((string)($json['scene'] ?? ''));
                if ($needsScene && str_word_count($sc) >= 4) $scene = $sc;
            }
        }
    }

    // Rule-based fallback for a long headline: keep the part before a colon/dash if it stands on its own.
    if ($needsShort && $headline === $clean && mb_strlen($clean) > 42) {
        $first = trim(preg_split('/\s*(?::|\s-\s|\s–\s|\s—\s)\s*/u', $clean)[0]);
        if (str_word_count($first) >= 3 && mb_strlen($first) < mb_strlen($clean)) $headline = $first;
    }
    if ($needsScene && $scene === '') {
        $scene = pin_scene_sanitize(pin_scene_keywords($clean));
        if ($categoryPath !== '') $scene .= ', ' . str_replace(' › ', ' ', $categoryPath) . ' setting';
    }
    return ['headline' => $headline, 'scene' => $scene, 'category' => $categoryPath, 'clean_title' => $clean];
}

/** Generation size for a pin format — the photo is made in the pin's own shape instead of a cropped square. */
function pin_generation_dims(?string $sizeKey): array
{
    return [
        '2:3' => [832, 1248], '9:16' => [768, 1344], '1:2.1' => [704, 1472], '1:1' => [1024, 1024],
    ][(string)$sizeKey] ?? [1024, 1024];
}

/** Generation size (multiples of 32, about 1 megapixel) for an arbitrary output width × height. */
function pin_generation_dims_for(int $w, int $h): array
{
    if ($w <= 0 || $h <= 0) return [1024, 1024];
    $scale = sqrt(1048576 / ($w * $h));
    $gw = max(512, min(1536, (int)round($w * $scale / 32) * 32));
    $gh = max(512, min(1536, (int)round($h * $scale / 32) * 32));
    return [$gw, $gh];
}

/**
 * Builds the prompt pair for one background photo.
 *   positive — purely visual. Used for FLUX-1 and Cloudflare: those models ignore negative prompts,
 *              and merely MENTIONING text ("no text, no letters, no captions…") makes them more likely
 *              to draw some, which then duplicates the title printed on top.
 *   strict   — the same scene plus an explicit no-text instruction, for models that follow instructions
 *              (FLUX-2, Nano Banana).
 * $brief comes from pin_prepare_image_brief(); without it the scene is derived from the title.
 */
function build_pin_image_prompt(string $title, string $customPrompt, ?array $brief = null, ?array $dims = null, ?int $variant = null, int $total = 1): array
{
    if (trim($customPrompt) !== '') {
        $scene = trim($customPrompt);
    } elseif ($brief && trim((string)($brief['scene'] ?? '')) !== '') {
        $scene = trim($brief['scene']);
    } else {
        $scene = pin_scene_sanitize(pin_scene_keywords(pin_title_make_unique($title)));
    }
    $scene = rtrim($scene, " .");
    $person = pin_scene_has_person($scene . ' ' . $title);
    // Collage tiles: every photo in the set shows a clearly different person / variation, and the whole
    // subject is in frame so the collage cells don't cut people in half.
    $variation = ($variant !== null && $total > 1) ? ' ' . pin_collage_variation($variant, $total, $person) : '';
    $framing = $person
        ? ' Full-length shot: the whole person is visible from head to toe with a little space around them, centered in the frame.'
        : ' The whole subject is fully in frame and centered, nothing cut off at the edges.';
    $positive = $scene . '.' . $variation . $framing . ' High-end editorial lifestyle photograph, bright natural daylight, rich true-to-life colors, '
        . 'tack-sharp focus across the entire frame, crisp detailed in-focus background, deep depth of field, high resolution, '
        . 'clean uncluttered setting, plain unbranded surfaces, vertical composition.';
    $strict = $positive . ' This is a pure photograph with no text of any kind: no letters, words, numbers, logos, watermarks, '
        . 'signs, labels or captions, and nothing written or printed on any object, wall or surface.';
    $negative = 'text, words, letters, writing, typography, captions, titles, headlines, watermark, logo, signage, '
        . 'numbers, labels, subtitles, speech bubbles, UI elements, borders, frames, quotes, infographic, poster, banner, '
        . 'duplicate text, repeated text, wall art with words, decorative letters, signboard, chalkboard sign, neon sign, '
        . 'blurry, blurred background, bokeh, shallow depth of field, out of focus, soft focus, hazy, low quality, grainy, '
        . 'cropped head, cut off feet, cropped body, deformed, distorted, split image, collage, multiple panels';
    [$w, $h] = $dims ?: [1024, 1024];
    return ['positive' => $positive, 'strict' => $strict, 'negative' => $negative, 'width' => $w, 'height' => $h];
}

/** True when the photo scene is about a person (outfits, hairstyles, style guides …). */
function pin_scene_has_person(string $text): bool
{
    return (bool)preg_match('/\b(woman|women|man|men|girl|girls|lady|ladies|person|people|model|mom|moms|bride|she|he|her|his|wearing|outfits?|dressed|hairstyles?|haircuts?|over\s+\d{2}|in\s+your\s+\d{2}s)\b/i', $text);
}

/** Per-photo variation for collage sets, so no two photos in one pin look the same. */
function pin_collage_variation(int $i, int $total, bool $person): string
{
    $n = $i + 1;
    if ($person) {
        $hair = ['platinum blonde bob', 'silver shoulder-length hair', 'dark brown wavy hair', 'short grey pixie cut', 'auburn layered hair',
            'black curly hair', 'honey blonde long hair', 'salt-and-pepper chin-length hair', 'chestnut hair in a low bun'];
        $place = ['on a sunny city sidewalk', 'in an elegant home hallway', 'on a path in a green park', 'in front of a modern stone building',
            'at a bright city crosswalk', 'on a cafe terrace', 'inside a bright boutique', 'on a garden path with flowers', 'in a stylish living room'];
        $tones = ['neutral beige and white', 'camel and chocolate brown', 'navy and cream', 'black and ivory', 'olive and tan',
            'soft grey and white', 'white and denim blue', 'rust and cream', 'taupe and black'];
        return "Photo {$n} of {$total} in a set — show a DIFFERENT person from the other photos: {$hair[$i % 9]}, a different face and "
            . "skin tone, a different outfit in {$tones[($i * 4) % 9]} tones, {$place[($i * 2) % 9]}.";
    }
    $view = ['overhead flat-lay view', 'three-quarter angle view', 'straight-on eye-level view', 'wide view of the whole setting',
        'side view on a styled table', 'close view of one finished example', 'top-down view on a light background', 'low angle view', 'framed doorway view'];
    $tones = ['bright white and natural wood', 'warm rustic tones', 'cool marble and grey', 'soft pastel accents', 'dark moody wood',
        'fresh green accents', 'terracotta and cream', 'black and brass', 'blue and white'];
    return "Photo {$n} of {$total} in a set — show a DIFFERENT variation of the subject from the other photos (a different example, "
        . "arrangement and setting), {$view[$i % 9]}, {$tones[($i * 4) % 9]} color scheme.";
}

/**
 * Generates every photo a template needs: 1 for single-photo templates, several DIFFERENT photos
 * for collage templates (each with its own variation prompt, portrait-shaped so people fit whole).
 * Returns ['ok' => bool, 'images' => [bytes…], 'error' => ?string].
 */
function pin_generate_template_images(PDO $pdo, string $style, string $title, string $customPrompt, ?array $brief, string $sizeKey, string $provider, string $model, int $iterations): array
{
    $count = pin_template_image_count($style);
    $images = [];
    for ($i = 0; $i < $count; $i++) {
        $prompt = $count > 1
            ? build_pin_image_prompt($title, $customPrompt, $brief, [832, 1248], $i, $count)
            : build_pin_image_prompt($title, $customPrompt, $brief, pin_generation_dims($sizeKey));
        $r = ai_generate_pin_image_with_retry($pdo, $provider, $model, $prompt, $iterations);
        if (!$r['ok']) return ['ok' => false, 'images' => $images, 'error' => $r['error']];
        $images[] = $r['image_data'];
    }
    return ['ok' => true, 'images' => $images, 'error' => null];
}

/** Calls the configured image provider once (DeepInfra with $iterations steps, or Cloudflare via account rotation). */
function ai_generate_pin_image_raw(PDO $pdo, string $provider, string $model, array $promptPair, int $iterations = 4): array
{
    $positivePrompt = $promptPair['positive'];
    $negativePrompt = $promptPair['negative'];
    $width = (int)($promptPair['width'] ?? 1024) ?: 1024;
    $height = (int)($promptPair['height'] ?? 1024) ?: 1024;

    if ($provider === 'cloudflare') {
        // Try accounts one at a time: if one fails, move straight to the next rather than
        // surfacing a failure — an account can run out of Cloudflare's own rate/neuron quota
        // even when our own daily-usage counter still shows room, so a single account's
        // failure shouldn't be treated as "no capacity" if others are still available.
        $triedIds = [];
        $lastError = 'No Cloudflare image-generation capacity available right now — every account has hit its daily limit.';

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $account = get_available_cloudflare_account($pdo, $triedIds);
            if (!$account) {
                return ['ok' => false, 'image_data' => null, 'error' => $lastError];
            }
            $triedIds[] = (int)$account['id'];

            // The worker only forwards {prompt}. The "avoid" list must NOT be folded into it: a prompt that
            // says "text, words, letters, typography, captions…" makes diffusion models draw exactly that,
            // which is what put duplicate titles into Cloudflare-generated pins. Visual-only prompt instead.
            $cloudflarePrompt = $positivePrompt;
            $headers = ['Content-Type: application/json'];
            if (!empty($account['api_key'])) $headers[] = 'Authorization: Bearer ' . $account['api_key'];
            $ch = curl_init(rtrim($account['worker_url'], '/'));
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode(['prompt' => $cloudflarePrompt]),
                CURLOPT_TIMEOUT => 60,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode >= 300) {
                $err = $curlError ?: ('Cloudflare worker returned HTTP ' . $httpCode . (is_string($body) ? (': ' . mb_substr($body, 0, 200)) : ''));
                $lastError = $err;
                if ($httpCode === 429 || looks_like_cloudflare_limit_error($err)) {
                    mark_cloudflare_account_exhausted_today($pdo, (int)$account['id']);
                }
                continue; // try the next account
            }
            increment_cloudflare_usage($pdo, (int)$account['id']);

            if ($contentType && stripos($contentType, 'image/') === 0) {
                return ['ok' => true, 'image_data' => $body, 'error' => null];
            }
            $json = json_decode($body, true);
            $raw = $json['image'] ?? $json['image_base64'] ?? $json['b64_json'] ?? ($json['result']['image'] ?? null);
            if ($raw) {
                // The provided worker returns a full data URI ("data:image/jpeg;base64,xxxx"), not bare base64 — strip the prefix if present.
                $commaPos = strpos($raw, 'base64,');
                $b64 = $commaPos !== false ? substr($raw, $commaPos + 7) : $raw;
                $decoded = base64_decode($b64, true);
                if ($decoded !== false && strlen($decoded) > 100) {
                    return ['ok' => true, 'image_data' => $decoded, 'error' => null];
                }
            }
            if ($body !== '' && $body[0] !== '{') {
                // Some workers stream raw bytes without setting Content-Type correctly — accept as-is.
                return ['ok' => true, 'image_data' => $body, 'error' => null];
            }
            // A genuine content-error from a JSON error body (e.g. Cloudflare's own error envelope) —
            // check whether it smells like a limit before deciding whether to bench this account.
            $lastError = 'Unrecognized response from the Cloudflare worker — expected raw image bytes or {"image": "<base64 or data URI>"}.';
            if (is_array($json) && !empty($json['error']) && looks_like_cloudflare_limit_error((string)$json['error'])) {
                mark_cloudflare_account_exhausted_today($pdo, (int)$account['id']);
                $lastError = (string)$json['error'];
            }
        }
        return ['ok' => false, 'image_data' => null, 'error' => $lastError];
    }

    // DeepInfra. FLUX-1 models use the native inference endpoint (the only one that honours
    // num_inference_steps); FLUX-2 and Nano Banana use the OpenAI-compatible images endpoint.
    // If the preferred endpoint fails, the other one is tried once before giving up.
    $row = get_ai_provider_row($pdo, 'deepinfra', 'image');
    if (!$row || empty($row['api_key'])) {
        return ['ok' => false, 'image_data' => null, 'error' => 'No API key configured for DeepInfra image generation.'];
    }
    $apiKey = $row['api_key'];
    $model = $model ?: 'black-forest-labs/FLUX-1-schnell';
    $info = image_model_catalog()[$model] ?? ['api' => 'native', 'steps' => true, 'strict' => false];
    $prompt = !empty($info['strict']) ? ($promptPair['strict'] ?? $positivePrompt) : $positivePrompt;
    // DeepInfra accepts 128–1920 px per side.
    $width = max(128, min(1920, $width));
    $height = max(128, min(1920, $height));

    if (($info['api'] ?? 'native') === 'openai') {
        $size = !empty($info['square_only']) ? '1024x1024' : ($width . 'x' . $height);
        $r = deepinfra_image_openai($apiKey, $model, $prompt, $size);
        if (!$r['ok'] && $size !== '1024x1024') {
            $r = deepinfra_image_openai($apiKey, $model, $prompt, '1024x1024');
        }
        if (!$r['ok']) {
            $n = deepinfra_image_native($apiKey, $model, $prompt, $width, $height, 0);
            if ($n['ok']) return $n;
        }
        return $r;
    }

    // Native endpoint. FLUX-1 schnell/dev have no negative_prompt field — sending unknown fields
    // makes DeepInfra reject the request, which is why schnell images were failing.
    $steps = $iterations > 0 ? $iterations : (int)($info['default_steps'] ?? 4);
    if (!empty($info['steps'])) {
        $steps = max((int)($info['min_steps'] ?? 1), min((int)($info['max_steps'] ?? 50), $steps));
    }
    $r = deepinfra_image_native($apiKey, $model, $prompt, $width, $height, $steps);
    if ($r['ok']) return $r;
    // Fallback: OpenAI-compatible endpoint (fixed steps, but it works for every DeepInfra image model).
    $o = deepinfra_image_openai($apiKey, $model, $prompt, $width . 'x' . $height);
    if (!$o['ok']) $o = deepinfra_image_openai($apiKey, $model, $prompt, '1024x1024');
    if ($o['ok']) return $o;
    return ['ok' => false, 'image_data' => null, 'error' => $r['error'] . ' | OpenAI endpoint: ' . $o['error']];
}

/** Decodes a base64 string or data URI into image bytes, or null. */
function deepinfra_decode_image_payload($raw): ?string
{
    if (!is_string($raw) || $raw === '') return null;
    if (preg_match('~^https?://~i', $raw)) {
        $ch = curl_init($raw);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
        $bytes = curl_exec($ch);
        curl_close($ch);
        return ($bytes !== false && strlen($bytes) > 100) ? $bytes : null;
    }
    $commaPos = strpos($raw, 'base64,');
    $b64 = $commaPos !== false ? substr($raw, $commaPos + 7) : $raw;
    $decoded = base64_decode($b64, true);
    return ($decoded !== false && strlen($decoded) > 100) ? $decoded : null;
}

/** DeepInfra native inference call (/v1/inference/{model}). $steps = 0 → no step count sent. */
function deepinfra_image_native(string $apiKey, string $model, string $prompt, int $width, int $height, int $steps): array
{
    $body = ['prompt' => $prompt, 'width' => $width, 'height' => $height];
    if ($steps > 0) {
        $body['num_inference_steps'] = max(1, min(50, $steps));
        $body['num_images'] = 1;
    }
    $result = ai_http_post('https://api.deepinfra.com/v1/inference/' . $model,
        ["Authorization: Bearer " . $apiKey], $body, $steps > 10 ? 120 : 90);
    if ($result['ok']) {
        $data = $result['data'];
        $raw = $data['images'][0] ?? $data['image'] ?? ($data['data'][0]['b64_json'] ?? null) ?? ($data['output'][0] ?? null);
        $bytes = deepinfra_decode_image_payload($raw);
        if ($bytes !== null) return ['ok' => true, 'image_data' => $bytes, 'error' => null];
        return ['ok' => false, 'image_data' => null, 'error' => 'DeepInfra native endpoint returned no image.'];
    }
    return ['ok' => false, 'image_data' => null, 'error' => is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'Image generation failed.')];
}

/** DeepInfra OpenAI-compatible call (/v1/openai/images/generations). */
function deepinfra_image_openai(string $apiKey, string $model, string $prompt, string $size): array
{
    $result = ai_http_post('https://api.deepinfra.com/v1/openai/images/generations',
        ["Authorization: Bearer " . $apiKey],
        ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'n' => 1],
        120);
    $item = $result['data']['data'][0] ?? null;
    if ($result['ok'] && $item) {
        $bytes = deepinfra_decode_image_payload($item['b64_json'] ?? null) ?? deepinfra_decode_image_payload($item['url'] ?? null);
        if ($bytes !== null) return ['ok' => true, 'image_data' => $bytes, 'error' => null];
    }
    return ['ok' => false, 'image_data' => null, 'error' => is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'Image generation failed.')];
}

/** Retries generation up to 3 times: attempt 1 immediately, attempt 2 after 3s, attempt 3 after 4s. */
function ai_generate_pin_image_with_retry(PDO $pdo, string $provider, string $model, array $promptPair, int $iterations = 4): array
{
    $delays = [0, 3, 4];
    $lastError = null;
    for ($i = 0; $i < 3; $i++) {
        if ($delays[$i] > 0) sleep($delays[$i]);
        $result = ai_generate_pin_image_raw($pdo, $provider, $model, $promptPair, $iterations);
        if ($result['ok']) return $result;
        $lastError = $result['error'];
    }
    return ['ok' => false, 'image_data' => null, 'error' => $lastError ?: 'Image generation failed after 3 attempts.'];
}

/** Resizes+crops (cover fit) $src into the exact dimensions of $dst. */
function pin_image_cover_resize($src, $dst, int $dstW, int $dstH): void
{
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $srcRatio = $srcW / $srcH;
    $dstRatio = $dstW / $dstH;
    if ($srcRatio > $dstRatio) {
        $newH = $srcH;
        $newW = (int)round($srcH * $dstRatio);
        $srcX = (int)(($srcW - $newW) / 2);
        $srcY = 0;
    } else {
        $newW = $srcW;
        $newH = (int)round($srcW / $dstRatio);
        $srcX = 0;
        $srcY = (int)(($srcH - $newH) / 2);
    }
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $dstW, $dstH, $newW, $newH);
}

/**
 * Line height that can never let stacked lines overlap. GD renders a font "size" (points) at ~1.33× in
 * pixels, so a line height of size×1.1 is SMALLER than the letters themselves for tall condensed fonts
 * (Anton, Poppins Black …) — that is what made lines sit on top of each other. This measures the real
 * glyphs: cap height + (descenders only if the text has any) + a gap for outlines, and never returns
 * less than that.
 */
function pin_line_height(string $font, int $size, float $mul = 1.15, $lines = null): int
{
    $cap = imagettfbbox($size, 0, $font, 'HÉ');
    $capH = (int)(-$cap[7]);
    $text = is_array($lines) ? implode(' ', $lines) : (string)$lines;
    $desc = 0;
    if ($text === '' || preg_match('/[gjpqy,;(){}\[\]]/u', $text)) {
        $d = imagettfbbox($size, 0, $font, 'gjpqy');
        $desc = (int)max(0, $d[1]);
    }
    return max((int)round($size * $mul), $capH + $desc + (int)round($capH * 0.24));
}

/** Word-wraps $text into lines that fit $maxWidth at $size using TTF font $font, capped at 3 lines (with ellipsis). */
function pin_image_wrap_text(string $font, int $size, string $text, int $maxWidth, int $maxLines = 5): array
{
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        $bbox = imagettfbbox($size, 0, $font, $test);
        $width = $bbox[2] - $bbox[0];
        if ($width > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') $lines[] = $current;
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]) . '…';
    }
    return $lines;
}

/* pin_pick_auto_style() now lives in pin_template_registry.php (category-aware, supports a multi-selected pool). */

/**
 * Builds a WxH canvas from 1+ source images: a single cover-fit photo, or a
 * clean row-based collage grid (e.g. 5 images -> a 3-across row + a 2-across
 * row, matching how real Pinterest collage pins are laid out) with every
 * cell fully filled — no gaps, no blank tiles, so every photo renders clearly.
 */
function pin_build_photo_canvas(array $imageBytesList, int $W, int $H)
{
    $canvas = imagecreatetruecolor($W, $H);
    $sources = [];
    foreach ($imageBytesList as $bytes) {
        $img = @imagecreatefromstring($bytes);
        if ($img) $sources[] = $img;
    }
    if (empty($sources)) { imagedestroy($canvas); return null; }

    $count = count($sources);
    if ($count === 1) {
        pin_image_cover_resize($sources[0], $canvas, $W, $H);
    } else {
        $layouts = [2 => [2], 3 => [3], 4 => [2, 2], 5 => [3, 2], 6 => [3, 3]];
        $rowsLayout = $layouts[$count] ?? [$count];
        $rowH = intdiv($H, count($rowsLayout));
        $idx = 0;
        foreach ($rowsLayout as $rIdx => $numInRow) {
            $cellW = intdiv($W, $numInRow);
            for ($c = 0; $c < $numInRow; $c++) {
                $src = $sources[$idx] ?? $sources[count($sources) - 1];
                $cell = imagecreatetruecolor($cellW, $rowH);
                pin_image_cover_resize($src, $cell, $cellW, $rowH);
                imagecopy($canvas, $cell, $c * $cellW, $rIdx * $rowH, 0, 0, $cellW, $rowH);
                imagedestroy($cell);
                $idx++;
            }
        }
    }
    foreach ($sources as $src) imagedestroy($src);
    return $canvas;
}

/**
 * Shared "big stacked headline over a photo" engine, parameterized to cover
 * several presets: high_attractive_multi, fashion_outfits2 (centered),
 * simple / hairstyles_simple (single colour, no bar), simple2 (grey outline),
 * fashion_outfits (white + one italic accent line + solid bottom bar),
 * home_decor2 (single colour, text dominates the canvas, no bar).
 */
function pin_render_stacked_headline(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, array $opts, ?array $brandPalette = null): ?string
{
    $opts = array_merge([
        'multi_color' => true,
        'outline_rgb' => null,        // null = alternate dark/white outlines automatically
        'anchor' => 'bottom',         // 'bottom' | 'center'
        'bottom_bar' => true,
        'bar_color_rgb' => null,      // null = use palette accent
        'cta_badge' => true,
        'height_fraction' => 0.72,
        'italic_last_line' => false,
        'site_plain' => false,        // plain small site text instead of a bar
    ], $opts);

    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas($imageBytesList, $W, $H);
    if (!$canvas) return null;
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $palette = pin_design_palette();
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $accent = imagecolorallocate($canvas, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2]);
    $accent2 = imagecolorallocate($canvas, $palette['accent2'][0], $palette['accent2'][1], $palette['accent2'][2]);
    $dark = imagecolorallocate($canvas, $palette['dark'][0], $palette['dark'][1], $palette['dark'][2]);
    $barColor = $opts['bar_color_rgb'] ? imagecolorallocate($canvas, ...$opts['bar_color_rgb']) : $accent;
    $fixedOutline = $opts['outline_rgb'] ? imagecolorallocate($canvas, ...$opts['outline_rgb']) : null;

    // Optional user-selected brand palette overrides the auto-picked design palette above:
    // brand colors replace the multi-color headline lines, and the website bar / CTA badge
    // use the user's own text+background picks instead of the accent color.
    $brandFills = null;
    if ($brandPalette) {
        $brandFills = array_map(fn($rgb) => imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]), $brandPalette['colors']);
        if (!empty($brandPalette['website_bg']) && $opts['bar_color_rgb'] === null) {
            $barColor = imagecolorallocate($canvas, ...$brandPalette['website_bg']);
        }
    }
    $siteTextColor = ($brandPalette && !empty($brandPalette['website_text'])) ? imagecolorallocate($canvas, ...$brandPalette['website_text']) : $white;
    $ctaBgColor = ($brandPalette && !empty($brandPalette['cta_bg'])) ? imagecolorallocate($canvas, ...$brandPalette['cta_bg']) : $accent;
    $ctaTextColor = ($brandPalette && !empty($brandPalette['cta_text'])) ? imagecolorallocate($canvas, ...$brandPalette['cta_text']) : $white;

    // A soft overall darkening so bold outlined text stays readable over any photo.
    $scrim = imagecolorallocatealpha($canvas, 0, 0, 0, 95);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $scrim);

    $fontBlack = __DIR__ . '/../assets/fonts/Poppins-Black.ttf';
    $fontBold = __DIR__ . '/../assets/fonts/Poppins-Bold.ttf';
    $fontItalic = __DIR__ . '/../assets/fonts/Lobster-Regular.ttf';
    if (!is_file($fontBlack)) $fontBlack = $fontBold;
    if (!is_file($fontItalic)) $fontItalic = $fontBold;

    $barHeight = $opts['bottom_bar'] ? (int)($H * 0.052) : ($opts['site_plain'] && $website !== '' ? (int)($H * 0.035) : 0);
    $ctaReserve = ($opts['cta_badge'] && $ctaText !== '') ? (int)($H * 0.06) : 0;

    $titleSize = max(34, (int)($W / 8.2));
    $maxHeadlineHeight = (int)(($H - $barHeight - $ctaReserve - 60) * $opts['height_fraction']);
    $attempts = 0;
    do {
        $wrapWidth = $W - (int)($titleSize * 1.1) - 60;
        // Wrap with a generous line cap here so the fit-check below sees the TRUE line count
        // needed at this size, instead of a silently-truncated one that would mask overflow.
        $lines = pin_image_wrap_text($fontBlack, $titleSize, pin_image_upper($title), $wrapWidth, 12);
        $lineHeight = pin_line_height($fontBlack, $titleSize, 1.12, $lines);
        $totalTextHeight = count($lines) * $lineHeight;
        $maxLineWidth = 0;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($titleSize, 0, $fontBlack, $line);
            $maxLineWidth = max($maxLineWidth, $bbox[2] - $bbox[0]);
        }
        // Also guard against a single unbreakable long word overflowing the canvas edges.
        $fits = count($lines) <= 5 && $totalTextHeight <= $maxHeadlineHeight && $maxLineWidth <= $wrapWidth;
        if (!$fits) $titleSize = max(18, (int)($titleSize * 0.85));
        $attempts++;
    } while (!$fits && $attempts < 8);
    if (count($lines) > 5) {
        $lines = array_slice($lines, 0, 5);
        $lines[4] = rtrim($lines[4]) . '…';
    }
    $lineHeight = pin_line_height($fontBlack, $titleSize, 1.12, $lines);
    $totalTextHeight = count($lines) * $lineHeight;

    if ($opts['anchor'] === 'center') {
        $available = $H - $barHeight - $ctaReserve;
        $startY = max(40, (int)(($available - $totalTextHeight) / 2) - 10);
    } else {
        $blockBottom = $H - $barHeight - $ctaReserve - 30;
        $startY = max(50, $blockBottom - $totalTextHeight);
    }

    if ($opts['multi_color'] && $brandFills) {
        $outlineForBrand = $fixedOutline ?: $dark;
        $lineColors = array_map(fn($c) => ['fill' => $c, 'outline' => $outlineForBrand], $brandFills);
    } else {
        $lineColors = $opts['multi_color']
            ? ($fixedOutline
                ? [['fill' => $white, 'outline' => $fixedOutline], ['fill' => $accent, 'outline' => $fixedOutline], ['fill' => $accent2, 'outline' => $fixedOutline]]
                : [['fill' => $white, 'outline' => $dark], ['fill' => $accent, 'outline' => $white], ['fill' => $accent2, 'outline' => $dark]])
            : [['fill' => $white, 'outline' => $fixedOutline ?: $dark]];
    }

    foreach ($lines as $i => $line) {
        $isLast = $i === count($lines) - 1;
        $useItalic = $opts['italic_last_line'] && $isLast && count($lines) > 1;
        $font = $useItalic ? $fontItalic : $fontBlack;
        $size = $useItalic ? (int)($titleSize * 0.9) : $titleSize;
        $bbox = imagettfbbox($size, 0, $font, $line);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        $y = $startY + ($i + 1) * $lineHeight;
        $c = $lineColors[$i % count($lineColors)];
        pin_image_draw_outlined_text($canvas, $size, $font, $line, $x, $y, $c['fill'], $c['outline'], max(3, (int)($size * 0.09)));
    }

    if ($opts['cta_badge'] && $ctaText !== '') {
        $ctaSize = max(16, (int)($W / 26));
        $bbox = imagettfbbox($ctaSize, 0, $fontBold, pin_image_upper($ctaText));
        $textWidth = $bbox[2] - $bbox[0];
        $padX = 28; $padY = 15;
        $badgeW = $textWidth + $padX * 2;
        $badgeH = $ctaSize + $padY * 2;
        $badgeX = (int)(($W - $badgeW) / 2);
        $badgeY = $H - $barHeight - $badgeH - 16;
        imagefilledrectangle($canvas, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $ctaBgColor);
        imagettftext($canvas, $ctaSize, 0, $badgeX + $padX, $badgeY + $badgeH - $padY + 2, $ctaTextColor, $fontBold, pin_image_upper($ctaText));
    }

    if ($opts['bottom_bar'] && $website !== '') {
        imagefilledrectangle($canvas, 0, $H - $barHeight, $W, $H, $barColor);
        $siteSize = max(14, (int)($barHeight * 0.42));
        $siteLabel = pin_image_upper($website);
        $bbox = imagettfbbox($siteSize, 0, $fontBold, $siteLabel);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        $y = $H - (int)($barHeight / 2) + (int)($siteSize / 3);
        imagettftext($canvas, $siteSize, 0, $x, $y, $siteTextColor, $fontBold, $siteLabel);
    } elseif ($opts['site_plain'] && $website !== '') {
        $siteSize = max(12, (int)($H * 0.022));
        $siteLabel = pin_image_upper($website);
        $bbox = imagettfbbox($siteSize, 0, $fontBold, $siteLabel);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        pin_image_draw_outlined_text($canvas, $siteSize, $fontBold, $siteLabel, $x, $H - 16, $siteTextColor, $fixedOutline ?: $dark, 2);
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * "Unique Multi Colored" — decorative serif display font, each line a
 * different vivid color with no heavy outline, a small italic-script credit
 * line at the very bottom, no CTA badge, no bottom bar.
 */
function pin_render_unique_multi(array $imageBytesList, string $title, string $website, string $sizeKey, bool $center, ?array $brandPalette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas($imageBytesList, $W, $H);
    if (!$canvas) return null;

    $scrim = imagecolorallocatealpha($canvas, 0, 0, 0, 90);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $scrim);

    $fontDisplay = __DIR__ . '/../assets/fonts/AbrilFatface-Regular.ttf';
    $fontScript = __DIR__ . '/../assets/fonts/Lobster-Regular.ttf';
    if (!is_file($fontDisplay)) $fontDisplay = __DIR__ . '/../assets/fonts/Poppins-Black.ttf';

    $colors = $brandPalette ? $brandPalette['colors'] : [[255, 255, 255], [244, 162, 97], [231, 111, 81], [233, 196, 106], [42, 157, 143]];
    $allocated = array_map(fn($c) => imagecolorallocate($canvas, $c[0], $c[1], $c[2]), $colors);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 45);
    $siteColor = ($brandPalette && !empty($brandPalette['website_text'])) ? imagecolorallocate($canvas, ...$brandPalette['website_text']) : $allocated[0];

    $titleSize = max(30, (int)($W / 9));
    $attempts = 0;
    do {
        $wrapWidth = $W - (int)($titleSize * 0.8) - 60;
        $lines = pin_image_wrap_text($fontDisplay, $titleSize, $title, $wrapWidth, 12); // mixed case — decorative serif looks better than all-caps
        $lineHeight = pin_line_height($fontDisplay, $titleSize, 1.2, $lines);
        $totalTextHeight = count($lines) * $lineHeight;
        $maxLineWidth = 0;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($titleSize, 0, $fontDisplay, $line);
            $maxLineWidth = max($maxLineWidth, $bbox[2] - $bbox[0]);
        }
        $fits = count($lines) <= 5 && $totalTextHeight <= $H * 0.68 && $maxLineWidth <= $wrapWidth;
        if (!$fits) $titleSize = max(18, (int)($titleSize * 0.85));
        $attempts++;
    } while (!$fits && $attempts < 8);
    if (count($lines) > 5) {
        $lines = array_slice($lines, 0, 5);
        $lines[4] = rtrim($lines[4]) . '…';
    }
    $lineHeight = pin_line_height($fontDisplay, $titleSize, 1.2, $lines);
    $totalTextHeight = count($lines) * $lineHeight;

    $startY = $center
        ? max(40, (int)(($H - $totalTextHeight) / 2) - 10)
        : max(40, $H - 70 - $totalTextHeight);

    foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($titleSize, 0, $fontDisplay, $line);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        $y = $startY + ($i + 1) * $lineHeight;
        $color = $allocated[$i % count($allocated)];
        imagettftext($canvas, $titleSize, 0, $x + 2, $y + 2, $shadow, $fontDisplay, $line);
        imagettftext($canvas, $titleSize, 0, $x, $y, $color, $fontDisplay, $line);
    }

    if ($website !== '') {
        $siteSize = max(12, (int)($H * 0.02));
        $bbox = imagettfbbox($siteSize, 0, $fontScript, $website);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        imagettftext($canvas, $siteSize, 0, $x + 1, $H - 15, $shadow, $fontScript, $website);
        imagettftext($canvas, $siteSize, 0, $x, $H - 16, $siteColor, $fontScript, $website);
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * "Home Decor" — a solid light band across the top with dark bold text,
 * a single clear photo filling the rest, no bar, no CTA.
 */
/** Darkens a rectangular region of an existing canvas — used to keep any residual hallucinated text in an AI photo illegible. */
function pin_apply_scrim($canvas, int $x, int $y, int $w, int $h, int $alpha = 100): void
{
    imagealphablending($canvas, true);
    $scrim = imagecolorallocatealpha($canvas, 0, 0, 0, $alpha);
    imagefilledrectangle($canvas, $x, $y, $x + $w, $y + $h, $scrim);
}

function pin_render_home_decor(array $imageBytesList, string $title, string $sizeKey, ?array $brandPalette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $bandBgRgb = ($brandPalette && !empty($brandPalette['website_bg'])) ? $brandPalette['website_bg'] : ($brandPalette ? $brandPalette['colors'][0] : [240, 234, 222]);
    $bandTextRgb = ($brandPalette && !empty($brandPalette['website_text'])) ? $brandPalette['website_text'] : [30, 27, 22];
    $cream = imagecolorallocate($canvas, ...$bandBgRgb);
    $dark = imagecolorallocate($canvas, ...$bandTextRgb);

    $fontBold = __DIR__ . '/../assets/fonts/Poppins-ExtraBold.ttf';
    $titleSize = max(28, (int)($W / 14));
    $bandHeight = (int)($H * 0.2);
    $wrapWidth = $W - 70;
    $lines = pin_image_wrap_text($fontBold, $titleSize, $title, $wrapWidth, 12);
    while (count($lines) > 2 && $titleSize > 16) {
        $titleSize = (int)($titleSize * 0.88);
        $lines = pin_image_wrap_text($fontBold, $titleSize, $title, $wrapWidth, 12);
    }
    if (count($lines) > 2) {
        $lines = array_slice($lines, 0, 2);
        $lines[1] = rtrim($lines[1]) . '…';
    }
    $lineHeight = pin_line_height($fontBold, $titleSize, 1.25, $lines);
    $bandHeight = max($bandHeight, count($lines) * $lineHeight + 40);

    imagefilledrectangle($canvas, 0, 0, $W, $bandHeight, $cream);
    $startY = (int)(($bandHeight - count($lines) * $lineHeight) / 2);
    foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($titleSize, 0, $fontBold, $line);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        $y = $startY + ($i + 1) * $lineHeight;
        imagettftext($canvas, $titleSize, 0, $x, $y, $dark, $fontBold, $line);
    }

    $photoCanvas = pin_build_photo_canvas($imageBytesList, $W, $H - $bandHeight);
    if ($photoCanvas) {
        imagecopy($canvas, $photoCanvas, 0, $bandHeight, 0, 0, $W, $H - $bandHeight);
        imagedestroy($photoCanvas);
        pin_apply_scrim($canvas, 0, $bandHeight, $W, $H - $bandHeight, 55);
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * "Recipe Food" / "Recipe Food 2" — two stacked photos with a horizontal
 * text band sandwiched between them: a dark band with plain white text
 * (recipe_food), or a white band with a different vivid color per line
 * (recipe_food2).
 */
function pin_render_recipe(array $imageBytesList, string $title, string $sizeKey, bool $lightBand, ?array $brandPalette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);

    $fontBold = __DIR__ . '/../assets/fonts/Poppins-ExtraBold.ttf';
    $titleSize = max(30, (int)($W / 11));
    $wrapWidth = $W - 80;
    $lines = pin_image_wrap_text($fontBold, $titleSize, pin_image_upper($title), $wrapWidth, 12);
    while (count($lines) > 3 && $titleSize > 16) {
        $titleSize = (int)($titleSize * 0.88);
        $lines = pin_image_wrap_text($fontBold, $titleSize, pin_image_upper($title), $wrapWidth, 12);
    }
    if (count($lines) > 3) {
        $lines = array_slice($lines, 0, 3);
        $lines[2] = rtrim($lines[2]) . '…';
    }
    $lineHeight = pin_line_height($fontBold, $titleSize, 1.28, $lines);
    $bandHeight = min((int)($H * 0.34), count($lines) * $lineHeight + 50);

    $photoHeight = intdiv($H - $bandHeight, 2);
    $topPhoto = pin_build_photo_canvas(array_slice($imageBytesList, 0, max(1, intdiv(count($imageBytesList) + 1, 2))), $W, $photoHeight);
    $bottomImgs = array_slice($imageBytesList, intdiv(count($imageBytesList) + 1, 2));
    if (empty($bottomImgs)) $bottomImgs = $imageBytesList;
    $bottomPhoto = pin_build_photo_canvas($bottomImgs, $W, $H - $photoHeight - $bandHeight);

    if ($topPhoto) { imagecopy($canvas, $topPhoto, 0, 0, 0, 0, $W, $photoHeight); imagedestroy($topPhoto); pin_apply_scrim($canvas, 0, 0, $W, $photoHeight, 45); }
    $bandColor = $lightBand ? imagecolorallocate($canvas, 255, 255, 255) : imagecolorallocate($canvas, 35, 24, 18);
    imagefilledrectangle($canvas, 0, $photoHeight, $W, $photoHeight + $bandHeight, $bandColor);
    if ($bottomPhoto) { imagecopy($canvas, $bottomPhoto, 0, $photoHeight + $bandHeight, 0, 0, $W, $H - $photoHeight - $bandHeight); imagedestroy($bottomPhoto); pin_apply_scrim($canvas, 0, $photoHeight + $bandHeight, $W, $H - $photoHeight - $bandHeight, 45); }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    if ($brandPalette) {
        $multiColors = array_map(fn($c) => imagecolorallocate($canvas, ...$c), $brandPalette['colors']);
    } else {
        $palette = ['recipe' => imagecolorallocate($canvas, 230, 126, 34), 'recipe2' => imagecolorallocate($canvas, 192, 57, 43), 'recipe3' => imagecolorallocate($canvas, 120, 30, 25)];
        $multiColors = array_values($palette);
    }
    $startY = $photoHeight + (int)(($bandHeight - count($lines) * $lineHeight) / 2);
    foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($titleSize, 0, $fontBold, $line);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int)(($W - $textWidth) / 2);
        $y = $startY + ($i + 1) * $lineHeight;
        $color = $lightBand ? $multiColors[$i % count($multiColors)] : $white;
        imagettftext($canvas, $titleSize, 0, $x, $y, $color, $fontBold, $line);
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * "Pet Recipe Foods" — a playful full-photo layout: a yellow banner box with
 * a script/bold title, a dark banner box with a smaller subtitle, and a
 * solid footer bar with the website. (A simplified take on the illustrated
 * brush-stroke/badge style — GD can't easily draw brush textures or icons.)
 */
function pin_render_pet_recipe(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $brandPalette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas($imageBytesList, $W, $H);
    if (!$canvas) return null;
    pin_apply_scrim($canvas, 0, 0, $W, $H, 40); // light overall dimming so any stray hallucinated text stays illegible

    $fontScript = __DIR__ . '/../assets/fonts/Lobster-Regular.ttf';
    $fontBold = __DIR__ . '/../assets/fonts/Poppins-ExtraBold.ttf';
    $yellow = imagecolorallocate($canvas, ...($brandPalette['colors'][0] ?? [255, 209, 51]));
    $brown = imagecolorallocate($canvas, ...($brandPalette['colors'][1] ?? [62, 39, 21]));
    $red = imagecolorallocate($canvas, ...($brandPalette['colors'][2] ?? [200, 40, 40]));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $barBg = ($brandPalette && !empty($brandPalette['website_bg'])) ? imagecolorallocate($canvas, ...$brandPalette['website_bg']) : $brown;
    $barText = ($brandPalette && !empty($brandPalette['website_text'])) ? imagecolorallocate($canvas, ...$brandPalette['website_text']) : $yellow;

    $words = explode(' ', trim($title));
    $splitAt = max(1, (int)(count($words) * 0.6));
    $mainWords = implode(' ', array_slice($words, 0, $splitAt));
    $subWords = implode(' ', array_slice($words, $splitAt));
    if ($subWords === '') { $subWords = $mainWords; $mainWords = ''; }

    $barHeight = $website !== '' ? (int)($H * 0.05) : 0;
    $y = (int)($H * 0.06);

    if ($mainWords !== '') {
        $size = max(30, (int)($W / 10));
        $lines = pin_image_wrap_text($fontScript, $size, $mainWords, $W - 60);
        $lh = pin_line_height($fontScript, $size, 1.2, $lines);
        $bandH = count($lines) * $lh + 30;
        imagefilledrectangle($canvas, 20, $y, $W - 20, $y + $bandH, $yellow);
        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($size, 0, $fontScript, $line);
            $tw = $bbox[2] - $bbox[0];
            $x = (int)(($W - $tw) / 2);
            imagettftext($canvas, $size, 0, $x, $y + ($i + 1) * $lh, $red, $fontScript, $line);
        }
        $y += $bandH + 14;
    }

    if ($subWords !== '') {
        $size = max(22, (int)($W / 16));
        $lines = pin_image_wrap_text($fontBold, $size, $subWords, $W - 60);
        $lh = pin_line_height($fontBold, $size, 1.25, $lines);
        $bandH = count($lines) * $lh + 26;
        imagefilledrectangle($canvas, 20, $y, $W - 20, $y + $bandH, $brown);
        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($size, 0, $fontBold, $line);
            $tw = $bbox[2] - $bbox[0];
            $x = (int)(($W - $tw) / 2);
            imagettftext($canvas, $size, 0, $x, $y + ($i + 1) * $lh, $white, $fontBold, $line);
        }
    }

    if ($barHeight > 0) {
        imagefilledrectangle($canvas, 0, $H - $barHeight, $W, $H, $barBg);
        $siteSize = max(14, (int)($barHeight * 0.42));
        $siteLabel = pin_image_upper($website);
        $bbox = imagettfbbox($siteSize, 0, $fontBold, $siteLabel);
        $tw = $bbox[2] - $bbox[0];
        $x = (int)(($W - $tw) / 2);
        imagettftext($canvas, $siteSize, 0, $x, $H - (int)($barHeight / 2) + (int)($siteSize / 3), $barText, $fontBold, $siteLabel);
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * "Fashion Outfits 3" — purpose-built for collages: a row of photos on top,
 * a text band in the middle (number badge + bold headline + a colored box
 * with the rest of the title), and another row of photos on the bottom.
 */
function pin_render_collage_banner(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $brandPalette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $white);

    $count = count($imageBytesList);
    $topCount = max(1, (int)ceil($count / 2));
    $topImgs = array_slice($imageBytesList, 0, $topCount);
    $bottomImgs = array_slice($imageBytesList, $topCount);
    if (empty($bottomImgs)) $bottomImgs = $topImgs;

    $topH = (int)($H * 0.32);
    $bottomH = (int)($H * 0.24);
    $midH = $H - $topH - $bottomH;

    $topCanvas = pin_build_photo_canvas($topImgs, $W, $topH);
    if ($topCanvas) { imagecopy($canvas, $topCanvas, 0, 0, 0, 0, $W, $topH); imagedestroy($topCanvas); pin_apply_scrim($canvas, 0, 0, $W, $topH, 35); }
    // The bottom photo strip is placed AFTER the middle text content is measured below,
    // so its position hugs the content instead of leaving a fixed (and often too-large) gap.

    $palette = pin_design_palette();
    $accent = $brandPalette
        ? imagecolorallocate($canvas, ...$brandPalette['colors'][0])
        : imagecolorallocate($canvas, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2]);
    $dark = imagecolorallocate($canvas, 20, 20, 20);
    $boxTextColor = ($brandPalette && !empty($brandPalette['cta_text'])) ? imagecolorallocate($canvas, ...$brandPalette['cta_text']) : $white;
    $fontBold = __DIR__ . '/../assets/fonts/Poppins-ExtraBold.ttf';
    $fontBlack = __DIR__ . '/../assets/fonts/Poppins-Black.ttf';

    $number = null;
    $rest = $title;
    if (preg_match('/^(\d{1,3})\s+(.*)$/', trim($title), $m)) {
        $number = $m[1];
        $rest = $m[2];
    }
    $words = explode(' ', trim($rest));
    $half = max(1, (int)ceil(count($words) * 0.45));
    $headline = implode(' ', array_slice($words, 0, $half));
    $boxText = implode(' ', array_slice($words, $half));

    $cy = $topH;
    if ($number !== null) {
        // Smaller badge, straddling the boundary between the top photo row and the text
        // band (half over the photo, half over the band) — matching the reference layout.
        $r = (int)($midH * 0.16);
        $cx = (int)($W / 2);
        $badgeCy = $topH;
        imagefilledellipse($canvas, $cx, $badgeCy, $r * 2, $r * 2, $accent);
        $numSize = (int)($r * 1.05);
        $bbox = imagettfbbox($numSize, 0, $fontBlack, $number);
        $tw = $bbox[2] - $bbox[0];
        $th = $bbox[1] - $bbox[7];
        imagettftext($canvas, $numSize, 0, $cx - (int)($tw / 2), $badgeCy + (int)($th / 2), $white, $fontBlack, $number);
        $cy = $badgeCy + $r + 10;
    } else {
        $cy += 20;
    }

    if ($headline !== '') {
        $size = max(24, (int)($W / 13));
        $lines = pin_image_wrap_text($fontBlack, $size, pin_image_upper($headline), $W - 60);
        $lh = pin_line_height($fontBlack, $size, 1.15, $lines);
        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($size, 0, $fontBlack, $line);
            $tw = $bbox[2] - $bbox[0];
            $x = (int)(($W - $tw) / 2);
            $y = $cy + ($i + 1) * $lh;
            pin_image_draw_outlined_text($canvas, $size, $fontBlack, $line, $x, $y, $dark, $accent, 3);
        }
        $cy += count($lines) * $lh + 2; // tight gap before the subtitle box, matching the reference
    }

    if ($boxText !== '') {
        $size = max(18, (int)($W / 18));
        $lines = pin_image_wrap_text($fontBold, $size, pin_image_upper($boxText), $W - 100);
        $lh = pin_line_height($fontBold, $size, 1.3, $lines);
        $boxH = count($lines) * $lh + 24;
        $boxW = $W - 80;
        $boxX = (int)(($W - $boxW) / 2);
        imagefilledrectangle($canvas, $boxX, $cy, $boxX + $boxW, $cy + $boxH, $accent);
        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($size, 0, $fontBold, $line);
            $tw = $bbox[2] - $bbox[0];
            $x = (int)(($W - $tw) / 2);
            $y = $cy + 20 + $i * $lh + $size;
            imagettftext($canvas, $size, 0, $x, $y, $boxTextColor, $fontBold, $line);
        }
        $cy += $boxH;
    }

    // Bottom photo strip hugs the actual content (minimum ~15% of H) instead of a fixed gap.
    $bottomY = min($H - (int)($H * 0.15), $cy + 18);
    $bottomH = $H - $bottomY;
    $bottomCanvas = pin_build_photo_canvas($bottomImgs, $W, $bottomH);
    if ($bottomCanvas) { imagecopy($canvas, $bottomCanvas, 0, $bottomY, 0, 0, $W, $bottomH); imagedestroy($bottomCanvas); pin_apply_scrim($canvas, 0, $bottomY, $W, $bottomH, 35); }

    ob_start();
    imagejpeg($canvas, null, 90);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

/**
 * Converts a '#RRGGBB' (or 'RRGGBB') hex color string to an [r, g, b] array,
 * or null if it's missing/invalid. Used to turn the user's brand-palette
 * color-picker values into GD-friendly RGB triplets.
 */
function pin_hex_to_rgb(?string $hex): ?array
{
    if (!$hex) return null;
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return null;
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Normalizes a raw brand color palette (as submitted by the "Brand Color
 * Palette" UI in bulk-schedule.php / auto-website-create.php /
 * auto-article-create.php) into a clean array of RGB triplets, or null when
 * the feature wasn't enabled / the data is invalid — in which case callers
 * fall back to the AI's own automatic color choices, exactly as before.
 *
 * Accepts either a JSON string or an already-decoded array, shaped like:
 *   { enabled: true, colors: ['#RRGGBB', ...] (3 or 4),
 *     website_text_color, website_bg_color, cta_text_color, cta_bg_color }
 */
function pin_normalize_color_palette($raw): ?array
{
    if (is_string($raw)) {
        $raw = trim($raw) === '' ? null : json_decode($raw, true);
    }
    if (!is_array($raw) || empty($raw['enabled'])) return null;

    $colors = [];
    foreach ((array)($raw['colors'] ?? []) as $c) {
        $rgb = pin_hex_to_rgb(is_string($c) ? $c : null);
        if ($rgb) $colors[] = $rgb;
    }
    $colors = array_slice($colors, 0, 4);
    if (count($colors) < 3) return null; // need at least 3 brand colors for a usable palette

    return [
        'colors' => $colors,
        'website_bg' => pin_hex_to_rgb($raw['website_bg_color'] ?? null),
        'website_text' => pin_hex_to_rgb($raw['website_text_color'] ?? null),
        'cta_bg' => pin_hex_to_rgb($raw['cta_bg_color'] ?? null),
        'cta_text' => pin_hex_to_rgb($raw['cta_text_color'] ?? null),
    ];
}

/**
 * Composites one or more raw generated background images into a finished pin
 * using the chosen preset $style (or auto-picks one from the title). Returns
 * raw JPEG bytes.
 *
 * $colorPalette is optional: a JSON string or array from the "Brand Color
 * Palette" UI. When present and valid it overrides the AI's automatically
 * picked colors (headline text colors, website bar, CTA badge) with the
 * user's own brand colors; when absent/disabled/invalid, behavior is
 * unchanged from before this feature existed.
 */
function compose_pin_image(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, string $style = 'high_attractive_multi', $colorPalette = null): ?string
{
    // Resolve "auto" / comma lists once, so the template we remember is the one actually drawn.
    $style = pin_resolve_style($style, $title);
    $out = compose_pin_image_render($imageBytesList, $title, $website, $ctaText, $sizeKey, $style, $colorPalette);
    // Analytics → Template Tracking: fingerprint the finished image with its template.
    if ($out !== null && $out !== '' && function_exists('tt_record_image')) {
        $reg = function_exists('pin_template_registry') ? pin_template_registry() : [];
        tt_record_image($out, 'style', $style, $reg[$style]['name'] ?? $style);
    }
    return $out;
}

function compose_pin_image_render(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, string $style = 'high_attractive_multi', $colorPalette = null): ?string
{
    // "auto", a single key, or a comma list from the multi-select template picker.
    $style = pin_resolve_style($style, $title);

    $palette = is_array($colorPalette) && isset($colorPalette['colors']) && !isset($colorPalette['enabled'])
        ? $colorPalette              // already-normalized (internal re-use)
        : pin_normalize_color_palette($colorPalette);

    if (strpos($style, 'tpl_') === 0) {
        $out = pt_render($style, $imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        if ($out !== null) return $out;
    }
    if (strpos($style, 'tpl2_') === 0 || strpos($style, 'tpl3_') === 0 || strpos($style, 'tpl4_') === 0) {
        $out = pt2_render($style, $imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        if ($out !== null) return $out;
    }

    switch ($style) {
        case 'simple':
        case 'hairstyles_simple':
            return pin_render_stacked_headline($imageBytesList, $title, $website, '', $sizeKey, [
                'multi_color' => false, 'outline_rgb' => [20, 20, 20], 'anchor' => 'bottom',
                'bottom_bar' => false, 'cta_badge' => false, 'site_plain' => true, 'height_fraction' => 0.5,
            ], $palette);
        case 'simple2':
            return pin_render_stacked_headline($imageBytesList, $title, $website, $ctaText, $sizeKey, [
                'multi_color' => true, 'outline_rgb' => [128, 128, 128], 'anchor' => 'bottom',
                'bottom_bar' => true, 'cta_badge' => true, 'height_fraction' => 0.65,
            ], $palette);
        case 'unique_multi':
            return pin_render_unique_multi($imageBytesList, $title, $website, $sizeKey, false, $palette);
        case 'fashion_outfits2':
            return pin_render_stacked_headline($imageBytesList, $title, $website, $ctaText, $sizeKey, [
                'multi_color' => true, 'anchor' => 'center', 'bottom_bar' => false, 'cta_badge' => false, 'height_fraction' => 0.6,
            ], $palette);
        case 'fashion_outfits':
            return pin_render_stacked_headline($imageBytesList, $title, $website, '', $sizeKey, [
                'multi_color' => false, 'outline_rgb' => [15, 15, 15], 'anchor' => 'bottom',
                'bottom_bar' => true, 'bar_color_rgb' => [10, 10, 10], 'cta_badge' => false,
                'italic_last_line' => true, 'height_fraction' => 0.62,
            ], $palette);
        case 'fashion_outfits3':
            return pin_render_collage_banner($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'recipe_food':
            return pin_render_recipe($imageBytesList, $title, $sizeKey, false, $palette);
        case 'recipe_food2':
            return pin_render_recipe($imageBytesList, $title, $sizeKey, true, $palette);
        case 'pet_recipe':
            return pin_render_pet_recipe($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'home_decor':
            return pin_render_home_decor($imageBytesList, $title, $sizeKey, $palette);
        case 'home_decor2':
            return pin_render_stacked_headline($imageBytesList, $title, $website, '', $sizeKey, [
                'multi_color' => false, 'outline_rgb' => [15, 15, 15], 'anchor' => 'bottom',
                'bottom_bar' => false, 'cta_badge' => false, 'site_plain' => true, 'height_fraction' => 0.86,
            ], $palette);
        case 'home_decor3':
            return pin_render_home_decor3($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'home_decor4':
            return pin_render_home_decor4($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'home_decor5':
            return pin_render_home_decor5($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'home_decor6':
            return pin_render_home_decor6($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food3':
            return pin_render_recipe_food3($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'recipe_food4':
            return pin_render_recipe_food4($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food5':
            return pin_render_recipe_food5($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food6':
            return pin_render_recipe_food6($imageBytesList, $title, $website, $sizeKey, $palette);
        case 'recipe_food7':
            return pin_render_recipe_food7($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food8':
            return pin_render_recipe_food8($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food9':
            return pin_render_recipe_food9($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'recipe_food10':
            return pin_render_recipe_food10($imageBytesList, $title, $website, $ctaText, $sizeKey, $palette);
        case 'high_attractive_multi':
        default:
            return pin_render_stacked_headline($imageBytesList, $title, $website, $ctaText, $sizeKey, [], $palette);
    }
}
