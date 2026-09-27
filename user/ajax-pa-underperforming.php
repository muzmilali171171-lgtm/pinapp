<?php
/**
 * Delete Underperforming Pins → candidate list.
 * GET age=90|180 → the account's own pins older than that (not already queued/deleted),
 * plus the median and 25th-percentile impressions used for the threshold buttons.
 */
@set_time_limit(60);
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];
$state = pa_sync_state($pdo, $accountId);
if (empty($state['pins_synced_at'])) pa_json(['ok' => true, 'needs_pins_sync' => true]);

$age = ($_GET['age'] ?? '90') === '180' ? 180 : 90;
$pins = pa_underperforming_candidates($pdo, $accountId, $age);
$imps = array_column($pins, 'impressions');

pa_json([
    'ok' => true,
    'needs_pins_sync' => false,
    'age' => $age,
    'median' => round(pa_median($imps), 1),
    'p25' => round(pa_percentile($imps, 25)),
    'p40' => round(pa_percentile($imps, 40)),
    'queue_count' => pa_delete_queue_count($pdo, $accountId),
    'synced_at' => $state['pins_synced_at'] ? format_datetime($state['pins_synced_at']) : null,
    'pins' => $pins,
]);
