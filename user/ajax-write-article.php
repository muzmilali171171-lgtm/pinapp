<?php
/**
 * AJAX backend for user/write-article.php. Every action returns JSON —
 * the front-end JS swaps DOM content itself, so the browser never navigates.
 */

// Content generation (AI text + multiple section images) can legitimately take
// a couple of minutes on shared hosting's slower outbound connections — raise
// the time limit and make sure that even a fatal error still comes back as
// JSON instead of an HTML error page (which would break the JS fetch() call
// and leave the button stuck with no visible error).
@set_time_limit(280);
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message'] . ' (' . basename($error['file']) . ':' . $error['line'] . ')']);
    }
});

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$articleSettings = get_article_settings($pdo);

function respond(array $data): void
{
    echo json_encode($data);
    exit;
}

/** Build the {title, content, sections[{heading,brief}], ...} shape the JS expects. */
function article_public_state(array $article): array
{
    return [
        'id' => (int)$article['id'],
        'title' => $article['title'],
        'content' => $article['content'],
        'website_id' => $article['website_id'],
        'category' => $article['category'],
        'tags' => $article['tags'],
        'status' => $article['status'],
        'wp_post_url' => $article['wp_post_url'],
        'last_error' => $article['last_error'],
        'sections' => array_map(function ($s) {
            return [
                'heading' => $s['heading'] ?? '',
                'brief' => $s['brief'] ?? '',
            ];
        }, $article['sections'] ?? []),
    ];
}

$action = $_POST['action'] ?? '';
$articleId = (int)($_POST['id'] ?? 0);
$article = $articleId ? load_article($pdo, $articleId, $user['id']) : null;

/* ---------------- get_state: load an existing article's full current state ---------------- */
if ($action === 'get_state') {
    if (!$article) respond(['ok' => false, 'error' => 'Article not found.']);
    respond(['ok' => true, 'article' => article_public_state($article)]);
}

/* ---------------- generate_outline ---------------- */
if ($action === 'generate_outline') {
    if (!$articleSettings || !$articleSettings['text_provider']) {
        respond(['ok' => false, 'error' => 'AI writing is not available right now. Please try again later.']);
    }

    $sourceType = ($_POST['source_type'] ?? 'keyword') === 'competitor_url' ? 'competitor_url' : 'keyword';
    $sourceValue = trim($_POST['source_value'] ?? '');

    if ($sourceValue === '') {
        respond(['ok' => false, 'error' => 'Please enter a keyword/title or a competitor URL.']);
    }

    $context = '';
    if ($sourceType === 'competitor_url') {
        $outline = fetch_competitor_outline($sourceValue);
        if ($outline['ok']) {
            $context = "Reference page title: {$outline['title']}\nReference headings: " . implode(' | ', $outline['headings']) . "\nReference excerpt (for topic inspiration only, do not copy wording): {$outline['excerpt']}";
        } else {
            $context = "Could not fetch the reference URL; write a strong original article for the topic implied by this URL: $sourceValue";
        }
    }

    $systemPrompt = "You are an expert blog writer. Respond with ONLY a JSON object, no markdown fences, no commentary. "
        . 'Shape: {"title": "...", "sections": [{"heading": "...", "brief": "1-sentence note on what this section should cover"}, ...]}. '
        . 'Include 4 to 8 sections. Titles and headings should be engaging and SEO-friendly.';
    $userPrompt = $sourceType === 'keyword'
        ? "Create an article outline for this topic/keyword: \"$sourceValue\""
        : "Create an original article outline inspired by (but not copied from) this reference:\n$context";

    $result = ai_generate_text($pdo, $articleSettings['text_provider'], $articleSettings['text_model'], $systemPrompt, $userPrompt, 1500, (int)$user['id']);

    if (!$result['ok']) {
        respond(['ok' => false, 'error' => 'Outline generation failed: ' . $result['error']]);
    }

    $json = extract_json_from_text($result['text']);
    if (!$json || empty($json['sections'])) {
        respond(['ok' => false, 'error' => 'The AI response could not be parsed. Please try again.']);
    }

    $sections = [];
    foreach ($json['sections'] as $s) {
        $sections[] = [
            'heading' => $s['heading'] ?? '',
            'brief' => $s['brief'] ?? '',
            'content' => null,
        ];
    }
    $stmt = $pdo->prepare("INSERT INTO articles (user_id, source_type, source_value, title, sections_json, status) VALUES (?, ?, ?, ?, ?, 'draft')");
    $stmt->execute([$user['id'], $sourceType, $sourceValue, $json['title'] ?? $sourceValue, json_encode($sections)]);
    $newId = $pdo->lastInsertId();
    log_event($pdo, 'article', "Outline generated for article #$newId", $user['id']);

    $article = load_article($pdo, $newId, $user['id']);
    respond(['ok' => true, 'article' => article_public_state($article)]);
}

