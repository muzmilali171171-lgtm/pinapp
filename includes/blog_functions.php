<?php
/**
 * Blog feature — Admin -> Blog Post (Create New / All Posts / Category)
 * manages this, the public archive is /blog.php (also reachable at
 * /blog and /blog/{category-slug}), and single posts render through
 * /blog-post.php at the pretty URL /{category-slug}/{post-slug}
 * (see .htaccess for the rewrite rules).
 */

/* ===================== Slugs ===================== */

function blog_slugify(string $s): string
{
    $s = trim($s);
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower($s);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'post';
}

function blog_unique_category_slug(PDO $pdo, string $slug, ?int $excludeId = null): string
{
    $base = blog_slugify($slug);
    $try = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM blog_categories WHERE slug = ?" . ($excludeId ? " AND id != ?" : "");
        $stmt = $pdo->prepare($sql);
        $params = [$try];
        if ($excludeId) $params[] = $excludeId;
        $stmt->execute($params);
        if (!$stmt->fetch()) return $try;
        $try = $base . '-' . $i;
        $i++;
    }
}

function blog_unique_post_slug(PDO $pdo, string $slug, ?int $excludeId = null): string
{
    $base = blog_slugify($slug);
    $try = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM blog_posts WHERE slug = ?" . ($excludeId ? " AND id != ?" : "");
        $stmt = $pdo->prepare($sql);
        $params = [$try];
        if ($excludeId) $params[] = $excludeId;
        $stmt->execute($params);
        if (!$stmt->fetch()) return $try;
        $try = $base . '-' . $i;
        $i++;
    }
}

/* ===================== Categories ===================== */

function blog_get_categories(PDO $pdo, bool $withCounts = false): array
{
    if ($withCounts) {
        return $pdo->query(
            "SELECT c.*, COUNT(CASE WHEN p.status = 'published' THEN 1 END) AS post_count
             FROM blog_categories c
             LEFT JOIN blog_posts p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name ASC"
        )->fetchAll();
    }
    return $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();
}

function blog_get_category(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function blog_get_category_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** Create or update a category. Returns [id, error|null]. */
function blog_save_category(PDO $pdo, ?int $id, string $name, string $slugInput): array
{
    $name = mb_substr(trim($name), 0, 150);
    if ($name === '') return [0, 'Name is required.'];
    $slug = blog_unique_category_slug($pdo, $slugInput !== '' ? $slugInput : $name, $id);

    if ($id) {
        $pdo->prepare("UPDATE blog_categories SET name = ?, slug = ? WHERE id = ?")->execute([$name, $slug, $id]);
        return [$id, null];
    }
    $pdo->prepare("INSERT INTO blog_categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
    return [(int)$pdo->lastInsertId(), null];
}

/** Returns an error string if the category can't be deleted (still has posts), else null. */
function blog_delete_category(PDO $pdo, int $id): ?string
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE category_id = ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        return 'Move or delete the posts in this category first.';
    }
    $pdo->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$id]);
    return null;
}

/* ===================== Posts ===================== */

function blog_get_post(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id
         WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Look a post up by its pretty URL — category slug + post slug. */
function blog_get_post_by_slug(PDO $pdo, string $categorySlug, string $slug): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id
         WHERE c.slug = ? AND p.slug = ?"
    );
    $stmt->execute([$categorySlug, $slug]);
    return $stmt->fetch() ?: null;
}

