<?php
/**
 * Classic Wizard (v2) — server side.
 *
 * Flow: Design (scan site, pick pages, size, layout, templates, colours, fonts) →
 * Schedule (pace, per-page gap, boards) → Generate & review (pins are rendered in the
 * browser by assets/js/cw-engine.js, uploaded here, kept as a draft project, and only
 * inserted into scheduled_pins when the user clicks "Approve & schedule now").
 *
 * Pins render in the browser so templates, Google fonts, colour palettes and Canva SVG
 * designs preview live. The page's own images are downloaded here first (and cached under
 * uploads/cw-src/) so the canvas stays same-origin and exportable.
 */

require_once __DIR__ . '/ai_functions.php';

const CW_SAMPLE_IMAGE = __DIR__ . '/../assets/img/template-sky.jpg'; // sky-blue cloudy sample used before a page is scanned
const CW_MIN_IMG_W = 350;        // narrower than this = too small, skipped
const CW_MIN_IMG_H = 350;        // shorter than this = too small, skipped
const CW_MAX_WIDE_RATIO = 2.2;   // width / height above this = banner/strip, skipped
const CW_MAX_TALL_RATIO = 3.5;   // height / width above this = skyscraper strip, skipped
const CW_MAX_IMG_BYTES = 12000000;
const CW_SRC_DIR = 'uploads/cw-src';

function cw_src_abs_dir(): string
{
    $dir = __DIR__ . '/../' . CW_SRC_DIR . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/* ===================== Page fetching ===================== */

/** Fetches a page once and pulls out title, description, a text excerpt and every image URL. */
function cw_fetch_page(string $url): array
{
    $html = http_get_text($url, 20);
    if (!$html) {
        return ['ok' => false, 'error' => 'Could not open this page — it may block automated visits.', 'title' => '', 'description' => '', 'excerpt' => '', 'images' => []];
    }

    $title = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = strip_tags($m[1]);
    }
    $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // "Post title | Site name" -> "Post title"
    $title = trim(preg_split('/\s+[|–—]\s+/u', $title)[0] ?? $title);

    $description = '';
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)
        || preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)) {
        $description = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $excerpt = '';
    if (preg_match_all('/<(h2|h3|p)\b[^>]*>(.*?)<\/\1>/is', $html, $m)) {
        foreach ($m[2] as $chunk) {
            $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (mb_strlen($t) < 25) continue;
            $excerpt .= $t . ' ';
            if (mb_strlen($excerpt) > 1400) break;
        }
    }

    return [
        'ok' => true, 'error' => null,
        'title' => $title,
        'description' => $description,
        'excerpt' => mb_substr(trim($excerpt), 0, 1400),
        'images' => cw_extract_image_urls($html, $url),
    ];
}

/** Same discovery rules as scan_page_for_images(), working on HTML we already have. */
function cw_extract_image_urls(string $html, string $pageUrl): array
{
    $urls = [];
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
        $urls[] = $m[1];
    }
    if (preg_match_all('/<img\b[^>]*>/i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            $candidates = [];
            foreach (['data-lazy-srcset', 'data-srcset', 'srcset'] as $attr) {
                if (preg_match('/\b' . $attr . '=["\']([^"\']+)["\']/i', $tag, $m)) {
                    $parts = array_map('trim', explode(',', $m[1]));
                    $best = ''; $bestW = 0;
                    foreach ($parts as $p) {
                        $bits = preg_split('/\s+/', $p);
                        $w = isset($bits[1]) ? (int)$bits[1] : 0;
                        if ($bits[0] !== '' && $w >= $bestW) { $best = $bits[0]; $bestW = $w; }
                    }
                    if ($best !== '') $candidates[] = $best;
                }
            }
            foreach (['data-lazy-src', 'data-src', 'data-original', 'src'] as $attr) {
                if (preg_match('/\b' . $attr . '=["\']([^"\']+)["\']/i', $tag, $m)) $candidates[] = $m[1];
            }
            foreach ($candidates as $c) {
                if ($c !== '' && strpos($c, 'data:') !== 0) { $urls[] = $c; break; }
            }
        }
    }

    $parts = parse_url($pageUrl);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    $dirPath = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';
    $out = [];
    foreach ($urls as $u) {
        $u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($u === '' || strpos($u, 'data:') === 0) continue;
        if (strpos($u, '//') === 0) $u = 'https:' . $u;
        elseif (strpos($u, '/') === 0) $u = $origin . $u;
        elseif (!preg_match('#^https?://#i', $u)) $u = $origin . $dirPath . $u;
        $lower = strtolower(parse_url($u, PHP_URL_PATH) ?? '');
        if (preg_match('/\.(svg|gif|ico)$/', $lower)) continue;
        if (preg_match('/(logo|icon|avatar|gravatar|sprite|favicon|emoji|badge|spinner|placeholder|pixel)/', $lower)) continue;
        $out[] = $u;
    }
    return array_values(array_unique($out));
}

