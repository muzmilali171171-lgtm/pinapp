<?php
/**
 * Admin → Articles Schedule → All Articles.
 * Every Auto Article, filterable by Published / Scheduled / Failed, with article stats, the stats of the
 * article's pins (count + Pinterest views / clicks / saves) and a link to the post.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'all-articles';
$pageTitle = 'All Articles';

$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'published', 'scheduled', 'failed'], true)) $status = 'all';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$pipeline = "'queued','drafting','drafted','imaging','ready','publishing','draft'";

$where = []; $args = [];
if ($status === 'published') $where[] = "a.status = 'published'";
if ($status === 'failed') $where[] = "a.status = 'failed'";
if ($status === 'scheduled') $where[] = "a.status IN ($pipeline)";
if ($q !== '') { $where[] = "(a.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$counts = $pdo->query("SELECT SUM(status = 'published'), SUM(status IN ($pipeline)), SUM(status = 'failed'), COUNT(*) FROM articles")->fetch(PDO::FETCH_NUM);
[$cPub, $cSched, $cFail, $cAll] = array_map('intval', $counts);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM articles a JOIN users u ON u.id = a.user_id $whereSql");
$cnt->execute($args);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$st = $pdo->prepare("SELECT a.id, a.title, a.status, a.scheduled_for, a.published_at, a.wp_post_url, a.last_error, a.created_at,
        CHAR_LENGTH(a.content) AS content_len, a.content, a.featured_image_path,
        u.name AS user_name, u.email, w.site_name, w.site_url
    FROM articles a JOIN users u ON u.id = a.user_id LEFT JOIN websites w ON w.id = a.website_id
    $whereSql ORDER BY COALESCE(a.published_at, a.scheduled_for, a.created_at) DESC, a.id DESC
    LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$st->execute($args);
$rows = $st->fetchAll();

// pin stats for the listed articles
$pinStats = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ps = $pdo->prepare("SELECT sp.source_article_id AS aid, COUNT(*) AS total, SUM(sp.status = 'published') AS pub, SUM(sp.status IN ('pending','processing')) AS sched,
            SUM(sp.status = 'failed') AS fail,
            SUM(COALESCE(pp.imp_life, pp.imp_90, 0)) AS views, SUM(COALESCE(pp.outbound_life, pp.outbound_90, 0)) AS clicks, SUM(COALESCE(pp.saves_life, pp.saves_90, 0)) AS saves
        FROM scheduled_pins sp
        LEFT JOIN pa_pins pp ON pp.pinterest_account_id = sp.pinterest_account_id AND pp.pin_id = sp.pinterest_pin_id
        WHERE sp.source_article_id IN ($in) GROUP BY sp.source_article_id");
    $ps->execute($ids);
    foreach ($ps->fetchAll() as $r) $pinStats[(int)$r['aid']] = $r;
}
$stepLabel = ['queued' => 'Scheduled', 'drafting' => 'Writing', 'drafted' => 'Writing (in parts)', 'draft' => 'Writing', 'imaging' => 'Making images', 'ready' => 'Ready', 'publishing' => 'Publishing', 'published' => 'Published', 'failed' => 'Failed'];
$qs = fn(array $o) => '?' . http_build_query(array_merge(['status' => $status, 'q' => $q], $o));

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.aa-pills { display:flex; gap:6px; flex-wrap:wrap; }
.aa-pills a { padding:6px 13px; border:1px solid #d1d5db; border-radius:999px; text-decoration:none; color:#374151; font-size:13px; font-weight:600; background:#fff; }
.aa-pills a.on { background:#7c3aed; border-color:#7c3aed; color:#fff; }
.aa-small { font-size:12px; color:#6b7280; }
.aa-pager { display:flex; gap:6px; justify-content:center; margin-top:14px; }
.aa-pager a, .aa-pager span { padding:6px 11px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151; }
.aa-pager span { background:#7c3aed; color:#fff; border-color:#7c3aed; }
</style>
<div class="page-header"><h1>All Articles</h1></div>
<div class="stat-grid">
    <div class="stat-card"><div class="num"><?= number_format($cAll) ?></div><div class="label">All articles</div></div>
    <div class="stat-card"><div class="num"><?= number_format($cPub) ?></div><div class="label">Published</div></div>
    <div class="stat-card"><div class="num"><?= number_format($cSched) ?></div><div class="label">Scheduled</div></div>
    <div class="stat-card"><div class="num"><?= number_format($cFail) ?></div><div class="label">Failed</div></div>
</div>
<div class="card">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <div class="aa-pills">
            <?php foreach (['all' => 'All', 'published' => 'Published', 'scheduled' => 'Scheduled', 'failed' => 'Failed'] as $k => $l): ?>
                <a href="<?= e($qs(['status' => $k, 'page' => 1])) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($l) ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search title or user" style="margin-left:auto;">
        <button type="submit" class="btn-primary">Search</button>
    </form>
</div>
<div class="card">
    <?php if (!$rows): ?><div class="empty-state">No articles here.</div><?php else: ?>
    <div style="overflow-x:auto;"><table>
        <tr><th>ID</th><th>User</th><th>Article title</th><th>Published / scheduled</th><th>Stats</th><th>Pin stats</th><th>Post</th></tr>
        <?php foreach ($rows as $a):
            $words = $a['content'] ? str_word_count(strip_tags($a['content'])) : 0;
            $imgs = $a['content'] ? substr_count(strtolower($a['content']), '<img') : 0;
            $p = $pinStats[(int)$a['id']] ?? null;
            $date = $a['status'] === 'published' ? $a['published_at'] : ($a['scheduled_for'] ? $a['scheduled_for'] . ' 00:00:00' : null);
        ?>
        <tr>
            <td>#<?= (int)$a['id'] ?></td>
            <td><?= e($a['user_name'] ?: $a['email']) ?></td>
            <td style="max-width:320px;"><strong><?= e($a['title'] ?: '(title pending)') ?></strong><br><span class="aa-small">🌐 <?= e($a['site_name'] ?: ($a['site_url'] ?: '—')) ?></span></td>
            <td style="white-space:nowrap;">
                <span class="badge badge-<?= e($a['status']) ?>"><?= e($stepLabel[$a['status']] ?? $a['status']) ?></span><br>
                <span class="aa-small"><?= $date ? e($a['status'] === 'published' ? format_datetime($date) : date('d M Y', strtotime($date))) : '—' ?></span>
            </td>
            <td class="aa-small">
                <?= number_format($words) ?> words · <?= (int)$imgs + (!empty($a['featured_image_path']) ? 1 : 0) ?> images
                <?php if ($a['status'] === 'failed' && $a['last_error']): ?><br><span style="color:#b91c1c;" title="<?= e($a['last_error']) ?>"><?= e(mb_substr($a['last_error'], 0, 90)) ?>…</span><?php endif; ?>
            </td>
            <td class="aa-small">
                <?php if ($p): ?>
                    📌 <?= (int)$p['total'] ?> pins (<?= (int)$p['pub'] ?> published<?= (int)$p['sched'] ? ', ' . (int)$p['sched'] . ' scheduled' : '' ?><?= (int)$p['fail'] ? ', ' . (int)$p['fail'] . ' failed' : '' ?>)<br>
                    👁 <?= number_format((int)$p['views']) ?> · 🔗 <?= number_format((int)$p['clicks']) ?> · 💾 <?= number_format((int)$p['saves']) ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?php if ($a['wp_post_url']): ?><a href="<?= e($a['wp_post_url']) ?>" target="_blank" rel="noopener" class="btn-secondary btn-small">View post ↗</a><?php else: ?><span class="aa-small">—</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php if ($pages > 1): ?><div class="aa-pager">
        <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?>
            <?php if ($i === $page): ?><span><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
    </div><?php endif; ?>
    <p class="aa-small" style="margin-top:10px;">Pin views / clicks / saves come from each user's Pinterest Analytics sync (lifetime where available).</p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
