<?php
/** One pin's daily metrics for the last 90 days (the expandable graph under a Top Pins row). */
@set_time_limit(60);
require_once __DIR__ . '/includes/pa-ajax.php';

$pinId = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)($_GET['pin_id'] ?? ''));
if ($pinId === '') pa_json(['ok' => false, 'error' => 'Missing pin.']);

$r = pa_pin_daily($pdo, $paAccount, $pinId);
pa_json(['ok' => $r['ok'], 'days' => $r['days'], 'error' => $r['error']]);
