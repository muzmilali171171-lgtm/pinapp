<?php
/**
 * Pinterest Analytics (User → Analytics → Pinterest Analytics).
 *
 * Pinterest API v5 endpoints used (all need scopes pins:read + user_accounts:read, which the
 * app already requests — see pinterest_build_authorize_url()):
 *   GET /user_account/analytics              account-level daily metrics (last 90 days max)
 *   GET /user_account/analytics/top_pins     top 50 pins per sort metric
 *   GET /pins?pin_metrics=true               the account's own pins + 90d / lifetime stats
 *   GET /pins/analytics?pin_ids=…            90-day totals for up to 100 pins per call
 *   GET /pins/{id}/analytics                 one pin's daily metrics (the expandable graph)
 *
 * Analytics endpoints only work for Pinterest BUSINESS accounts — a personal account gets
 * an API error, which pa_friendly_error() turns into a clear message.
 *
 * Everything is cached locally (pa_* tables in database/schema.sql) so opening the page
 * doesn't burn through Pinterest's rate limit:
 *   - account daily metrics: refreshed at most once per PA_DAILY_TTL_MIN minutes
 *   - pin list: refreshed at most once per PA_PINS_TTL_HOURS hours (or on "Resync")
 *   - one pin's graph: cached PA_PIN_GRAPH_TTL_MIN minutes
 */

const PA_DAILY_TTL_MIN = 60;
const PA_PINS_TTL_HOURS = 6;
const PA_PIN_GRAPH_TTL_MIN = 180;
const PA_SYNC_MAX_PAGES = 100;     // 100 × 100 = up to 10,000 pins (old pins matter for Delete Underperforming)
const PA_TOP_LIMIT = 200;

/**
 * Pinterest timestamps are UTC (often without an offset, e.g. "2026-05-01T10:00:00"). Convert
 * to the app's timezone for storage — reading them as local time would shift every pin's
 * creation time (and the Breakdowns → Time buckets) by the server's UTC offset.
 */
