<?php
/**
 * Serves an image whose hosting copy was removed after it was moved to external storage
 * (Admin → Storage Settings → "Remove hosting copy after N days"). .htaccess sends any
 * missing file under uploads/pins|articles|blog|storage|designs here; we redirect to the
 * external copy, or show the normal 404 if we don't know the file.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$rel = ltrim((string)($_GET['p'] ?? ''), '/');
if ($rel === '' || strpos($rel, '..') !== false || !ext_category_for($rel)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}
$row = ext_get_row($pdo, $rel);
if ($row && $row['status'] === 'uploaded' && !empty($row['public_url'])) {
    header('Cache-Control: public, max-age=86400');
    header('Location: ' . $row['public_url'], true, 302);
    exit;
}
http_response_code(404);
include __DIR__ . '/404.php';
