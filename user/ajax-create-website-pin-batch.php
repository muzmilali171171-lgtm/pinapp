<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/website_pin_functions.php';
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

$crawlSiteId = (int)($payload['crawl_site_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM crawl_sites WHERE id = ? AND user_id = ?");
$stmt->execute([$crawlSiteId, $user['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid website.']);
    exit;
}

$accountId = (int)($payload['pinterest_account_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
$stmt->execute([$accountId, $user['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a valid connected Pinterest account.']);
    exit;
}

$boardMode = ($payload['board_mode'] ?? 'ai_separate') === 'existing' ? 'existing' : 'ai_separate';
$boardRowId = null;
if ($boardMode === 'existing') {
    $boardRowId = (int)($payload['board_row_id'] ?? 0);
    if (!$boardRowId) {
        echo json_encode(['ok' => false, 'error' => 'Please select a board.']);
        exit;
    }
}

$pages = $payload['pages'] ?? [];
if (!is_array($pages) || empty($pages)) {
    echo json_encode(['ok' => false, 'error' => 'No pages selected.']);
    exit;
}
if (count($pages) > 500) {
    echo json_encode(['ok' => false, 'error' => 'Please keep a schedule to 500 pages or fewer.']);
    exit;
}

$contentSource = ($payload['content_source'] ?? 'ai') === 'csv' ? 'csv' : 'ai';

$cfg = [
    'name' => trim($payload['name'] ?? ''),
    'crawl_site_id' => $crawlSiteId,
    'pinterest_account_id' => $accountId,
    'pins_per_page' => max(1, (int)($payload['pins_per_page'] ?? 3)),
    'board_mode' => $boardMode,
    'board_row_id' => $boardRowId,
    'page_gap_days' => max(1, (int)($payload['page_gap_days'] ?? 30)),
    'page_gap_unit' => ($payload['page_gap_unit'] ?? 'days') === 'minutes' ? 'minutes' : 'days',
    'page_gap_minutes' => ($payload['page_gap_unit'] ?? 'days') === 'minutes' ? max(1, min(525600, (int)($payload['page_gap_value'] ?? 60))) : null,
    'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($payload['start_date'] ?? '')) ? $payload['start_date'] : null,
    'start_time' => preg_match('/^\d{1,2}:\d{2}$/', (string)($payload['start_time'] ?? '')) ? $payload['start_time'] : null,
    'daily_pin_count' => max(1, (int)($payload['daily_pin_count'] ?? 6)),
    'image_quality' => in_array($payload['image_quality'] ?? '', ['budget', 'high', 'ultra'], true) ? $payload['image_quality'] : 'ultra',
    'pin_size' => trim($payload['pin_size'] ?? '2:3'),
    'image_style' => pin_sanitize_style_value((string)($payload['image_style'] ?? 'auto')),
    'image_category_id' => max(0, (int)($payload['image_category_id'] ?? 0)),
    'color_palette' => is_array($payload['color_palette'] ?? null) ? json_encode($payload['color_palette']) : null,
    'cta_mode' => trim($payload['cta_mode'] ?? 'auto'),
    'cta_text' => trim($payload['cta_text'] ?? ''),
    'website_text' => trim($payload['website_text'] ?? ''),
    'content_source' => $contentSource,
];
// Start day/time were picked in the user's own time zone — stored in server time.
[$cfg['start_date'], $cfg['start_time']] = user_start_to_server($cfg['start_date'], $cfg['start_time']);

$pageRows = [];
foreach ($pages as $p) {
    if (empty($p['url'])) continue;
    $pageRows[] = [
        'crawl_page_id' => !empty($p['crawl_page_id']) ? (int)$p['crawl_page_id'] : null,
        'url' => trim($p['url']),
        'title' => $contentSource === 'csv' && !empty($p['title']) ? trim($p['title']) : null,
        'description' => $contentSource === 'csv' && !empty($p['description']) ? trim($p['description']) : null,
        'alt' => $contentSource === 'csv' && !empty($p['alt']) ? trim($p['alt']) : null,
        'keywords' => $contentSource === 'csv' && !empty($p['keywords']) ? trim($p['keywords']) : null,
    ];
}
if (empty($pageRows)) {
    echo json_encode(['ok' => false, 'error' => 'No valid pages to schedule.']);
    exit;
}

$result = create_website_pin_batch($pdo, $user['id'], $cfg, $pageRows);
log_event($pdo, 'system', "Auto Website to Daily Pin schedule created: {$result['count']} pages queued", $user['id']);

// Kick off background processing immediately (detached), same pattern as Auto Article batches,
// so pin generation starts right away instead of waiting for the next tick or a manual click.
ignore_user_abort(true);
@set_time_limit(90);
if (function_exists('fastcgi_finish_request')) {
    echo json_encode($result);
    fastcgi_finish_request();
    run_due_website_pin_steps($pdo, 6);
} else {
    echo json_encode($result);
}