function pa_api_time(?string $s): ?string
{
    if (!$s) return null;
    try {
        $dt = new DateTime($s, new DateTimeZone('UTC')); // an explicit offset in $s still wins
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/** A connected account that belongs to this user, or null. */
function pa_get_account(PDO $pdo, int $userId, int $accountId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    return $stmt->fetch() ?: null;
}

function pa_user_accounts(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE user_id = ? ORDER BY status = 'connected' DESC, connected_at ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** GET a Pinterest v5 endpoint for this account. Commas in list params are kept literal. */
function pa_api_get(PDO $pdo, array &$account, string $path, array $query = []): array
{
    $account = pinterest_ensure_fresh_token($pdo, $account);
    $qs = $query ? ('?' . str_replace('%2C', ',', http_build_query($query))) : '';
    return pinterest_http_request('GET', PINTEREST_API_BASE . $path . $qs, [
        'Authorization: Bearer ' . $account['access_token'],
    ]);
}

/** Turn a raw Pinterest error into something a user can act on. */
function pa_friendly_error(array $result): string
{
    $msg = '';
    if (is_array($result['data'] ?? null)) {
        $msg = (string)($result['data']['message'] ?? '');
    }
    if ($msg === '' && !empty($result['error'])) {
        $decoded = json_decode((string)$result['error'], true);
        $msg = is_array($decoded) ? (string)($decoded['message'] ?? '') : substr((string)$result['error'], 0, 300);
    }
    $code = (int)($result['code'] ?? 0);
    $lower = strtolower($msg);

    if ($code === 401) {
        return 'Pinterest rejected this account\'s login. Please reconnect it from Pinterest Accounts.';
    }
    if (strpos($lower, 'business') !== false || strpos($lower, 'consumer type') !== false) {
        return 'Pinterest only shares analytics for Business accounts. Switch this Pinterest profile to a free Business account (Pinterest → Settings → Account management → Convert to business), then reload this page.';
    }
    if ($code === 403 && (strpos($lower, 'scope') !== false || strpos($lower, 'permission') !== false || strpos($lower, 'authorized') !== false)) {
        return 'This account was connected without analytics permission. Please disconnect and reconnect it from Pinterest Accounts.';
    }
    if ($code === 429) {
        return 'Pinterest\'s rate limit was reached for now. Please try again in a few minutes.';
    }
    if ($code === 0) {
        return 'Could not reach Pinterest (' . ($msg ?: 'network error') . '). Please try again.';
    }
    return 'Pinterest API error' . ($code ? " ($code)" : '') . ($msg ? ': ' . $msg : '.');
}

function pa_sync_state(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pa_sync_state WHERE pinterest_account_id = ?");
    $stmt->execute([$accountId]);
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->prepare("INSERT IGNORE INTO pa_sync_state (pinterest_account_id) VALUES (?)")->execute([$accountId]);
        $row = ['pinterest_account_id' => $accountId, 'daily_synced_at' => null, 'pins_sync_started_at' => null,
            'pins_synced_at' => null, 'pins_count' => 0, 'last_error' => null];
    }
    return $row;
}

function pa_sync_state_set(PDO $pdo, int $accountId, array $fields): void
{
    pa_sync_state($pdo, $accountId);
    $sets = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        $sets[] = "`$k` = ?";
        $vals[] = $v;
    }
    $vals[] = $accountId;
    $pdo->prepare("UPDATE pa_sync_state SET " . implode(', ', $sets) . " WHERE pinterest_account_id = ?")->execute($vals);
}

/** Read a numeric metric from a metrics array regardless of key casing (IMPRESSION / impression). */
function pa_metric(array $metrics, array $keys): ?float
{
    foreach ($keys as $k) {
        foreach ([$k, strtoupper($k), strtolower($k)] as $variant) {
            if (isset($metrics[$variant]) && is_numeric($metrics[$variant])) {
                return (float)$metrics[$variant];
            }
        }
    }
    return null;
}

/* ============================== Account daily metrics ============================== */

/**
 * Pull the last 90 days of account metrics into pa_account_daily (at most once an hour
 * unless $force). Returns ['ok'=>bool, 'error'=>?string, 'refreshed'=>bool].
 */
function pa_refresh_account_daily(PDO $pdo, array $account, bool $force = false): array
{
    $state = pa_sync_state($pdo, (int)$account['id']);
    if (!$force && $state['daily_synced_at'] && strtotime($state['daily_synced_at']) > time() - PA_DAILY_TTL_MIN * 60) {
        return ['ok' => true, 'error' => null, 'refreshed' => false];
    }

    // Pinterest analytics dates are UTC and start_date may not be more than 90 days back.
    $end = gmdate('Y-m-d');
    $start = gmdate('Y-m-d', strtotime('-89 days'));
    $result = pa_api_get($pdo, $account, '/user_account/analytics', [
        'start_date' => $start,
        'end_date' => $end,
        'from_claimed_content' => 'BOTH',
        'pin_format' => 'ALL',
        'app_types' => 'ALL',
        'metric_types' => 'IMPRESSION,PIN_CLICK,OUTBOUND_CLICK,SAVE',
        'split_field' => 'NO_SPLIT',
    ]);

    if (!$result['ok']) {
        $err = pa_friendly_error($result);
        pa_sync_state_set($pdo, (int)$account['id'], ['last_error' => $err]);
        log_event($pdo, 'api', 'Pinterest analytics fetch failed for account #' . $account['id'] . ': ' . json_encode($result['data'] ?? $result['error']), (int)$account['user_id']);
        // Stale data is still better than nothing — only surface the error if we have none cached.
        return ['ok' => false, 'error' => $err, 'refreshed' => false];
    }

    $days = $result['data']['all']['daily_metrics'] ?? [];
    $upsert = $pdo->prepare("INSERT INTO pa_account_daily
            (pinterest_account_id, metric_date, impressions, pin_clicks, outbound_clicks, saves, data_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE impressions = VALUES(impressions), pin_clicks = VALUES(pin_clicks),
            outbound_clicks = VALUES(outbound_clicks), saves = VALUES(saves), data_status = VALUES(data_status)");
    foreach ($days as $d) {
        if (empty($d['date'])) continue;
        $m = is_array($d['metrics'] ?? null) ? $d['metrics'] : [];
        $upsert->execute([
            $account['id'], substr($d['date'], 0, 10),
            (int)(pa_metric($m, ['IMPRESSION']) ?? 0),
            (int)(pa_metric($m, ['PIN_CLICK']) ?? 0),
            (int)(pa_metric($m, ['OUTBOUND_CLICK']) ?? 0),
            (int)(pa_metric($m, ['SAVE']) ?? 0),
            $d['data_status'] ?? null,
        ]);
    }
    pa_sync_state_set($pdo, (int)$account['id'], ['daily_synced_at' => date('Y-m-d H:i:s'), 'last_error' => null]);
    return ['ok' => true, 'error' => null, 'refreshed' => true];
}

/**
 * Day-by-day series for [$start, $end] (inclusive, Y-m-d) from the local history, with
 * missing days filled as null (so the graph shows a gap instead of a fake zero).
 */
function pa_account_series(PDO $pdo, int $accountId, string $start, string $end): array
{
    $stmt = $pdo->prepare("SELECT * FROM pa_account_daily WHERE pinterest_account_id = ? AND metric_date BETWEEN ? AND ? ORDER BY metric_date");
    $stmt->execute([$accountId, $start, $end]);
    $byDate = [];
    foreach ($stmt->fetchAll() as $r) $byDate[$r['metric_date']] = $r;

    $out = [];
    $cur = strtotime($start);
    $last = strtotime($end);
    while ($cur <= $last) {
        $d = date('Y-m-d', $cur);
        $r = $byDate[$d] ?? null;
        $out[] = [
            'date' => $d,
            'has_data' => $r !== null,
            'processing' => $r ? ($r['data_status'] !== null && strtoupper($r['data_status']) !== 'READY') : false,
            'impressions' => $r ? (int)$r['impressions'] : null,
            'pin_clicks' => $r ? (int)$r['pin_clicks'] : null,
            'outbound_clicks' => $r ? (int)$r['outbound_clicks'] : null,
            'saves' => $r ? (int)$r['saves'] : null,
        ];
        $cur = strtotime('+1 day', $cur);
    }
    return $out;
}

/** Totals + rates for a series. 'coverage' = share of days that actually have data. */
function pa_series_totals(array $series): array
{
    $t = ['impressions' => 0, 'pin_clicks' => 0, 'outbound_clicks' => 0, 'saves' => 0];
    $withData = 0;
    foreach ($series as $d) {
        if (!$d['has_data']) continue;
        $withData++;
        foreach ($t as $k => $_) $t[$k] += (int)$d[$k];
    }
    $t['outbound_click_rate'] = $t['impressions'] > 0 ? $t['outbound_clicks'] / $t['impressions'] * 100 : 0;
    $t['save_rate'] = $t['impressions'] > 0 ? $t['saves'] / $t['impressions'] * 100 : 0;
    $t['pin_click_rate'] = $t['impressions'] > 0 ? $t['pin_clicks'] / $t['impressions'] * 100 : 0;
    $t['coverage'] = count($series) ? $withData / count($series) : 0;
    return $t;
}

/* ============================== Pin list sync (Top Pins) ============================== */

/** Best image URL from a v5 pin object. */
function pa_pin_image_url(array $pin): ?string
{
    $images = $pin['media']['images'] ?? [];
    foreach (['600x', '400x300', '1200x', '150x150', 'originals'] as $size) {
        if (!empty($images[$size]['url'])) return $images[$size]['url'];
    }
    if (!empty($pin['media']['cover_image_url'])) return $pin['media']['cover_image_url'];
    if (!empty($pin['media']['items'][0]['images']['600x']['url'])) return $pin['media']['items'][0]['images']['600x']['url'];
    return null;
}

/** Full-size image URL (used when the user keeps the original image while regenerating). */
function pa_pin_original_image_url(array $pin): ?string
{
    $images = $pin['media']['images'] ?? [];
    foreach (['originals', '1200x', '600x'] as $size) {
        if (!empty($images[$size]['url'])) return $images[$size]['url'];
    }
    return pa_pin_image_url($pin);
}

/**
 * 90-day totals for up to 100 pins in one call. Returns [pin_id => [imp, clicks, outbound, saves]]
 * or null if the endpoint isn't available to this app (then pin_metrics is used instead).
 */
function pa_multi_pin_totals(PDO $pdo, array &$account, array $pinIds): ?array
{
    if (empty($pinIds)) return [];
    $result = pa_api_get($pdo, $account, '/pins/analytics', [
        'pin_ids' => implode(',', $pinIds),
        'start_date' => gmdate('Y-m-d', strtotime('-89 days')),
        'end_date' => gmdate('Y-m-d'),
        'metric_types' => 'IMPRESSION,PIN_CLICK,OUTBOUND_CLICK,SAVE',
        'app_types' => 'ALL',
    ]);
    if (!$result['ok'] || !is_array($result['data'])) return null;

    $out = [];
    foreach ($result['data'] as $pinId => $node) {
        if (!is_array($node)) continue;
        $node = $node['all'] ?? $node;
        $summary = is_array($node['summary_metrics'] ?? null) ? $node['summary_metrics'] : null;
        if (!$summary && is_array($node['daily_metrics'] ?? null)) {
            $summary = ['IMPRESSION' => 0, 'PIN_CLICK' => 0, 'OUTBOUND_CLICK' => 0, 'SAVE' => 0];
            foreach ($node['daily_metrics'] as $d) {
                $m = is_array($d['metrics'] ?? null) ? $d['metrics'] : [];
                foreach ($summary as $k => $_) $summary[$k] += (float)(pa_metric($m, [$k]) ?? 0);
            }
        }
        if (!$summary) continue;
        $out[(string)$pinId] = [
            'imp' => (int)(pa_metric($summary, ['IMPRESSION']) ?? 0),
            'clicks' => (int)(pa_metric($summary, ['PIN_CLICK']) ?? 0),
            'outbound' => (int)(pa_metric($summary, ['OUTBOUND_CLICK']) ?? 0),
            'saves' => pa_metric($summary, ['SAVE']) === null ? null : (int)pa_metric($summary, ['SAVE']),
        ];
    }
    return $out;
}

/**
 * Sync ONE page (100 pins) of the account's own pins. Called repeatedly by the browser
 * (ajax-pa-sync.php) with the returned bookmark so no single request runs long.
 * Returns ['ok', 'error', 'bookmark' (next or null), 'count' (pins in this page)].
 */
function pa_sync_pins_page(PDO $pdo, array $account, ?string $bookmark, int $pageNum): array
{
    $accountId = (int)$account['id'];
    if ($pageNum === 1) {
        pa_sync_state_set($pdo, $accountId, ['pins_sync_started_at' => date('Y-m-d H:i:s'), 'last_error' => null]);
    }

    $query = ['page_size' => 100, 'pin_metrics' => 'true'];
    if ($bookmark) $query['bookmark'] = $bookmark;
    $result = pa_api_get($pdo, $account, '/pins', $query);
    if (!$result['ok']) {
        $err = pa_friendly_error($result);
        pa_sync_state_set($pdo, $accountId, ['last_error' => $err]);
        return ['ok' => false, 'error' => $err, 'bookmark' => null, 'count' => 0];
    }

    $items = $result['data']['items'] ?? [];
    $ids = array_values(array_filter(array_map(fn($p) => (string)($p['id'] ?? ''), $items)));

    // Accurate 90-day numbers (incl. saves) in one extra call; skipped for the rest of this
    // session once Pinterest says the endpoint isn't enabled for this app.
    $multi = null;
    if (empty($_SESSION['pa_multi_unavailable'])) {
        $multi = pa_multi_pin_totals($pdo, $account, $ids);
        if ($multi === null) $_SESSION['pa_multi_unavailable'] = 1;
    }

    $upsert = $pdo->prepare("INSERT INTO pa_pins
            (pinterest_account_id, pin_id, title, description, link, image_url, board_id, pin_created_at, is_own,
             imp_90, clicks_90, outbound_90, saves_90, imp_life, clicks_life, outbound_life, saves_life, synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), link = VALUES(link),
            image_url = VALUES(image_url), board_id = VALUES(board_id), pin_created_at = VALUES(pin_created_at), is_own = 1,
            imp_90 = VALUES(imp_90), clicks_90 = VALUES(clicks_90), outbound_90 = VALUES(outbound_90),
            saves_90 = COALESCE(VALUES(saves_90), saves_90),
            imp_life = VALUES(imp_life), clicks_life = VALUES(clicks_life), outbound_life = VALUES(outbound_life),
            saves_life = COALESCE(VALUES(saves_life), saves_life), synced_at = VALUES(synced_at)");

    $now = date('Y-m-d H:i:s');
    foreach ($items as $p) {
        $id = (string)($p['id'] ?? '');
        if ($id === '') continue;
        $m90 = is_array($p['pin_metrics']['90d'] ?? null) ? $p['pin_metrics']['90d'] : [];
        $mLife = is_array($p['pin_metrics']['lifetime_metrics'] ?? null) ? $p['pin_metrics']['lifetime_metrics'] : [];

        $imp = (int)(pa_metric($m90, ['impression']) ?? 0);
        $clicks = (int)(pa_metric($m90, ['pin_click']) ?? 0);
        $outbound = (int)(pa_metric($m90, ['clickthrough', 'outbound_click']) ?? 0);
        $saves = pa_metric($m90, ['save']);
        if (isset($multi[$id])) {
            $imp = $multi[$id]['imp'];
            $clicks = $multi[$id]['clicks'];
            $outbound = $multi[$id]['outbound'];
            $saves = $multi[$id]['saves'] ?? $saves;
        }

        $created = pa_api_time($p['created_at'] ?? null);
        $lifeImp = pa_metric($mLife, ['impression']);
        $lifeClicks = pa_metric($mLife, ['pin_click']);
        $lifeOut = pa_metric($mLife, ['clickthrough', 'outbound_click']);
        $lifeSaves = pa_metric($mLife, ['save']);

        $upsert->execute([
            $accountId, $id,
            mb_substr((string)($p['title'] ?? ''), 0, 500),
            (string)($p['description'] ?? ''),
            mb_substr((string)($p['link'] ?? ''), 0, 1000),
            pa_pin_image_url($p),
            $p['board_id'] ?? null,
            $created,
            $imp, $clicks, $outbound, $saves === null ? null : (int)$saves,
            $lifeImp === null ? null : (int)$lifeImp,
            $lifeClicks === null ? null : (int)$lifeClicks,
            $lifeOut === null ? null : (int)$lifeOut,
            $lifeSaves === null ? null : (int)$lifeSaves,
            $now,
        ]);
    }

    $next = $result['data']['bookmark'] ?? null;
    if ($pageNum >= PA_SYNC_MAX_PAGES) $next = null;
    return ['ok' => true, 'error' => null, 'bookmark' => $next ?: null, 'count' => count($items)];
}

/**
 * Final sync step: merge Pinterest's own "top pins" report (top 50 per metric) — this adds
 * accurate save counts for the best pins and pins that aren't on your own boards (e.g. saves
 * of your claimed website's content by other people). Then drop own pins that disappeared.
 */
function pa_sync_top_pins_and_finish(PDO $pdo, array $account): array
{
    $accountId = (int)$account['id'];
    $state = pa_sync_state($pdo, $accountId);
    $start = gmdate('Y-m-d', strtotime('-89 days'));
    $end = gmdate('Y-m-d');

    $top = [];
    foreach (['IMPRESSION', 'OUTBOUND_CLICK', 'SAVE', 'PIN_CLICK'] as $sort) {
        $r = pa_api_get($pdo, $account, '/user_account/analytics/top_pins', [
            'start_date' => $start, 'end_date' => $end, 'sort_by' => $sort,
            'from_claimed_content' => 'BOTH', 'pin_format' => 'ALL', 'app_types' => 'ALL',
            'metric_types' => 'IMPRESSION,PIN_CLICK,OUTBOUND_CLICK,SAVE', 'num_of_pins' => 50,
        ]);
        if (!$r['ok']) continue; // non-fatal: the own-pin list is already synced
        foreach ($r['data']['pins'] ?? [] as $tp) {
            $id = (string)($tp['pin_id'] ?? $tp['id'] ?? '');
            if ($id === '' || isset($top[$id])) continue;
            $m = is_array($tp['metrics'] ?? null) ? $tp['metrics'] : [];
            $top[$id] = [
                'imp' => (int)(pa_metric($m, ['IMPRESSION']) ?? 0),
                'clicks' => (int)(pa_metric($m, ['PIN_CLICK']) ?? 0),
                'outbound' => (int)(pa_metric($m, ['OUTBOUND_CLICK']) ?? 0),
                'saves' => (int)(pa_metric($m, ['SAVE']) ?? 0),
            ];
        }
    }

    $exists = $pdo->prepare("SELECT id FROM pa_pins WHERE pinterest_account_id = ? AND pin_id = ?");
    $update = $pdo->prepare("UPDATE pa_pins SET imp_90 = ?, clicks_90 = ?, outbound_90 = ?, saves_90 = ?, synced_at = ? WHERE pinterest_account_id = ? AND pin_id = ?");
    $insert = $pdo->prepare("INSERT INTO pa_pins (pinterest_account_id, pin_id, title, description, link, image_url, board_id, pin_created_at, is_own, imp_90, clicks_90, outbound_90, saves_90, synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE imp_90 = VALUES(imp_90), clicks_90 = VALUES(clicks_90), outbound_90 = VALUES(outbound_90), saves_90 = VALUES(saves_90), synced_at = VALUES(synced_at)");
    $now = date('Y-m-d H:i:s');
    $detailFetches = 0;
    foreach ($top as $id => $t) {
        $exists->execute([$accountId, $id]);
        if ($exists->fetchColumn()) {
            $update->execute([$t['imp'], $t['clicks'], $t['outbound'], $t['saves'], $now, $accountId, $id]);
            continue;
        }
        // Not one of the account's own pins — try to read its details (works for group boards;
        // other people's pins usually refuse, then we store a minimal placeholder row).
        $title = null; $desc = null; $link = null; $img = null; $board = null; $created = null;
        if ($detailFetches < 40) {
            $detailFetches++;
            $d = pa_api_get($pdo, $account, '/pins/' . rawurlencode($id));
            if ($d['ok'] && is_array($d['data'])) {
                $title = mb_substr((string)($d['data']['title'] ?? ''), 0, 500);
                $desc = (string)($d['data']['description'] ?? '');
                $link = mb_substr((string)($d['data']['link'] ?? ''), 0, 1000);
                $img = pa_pin_image_url($d['data']);
                $board = $d['data']['board_id'] ?? null;
                $created = pa_api_time($d['data']['created_at'] ?? null);
            }
        }
        $insert->execute([$accountId, $id, $title, $desc, $link, $img, $board, $created, $t['imp'], $t['clicks'], $t['outbound'], $t['saves'], $now]);
    }

    // Own pins not seen in this sync were deleted on Pinterest.
    if (!empty($state['pins_sync_started_at'])) {
        $pdo->prepare("DELETE FROM pa_pins WHERE pinterest_account_id = ? AND is_own = 1 AND (synced_at IS NULL OR synced_at < ?)")
            ->execute([$accountId, $state['pins_sync_started_at']]);
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM pa_pins WHERE pinterest_account_id = ?");
    $count->execute([$accountId]);
    $total = (int)$count->fetchColumn();
    pa_sync_state_set($pdo, $accountId, ['pins_synced_at' => $now, 'pins_count' => $total]);
    return ['ok' => true, 'total' => $total];
}

function pa_pins_need_sync(array $state): bool
{
    return empty($state['pins_synced_at']) || strtotime($state['pins_synced_at']) < time() - PA_PINS_TTL_HOURS * 3600;
}

/** Sort key → SQL ORDER BY (whitelisted). */
function pa_sort_sql(string $sort): string
{
    switch ($sort) {
        case 'clicks': return 'clicks_90 DESC, imp_90 DESC';
        case 'outbound': return 'outbound_90 DESC, imp_90 DESC';
        case 'saves': return 'COALESCE(saves_90, 0) DESC, imp_90 DESC';
        // CTR on a pin with 3 impressions means nothing — rank pins with real reach first.
        case 'ctr': return '(imp_90 >= 100) DESC, (outbound_90 / NULLIF(imp_90, 0)) DESC, imp_90 DESC';
        default: return 'imp_90 DESC, clicks_90 DESC';
    }
}

function pa_top_pins(PDO $pdo, int $accountId, string $sort, bool $ownOnly, int $limit = PA_TOP_LIMIT): array
{
    $limit = max(1, min(PA_TOP_LIMIT, $limit));
    $sql = "SELECT p.*, (SELECT COUNT(*) FROM pa_regenerated_pins r WHERE r.pinterest_account_id = p.pinterest_account_id AND r.source_pin_id = p.pin_id) AS regen_count
            FROM pa_pins p WHERE p.pinterest_account_id = ?" . ($ownOnly ? ' AND p.is_own = 1' : '') .
        " ORDER BY " . pa_sort_sql($sort) . " LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}

/* ============================== One pin's 90-day graph ============================== */

function pa_cache_get(PDO $pdo, int $accountId, string $key, int $ttlMinutes): ?array
{
    $stmt = $pdo->prepare("SELECT payload, fetched_at FROM pa_api_cache WHERE pinterest_account_id = ? AND cache_key = ?");
    $stmt->execute([$accountId, $key]);
    $row = $stmt->fetch();
    if (!$row || strtotime($row['fetched_at']) < time() - $ttlMinutes * 60) return null;
    $data = json_decode((string)$row['payload'], true);
    return is_array($data) ? $data : null;
}

function pa_cache_set(PDO $pdo, int $accountId, string $key, array $payload): void
{
    $pdo->prepare("INSERT INTO pa_api_cache (pinterest_account_id, cache_key, payload, fetched_at) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = VALUES(fetched_at)")
        ->execute([$accountId, $key, json_encode($payload), date('Y-m-d H:i:s')]);
}

/** Daily IMPRESSION/PIN_CLICK/OUTBOUND_CLICK/SAVE for one pin over the last 90 days. */
function pa_pin_daily(PDO $pdo, array $account, string $pinId): array
{
    $key = 'pin90:' . $pinId;
    $cached = pa_cache_get($pdo, (int)$account['id'], $key, PA_PIN_GRAPH_TTL_MIN);
    if ($cached) return ['ok' => true, 'days' => $cached, 'error' => null];

    $result = pa_api_get($pdo, $account, '/pins/' . rawurlencode($pinId) . '/analytics', [
        'start_date' => gmdate('Y-m-d', strtotime('-89 days')),
        'end_date' => gmdate('Y-m-d'),
        'metric_types' => 'IMPRESSION,PIN_CLICK,OUTBOUND_CLICK,SAVE',
        'app_types' => 'ALL',
        'split_field' => 'NO_SPLIT',
    ]);
    if (!$result['ok']) {
        return ['ok' => false, 'days' => [], 'error' => pa_friendly_error($result)];
    }
    $days = [];
    foreach ($result['data']['all']['daily_metrics'] ?? [] as $d) {
        if (empty($d['date'])) continue;
        $m = is_array($d['metrics'] ?? null) ? $d['metrics'] : [];
        $days[] = [
            'date' => substr($d['date'], 0, 10),
            'processing' => !empty($d['data_status']) && strtoupper($d['data_status']) !== 'READY',
            'impressions' => (int)(pa_metric($m, ['IMPRESSION']) ?? 0),
            'pin_clicks' => (int)(pa_metric($m, ['PIN_CLICK']) ?? 0),
            'outbound_clicks' => (int)(pa_metric($m, ['OUTBOUND_CLICK']) ?? 0),
            'saves' => (int)(pa_metric($m, ['SAVE']) ?? 0),
        ];
    }
    pa_cache_set($pdo, (int)$account['id'], $key, $days);
    return ['ok' => true, 'days' => $days, 'error' => null];
}

/* ============================== Regenerate → schedule ============================== */

/**
 * Download a Pinterest-hosted image into uploads/pins so it can be re-published
 * (pinterest_create_pin() needs an image on this site). Only i.pinimg.com is allowed.
 */
function pa_download_pin_image(string $url): array
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!preg_match('#^https://#i', $url) || !preg_match('/(^|\.)pinimg\.com$/', $host)) {
        return ['ok' => false, 'path' => null, 'error' => 'Only the original Pinterest image can be reused.'];
    }
    $fetch = function (string $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 30]);
        $b = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($b === false || $c >= 300 || strlen($b) < 500) ? null : $b;
    };
    // The cached URL is the 600px preview — try Pinterest's full-size "originals" copy first.
    $bytes = null;
    $full = preg_replace('#^(https://i\.pinimg\.com/)[^/]+/#i', '$1originals/', $url);
    if ($full && $full !== $url) $bytes = $fetch($full);
    if ($bytes === null) $bytes = $fetch($url);
    if ($bytes === null) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not download the original pin image.'];
    }
    $info = @getimagesizefromstring($bytes);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($map[$info['mime']])) {
        return ['ok' => false, 'path' => null, 'error' => 'The original pin image is not a supported image type.'];
    }
    $dir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'regen_' . bin2hex(random_bytes(8)) . '.' . $map[$info['mime']];
    file_put_contents($dir . $name, $bytes);
    return ['ok' => true, 'path' => 'uploads/pins/' . $name, 'error' => null];
}

