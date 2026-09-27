<?php
/**
 * Admin → AI Article Data → Ideas Articles / Recipes & Food Articles.
 * Reference articles by category + sub category. Auto Article reads the closest ones before writing.
 *   ?type=ideas | recipe
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_article_data_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$type = ($_GET['type'] ?? 'ideas') === 'recipe' ? 'recipe' : 'ideas';
$activePage = $type === 'recipe' ? 'aad-recipe' : 'aad-ideas';
$label = $type === 'recipe' ? 'Recipes & Food Articles' : 'Ideas Articles';
$pageTitle = 'AI Article Data — ' . $label;
aad_ensure_schema($pdo);
$msg = null; $err = null;

/** Finds or creates a category (parent_id null) or sub category. */
$ensureCat = function (string $name, ?int $parentId) use ($pdo, $type): ?int {
    $name = mb_substr(trim(preg_replace('/\s+/', ' ', $name)), 0, 150);
    if ($name === '') return null;
    $st = $pdo->prepare("SELECT id FROM ai_article_categories WHERE type = ? AND parent_id <=> ? AND name = ?");
    $st->execute([$type, $parentId, $name]);
    if ($id = $st->fetchColumn()) return (int)$id;
    $pdo->prepare("INSERT INTO ai_article_categories (type, parent_id, name) VALUES (?, ?, ?)")->execute([$type, $parentId, $name]);
    return (int)$pdo->lastInsertId();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'add_category') {
            $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
            $id = $ensureCat((string)($_POST['name'] ?? ''), $parent);
            $msg = $id ? ($parent ? 'Sub category added.' : 'Category added.') : 'Please type a name.';
        } elseif ($act === 'delete_category') {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE ai_reference_articles SET subcategory_id = NULL WHERE subcategory_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE ai_reference_articles SET category_id = NULL WHERE category_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM ai_article_categories WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM ai_article_categories WHERE id = ? AND type = ?")->execute([$id, $type]);
            $msg = 'Category deleted (its articles were kept, without a category).';
        } elseif ($act === 'add_articles') {
            $catId = (int)($_POST['category_id'] ?? 0);
            if (($_POST['category_id'] ?? '') === '__new__') $catId = (int)$ensureCat((string)($_POST['new_category'] ?? ''), null);
            $subId = (int)($_POST['subcategory_id'] ?? 0) ?: null;
            if (($_POST['subcategory_id'] ?? '') === '__new__' && $catId) $subId = $ensureCat((string)($_POST['new_subcategory'] ?? ''), $catId);
            if (!$catId) throw new RuntimeException('Please choose or create a category.');
            $titles = (array)($_POST['title'] ?? []);
            $contents = (array)($_POST['content'] ?? []);
            $ins = $pdo->prepare("INSERT INTO ai_reference_articles (type, category_id, subcategory_id, title, content, word_count) VALUES (?, ?, ?, ?, ?, ?)");
            $n = 0; $skipped = 0;
            foreach ($titles as $i => $t) {
                $t = mb_substr(trim((string)$t), 0, 500);
                $c = trim((string)($contents[$i] ?? ''));
                if ($t === '' && $c === '') continue;
                if ($t === '' || mb_strlen($c) < 200) { $skipped++; continue; }
                $ins->execute([$type, $catId, $subId, $t, $c, aad_word_count($c)]);
                $n++;
            }
            if (!$n) throw new RuntimeException('Nothing added — each article needs a title and its content (at least 200 characters).');
            $msg = "$n article(s) added." . ($skipped ? " $skipped skipped (missing title or content too short)." : '');
        } elseif ($act === 'delete_article') {
            $pdo->prepare("DELETE FROM ai_reference_articles WHERE id = ? AND type = ?")->execute([(int)$_POST['id'], $type]);
            $msg = 'Article deleted.';
        } elseif ($act === 'move_article') {
            $pdo->prepare("UPDATE ai_reference_articles SET category_id = ?, subcategory_id = ? WHERE id = ? AND type = ?")
                ->execute([(int)$_POST['category_id'] ?: null, (int)($_POST['subcategory_id'] ?? 0) ?: null, (int)$_POST['id'], $type]);
            $msg = 'Article updated.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$cats = aad_categories($pdo, $type);
$fCat = (int)($_GET['cat'] ?? 0);
$q = trim($_GET['q'] ?? '');
$where = ['r.type = ?']; $args = [$type];
if ($fCat) { $where[] = '(r.category_id = ? OR r.subcategory_id = ?)'; $args[] = $fCat; $args[] = $fCat; }
if ($q !== '') { $where[] = 'r.title LIKE ?'; $args[] = "%$q%"; }
$st = $pdo->prepare("SELECT r.id, r.title, r.word_count, r.created_at, r.category_id, r.subcategory_id, LEFT(r.content, 400) AS preview,
        c.name AS cat, s.name AS sub
    FROM ai_reference_articles r
    LEFT JOIN ai_article_categories c ON c.id = r.category_id
    LEFT JOIN ai_article_categories s ON s.id = r.subcategory_id
    WHERE " . implode(' AND ', $where) . " ORDER BY r.id DESC LIMIT 300");
$st->execute($args);
$articles = $st->fetchAll();
$total = (int)$pdo->query("SELECT COUNT(*) FROM ai_reference_articles WHERE type = " . $pdo->quote($type))->fetchColumn();
$subsJs = [];
foreach ($cats as $c) $subsJs[$c['id']] = array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $c['subs']);

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.aad-art { border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:12px; background:#fafafa; position:relative; }
.aad-art textarea { width:100%; min-height:180px; font-family:inherit; }
.aad-art .aad-remove { position:absolute; top:8px; right:8px; }
.aad-cats { display:flex; flex-wrap:wrap; gap:6px; }
.aad-cats a { padding:4px 11px; border:1px solid #e5e7eb; border-radius:999px; text-decoration:none; font-size:12.5px; color:#374151; background:#fff; }
.aad-cats a.on { background:#7c3aed; color:#fff; border-color:#7c3aed; }
.aad-row-preview { font-size:12px; color:#6b7280; max-height:36px; overflow:hidden; }
</style>

<div class="page-header"><h1>AI Article Data — <?= e($label) ?></h1></div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<div class="card">
    <p class="muted" style="margin-top:0;">Reference articles teach the AI what a great <?= $type === 'recipe' ? 'recipe / food' : 'ideas' ?> article in each niche looks like.
    Before Auto Article writes a new one, it finds the closest references (by title and category), studies their structure, depth and tone, and then writes an
    <strong>original</strong> article — it never copies their sentences. Tip: add articles you have the right to use (your own posts, or articles written for you).</p>
</div>

<div class="card">
    <h2>Add articles</h2>
    <form method="post" id="aadForm">
        <input type="hidden" name="act" value="add_articles">
        <div class="two-col">
            <div class="form-row">
                <label>Select <?= $type === 'recipe' ? 'recipe / food category' : 'category' ?></label>
                <select name="category_id" id="aadCat" required>
                    <option value="">— choose —</option>
                    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    <option value="__new__">➕ Add new category…</option>
                </select>
                <input type="text" name="new_category" id="aadNewCat" placeholder="New category name" maxlength="150" style="display:none; margin-top:6px;">
            </div>
            <div class="form-row">
                <label>Sub category <span class="muted">(optional)</span></label>
                <select name="subcategory_id" id="aadSub"><option value="">— none —</option><option value="__new__">➕ Add new sub category…</option></select>
                <input type="text" name="new_subcategory" id="aadNewSub" placeholder="New sub category name" maxlength="150" style="display:none; margin-top:6px;">
            </div>
        </div>
        <div id="aadArticles">
            <div class="aad-art">
                <div class="form-row"><label>Title</label><input type="text" name="title[]" maxlength="500" placeholder="<?= $type === 'recipe' ? 'e.g. Creamy Garlic Prawn Pasta' : 'e.g. 34 Super Cute Short Hairstyles for Women' ?>"></div>
                <div class="form-row"><label><?= $type === 'recipe' ? 'Recipe content' : 'Article content' ?></label><textarea name="content[]" placeholder="Paste the full article (text or HTML)…"></textarea></div>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="btn-secondary" id="aadMore">＋ Add another article</button>
            <button type="submit" class="btn-primary">⬆ Add now</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Categories (<?= count($cats) ?>)</h2>
    <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px;">
        <input type="hidden" name="act" value="add_category">
        <div class="form-row" style="margin:0;"><label>New category / sub category</label><input type="text" name="name" maxlength="150" placeholder="Name" required></div>
        <div class="form-row" style="margin:0;"><label>Inside</label>
            <select name="parent_id"><option value="0">— top-level category —</option>
                <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>">Sub category of: <?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <button type="submit" class="btn-secondary">＋ Create</button>
    </form>
    <div class="aad-cats">
        <a href="?type=<?= e($type) ?>" class="<?= $fCat ? '' : 'on' ?>">All (<?= $total ?>)</a>
        <?php foreach ($cats as $c): ?>
            <a href="?type=<?= e($type) ?>&amp;cat=<?= (int)$c['id'] ?>" class="<?= $fCat === (int)$c['id'] ? 'on' : '' ?>"><?= e($c['name']) ?> (<?= (int)$c['articles'] ?>)</a>
            <?php foreach ($c['subs'] as $s): ?>
                <a href="?type=<?= e($type) ?>&amp;cat=<?= (int)$s['id'] ?>" class="<?= $fCat === (int)$s['id'] ? 'on' : '' ?>" style="font-size:11.5px;">↳ <?= e($s['name']) ?> (<?= (int)$s['articles'] ?>)</a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($fCat): ?>
    <form method="post" style="margin-top:10px;" onsubmit="return confirm('Delete this category (and its sub categories)? Articles are kept.');">
        <input type="hidden" name="act" value="delete_category"><input type="hidden" name="id" value="<?= $fCat ?>">
        <button type="submit" class="btn-secondary btn-small">🗑 Delete this category</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
        <h2 style="margin:0;">Added articles (<?= count($articles) ?><?= $fCat || $q ? ' in this view' : '' ?>)</h2>
        <form method="get" style="display:flex; gap:6px;"><input type="hidden" name="type" value="<?= e($type) ?>"><?php if ($fCat): ?><input type="hidden" name="cat" value="<?= $fCat ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search titles"><button type="submit" class="btn-secondary">Search</button></form>
    </div>
    <?php if (!$articles): ?><div class="empty-state">No articles yet — add some above.</div><?php else: ?>
    <div style="overflow-x:auto;"><table>
        <tr><th>ID</th><th>Title</th><th>Category</th><th>Words</th><th>Added</th><th></th></tr>
        <?php foreach ($articles as $a): ?>
        <tr>
            <td>#<?= (int)$a['id'] ?></td>
            <td style="max-width:380px;"><strong><?= e($a['title']) ?></strong><div class="aad-row-preview"><?= e(mb_substr(aad_plain($a['preview']), 0, 180)) ?>…</div></td>
            <td><?= e($a['cat'] ?: '—') ?><?= $a['sub'] ? '<br><span class="muted" style="font-size:12px;">↳ ' . e($a['sub']) . '</span>' : '' ?></td>
            <td><?= number_format((int)$a['word_count']) ?></td>
            <td style="white-space:nowrap;"><?= e(format_datetime($a['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <form method="post" onsubmit="return confirm('Delete this article?');" style="display:inline;">
                    <input type="hidden" name="act" value="delete_article"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<script>
(function () {
    const SUBS = <?= json_encode($subsJs, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    const cat = document.getElementById('aadCat'), sub = document.getElementById('aadSub');
    const newCat = document.getElementById('aadNewCat'), newSub = document.getElementById('aadNewSub');
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    function fillSubs() {
        const list = SUBS[cat.value] || [];
        sub.innerHTML = '<option value="">— none —</option>' + list.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('')
            + '<option value="__new__">➕ Add new sub category…</option>';
        newCat.style.display = cat.value === '__new__' ? 'block' : 'none';
        newCat.required = cat.value === '__new__';
        toggleSub();
    }
    function toggleSub() { newSub.style.display = sub.value === '__new__' ? 'block' : 'none'; newSub.required = sub.value === '__new__'; }
    cat.addEventListener('change', fillSubs);
    sub.addEventListener('change', toggleSub);
    document.getElementById('aadMore').addEventListener('click', () => {
        const box = document.createElement('div');
        box.className = 'aad-art';
        box.innerHTML = '<button type="button" class="btn-secondary btn-small aad-remove">✕</button>'
            + '<div class="form-row"><label>Title</label><input type="text" name="title[]" maxlength="500"></div>'
            + '<div class="form-row"><label>Content</label><textarea name="content[]" placeholder="Paste the full article (text or HTML)…"></textarea></div>';
        document.getElementById('aadArticles').appendChild(box);
        box.querySelector('input').focus();
    });
    document.getElementById('aadArticles').addEventListener('click', (e) => { if (e.target.closest('.aad-remove')) e.target.closest('.aad-art').remove(); });
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
