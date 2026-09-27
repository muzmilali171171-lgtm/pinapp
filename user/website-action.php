<?php
/**
 * Small POST-only endpoint for the buttons on the website list pages:
 *   recheck  - verify the connection again and refresh categories / authors / blogs
 *   delete   - remove the website from the account
 *   add      - add a website URL without connecting a platform yet (shows as Unconnected)
 * Redirects back to the page the button was on and leaves the result as a flash message.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);

$allowedReturn = ['websites', 'website-wordpress', 'shopify-stores', 'wix-sites', 'custom-websites'];
$return = basename((string)($_POST['return'] ?? 'websites'));
if (!in_array($return, $allowedReturn, true)) $return = 'websites';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect($return);
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($action === 'add') {
    $url = normalize_site_url((string)($_POST['site_url'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL) || strpos((string)parse_url($url, PHP_URL_HOST), '.') === false) {
        flash_set('error', 'Please enter a valid website URL, for example https://myblog.com');
        redirect($return);
    }
    $stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND site_url = ?");
    $stmt->execute([$user['id'], $url]);
    if ($stmt->fetchColumn()) {
        flash_set('error', 'That website is already in your list.');
        redirect($return);
    }
    $plan = get_user_plan($pdo, (int)$user['id']);
    $websiteLimit = plan_limit_value($plan, 'websites_limit');
    if ($websiteLimit !== null) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE user_id = ?");
        $countStmt->execute([$user['id']]);
        if ((int)$countStmt->fetchColumn() >= $websiteLimit) {
            flash_set('error', "Your plan allows up to $websiteLimit website(s). Upgrade your plan to add more.");
            redirect($return);
        }
    }
    $name = trim((string)($_POST['site_name'] ?? '')) ?: (parse_url($url, PHP_URL_HOST) ?: $url);
    $pdo->prepare("INSERT INTO websites (user_id, platform, site_name, site_url, site_key, status) VALUES (?, 'none', ?, ?, '', 'error')")
        ->execute([$user['id'], mb_substr($name, 0, 255), $url]);
    flash_set('success', 'Website added. It shows as Unconnected — click Connect to link it to WordPress, Shopify, Wix or a custom webhook. You can already use Automate Pin on it.');
    redirect($return);
}

$site = $id ? get_user_website($pdo, $id, (int)$user['id']) : null;
if (!$site) {
    flash_set('error', 'Website not found.');
    redirect($return);
}

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM websites WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
    flash_set('success', 'Website removed.');
    redirect($return);
}

if ($action === 'recheck') {
    if (($site['platform'] ?? 'none') === 'none') {
        flash_set('error', 'This website is not linked to a platform yet — use Connect.');
        redirect($return);
    }
    @set_time_limit(90);
    $r = website_recheck($pdo, $site);
    if ($r['ok']) {
        flash_set('success', ($site['site_name'] ?: $site['site_url']) . ' is connected.');
        if (!empty($r['warning'])) flash_set('info', $r['warning']);
    } else {
        flash_set('error', 'Connection check failed for ' . ($site['site_name'] ?: $site['site_url']) . ': ' . $r['error']);
    }
    redirect($return);
}

flash_set('error', 'Unknown action.');
redirect($return);