/** A pin image path produced by this app (uploads/pins/<file>) that exists on disk. */
function pa_valid_local_pin_image(string $path): bool
{
    if (!preg_match('#^uploads/pins/[A-Za-z0-9_\-]+\.(jpe?g|png|webp|gif)$#', $path)) return false;
    return is_file(__DIR__ . '/../' . $path);
}

/**
 * Publish one scheduled_pins row right away (same rules as cron/scheduler.php). A board
 * that is still 'pending_creation' is created first. Leaves the row 'pending' for the cron
 * to retry if Pinterest fails, so nothing is lost.
 */
function pa_publish_pin_now(PDO $pdo, int $scheduledPinId): array
{
    // Delegates to the shared publisher (atomic claim, board creation, 2787 retry-with-upload, back-off).
    $r = publish_one_scheduled_pin($pdo, $scheduledPinId);
    if ($r['status'] === 'published') {
        $s = $pdo->prepare("SELECT pinterest_pin_id FROM scheduled_pins WHERE id = ?");
        $s->execute([$scheduledPinId]);
        return ['ok' => true, 'error' => null, 'pinterest_pin_id' => $s->fetchColumn()];
    }
    if ($r['status'] === 'skipped') {
        return ['ok' => false, 'error' => 'The board isn\'t ready on Pinterest yet — the pin stays queued and will publish automatically.'];
    }
    if ($r['status'] === 'retry') {
        return ['ok' => false, 'error' => 'Pinterest had a temporary problem. The pin stays queued and the scheduler will retry it.'];
    }
    return ['ok' => false, 'error' => $r['message']];
}