/* ---------------- generate_content (text only — no images) ---------------- */
if ($action === 'generate_content') {
    if (!$article) respond(['ok' => false, 'error' => 'Article not found.']);

    $sections = $article['sections'];

    $systemPrompt = "You are an expert blog writer. Respond with ONLY a JSON object, no markdown fences. "
        . 'Shape: {"sections": [{"heading": "...", "html": "<p>...</p>..."}]}. '
        . 'Write 150-300 words of well-formatted HTML (p, ul/li, strong where useful — no h1/h2 tags, those are added separately) per section, in the same order given, matching each heading exactly.';
    $sectionList = array_map(fn($s) => ['heading' => $s['heading'], 'brief' => $s['brief']], $sections);
    $userPrompt = "Article title: {$article['title']}\nWrite content for these sections:\n" . json_encode($sectionList);

    $result = ai_generate_text($pdo, $articleSettings['text_provider'], $articleSettings['text_model'], $systemPrompt, $userPrompt, 4000, (int)$user['id']);

    if (!$result['ok']) {
        respond(['ok' => false, 'error' => 'Content generation failed: ' . $result['error']]);
    }

    $json = extract_json_from_text($result['text']);
    $byHeading = [];
    foreach (($json['sections'] ?? []) as $s) {
        $byHeading[$s['heading'] ?? ''] = $s['html'] ?? '';
    }

    $fullHtml = '';
    foreach ($sections as $i => &$section) {
        $section['content'] = $byHeading[$section['heading']] ?? '<p>' . e($section['brief']) . '</p>';
        $fullHtml .= "<h2>" . e($section['heading']) . "</h2>\n" . $section['content'] . "\n";
    }
    unset($section);

    ensure_db_connection($pdo);
    $pdo->prepare("UPDATE articles SET sections_json = ?, content = ? WHERE id = ?")
        ->execute([json_encode($sections), $fullHtml, $article['id']]);
    log_event($pdo, 'article', "Full content generated for article #{$article['id']}", $user['id']);

    $article = load_article($pdo, $article['id'], $user['id']);
    respond(['ok' => true, 'article' => article_public_state($article)]);
}

/* ---------------- save (draft or publish) — text only, no images ---------------- */
if ($action === 'save') {
    if (!$article) respond(['ok' => false, 'error' => 'Article not found.']);

    $title = trim($_POST['title'] ?? $article['title']);
    $content = $_POST['content'] ?? $article['content'];
    $websiteId = (int)($_POST['website_id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $publishNow = !empty($_POST['publish_now']);

    ensure_db_connection($pdo);
    $pdo->prepare("UPDATE articles SET title = ?, content = ?, website_id = ?, category = ?, tags = ? WHERE id = ?")
        ->execute([$title, $content, $websiteId ?: null, $category, $tags, $article['id']]);

    if (!$publishNow) {
        $article = load_article($pdo, $article['id'], $user['id']);
        respond(['ok' => true, 'saved_only' => true, 'article' => article_public_state($article)]);
    }

    if (!$websiteId) {
        respond(['ok' => false, 'error' => 'Please select a website to publish to.']);
    }

    $siteStmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
    $siteStmt->execute([$websiteId, $user['id']]);
    $site = $siteStmt->fetch();

    if (!$site) {
        respond(['ok' => false, 'error' => 'Selected website not found.']);
    }

    $payload = ['title' => $title, 'content' => $content, 'category' => $category, 'tags' => $tags, 'status' => 'publish'];
    $result = website_publish_dispatch($pdo, $site, $payload);

    ensure_db_connection($pdo);
    if ($result['ok'] && !empty($result['data']['id'])) {
        $pdo->prepare("UPDATE articles SET status = 'published', wp_post_id = ?, wp_post_url = ?, published_at = NOW(), last_error = NULL WHERE id = ?")
            ->execute([$result['data']['id'], $result['data']['url'] ?? null, $article['id']]);
        log_event($pdo, 'publish', "Article #{$article['id']} published to {$site['site_url']}", $user['id']);
        $article = load_article($pdo, $article['id'], $user['id']);
        respond(['ok' => true, 'published' => true, 'article' => article_public_state($article)]);
    }

    $errMsg = is_array($result['data'] ?? null) ? json_encode($result['data']) : $result['error'];
    $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?")->execute([$errMsg, $article['id']]);
    respond(['ok' => false, 'error' => 'Publish failed: ' . $errMsg]);
}

respond(['ok' => false, 'error' => 'Unknown action.']);
