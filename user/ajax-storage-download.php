<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
if (empty($ids)) { die('Nothing selected.'); }

$ph = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM storage_images WHERE id IN ($ph) AND user_id = ?");
$stmt->execute([...array_values($ids), $user['id']]);
$rows = $stmt->fetchAll();
if (empty($rows)) { die('No images found.'); }

// Single image: send it directly rather than zipping one file.
if (count($rows) === 1) {
    $row = $rows[0];
    $url = storage_image_url($row);
    if ($row['storage_provider'] === 'local') {
        $fullPath = __DIR__ . '/../' . $row['file_path'];
        if (is_file($fullPath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($row['filename'] ?: $row['file_path']) . '"');
            readfile($fullPath);
            exit;
        }
    }
    header('Location: ' . $url);
    exit;
}

$zipPath = tempnam(sys_get_temp_dir(), 'storage_') . '.zip';
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Bulk download needs the PHP "zip" extension, which isn\'t enabled on this server. Ask your host to enable it, or download images one at a time for now.');
}
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE);
foreach ($rows as $i => $row) {
    $name = ($i + 1) . '-' . ($row['filename'] ?: basename($row['file_path']));
    if ($row['storage_provider'] === 'local') {
        $fullPath = __DIR__ . '/../' . $row['file_path'];
        if (is_file($fullPath)) $zip->addFile($fullPath, $name);
    } else {
        $data = @file_get_contents(storage_image_url($row));
        if ($data !== false) $zip->addFromString($name, $data);
    }
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="storage-images.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
