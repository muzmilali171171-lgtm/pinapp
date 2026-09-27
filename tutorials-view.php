<?php
/**
 * Fire-and-forget endpoint called from tutorials.php when a video modal is
 * opened, just to bump tutorials.view_count for the admin list. No response
 * body is needed — the page's fetch() call doesn't read it.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tutorial_functions.php';

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    increment_tutorial_views($pdo, $id);
}
http_response_code(204);
