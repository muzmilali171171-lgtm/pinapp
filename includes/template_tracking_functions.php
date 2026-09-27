<?php
/**
 * Analytics → Template Tracking.
 *
 * Which template made each published pin, and how those pins perform (views, clicks, CTR, save %),
 * grouped by template — and inside a template, by the pin's main colour.
 *
 * How a pin is linked to its template:
 *  - Image Styles & Templates (AI pin image, Bulk, Website → Daily Pin, Auto Article): every image
 *    made by compose_pin_image() is fingerprinted (md5 of the file bytes) with its template key in
 *    pin_image_templates. The saved pin image has exactly those bytes, so it matches later.
 *  - Classic Wizard: cw_pins.template_id + cw_pins.scheduled_pin_id (works for old pins too).
 *  - Custom designs (Design editor → Use This Design): fingerprinted with the design's id + title.
 * Each published pin is resolved once into pin_template_links (with its main colour).
 *
 * Metrics come from the Pinterest Analytics cache: pa_pins (lifetime / 90 days) and
 * pa_pin_daily (per-day, for "last 30 days" and custom ranges).
 */

const TT_COLORS = [
    'Red' => '#e53935', 'Orange' => '#fb8c00', 'Yellow' => '#fdd835', 'Green' => '#43a047', 'Teal' => '#00897b',
    'Blue' => '#1e88e5', 'Navy' => '#1a237e', 'Purple' => '#8e24aa', 'Pink' => '#ec407a', 'Brown' => '#6d4c41',
    'Beige' => '#e8d8b8', 'Black' => '#111111', 'White' => '#fafafa', 'Gray' => '#9e9e9e',
];
const TT_SOURCES = [
    'style' => 'Image Styles & Templates',
    'cw' => 'Classic Wizard',
    'design' => 'Custom Design',
    'none' => 'No template',
];