/* ============================== Trends ============================== */

const PA_TRENDS_PINS = 50;           // "Top 50 pins" compared on the Trends tab
const PA_TRENDS_TTL_HOURS = 6;
const PA_TRENDS_PROCESSING_DAYS = 2; // Pinterest's last 2 days are still processing — leave them out

/** The account's top own pins by 90-day impressions — the set Trends compares. */
function pa_trends_pin_ids(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("SELECT pin_id FROM pa_pins WHERE pinterest_account_id = ? AND is_own = 1 ORDER BY imp_90 DESC, clicks_90 DESC LIMIT " . PA_TRENDS_PINS);
    $stmt->execute([$accountId]);
    return array_column($stmt->fetchAll(), 'pin_id');
}

/**
 * Daily metrics for up to 100 pins in one call. Returns [pin_id => [[date, imp, clicks, outbound, saves], ...]]
 * or null when the multi-pin endpoint isn't available / doesn't return daily rows.
 */
function pa_multi_pin_daily(PDO $pdo, array &$account, array $pinIds): ?array
{
    if (empty($pinIds)) return [];
    $result = pa_api_get($pdo, $account, '/pins/analytics', [
        'pin_ids' => implode(',', $pinIds),
        'start_date' => gmdate('Y-m-d', strtotime('-89 days')),
        'end_date' => gmdate('Y-m-d'),
        'metric_types' => 'IMPRESSION,PIN_CLICK,OUTBOUND_CLICK,SAVE',
        'app_types' => 'ALL',
    ]);
    if (!$result['ok'] || !is_array($result['data'])) return null;
    $out = [];
    $anyRows = false;
    foreach ($result['data'] as $pinId => $node) {
        if (!is_array($node)) continue;
        $node = $node['all'] ?? $node;
        if (!is_array($node['daily_metrics'] ?? null)) continue;
        $rows = [];
        foreach ($node['daily_metrics'] as $d) {
            if (empty($d['date'])) continue;
            $m = is_array($d['metrics'] ?? null) ? $d['metrics'] : [];
            $rows[] = [substr($d['date'], 0, 10), (int)(pa_metric($m, ['IMPRESSION']) ?? 0), (int)(pa_metric($m, ['PIN_CLICK']) ?? 0),
                (int)(pa_metric($m, ['OUTBOUND_CLICK']) ?? 0), (int)(pa_metric($m, ['SAVE']) ?? 0)];
        }
        $out[(string)$pinId] = $rows;
        if ($rows) $anyRows = true;
    }
    // Summary-only answers (no daily rows) can't power Trends — fall back to per-pin calls.
    return ($out && $anyRows) ? $out : null;
}

