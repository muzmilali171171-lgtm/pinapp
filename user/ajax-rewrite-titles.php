<?php
/**
 * AJAX backend for the Auto Article wizard's "Rewrite to Your Original Title
 * (AI)" button — rewrites each given (usually competitor-sourced) title into
 * an original one via the configured text model.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/auto_article_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$titles = json_decode($_POST['titles'] ?? '[]', true);
if (!is_array($titles)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}
$titles = array_slice(array_values(array_filter(array_map('trim', $titles), fn($t) => $t !== '')), 0, 60);

$rewritten = [];
foreach ($titles as $t) {
    $rewritten[] = ai_rewrite_competitor_title($pdo, $t);
}

echo json_encode(['ok' => true, 'titles' => $rewritten]);
