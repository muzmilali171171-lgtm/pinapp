<?php
/**
 * Shared Pinterest publisher — used by cron/scheduler.php AND by every place that creates
 * pins (Single pin, Bulk scheduler, Website → Daily Pin, Auto Article, Classic Wizard).
 *
 *  - publish_one_scheduled_pin(): atomically CLAIMS a pending pin (so two cron runs, or cron
 *    + an "overdue → publish now" request, can never publish the same pin twice), resolves
 *    its board, publishes it, and on a temporary Pinterest error (2787 "Something went wrong
 *    on our end", 2786 "Unable to reach the URL", HTTP 5xx, network errors) immediately
 *    retries ONCE by uploading the image bytes directly (base64 JPEG) instead of a URL, then
 *    backs off (2 → 5 → 15 → 30 min) instead of retrying every cron minute.
 *  - publish_overdue_pins_now(): right after pins are saved, any pin whose time has ALREADY
 *    passed is published immediately; the rest stay scheduled.
 */

const PIN_MAX_ATTEMPTS = 5;

/** Adds the few columns this module (and the new gap/start settings) need. Runs once, then a flag file skips it. */
function webtopin_ensure_schema_pinfix(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_pinfix_v1';
    if (is_file($flag)) return;

    $cols = [
        ['scheduled_pins', 'next_retry_at', 'next_retry_at DATETIME DEFAULT NULL'],
        ['scheduled_pins', 'claimed_at', 'claimed_at DATETIME DEFAULT NULL'],
        ['website_pin_batches', 'page_gap_unit', "page_gap_unit VARCHAR(10) DEFAULT NULL"],
        ['website_pin_batches', 'page_gap_minutes', 'page_gap_minutes INT DEFAULT NULL'],
        ['website_pin_batches', 'start_date', 'start_date DATE DEFAULT NULL'],
        ['website_pin_batches', 'start_time', 'start_time VARCHAR(5) DEFAULT NULL'],
    ];
    $allOk = true;
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    foreach ($cols as [$table, $col, $def]) {
        try {
            $check->execute([$table, $col]);
            if ((int)$check->fetchColumn() > 0) continue;
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $def");
        } catch (Throwable $e) {
            $allOk = false;
        }
    }
    if ($allOk) {
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    }
}

/** True for Pinterest errors that are worth retrying (their side / fetch / network), false for real content errors. */
function pin_error_is_transient(array $result): bool
{
    $http = (int)($result['code'] ?? 0);
    $pcode = (int)($result['data']['code'] ?? 0);
    if ($http === 0) return true;                    // curl / network error
    if ($http >= 500 || $http === 429) return true;  // Pinterest server error / rate limit
    if (in_array($pcode, [2786, 2787], true)) return true;
    return false;
}