/**
 * Downloads candidate images in parallel, drops the ones that are too small or too wide/tall,
 * de-duplicates, and caches the keepers under uploads/cw-src/. Returns
 * ['images' => [['url' => 'uploads/cw-src/x.jpg', 'w' => .., 'h' => ..], ...], 'skipped' => n].
 */
function cw_download_usable_images(array $urls, int $want = 6, int $maxTry = 14): array
{
    $urls = array_slice(array_values($urls), 0, $maxTry);
    $dir = cw_src_abs_dir();
    $kept = [];
    $skipped = 0;
    $seen = [];

    // Already cached? (cache key = URL hash — no download needed)
    $toFetch = [];
    foreach ($urls as $u) {
        $key = md5($u);
        $hit = glob($dir . $key . '.*');
        if ($hit) {
            $info = @getimagesize($hit[0]);
            if ($info && cw_image_dims_ok((int)$info[0], (int)$info[1])) {
                $kept[] = ['url' => CW_SRC_DIR . '/' . basename($hit[0]), 'w' => (int)$info[0], 'h' => (int)$info[1], 'src' => $u];
            } else {
                $skipped++;
            }
            continue;
        }
        $toFetch[] = $u;
    }

    if ($toFetch && count($kept) < $want) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($toFetch as $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$u] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $u => $ch) {
            $data = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if (!$data || $code >= 300 || strlen($data) > CW_MAX_IMG_BYTES) { $skipped++; continue; }
            $info = @getimagesizefromstring($data);
            if (!$info) { $skipped++; continue; }
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
            if (!$ext) { $skipped++; continue; }
            $w = (int)$info[0]; $h = (int)$info[1];
            if (!cw_image_dims_ok($w, $h)) { $skipped++; continue; }
            $fp = md5($data);
            if (isset($seen[$fp])) continue; // same picture under two URLs
            $seen[$fp] = true;
            $file = md5($u) . '.' . $ext;
            @file_put_contents($dir . $file, $data);
            $kept[] = ['url' => CW_SRC_DIR . '/' . $file, 'w' => $w, 'h' => $h, 'src' => $u];
        }
        curl_multi_close($mh);
    }

    // Keep original page order (og:image / featured image first), capped at $want.
    $order = array_flip($urls);
    usort($kept, fn($a, $b) => ($order[$a['src']] ?? 99) <=> ($order[$b['src']] ?? 99));
    $kept = array_slice($kept, 0, $want);
    return ['images' => array_map(fn($k) => ['url' => $k['url'], 'w' => $k['w'], 'h' => $k['h']], $kept), 'skipped' => $skipped];
}

function cw_image_dims_ok(int $w, int $h): bool
{
    if ($w < CW_MIN_IMG_W || $h < CW_MIN_IMG_H) return false;
    if ($w / max(1, $h) > CW_MAX_WIDE_RATIO) return false;
    if ($h / max(1, $w) > CW_MAX_TALL_RATIO) return false;
    return true;
}

/** Caches the sample image used for template thumbnails before any site is scanned. */
function cw_sample_image(): ?string
{
    // Copied next to the downloaded page images, so pins can be drawn from it like any other image.
    $name = 'sample_sky_' . substr(md5((string)@filemtime(CW_SAMPLE_IMAGE)), 0, 8) . '.jpg';
    $dest = cw_src_abs_dir() . $name;
    if (!is_file($dest) && is_file(CW_SAMPLE_IMAGE)) @copy(CW_SAMPLE_IMAGE, $dest);
    return is_file($dest) ? CW_SRC_DIR . '/' . $name : null;
}

/* ===================== Projects ===================== */

