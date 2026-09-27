<?php
/**
 * Custom Design editor (Canva-style) — shared helpers.
 * Tables: user_designs, design_uploads, design_elements (database/schema.sql).
 * Files:  uploads/designs/<user_id>/…  (user uploads + thumbnails), uploads/design-elements/… (admin elements).
 */

const DESIGN_MAX_UPLOAD_BYTES = 15 * 1024 * 1024;
const DESIGN_MAX_JSON_BYTES = 30 * 1024 * 1024; // multi-page designs

/** Element types shown as tabs in the editor's Elements panel, with suggested sub-categories. */
const DESIGN_ELEMENT_TYPES = [
    'shapes' => ['label' => 'Shapes', 'subs' => ['Lines', 'Basic Shapes', 'Polygons', 'Arrows', 'Stars', 'Flow Charts', 'Hearts', 'Speech Bubbles', 'Clouds', 'Banners', 'Teardrops', 'Cogs', 'Square Stars', 'Organic Shapes']],
    'graphics' => ['label' => 'Graphics', 'subs' => ['Food & Drink', 'Fruits & Veggies', 'Animals', 'Plants & Flowers', 'Travel & Places', 'Home & Living', 'Fashion & Beauty', 'Holidays & Party', 'Sports & Hobbies', 'Weather & Sky', 'Business & Office', 'Tech & Science', 'Badges & Labels', 'Doodles', 'Kids']],
    'emoji' => ['label' => 'Emoji', 'subs' => ['Smileys', 'Hands & People', 'Hearts', 'Animals & Nature', 'Food & Drink', 'Travel & Places', 'Activities', 'Objects', 'Symbols']],
    '3d' => ['label' => '3D', 'subs' => ['Faces', 'Hearts & Symbols', 'Hands', 'Objects', 'Food', 'Nature', 'Travel', '3D Shapes', '3D Icons']],
    'frames' => ['label' => 'Frames', 'subs' => ['Photo frames', 'Polaroid', 'Devices', 'Decorative', 'Borders']],
];

/** Adds design_elements.element_type (the Elements tab) once; older installs default to "graphics". */
function design_ensure_element_type(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_eltype_v1';
    if (is_file($flag)) return;
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'design_elements' AND column_name = 'element_type'")->fetchColumn();
        if ((int)$c === 0) {
            $pdo->exec("ALTER TABLE design_elements ADD COLUMN element_type VARCHAR(20) NOT NULL DEFAULT 'graphics' AFTER name, ADD KEY idx_de_type (element_type, category)");
        }
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* table missing — migrate.php creates it */ }
}

function design_user_dir(int $userId): string
{
    $dir = __DIR__ . '/../uploads/designs/' . $userId . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/** Saves a "data:image/…;base64,…" string to $destAbs. Returns true on success. */
function design_save_data_url(string $dataUrl, string $destAbs, int $maxBytes = 25 * 1024 * 1024): bool
{
    if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $dataUrl, $m)) return false;
    $bytes = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bytes === false || strlen($bytes) < 100 || strlen($bytes) > $maxBytes) return false;
    if (!@imagecreatefromstring($bytes)) return false;
    return file_put_contents($destAbs, $bytes) !== false;
}

/** Validates an uploaded image file; returns [ok, ext|error]. */
function design_check_upload(array $f): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'Upload failed.'];
    if ($f['size'] > DESIGN_MAX_UPLOAD_BYTES) return [false, 'File is too large (max 15 MB).'];
    $info = @getimagesize($f['tmp_name']);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext === 'svg') return [false, 'SVG files can\'t be uploaded here — use a PNG, JPG or WebP image.'];
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return [false, 'Please upload a JPG, PNG, WEBP or GIF image.'];
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    return [true, $map[$info[2]]];
}

