<?php
/**
 * Top Pins list from the local cache. GET account_id, sort, own_only (1/0).
 * needs_sync=true tells the page to run the sync loop first.
 */
require_once __DIR__ . '/includes/pa-ajax.php';

$sort = in_array($_GET['sort'] ?? '', ['impressions', 'clicks', 'outbound', 'saves', 'ctr'], true) ? $_GET['sort'] : 'impressions';
$ownOnly = ($_GET['own_only'] ?? '0') === '1';
$state = pa_sync_state($pdo, (int)$paAccount['id']);

$rows = pa_top_pins($pdo, (int)$paAccount['id'], $sort, $ownOnly);
$pins = array_map(function ($p) {
    $imp = (int)$p['imp_90'];
    return [
        'pin_id' => $p['pin_id'],
        'title' => $p['title'] ?: '',
        'description' => $p['description'] ?: '',
        'link' => $p['link'] ?: '',
        'image_url' => $p['image_url'] ?: '',
        'board_id' => $p['board_id'] ?: '',
        'created_at' => $p['pin_created_at'] ? date('M j, Y', strtotime($p['pin_created_at'])) : null,
        'is_own' => (int)$p['is_own'] === 1,
        'impressions' => $imp,
        'clicks' => (int)$p['clicks_90'],
        'outbound' => (int)$p['outbound_90'],
        'saves' => $p['saves_90'] === null ? null : (int)$p['saves_90'],
        'ctr' => $imp > 0 ? round($p['outbound_90'] / $imp * 100, 2) : 0,
        'save_rate' => ($imp > 0 && $p['saves_90'] !== null) ? round($p['saves_90'] / $imp * 100, 2) : null,
        'life' => [
            'impressions' => $p['imp_life'] === null ? null : (int)$p['imp_life'],
            'clicks' => $p['clicks_life'] === null ? null : (int)$p['clicks_life'],
            'outbound' => $p['outbound_life'] === null ? null : (int)$p['outbound_life'],
            'saves' => $p['saves_life'] === null ? null : (int)$p['saves_life'],
        ],
        'regen_count' => (int)$p['regen_count'],
        'url' => 'https://www.pinterest.com/pin/' . rawurlencode($p['pin_id']) . '/',
    ];
}, $rows);

pa_json([
    'ok' => true,
    'needs_sync' => pa_pins_need_sync($state),
    'never_synced' => empty($state['pins_synced_at']),
    'synced_at' => $state['pins_synced_at'] ? format_datetime($state['pins_synced_at']) : null,
    'last_error' => $state['last_error'],
    'pins' => $pins,
]);
