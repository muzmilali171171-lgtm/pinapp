<?php
/**
 * User → Analytics → Competitor Research.
 * Keyword → relevant Pins (from every competitor you track) → which competitors keep appearing →
 * their titles/descriptions → repeated keywords & phrases → destination domains → public save counts →
 * which topics / content patterns get the most saves → related keywords (official Pinterest API,
 * using your connected account) and topic clusters. CSV downloads for the report and the Pins.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pinterest_analytics_functions.php';
require_once __DIR__ . '/../includes/competitor_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/competitor-report.php';
require_login();

$user = current_user($pdo);
$activePage = 'competitor-research';
$pageTitle = 'Competitor Research';
$uid = (int)$user['id'];
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$competitors = cp_user_competitors($pdo, $uid);
$res = $q !== '' ? cp_keyword_research($pdo, $uid, $q) : null;

// Related / suggested keywords from the official Pinterest API (needs a connected account; quietly skipped when refused).
$related = [];
$relatedNote = null;
if ($q !== '') {
    $account = null;
    foreach (pa_user_accounts($pdo, $uid) as $a) if ($a['status'] === 'connected') { $account = $a; break; }
    if ($account) {
        foreach ([['terms' => 'related', 'term' => $q], ['terms' => 'suggested', 'term' => $q]] as $src) {
            try {
                $r = pa_kw_fetch_source($pdo, $account, 'US', $src);
                if ($r['ok']) foreach ($r['rows'] as $row) $related[mb_strtolower($row['keyword'])] = $row['keyword'];
            } catch (Throwable $e) { /* optional */ }
        }
        unset($related[mb_strtolower($q)]);
        if (!$related) $relatedNote = 'Pinterest did not return related keywords for this term (the related-terms API may need extra access for your app).';
    } else {
        $relatedNote = 'Connect a Pinterest account to also see Pinterest\'s own related keywords.';
    }
}

include __DIR__ . '/includes/user-header.php';
cp_css();
?>
<style>
.cpr-form { display: flex; gap: 8px; flex-wrap: wrap; }
.cpr-form input { flex: 1; min-width: 240px; padding: 11px 13px; border: 1px solid var(--border); border-radius: 10px; font: inherit; font-size: 15px; }
.cpr-comp { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; }
.cpr-comp a { display: block; border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; color: inherit; text-decoration: none; }
.cpr-comp a:hover { background: var(--light); text-decoration: none; }
.cpr-rank { display: inline-flex; width: 24px; height: 24px; border-radius: 50%; background: var(--red); color: #fff; font-size: 12px; font-weight: 800; align-items: center; justify-content: center; margin-right: 6px; }
.cpr-topics li { margin: 4px 0; }
</style>

<div class="page-header">
    <h1>Competitor Research</h1>
    <a href="competitor-analysis" class="btn-secondary">👤 Competitor Analysis</a>
</div>

<div class="card">
    <form method="GET" class="cpr-form">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Keyword — e.g. small bedroom ideas" maxlength="100" autofocus>
        <button type="submit" class="btn-primary">🔎 Research</button>
    </form>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">Searches the public Pins of the <strong><?= count($competitors) ?></strong> competitor<?= count($competitors) === 1 ? '' : 's' ?> you track
        (<a href="competitor-analysis">add more</a> for wider results), compares them with your own Pins, and adds Pinterest's related keywords.
        Pinterest's official API doesn't offer a public Pin search, so results come from the competitors you add.</p>
</div>

<?php if (!$competitors): ?>
<div class="card"><div class="empty-state">Add a few competitors in <a href="competitor-analysis">Competitor Analysis</a> first — research runs over their Pins.</div></div>
<?php elseif ($res): $rep = $res['report']; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
        <h2 style="margin:0;">Keyword: “<?= e($q) ?>”</h2>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn-secondary btn-small" href="ajax-competitor?action=research_csv&type=report&q=<?= rawurlencode($q) ?>">⬇ Report CSV</a>
            <a class="btn-secondary btn-small" href="ajax-competitor?action=research_csv&type=pins&q=<?= rawurlencode($q) ?>">⬇ Matching Pins CSV</a>
        </div>
    </div>
    <div class="cp-grid" style="margin-top:12px;">
        <div class="cp-stat"><b><?= number_format(count($res['pins'])) ?></b><span>Relevant competitor Pins</span></div>
        <div class="cp-stat"><b><?= count($res['competitors']) ?></b><span>Competitors on this keyword</span></div>
        <div class="cp-stat"><b><?= $rep['avg_saves'] !== null ? number_format($rep['avg_saves'], 1) : '—' ?></b><span>Competitors' avg saves</span></div>
        <div class="cp-stat"><b><?= number_format($res['own']['pins']) ?></b><span>Your Pins on this keyword<?= $res['own']['avg_saves'] !== null ? ' · ' . number_format($res['own']['avg_saves'], 1) . ' avg saves' : '' ?></span></div>
    </div>
</div>

<?php if (!$res['pins']): ?>
    <div class="card"><div class="empty-state">None of your competitors' collected Pins mention “<?= e($q) ?>”. Try a broader keyword, or add competitors in this niche.</div></div>
<?php endif; ?>

<div class="cp-two">
    <div class="card cp-card">
        <h3>🧩 Top recurring topics</h3>
        <?php if (!$rep['clusters']): ?><p class="cp-note">Not enough Pins yet.</p><?php else: ?>
        <ol class="cpr-topics">
            <?php foreach ($rep['clusters'] as $c): ?><li><strong><?= e($c['topic']) ?></strong> <span class="cp-note">— <?= (int)$c['pins'] ?> Pins<?= $c['avg_saves'] !== null ? ' · ' . number_format($c['avg_saves'], 1) . ' avg saves' : '' ?></span></li><?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <?php if ($related): ?>
        <h3 style="margin-top:16px;">🔎 Related keywords <span class="cp-note">(Pinterest)</span></h3>
        <div class="cp-chips"><?php foreach (array_slice($related, 0, 30) as $k): ?><a href="?q=<?= rawurlencode($k) ?>" style="text-decoration:none;"><span><?= e($k) ?></span></a><?php endforeach; ?></div>
        <?php elseif ($relatedNote): ?><p class="cp-note" style="margin-top:12px;"><?= e($relatedNote) ?></p><?php endif; ?>
    </div>
    <div class="card cp-card">
        <h3>👤 Frequently appearing competitors</h3>
        <?php if (!$res['competitors']): ?><p class="cp-note">No competitor mentions this keyword yet.</p><?php else: ?>
        <div class="cpr-comp">
            <?php
            $ids = [];
            foreach ($competitors as $c) $ids[$c['username']] = (int)$c['id'];
            foreach (array_slice($res['competitors'], 0, 12) as $i => $c): ?>
            <a href="competitor-analysis?id=<?= (int)($ids[$c['username']] ?? 0) ?>">
                <span class="cpr-rank"><?= $i + 1 ?></span><strong><?= e($c['name']) ?></strong>
                <div class="cp-note">@<?= e($c['username']) ?> · <?= (int)$c['pins'] ?> relevant Pins<?= $c['avg_saves'] !== null ? ' · ' . number_format($c['avg_saves'], 1) . ' avg saves' : '' ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($res['pins']) cp_render_report($rep, ['hide_boards' => false]); ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
