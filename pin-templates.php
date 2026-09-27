<?php
/**
 * Public, read-only list of pin templates for the "Pin Templates & Styles" picker
 * (assets/js/template-picker.js). Each item has a preview URL rendered by pin-template-preview.php.
 */
require_once __DIR__ . '/includes/pin_template_registry.php';

header('Content-Type: application/json; charset=utf-8');
// No caching: the list grows whenever templates are added, so browsers/CDNs must always get the current one.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ver = pin_template_preview_version();
$items = [];
foreach (pin_template_registry() as $t) {
    $t['preview'] = 'pin-template-preview?s=' . rawurlencode($t['key']) . '&v=' . $ver;
    $items[] = $t;
}
echo json_encode(['ok' => true, 'count' => count($items), 'version' => $ver, 'categories' => pin_template_categories(), 'templates' => $items], JSON_UNESCAPED_UNICODE);