function tt_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_tt_v1';
    if (is_file($flag)) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pin_image_templates (
            image_hash CHAR(32) NOT NULL PRIMARY KEY,
            source VARCHAR(20) NOT NULL,
            template_key VARCHAR(120) NOT NULL,
            template_name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pin_template_links (
            scheduled_pin_id INT NOT NULL PRIMARY KEY,
            pinterest_account_id INT NOT NULL,
            pin_id VARCHAR(100) NOT NULL,
            source VARCHAR(20) NOT NULL,
            template_key VARCHAR(120) NOT NULL,
            template_name VARCHAR(255) DEFAULT NULL,
            color_name VARCHAR(20) DEFAULT NULL,
            color_hex CHAR(7) DEFAULT NULL,
            resolved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tt_tpl (pinterest_account_id, template_key),
            KEY idx_tt_pin (pinterest_account_id, pin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* retried next time */ }
}

/** Remembers which template produced these exact image bytes. Never throws. */
function tt_record_image(string $bytes, string $source, string $key, ?string $name): void
{
    if (!empty($GLOBALS['TT_SKIP']) || $bytes === '' || $key === '') return;
    global $pdo;
    if (!($pdo instanceof PDO)) return;
    try {
        tt_ensure_schema($pdo);
        $pdo->prepare("INSERT IGNORE INTO pin_image_templates (image_hash, source, template_key, template_name) VALUES (?, ?, ?, ?)")
            ->execute([md5($bytes), $source, mb_substr($key, 0, 120), $name !== null ? mb_substr($name, 0, 255) : null]);
    } catch (Throwable $e) { /* tracking must never break pin creation */ }
}

/** Classic Wizard template names, read from the template list in assets/js/cw-engine.js. */
function tt_cw_template_names(): array
{
    static $names = null;
    if ($names !== null) return $names;
    $names = [];
    $js = @file_get_contents(__DIR__ . '/../assets/js/cw-engine.js');
    if ($js && preg_match_all("/T\\('([A-Za-z0-9_-]+)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", $js, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) $names[$row[1]] = stripslashes($row[2]);
    }
    return $names;
}

function tt_template_label(PDO $pdo, string $source, string $key, ?string $stored, int $userId): string
{
    if ($source === 'cw') {
        if (preg_match('/^c(\d+)$/', $key, $mm)) {
            $s = $pdo->prepare("SELECT name FROM cw_custom_templates WHERE id = ? AND user_id = ?");
            $s->execute([(int)$mm[1], $userId]);
            $n = $s->fetchColumn();
            return $n ? $n . ' (Canva import)' : 'Custom template ' . $key;
        }
        $names = tt_cw_template_names();
        return $names[$key] ?? ('Template ' . $key);
    }
    if ($source === 'style' && function_exists('pin_template_registry')) {
        $reg = pin_template_registry();
        if (isset($reg[$key])) return $reg[$key]['name'];
    }
    return $stored ?: ($source === 'none' ? 'No template (own image)' : $key);
}

/* ---------- main colour of an image ---------- */

function tt_color_bucket(int $r, int $g, int $b): string
{
    $max = max($r, $g, $b); $min = min($r, $g, $b);
    $v = $max / 255; $d = $max - $min; $s = $max ? $d / $max : 0;
    if ($v < 0.16) return 'Black';
    if ($s < 0.14) return $v > 0.9 ? 'White' : 'Gray';
    if ($d == 0) $h = 0;
    elseif ($max === $r) $h = 60 * fmod((($g - $b) / $d), 6);
    elseif ($max === $g) $h = 60 * ((($b - $r) / $d) + 2);
    else $h = 60 * ((($r - $g) / $d) + 4);
    if ($h < 0) $h += 360;
    if ($s < 0.35 && $v > 0.75 && $h >= 20 && $h < 60) return 'Beige';
    if ($h < 15 || $h >= 345) return ($v < 0.55 && $s > 0.3) ? 'Brown' : ($h >= 345 && $s < 0.6 ? 'Pink' : 'Red');
    if ($h < 40) return $v < 0.6 ? 'Brown' : 'Orange';
    if ($h < 68) return $v < 0.5 ? 'Brown' : 'Yellow';
    if ($h < 160) return 'Green';
    if ($h < 195) return 'Teal';
    if ($h < 250) return $v < 0.45 ? 'Navy' : 'Blue';
    if ($h < 290) return 'Purple';
    return 'Pink';
}

/** Returns ['name' => 'Blue', 'hex' => '#2a5bd7'] for the colour covering most of the image. */
function tt_main_color(string $bytes): ?array
{
    if (!function_exists('imagecreatefromstring')) return null;
    $im = @imagecreatefromstring($bytes);
    if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im);
    $sw = 48; $sh = max(1, (int)round(48 * $h / max(1, $w)));
    $small = imagecreatetruecolor($sw, $sh);
    imagecopyresampled($small, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
    imagedestroy($im);
    $count = []; $sum = [];
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $c = imagecolorat($small, $x, $y);
            $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
            $k = tt_color_bucket($r, $g, $b);
            $count[$k] = ($count[$k] ?? 0) + 1;
            $sum[$k] = [($sum[$k][0] ?? 0) + $r, ($sum[$k][1] ?? 0) + $g, ($sum[$k][2] ?? 0) + $b];
        }
    }
    imagedestroy($small);
    if (!$count) return null;
    arsort($count);
    $name = array_key_first($count);
    $n = $count[$name];
    $hex = sprintf('#%02x%02x%02x', (int)($sum[$name][0] / $n), (int)($sum[$name][1] / $n), (int)($sum[$name][2] / $n));
    return ['name' => $name, 'hex' => $hex];
}

/* ---------- resolve published pins → template + colour ---------- */

