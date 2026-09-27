<?php
/**
 * Analytics → Keyword Research data.
 * GET account_id, q, region, type, interest, gmetric (wow|mom|yoy), gmin, gmax, offset, refresh=1
 * Returns up to 100 keywords starting at offset (after the growth filter) and has_more.
 */
@set_time_limit(150);
require_once __DIR__ . '/includes/pa-ajax.php';

$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$region = array_key_exists($_GET['region'] ?? '', PA_KW_REGIONS) ? $_GET['region'] : 'US';
$type = array_key_exists($_GET['type'] ?? '', PA_KW_TYPES) ? $_GET['type'] : 'monthly';
$interest = array_key_exists($_GET['interest'] ?? '', PA_KW_INTERESTS) ? $_GET['interest'] : '';
$metric = in_array($_GET['gmetric'] ?? '', ['wow', 'mom', 'yoy'], true) ? $_GET['gmetric'] : 'mom';
$gmin = isset($_GET['gmin']) && $_GET['gmin'] !== '' ? (float)$_GET['gmin'] : null;
$gmax = isset($_GET['gmax']) && $_GET['gmax'] !== '' ? (float)$_GET['gmax'] : null;
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = 100;

// Keywords without trend numbers (suggested terms) can't be judged by growth — a growth
// filter hides them.
$filter = function ($row) use ($metric, $gmin, $gmax) {
    if ($gmin === null && $gmax === null) return true;
    $v = $row[$metric];
    if ($v === null) return false;
    if ($gmin !== null && $v < $gmin) return false;
    if ($gmax !== null && $v > $gmax) return false;
    return true;
};

$r = pa_kw_pool($pdo, $paAccount, $region, $type, $interest, $q, $offset + $limit + 1, $filter, !empty($_GET['refresh']) && $offset === 0);
if (!$r['ok']) pa_json(['ok' => false, 'error' => $r['error']]);

$rows = array_values(array_filter($r['pool'], $filter));
$page = array_slice($rows, $offset, $limit);
$hasMore = count($rows) > $offset + $limit || !$r['done'];

pa_json([
    'ok' => true,
    'keywords' => array_map(function ($k) use ($region) {
        $countryParam = explode('+', $region)[0];
        $k['search_url'] = 'https://www.pinterest.com/search/pins/?q=' . rawurlencode($k['keyword']);
        $k['trends_url'] = 'https://trends.pinterest.com/search/?q=' . rawurlencode($k['keyword']) . '&country=' . rawurlencode($countryParam);
        $k['interest_label'] = PA_KW_INTERESTS[$k['interest'] ?? ''] ?? '';
        return $k;
    }, $page),
    'offset' => $offset,
    'has_more' => $hasMore,
    'loaded' => count($rows),
]);
