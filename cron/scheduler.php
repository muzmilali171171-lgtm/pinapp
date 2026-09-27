<?php
/**
 * Publishes due pins. Run it EVERY MINUTE.
 *
 * CyberPanel (Websites → your site → Cron Jobs) — the command must start with the PHP binary:
 *   * * * * *  /usr/local/lsws/lsphp82/bin/php /home/YOUR-SITE/public_html/cron/scheduler.php >/dev/null 2>&1
 * (use the lsphpXX folder of your site's PHP version — Admin → Scheduler shows the exact command).
 *
 * Or by URL (wget/curl cron, or to test in the browser):
 *   https://YOUR-SITE/cron/scheduler.php?key=THE-KEY   (Admin → Scheduler shows the key)
 *
 * Safe to run every minute: a lock stops overlapping runs, each pin is claimed atomically,
 * stuck pins are recovered, temporary Pinterest errors are retried with back-off.
 */
chdir(__DIR__);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(scheduler_web_key(), (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        echo "Forbidden — add ?key=… (see Admin → Scheduler).\n";
        exit;
    }
    ignore_user_abort(true);
}
@set_time_limit(0);

$r = scheduler_run($pdo, $isCli ? 'cron' : 'web', 40, 240);
echo implode("\n", $r['messages']) . "\n";

// Move new images to external storage + hourly hosting cleanup (Admin → Storage Settings).
try {
    $sw = ext_sweep($pdo, 25, 3, 40);
    if ($sw['checked']) echo "External storage: {$sw['uploaded']} uploaded, {$sw['hosting']} kept on hosting.\n";
    $cleanFlag = __DIR__ . '/../uploads/.ext_cleanup_last';
    if (!is_file($cleanFlag) || filemtime($cleanFlag) < time() - 3600) {
        @touch($cleanFlag);
        $removed = ext_cleanup_local($pdo);
        if ($removed) echo "Removed $removed hosting copies (already on external storage).\n";
    }
} catch (Throwable $e) {
    ext_log_error($pdo, null, null, 'Background upload error: ' . $e->getMessage());
}
