<?php
/**
 * AJAX / downloads for Analytics → Competitor Analysis and Competitor Research.
 *   POST add       — username or Pinterest URL (profile or board) → saved + first data collection
 *   POST refresh   — collect again (at most once an hour per competitor)
 *   POST delete    — remove a competitor and its collected Pins
 *   GET  csv       — id=…&type=report|pins            (one competitor)
 *   GET  research_csv — q=…&type=report|pins           (keyword research over all competitors)
 */
@set_time_limit(240);
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/competitor_functions.php';
require_once __DIR__ . '/../includes/auth.php';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$json = fn(array $a) => exit(json_encode($a));
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    if (in_array($action, ['csv', 'research_csv'], true)) exit('Please log in again.');
    header('Content-Type: application/json');
    $json(['ok' => false, 'error' => 'Please log in again.']);
}
$userId = (int)$_SESSION['user_id'];
cp_ensure_schema($pdo);

/* ---------- downloads ---------- */
if ($action === 'csv') {
    $c = cp_get($pdo, $userId, (int)($_GET['id'] ?? 0));
    if (!$c) { http_response_code(404); exit('Competitor not found.'); }
    $pins = cp_pins($pdo, (int)$c['id']);
    $name = 'competitor-' . $c['username'] . '-' . date('Y-m-d');
    if (($_GET['type'] ?? 'report') === 'pins') cp_csv_out($name . '-pins.csv', ['' => cp_pins_csv_rows($pins)]);
    $sections = ['Competitor' => [['Username', $c['username']], ['Name', $c['display_name']], ['Followers', $c['followers']],
        ['Pins on profile', $c['total_pins']], ['Collected', $c['fetched_at']], ['Profile', 'https://www.pinterest.com/' . $c['username'] . '/']]];
    cp_csv_out($name . '-report.csv', $sections + cp_report_csv_sections(cp_report($pins)));
}
if ($action === 'research_csv') {
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
    if ($q === '') { http_response_code(400); exit('Missing keyword.'); }
    $r = cp_keyword_research($pdo, $userId, $q);
    $name = 'competitor-research-' . preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($q)) . '-' . date('Y-m-d');
    if (($_GET['type'] ?? 'report') === 'pins') cp_csv_out($name . '-pins.csv', ['' => cp_pins_csv_rows($r['pins'], true)]);
    $sections = ['Keyword' => [['Keyword', $q], ['Matching competitor Pins', count($r['pins'])], ['Your matching Pins', $r['own']['pins']], ['Your avg saves', $r['own']['avg_saves']]],
        'Frequently appearing competitors' => array_merge([['Competitor', 'Name', 'Pins on this keyword', 'Avg saves', 'Total saves']],
            array_map(fn($c) => [$c['username'], $c['name'], $c['pins'], $c['avg_saves'], $c['total_saves']], $r['competitors']))];
    cp_csv_out($name . '-report.csv', $sections + cp_report_csv_sections($r['report']));
}

/* ---------- actions (POST, JSON) ---------- */
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) $json(['ok' => false, 'error' => 'Your session expired. Please reload the page and try again.']);

if ($action === 'add') {
    $in = cp_parse_input((string)($_POST['profile'] ?? ''));
    if (!$in) $json(['ok' => false, 'error' => 'Enter a Pinterest username (e.g. "apartmenttherapy") or a profile / board link.']);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM competitors WHERE user_id = " . $userId)->fetchColumn();
    $st = $pdo->prepare("SELECT * FROM competitors WHERE user_id = ? AND username = ?");
    $st->execute([$userId, $in['username']]);
    $c = $st->fetch();
    if (!$c) {
        if ($count >= CP_MAX_COMPETITORS) $json(['ok' => false, 'error' => 'You can track up to ' . CP_MAX_COMPETITORS . ' competitors — remove one first.']);
        $pdo->prepare("INSERT INTO competitors (user_id, username) VALUES (?, ?)")->execute([$userId, $in['username']]);
        $c = cp_get($pdo, $userId, (int)$pdo->lastInsertId());
    }
    $r = cp_refresh($pdo, $c, $in['board']);
    if (!$r['ok'] && (int)$pdo->query("SELECT COUNT(*) FROM competitor_pins WHERE competitor_id = " . (int)$c['id'])->fetchColumn() === 0) {
        $pdo->prepare("DELETE FROM competitors WHERE id = ?")->execute([$c['id']]);
        $json(['ok' => false, 'error' => $r['error']]);
    }
    $json(['ok' => true, 'id' => (int)$c['id'], 'total' => $r['total'], 'new' => $r['new'], 'warning' => $r['ok'] ? null : $r['error']]);
}

if ($action === 'refresh') {
    $c = cp_get($pdo, $userId, (int)($_POST['id'] ?? 0));
    if (!$c) $json(['ok' => false, 'error' => 'Competitor not found.']);
    if ($c['fetched_at'] && strtotime($c['fetched_at']) > time() - CP_REFRESH_MINUTES * 60 && $c['status'] === 'ok') {
        $json(['ok' => false, 'error' => 'Collected less than an hour ago — try again later.']);
    }
    $r = cp_refresh($pdo, $c);
    $json($r['ok'] ? ['ok' => true, 'total' => $r['total'], 'new' => $r['new']] : ['ok' => false, 'error' => $r['error']]);
}

if ($action === 'delete') {
    $c = cp_get($pdo, $userId, (int)($_POST['id'] ?? 0));
    if ($c) {
        $pdo->prepare("DELETE FROM competitor_pins WHERE competitor_id = ?")->execute([$c['id']]);
        $pdo->prepare("DELETE FROM competitors WHERE id = ?")->execute([$c['id']]);
    }
    $json(['ok' => true]);
}

$json(['ok' => false, 'error' => 'Unknown action.']);
