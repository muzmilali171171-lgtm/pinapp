<?php
/**
 * "Automate Pin" button target. Gives the website a crawl site (the source for Auto Website to Daily Pin)
 * and sends the user to the page picker. A site with no pages yet is scanned automatically on arrival.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$site = get_user_website($pdo, (int)($_GET['id'] ?? 0), (int)$user['id']);
if (!$site) {
    flash_set('error', 'Website not found.');
    redirect('websites');
}

$crawlSite = website_get_crawl_site($pdo, $site);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM crawl_pages WHERE crawl_site_id = ?");
$stmt->execute([$crawlSite['id']]);
$hasPages = (int)$stmt->fetchColumn() > 0;

redirect('website-pages?site_id=' . (int)$crawlSite['id'] . ($hasPages ? '' : '&autoscan=1'));
