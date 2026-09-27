<?php
/**
 * Built-in runners — keep the app working WITHOUT cron jobs (and without web time limits):
 *   /cron/runner.php?job=pins&key=KEY       publishes due pins every minute
 *   /cron/runner.php?job=articles&key=KEY   writes / illustrates / publishes Auto Articles, step by step
 * Started automatically (page loads / Admin) only while the matching cron job isn't running. Each run
 * lasts a few minutes, then starts the next one and exits; it stops by itself when the real cron job
 * works or when it's switched off in Admin → Scheduler.
 */
chdir(__DIR__);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
if (!hash_equals(scheduler_web_key(), (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Forbidden\n"); }
$job = ($_GET['job'] ?? 'pins') === 'articles' ? 'articles' : 'pins';

// Answer right away, keep working in the background.
ignore_user_abort(true);
@set_time_limit(0);
echo "runner ($job) started\n";
if (function_exists('litespeed_finish_request')) litespeed_finish_request();
elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { @ob_end_flush(); @flush(); }

if ($job === 'articles') {
    require_once __DIR__ . '/../includes/ai_functions.php';
    require_once __DIR__ . '/../includes/auto_article_functions.php';
    require_once __DIR__ . '/../includes/website_pin_functions.php';
}

$suffix = $job === 'articles' ? '_articles' : '';
$lock = @fopen(__DIR__ . '/../uploads/.runner' . $suffix . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;   // this job's runner is already active

$hb = __DIR__ . '/../uploads/.runner_heartbeat' . $suffix;
$end = time() + ($job === 'articles' ? 540 : 235);
$stop = false;
while (time() < $end) {
    $cronAlive = $job === 'articles' ? article_cron_alive() : scheduler_cron_alive();
    if (!scheduler_runner_enabled() || $cronAlive) { $stop = true; break; }
    @file_put_contents($hb, (string)time());
    try {
        ensure_db_connection($pdo);
        if ($job === 'articles') {
            $r = article_scheduler_run($pdo, 'runner', 3);
            $idle = $r['steps'] === 0;
        } else {
            scheduler_run($pdo, 'runner', 20, 45);
            $idle = true;
        }
    } catch (Throwable $e) {
        scheduler_log("[runner/$job] " . $e->getMessage());
        $idle = true;
    }
    if ($job === 'articles' && !$idle) continue;       // more article work waiting — keep going
    $wait = 60 - (time() % 60);
    if (time() + $wait >= $end) break;
    sleep(max(15, $wait));
}
flock($lock, LOCK_UN);
fclose($lock);
@file_put_contents($hb, (string)(time() - 1000));   // let the next kick through straight away
if (!$stop) scheduler_runner_kick(true, $job);      // chain the next run
