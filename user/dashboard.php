<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Came from a shared design ("Edit this design" → log in / sign up): open it in the editor now.
if (!empty($_SESSION['after_login_redirect'])) {
    $next = (string)$_SESSION['after_login_redirect'];
    unset($_SESSION['after_login_redirect']);
    if (preg_match('#^/user/[a-z0-9\-]+(\?[A-Za-z0-9=&%_\-]*)?$#', $next)) redirect(rtrim(APP_URL, '/') . $next);
}

$user = current_user($pdo);
$activePage = 'dashboard';
$pageTitle = 'Dashboard';

$accounts = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ?");
$accounts->execute([$user['id']]);
$accounts = $accounts->fetchAll();

$stmt = $pdo->prepare("SELECT status, COUNT(*) as c FROM scheduled_pins WHERE user_id = ? GROUP BY status");
$stmt->execute([$user['id']]);
$statusCounts = ['pending' => 0, 'processing' => 0, 'published' => 0, 'failed' => 0];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['c'];
}

// Pins grouped by board
$stmt = $pdo->prepare("SELECT board_name, board_id, COUNT(*) as total,
        SUM(status='published') as published, SUM(status='pending') as pending
    FROM scheduled_pins WHERE user_id = ? GROUP BY board_id, board_name ORDER BY total DESC");
$stmt->execute([$user['id']]);
$byBoard = $stmt->fetchAll();

$totalScheduled = array_sum($statusCounts);

// Websites connected + Auto Article results.
$sitesTotal = $sitesConnected = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) total, COALESCE(SUM(status = 'connected'), 0) ok FROM websites WHERE user_id = ?");
    $st->execute([$user['id']]);
    $r = $st->fetch();
    $sitesTotal = (int)$r['total'];
    $sitesConnected = (int)$r['ok'];
} catch (Throwable $e) { /* table missing */ }
$articleCounts = ['published' => 0, 'failed' => 0, 'progress' => 0, 'draft' => 0];
try {
    $st = $pdo->prepare("SELECT status, COUNT(*) c FROM articles WHERE user_id = ? GROUP BY status");
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $r) {
        $k = in_array($r['status'], ['published', 'failed', 'draft'], true) ? $r['status'] : 'progress';
        $articleCounts[$k] += (int)$r['c'];
    }
} catch (Throwable $e) { /* table missing */ }

// Full plan usage.
require_once __DIR__ . '/../includes/pricing_functions.php';
$usage = get_user_plan_usage_summary($pdo, (int)$user['id']);
$plan = $usage['plan'];
$planEnd = !empty($user['plan_end_date']) ? format_datetime($user['plan_end_date']) : null;
$fmtNum = fn($v) => $v === null ? 'Unlimited' : number_format((int)$v);
$fmtBytes = fn($b) => $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : round($b / 1048576, 1) . ' MB';
$usageRows = [
    ['🖼️', 'Image AI credits (month)', $usage['image_credits']],
    ['✍️', 'Text AI credits (month)', $usage['text_credits']],
    ['📅', 'Pins scheduled this month', $usage['pin_monthly']],
    ['⏱️', 'Pins scheduled today', $usage['pin_daily']],
    ['📌', 'Uploaded pins', $usage['upload_pins']],
    ['👤', 'Pinterest accounts', $usage['pinterest_accounts']],
    ['🌐', 'Websites', $usage['websites']],
    ['👥', 'Team members', $usage['team_invites']],
];

include __DIR__ . '/includes/user-header.php';
?>
<?php
$freeToolPinPending = $_SESSION['free_tool_pincreate_pending'] ?? null;
unset($_SESSION['free_tool_pincreate_pending']);
?>
<div class="page-header">
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <a href="schedule-create" class="btn-primary">+ New Schedule</a>
</div>

<?php if ($freeToolPinPending && !empty($freeToolPinPending['clean_path'])): ?>
<div class="alert alert-success">
    Your free pin "<?= e($freeToolPinPending['title'] ?? '') ?>" is ready —
    <a href="../<?= e($freeToolPinPending['clean_path']) ?>" download>download it without the watermark</a>.
