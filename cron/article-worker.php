<?php
/**
 * Background worker for ONE Auto Article batch:
 *   /cron/article-worker.php?batch=BATCH_DB_ID&key=KEY
 *
 * Every batch gets its own worker, so batches (from the same user or different users) are written,
 * illustrated and published at the same time — a new batch starts right away instead of waiting
 * for older batches to finish. Started automatically (batch creation, cron, the built-in runner,
 * page-load ticks) by article_batch_workers_kick(); a per-batch lock means only one worker ever
 * runs a batch. It works until the batch has nothing due, then exits; if time runs out with work
 * still waiting, it starts the next worker for the same batch and exits.
 */
chdir(__DIR__);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
if (!hash_equals(scheduler_web_key(), (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Forbidden\n"); }
$batchDbId = (int)($_GET['batch'] ?? 0);
if ($batchDbId <= 0) { http_response_code(400); exit("Missing batch\n"); }

// Answer right away, keep working in the background.
ignore_user_abort(true);
@set_time_limit(0);
ini_set('display_errors', '0');
echo "article worker (batch $batchDbId) started\n";
if (function_exists('litespeed_finish_request')) litespeed_finish_request();
elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { @ob_end_flush(); @flush(); }

require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';

$lock = article_batch_lock_try($batchDbId);
if (!$lock) exit;   // this batch is already being worked on

// A truly fatal PHP error (e.g. memory exhaustion) mid-step: mark that article failed with a
// real reason instead of leaving it claimed, and free the batch for the next run.
$currentArticleId = null;
register_shutdown_function(function () use ($batchDbId) {
    global $pdo, $currentArticleId;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!empty($currentArticleId) && isset($pdo)) {
            try {
                $pdo->prepare("UPDATE articles SET status = 'failed', last_error = ? WHERE id = ?")
                    ->execute(['Server error: ' . $error['message'], $currentArticleId]);
            } catch (Throwable $e) { /* best-effort */ }
        }
        scheduler_log("[article-worker] batch #$batchDbId fatal: " . $error['message']);
    }
});

$more = false;
try {
    $r = run_batch_article_steps($pdo, $batchDbId, 540);
    $more = $r['more'];
    foreach ($r['log'] as $entry) {
        if (!$entry['more'] && !$entry['ok']) scheduler_log("[article-worker] article #{$entry['article_id']} failed: {$entry['error']}");
    }
} catch (Throwable $e) {
    scheduler_log("[article-worker] batch #$batchDbId ERROR " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}
article_batch_lock_release($lock);
if ($more) article_batch_worker_start($batchDbId);   // time ran out with work waiting — chain the next run
