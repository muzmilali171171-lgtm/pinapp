<?php
/**
 * Auto Article + Auto Website to Daily Pin — background "tick": nudges both
 * queues forward a few steps each, detached from whoever's browser triggered
 * it (so it keeps running even if that tab is closed or navigates away
 * immediately after firing this request). This is a "poor man's cron": the
 * real fix for reliable, ongoing processing is still the
 * cron/article-scheduler.php scheduled task, but not every host makes that
 * easy to set up, so this gives a working fallback — literally any page load
 * anywhere in the app can nudge both queues.
 *
 * No login required (it does no user-specific work and returns nothing
 * sensitive) so it can be pinged from any page. Throttled via a marker file
 * so many simultaneous visitors don't all kick off a processing burst at once.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';

ignore_user_abort(true); // keep running even if the client disconnects (tab closed / navigated away)
@set_time_limit(90);
header('Content-Type: application/json');

// Throttle: skip entirely if another tick ran too recently, so a page with many
// concurrent visitors doesn't fire overlapping processing bursts.
$lockFile = __DIR__ . '/../uploads/.article_tick_lock';
$now = time();
$last = is_file($lockFile) ? (int)@file_get_contents($lockFile) : 0;
if ($now - $last < 180) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}
if (!is_dir(dirname($lockFile))) @mkdir(dirname($lockFile), 0755, true);
@file_put_contents($lockFile, (string)$now);

// Respond immediately and detach from the connection where the server supports it
// (PHP-FPM), so the actual processing below continues even after the browser has
// moved on — this is what makes the queue advance without needing the tab to stay open.
if (function_exists('fastcgi_finish_request')) {
    echo json_encode(['ok' => true, 'started' => true]);
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) { // CyberPanel / OpenLiteSpeed
    echo json_encode(['ok' => true, 'started' => true]);
    litespeed_finish_request();
}

// Fallback when the cron job isn't running: publish due pins from page loads too.
$lastRun = scheduler_last_run();
if (!$lastRun || time() - (int)$lastRun['at'] > 180) {
    try { scheduler_run($pdo, 'tick', 10, 45); } catch (Throwable $e) { /* never block the tick */ }
}
// No working cron job? Make sure the built-in background runner is going.
try { scheduler_runner_kick(); } catch (Throwable $e) { /* optional */ }

// Article writing / images can take minutes per step — far longer than a web request may live
// (a killed request left articles stuck in "Writing" / "Imaging"). So page loads no longer do that
// work themselves: they only make sure the background article runner is going (when cron isn't).
try { scheduler_runner_kick(false, 'articles'); } catch (Throwable $e) { /* optional */ }

if (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request')) {
    echo json_encode(['ok' => true, 'steps_run' => 0]);
}
