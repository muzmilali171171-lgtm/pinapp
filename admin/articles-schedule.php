<?php
/**
 * Admin → Articles Schedule.
 * Cron settings for the article queue (like Scheduler for pins), the upcoming queue (articles not
 * published yet) and failed articles with their full error — with Retry.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'articles-schedule';
$pageTitle = 'Articles Schedule';
$msg = null; $err = null; $runOutput = null; $cliTest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_now'])) {
        @set_time_limit(600);
        $r = article_scheduler_run($pdo, 'admin', 8);
        $runOutput = implode("\n", $r['messages']);
    } elseif (isset($_POST['install_cron'])) {
        $r = cron_install();
        if ($r['ok']) $msg = 'Cron jobs added: ' . $r['line']; else $err = $r['error'];
    } elseif (isset($_POST['test_cli'])) {
        $cliTest = cron_test_cli('article-scheduler.php');
    } elseif (isset($_POST['retry'])) {
        $st = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND status = 'failed'");
        $st->execute([(int)$_POST['retry']]);
        if ($a = $st->fetch()) {
            // restart from the furthest safe step: publish again if it's written + illustrated, else redo images, else start over
            $to = trim((string)$a['content']) !== '' ? (!empty($a['featured_image_path']) ? 'ready' : 'imaging') : 'queued';
            // writing was in progress: keep the parts already written, reset the try counter
            $sj = json_decode((string)($a['sections_json'] ?? ''), true);
            $sjOut = $a['sections_json'];
            if ($to === 'queued' && is_array($sj) && ($sj['v'] ?? 0) === 2) { $sj['fails'] = 0; $sjOut = json_encode($sj); $to = 'drafted'; }
            elseif ($to === 'queued' && is_array($sj) && isset($sj['outline_fails'])) { $sjOut = null; }
            $pdo->prepare("UPDATE articles SET status = ?, sections_json = ?, last_error = NULL, scheduled_for = LEAST(COALESCE(scheduled_for, CURDATE()), CURDATE()) WHERE id = ?")
                ->execute([$to, $sjOut, $a['id']]);
            $msg = "Article #{$a['id']} queued again (from step: $to). It runs on the next cron pass.";
        }
    }
}

scheduler_runner_kick(false, 'articles');   // keep articles moving while no cron job works
$last = article_last_run();
$artRunnerBeat = scheduler_runner_heartbeat('articles');
$lastAgo = $last ? time() - (int)$last['at'] : null;
$cron = cron_status('article-scheduler.php');
$cronAlive = article_cron_alive();
$webUrl = rtrim(APP_URL, '/') . '/cron/article-scheduler.php?key=' . scheduler_web_key();

$pipeline = "'queued','drafting','drafted','imaging','ready','publishing','draft'";
$upcoming = $pdo->query("SELECT a.id, a.title, a.status, a.scheduled_for, a.updated_at, a.last_error, u.name AS user_name, u.email,
        b.name AS batch_name, b.status AS batch_status, w.site_name, w.site_url
    FROM articles a JOIN users u ON u.id = a.user_id
    LEFT JOIN article_batches b ON b.id = a.batch_id
    LEFT JOIN websites w ON w.id = a.website_id
    WHERE a.status IN ($pipeline) ORDER BY a.scheduled_for ASC, a.id ASC LIMIT 100")->fetchAll();
$failed = $pdo->query("SELECT a.id, a.title, a.last_error, a.updated_at, a.scheduled_for, u.name AS user_name, u.email, b.name AS batch_name, w.site_name
    FROM articles a JOIN users u ON u.id = a.user_id
    LEFT JOIN article_batches b ON b.id = a.batch_id
    LEFT JOIN websites w ON w.id = a.website_id
    WHERE a.status = 'failed' ORDER BY a.updated_at DESC LIMIT 100")->fetchAll();
$stuckBatches = (int)$pdo->query("SELECT COUNT(*) FROM articles a JOIN article_batches b ON b.id = a.batch_id WHERE a.status IN ($pipeline) AND b.status <> 'active'")->fetchColumn();

$stepLabel = ['queued' => 'Waiting', 'drafting' => 'Writing', 'drafted' => 'Writing (in parts)', 'draft' => 'Writing', 'imaging' => 'Making images', 'ready' => 'Ready to publish', 'publishing' => 'Publishing'];

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Articles Schedule</h1></div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<div class="card">
    <h2>Article queue health</h2>
    <?php if ($lastAgo === null): ?>
        <div class="alert alert-error">The article queue has <strong>never run</strong>. Articles wait until it does — add the cron job below.</div>
    <?php elseif (!$cronAlive): ?>
        <div class="alert alert-error">No cron run in the last 10 minutes (last run <?= e(floor($lastAgo / 60)) ?> min ago by <?= e($last['source'] ?? '?') ?>). Articles only move forward when someone uses the app — add the cron job.</div>
    <?php else: ?>
        <div class="alert alert-success">Running — last run <?= (int)$lastAgo ?> s ago (<?= e($last['source'] ?? '') ?>): <?= (int)($last['steps'] ?? 0) ?> step(s), <?= (int)($last['published'] ?? 0) ?> published, <?= (int)($last['failed'] ?? 0) ?> failed.</div>
    <?php endif; ?>
    <?php if ($stuckBatches): ?><div class="alert alert-info"><?= $stuckBatches ?> queued article(s) belong to stopped batches — they won't run until the batch is resumed.</div><?php endif; ?>

    <table>
        <tr><td style="width:220px;">Cron job (every 2 minutes)</td><td>
            <?php if ($cronAlive): ?><span class="badge badge-connected">working</span>
            <?php elseif ($cron['installed']): ?><span class="badge badge-error">installed but not running</span> <?= $cron['crond'] === false ? '— the cron service (crond) is stopped: <code>systemctl enable --now crond</code>' : '' ?>
            <?php elseif ($cron['broken']): ?><span class="badge badge-error">wrong command</span> <code><?= e($cron['broken'][0]) ?></code>
            <?php else: ?><span class="badge badge-error">not set up</span><?php endif; ?>
        </td></tr>
        <tr><td>Built-in article runner</td><td>
            <?php if (!scheduler_runner_enabled()): ?><span class="badge badge-error">off</span> (Admin → Scheduler)
            <?php elseif ($cronAlive): ?><span class="badge">standing by</span> — not needed while the cron job works
            <?php elseif ($artRunnerBeat > time() - 420): ?><span class="badge badge-connected">running</span> — writing in the background (last beat <?= (int)(time() - $artRunnerBeat) ?> s ago)
            <?php else: ?><span class="badge">starting…</span> — refresh in a minute<?php endif; ?>
        </td></tr>
        <tr><td>App time</td><td><?= e(date('Y-m-d H:i:s')) ?> — <?= e(date_default_timezone_get()) ?></td></tr>
    </table>
    <form method="post" style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <button type="submit" name="run_now" value="1" class="btn-primary">▶ Run article queue now</button>
        <?php if ($cron['exec']): ?>
            <button type="submit" name="install_cron" value="1" class="btn-secondary">⚙ Add cron jobs automatically</button>
            <button type="submit" name="test_cli" value="1" class="btn-secondary">🧪 Test cron command</button>
        <?php endif; ?>
    </form>
    <?php if ($runOutput !== null): ?><pre style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:10px; margin-top:10px;"><?= e($runOutput) ?></pre><?php endif; ?>
    <?php if ($cliTest): ?><pre style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:10px; margin-top:10px;"><?= e($cliTest['output']) ?></pre><?php endif; ?>
    <details style="margin-top:12px;">
        <summary style="cursor:pointer; font-weight:600;">Add the cron job by hand (CyberPanel / SSH)</summary>
        <p class="muted">CyberPanel → Websites → your site → Cron Jobs: minute <code>*/2</code>, the rest <code>*</code>, command:</p>
        <pre style="white-space:pre-wrap; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; user-select:all;"><?= e(preg_replace('/^\S+ \S+ \S+ \S+ \S+ /', '', cron_line_for('article-scheduler.php'))) ?></pre>
        <p class="muted">Or an external cron (e.g. cron-job.org, every 2 minutes):</p>
        <pre style="white-space:pre-wrap; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; user-select:all;"><?= e($webUrl) ?></pre>
    </details>