/** Re-encodes the pin image as a JPEG (max 2000px tall-side, quality 88) and returns its base64, or null. */
function pin_image_as_base64_jpeg(string $imagePath): ?string
{
    // Re-downloads from external storage if the hosting copy was removed.
    $full = function_exists('media_local_file') ? media_local_file($imagePath) : realpath(__DIR__ . '/../' . ltrim($imagePath, '/'));
    if (!$full || !is_file($full)) return null;
    $raw = @file_get_contents($full);
    if ($raw === false || $raw === '') return null;

    if (!function_exists('imagecreatefromstring')) {
        return base64_encode($raw);
    }
    $img = @imagecreatefromstring($raw);
    if (!$img) return base64_encode($raw);

    $w = imagesx($img);
    $h = imagesy($img);
    $max = 2000;
    if ($w > $max || $h > $max) {
        $scale = $max / max($w, $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    } else {
        // Flatten transparency (PNG/WebP) onto white — JPEG has no alpha.
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    ob_start();
    imagejpeg($img, null, 88);
    $jpg = ob_get_clean();
    imagedestroy($img);
    return $jpg ? base64_encode($jpg) : null;
}

/** Same as pinterest_create_pin() but sends the image bytes directly (no URL for Pinterest to fetch). */
function pinterest_create_pin_base64(PDO $pdo, array $account, array $pinRow): array
{
    $b64 = pin_image_as_base64_jpeg((string)$pinRow['image_path']);
    if ($b64 === null) return ['ok' => false, 'code' => 0, 'data' => null, 'error' => 'Image file not found on server.'];

    $account = pinterest_ensure_fresh_token($pdo, $account);
    // No keyword list appended, no URL in the text (same as pinterest_create_pin()).
    $description = pin_description_clean((string)($pinRow['description'] ?? ''));
    $payload = [
        'board_id' => $pinRow['board_id'],
        'title' => $pinRow['title'],
        'description' => $description,
        'media_source' => ['source_type' => 'image_base64', 'content_type' => 'image/jpeg', 'data' => $b64],
    ];
    $link = $pinRow['dest_link'] ?: ($pinRow['product_link'] ?? '');
    if (!empty($link)) $payload['link'] = $link;
    if (!empty($pinRow['alt_text'])) $payload['alt_text'] = $pinRow['alt_text'];

    $result = pinterest_http_request('POST', PINTEREST_API_BASE . '/pins', [
        'Authorization: Bearer ' . $account['access_token'],
        'Content-Type: application/json',
    ], json_encode($payload));
    $result['image_url'] = 'base64-upload';
    return $result;
}

/**
 * Claims and publishes ONE scheduled pin. Returns ['status' => 'published'|'failed'|'retry'|'skipped', 'message' => ..].
 * 'skipped' = someone else already claimed it, or its new board isn't created yet (left pending, no attempt used).
 */
function publish_one_scheduled_pin(PDO $pdo, int $pinId): array
{
    webtopin_ensure_schema_pinfix($pdo);
    ensure_db_connection($pdo);
    $nowStr = date('Y-m-d H:i:s');

    // Atomic claim — only one process can flip pending → processing.
    $claim = $pdo->prepare("UPDATE scheduled_pins SET status = 'processing', attempts = attempts + 1, claimed_at = ? WHERE id = ? AND status = 'pending'");
    $claim->execute([$nowStr, $pinId]);
    if ($claim->rowCount() === 0) return ['status' => 'skipped', 'message' => 'Already claimed.'];

    $stmt = $pdo->prepare("SELECT * FROM scheduled_pins WHERE id = ?");
    $stmt->execute([$pinId]);
    $pin = $stmt->fetch();
    if (!$pin) return ['status' => 'skipped', 'message' => 'Pin not found.'];

    $release = function () use ($pdo, $pinId) {
        $pdo->prepare("UPDATE scheduled_pins SET status = 'pending', attempts = GREATEST(attempts - 1, 0), claimed_at = NULL WHERE id = ?")->execute([$pinId]);
    };
    $fail = function (string $msg) use ($pdo, $pin) {
        $pdo->prepare("UPDATE scheduled_pins SET status = 'failed', last_error = ?, claimed_at = NULL WHERE id = ?")->execute([$msg, $pin['id']]);
        log_event($pdo, 'publish', "Pin #{$pin['id']} failed: $msg", (int)$pin['user_id']);
    };

    // Board: a locally-drafted board is created on Pinterest first.
    if (!empty($pin['board_row_id'])) {
        $b = $pdo->prepare("SELECT * FROM pinterest_boards WHERE id = ?");
        $b->execute([$pin['board_row_id']]);
        $board = $b->fetch();
        if ($board && $board['status'] === 'pending_creation') {
            // This pin is due now, so create its board right away (the batch query skips claimed pins).
            pinterest_create_pending_board($pdo, $board);
            ensure_db_connection($pdo);
            $b->execute([$pin['board_row_id']]);
            $board = $b->fetch();
        }
        if (!$board || $board['status'] === 'create_failed') {
            $fail('Board could not be created on Pinterest.');
            return ['status' => 'failed', 'message' => 'Board could not be created.'];
        }
        if ($board['status'] !== 'ready' || empty($board['board_id'])) {
            $release();
            return ['status' => 'skipped', 'message' => "Board '{$board['board_name']}' not created yet."];
        }
        $pin['board_id'] = $board['board_id'];
        $pin['board_name'] = $board['board_name'];
    }
    if (empty($pin['board_id'])) {
        $fail('No board resolved for this pin.');
        return ['status' => 'failed', 'message' => 'No board.'];
    }

    $acc = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ?");
    $acc->execute([$pin['pinterest_account_id']]);
    $account = $acc->fetch();
    if (!$account || $account['status'] !== 'connected') {
        $fail('Pinterest account is not connected.');
        return ['status' => 'failed', 'message' => 'Account not connected.'];
    }

    // 1st try: image URL (Pinterest fetches it). On a temporary error, retry right away with the image bytes.
    $result = ((int)$pin['attempts'] <= 1) ? pinterest_create_pin($pdo, $account, $pin) : pinterest_create_pin_base64($pdo, $account, $pin);
    if (!($result['ok'] && !empty($result['data']['id'])) && pin_error_is_transient($result)) {
        sleep(2);
        $second = pinterest_create_pin_base64($pdo, $account, $pin);
        if ($second['ok'] && !empty($second['data']['id'])) $result = $second;
        elseif (!empty($second['data']) || !empty($second['error'])) $result = $second + ['first_error' => $result['data'] ?? $result['error']];
    }
    ensure_db_connection($pdo);

    if ($result['ok'] && !empty($result['data']['id'])) {
        $pdo->prepare("UPDATE scheduled_pins SET status = 'published', pinterest_pin_id = ?, published_at = ?, last_error = NULL, next_retry_at = NULL, claimed_at = NULL WHERE id = ?")
            ->execute([$result['data']['id'], date('Y-m-d H:i:s'), $pin['id']]);
        log_event($pdo, 'publish', "Pin #{$pin['id']} published successfully (Pinterest pin id {$result['data']['id']})", (int)$pin['user_id']);
        return ['status' => 'published', 'message' => 'Published pin #' . $pin['id']];
    }

    $errorMsg = json_encode(['pinterest' => $result['data'] ?? $result['error'], 'image_url' => $result['image_url'] ?? null]);
    $attempts = (int)$pin['attempts'];
    $transient = pin_error_is_transient($result);
    if ($transient && $attempts < PIN_MAX_ATTEMPTS) {
        $backoff = [1 => 2, 2 => 5, 3 => 15, 4 => 30][$attempts] ?? 30; // minutes
        $pdo->prepare("UPDATE scheduled_pins SET status = 'pending', last_error = ?, next_retry_at = ?, claimed_at = NULL WHERE id = ?")
            ->execute([$errorMsg, date('Y-m-d H:i:s', time() + $backoff * 60), $pin['id']]);
        log_event($pdo, 'publish', "Pin #{$pin['id']} temporary error (attempt $attempts), retry in {$backoff} min: $errorMsg", (int)$pin['user_id']);
        return ['status' => 'retry', 'message' => "Pin #{$pin['id']} temporary error, retry in {$backoff} min: $errorMsg"];
    }
    $pdo->prepare("UPDATE scheduled_pins SET status = 'failed', last_error = ?, claimed_at = NULL WHERE id = ?")->execute([$errorMsg, $pin['id']]);
    log_event($pdo, 'publish', "Pin #{$pin['id']} failed (attempt $attempts): $errorMsg", (int)$pin['user_id']);
    return ['status' => 'failed', 'message' => "Failed pin #{$pin['id']}: $errorMsg"];
}

/**
 * Publishes, right now, every still-pending pin whose publish time has ALREADY passed.
 * $scope: ['ids' => [..]] and/or ['batch_id' => '..'], plus 'user_id'. Pins with a future time are untouched.
 * Returns ['published' => n, 'failed' => n, 'left' => n].
 */
function publish_overdue_pins_now(PDO $pdo, array $scope, int $limit = 20): array
{
    webtopin_ensure_schema_pinfix($pdo);
    $out = ['published' => 0, 'failed' => 0, 'left' => 0];
    $where = ["status = 'pending'", 'publish_at <= ?'];
    $params = [date('Y-m-d H:i:s')];
    if (!empty($scope['user_id'])) { $where[] = 'user_id = ?'; $params[] = (int)$scope['user_id']; }
    if (!empty($scope['batch_id'])) { $where[] = 'batch_id = ?'; $params[] = (string)$scope['batch_id']; }
    if (!empty($scope['ids'])) {
        $ids = array_values(array_filter(array_map('intval', (array)$scope['ids'])));
        if (!$ids) return $out;
        $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = array_merge($params, $ids);
    }
    if (empty($scope['batch_id']) && empty($scope['ids'])) return $out; // never publish "everything" by accident

    $stmt = $pdo->prepare('SELECT id FROM scheduled_pins WHERE ' . implode(' AND ', $where) . ' ORDER BY publish_at ASC, id ASC');
    $stmt->execute($params);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if (!$ids) return $out;

    @set_time_limit(max(120, count($ids) * 15));
    foreach ($ids as $n => $id) {
        if ($n >= $limit) { $out['left']++; continue; } // cron picks these up within a minute
        $r = publish_one_scheduled_pin($pdo, $id);
        if ($r['status'] === 'published') $out['published']++;
        elseif ($r['status'] === 'failed') $out['failed']++;
        else $out['left']++;
        if ($n + 1 < min($limit, count($ids))) usleep(1200000);
    }
    return $out;
}

/* ===================== Scheduler run (shared by cron/scheduler.php and the page-load fallback) ===================== */

function scheduler_heartbeat_file(): string { return __DIR__ . '/../uploads/.scheduler_heartbeat'; }

/** Last scheduler run: ['at' => unix time, 'source' => 'cron'|'web'|'tick', ...] or null. */
function scheduler_last_run(): ?array
{
    $f = scheduler_heartbeat_file();
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

/** Appends a line to uploads/logs/scheduler.log (rotated at 1 MB). */
function scheduler_log(string $line): void
{
    $dir = __DIR__ . '/../uploads/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    $f = $dir . '/scheduler.log';
    if (is_file($f) && filesize($f) > 1048576) @rename($f, $f . '.1');
    @file_put_contents($f, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/**
 * One scheduler pass: recover stuck pins, create due boards, publish due pins.
 * Uses a lock so cron and the page-load fallback never run at the same time.
 * Returns ['ran' => bool, 'published' => n, 'failed' => n, 'retry' => n, 'due' => n, 'messages' => [...]].
 */
function scheduler_run(PDO $pdo, string $source = 'cron', int $maxPins = 40, int $timeBudget = 240): array
{
    $out = ['ran' => false, 'published' => 0, 'failed' => 0, 'retry' => 0, 'due' => 0, 'messages' => []];
    $lockPath = __DIR__ . '/../uploads/.scheduler_cron.lock';
    if (!is_dir(dirname($lockPath))) @mkdir(dirname($lockPath), 0755, true);
    $lock = @fopen($lockPath, 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        $out['messages'][] = 'Previous run still working — skipping.';
        return $out;
    }
    $out['ran'] = true;
    try {
        webtopin_ensure_schema_pinfix($pdo);
        $hasRetryCol = true;
        try { $pdo->query("SELECT next_retry_at, claimed_at FROM scheduled_pins LIMIT 0"); } catch (Throwable $e) { $hasRetryCol = false; }

        $nowLocal = date('Y-m-d H:i:s');
        if ($hasRetryCol) {
            $pdo->prepare("UPDATE scheduled_pins SET status = 'pending', claimed_at = NULL
                WHERE status = 'processing' AND (claimed_at IS NULL OR claimed_at < ?)")
                ->execute([date('Y-m-d H:i:s', time() - 600)]);
        } else {
            $out['messages'][] = 'WARNING: columns next_retry_at / claimed_at are missing — open /migrate.php once.';
            $pdo->exec("UPDATE scheduled_pins SET status = 'pending' WHERE status = 'processing'");
        }

        process_pending_board_creations($pdo, 5);

        $sql = "SELECT id FROM scheduled_pins WHERE status = 'pending' AND publish_at <= ? AND attempts < ?"
            . ($hasRetryCol ? " AND (next_retry_at IS NULL OR next_retry_at <= ?)" : '')
            . " ORDER BY publish_at ASC, id ASC LIMIT " . (int)$maxPins;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($hasRetryCol ? [$nowLocal, PIN_MAX_ATTEMPTS, $nowLocal] : [$nowLocal, PIN_MAX_ATTEMPTS]);
        $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $out['due'] = count($ids);
        if (!$ids) $out['messages'][] = 'No due pins.';

        $started = time();
        foreach ($ids as $n => $id) {
            if (time() - $started > $timeBudget) { $out['messages'][] = 'Time budget used — the rest go next run.'; break; }
            $r = publish_one_scheduled_pin($pdo, $id);
            if ($r['status'] === 'published') $out['published']++;
            elseif ($r['status'] === 'failed') $out['failed']++;
            elseif ($r['status'] === 'retry') $out['retry']++;
            $out['messages'][] = $r['status'] === 'published' ? "Published pin #$id" : $r['message'];
            if ($n + 1 < count($ids)) usleep(1500000);
        }
    } catch (Throwable $e) {
        $out['messages'][] = 'ERROR: ' . $e->getMessage();
        scheduler_log("[$source] ERROR " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
    $prev = scheduler_last_run() ?: [];
    $sources = is_array($prev['sources'] ?? null) ? $prev['sources'] : [];
    $sources[$source] = time();
    @file_put_contents(scheduler_heartbeat_file(), json_encode([
        'at' => time(), 'source' => $source, 'due' => $out['due'], 'published' => $out['published'],
        'failed' => $out['failed'], 'retry' => $out['retry'], 'php' => PHP_VERSION, 'sources' => $sources,
    ]));
    if ($out['due'] || $out['failed']) {
        scheduler_log("[$source] due {$out['due']}, published {$out['published']}, failed {$out['failed']}, retry {$out['retry']}");
    }
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    return $out;
}

/** Secret key for triggering the scheduler by URL (cron via wget/curl, or "Run now" in Admin). */
function scheduler_web_key(): string
{
    return substr(hash_hmac('sha256', 'scheduler-cron', defined('APP_SECRET') ? APP_SECRET : 'webtopin'), 0, 24);
}

/**
 * Fires many background worker URLs at once (curl_multi, $parallel at a time). Each request is
 * dropped after ~1.5s on purpose — the worker keeps running on the server after we hang up — so
 * starting 1,000 workers takes seconds instead of 1,000 × 1.5s one after another.
 * Returns how many requests were sent.
 */
function background_workers_start(array $urls, string $userAgent = 'AutomatedPin-Worker', int $parallel = 100): int
{
    if (!$urls || !function_exists('curl_multi_init')) return 0;
    $sent = 0;
    foreach (array_chunk(array_values($urls), max(1, $parallel)) as $group) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($group as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 1500, CURLOPT_CONNECTTIMEOUT_MS => 1200,
                CURLOPT_NOSIGNAL => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => $userAgent,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 0.2);
        } while ($active && $status === CURLM_OK);
        foreach ($handles as $ch) { curl_multi_remove_handle($mh, $ch); curl_close($ch); $sent++; }
        curl_multi_close($mh);
    }
    return $sent;
}


/* ===================== Automatic running: real cron (auto-installed) + built-in runner fallback ===================== */

/** True when a real cron job (CLI or URL cron) ran in the last 5 minutes. */
function scheduler_cron_alive(): bool
{
    $h = scheduler_last_run();
    $src = is_array($h['sources'] ?? null) ? $h['sources'] : [];
    $t = max((int)($src['cron'] ?? 0), (int)($src['web'] ?? 0));
    return $t > time() - 300;
}

function scheduler_runner_enabled(): bool { return !is_file(__DIR__ . '/../uploads/.runner_disabled'); }
/** Last heartbeat of a built-in runner job ('pins' | 'articles'). */
function scheduler_runner_heartbeat(string $job = 'pins'): int
{
    $f = __DIR__ . '/../uploads/.runner_heartbeat' . ($job === 'articles' ? '_articles' : '');
    return is_file($f) ? (int)@file_get_contents($f) : 0;
}

/**
 * Built-in runners: when a cron job isn't running, a background request keeps the work going —
 *   pins:     publishes due pins every minute
 *   articles: writes / illustrates / publishes Auto Articles one step at a time (no web time limits)
 * Each run lasts a few minutes, then starts the next one; it stands by as soon as the real cron job works.
 * Called from page loads (ajax-tick), Admin pages and by the runner itself.
 */
function scheduler_runner_kick(bool $force = false, ?string $only = null): bool
{
    if (!scheduler_runner_enabled()) return false;
    $jobs = [];
    if (($only === null || $only === 'pins') && !scheduler_cron_alive()) $jobs[] = 'pins';
    if (($only === null || $only === 'articles') && function_exists('article_cron_alive') && !article_cron_alive()) $jobs[] = 'articles';
    $kicked = false;
    foreach ($jobs as $job) {
        $stale = $job === 'articles' ? 420 : 150;   // an article step can take a few minutes
        if (!$force && scheduler_runner_heartbeat($job) > time() - $stale) continue;   // already running
        $url = rtrim(APP_URL, '/') . '/cron/runner.php?job=' . $job . '&key=' . scheduler_web_key() . '&t=' . time();
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 1500, CURLOPT_CONNECTTIMEOUT_MS => 1200,
            CURLOPT_NOSIGNAL => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'AutomatedPin-Runner',
        ]);
        curl_exec($ch);   // times out on purpose — the runner keeps working after we hang up
        curl_close($ch);
        $kicked = true;
    }
    return $kicked;
}

/* ---------- real cron: detect, install into this site user's crontab, test ---------- */

function cron_exec_allowed(): bool
{
    if (!function_exists('exec')) return false;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('exec', $disabled, true);
}

/** Absolute path of a PHP command-line binary for cron (CyberPanel: /usr/local/lsws/lsphpXX/bin/php). */
function cron_php_cli(): ?string
{
    $cands = [];
    if (preg_match('#/lsphp(\d+)/bin/#', PHP_BINARY, $m)) $cands[] = "/usr/local/lsws/lsphp{$m[1]}/bin/php";
    if (PHP_SAPI === 'cli' && PHP_BINARY) $cands[] = PHP_BINARY;
    $found = glob('/usr/local/lsws/lsphp*/bin/php') ?: [];
    rsort($found, SORT_NATURAL);
    $cands = array_merge($cands, $found, ['/usr/bin/php', '/usr/local/bin/php']);
    foreach ($cands as $c) if (@is_file($c) && @is_executable($c)) return $c;
    return null;
}

/** Cron jobs this app uses: script => schedule. */
const CRON_JOBS = [
    'scheduler.php' => '* * * * *',          // publish due pins (every minute)
    'article-scheduler.php' => '*/2 * * * *', // write / publish articles + website-to-pin pages (every 2 minutes)
];

function cron_line_for(string $script): string
{
    $path = realpath(__DIR__ . '/../cron/' . $script) ?: (__DIR__ . '/../cron/' . $script);
    $sched = CRON_JOBS[$script] ?? '* * * * *';
    $php = cron_php_cli();
    if ($php) return $sched . ' ' . $php . ' ' . $path . ' >/dev/null 2>&1';
    return $sched . ' curl -s "' . rtrim(APP_URL, '/') . '/cron/' . $script . '?key=' . scheduler_web_key() . '" >/dev/null 2>&1';
}
function cron_scheduler_line(): string { return cron_line_for('scheduler.php'); }

function cron_line_matches(string $line, string $script): bool
{
    return (bool)preg_match('#cron/' . preg_quote($script, '#') . '(\s|\?|"|$)#', $line);
}

/** What the server says about cron for the user this site runs as. $script: which job to check. */
function cron_status(string $script = 'scheduler.php'): array
{
    $st = ['exec' => cron_exec_allowed(), 'user' => null, 'crontab' => null, 'installed' => false, 'broken' => [], 'crond' => null, 'php' => cron_php_cli()];
    if (!$st['exec']) return $st;
    $o = []; @exec('whoami 2>/dev/null', $o); $st['user'] = trim($o[0] ?? '') ?: null;
    $o = []; $rc = 1; @exec('crontab -l 2>&1', $o, $rc);
    $st['crontab'] = $rc === 0 ? implode("\n", $o) : '';
    foreach ($rc === 0 ? $o : [] as $line) {
        if (preg_match('/^\s*#/', $line) || !cron_line_matches($line, $script)) continue;
        // a working entry runs php (or curl/wget) — a bare file path does nothing
        if (preg_match('#(php|curl|wget)\S*\s#', $line)) $st['installed'] = true; else $st['broken'][] = $line;
    }
    $o = []; $rc = 1; @exec('pgrep -x crond 2>/dev/null || pgrep -x cron 2>/dev/null', $o, $rc);
    $st['crond'] = $rc === 0;
    return $st;
}

/** Writes this app's cron lines (pins every minute + articles every 2 minutes) into the site user's crontab. */
function cron_install(): array
{
    if (!cron_exec_allowed()) return ['ok' => false, 'error' => 'PHP exec() is disabled on this server, so the cron jobs can\'t be added automatically. Add them in CyberPanel → Cron Jobs.'];
    $o = []; $rc = 1; @exec('crontab -l 2>/dev/null', $o, $rc);
    $keep = array_values(array_filter($rc === 0 ? $o : [], function ($l) {
        if (trim($l) === '') return false;
        foreach (array_keys(CRON_JOBS) as $sc) if (cron_line_matches($l, $sc)) return false;
        return true;
    }));
    foreach (array_keys(CRON_JOBS) as $sc) $keep[] = cron_line_for($sc);
    $tmp = tempnam(sys_get_temp_dir(), 'wtpcron');
    file_put_contents($tmp, implode("\n", $keep) . "\n");
    $o = []; $rc = 1; @exec('crontab ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    @unlink($tmp);
    if ($rc !== 0) return ['ok' => false, 'error' => 'crontab said: ' . trim(implode(' ', $o))];
    $check = cron_status('scheduler.php');
    return $check['installed']
        ? ['ok' => true, 'line' => implode(' | ', array_map('cron_line_for', array_keys(CRON_JOBS)))]
        : ['ok' => false, 'error' => 'The lines were written but crontab doesn\'t list them — add them in CyberPanel → Cron Jobs.'];
}

/** Runs a cron script exactly like cron would (command line, as this site user) and returns its output. */
function cron_test_cli(string $script = 'scheduler.php'): array
{
    if (!cron_exec_allowed()) return ['ok' => false, 'output' => 'exec() is disabled.'];
    $php = cron_php_cli();
    if (!$php) return ['ok' => false, 'output' => 'No PHP command-line binary found (looked in /usr/local/lsws/lsphp*/bin/php and /usr/bin/php).'];
    $o = []; $rc = 1;
    $script = basename($script);
    @exec(escapeshellarg($php) . ' ' . escapeshellarg(realpath(__DIR__ . '/../cron/' . $script)) . ' 2>&1', $o, $rc);
    return ['ok' => $rc === 0, 'output' => "$ $php cron/$script  (exit $rc)\n" . implode("\n", array_slice($o, -30))];
}

/* ===================== Article queue runs (cron/article-scheduler.php, Admin → Articles Schedule) ===================== */

function article_heartbeat_file(): string { return __DIR__ . '/../uploads/.article_heartbeat'; }
function article_last_run(): ?array
{
    $f = article_heartbeat_file();
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : null;
}
function article_cron_alive(): bool
{
    $h = article_last_run();
    $src = is_array($h['sources'] ?? null) ? $h['sources'] : [];
    return max((int)($src['cron'] ?? 0), (int)($src['web'] ?? 0)) > time() - 600;
}

/** One pass of the article + website-to-pin queues, with a lock. Needs auto_article_functions + website_pin_functions loaded. */
function article_scheduler_run(PDO $pdo, string $source = 'cron', int $steps = 8): array
{
    $out = ['ran' => false, 'messages' => [], 'steps' => 0, 'published' => 0, 'failed' => 0];
    $lockPath = __DIR__ . '/../uploads/.article_cron.lock';
    if (!is_dir(dirname($lockPath))) @mkdir(dirname($lockPath), 0755, true);
    $lock = @fopen($lockPath, 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        $out['messages'][] = 'Previous article run still working — skipping.';
        return $out;
    }
    $out['ran'] = true;
    try {
        // Every batch with due work gets its own background worker, so batches run side by side.
        try {
            $kicked = article_batch_workers_kick($pdo);
            if ($kicked) $out['messages'][] = "Started $kicked batch worker(s).";
        } catch (Throwable $e) { scheduler_log("[articles/$source] worker kick: " . $e->getMessage()); }
        // Batches without a worker are stepped here, round-robin (one step per batch in turn).
        $result = run_due_article_steps($pdo, $steps);
        if ($result['steps_run'] === 0) $out['messages'][] = 'No due articles.';
        foreach ($result['log'] as $entry) {
            $out['messages'][] = "Step for article #{$entry['article_id']}: {$entry['title']}";
            if (!$entry['more']) {
                if ($entry['ok']) { $out['published']++; $out['messages'][] = '  Published.'; }
                else { $out['failed']++; $out['messages'][] = "  Failed: {$entry['error']}"; scheduler_log("[articles] article #{$entry['article_id']} failed: {$entry['error']}"); }
            } elseif (!$entry['ok']) {
                $out['messages'][] = "  (in progress) {$entry['error']}";
            }
        }
        $out['steps'] += $result['steps_run'];
        ensure_db_connection($pdo);
        // Auto Website to Daily Pin / Classic Wizard: same thing — one worker per batch, side by side.
        try {
            $kicked = website_pin_batch_workers_kick($pdo);
            if ($kicked) $out['messages'][] = "Started $kicked website pin batch worker(s).";
        } catch (Throwable $e) { scheduler_log("[website-pins/$source] worker kick: " . $e->getMessage()); }
        $w = run_due_website_pin_steps($pdo, $steps);
        if ($w['steps_run'] === 0) $out['messages'][] = 'No due website pin pages.';
        foreach ($w['log'] as $entry) {
            $out['messages'][] = "Step for page #{$entry['page_id']}: {$entry['url']}";
            if (!$entry['more']) $out['messages'][] = $entry['ok'] ? '  Scheduled.' : "  Failed: {$entry['error']}";
            elseif (!$entry['ok']) $out['messages'][] = "  (in progress) {$entry['error']}";
        }
        $out['steps'] += $w['steps_run'];
    } catch (Throwable $e) {
        $out['messages'][] = 'ERROR: ' . $e->getMessage();
        scheduler_log("[articles/$source] ERROR " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
    $prev = article_last_run() ?: [];
    $sources = is_array($prev['sources'] ?? null) ? $prev['sources'] : [];
    $sources[$source] = time();
    @file_put_contents(article_heartbeat_file(), json_encode(['at' => time(), 'source' => $source, 'steps' => $out['steps'],
        'published' => $out['published'], 'failed' => $out['failed'], 'sources' => $sources]));
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    return $out;
}