function pa_store_pin_days(PDO $pdo, int $accountId, string $pinId, array $rows): void
{
    static $stmt = null;
    if (!$stmt) {
        $stmt = $pdo->prepare("INSERT INTO pa_pin_daily (pinterest_account_id, pin_id, metric_date, impressions, pin_clicks, outbound_clicks, saves)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE impressions = VALUES(impressions), pin_clicks = VALUES(pin_clicks),
                outbound_clicks = VALUES(outbound_clicks), saves = VALUES(saves)");
    }
    foreach ($rows as $r) $stmt->execute([$accountId, $pinId, $r[0], $r[1], $r[2], $r[3], $r[4]]);
}

function pa_trends_needs_sync(PDO $pdo, int $accountId): bool
{
    return pa_cache_get($pdo, $accountId, 'trends:synced', PA_TRENDS_TTL_HOURS * 60) === null;
}

/**
 * One step of the Trends sync, called repeatedly by the browser. Uses the multi-pin endpoint
 * (100 pins per call) when available, otherwise 8 single-pin calls per step.
 * Returns ['ok', 'error', 'next' (offset or null when done), 'total'].
 */
function pa_trends_sync_step(PDO $pdo, array $account, int $offset): array
{
    $accountId = (int)$account['id'];
    $ids = pa_trends_pin_ids($pdo, $accountId);
    $total = count($ids);
    if ($offset >= $total) {
        pa_cache_set($pdo, $accountId, 'trends:synced', ['at' => date('Y-m-d H:i:s')]);
        return ['ok' => true, 'error' => null, 'next' => null, 'total' => $total];
    }

    if (empty($_SESSION['pa_multi_daily_unavailable'])) {
        $chunk = array_slice($ids, $offset, 100);
        $data = pa_multi_pin_daily($pdo, $account, $chunk);
        if ($data !== null) {
            foreach ($data as $pinId => $rows) pa_store_pin_days($pdo, $accountId, (string)$pinId, $rows);
            $next = $offset + count($chunk);
            return ['ok' => true, 'error' => null, 'next' => $next, 'total' => $total];
        }
        $_SESSION['pa_multi_daily_unavailable'] = 1;
    }

    $chunk = array_slice($ids, $offset, 8);
    $errors = 0;
    $lastError = null;
    foreach ($chunk as $pinId) {
        $r = pa_pin_daily($pdo, $account, $pinId);
        if (!$r['ok']) { $errors++; $lastError = $r['error']; continue; }
        pa_store_pin_days($pdo, $accountId, $pinId, array_map(fn($d) => [$d['date'], $d['impressions'], $d['pin_clicks'], $d['outbound_clicks'], $d['saves']], $r['days']));
    }
    if ($errors === count($chunk) && $lastError) {
        return ['ok' => false, 'error' => $lastError, 'next' => null, 'total' => $total];
    }
    return ['ok' => true, 'error' => null, 'next' => $offset + count($chunk), 'total' => $total];
}

/**
 * Compare the last $days (ending before Pinterest's still-processing days) with the $days
 * before that, for each of the top pins. Totals are compared, which equals comparing the
 * average daily performance because both windows have the same length.
 */
function pa_trends_compute(PDO $pdo, int $accountId, int $days): array
{
    $end = date('Y-m-d', strtotime('-' . PA_TRENDS_PROCESSING_DAYS . ' days'));
    $curStart = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
    $prevEnd = date('Y-m-d', strtotime($curStart . ' -1 day'));
    $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));

    $ids = pa_trends_pin_ids($pdo, $accountId);
    if (!$ids) return ['pins' => [], 'window' => compact('curStart', 'end', 'prevStart', 'prevEnd'), 'history_days' => 0, 'prev_complete' => false];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT pin_id, metric_date, impressions, pin_clicks, outbound_clicks, saves FROM pa_pin_daily
        WHERE pinterest_account_id = ? AND pin_id IN ($in) AND metric_date BETWEEN ? AND ?");
    $stmt->execute(array_merge([$accountId], $ids, [$prevStart, $end]));
    $byPin = [];
    foreach ($stmt->fetchAll() as $r) $byPin[$r['pin_id']][$r['metric_date']] = $r;

    // How far back per-pin history actually goes (decides whether the previous window is complete).
    $h = $pdo->prepare("SELECT MIN(metric_date) FROM pa_pin_daily WHERE pinterest_account_id = ?");
    $h->execute([$accountId]);
    $oldest = $h->fetchColumn();
    $historyDays = $oldest ? (int)((strtotime($end) - strtotime($oldest)) / 86400) + 1 : 0;
    $prevComplete = $oldest && $oldest <= $prevStart;

    $meta = $pdo->prepare("SELECT * FROM pa_pins WHERE pinterest_account_id = ? AND pin_id IN ($in)");
    $meta->execute(array_merge([$accountId], $ids));
    $pinsMeta = [];
    foreach ($meta->fetchAll() as $m) $pinsMeta[$m['pin_id']] = $m;

    $pins = [];
    foreach ($ids as $pinId) {
        if (!isset($pinsMeta[$pinId])) continue;
        $m = $pinsMeta[$pinId];
        $rows = $byPin[$pinId] ?? [];
        $cur = ['imp' => [], 'clk' => []];
        $prev = ['imp' => [], 'clk' => []];
        for ($i = 0; $i < $days; $i++) {
            $dc = date('Y-m-d', strtotime($curStart . " +$i days"));
            $dp = date('Y-m-d', strtotime($prevStart . " +$i days"));
            $cur['imp'][] = isset($rows[$dc]) ? (int)$rows[$dc]['impressions'] : 0;
            $cur['clk'][] = isset($rows[$dc]) ? (int)$rows[$dc]['pin_clicks'] + (int)$rows[$dc]['outbound_clicks'] : 0;
            $prev['imp'][] = isset($rows[$dp]) ? (int)$rows[$dp]['impressions'] : 0;
            $prev['clk'][] = isset($rows[$dp]) ? (int)$rows[$dp]['pin_clicks'] + (int)$rows[$dp]['outbound_clicks'] : 0;
        }
        $pins[] = [
            'pin_id' => $pinId,
            'title' => $m['title'] ?: '',
            'description' => $m['description'] ?: '',
            'link' => $m['link'] ?: '',
            'image_url' => $m['image_url'] ?: '',
            'board_id' => $m['board_id'] ?: '',
            'created_at' => $m['pin_created_at'] ? date('M j, Y', strtotime($m['pin_created_at'])) : null,
            'pin_created_at' => $m['pin_created_at'],
            'impressions' => (int)$m['imp_90'],
            'clicks' => (int)$m['clicks_90'],
            'outbound' => (int)$m['outbound_90'],
            'saves' => $m['saves_90'] === null ? null : (int)$m['saves_90'],
            'url' => 'https://www.pinterest.com/pin/' . rawurlencode($pinId) . '/',
            'cur_imp' => $cur['imp'], 'prev_imp' => $prev['imp'],
            'cur_clk' => $cur['clk'], 'prev_clk' => $prev['clk'],
        ];
    }
    return [
        'pins' => $pins,
        'window' => ['cur_start' => $curStart, 'cur_end' => $end, 'prev_start' => $prevStart, 'prev_end' => $prevEnd],
        'history_days' => $historyDays,
        'prev_complete' => (bool)$prevComplete,
    ];
}

