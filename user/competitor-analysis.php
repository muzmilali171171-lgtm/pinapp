<?php
/**
 * User → Analytics → Competitor Analysis.
 * Add a competitor by Pinterest username or profile / board link; their public Pins, boards, destination
 * links and save counts are collected (includes/competitor_functions.php) and turned into a report:
 * total Pins, top Pins, common keywords & phrases, content type, destination domains, posting pattern,
 * topic clusters, content patterns and boards — with CSV downloads.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/competitor_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/competitor-report.php';
require_login();

$user = current_user($pdo);
$activePage = 'competitor-analysis';
$pageTitle = 'Competitor Analysis';
$uid = (int)$user['id'];
$competitors = cp_user_competitors($pdo, $uid);
$sel = !empty($_GET['id']) ? cp_get($pdo, $uid, (int)$_GET['id']) : null;
$report = $sel ? cp_report(cp_pins($pdo, (int)$sel['id'])) : null;

include __DIR__ . '/includes/user-header.php';
cp_css();
?>
<style>
.cpa-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
.cpa-item { display: flex; gap: 10px; align-items: center; border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; color: inherit; text-decoration: none; }
.cpa-item:hover { text-decoration: none; background: var(--light); }
.cpa-item.on { border-color: var(--red); box-shadow: 0 0 0 1px var(--red) inset; }
.cpa-item img, .cpa-av { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: var(--light); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--gray); }
.cpa-item .nm { font-weight: 700; line-height: 1.2; }
.cpa-item .mt { font-size: 12px; color: var(--gray); }
.cpa-add { display: flex; gap: 8px; flex-wrap: wrap; }
.cpa-add input { flex: 1; min-width: 240px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; font: inherit; }
.cpa-head { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.cpa-head img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
.cpa-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
</style>

<div class="page-header">
    <h1>Competitor Analysis</h1>
    <a href="competitor-research" class="btn-secondary">🔎 Competitor Research</a>
</div>
<div id="alertBox"></div>

<div class="card">
    <h2 style="margin-top:0;">Add a competitor</h2>
    <form id="cpaAdd" class="cpa-add">
        <input type="text" id="cpaProfile" placeholder="Pinterest username or link — e.g. apartmenttherapy or https://www.pinterest.com/apartmenttherapy/" maxlength="300">
        <button type="submit" class="btn-primary" id="cpaAddBtn">+ Add &amp; analyze</button>
    </form>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">We collect the profile's public Pins, boards, destination links and save counts from Pinterest's public data
        (Pinterest's API only shares full metrics for your own account). Up to <?= CP_MAX_COMPETITORS ?> competitors; refresh any time after an hour.</p>
</div>

<?php if ($competitors): ?>
<div class="card">
    <h2 style="margin-top:0;">Your competitors <span class="muted" style="font-weight:400;">(<?= count($competitors) ?>)</span></h2>
    <div class="cpa-list">
        <?php foreach ($competitors as $c): ?>
        <a class="cpa-item <?= $sel && (int)$sel['id'] === (int)$c['id'] ? 'on' : '' ?>" href="competitor-analysis?id=<?= (int)$c['id'] ?>">
            <?php if ($c['avatar_url']): ?><img src="<?= e($c['avatar_url']) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><span class="cpa-av"><?= e(mb_strtoupper(mb_substr($c['username'], 0, 1))) ?></span><?php endif; ?>
            <span>
                <span class="nm"><?= e($c['display_name'] ?: $c['username']) ?></span><br>
                <span class="mt">@<?= e($c['username']) ?> · <?= number_format((int)$c['pins_collected']) ?> Pins collected<?= $c['followers'] !== null ? ' · ' . number_format((int)$c['followers']) . ' followers' : '' ?></span><br>
                <span class="mt"><?= $c['status'] === 'error' ? '⚠️ ' . e(mb_strimwidth((string)$c['last_error'], 0, 70, '…')) : 'Updated ' . e(format_datetime($c['fetched_at'])) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($sel && $report): ?>
<div class="card">
    <div class="cpa-head">
        <?php if ($sel['avatar_url']): ?><img src="<?= e($sel['avatar_url']) ?>" alt="" referrerpolicy="no-referrer"><?php endif; ?>
        <div>
            <h2 style="margin:0;"><?= e($sel['display_name'] ?: $sel['username']) ?></h2>
            <div class="muted"><a href="https://www.pinterest.com/<?= e($sel['username']) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($sel['username']) ?> ↗</a>
                <?= $sel['followers'] !== null ? ' · ' . number_format((int)$sel['followers']) . ' followers' : '' ?>
                <?= $sel['total_pins'] !== null ? ' · ' . number_format((int)$sel['total_pins']) . ' Pins on profile' : '' ?>
                · collected <?= e(format_datetime($sel['fetched_at'])) ?></div>
            <?php if ($sel['about']): ?><div class="muted" style="font-size:13px; margin-top:4px;"><?= e(mb_strimwidth((string)$sel['about'], 0, 220, '…')) ?></div><?php endif; ?>
        </div>
        <div class="cpa-actions">
            <button type="button" class="btn-secondary btn-small" data-refresh="<?= (int)$sel['id'] ?>">🔄 Refresh data</button>
            <a class="btn-secondary btn-small" href="ajax-competitor?action=csv&type=report&id=<?= (int)$sel['id'] ?>">⬇ Report CSV</a>
            <a class="btn-secondary btn-small" href="ajax-competitor?action=csv&type=pins&id=<?= (int)$sel['id'] ?>">⬇ All Pins CSV</a>
            <button type="button" class="btn-danger btn-small" data-delete="<?= (int)$sel['id'] ?>">Remove</button>
        </div>
    </div>
    <?php if ($sel['last_error']): ?><p class="cp-note" style="margin:10px 0 0;">Note from the last collection: <?= e(mb_strimwidth((string)$sel['last_error'], 0, 300, '…')) ?></p><?php endif; ?>
</div>
<?php cp_render_report($report); ?>
<?php elseif (!$competitors): ?>
<div class="card"><div class="empty-state">Add your first competitor above to see their report: top Pins, keywords, topics, domains and posting pattern.</div></div>
<?php else: ?>
<div class="card"><div class="empty-state">Pick a competitor above to see their report.</div></div>
<?php endif; ?>

<script>
(function () {
    const CSRF = <?= json_encode(csrf_token()) ?>;
    const alertBox = document.getElementById('alertBox');
    const show = (m, t) => { alertBox.innerHTML = '<div class="alert alert-' + t + '">' + String(m).replace(/</g, '&lt;') + '</div>'; window.scrollTo({ top: 0, behavior: 'smooth' }); };
    async function post(data) {
        const fd = new FormData(); fd.append('csrf_token', CSRF);
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        try { return await (await fetch('ajax-competitor', { method: 'POST', body: fd, credentials: 'same-origin' })).json(); }
        catch (e) { return { ok: false, error: 'Network error — collecting can take up to a minute for big profiles, please try again.' }; }
    }
    document.getElementById('cpaAdd').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('cpaAddBtn');
        const v = document.getElementById('cpaProfile').value.trim();
        if (!v) return;
        btn.disabled = true; btn.textContent = 'Collecting public Pins… (up to a minute)';
        const r = await post({ action: 'add', profile: v });
        btn.disabled = false; btn.textContent = '+ Add & analyze';
        if (!r.ok) return show(r.error || 'Could not add this profile.', 'error');
        location.href = 'competitor-analysis?id=' + r.id + (r.warning ? '&w=1' : '');
    });
    document.addEventListener('click', async (e) => {
        const rf = e.target.closest('[data-refresh]');
        if (rf) {
            rf.disabled = true; rf.textContent = 'Collecting…';
            const r = await post({ action: 'refresh', id: rf.dataset.refresh });
            if (r.ok) { show('Updated — ' + r.new + ' new Pins, ' + r.total + ' in total.', 'success'); setTimeout(() => location.reload(), 900); }
            else { rf.disabled = false; rf.textContent = '🔄 Refresh data'; show(r.error || 'Could not refresh.', 'error'); }
        }
        const del = e.target.closest('[data-delete]');
        if (del && confirm('Remove this competitor and all Pins collected for it?')) {
            const r = await post({ action: 'delete', id: del.dataset.delete });
            if (r.ok) location.href = 'competitor-analysis'; else show(r.error || 'Could not remove it.', 'error');
        }
    });
})();
</script>
<?php include __DIR__ . '/includes/user-footer.php'; ?>
