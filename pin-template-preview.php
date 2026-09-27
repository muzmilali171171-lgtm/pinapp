<?php
/**
 * Preview thumbnail for one pin template: renders the real template (same code the generator uses)
 * on the sample photo, once, and caches it in uploads/template-previews/. Later requests are served
 * straight from the cache file.
 *
 *   GET pin-template-preview?s=<template key>&v=<version>
 */
@set_time_limit(60);
ini_set('display_errors', '0');

$GLOBALS['TT_SKIP'] = true; // previews aren't pins
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai_functions.php';

$key = (string)($_GET['s'] ?? '');
$reg = pin_template_registry();
if (!isset($reg[$key])) { http_response_code(404); exit; }

$dir = __DIR__ . '/uploads/template-previews/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$ver = pin_template_preview_version();
$file = $dir . $key . '-' . $ver . '.jpg';

if (!is_file($file)) {
    $sample = pin_template_sample_image($dir);
    $t = $reg[$key];
    $imgs = $t['layout'] === 'collage' ? [$sample, $sample, $sample, $sample] : [$sample];
    $full = $sample !== null
        ? compose_pin_image($imgs, pin_template_sample_title($t['category']), 'yourwebsite.com', 'Read More', '2:3', $key)
        : null;
    if ($full) {
        $src = imagecreatefromstring($full);
        $thumb = imagecreatetruecolor(400, 600);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, 400, 600, imagesx($src), imagesy($src));
        imagejpeg($thumb, $file, 88);
        imagedestroy($src); imagedestroy($thumb);
        // old versions of this template's preview are no longer needed
        foreach (glob($dir . $key . '-*.jpg') ?: [] as $old) if ($old !== $file) @unlink($old);
    }
}

if (is_file($file)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=2592000, immutable');
    readfile($file);
} else {
    http_response_code(503);
}