/** Very small SVG sanitizer for admin element uploads: drops scripts, event handlers and external refs. */
function design_sanitize_svg(string $svg): ?string
{
    if (stripos($svg, '<svg') === false) return null;
    $svg = preg_replace('#<\?xml[^>]*>|<!DOCTYPE[^>]*>#i', '', $svg);
    $svg = preg_replace('#<(script|foreignObject|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $svg);
    $svg = preg_replace('#<(script|foreignObject|iframe|object|embed)\b[^>]*/?>#is', '', $svg);
    $svg = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg);
    $svg = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*(javascript:|https?:|//)[^"\']*\2#i', '', $svg);
    return trim($svg);
}

function design_row_for_user(PDO $pdo, int $id, int $userId): ?array
{
    $st = $pdo->prepare("SELECT * FROM user_designs WHERE id = ? AND user_id = ?");
    $st->execute([$id, $userId]);
    return $st->fetch() ?: null;
}

/** Deletes a design's thumbnail file (only inside uploads/designs/). */
function design_delete_thumb(?string $path): void
{
    if (!$path || strpos($path, 'uploads/designs/') !== 0 || strpos($path, '..') !== false) return;
    $abs = __DIR__ . '/../' . $path;
    if (is_file($abs)) @unlink($abs);
}

/* ===================== Share design (public link) ===================== */

/** Adds the share columns to user_designs once (migrate.php also adds them). */
function design_ensure_share_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_design_share_v1';
    if (is_file($flag)) return;
    try {
        $have = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'user_designs'")->fetchAll(PDO::FETCH_COLUMN);
        $have = array_map('strtolower', $have);
        $add = [
            'share_token' => 'ADD COLUMN share_token VARCHAR(32) DEFAULT NULL UNIQUE',
            'shared_at' => 'ADD COLUMN shared_at DATETIME DEFAULT NULL',
            'share_images' => 'ADD COLUMN share_images TEXT DEFAULT NULL',
            'copied_from' => 'ADD COLUMN copied_from INT DEFAULT NULL',
        ];
        $parts = [];
        foreach ($add as $col => $sql) if (!in_array($col, $have, true)) $parts[] = $sql;
        if ($parts) $pdo->exec('ALTER TABLE user_designs ' . implode(', ', $parts));
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* migrate.php adds the columns */ }
}

/** Public link of a shared design. */
function design_share_url(string $token): string
{
    return rtrim(APP_URL, '/') . '/design-share?t=' . rawurlencode($token);
}

/** A shared design by its token (null if not shared / unknown). */
function design_by_share_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{16,32}$/', $token)) return null;
    design_ensure_share_schema($pdo);
    $st = $pdo->prepare("SELECT d.*, u.name AS owner_name FROM user_designs d JOIN users u ON u.id = d.user_id WHERE d.share_token = ?");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

/** Deletes the preview images saved when a design was shared. */
function design_delete_share_images(?string $json): void
{
    foreach ((array)json_decode((string)$json, true) as $p) design_delete_thumb(is_string($p) ? $p : null);
}

/**
 * Someone opened a shared design in the editor: the owner gets the original, anyone else gets
 * their own copy (made once, then re-used) so the original is never changed. Returns the design id.
 */
function design_open_shared(PDO $pdo, array $row, int $userId): int
{
    if ((int)$row['user_id'] === $userId) return (int)$row['id'];
    $st = $pdo->prepare("SELECT id FROM user_designs WHERE user_id = ? AND copied_from = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId, (int)$row['id']]);
    if ($id = (int)$st->fetchColumn()) return $id;
    $pdo->prepare("INSERT INTO user_designs (user_id, title, width, height, design_json, copied_from) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, mb_substr($row['title'], 0, 255), (int)$row['width'], (int)$row['height'], $row['design_json'], (int)$row['id']]);
    $newId = (int)$pdo->lastInsertId();
    if ($row['thumb_path'] && is_file(__DIR__ . '/../' . $row['thumb_path'])) {
        design_user_dir($userId);
        $rel = 'uploads/designs/' . $userId . '/thumb_' . $newId . '_' . bin2hex(random_bytes(3)) . '.jpg';
        if (@copy(__DIR__ . '/../' . $row['thumb_path'], __DIR__ . '/../' . $rel)) {
            $pdo->prepare("UPDATE user_designs SET thumb_path = ? WHERE id = ?")->execute([$rel, $newId]);
        }
    }
    return $newId;
}