/* ============================== Delete Underperforming Pins ============================== */

/**
 * The account's own pins created more than $ageDays ago that are not queued/deleted yet,
 * with the impression figure used for ranking: lifetime when Pinterest has it, else 90 days.
 */
function pa_underperforming_candidates(PDO $pdo, int $accountId, int $ageDays): array
{
    $cutoff = date('Y-m-d H:i:s', strtotime("-$ageDays days"));
    $stmt = $pdo->prepare("SELECT p.* FROM pa_pins p
        WHERE p.pinterest_account_id = ? AND p.is_own = 1 AND p.pin_created_at IS NOT NULL AND p.pin_created_at < ?
          AND NOT EXISTS (SELECT 1 FROM pa_delete_queue q WHERE q.pinterest_account_id = p.pinterest_account_id AND q.pin_id = p.pin_id AND q.status IN ('queued','deleted'))
        ORDER BY p.link, p.pin_created_at DESC LIMIT 10000");
    $stmt->execute([$accountId, $cutoff]);
    return array_map(function ($p) {
        $life = $p['imp_life'] !== null;
        $imp = $life ? (int)$p['imp_life'] : (int)$p['imp_90'];
        $clicks = $p['clicks_life'] !== null ? (int)$p['clicks_life'] : (int)$p['clicks_90'];
        $outbound = $p['outbound_life'] !== null ? (int)$p['outbound_life'] : (int)$p['outbound_90'];
        $saves = $p['saves_life'] !== null ? (int)$p['saves_life'] : ($p['saves_90'] === null ? null : (int)$p['saves_90']);
        return [
            'pin_id' => $p['pin_id'],
            'title' => $p['title'] ?: '',
            'link' => $p['link'] ?: '',
            'image_url' => $p['image_url'] ?: '',
            'created' => $p['pin_created_at'] ? date('n/j/Y', strtotime($p['pin_created_at'])) : '',
            'impressions' => $imp,
            'clicks' => $clicks,
            'outbound' => $outbound,
            'saves' => $saves,
            'lifetime' => $life,
            'url' => 'https://www.pinterest.com/pin/' . rawurlencode($p['pin_id']) . '/',
        ];
    }, $stmt->fetchAll());
}

function pa_median(array $values): float
{
    if (!$values) return 0;
    sort($values);
    $n = count($values);
    $mid = intdiv($n, 2);
    return $n % 2 ? (float)$values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
}

function pa_percentile(array $values, float $p): float
{
    if (!$values) return 0;
    sort($values);
    $idx = ($p / 100) * (count($values) - 1);
    $lo = (int)floor($idx);
    $hi = (int)ceil($idx);
    return $values[$lo] + ($values[$hi] - $values[$lo]) * ($idx - $lo);
}

/** Permanently delete a pin on Pinterest (needs the pins:write scope the app already requests). */
function pa_delete_pin_api(PDO $pdo, array $account, string $pinId): array
{
    $account = pinterest_ensure_fresh_token($pdo, $account);
    $r = pinterest_http_request('DELETE', PINTEREST_API_BASE . '/pins/' . rawurlencode($pinId), [
        'Authorization: Bearer ' . $account['access_token'],
    ]);
    // Already gone on Pinterest counts as deleted.
    if ($r['ok'] || (int)$r['code'] === 404) return ['ok' => true, 'error' => null];
    return ['ok' => false, 'error' => pa_friendly_error($r)];
}

/* ============================== Regen Drafts ============================== */

function pa_draft_row_to_array(array $d): array
{
    $source = json_decode((string)$d['source_json'], true);
    return [
        'draft_id' => (int)$d['id'],
        'source_pin_id' => $d['source_pin_id'],
        'source' => is_array($source) ? $source : null,
        'title' => $d['title'] ?? '',
        'description' => $d['description'] ?? '',
        'link' => $d['link'] ?? '',
        'alt_text' => $d['alt_text'] ?? '',
        'keywords' => $d['keywords'] ?? '',
        'image_mode' => $d['image_mode'],
        'image_path' => ($d['image_path'] && pa_valid_local_pin_image($d['image_path'])) ? $d['image_path'] : '',
        'board' => $d['board'] ?? '',
        'new_board_name' => $d['new_board_name'] ?? '',
        'updated_at' => format_datetime($d['updated_at']),
    ];
}

function pa_drafts_list(PDO $pdo, int $userId, int $accountId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pa_regen_drafts WHERE user_id = ? AND pinterest_account_id = ? ORDER BY updated_at DESC LIMIT 500");
    $stmt->execute([$userId, $accountId]);
    return array_map('pa_draft_row_to_array', $stmt->fetchAll());
}

function pa_drafts_count(PDO $pdo, int $userId, int $accountId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pa_regen_drafts WHERE user_id = ? AND pinterest_account_id = ?");
        $stmt->execute([$userId, $accountId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0; // tables not migrated yet
    }
}

function pa_delete_queue_count(PDO $pdo, int $accountId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pa_delete_queue WHERE pinterest_account_id = ? AND status IN ('queued','failed')");
        $stmt->execute([$accountId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ============================== Keyword Research (Analytics → Keyword Research) ==============================
 * Official Pinterest Trends API only:
 *   GET /trends/keywords/{region}/top/{trend_type}   top 50 trending keywords per call, with
 *       week/month/year growth % and a 52-week relative-interest curve (0-100)
 *   GET /terms/suggested, /terms/related              extra keyword ideas (needs the ads:read
 *       scope, which this app doesn't request — used only when Pinterest allows it)
 * Pinterest does not publish absolute search counts ("58m people searched") through its API,
 * so popularity = trend rank + growth + the interest curve.
 * Each search builds a keyword pool from a list of "sources" (API calls), fetched a few at a
 * time as the user clicks Load more, cached 6 hours.
 */
const PA_KW_REGIONS = ['US' => 'United States', 'CA' => 'Canada', 'GB+IE' => 'UK & Ireland', 'AU+NZ' => 'Australia & New Zealand',
    'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'DE+AT+CH' => 'Germany, Austria & Switzerland',
    'IT+ES+PT+GR+MT' => 'Southern Europe', 'PL+RO+HU+SK+CZ' => 'Central & Eastern Europe', 'SE+DK+FI+NO' => 'Nordics',
    'NL+BE+LU' => 'Benelux', 'AR' => 'Argentina', 'BR' => 'Brazil', 'CO' => 'Colombia', 'MX' => 'Mexico', 'MX+AR+CO+CL' => 'Latin America'];
const PA_KW_TYPES = ['monthly' => 'Monthly (high volume last month)', 'yearly' => 'Yearly (high volume last year)',
    'growing' => 'Growing (fast growth last quarter)', 'seasonal' => 'Seasonal (rising this season)'];
const PA_KW_INTERESTS = ['animals' => 'Animals', 'architecture' => 'Architecture', 'art' => 'Art', 'beauty' => 'Beauty',
    'childrens_fashion' => "Children's Fashion", 'design' => 'Design', 'diy_and_crafts' => 'DIY & Crafts', 'education' => 'Education',
    'electronics' => 'Electronics', 'entertainment' => 'Entertainment', 'event_planning' => 'Event Planning', 'finance' => 'Finance',
    'food_and_drinks' => 'Food & Drink', 'gardening' => 'Gardening', 'health' => 'Health', 'home_decor' => 'Home Decor',
    'mens_fashion' => "Men's Fashion", 'parenting' => 'Parenting', 'quotes' => 'Quotes', 'sport' => 'Sports', 'travel' => 'Travel',
    'vehicles' => 'Vehicles', 'wedding' => 'Wedding', 'womens_fashion' => "Women's Fashion"];
const PA_KW_CALLS_PER_REQUEST = 6;
const PA_KW_TTL_MIN = 360;

/** The ordered list of API calls that feed one search's keyword pool. */
function pa_kw_sources(string $type, string $interest, string $query): array
{
    $types = array_values(array_unique(array_merge([$type], array_keys(PA_KW_TYPES))));
    $words = [];
    if ($query !== '') {
        foreach (preg_split('/\s+/u', mb_strtolower($query)) as $w) {
            if (mb_strlen($w) >= 3) $words[] = $w;
        }
    }
    $sources = [];
    foreach ($types as $t) $sources[] = ['type' => $t, 'interest' => $interest, 'kw' => $query !== '' ? [$query] : []];
    if ($query !== '') {
        $sources[] = ['terms' => 'suggested', 'term' => $query];
        $sources[] = ['terms' => 'related', 'term' => $query];
        if (count($words) > 1) {
            foreach ($types as $t) $sources[] = ['type' => $t, 'interest' => $interest, 'kw' => $words];
        }
    }
    if ($interest === '') {
        foreach (array_keys(PA_KW_INTERESTS) as $i) {
            $sources[] = ['type' => $type, 'interest' => $i, 'kw' => $query !== '' ? ($words ?: [$query]) : []];
        }
    }
    return $sources;
}

/** Run one source. Returns ['ok', 'error', 'code', 'rows' => [keyword rows]]. */
function pa_kw_fetch_source(PDO $pdo, array &$account, string $region, array $src): array
{
    if (isset($src['terms'])) {
        $path = $src['terms'] === 'suggested' ? '/terms/suggested' : '/terms/related';
        $q = $src['terms'] === 'suggested' ? ['term' => $src['term'], 'limit' => 50] : ['terms' => $src['term']];
        $r = pa_api_get($pdo, $account, $path, $q);
        if (!$r['ok']) return ['ok' => false, 'error' => pa_friendly_error($r), 'code' => (int)$r['code'], 'rows' => []];
        $terms = [];
        if ($src['terms'] === 'suggested') {
            $terms = is_array($r['data']) ? array_values(array_filter($r['data'], 'is_string')) : [];
        } else {
            foreach ($r['data']['related_terms_list'] ?? [] as $grp) {
                foreach ($grp['related_terms'] ?? [] as $t) if (is_string($t)) $terms[] = $t;
            }
        }
        return ['ok' => true, 'error' => null, 'code' => 200, 'rows' => array_map(fn($t) => [
            'keyword' => $t, 'wow' => null, 'mom' => null, 'yoy' => null, 'series' => [], 'now' => null,
            'rank' => null, 'trend_type' => null, 'source' => $src['terms'],
        ], $terms)];
    }

    $q = ['limit' => 50];
    if ($src['interest'] !== '') $q['interests'] = $src['interest'];
    if ($src['kw']) $q['include_keywords'] = implode(',', $src['kw']);
    $r = pa_api_get($pdo, $account, '/trends/keywords/' . rawurlencode($region) . '/top/' . rawurlencode($src['type']), $q);
    if (!$r['ok']) return ['ok' => false, 'error' => pa_friendly_error($r), 'code' => (int)$r['code'], 'rows' => []];
    $rows = [];
    foreach ($r['data']['trends'] ?? [] as $i => $t) {
        if (empty($t['keyword'])) continue;
        $series = is_array($t['time_series'] ?? null) ? $t['time_series'] : [];
        ksort($series);
        $vals = array_map('intval', array_values($series));
        // Keep ~26 points — enough for a sparkline, small enough to cache.
        if (count($vals) > 26) {
            $step = count($vals) / 26;
            $vals = array_map(fn($k) => $vals[(int)floor($k * $step)], range(0, 25));
        }
        $rows[] = [
            'keyword' => (string)$t['keyword'],
            'wow' => isset($t['pct_growth_wow']) ? (int)$t['pct_growth_wow'] : null,
            'mom' => isset($t['pct_growth_mom']) ? (int)$t['pct_growth_mom'] : null,
            'yoy' => isset($t['pct_growth_yoy']) ? (int)$t['pct_growth_yoy'] : null,
            'series' => $vals,
            'now' => $vals ? end($vals) : null,
            'rank' => $i + 1,
            'trend_type' => $src['type'],
            'interest' => $src['interest'],
            'source' => 'trends',
        ];
    }
    return ['ok' => true, 'error' => null, 'code' => 200, 'rows' => $rows];
}

/**
 * The keyword pool for a search, grown until it holds at least $need rows that pass $filter
 * (or every source is used). Returns ['ok', 'error', 'pool', 'done', 'notes'].
 */
function pa_kw_pool(PDO $pdo, array $account, string $region, string $type, string $interest, string $query, int $need, callable $filter, bool $refresh): array
{
    $accountId = (int)$account['id'];
    $key = 'kw:' . md5(json_encode([$region, $type, $interest, mb_strtolower($query)]));
    $state = $refresh ? null : pa_cache_get($pdo, $accountId, $key, PA_KW_TTL_MIN);
    if (!$state) $state = ['pool' => [], 'next' => 0, 'notes' => [], 'trend_ok' => false];

    $sources = pa_kw_sources($type, $interest, $query);
    $calls = 0;
    $lastError = null;
    while ($state['next'] < count($sources) && $calls < PA_KW_CALLS_PER_REQUEST
        && count(array_filter($state['pool'], $filter)) < $need) {
        $src = $sources[$state['next']];
        $state['next']++;
        $calls++;
        $r = pa_kw_fetch_source($pdo, $account, $region, $src);
        if (!$r['ok']) {
            if (isset($src['terms'])) {
                // Terms endpoints need an ads permission — quietly skip them when refused.
                continue;
            }
            $lastError = $r['error'];
            if (in_array($r['code'], [401, 403], true) && !$state['trend_ok']) {
                // The Trends API itself is refused — no point trying the remaining sources now.
                return ['ok' => false, 'error' => $r['error'] . ' If this persists, Pinterest may not have enabled the Trends API for this app yet (it is part of Standard API access).', 'pool' => [], 'done' => true, 'notes' => []];
            }
            if ($r['code'] === 429) { $state['next']--; break; }
            continue;
        }
        if (!isset($src['terms'])) $state['trend_ok'] = true;
        $seen = [];
        foreach ($state['pool'] as $i => $row) $seen[mb_strtolower($row['keyword'])] = $i;
        foreach ($r['rows'] as $row) {
            $k = mb_strtolower($row['keyword']);
            if (isset($seen[$k])) {
                // A suggested term later found in the trends data gets the trend numbers.
                if ($state['pool'][$seen[$k]]['source'] !== 'trends' && $row['source'] === 'trends') $state['pool'][$seen[$k]] = $row;
                continue;
            }
            $seen[$k] = count($state['pool']);
            $state['pool'][] = $row;
        }
    }
    pa_cache_set($pdo, $accountId, $key, $state);
    if (!$state['pool'] && $lastError) return ['ok' => false, 'error' => $lastError, 'pool' => [], 'done' => true, 'notes' => []];
    return ['ok' => true, 'error' => null, 'pool' => $state['pool'], 'done' => $state['next'] >= count($sources), 'notes' => $state['notes']];
}
