<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'scheduler';
$pageTitle = 'Scheduler';

// Retry a failed pin: reset it to pending so the cron picks it up again.
if (isset($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    webtopin_ensure_schema_pinfix($pdo);
    $pdo->prepare("UPDATE scheduled_pins SET status = 'pending', last_error = NULL, attempts = 0, next_retry_at = NULL WHERE id = ? AND status = 'failed'")->execute([$id]);
    redirect('scheduler');
}

// "Run now": one scheduler pass right here (same code as the cron job).
$runOutput = null;
if (isset($_POST['run_now'])) {
    @set_time_limit(300);
    $run = scheduler_run($pdo, 'admin', 40, 240);
    $runOutput = implode("\n", $run['messages']);
}

$cronMsg = null; $cronErr = null; $cliTest = null;
if (isset($_POST['install_cron'])) {
    $r = cron_install();
    if ($r['ok']) $cronMsg = 'Cron job added: ' . $r['line'] . ' — it starts within a minute.';
    else $cronErr = $r['error'];
}
if (isset($_POST['test_cli'])) $cliTest = cron_test_cli();
if (isset($_POST['runner_toggle'])) {
    $flag = __DIR__ . '/../uploads/.runner_disabled';
    if ($_POST['runner_toggle'] === 'off') @file_put_contents($flag, date('c')); else @unlink($flag);
}
// keep things moving even while no cron job works
scheduler_runner_kick();

// ---- health check ----
$last = scheduler_last_run();
$phpNow = date('Y-m-d H:i:s');
$dbNow = (string)$pdo->query("SELECT NOW()")->fetchColumn();
$dbTz = (string)$pdo->query("SELECT @@session.time_zone")->fetchColumn();
$tzDiffMin = (int)round((strtotime($dbNow) - strtotime($phpNow)) / 60);
$overdue = (int)$pdo->query("SELECT COUNT(*) FROM scheduled_pins WHERE status = 'pending' AND publish_at <= '" . date('Y-m-d H:i:s', time() - 180) . "'")->fetchColumn();
$stuck = (int)$pdo->query("SELECT COUNT(*) FROM scheduled_pins WHERE status = 'processing'")->fetchColumn();
$cliPhp = PHP_BINARY;
if (preg_match('#/lsphp(\d+)/bin/#', $cliPhp, $mm)) $cliPhp = "/usr/local/lsws/lsphp{$mm[1]}/bin/php";
elseif (preg_match('#php-fpm|php-cgi|lsphp$#', $cliPhp)) $cliPhp = 'php';
$scriptPath = realpath(__DIR__ . '/../cron/scheduler.php');
$cronCmd = $cliPhp . ' ' . $scriptPath . ' >/dev/null 2>&1';
$webUrl = rtrim(APP_URL, '/') . '/cron/scheduler.php?key=' . scheduler_web_key();
$lastAgo = $last ? time() - (int)$last['at'] : null;
$cron = cron_status();
$cronAlive = scheduler_cron_alive();
$srcTimes = is_array($last['sources'] ?? null) ? $last['sources'] : [];
$runnerOn = scheduler_runner_enabled();
$runnerBeat = scheduler_runner_heartbeat();
$logTail = '';
$logFile = __DIR__ . '/../uploads/logs/scheduler.log';
if (is_file($logFile)) { $lines = file($logFile, FILE_IGNORE_NEW_LINES); $logTail = implode("\n", array_slice($lines ?: [], -15)); }

$upcoming = $pdo->query("SELECT sp.*, u.name as user_name FROM scheduled_pins sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.status IN ('pending','processing') ORDER BY sp.publish_at ASC LIMIT 50")->fetchAll();

$failed = $pdo->query("SELECT sp.*, u.name as user_name FROM scheduled_pins sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.status = 'failed' ORDER BY sp.publish_at DESC LIMIT 50")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Scheduler</h1></div>

<div class="card">
    <h2>Scheduler health</h2>
    <?php if ($lastAgo === null): ?>
        <div class="alert alert-error">The scheduler has <strong>never run</strong>. Pins stay "Pending" until it does — add the cron job below.</div>
    <?php elseif ($lastAgo > 300): ?>
        <div class="alert alert-error">Last run was <strong><?= e(floor($lastAgo / 60)) ?> min ago</strong> (<?= e($last['source'] ?? '') ?>). The cron job isn't running every minute — check the command below.</div>
    <?php else: ?>
        <div class="alert alert-success">Running — last run <?= (int)$lastAgo ?> s ago (<?= e($last['source'] ?? '') ?>, PHP <?= e($last['php'] ?? '') ?>): <?= (int)($last['due'] ?? 0) ?> due, <?= (int)($last['published'] ?? 0) ?> published, <?= (int)($last['failed'] ?? 0) ?> failed.</div>
    <?php endif; ?>
    <?php if ($overdue): ?><div class="alert alert-error"><?= $overdue ?> pending pin(s) are more than 3 minutes past their time.</div><?php endif; ?>
    <?php if ($stuck): ?><div class="alert alert-info"><?= $stuck ?> pin(s) are "processing" — they're put back to pending automatically after 10 minutes.</div><?php endif; ?>

    <table>
        <tr><td style="width:220px;">App time (PHP)</td><td><strong><?= e($phpNow) ?></strong> — <?= e(date_default_timezone_get()) ?> (UTC<?= e(date('P')) ?>)</td></tr>
        <tr><td>Database time (MySQL)</td><td><strong><?= e($dbNow) ?></strong> — session time zone <?= e($dbTz) ?>
            <?php if (abs($tzDiffMin) > 2): ?><span class="badge badge-error">differs by <?= e($tzDiffMin) ?> min</span><?php else: ?><span class="badge badge-connected">matches</span><?php endif; ?></td></tr>
        <tr><td>Web PHP binary</td><td><code><?= e(PHP_BINARY) ?></code> (PHP <?= e(PHP_VERSION) ?>)</td></tr>
    </table>

    <h3 style="margin-top:18px;">Automatic publishing</h3>
    <?php if ($cronMsg): ?><div class="alert alert-success"><?= e($cronMsg) ?></div><?php endif; ?>
    <?php if ($cronErr): ?><div class="alert alert-error"><?= e($cronErr) ?></div><?php endif; ?>
    <table>
        <tr><td style="width:220px;">Cron job</td><td>
            <?php if ($cronAlive): ?><span class="badge badge-connected">working</span> last cron run <?= (int)(time() - max((int)($srcTimes['cron'] ?? 0), (int)($srcTimes['web'] ?? 0))) ?> s ago
            <?php elseif ($cron['installed']): ?><span class="badge badge-error">installed but not running</span> <?= $cron['crond'] === false ? '— the cron service (crond) is stopped on the server, see the SSH fix below.' : '— check the command / wait one minute.' ?>
            <?php elseif ($cron['broken']): ?><span class="badge badge-error">wrong command</span> <code><?= e($cron['broken'][0]) ?></code> — it has no <code>php</code> in front, so nothing runs. Click “Add cron job automatically”.
            <?php else: ?><span class="badge badge-error">not set up</span><?php endif; ?>
        </td></tr>
        <tr><td>Built-in runner (no cron needed)</td><td>
            <?php if (!$runnerOn): ?><span class="badge badge-error">off</span>
            <?php elseif ($cronAlive): ?><span class="badge">standing by</span> — not needed while the cron job works
            <?php elseif ($runnerBeat > time() - 150): ?><span class="badge badge-connected">running</span> — publishing due pins every minute (last beat <?= (int)(time() - $runnerBeat) ?> s ago)
            <?php else: ?><span class="badge">starting…</span> — it starts from page loads; refresh in a minute<?php endif; ?>
        </td></tr>
        <?php if ($cron['exec']): ?>
        <tr><td>Server</td><td>runs as user <code><?= e($cron['user'] ?: '?') ?></code> · cron service <?= $cron['crond'] === null ? '?' : ($cron['crond'] ? '<span class="badge badge-connected">running</span>' : '<span class="badge badge-error">stopped</span>') ?> · PHP CLI <code><?= e($cron['php'] ?: 'not found') ?></code></td></tr>
        <?php endif; ?>
    </table>

    <form method="post" style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <?php if ($cron['exec']): ?>
            <button type="submit" name="install_cron" value="1" class="btn-primary">⚙ Add cron job automatically</button>
            <button type="submit" name="test_cli" value="1" class="btn-secondary">🧪 Test cron command</button>
        <?php endif; ?>
        <button type="submit" name="runner_toggle" value="<?= $runnerOn ? 'off' : 'on' ?>" class="btn-secondary"><?= $runnerOn ? 'Turn built-in runner off' : 'Turn built-in runner on' ?></button>
    </form>
    <?php if ($cliTest): ?><pre style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:10px; margin-top:10px;"><?= e($cliTest['output']) ?></pre><?php endif; ?>

    <details style="margin-top:14px;">
        <summary style="cursor:pointer; font-weight:600;">Add the cron job by hand (CyberPanel / SSH)</summary>
        <p class="muted">CyberPanel → Websites → your site → <strong>Cron Jobs</strong>: minute/hour/day/month/weekday all <code>*</code>, command:</p>
        <pre style="white-space:pre-wrap; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; user-select:all;"><?= e(preg_replace('/^(\* ){5}/', '', cron_scheduler_line())) ?></pre>
        <p class="muted">If the cron service is stopped (AlmaLinux), in SSH as root: <code>systemctl enable --now crond</code>. To see cron activity: <code>tail -f /var/log/cron</code>.</p>
        <p class="muted">No server access? Use a free external cron (e.g. cron-job.org, every minute) with this URL:</p>
        <pre style="white-space:pre-wrap; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; user-select:all;"><?= e($webUrl) ?></pre>
    </details>
    <form method="post" style="margin-top:10px;">
        <button type="submit" name="run_now" value="1" class="btn-primary">▶ Run scheduler now</button>
        <a href="<?= e($webUrl) ?>" target="_blank" rel="noopener" class="btn-secondary" style="margin-left:6px;">Test the URL</a>
    </form>
    <?php if ($runOutput !== null): ?><pre style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:10px; margin-top:10px;"><?= e($runOutput) ?></pre><?php endif; ?>
    <?php if ($logTail !== ''): ?>
        <h3 style="margin-top:18px;">Recent scheduler log</h3>
        <pre style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:10px; max-height:260px; overflow:auto;"><?= e($logTail) ?></pre>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Upcoming Queue (<?= count($upcoming) ?>)</h2>
    <?php if (empty($upcoming)): ?>
        <div class="empty-state">Nothing queued right now.</div>
    <?php else: ?>
    <table>
        <tr><th>User</th><th>Board</th><th>Title</th><th>Publish At</th><th>Status</th><th>Last error / retry</th></tr>
        <?php foreach ($upcoming as $p): ?>
        <tr>
            <td><?= e($p['user_name']) ?></td>
            <td><?= e($p['board_name'] ?: $p['board_id']) ?></td>
            <td><?= e($p['title'] ?: '(no title)') ?></td>
            <td><?= format_datetime($p['publish_at']) ?></td>
            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
            <td style="font-size:12px; max-width:320px;"><?= e(mb_substr((string)($p['last_error'] ?? ''), 0, 160)) ?><?= !empty($p['next_retry_at']) ? '<br><span class="muted">retry ' . e(format_datetime($p['next_retry_at'])) . '</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Failed Jobs (<?= count($failed) ?>)</h2>
    <?php if (empty($failed)): ?>
        <div class="empty-state">No failed jobs.</div>
    <?php else: ?>
    <table>
        <tr><th>User</th><th>Board</th><th>Title</th><th>Publish At</th><th>Error</th><th>Attempts</th><th></th></tr>
        <?php foreach ($failed as $p): ?>
        <tr>
            <td><?= e($p['user_name']) ?></td>
            <td><?= e($p['board_name'] ?: $p['board_id']) ?></td>
            <td><?= e($p['title'] ?: '(no title)') ?></td>
            <td><?= format_datetime($p['publish_at']) ?></td>
            <td class="muted"><?= e(mb_strimwidth((string)$p['last_error'], 0, 80, '...')) ?></td>
            <td><?= (int)$p['attempts'] ?></td>
            <td><a href="?retry=<?= (int)$p['id'] ?>" class="btn-secondary btn-small">Retry</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
