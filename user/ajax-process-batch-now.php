<?php
/**
 * AJAX backend for the "Process Now" button on the Auto Article batch view
 * page — runs the pipeline for exactly ONE due article in this batch per
 * call (kept to one so a single web request stays well inside typical
 * shared-hosting execution-time limits; the frontend calls this repeatedly
 * in a loop, the same pattern as the AI pin-image generator).
 *
 * This is also the practical fix for "articles stay Queued forever": if the
 * server's cron job for cron/article-scheduler.php isn't set up yet (or
 * hasn't run yet), this lets a batch run immediately from the browser
 * instead of silently waiting.
 */
// One step can take a few minutes (a long article part, a slow image). Keep going even if the
// browser or proxy gives up waiting, so the finished work is always saved.
ignore_user_abort(true);
@set_time_limit(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// A safety net for truly fatal PHP errors (e.g. memory exhaustion) that even
// process_article_step()'s own try/catch can't catch — without this, a fatal
// mid-step would silently return no JSON at all (surfacing as "Network error"
// in the browser) and leave the article claimed with no explanation. This
// marks it failed with a real reason instead, and always returns valid JSON.
register_shutdown_function(function () {
    global $pdo, $currentArticleId;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!empty($currentArticleId) && isset($pdo)) {
            try {
                $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?")
                    ->execute(['Server error: ' . $error['message'], $currentArticleId]);
            } catch (Throwable $e) { /* best-effort — nothing more we can do here */ }
        }
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $error['message'], 'done' => false]);
    }
});

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$batchId = trim($_POST['batch_id'] ?? '');

$batch = get_article_batch($pdo, $batchId, $user['id']);
if (!$batch) {
    echo json_encode(['ok' => false, 'error' => 'Batch not found.']);
    exit;
}
if ($batch['status'] !== 'active') {
    echo json_encode(['ok' => false, 'error' => 'This batch is stopped.', 'done' => true]);
    exit;
}

$today = date('Y-m-d');
// Pick up brand-new due articles, but also RESUME any article already mid-pipeline
// (drafting/imaging/ready/publishing, or a legacy stuck 'draft' row) regardless of
// its scheduled_for date, since once started it should be finished.
$stmt = $pdo->prepare("SELECT * FROM articles
    WHERE batch_id = ? AND user_id = ?
    AND (
        (status = 'queued' AND scheduled_for <= ?)
        OR status IN ('drafting', 'drafted', 'imaging', 'ready', 'publishing', 'draft')
    )
    ORDER BY scheduled_for ASC, id ASC LIMIT 1");
$stmt->execute([$batch['id'], $user['id'], $today]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'No due articles right now.']);
    exit;
}

$currentArticleId = $article['id'];
$result = process_article_step($pdo, $article, $batch);

echo json_encode([
    'ok' => $result['ok'],
    'done' => false,
    'more' => $result['more'] ?? false,
    'article_id' => $article['id'],
    'title' => $article['title'],
    'error' => $result['error'] ?? null,
    'wp_post_url' => $result['wp_post_url'] ?? null,
]);