function cw_get_project(PDO $pdo, int $projectId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM cw_projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    return $stmt->fetch() ?: null;
}

function cw_count_drafts(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cw_projects WHERE user_id = ? AND status = 'draft'");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0; // tables not migrated yet
    }
}

/** Removes a draft's rendered pin images from disk (source images stay cached — they're shared). */
function cw_delete_pin_files(array $paths): void
{
    foreach ($paths as $p) {
        if (is_string($p) && strpos($p, 'uploads/pins/cw_') === 0) {
            @unlink(__DIR__ . '/../' . $p);
        }
    }
}

/* ===================== AI: pin text + board choice for one page ===================== */

function cw_text_model(PDO $pdo): array
{
    $s = get_article_settings($pdo) ?: [];
    $provider = $s['freetool_text_provider'] ?? ($s['wpin_text_provider'] ?? ($s['pin_text_provider'] ?? ($s['text_provider'] ?? null)));
    $model = $s['freetool_text_model'] ?? ($s['wpin_text_model'] ?? ($s['pin_text_model'] ?? ($s['text_model'] ?? '')));
    return [$provider, (string)$model];
}

const CW_CATEGORIES = ['food', 'fashion', 'beauty', 'home', 'travel', 'diy', 'health', 'finance', 'parenting', 'pets', 'wedding', 'holiday', 'tech', 'general'];

/**
 * One AI call per page: N distinct pins (title, description, alt, keywords, short image
 * headline, kicker, CTA), the page's category (drives "AI picks templates"), and — when the
 * board choice is left to AI — the best board from $boardNames or a new board to create.
 */
function cw_ai_page_content(PDO $pdo, int $userId, array $page, int $count, array $boardNames, string $boardMode, bool $allowCreate): array
{
    [$provider, $model] = cw_text_model($pdo);
    if (!$provider) {
        return ['ok' => false, 'error' => 'AI writing is not available right now. Please try again later.'];
    }

    $boardPart = '';
    if ($boardMode !== 'fixed' && $boardNames) {
        $boardPart = ' Also choose the Pinterest board: "board" must be the EXACT name of the best-fitting board from the list';
        $boardPart .= $allowCreate
            ? ', or an empty string if none truly fits — in that case fill "new_board" with {"name": "...", "description": "..."} (name follows the BOARD NAME RULES below).'
            : ' (always pick one of them).';
    } elseif ($boardMode !== 'fixed' && $allowCreate) {
        $boardPart = ' The account has no boards yet: fill "new_board" with {"name": "...", "description": "..."} (name follows the BOARD NAME RULES below) and set "board" to an empty string.';
    }

    $system = 'You are an expert Pinterest marketer. Respond with ONLY a JSON object, no markdown fences, no commentary. '
        . 'Shape: {"category": "...", "board": "...", "new_board": null, "pins": [{"title": "...", "description": "...", "alt_text": "...", "keywords": "...", "headline": "...", "kicker": "...", "cta": "..."}]}. '
        . '"category" is one of: ' . implode(', ', CW_CATEGORIES) . '. '
        . 'Give exactly ' . $count . ' pins. All pins are for the SAME page and share its intent, but every title, description and headline must use a different angle and hook so none read as duplicates. '
        . 'title: keyword-rich, under 100 characters. description: 2-3 natural sentences, under 480 characters, ending with a short call to action. '
        . 'alt_text: literal description of what the pin image shows for screen readers, under 300 characters. keywords: 5-8 comma-separated lowercase search phrases. '
        . 'headline: the words printed ON the pin image — punchy, 3-7 words, under 45 characters, keep any leading number from the page title. '
        . 'kicker: 1-3 word small tag line printed above the headline (e.g. "Easy recipe", "Must try", "Save for later"). '
        . 'cta: 2-3 word button text (e.g. "Get the recipe", "Read more"). Write in the same language as the page.'
        . $boardPart . ($boardPart !== '' ? ' ' . board_name_ai_rules() : '');

    $user = "Page URL: {$page['url']}\nPage title: {$page['title']}\n";
    if (!empty($page['description'])) $user .= "Meta description: {$page['description']}\n";
    if (!empty($page['excerpt'])) $user .= "Page text excerpt: " . mb_substr($page['excerpt'], 0, 1200) . "\n";
    if ($boardMode !== 'fixed' && $boardNames) $user .= "Boards:\n- " . implode("\n- ", array_slice($boardNames, 0, 150)) . "\n";

    $result = ai_generate_text($pdo, $provider, $model, $system, $user, 3500, $userId);
    if (!$result['ok']) return ['ok' => false, 'error' => 'AI: ' . ($result['error'] ?? 'request failed')];

    $json = extract_json_from_text($result['text']);
    if (!is_array($json)) return ['ok' => false, 'error' => 'AI returned an unreadable answer — try this page again.'];
    $rows = $json['pins'] ?? (array_keys($json) === range(0, count($json) - 1) ? $json : []);

    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $r = is_array($rows[$i] ?? null) ? $rows[$i] : (is_array($rows[0] ?? null) ? $rows[0] : []);
        $title = trim((string)($r['title'] ?? $page['title']));
        $items[] = [
            'title' => pin_enforce_max_chars($title !== '' ? $title : $page['title'], 100),
            'description' => pin_enforce_max_chars(trim((string)($r['description'] ?? $page['description'] ?? '')), 500),
            'alt_text' => pin_enforce_max_chars(trim((string)($r['alt_text'] ?? $title)), 500),
            'keywords' => pin_enforce_max_chars(trim((string)($r['keywords'] ?? '')), 500),
            'headline' => pin_enforce_max_chars(trim((string)($r['headline'] ?? $title)), 60),
            'kicker' => pin_enforce_max_chars(trim((string)($r['kicker'] ?? '')), 24),
            'cta' => pin_enforce_max_chars(trim((string)($r['cta'] ?? '')), 22) ?: auto_pick_cta($title),
        ];
    }

    $category = strtolower(trim((string)($json['category'] ?? 'general')));
    if (!in_array($category, CW_CATEGORIES, true)) $category = 'general';

    $newBoard = null;
    if (is_array($json['new_board'] ?? null) && trim((string)($json['new_board']['name'] ?? '')) !== '') {
        $newBoard = [
            'name' => board_name_clean(trim((string)$json['new_board']['name']), (string)($page['title'] ?? '')),
            'description' => mb_substr(trim((string)($json['new_board']['description'] ?? '')), 0, 480),
        ];
    }

    return ['ok' => true, 'items' => $items, 'category' => $category, 'board' => trim((string)($json['board'] ?? '')), 'new_board' => $newBoard];
}