/** Admin listing with search/filter/pagination. */
function blog_admin_list_posts(PDO $pdo, string $search = '', int $categoryId = 0, string $status = '', int $page = 1, int $perPage = 20): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "p.title LIKE ?";
        $params[] = '%' . $search . '%';
    }
    if ($categoryId > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $categoryId;
    }
    if (in_array($status, ['draft', 'published'], true)) {
        $where[] = "p.status = ?";
        $params[] = $status;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id
         $whereSql ORDER BY p.id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Public archive listing (published only), optionally filtered by category slug + search, paged for "Load More". */
function blog_public_list_posts(PDO $pdo, ?string $categorySlug, string $search, int $offset, int $limit): array
{
    $where = ["p.status = 'published'"];
    $params = [];
    if ($categorySlug) {
        $where[] = "c.slug = ?";
        $params[] = $categorySlug;
    }
    if ($search !== '') {
        $where[] = "(p.title LIKE ? OR p.subtitle LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = max(0, $offset);
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id
         $whereSql ORDER BY p.published_at DESC, p.id DESC LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

function blog_related_posts(PDO $pdo, int $postId, int $categoryId, int $limit = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id
         WHERE p.status = 'published' AND p.category_id = ? AND p.id != ?
         ORDER BY p.published_at DESC LIMIT $limit"
    );
    $stmt->execute([$categoryId, $postId]);
    return $stmt->fetchAll();
}

/** Create or update a post. Returns the post id. */
function blog_save_post(PDO $pdo, ?int $id, array $data, ?string $featureImagePath): int
{
    $title = mb_substr(trim($data['title'] ?? ''), 0, 255);
    $subtitle = trim($data['subtitle'] ?? '') ?: null;
    $metaTitle = trim($data['meta_title'] ?? '') ?: null;
    $metaDescription = trim($data['meta_description'] ?? '') ?: null;
    $slugInput = trim($data['slug'] ?? '') ?: $title;
    $slug = blog_unique_post_slug($pdo, $slugInput, $id);
    $categoryId = (int)($data['category_id'] ?? 0);
    $content = $data['content'] ?? '';
    $editorMode = ($data['editor_mode'] ?? 'ckeditor') === 'html' ? 'html' : 'ckeditor';
    $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $author = trim($data['author'] ?? '') ?: null;

    if ($id) {
        $existing = blog_get_post($pdo, $id);
        $publishedAt = $existing['published_at'] ?? null;
        if ($status === 'published' && !$publishedAt) $publishedAt = date('Y-m-d H:i:s');

        $fields = [
            'title' => $title, 'subtitle' => $subtitle, 'meta_title' => $metaTitle,
            'meta_description' => $metaDescription, 'slug' => $slug, 'category_id' => $categoryId,
            'content' => $content, 'editor_mode' => $editorMode, 'status' => $status,
            'author' => $author, 'published_at' => $publishedAt,
        ];
        if ($featureImagePath) $fields['feature_image'] = $featureImagePath;
        $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
        $pdo->prepare("UPDATE blog_posts SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
        return $id;
    }

    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare(
        "INSERT INTO blog_posts
        (title, subtitle, meta_title, meta_description, slug, category_id, feature_image, content, editor_mode, status, author, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $subtitle, $metaTitle, $metaDescription, $slug, $categoryId, $featureImagePath, $content, $editorMode, $status, $author, $publishedAt]);
    return (int)$pdo->lastInsertId();
}

function blog_delete_post(PDO $pdo, int $id): void
{
    $p = blog_get_post($pdo, $id);
    if ($p && !empty($p['feature_image'])) {
        $path = __DIR__ . '/../' . $p['feature_image'];
        if (is_file($path)) @unlink($path);
    }
    $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
}

function blog_increment_views(PDO $pdo, int $id): void
{
    $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
}

/** Handles the optional feature-image upload from the admin post form. */
function blog_handle_feature_image_upload(string $field, array &$errors = []): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Feature image upload failed.';
        return null;
    }
    $allowed = ['png' => 1, 'jpg' => 1, 'jpeg' => 1, 'webp' => 1, 'gif' => 1];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        $errors[] = 'Unsupported feature image type (use PNG, JPG, WEBP or GIF).';
        return null;
    }
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Feature image is too large (max 5MB).';
        return null;
    }
    $dir = __DIR__ . '/../uploads/blog';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'blog-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        $errors[] = 'Could not save the uploaded feature image.';
        return null;
    }
    return 'uploads/blog/' . $name;
}

/* ===================== Content helpers ===================== */

/** Adds id="..." anchors to every H2/H3 in the post HTML (if missing) so the
 *  table of contents can scroll to them, and returns the updated HTML. */
function blog_inject_heading_ids(string $html): string
{
    return preg_replace_callback('~<(h2|h3)([^>]*)>(.*?)</\1>~is', function ($m) {
        $attrs = $m[2];
        if (preg_match('~\bid\s*=~i', $attrs)) return $m[0];
        $text = trim(strip_tags($m[3]));
        $id = blog_slugify($text);
        return '<' . $m[1] . $attrs . ' id="' . $id . '">' . $m[3] . '</' . $m[1] . '>';
    }, $html) ?? $html;
}

/** Table of contents entries: [['level'=>2|3, 'id'=>, 'text'=>], ...]. Call
 *  AFTER blog_inject_heading_ids() so the ids line up with the rendered HTML. */
function blog_extract_toc(string $html): array
{
    $toc = [];
    if (preg_match_all('~<(h2|h3)[^>]*\bid="([^"]*)"[^>]*>(.*?)</\1>~is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $toc[] = [
                'level' => $m[1] === 'h2' ? 2 : 3,
                'id' => $m[2],
                'text' => trim(strip_tags($m[3])),
            ];
        }
    }
    return $toc;
}

function blog_reading_time(string $html): int
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int)ceil($words / 200));
}

function blog_excerpt(string $html, int $len = 160): string
{
    $text = trim(preg_replace('~\s+~', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '…';
}

/**
 * Seeds any posts from the given manifest file (an array file under
 * database/) that don't already exist (matched by slug) into the given
 * category. Safe to call on every install.php run and every migrate.php
 * run — already-seeded or admin-edited posts (any existing slug) are left
 * untouched. $contentDir is the folder under database/ holding each entry's
 * "file" (e.g. "seed-growth-guide" or "seed-compare").
 */
function blog_seed_default_posts(PDO $pdo, string $categorySlug, string $author, string $manifestFile = 'blog-seed-manifest.php', string $contentDir = 'seed-growth-guide'): void
{
    $manifestPath = __DIR__ . '/../database/' . $manifestFile;
    if (!is_file($manifestPath)) return;
    $manifest = require $manifestPath;

    $cat = blog_get_category_by_slug($pdo, $categorySlug);
    if (!$cat) return;

    $existsStmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
    $insertStmt = $pdo->prepare(
        "INSERT INTO blog_posts
        (title, subtitle, meta_title, meta_description, slug, category_id, feature_image, content, editor_mode, status, author, published_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 'html', 'published', ?, NOW())"
    );

    foreach ($manifest as $entry) {
        $existsStmt->execute([$entry['slug']]);
        if ($existsStmt->fetch()) continue;

        $contentPath = __DIR__ . '/../database/' . $contentDir . '/' . $entry['file'];
        if (!is_file($contentPath)) continue;

        $insertStmt->execute([
            $entry['title'],
            $entry['subtitle'] ?? null,
            $entry['meta_title'] ?? null,
            $entry['meta_description'] ?? null,
            $entry['slug'],
            (int)$cat['id'],
            file_get_contents($contentPath),
            $author,
        ]);
    }
}