</div>

<div class="card">
    <h2>Upcoming queue — not published yet (<?= count($upcoming) ?><?= count($upcoming) >= 100 ? '+' : '' ?>)</h2>
    <?php if (!$upcoming): ?><div class="empty-state">Nothing waiting.</div><?php else: ?>
    <div style="overflow-x:auto;"><table>
        <tr><th>ID</th><th>User</th><th>Article</th><th>Website</th><th>Scheduled for</th><th>Step</th><th>Note</th></tr>
        <?php foreach ($upcoming as $a): ?>
        <tr>
            <td>#<?= (int)$a['id'] ?></td>
            <td><?= e($a['user_name'] ?: $a['email']) ?></td>
            <td><strong><?= e($a['title'] ?: '(title pending)') ?></strong><br><span class="muted" style="font-size:12px;"><?= e($a['batch_name'] ?: '') ?><?= $a['batch_status'] && $a['batch_status'] !== 'active' ? ' · batch stopped' : '' ?></span></td>
            <td><?= e($a['site_name'] ?: ($a['site_url'] ?: '—')) ?></td>
            <td style="white-space:nowrap;"><?= e($a['scheduled_for'] ? date('d M Y', strtotime($a['scheduled_for'])) : '—') ?></td>
            <td><span class="badge badge-<?= e($a['status']) ?>"><?= e($stepLabel[$a['status']] ?? $a['status']) ?></span></td>
            <td style="font-size:12px; max-width:280px; color:#b45309;"><?= e(mb_substr((string)$a['last_error'], 0, 160)) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Failed articles (<?= count($failed) ?>)</h2>
    <?php if (!$failed): ?><div class="empty-state">No failed articles.</div><?php else: ?>
    <?php foreach ($failed as $a): ?>
        <div style="border:1px solid #fecaca; border-radius:10px; padding:12px; margin-bottom:10px; background:#fff;">
            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <div><strong>#<?= (int)$a['id'] ?> · <?= e($a['title'] ?: '(no title)') ?></strong><br>
                    <span class="muted" style="font-size:12.5px;">👤 <?= e($a['user_name'] ?: $a['email']) ?> · 🌐 <?= e($a['site_name'] ?: '—') ?> · <?= e($a['batch_name'] ?: '') ?> · failed <?= e(format_datetime($a['updated_at'])) ?></span></div>
                <form method="post"><button type="submit" name="retry" value="<?= (int)$a['id'] ?>" class="btn-secondary btn-small">↻ Retry</button></form>
            </div>
            <pre style="white-space:pre-wrap; background:#fef2f2; color:#991b1b; padding:10px; border-radius:8px; margin:8px 0 0; font-size:12.5px; max-height:260px; overflow:auto;"><?= e($a['last_error'] ?: 'No error text was saved.') ?></pre>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