/** Fallback text when AI isn't available: the page's own title/description on every pin. */
function cw_plain_page_content(array $page, int $count): array
{
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $items[] = [
            'title' => pin_enforce_max_chars($page['title'], 100),
            'description' => pin_enforce_max_chars($page['description'] ?: $page['title'], 500),
            'alt_text' => pin_enforce_max_chars($page['title'], 500),
            'keywords' => '',
            'headline' => pin_enforce_max_chars($page['title'], 60),
            'kicker' => '',
            'cta' => auto_pick_cta($page['title']),
        ];
    }
    return ['ok' => true, 'items' => $items, 'category' => 'general', 'board' => '', 'new_board' => null];
}

/**
 * Resolves the board for a page from the project's board settings and the AI answer.
 * $cfg: ['board_mode' => 'selected'|'ai', 'board_ids' => [...], 'ai_create' => bool]
 */
function cw_resolve_board(PDO $pdo, int $accountId, array $cfg, array $ai, string $pageTitle): ?array
{
    $mode = $cfg['board_mode'] ?? 'selected';
    if ($mode === 'selected') {
        $ids = array_values(array_filter(array_map('intval', (array)($cfg['board_ids'] ?? []))));
        if (!$ids) return null;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, board_name FROM pinterest_boards WHERE pinterest_account_id = ? AND id IN ($ph) ORDER BY id");
        $stmt->execute([$accountId, ...$ids]);
        $pool = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, board_name FROM pinterest_boards WHERE pinterest_account_id = ? AND status <> 'create_failed' ORDER BY board_name");
        $stmt->execute([$accountId]);
        $pool = $stmt->fetchAll();
    }

    if ($pool && ($ai['board'] ?? '') !== '') {
        foreach ($pool as $b) {
            if (mb_strtolower(trim($b['board_name'])) === mb_strtolower($ai['board'])) {
                return ['row_id' => (int)$b['id'], 'name' => $b['board_name']];
            }
        }
    }

    if ($mode === 'ai' && !empty($cfg['ai_create']) && !empty($ai['new_board']['name'])) {
        $name = $ai['new_board']['name'];
        // Re-use a board with the same name (created earlier in this run or already on the account).
        if ($row = board_find_by_name($pdo, $accountId, $name)) return ['row_id' => (int)$row['id'], 'name' => $row['board_name']];
        $pdo->prepare("INSERT INTO pinterest_boards (pinterest_account_id, board_id, board_name, board_description, status) VALUES (?, NULL, ?, ?, 'pending_creation')")
            ->execute([$accountId, $name, $ai['new_board']['description'] ?? '']);
        return ['row_id' => (int)$pdo->lastInsertId(), 'name' => $name, 'created' => true];
    }

    if ($pool) {
        $b = $pool[crc32($pageTitle) % count($pool)];
        return ['row_id' => (int)$b['id'], 'name' => $b['board_name']];
    }
    return null;
}

