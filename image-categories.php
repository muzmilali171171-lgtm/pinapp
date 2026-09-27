<?php
/**
 * Public, read-only list of image categories for the "Select category" picker
 * (assets/js/category-picker.js) — used by user pages, the homepage widget and the free tools.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/image_category_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600');
echo json_encode(['ok' => true, 'categories' => image_categories_tree($pdo)], JSON_UNESCAPED_UNICODE);
