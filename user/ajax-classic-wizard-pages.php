<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
$user = current_user($pdo);

$siteId = (int)($_POST['crawl_site_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE id = ? AND user_id = ?");
$stmt->execute([$siteId, $user['id']]);
$site = $stmt->fetch();
if (!$site) {
    echo json_encode(['ok' => false, 'error' => 'Website not found.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, url, meta_title, meta_description, keywords FROM crawl_pages WHERE crawl_site_id = ? ORDER BY url ASC");
$stmt->execute([$siteId]);
$pages = $stmt->fetchAll();

echo json_encode([
    'ok' => true,
    'site' => ['id' => (int)$site['id'], 'name' => $site['site_name'], 'url' => $site['site_url']],
    'pages' => array_map(fn($p) => [
        'id' => (int)$p['id'], 'url' => $p['url'],
        'title' => $p['meta_title'], 'description' => $p['meta_description'], 'keywords' => $p['keywords'],
    ], $pages),
]);