/* ===================== Scheduling ===================== */

/**
 * Daily quota for a date. 'fixed' = the same number every day; 'ramp' = the new-account
 * warm-up: month 1, 2, 3, 4 and 5+ each have their own pins-per-day (default 1, 3, 5, 12, 20).
 */
function cw_daily_quota(array $sched, DateTime $start, DateTime $day): int
{
    if (($sched['mode'] ?? 'fixed') === 'ramp') {
        $ramp = array_values(array_map('intval', (array)($sched['ramp'] ?? [1, 3, 5, 12, 20])));
        $ramp = array_pad(array_slice($ramp, 0, 5), 5, 20);
        $monthIdx = (int)floor(($day->getTimestamp() - $start->getTimestamp()) / (30 * 86400));
        return max(1, $ramp[min(4, max(0, $monthIdx))]);
    }
    return max(1, min(100, (int)($sched['per_day'] ?? 6)));
}

/**
 * Assigns publish times. Pins are taken round by round (every page's 1st pin, then every
 * page's 2nd pin …); pin N+1 of a page goes out at least page_gap_days after pin N. Each day
 * holds at most its quota of THIS run's pins, and the slots of a day are spread evenly across
 * 24 hours from the start time (6/day = every 4 hours). The run always starts on the chosen
 * start date — pins the account already has scheduled (other runs / tools) never push it later;
 * they are only kept from landing on the exact same minute.
 * Starting today after the start time: today's pins are spread from now until midnight
 * (instead of all being overdue and going out at once).
 *
 * $pins: [['id'=>..,'page_key'=>..,'pin_index'=>..], ...] in page order. Returns [id => 'Y-m-d H:i:s'].
 */
