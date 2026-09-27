<?php
/**
 * Auto Article + Auto Website to Daily Pin queue — run every 2 minutes:
 *   *\/2 * * * * (remove the backslash)  /usr/local/lsws/lsphpXX/bin/php /home/SITE/public_html/cron/article-scheduler.php >/dev/null 2>&1
 * or by URL:  https://SITE/cron/article-scheduler.php?key=KEY
 * (Admin → Articles Schedule shows the exact lines and can add them automatically.)
 *
 * Each article/page pipeline runs in small steps (one AI/image call per step), so nothing is left
 * stuck: an unfinished step is simply picked up by the next run. A lock prevents overlapping runs.
 */
chdir(__DIR__);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(scheduler_web_key(), (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Forbidden\n"); }
    ignore_user_abort(true);
}
@set_time_limit(0);

echo implode("\n", article_scheduler_run($pdo, $isCli ? 'cron' : 'web')['messages']) . "\n";
