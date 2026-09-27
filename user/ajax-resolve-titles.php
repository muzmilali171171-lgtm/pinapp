<?php
/**
 * AJAX backend that resolves lines into titles: a plain line is kept as-is, a URL line gets its
 * page title fetched. Used by the Auto Article wizard (title step) and the Bulk Pin Scheduler's
 * "Create with AI" panel.
 *
 * Every input line always comes back as exactly one item, in the same order — if a page can't be
 * fetched (timeout, blocked, no <title>), a readable title is built from the URL's slug instead of
 * dropping the line. URLs are fetched in parallel, so 100+ links resolve well inside time limits.
 * The pages send links in chunks, so the per-request cap below is only a safety ceiling.
 */
@set_time_limit(300);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$lines = json_decode($_POST['lines'] ?? '[]', true);
if (!is_array($lines)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}
$lines = array_slice(array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== '')), 0, 500);

$urls = array_values(array_unique(array_filter($lines, fn($l) => preg_match('#^https?://#i', $l))));
$titles = $urls ? extract_titles_from_urls($urls, 10, 15) : [];

$resolved = [];
foreach ($lines as $line) {
    if (preg_match('#^https?://#i', $line)) {
        $title = $titles[$line] ?? null;
        $resolved[] = [
            'title' => ($title !== null && $title !== '') ? $title : title_from_url_slug($line),
            'source_type' => 'competitor_url',
            'source_value' => $line,
            'fetched' => $title !== null && $title !== '',
        ];
    } else {
        $resolved[] = ['title' => $line, 'source_type' => 'keyword', 'source_value' => null, 'fetched' => true];
    }
}

echo json_encode(['ok' => true, 'items' => $resolved]);