function cw_compute_schedule(PDO $pdo, int $accountId, array $pins, array $sched): array
{
    // The start day/time is picked in the user's own time zone.
    if (function_exists('user_start_to_server')) {
        [$sched['start_date'], $sched['start_time']] = user_start_to_server($sched['start_date'] ?? null, $sched['start_time'] ?? null);
    }
    $tz = new DateTimeZone(date_default_timezone_get());
    $now = new DateTime('now', $tz);
    $today = (clone $now)->setTime(0, 0);
    $startDate = DateTime::createFromFormat('Y-m-d', (string)($sched['start_date'] ?? ''), $tz) ?: (clone $now)->modify('+1 day');
    $startDate->setTime(0, 0);
    if ($startDate < $today) $startDate = clone $today;
    [$sh, $sm] = array_map('intval', explode(':', preg_match('/^\d{1,2}:\d{2}$/', (string)($sched['start_time'] ?? '')) ? $sched['start_time'] : '08:00'));
    $gapDays = max(1, min(365, (int)($sched['page_gap_days'] ?? 30)));
    // Gap between pins of the same page can be in minutes or days.
    $gapMinutesMode = ($sched['page_gap_unit'] ?? 'days') === 'minutes';
    $gapMinutes = max(1, min(525600, (int)($sched['page_gap_minutes'] ?? 60)));
    $jitter = !empty($sched['jitter']) ? 12 : 0;

    // Minutes the account already has a pending pin at, so this run doesn't post at the very same minute.
    $taken = [];
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(publish_at, '%Y-%m-%d %H:%i') m FROM scheduled_pins WHERE pinterest_account_id = ? AND status = 'pending' AND publish_at >= ?");
    $stmt->execute([$accountId, $startDate->format('Y-m-d 00:00:00')]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $m) $taken[$m] = true;

    // First slot today can't be in the past: start from a few minutes from now.
    $soon = (clone $now)->modify('+5 minutes');
    $soon->setTime((int)$soon->format('H'), (int)$soon->format('i'), 0);

    $byPage = [];
    foreach ($pins as $p) $byPage[$p['page_key']][] = $p;
    foreach ($byPage as &$list) usort($list, fn($a, $b) => $a['pin_index'] <=> $b['pin_index']);
    unset($list);
    $maxRounds = $byPage ? max(array_map('count', $byPage)) : 0;

    $used = [];
    $lastDate = [];
    $lastAt = [];
    $out = [];
    for ($round = 0; $round < $maxRounds; $round++) {
        foreach ($byPage as $key => $list) {
            if (!isset($list[$round])) continue;
            $pin = $list[$round];
            $earliest = null;
            if ($gapMinutesMode && isset($lastAt[$key])) {
                $earliest = (clone $lastAt[$key])->modify("+$gapMinutes minutes");
                $day = (clone $earliest)->setTime(0, 0);
            } else {
                $day = isset($lastDate[$key]) ? (clone $lastDate[$key])->modify("+$gapDays days") : clone $startDate;
            }
            for ($guard = 0; $guard < 4000; $guard++) {
                $d = $day->format('Y-m-d');
                $quota = cw_daily_quota($sched, $startDate, $day);
                if (($used[$d] ?? 0) < $quota) break;
                $day->modify('+1 day');
            }
            $d = $day->format('Y-m-d');
            $quota = cw_daily_quota($sched, $startDate, $day);
            $slot = $used[$d] ?? 0;
            $used[$d] = $slot + 1;
            $base = (clone $day)->setTime($sh, $sm);
            $step = 1440 / $quota;
            if ($day == $today && $base < $soon) {
                // Today, start time already passed: spread today's slots over the rest of the day.
                $base = clone $soon;
                $left = max(10, (int)floor(((clone $today)->modify('+1 day')->getTimestamp() - $soon->getTimestamp()) / 60));
                $step = min($step, $left / $quota);
            }
            $at = (clone $base)->modify('+' . (int)round($slot * $step) . ' minutes');
            if ($earliest && $at < $earliest) $at = clone $earliest;
            if ($jitter) $at->modify(random_int(-$jitter, $jitter) . ' minutes');
            if ($at < $soon && $day == $today) $at = clone $soon;
            for ($g = 0; $g < 60 && isset($taken[$at->format('Y-m-d H:i')]); $g++) $at->modify('+3 minutes');
            $taken[$at->format('Y-m-d H:i')] = true;
            $out[$pin['id']] = $at->format('Y-m-d H:i:s');
            $lastDate[$key] = $day;
            $lastAt[$key] = clone $at;
        }
    }
    return $out;
}

