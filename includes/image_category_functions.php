<?php
/**
 * Image categories (Admin → Image Categories). A two-level tree — main category → subcategories —
 * that users pick in every pin-image form ("Select category"). The chosen category tells the
 * image step what the pin is about ("Beauty › Nail Art"), so the AI photo matches the search
 * intent of that niche instead of guessing from the title alone.
 */

function image_category_slug(string $name): string
{
    $s = strtolower(trim($name));
    $s = str_replace('&', 'and', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'category';
}

/** Adds every default category/subcategory that doesn't exist yet. Returns how many rows were added. */
function image_categories_seed_defaults(PDO $pdo): int
{
    $file = __DIR__ . '/../database/seed-image-categories.txt';
    if (!is_file($file)) return 0;
    $added = 0;
    $findMain = $pdo->prepare("SELECT id FROM image_categories WHERE parent_id IS NULL AND name = ?");
    $findSub = $pdo->prepare("SELECT id FROM image_categories WHERE parent_id = ? AND name = ?");
    $insert = $pdo->prepare("INSERT INTO image_categories (parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?)");
    $mainOrder = 0;
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, ':') === false) continue;
        [$main, $subs] = array_map('trim', explode(':', $line, 2));
        if ($main === '') continue;
        $mainOrder++;
        $findMain->execute([$main]);
        $mainId = (int)$findMain->fetchColumn();
        if (!$mainId) {
            $insert->execute([null, $main, image_category_slug($main), $mainOrder]);
            $mainId = (int)$pdo->lastInsertId();
            $added++;
        }
        $subOrder = 0;
        foreach (array_unique(array_filter(array_map('trim', explode('|', $subs)))) as $sub) {
            $subOrder++;
            $findSub->execute([$mainId, $sub]);
            if ($findSub->fetchColumn()) continue;
            $insert->execute([$mainId, $sub, image_category_slug($sub), $subOrder]);
            $added++;
        }
    }
    return $added;
}

/** [ ['id','name','subs' => [['id','name'], ...]], ... ] in display order. */
function image_categories_tree(PDO $pdo): array
{
    try {
        $rows = $pdo->query("SELECT id, parent_id, name FROM image_categories ORDER BY sort_order, name")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $mains = [];
    $subs = [];
    foreach ($rows as $r) {
        if ($r['parent_id'] === null) $mains[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'subs' => []];
        else $subs[(int)$r['parent_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    }
    foreach ($mains as &$m) $m['subs'] = $subs[$m['id']] ?? [];
    return $mains;
}

/** "Main › Sub" (or just "Main") for a category id, or '' when unknown / 0. */
function image_category_path(PDO $pdo, ?int $id): string
{
    if (!$id) return '';
    try {
        $stmt = $pdo->prepare("SELECT c.name, p.name AS parent_name FROM image_categories c
            LEFT JOIN image_categories p ON p.id = c.parent_id WHERE c.id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
    } catch (Throwable $e) {
        return '';
    }
    if (!$r) return '';
    return $r['parent_name'] ? $r['parent_name'] . ' › ' . $r['name'] : $r['name'];
}

function image_category_add(PDO $pdo, string $name, ?int $parentId): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '' || mb_strlen($name) > 120) return ['ok' => false, 'error' => 'Please enter a name (up to 120 characters).'];
    if ($parentId) {
        $p = $pdo->prepare("SELECT id FROM image_categories WHERE id = ? AND parent_id IS NULL");
        $p->execute([$parentId]);
        if (!$p->fetchColumn()) return ['ok' => false, 'error' => 'Please choose a main category.'];
        $dup = $pdo->prepare("SELECT id FROM image_categories WHERE parent_id = ? AND name = ?");
        $dup->execute([$parentId, $name]);
    } else {
        $dup = $pdo->prepare("SELECT id FROM image_categories WHERE parent_id IS NULL AND name = ?");
        $dup->execute([$name]);
    }
    if ($dup->fetchColumn()) return ['ok' => false, 'error' => "\"$name\" already exists there."];
    $order = $pdo->prepare($parentId
        ? "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM image_categories WHERE parent_id = ?"
        : "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM image_categories WHERE parent_id IS NULL");
    $order->execute($parentId ? [$parentId] : []);
    $pdo->prepare("INSERT INTO image_categories (parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?)")
        ->execute([$parentId ?: null, $name, image_category_slug($name), (int)$order->fetchColumn()]);
    return ['ok' => true, 'error' => null, 'id' => (int)$pdo->lastInsertId()];
}

function image_category_rename(PDO $pdo, int $id, string $name): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '' || mb_strlen($name) > 120) return ['ok' => false, 'error' => 'Please enter a name (up to 120 characters).'];
    $pdo->prepare("UPDATE image_categories SET name = ?, slug = ? WHERE id = ?")->execute([$name, image_category_slug($name), $id]);
    return ['ok' => true, 'error' => null];
}

/** Deletes a category; deleting a main category deletes its subcategories too. */
function image_category_delete(PDO $pdo, int $id): void
{
    $pdo->prepare("DELETE FROM image_categories WHERE parent_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM image_categories WHERE id = ?")->execute([$id]);
}