/** Resolves up to $limit not-yet-linked published pins of this account. Returns how many are still left. */
function tt_resolve_pins(PDO $pdo, int $accountId, int $userId, int $limit = 250): int
{
    tt_ensure_schema($pdo);
    @set_time_limit(120);
    $st = $pdo->prepare("SELECT sp.id, sp.image_path, sp.pinterest_pin_id FROM scheduled_pins sp
        LEFT JOIN pin_template_links l ON l.scheduled_pin_id = sp.id
        WHERE sp.pinterest_account_id = ? AND sp.status = 'published' AND sp.pinterest_pin_id IS NOT NULL AND l.scheduled_pin_id IS NULL
        ORDER BY sp.id DESC LIMIT " . (int)$limit);
    $st->execute([$accountId]);
    $rows = $st->fetchAll();
    $cw = $pdo->prepare("SELECT template_id FROM cw_pins WHERE scheduled_pin_id = ? LIMIT 1");
    $fp = $pdo->prepare("SELECT source, template_key, template_name FROM pin_image_templates WHERE image_hash = ?");
    $ins = $pdo->prepare("INSERT IGNORE INTO pin_template_links (scheduled_pin_id, pinterest_account_id, pin_id, source, template_key, template_name, color_name, color_hex)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $started = time();
    foreach ($rows as $r) {
        if (time() - $started > 90) break;
        $source = 'none'; $key = 'none'; $name = null;
        try { $cw->execute([$r['id']]); $cwTpl = $cw->fetchColumn(); } catch (Throwable $e) { $cwTpl = false; }
        $path = function_exists('media_local_file') ? media_local_file((string)$r['image_path']) : realpath(__DIR__ . '/../' . ltrim((string)$r['image_path'], '/'));
        $bytes = ($path && is_file($path)) ? @file_get_contents($path) : false;
        if ($cwTpl) {
            $source = 'cw'; $key = (string)$cwTpl;
        } elseif ($bytes !== false && $bytes !== '') {
            $fp->execute([md5($bytes)]);
            if ($t = $fp->fetch()) { $source = $t['source']; $key = $t['template_key']; $name = $t['template_name']; }
        }
        $color = ($bytes !== false && $bytes !== '') ? tt_main_color($bytes) : null;
        $label = tt_template_label($pdo, $source, $key, $name, $userId);
        $ins->execute([$r['id'], $accountId, $r['pinterest_pin_id'], $source, $key, $label, $color['name'] ?? null, $color['hex'] ?? null]);
    }
    $left = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins sp LEFT JOIN pin_template_links l ON l.scheduled_pin_id = sp.id
        WHERE sp.pinterest_account_id = ? AND sp.status = 'published' AND sp.pinterest_pin_id IS NOT NULL AND l.scheduled_pin_id IS NULL");
    $left->execute([$accountId]);
    return (int)$left->fetchColumn();
}

/* ---------- metrics ---------- */

/** [startDate, endDate] for 30 / custom ranges, or null for lifetime / 90-day (served from pa_pins). */
function tt_range(string $range, string $from = '', string $to = ''): ?array
{
    if ($range === '30') return [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')];
    if ($range === 'custom') {
        $f = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-30 days'));
        $t = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        if ($f > $t) [$f, $t] = [$t, $f];
        return [$f, $t];
    }
    return null;
}

/**
 * Per-pin metrics joined to their template link. $groupBy: 'template' | 'color'.
 * $filterKey limits to one template (for the colour view) or one colour (pins list).
 */
function tt_pin_rows(PDO $pdo, int $accountId, string $range, string $from, string $to, string $source = ''): array
{
    tt_ensure_schema($pdo);
    $dr = tt_range($range, $from, $to);
    if ($dr) {
        $sql = "SELECT l.*, sp.image_path, sp.title, sp.published_at,
                COALESCE(d.imp, 0) AS views, COALESCE(d.outb, 0) AS clicks, COALESCE(d.sav, 0) AS saves, COALESCE(d.pclk, 0) AS pin_clicks
            FROM pin_template_links l
            JOIN scheduled_pins sp ON sp.id = l.scheduled_pin_id
            LEFT JOIN (SELECT pin_id, SUM(impressions) imp, SUM(outbound_clicks) outb, SUM(saves) sav, SUM(pin_clicks) pclk
                       FROM pa_pin_daily WHERE pinterest_account_id = ? AND metric_date BETWEEN ? AND ? GROUP BY pin_id) d ON d.pin_id = l.pin_id
            WHERE l.pinterest_account_id = ?";
        $args = [$accountId, $dr[0], $dr[1], $accountId];
    } else {
        $life = $range !== '90';
        $v = $life ? 'COALESCE(pp.imp_life, pp.imp_90, 0)' : 'COALESCE(pp.imp_90, 0)';
        $c = $life ? 'COALESCE(pp.outbound_life, pp.outbound_90, 0)' : 'COALESCE(pp.outbound_90, 0)';
        $s = $life ? 'COALESCE(pp.saves_life, pp.saves_90, 0)' : 'COALESCE(pp.saves_90, 0)';
        $p = $life ? 'COALESCE(pp.clicks_life, pp.clicks_90, 0)' : 'COALESCE(pp.clicks_90, 0)';
        $sql = "SELECT l.*, sp.image_path, sp.title, sp.published_at, $v AS views, $c AS clicks, $s AS saves, $p AS pin_clicks
            FROM pin_template_links l
            JOIN scheduled_pins sp ON sp.id = l.scheduled_pin_id
            LEFT JOIN pa_pins pp ON pp.pinterest_account_id = l.pinterest_account_id AND pp.pin_id = l.pin_id
            WHERE l.pinterest_account_id = ?";
        $args = [$accountId];
    }
    if ($source !== '' && isset(TT_SOURCES[$source])) { $sql .= " AND l.source = ?"; $args[] = $source; }
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** Groups pin rows and computes totals / averages. */
function tt_group(array $rows, string $by, int $minPins = 1): array
{
    $g = [];
    foreach ($rows as $r) {
        if ($by === 'color') { $k = $r['color_name'] ?: 'Unknown'; $label = $k; }
        else { $k = $r['source'] . ':' . $r['template_key']; $label = $r['template_name'] ?: $r['template_key']; }
        if (!isset($g[$k])) $g[$k] = ['key' => $k, 'name' => $label, 'source' => $r['source'], 'template_key' => $r['template_key'],
            'color_hex' => $by === 'color' ? (TT_COLORS[$k] ?? '#cccccc') : null, 'pins' => 0, 'views' => 0, 'clicks' => 0, 'saves' => 0, 'pin_clicks' => 0,
            'swatch_hexes' => []];
        $g[$k]['pins']++;
        $g[$k]['views'] += (int)$r['views'];
        $g[$k]['clicks'] += (int)$r['clicks'];
        $g[$k]['saves'] += (int)$r['saves'];
        $g[$k]['pin_clicks'] += (int)$r['pin_clicks'];
        if ($by === 'template' && $r['color_name']) $g[$k]['swatch_hexes'][$r['color_name']] = ($g[$k]['swatch_hexes'][$r['color_name']] ?? 0) + 1;
    }
    $out = [];
    foreach ($g as $row) {
        if ($row['pins'] < $minPins) continue;
        $row['avg_views'] = $row['pins'] ? round($row['views'] / $row['pins'], 1) : 0;
        $row['avg_clicks'] = $row['pins'] ? round($row['clicks'] / $row['pins'], 2) : 0;
        $row['ctr'] = $row['views'] ? round($row['clicks'] / $row['views'] * 100, 2) : 0;
        $row['save_pct'] = $row['views'] ? round($row['saves'] / $row['views'] * 100, 2) : 0;
        arsort($row['swatch_hexes']);
        $row['top_colors'] = array_map(fn($n) => ['name' => $n, 'hex' => TT_COLORS[$n] ?? '#ccc'], array_slice(array_keys($row['swatch_hexes']), 0, 4));
        unset($row['swatch_hexes']);
        $out[] = $row;
    }
    usort($out, fn($a, $b) => $b['avg_views'] <=> $a['avg_views']);
    return $out;
}

/* ---------- per-day metrics for tracked pins (30 days / custom) ---------- */

const TT_DAILY_TTL_HOURS = 12;

function tt_daily_needs_sync(PDO $pdo, int $accountId): bool
{
    return pa_cache_get($pdo, $accountId, 'tt:daily', TT_DAILY_TTL_HOURS * 60) === null;
}

/** One step of the daily sync for tracked pins (100 pins per multi-pin call, else 8 single calls). */
function tt_daily_sync_step(PDO $pdo, array $account, int $offset): array
{
    $accountId = (int)$account['id'];
    $st = $pdo->prepare("SELECT DISTINCT pin_id FROM pin_template_links WHERE pinterest_account_id = ? ORDER BY pin_id");
    $st->execute([$accountId]);
    $ids = array_column($st->fetchAll(), 'pin_id');
    $total = count($ids);
    if ($offset >= $total) {
        pa_cache_set($pdo, $accountId, 'tt:daily', ['at' => date('Y-m-d H:i:s')]);
        return ['ok' => true, 'next' => null, 'total' => $total];
    }
    if (empty($_SESSION['pa_multi_daily_unavailable'])) {
        $chunk = array_slice($ids, $offset, 100);
        $data = pa_multi_pin_daily($pdo, $account, $chunk);
        if ($data !== null) {
            foreach ($data as $pinId => $rows) pa_store_pin_days($pdo, $accountId, (string)$pinId, $rows);
            return ['ok' => true, 'next' => $offset + count($chunk), 'total' => $total];
        }
        $_SESSION['pa_multi_daily_unavailable'] = 1;
    }
    $chunk = array_slice($ids, $offset, 8);
    $errors = 0; $last = null;
    foreach ($chunk as $pinId) {
        $r = pa_pin_daily($pdo, $account, $pinId);
        if (!$r['ok']) { $errors++; $last = $r['error']; continue; }
        pa_store_pin_days($pdo, $accountId, $pinId, array_map(fn($d) => [$d['date'], $d['impressions'], $d['pin_clicks'], $d['outbound_clicks'], $d['saves']], $r['days']));
    }
    if ($errors === count($chunk) && $last) return ['ok' => false, 'error' => $last, 'next' => null, 'total' => $total];
    return ['ok' => true, 'next' => $offset + count($chunk), 'total' => $total];
}