/** Approves a draft: every remaining pin goes into scheduled_pins with its computed time. */
function cw_approve_project(PDO $pdo, array $project, int $userId): array
{
    if ($project['status'] !== 'draft') return ['ok' => false, 'error' => 'This run is already scheduled.'];
    $cfg = json_decode($project['config_json'] ?? '', true) ?: [];
    $sched = $cfg['schedule'] ?? [];
    $accountId = (int)$project['pinterest_account_id'];

    $stmt = $pdo->prepare("SELECT id FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
    $stmt->execute([$accountId, $userId]);
    if (!$stmt->fetch()) return ['ok' => false, 'error' => 'The Pinterest account for this run is no longer connected. Reconnect it, then try again.'];

    $stmt = $pdo->prepare("SELECT * FROM cw_pins WHERE project_id = ? AND status = 'draft' ORDER BY id");
    $stmt->execute([$project['id']]);
    $pins = $stmt->fetchAll();
    if (!$pins) return ['ok' => false, 'error' => 'There are no pins to schedule.'];

    $noBoard = array_filter($pins, fn($p) => empty($p['board_row_id']));
    if ($noBoard) return ['ok' => false, 'error' => count($noBoard) . ' pin(s) have no board. Open them and choose a board first.'];

    $limit = check_pin_scheduling_limit($pdo, $userId, count($pins));
    if (!$limit['allowed']) return ['ok' => false, 'error' => $limit['message'], 'upgrade' => true];

    // Page order = order the pages were selected in.
    $pages = json_decode($project['pages_json'] ?? '', true) ?: [];
    $order = [];
    foreach ($pages as $i => $pg) $order[$pg['key']] = $i;
    usort($pins, fn($a, $b) => [($order[$a['page_key']] ?? 9999), $a['pin_index'], $a['id']] <=> [($order[$b['page_key']] ?? 9999), $b['pin_index'], $b['id']]);

    $times = cw_compute_schedule($pdo, $accountId, $pins, $sched);
    $noLink = !empty($sched['no_link']);

    $newPinIds = [];
    $pdo->beginTransaction();
    try {
        $batchToken = new_batch_id();
        $name = 'Classic Wizard: ' . ($project['name'] ?: parse_url((string)$project['site_url'], PHP_URL_HOST) ?: 'Schedule #' . $project['id']);
        $pdo->prepare("INSERT INTO pin_batches (user_id, batch_id, name, pinterest_account_id, status, total_pins, scheduled_at) VALUES (?, ?, ?, ?, 'active', ?, NOW())")
            ->execute([$userId, $batchToken, $name, $accountId, count($pins)]);

        $ins = $pdo->prepare("INSERT INTO scheduled_pins
            (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, source, batch_id, publish_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'website_pin', ?, ?)");
        $boardStmt = $pdo->prepare("SELECT board_id, board_name FROM pinterest_boards WHERE id = ?");
        $upd = $pdo->prepare("UPDATE cw_pins SET status = 'scheduled', scheduled_pin_id = ?, publish_at = ? WHERE id = ?");

        foreach ($pins as $p) {
            $boardStmt->execute([$p['board_row_id']]);
            $board = $boardStmt->fetch() ?: ['board_id' => null, 'board_name' => $p['board_name']];
            $at = $times[$p['id']];
            $ins->execute([
                $userId, $accountId, $board['board_id'], $board['board_name'], $p['board_row_id'],
                $p['image_path'], $p['title'], $p['description'], $noLink ? null : $p['page_url'],
                $p['alt_text'], $p['keywords'], $batchToken, $at,
            ]);
            $newId = (int)$pdo->lastInsertId();
            $newPinIds[] = $newId;
            $upd->execute([$newId, $at, $p['id']]);
        }

        $pdo->prepare("UPDATE cw_projects SET status = 'scheduled', pin_batch_id = ?, total_pins = ?, scheduled_at = NOW() WHERE id = ?")
            ->execute([$batchToken, count($pins), $project['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Could not schedule: ' . $e->getMessage()];
    }

    log_event($pdo, 'system', 'Classic Wizard: ' . count($pins) . ' pin(s) approved and scheduled', $userId);
    $first = min($times);
    $last = max($times);
    // Pins whose time has already passed are published right now; the rest stay scheduled.
    $overdue = ['published' => 0, 'failed' => 0, 'left' => 0];
    if ($newPinIds && strtotime($first) <= time()) {
        ignore_user_abort(true);
        $overdue = publish_overdue_pins_now($pdo, ['ids' => $newPinIds, 'user_id' => $userId], 10);
    }
    return ['ok' => true, 'batch_id' => $batchToken, 'count' => count($pins), 'first' => $first, 'last' => $last,
        'published_now' => $overdue['published'], 'failed_now' => $overdue['failed'], 'overdue_queued' => $overdue['left']];
}

/* ===================== Canva (SVG) templates ===================== */

/** Strips anything executable from an uploaded SVG. Returns null if it isn't a usable SVG. */
function cw_sanitize_svg(string $svg): ?string
{
    $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg);
    if (stripos($svg, '<svg') === false) return null;
    $svg = preg_replace('/<\?xml[^>]*\?>/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);
    $svg = preg_replace('#<script\b.*?</script\s*>#is', '', $svg);
    $svg = preg_replace('#<script\b[^>]*/>#is', '', $svg);
    $svg = preg_replace('#<foreignObject\b.*?</foreignObject\s*>#is', '', $svg);
    $svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
    $svg = preg_replace('/(href\s*=\s*["\'])\s*javascript:[^"\']*/i', '$1#', $svg);
    return trim($svg);
}

/* ===================== Public Pin Maker (no login) ===================== */

const PM_MAX_PAGES_PER_RUN = 5;   // pages one free generation may cover
const PM_MAX_PINS_PER_PAGE = 3;

/** Free generations per visitor (Admin → Classic Wizard settings; default 3). */
function pm_max_attempts(PDO $pdo): int
{
    $row = get_article_settings($pdo) ?: [];
    return isset($row['pinmaker_attempts']) && $row['pinmaker_attempts'] !== null ? max(1, (int)$row['pinmaker_attempts']) : 3;
}

/** Used generations: the higher of this browser session's count and this IP's count (last 30 days). */
function pm_attempts_used(PDO $pdo): int
{
    $token = free_tool_session_token();
    $s = $pdo->prepare("SELECT COALESCE(SUM(attempts), 0) FROM free_tool_usage WHERE session_token = ? AND tool = 'pin_maker'");
    $s->execute([$token]);
    $bySession = (int)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(attempts), 0) FROM free_tool_usage WHERE ip_address = ? AND tool = 'pin_maker' AND usage_date >= CURDATE() - INTERVAL 30 DAY");
    $s->execute([free_tool_client_ip()]);
    return max($bySession, (int)$s->fetchColumn());
}

function pm_attempts_remaining(PDO $pdo): int
{
    return max(0, pm_max_attempts($pdo) - pm_attempts_used($pdo));
}

/** Fetches several URLs at the same time. Returns [url => body] for 2xx responses. */
function cw_http_multi(array $urls, int $timeout = 8): array
{
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls) return [];
    $mh = curl_multi_init();
    $hs = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$u] = $ch;
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $u => $ch) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = curl_multi_getcontent($ch);
        if ($code >= 200 && $code < 300 && $body) $out[$u] = $body;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/**
 * Reads a site's sitemap without storing anything (public tool): robots.txt and the usual
 * sitemap addresses are fetched at the same time, then up to 6 child sitemaps of an index
 * (post/page/product sitemaps first), also in parallel. Returns at most $limit page URLs.
 */
function pm_read_sitemap(string $siteUrl, int $limit = 500): array
{
    $p = parse_url($siteUrl);
    if (empty($p['host'])) return [];
    $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $guesses = [$origin . '/sitemap_index.xml', $origin . '/sitemap.xml', $origin . '/wp-sitemap.xml', $origin . '/post-sitemap.xml'];

    $first = cw_http_multi(array_merge([$origin . '/robots.txt'], $guesses), 8);
    $fromRobots = [];
    if (!empty($first[$origin . '/robots.txt']) && preg_match_all('/^\s*sitemap:\s*(\S+)/im', $first[$origin . '/robots.txt'], $m)) {
        $fromRobots = array_slice(array_diff($m[1], $guesses), 0, 4);
    }
    $docs = $first + cw_http_multi($fromRobots, 8);
    unset($docs[$origin . '/robots.txt']);

    $urls = [];
    $children = [];
    $collect = function (string $xml) use (&$urls, &$children, $limit) {
        if (stripos($xml, '<loc') === false) return;
        preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?\s*([^<\]]+?)\s*(?:\]\]>)?\s*</loc>#i', $xml, $m);
        $locs = array_map(fn($u) => html_entity_decode(trim($u), ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
        if (stripos($xml, '<sitemapindex') !== false) {
            foreach ($locs as $c) if (!preg_match('/(category|tag|author|image|video|taxonom|format)/i', $c)) $children[] = $c;
            return;
        }
        foreach ($locs as $u) {
            if (count($urls) >= $limit) break;
            if (!preg_match('#^https?://#i', $u) || preg_match('/\.(jpe?g|png|gif|webp|pdf|xml)$/i', $u)) continue;
            $urls[$u] = true;
        }
    };
    foreach ($docs as $xml) $collect($xml);

    if ($children && count($urls) < $limit) {
        $children = array_values(array_unique($children));
        usort($children, fn($a, $b) => (int)!preg_match('/post|page|product|recipe|article/i', $a) <=> (int)!preg_match('/post|page|product|recipe|article/i', $b));
        foreach (cw_http_multi(array_slice(array_diff($children, array_keys($docs)), 0, 6), 10) as $xml) $collect($xml);
    }
    return array_slice(array_keys($urls), 0, $limit);
}
