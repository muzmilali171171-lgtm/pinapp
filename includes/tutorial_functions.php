<?php
/**
 * Tutorials feature — Admin -> Tutorials manages the list, the public
 * /tutorials.php page displays it, and the user panel (topbar button +
 * sidebar link) points at that public page.
 */

/** Pull the 11-char YouTube video ID out of any common YouTube URL shape. */
function tutorials_extract_youtube_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') return null;
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) {
        return $m[1];
    }
    // Bare video ID pasted directly.
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
        return $url;
    }
    return null;
}

function tutorials_watch_url(string $videoId): string
{
    return 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
}

function tutorials_embed_url(string $videoId): string
{
    return 'https://www.youtube.com/embed/' . rawurlencode($videoId) . '?autoplay=1&rel=0';
}

/** Absolute-ish (site-root-relative) URL for a tutorial's thumbnail: a custom
 *  upload if one was set, else the video's own YouTube thumbnail, else null. */
function tutorials_thumbnail_url(array $t): ?string
{
    if (!empty($t['thumbnail_path'])) {
        return $t['thumbnail_path'];
    }
    if (!empty($t['video_id'])) {
        return 'https://img.youtube.com/vi/' . rawurlencode($t['video_id']) . '/hqdefault.jpg';
    }
    return null;
}

/** Handle the optional thumbnail upload from the admin form. Mirrors
 *  footer_handle_upload()/seo_handle_upload() but keeps its own folder. */
function tutorials_handle_thumbnail_upload(string $field, array &$error = []): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error[] = "Upload failed for $field.";
        return null;
    }
    $allowed = ['png' => 1, 'jpg' => 1, 'jpeg' => 1, 'webp' => 1, 'gif' => 1];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        $error[] = "Unsupported thumbnail type (use PNG, JPG, WEBP or GIF).";
        return null;
    }
    if ($_FILES[$field]['size'] > 3 * 1024 * 1024) {
        $error[] = "Thumbnail is too large (max 3MB).";
        return null;
    }
    $dir = __DIR__ . '/../uploads/tutorials';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'tutorial-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        $error[] = "Could not save the uploaded thumbnail.";
        return null;
    }
    return 'uploads/tutorials/' . $name;
}

/** All tutorials, featured one(s) first, then by sort_order/id.
 *  Pass $onlyActive = true for the public page. */
function get_all_tutorials(PDO $pdo, bool $onlyActive = false): array
{
    $sql = "SELECT * FROM tutorials";
    if ($onlyActive) $sql .= " WHERE status = 'active'";
    $sql .= " ORDER BY is_featured DESC, sort_order ASC, id ASC";
    return $pdo->query($sql)->fetchAll();
}

function get_tutorial(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The single tutorial currently marked as the main/"Quick Start" video, if any. */
function get_featured_tutorial(PDO $pdo, bool $onlyActive = true): ?array
{
    $sql = "SELECT * FROM tutorials WHERE is_featured = 1";
    if ($onlyActive) $sql .= " AND status = 'active'";
    $sql .= " ORDER BY id ASC LIMIT 1";
    return $pdo->query($sql)->fetch() ?: null;
}

/** Create or update a tutorial. Only one tutorial can be featured at a time,
 *  so marking this one featured un-marks every other row. Returns the row id. */
function save_tutorial(PDO $pdo, ?int $id, array $data, ?string $thumbnailPath): int
{
    $title = mb_substr(trim($data['title'] ?? ''), 0, 255);
    $videoUrl = mb_substr(trim($data['video_url'] ?? ''), 0, 500);
    $videoId = tutorials_extract_youtube_id($videoUrl);
    $duration = mb_substr(trim($data['duration'] ?? ''), 0, 20);
    $description = trim($data['description'] ?? '') ?: null;
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($isFeatured) {
        $pdo->exec("UPDATE tutorials SET is_featured = 0");
    }

    if ($id) {
        $fields = [
            'title' => $title,
            'video_url' => $videoUrl,
            'video_id' => $videoId,
            'duration' => $duration,
            'description' => $description,
            'is_featured' => $isFeatured,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
        if ($thumbnailPath) $fields['thumbnail_path'] = $thumbnailPath;
        $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
        $pdo->prepare("UPDATE tutorials SET $set WHERE id = ?")
            ->execute([...array_values($fields), $id]);
        return $id;
    }

    $stmt = $pdo->prepare("INSERT INTO tutorials
        (title, video_url, video_id, thumbnail_path, duration, description, is_featured, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $videoUrl, $videoId, $thumbnailPath, $duration, $description, $isFeatured, $sortOrder, $status]);
    return (int)$pdo->lastInsertId();
}

function delete_tutorial(PDO $pdo, int $id): void
{
    $t = get_tutorial($pdo, $id);
    if ($t && !empty($t['thumbnail_path'])) {
        $path = __DIR__ . '/../' . $t['thumbnail_path'];
        if (is_file($path)) @unlink($path);
    }
    $pdo->prepare("DELETE FROM tutorials WHERE id = ?")->execute([$id]);
}

function increment_tutorial_views(PDO $pdo, int $id): void
{
    $pdo->prepare("UPDATE tutorials SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
}
