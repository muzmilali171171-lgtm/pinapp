<?php
/**
 * Background worker for ONE Auto Website to Daily Pin / Classic Wizard batch:
 *   /cron/website-pin-worker.php?batch=BATCH_DB_ID&key=KEY
 *
 * Every batch gets its own worker, so batches (from the same user or different users) create and
 * schedule their pins at the same time — a new batch starts right away instead of waiting for older
 * batches to finish. Started by website_pin_batch_workers_kick() (batch creation, cron, the built-in
 * runner, page-load ticks); a per-batch lock means only one worker ever runs a batch. It works until
 * the batch has nothing due, then exits; if time runs out with work still waiting, it starts the
 * next worker for the same batch and exits.
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
echo "website pin worker (batch $batchDbId) started\n";
if (function_exists('litespeed_finish_request')) litespeed_finish_request();
elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { @ob_end_flush(); @flush(); }

require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';

$lock = website_pin_batch_lock_try($batchDbId);
if (!$lock) exit;   // this batch is already being worked on

register_shutdown_function(function () use ($batchDbId) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        scheduler_log("[website-pin-worker] batch #$batchDbId fatal: " . $error['message']);
    }
});

$more = false;
try {
    $r = run_website_pin_batch_steps($pdo, $batchDbId, 540);
    $more = $r['more'];
    foreach ($r['log'] as $entry) {
        if (!$entry['more'] && !$entry['ok']) scheduler_log("[website-pin-worker] page #{$entry['page_id']} failed: {$entry['error']}");
    }
} catch (Throwable $e) {
    scheduler_log("[website-pin-worker] batch #$batchDbId ERROR " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}
website_pin_batch_lock_release($lock);
if ($more) background_workers_start([website_pin_batch_worker_url($batchDbId)], 'AutomatedPin-WebsitePinWorker');   // time ran out with work waiting — chain the next run
