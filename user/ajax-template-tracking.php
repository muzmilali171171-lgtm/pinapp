<?php
/**
 * Analytics → Template Tracking (user/template-tracking.php).
 *   POST action=resolve                    → link newly published pins to their template + main colour
 *   POST action=daily_sync offset=N        → per-day metrics for tracked pins (30 days / custom)
 *   GET  action=stats  range=… min_pins=…  → one row per template
 *   GET  action=colors key=source:tpl      → one row per main colour ("all" = every tracked pin)
 *   GET  action=pins   key=… color=…       → the pins behind a row
 */
@set_time_limit(150);
require_once __DIR__ . '/includes/pa-ajax.php';
require_once __DIR__ . '/../includes/template_tracking_functions.php';

$accountId = (int)$paAccount['id'];
$userId = (int)$user['id'];
$action = $_REQUEST['action'] ?? 'stats';
$range = in_array($_REQUEST['range'] ?? 'life', ['life', '30', '90', 'custom'], true) ? $_REQUEST['range'] : 'life';
$from = (string)($_REQUEST['from'] ?? '');
$to = (string)($_REQUEST['to'] ?? '');
$source = (string)($_REQUEST['source'] ?? '');
$minPins = max(1, min(1000, (int)($_REQUEST['min_pins'] ?? 1)));

function tt_meta(PDO $pdo, int $accountId, string $range): array
{
    $state = pa_sync_state($pdo, $accountId);
    return [
        'pins_synced_at' => $state['pins_synced_at'] ?? null,
        'pins_need_sync' => pa_pins_need_sync($state),
        'daily_needs_sync' => in_array($range, ['30', 'custom'], true) && tt_daily_needs_sync($pdo, $accountId),
    ];
}

switch ($action) {
    case 'resolve':
        $left = tt_resolve_pins($pdo, $accountId, $userId, 250);
        pa_json(['ok' => true, 'left' => $left]);

    case 'daily_sync':
        $r = tt_daily_sync_step($pdo, $paAccount, max(0, (int)($_POST['offset'] ?? 0)));
        pa_json($r + ['ok' => true]);

    case 'stats': {
        $rows = tt_pin_rows($pdo, $accountId, $range, $from, $to, $source);
        $groups = tt_group($rows, 'template', $minPins);
        $shownPins = array_sum(array_column($groups, 'pins'));
        pa_json(['ok' => true, 'rows' => $groups, 'total_pins' => $shownPins, 'all_pins' => count($rows), 'templates' => count($groups)] + tt_meta($pdo, $accountId, $range));
    }

    case 'colors': {
        $key = (string)($_GET['key'] ?? 'all');
        $rows = tt_pin_rows($pdo, $accountId, $range, $from, $to, $source);
        $name = 'All tracked pins';
        if ($key !== 'all') {
            $rows = array_values(array_filter($rows, fn($r) => $r['source'] . ':' . $r['template_key'] === $key));
            $name = $rows ? ($rows[0]['template_name'] ?: $rows[0]['template_key']) : $key;
        }
        $groups = tt_group($rows, 'color', 1);
        pa_json(['ok' => true, 'template' => $name, 'rows' => $groups, 'total_pins' => count($rows)] + tt_meta($pdo, $accountId, $range));
    }

    case 'pins': {
        $key = (string)($_GET['key'] ?? 'all');
        $color = (string)($_GET['color'] ?? '');
        $rows = tt_pin_rows($pdo, $accountId, $range, $from, $to, $source);
        $rows = array_values(array_filter($rows, function ($r) use ($key, $color) {
            if ($key !== 'all' && $r['source'] . ':' . $r['template_key'] !== $key) return false;
            if ($color !== '' && ($r['color_name'] ?: 'Unknown') !== $color) return false;
            return true;
        }));
        usort($rows, fn($a, $b) => (int)$b['views'] <=> (int)$a['views']);
        $out = array_map(fn($r) => [
            'title' => $r['title'], 'image' => media_url((string)$r['image_path'], false), 'pin_id' => $r['pin_id'],
            'url' => 'https://www.pinterest.com/pin/' . rawurlencode($r['pin_id']) . '/',
            'views' => (int)$r['views'], 'clicks' => (int)$r['clicks'], 'saves' => (int)$r['saves'],
            'ctr' => (int)$r['views'] ? round($r['clicks'] / $r['views'] * 100, 2) : 0,
            'color' => $r['color_name'], 'color_hex' => $r['color_hex'], 'published_at' => $r['published_at'],
        ], array_slice($rows, 0, 300));
        pa_json(['ok' => true, 'pins' => $out, 'total' => count($rows)]);
    }
}
pa_json(['ok' => false, 'error' => 'Unknown action.']);
