<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
require_once __DIR__ . '/../includes/free_tool_functions.php';
require_once __DIR__ . '/../includes/page_crawler_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);
$payload = json_decode($_POST['payload'] ?? '', true);
if (!is_array($payload)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$pages = $payload['pages'] ?? [];
if (!is_array($pages) || empty($pages)) {
    echo json_encode(['ok' => false, 'error' => 'No pages selected. Please go back to Step 3.']);
    exit;
}

$crawlSiteId = (int)($payload['crawl_site_id'] ?? 0);
if ($crawlSiteId) {
    $stmt = $pdo->prepare("SELECT id FROM crawl_sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$crawlSiteId, $user['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Invalid website. Please scan and select pages in Step 3 again.']);
        exit;
    }
} else {
    // CSV-only submission (no site scanned) — file the pages under an ad-hoc archive
    // named from the first page's host, same convention as the CSV-archive importer.
    $firstUrl = trim($pages[0]['url'] ?? '');
    if ($firstUrl === '' || !filter_var($firstUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['ok' => false, 'error' => 'The imported CSV has no valid URLs.']);
        exit;
    }
    $host = parse_url($firstUrl, PHP_URL_SCHEME) . '://' . parse_url($firstUrl, PHP_URL_HOST);
    $site = get_or_create_crawl_site($pdo, (int)$user['id'], $host);
    $crawlSiteId = (int)$site['id'];
}

$accountId = (int)($payload['pinterest_account_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
$stmt->execute([$accountId, $user['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid connected Pinterest account.']);
    exit;
}

$boardRowIds = array_values(array_filter(array_map('intval', (array)($payload['board_row_ids'] ?? []))));
if (empty($boardRowIds)) {
    echo json_encode(['ok' => false, 'error' => 'Please select at least one board in Step 4.']);
    exit;
}
// Every selected board must actually belong to this account.
$placeholders = implode(',', array_fill(0, count($boardRowIds), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pinterest_boards WHERE pinterest_account_id = ? AND id IN ($placeholders)");
$stmt->execute([$accountId, ...$boardRowIds]);
if ((int)$stmt->fetchColumn() !== count($boardRowIds)) {
    echo json_encode(['ok' => false, 'error' => 'One or more selected boards are invalid.']);
    exit;
}

$pages = $payload['pages'] ?? [];
if (count($pages) > 500) {
    echo json_encode(['ok' => false, 'error' => 'Please keep a schedule to 500 pages or fewer.']);
    exit;
}

$templateOptions = free_tool_template_options();
$styles = array_values(array_intersect((array)($payload['template_styles'] ?? []), array_keys($templateOptions)));
if (empty($styles)) $styles = ['high_attractive_multi'];

$cfg = [
    'name' => trim($payload['name'] ?? ''),
    'crawl_site_id' => $crawlSiteId,
    'pinterest_account_id' => $accountId,
    'pins_per_page' => max(1, (int)($payload['pins_per_page'] ?? 1)),
    'board_mode' => 'multi_select',
    'board_row_id' => null,
    'board_row_ids' => $boardRowIds,
    'page_gap_days' => max(1, (int)($payload['page_gap_days'] ?? 14)),
    'daily_pin_count' => max(1, (int)($payload['daily_pin_count'] ?? 5)),
    'image_quality' => 'ultra', // unused for source_type=page_scan — no AI image credits spent
    'pin_size' => trim($payload['pin_size'] ?? '2:3'),
    'image_style' => 'auto',
    'cta_mode' => trim($payload['cta_mode'] ?? 'auto'),
    'cta_text' => trim($payload['cta_text'] ?? ''),
    'website_text' => trim($payload['website_text'] ?? ''),
    'content_source' => ($payload['content_source'] ?? 'ai') === 'csv' ? 'csv' : 'ai',
    'wizard_source' => 'classic_wizard',
    'source_type' => 'page_scan',
    'template_styles' => implode(',', $styles),
    'warmup_enabled' => !empty($payload['warmup_enabled']),
    'floating_days_enabled' => !empty($payload['floating_days_enabled']),
    'floating_times_enabled' => !empty($payload['floating_times_enabled']),
    'floating_minutes' => max(0, min(60, (int)($payload['floating_minutes'] ?? 5))),
    'lifetime_limit_per_url' => !empty($payload['lifetime_limit_per_url']) ? (int)$payload['lifetime_limit_per_url'] : null,
    'monthly_limit_per_url' => !empty($payload['monthly_limit_per_url']) ? (int)$payload['monthly_limit_per_url'] : null,
    'no_link_pins' => !empty($payload['no_link_pins']),
    'requires_approval' => true, // Classic Wizard always holds pins for review before they go live
];

$pageRows = [];
foreach ($pages as $p) {
    if (empty($p['url'])) continue;
    $pageRows[] = [
        'crawl_page_id' => !empty($p['crawl_page_id']) ? (int)$p['crawl_page_id'] : null,
        'url' => trim($p['url']),
        'title' => $cfg['content_source'] === 'csv' && !empty($p['title']) ? trim($p['title']) : null,
        'description' => $cfg['content_source'] === 'csv' && !empty($p['description']) ? trim($p['description']) : null,
        'alt' => $cfg['content_source'] === 'csv' && !empty($p['alt']) ? trim($p['alt']) : null,
        'keywords' => $cfg['content_source'] === 'csv' && !empty($p['keywords']) ? trim($p['keywords']) : null,
    ];
}
if (empty($pageRows)) {
    echo json_encode(['ok' => false, 'error' => 'No valid pages to schedule.']);
    exit;
}

$result = create_website_pin_batch($pdo, $user['id'], $cfg, $pageRows);
log_event($pdo, 'system', "Classic Wizard schedule created: {$result['count']} pages queued", $user['id']);

// Kick off background processing immediately (detached), same pattern as Auto Website to Daily Pin.
ignore_user_abort(true);
@set_time_limit(90);
if (function_exists('fastcgi_finish_request')) {
    echo json_encode($result);
    fastcgi_finish_request();
    run_due_website_pin_steps($pdo, 6);
} else {
    echo json_encode($result);
}
