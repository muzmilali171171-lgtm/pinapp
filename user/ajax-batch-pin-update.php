<?php
/**
 * AJAX backend for user/batch-view.php — lets the user edit and re-save a
 * single not-yet-published pin from a batch (title, description, link, alt
 * text, keywords, product link, publish time).
 */
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
$pinId = (int)($_POST['pin_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM scheduled_pins WHERE id = ? AND user_id = ?");
$stmt->execute([$pinId, $user['id']]);
$pin = $stmt->fetch();
if (!$pin) {
    echo json_encode(['ok' => false, 'error' => 'Pin not found.']);
    exit;
}
if ($pin['status'] !== 'pending') {
    echo json_encode(['ok' => false, 'error' => 'Only pins that have not published yet can be edited.']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$link = trim($_POST['link'] ?? '');
$alt = trim($_POST['alt'] ?? '');
$keywords = trim($_POST['keywords'] ?? '');
$productLink = trim($_POST['product_link'] ?? '');
$publishAt = trim($_POST['publish_at'] ?? '');

if ($publishAt === '' || strtotime($publishAt) === false) {
    echo json_encode(['ok' => false, 'error' => 'Please provide a valid publish date/time.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE scheduled_pins SET title = ?, description = ?, dest_link = ?, alt_text = ?, keywords = ?, product_link = ?, publish_at = ? WHERE id = ?");
$stmt->execute([
    $title ?: null, $description ?: null, $link ?: null, $alt ?: null,
    $keywords ?: null, $productLink ?: null,
    date('Y-m-d H:i:s', strtotime($publishAt)), $pinId,
]);

log_event($pdo, 'system', "Pin #{$pinId} edited in batch view", $user['id']);

echo json_encode(['ok' => true]);
