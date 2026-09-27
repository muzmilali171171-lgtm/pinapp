<?php
/** Admin → Canva → User Designs: every design users saved; publish one to make it a template for all users. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'user-designs';
$pageTitle = 'Canva — User Designs';
$msg = null; $err = null;
$view = in_array($_GET['view'] ?? 'all', ['all', 'published'], true) ? ($_GET['view'] ?? 'all') : 'all';
$q = trim($_GET['q'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $act = $_POST['act'] ?? '';
        if ($act === 'publish') {
            $pdo->prepare("UPDATE user_designs SET is_published = 1, published_at = NOW(), template_category = ? WHERE id = ?")
                ->execute([mb_substr(trim($_POST['category'] ?? ''), 0, 100) ?: null, $id]);
            $msg = 'Published — every user can now start from this design.';
        } elseif ($act === 'unpublish') {
            $pdo->prepare("UPDATE user_designs SET is_published = 0 WHERE id = ?")->execute([$id]);
            $msg = 'Unpublished.';
        } elseif ($act === 'category') {
            $pdo->prepare("UPDATE user_designs SET template_category = ? WHERE id = ?")->execute([mb_substr(trim($_POST['category'] ?? ''), 0, 100) ?: null, $id]);
            $msg = 'Category saved.';
        } elseif ($act === 'delete') {
            $st = $pdo->prepare("SELECT thumb_path FROM user_designs WHERE id = ?");
            $st->execute([$id]);
            design_delete_thumb($st->fetchColumn() ?: null);
            $pdo->prepare("DELETE FROM user_designs WHERE id = ?")->execute([$id]);
            $msg = 'Design deleted.';
        }
    }
    $where = []; $args = [];
    if ($view === 'published') $where[] = 'd.is_published = 1';
    if ($q !== '') { $where[] = '(d.title LIKE ? OR u.email LIKE ? OR u.name LIKE ?)'; $args = ["%$q%", "%$q%", "%$q%"]; }
    $sql = "SELECT d.id, d.title, d.width, d.height, d.thumb_path, d.is_published, d.template_category, d.updated_at, u.name AS user_name, u.email
            FROM user_designs d JOIN users u ON u.id = d.user_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY d.updated_at DESC LIMIT 600";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    $counts = $pdo->query("SELECT COUNT(*) total, SUM(is_published) pub FROM user_designs")->fetch();
} catch (PDOException $e) {
    $err = 'The design tables are missing — run migrate.php once.';
    $rows = []; $counts = ['total' => 0, 'pub' => 0];
}

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.ud-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:14px; }
.ud-card { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
.ud-card.pub { border-color:#86efac; box-shadow:0 0 0 2px #dcfce7; }
.ud-thumb { background:#f3f4f6; aspect-ratio:2/3; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.ud-thumb img { width:100%; height:100%; object-fit:cover; }
.ud-body { padding:10px; font-size:13px; }
.ud-body strong { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ud-body .muted { font-size:12px; }
.ud-body form { display:flex; gap:4px; flex-wrap:wrap; margin-top:6px; }
.ud-body input[type=text] { flex:1; min-width:0; font-size:12px; padding:4px 6px; }
.ud-body button { font-size:12px; padding:4px 8px; }
.ud-tabs { display:flex; gap:8px; margin-bottom:12px; align-items:center; flex-wrap:wrap; }
.ud-tabs a { padding:6px 14px; border:1px solid #e5e7eb; border-radius:999px; text-decoration:none; color:#374151; font-size:13px; }
.ud-tabs a.on { background:#7c3aed; color:#fff; border-color:#7c3aed; }
</style>
<div class="page-header"><h1>Canva — User Designs</h1></div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
<p class="muted">Every design your users saved in the Custom Design editor. <strong>Publish</strong> a design to make it a template anyone can start from (Your Designs → Templates, and the editor's Templates tab). The original stays with its owner; other users get their own copy.</p>
<div class="ud-tabs">
    <a href="user-designs?view=all" class="<?= $view === 'all' ? 'on' : '' ?>">All designs (<?= (int)$counts['total'] ?>)</a>
    <a href="user-designs?view=published" class="<?= $view === 'published' ? 'on' : '' ?>">Published templates (<?= (int)$counts['pub'] ?>)</a>
    <form method="get" style="display:flex; gap:6px; margin-left:auto;">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or user…">
        <button type="submit" class="btn-secondary">Search</button>
    </form>
</div>
<?php if (!$rows): ?><div class="card"><p class="muted" style="margin:0;">No designs found.</p></div><?php endif; ?>
<div class="ud-grid">
<?php foreach ($rows as $d): ?>
    <div class="ud-card <?= $d['is_published'] ? 'pub' : '' ?>">
        <div class="ud-thumb" style="aspect-ratio:<?= (int)$d['width'] ?>/<?= (int)$d['height'] ?>;"><?php if ($d['thumb_path']): ?><img loading="lazy" src="../<?= e($d['thumb_path']) ?>" alt=""><?php else: ?><span class="muted">No preview</span><?php endif; ?></div>
        <div class="ud-body">
            <strong title="<?= e($d['title']) ?>"><?= e($d['title']) ?></strong>
            <div class="muted"><?= e($d['user_name']) ?> · <?= e($d['email']) ?></div>
            <div class="muted"><?= (int)$d['width'] ?> × <?= (int)$d['height'] ?> · <?= e(date('M j, Y', strtotime($d['updated_at']))) ?><?= $d['is_published'] ? ' · <b style="color:#166534">Published</b>' : '' ?></div>
            <form method="post">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <input type="text" name="category" value="<?= e($d['template_category'] ?? '') ?>" placeholder="Template category">
                <?php if ($d['is_published']): ?>
                    <button type="submit" name="act" value="category" class="btn-secondary">Save</button>
                    <button type="submit" name="act" value="unpublish" class="btn-secondary">Unpublish</button>
                <?php else: ?>
                    <button type="submit" name="act" value="publish" class="btn-primary">Publish</button>
                <?php endif; ?>
                <button type="submit" name="act" value="delete" class="btn-secondary" onclick="return confirm('Delete this design for its owner too?')">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
