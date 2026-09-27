<?php
/**
 * Auto Website to Daily Pin — "Upload URLs from CSV".
 *
 * Takes a CSV of page URLs (plus optional title/description/alt/keywords) and
 * turns it into an archive the schedule builder can use, instead of having to
 * scan a live website for its sitemap.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/page_crawler_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(120);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$user = current_user($pdo);

if (empty($_FILES['csv_file']['name']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Please choose a CSV file to upload.']);
    exit;
}
if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'That file is larger than 5MB — please split it up.']);
    exit;
}
$ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Please upload a .csv file.']);
    exit;
}

$csvText = file_get_contents($_FILES['csv_file']['tmp_name']);
if ($csvText === false) {
    echo json_encode(['ok' => false, 'error' => 'Could not read the uploaded file.']);
    exit;
}

$parsed = parse_url_csv($csvText);
if (!$parsed['ok']) {
    echo json_encode(['ok' => false, 'error' => $parsed['error']]);
    exit;
}
if (count($parsed['rows']) > 2000) {
    echo json_encode(['ok' => false, 'error' => 'That CSV has more than 2,000 URLs — please split it into smaller files.']);
    exit;
}

$archiveName = trim($_POST['archive_name'] ?? '');
$existingSiteId = (int)($_POST['existing_site_id'] ?? 0) ?: null;
$activate = ($_POST['activate'] ?? '1') === '1';

if (!$existingSiteId && $archiveName === '') {
    echo json_encode(['ok' => false, 'error' => 'Please give this archive a name.']);
    exit;
}

$result = import_urls_from_csv($pdo, (int)$user['id'], $parsed['rows'], $archiveName, $existingSiteId, $activate);
if (!$result['ok']) {
    echo json_encode($result);
    exit;
}

$result['skipped'] = $parsed['skipped'];
log_event(
    $pdo,
    'system',
    "CSV URL archive \"{$result['site_name']}\": {$result['pages_added']} added, {$result['pages_updated']} updated",
    (int)$user['id']
);

echo json_encode($result);
