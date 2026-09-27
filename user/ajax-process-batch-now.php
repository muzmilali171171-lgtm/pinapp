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

// Only one process works on a batch at a time. If its background worker is already on it,
// there is nothing to do here — the worker keeps going on its own.
$batchLock = article_batch_lock_try((int)$batch['id']);
if (!$batchLock) {
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'This batch is already being written and published in the background — refresh in a minute to see progress.']);
    exit;
}

// Resume any article already mid-pipeline first, then the next due one.
$article = next_due_article_for_batch($pdo, (int)$batch['id']);

if (!$article) {
    article_batch_lock_release($batchLock);
    echo json_encode(['ok' => true, 'done' => true, 'message' => 'No due articles right now.']);
    exit;
}
// Each user can run only a set number of batches at once (Admin → Articles Schedule → Batch Limits).
if ($article['status'] === 'queued' && !article_batch_allowed($pdo, (int)$batch['id'])) {
    article_batch_lock_release($batchLock);
    $limit = article_batch_limit_for_user(article_batch_limits_all($pdo), (int)$batch['user_id']);
    echo json_encode(['ok' => true, 'done' => true, 'message' => "You already have $limit batch(es) running at once — this batch starts automatically as soon as one of them finishes."]);
    exit;
}

$currentArticleId = $article['id'];
$result = process_article_step($pdo, $article, $batch);
article_batch_lock_release($batchLock);

echo json_encode([
    'ok' => $result['ok'],
    'done' => false,
    'more' => $result['more'] ?? false,
    'article_id' => $article['id'],
    'title' => $article['title'],
    'error' => $result['error'] ?? null,
    'wp_post_url' => $result['wp_post_url'] ?? null,
]);
