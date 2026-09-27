<?php
/**
 * Saves the user's time zone (header clock / Account Settings).
 *   tz=<IANA zone>                → the user picked a zone.
 *   auto=1 (+ tz = browser zone)  → first visit: zone of the visitor's IP (browser zone as fallback), saved only if none is set yet.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) { echo json_encode(['ok' => false, 'error' => 'Session expired — please refresh the page.']); exit; }

$userId = (int)$_SESSION['user_id'];
$tz = trim((string)($_POST['tz'] ?? ''));
$auto = !empty($_POST['auto']);

if ($auto && user_tz_is_set()) { echo json_encode(['ok' => true, 'tz' => user_tz(), 'changed' => false]); exit; }
// First visit: the zone of the visitor's IP address; the browser's own zone if the IP can't be looked up.
if ($auto) $tz = visitor_ip_timezone() ?? $tz;
if (!tz_valid($tz)) { echo json_encode(['ok' => false, 'error' => 'Please pick a valid time zone.']); exit; }

$before = user_tz();
if (!user_tz_save($pdo, $userId, $tz)) { echo json_encode(['ok' => false, 'error' => 'Could not save your time zone. Please try again.']); exit; }
echo json_encode(['ok' => true, 'tz' => $tz, 'label' => tz_offset_label($tz), 'changed' => $before !== $tz]);
