<?php
/**
 * AJAX backend for user/bulk-schedule.php — saves one uploaded image (either
 * from the "Upload Bulk Pin Images" multi-select, or a single row's
 * upload/replace click) and returns its path for the row to display.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'No image received.']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = mime_content_type($_FILES['image']['tmp_name']);
if (!isset($allowed[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'Unsupported image type. Please upload JPG, PNG, WEBP or GIF.']);
    exit;
}

$ext = $allowed[$mime];
$filename = 'pin_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destDir = __DIR__ . '/../uploads/pins/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

if (!move_uploaded_file($_FILES['image']['tmp_name'], $destDir . $filename)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save the uploaded image.']);
    exit;
}
// On many shared hosts, PHP runs as a different user than the webserver (suPHP/FastCGI),
// so a file just written by PHP can end up 0600 (owner-only) — the file exists on disk
// (visible in cPanel's File Manager) but the webserver gets a 403 trying to serve it,
// which is exactly why the image looked "uploaded" but never rendered / opened as an error
// when clicked directly. Force it world-readable so the webserver can always serve it.
@chmod($destDir . $filename, 0644);
@chmod($destDir, 0755);

$originalName = $_FILES['image']['name'] ?? $filename;

echo json_encode([
    'ok' => true,
    'path' => 'uploads/pins/' . $filename,
    'filename' => $originalName,
]);
