<?php
/** Top Pins → Export CSV (same sort / "only your pins" filter as the screen). */
$paSkipJsonHeader = true;
require_once __DIR__ . '/includes/pa-ajax.php';

$sort = in_array($_GET['sort'] ?? '', ['impressions', 'clicks', 'outbound', 'saves', 'ctr'], true) ? $_GET['sort'] : 'impressions';
$ownOnly = ($_GET['own_only'] ?? '0') === '1';
$rows = pa_top_pins($pdo, (int)$paAccount['id'], $sort, $ownOnly);

$name = 'top-pins-' . preg_replace('/[^a-z0-9]+/i', '-', $paAccount['pinterest_username'] ?: ('account-' . $paAccount['id'])) . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows emoji/accents correctly
fputcsv($out, ['Rank', 'Pin ID', 'Title', 'Created', 'Impressions (90d)', 'Pin Clicks (90d)', 'Outbound Clicks (90d)', 'Saves (90d)',
    'Outbound Click Rate %', 'Lifetime Impressions', 'Lifetime Clicks', 'Lifetime Outbound Clicks', 'Lifetime Saves', 'Destination Link', 'Pin URL', 'Your Pin']);
foreach ($rows as $i => $p) {
    $imp = (int)$p['imp_90'];
    fputcsv($out, [
        $i + 1, $p['pin_id'], $p['title'], $p['pin_created_at'] ? date('Y-m-d', strtotime($p['pin_created_at'])) : '',
        $imp, (int)$p['clicks_90'], (int)$p['outbound_90'], $p['saves_90'] ?? '',
        $imp > 0 ? round($p['outbound_90'] / $imp * 100, 3) : 0,
        $p['imp_life'] ?? '', $p['clicks_life'] ?? '', $p['outbound_life'] ?? '', $p['saves_life'] ?? '',
        $p['link'], 'https://www.pinterest.com/pin/' . $p['pin_id'] . '/', (int)$p['is_own'] === 1 ? 'Yes' : 'No',
    ]);
}
fclose($out);
exit;