</div>
<?php endif; ?>

<style>
.db-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px; }
.db-stat { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 16px; position: relative; overflow: hidden; color: inherit; }
a.db-stat:hover { text-decoration: none; box-shadow: 0 8px 24px rgba(17,24,39,.08); }
.db-stat::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--c, #e60023); }
.db-stat .ic { font-size: 20px; }
.db-stat .num { font-size: 26px; font-weight: 800; margin-top: 6px; line-height: 1.1; }
.db-stat .num small { font-size: 13px; font-weight: 600; color: var(--gray); }
.db-stat .label { font-size: 13px; color: var(--gray); margin-top: 4px; }
.db-sec-title { font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--gray); margin: 4px 0 10px; }
.db-plan-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.db-plan-head h2 { margin: 0; }
.db-plan-name { display: inline-block; background: linear-gradient(90deg, #e60023, #db2777); color: #fff; border-radius: 999px; padding: 3px 12px; font-size: 13px; font-weight: 700; margin-left: 6px; vertical-align: middle; }
.db-usage { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
.db-u { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.db-u-top { display: flex; justify-content: space-between; gap: 8px; font-size: 13px; }
.db-u-top strong { white-space: nowrap; }
.db-u-bar { height: 7px; border-radius: 99px; background: var(--light); margin-top: 9px; overflow: hidden; }
.db-u-bar i { display: block; height: 100%; border-radius: 99px; background: #16a34a; }
.db-u.warn { border-color: #fca5a5; background: #fef2f2; }
.db-u.warn .db-u-bar i { background: #dc2626; }
.db-u .db-u-inf { margin-top: 8px; font-size: 12px; color: #16a34a; font-weight: 700; }
.db-u .db-u-left { margin-top: 6px; font-size: 12px; color: var(--gray); }
.db-flags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.db-flags span { font-size: 13px; border-radius: 999px; padding: 6px 12px; background: #ecfdf5; color: #047857; font-weight: 600; }
.db-flags span.off { background: #f3f4f6; color: #6b7280; }
[data-theme="dark"] .db-stat, [data-theme="dark"] .db-u { background: #1e2025; border-color: #33353c; }
[data-theme="dark"] .db-u.warn { background: #3a1d1d; border-color: #7f1d1d; }
[data-theme="dark"] .db-u-bar { background: #2a2c33; }
[data-theme="dark"] .db-flags span { background: #12352a; color: #6ee7b7; }
[data-theme="dark"] .db-flags span.off { background: #2a2c33; color: #9ca3af; }
</style>

<div class="db-sec-title">Overview</div>
<div class="db-stats">
    <a href="websites" class="db-stat" style="--c:#2563eb"><div class="ic">🌐</div><div class="num"><?= number_format($sitesConnected) ?><?php if ($sitesTotal > $sitesConnected): ?> <small>/ <?= number_format($sitesTotal) ?></small><?php endif; ?></div><div class="label">Websites connected</div></a>
    <a href="connect-pinterest" class="db-stat" style="--c:#e60023"><div class="ic">📌</div><div class="num"><?= count($accounts) ?></div><div class="label">Pinterest accounts</div></a>
    <a href="auto-article-batches" class="db-stat" style="--c:#16a34a"><div class="ic">📰</div><div class="num"><?= number_format($articleCounts['published']) ?></div><div class="label">Articles published</div></a>
    <a href="auto-article-batches" class="db-stat" style="--c:#dc2626"><div class="ic">⚠️</div><div class="num"><?= number_format($articleCounts['failed']) ?></div><div class="label">Articles failed</div></a>
    <a href="auto-article-batches" class="db-stat" style="--c:#f59e0b"><div class="ic">✍️</div><div class="num"><?= number_format($articleCounts['progress']) ?></div><div class="label">Articles in progress</div></a>
</div>

<div class="db-sec-title">Pins</div>
<div class="db-stats">
    <a href="schedule-list" class="db-stat" style="--c:#7c3aed"><div class="ic">🗓️</div><div class="num"><?= number_format($totalScheduled) ?></div><div class="label">Total scheduled pins</div></a>
    <a href="schedule-list?status=pending" class="db-stat" style="--c:#f59e0b"><div class="ic">⏳</div><div class="num"><?= number_format($statusCounts['pending'] + $statusCounts['processing']) ?></div><div class="label">Pending</div></a>
    <a href="schedule-list?status=published" class="db-stat" style="--c:#16a34a"><div class="ic">✅</div><div class="num"><?= number_format($statusCounts['published']) ?></div><div class="label">Published</div></a>
    <a href="schedule-list?status=failed" class="db-stat" style="--c:#dc2626"><div class="ic">❌</div><div class="num"><?= number_format($statusCounts['failed']) ?></div><div class="label">Failed pins</div></a>
</div>

<div class="card">
    <div class="db-plan-head">
        <div>
            <h2>Plan usage <?php if ($plan): ?><span class="db-plan-name"><?= e($plan['name']) ?></span><?php endif; ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;">
                <?php if (!$plan): ?>You don't have an active plan yet.
                <?php elseif ($planEnd): ?>Renews / ends on <?= e($planEnd) ?>
                <?php else: ?>Your current plan<?php endif; ?>
            </div>
        </div>
        <a href="upgrade" class="btn-primary btn-small">✨ Upgrade plan</a>
    </div>
    <div class="db-usage">
        <?php foreach ($usageRows as [$ic, $label, $it]): $warn = usage_is_at_limit($it); ?>
        <div class="db-u <?= $warn ? 'warn' : '' ?>">
            <div class="db-u-top"><span><?= $ic ?> <?= e($label) ?></span><strong><?= number_format((int)$it['used']) ?> / <?= e($fmtNum($it['total'])) ?></strong></div>
            <?php if ($it['total'] === null): ?>
                <div class="db-u-inf">∞ Unlimited</div>
            <?php else: ?>
                <div class="db-u-bar"><i style="width:<?= (int)$it['pct'] ?>%"></i></div>
                <div class="db-u-left"><?= $warn ? 'Limit reached — <a href="upgrade">upgrade</a>' : number_format(max(0, (int)$it['total'] - (int)$it['used'])) . ' left' ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php $stg = $usage['storage']; $warn = usage_is_at_limit($stg); ?>
        <div class="db-u <?= $warn ? 'warn' : '' ?>">
            <div class="db-u-top"><span>☁️ Cloud storage</span><strong><?= e($fmtBytes($stg['used'])) ?> / <?= e($fmtBytes($stg['total'])) ?></strong></div>
            <div class="db-u-bar"><i style="width:<?= (int)$stg['pct'] ?>%"></i></div>
            <div class="db-u-left"><?= $warn ? 'Storage full — <a href="upgrade">upgrade</a>' : e($fmtBytes(max(0, $stg['total'] - $stg['used']))) . ' free' ?></div>
        </div>
    </div>
    <div class="db-flags">
        <?php foreach ($usage['features'] as $f): ?>
            <span class="<?= $f['available'] ? '' : 'off' ?>"><?= $f['available'] ? '✓' : '✕' ?> <?= e($f['label']) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($accounts)): ?>
<div class="card">
    <h2>Get started</h2>
    <p>You haven't connected a Pinterest account yet.</p>
    <a href="connect-pinterest" class="btn-primary">Connect Pinterest</a>
</div>
<?php endif; ?>

<div class="card">
    <h2>Scheduled pins by board</h2>
    <?php if (empty($byBoard)): ?>
        <div class="empty-state">No pins scheduled yet.</div>
    <?php else: ?>
    <table>
        <tr><th>Board</th><th>Total</th><th>Published</th><th>Pending</th></tr>
        <?php foreach ($byBoard as $b): ?>
        <tr>
            <td><?= e((string)($b['board_name'] ?: $b['board_id'])) ?></td>
            <td><?= $b['total'] ?></td>
            <td><?= $b['published'] ?></td>
            <td><?= $b['pending'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
