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
$activeIds = array_filter(array_map('intval', json_decode($_POST['active_ids'] ?? '[]', true) ?: []));
$inactiveIds = array_filter(array_map('intval', json_decode($_POST['inactive_ids'] ?? '[]', true) ?: []));

// Only ever touches pages that belong to one of this user's own crawl sites.
$ownedSiteIds = $pdo->prepare("SELECT id FROM crawl_sites WHERE user_id = ?");
$ownedSiteIds->execute([$user['id']]);
$ownedSiteIds = $ownedSiteIds->fetchAll(PDO::FETCH_COLUMN);
if (empty($ownedSiteIds)) {
    echo json_encode(['ok' => false, 'error' => 'No sites found for this account.']);
    exit;
}
$placeholders = implode(',', array_fill(0, count($ownedSiteIds), '?'));

if (!empty($activeIds)) {
    $ph = implode(',', array_fill(0, count($activeIds), '?'));
    $pdo->prepare("UPDATE crawl_pages SET active = 1 WHERE id IN ($ph) AND crawl_site_id IN ($placeholders)")
        ->execute([...array_values($activeIds), ...$ownedSiteIds]);
}
if (!empty($inactiveIds)) {
    $ph = implode(',', array_fill(0, count($inactiveIds), '?'));
    $pdo->prepare("UPDATE crawl_pages SET active = 0 WHERE id IN ($ph) AND crawl_site_id IN ($placeholders)")
        ->execute([...array_values($inactiveIds), ...$ownedSiteIds]);
}

echo json_encode(['ok' => true]);
