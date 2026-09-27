<?php
/**
 * Shopify product / blog viewer backend.
 *   action=sync    - pull the store's products or blog posts into the selectable list
 *   action=select  - save which items are ticked (active) for Auto Website to Daily Pin
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(150);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Please reload the page.']);
    exit;
}

$user = current_user($pdo);
$site = get_user_website($pdo, (int)($_POST['website_id'] ?? 0), (int)$user['id']);
if (!$site || $site['platform'] !== 'shopify' || website_connection_state($site) !== 'connected') {
    echo json_encode(['ok' => false, 'error' => 'This store is not connected. Reconnect it on the Shopify Stores page.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'sync') {
    $type = ($_POST['type'] ?? '') === 'blog' ? 'blog' : 'product';
    $r = shopify_sync_items($pdo, $site, $type);
    log_event($pdo, 'system', "Shopify {$type} sync for {$site['external_id']}: " . ($r['ok'] ? "{$r['total']} found, {$r['added']} new" : $r['error']), (int)$user['id']);
    echo json_encode($r);
    exit;
}

if ($action === 'select') {
    $crawlSite = website_get_crawl_site($pdo, $site);
    $active = array_values(array_filter(array_map('intval', json_decode($_POST['active_ids'] ?? '[]', true) ?: [])));
    $inactive = array_values(array_filter(array_map('intval', json_decode($_POST['inactive_ids'] ?? '[]', true) ?: [])));

    // Only ever touches pages of this store's own crawl site.
    foreach ([[1, $active], [0, $inactive]] as [$flag, $ids]) {
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $pdo->prepare("UPDATE crawl_pages SET active = $flag WHERE crawl_site_id = ? AND id IN ($ph)")->execute(array_merge([$crawlSite['id']], $chunk));
        }
    }

    $stmt = $pdo->prepare("SELECT item_type, COUNT(*) c FROM crawl_pages WHERE crawl_site_id = ? AND active = 1 GROUP BY item_type");
    $stmt->execute([$crawlSite['id']]);
    echo json_encode(['ok' => true, 'crawl_site_id' => (int)$crawlSite['id'], 'selected' => $stmt->fetchAll(PDO::FETCH_KEY_PAIR)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
