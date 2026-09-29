<?php
/**
 * Analytics → Competitor Analysis & Competitor Research.
 *
 * Pipeline:  Pinterest username / URL → public Pinterest data → pins + boards + available metrics
 *            → database (competitors, competitor_pins) → analytics engine → report (+ CSV).
 *
 * Data sources (public only — Pinterest's official API returns Pins only for the token's own account,
 * so a competitor's Pins can't be read through it):
 *   1. Pinterest's public widget JSON (the data behind Pinterest's own "Profile / Board" embed widgets):
 *      a profile's recent Pins and each board's Pins, with description, image, destination link,
 *      save count, video flag and board.
 *   2. Pinterest's public RSS feeds (profile + each board): titles, links and publish dates — used for the
 *      posting pattern and as a fallback when the widget data isn't available.
 * Both show only what Pinterest makes public (recent Pins, save counts); impressions/clicks exist only
 * for your own Pins (Pinterest Analytics page).
 */

const CP_MAX_COMPETITORS = 50;
const CP_MAX_BOARDS = 25;           // boards fetched per competitor refresh
const CP_REFRESH_MINUTES = 60;      // a competitor can be refreshed at most once an hour

function cp_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_competitors_v1';
    if (is_file($flag)) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS competitors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            display_name VARCHAR(255) DEFAULT NULL,
            avatar_url VARCHAR(1000) DEFAULT NULL,
            about TEXT DEFAULT NULL,
            followers INT DEFAULT NULL,
            total_pins INT DEFAULT NULL,
            boards_json LONGTEXT DEFAULT NULL,
            status ENUM('new','ok','error') NOT NULL DEFAULT 'new',
            last_error TEXT DEFAULT NULL,
            fetched_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cp_user (user_id, username),
            KEY idx_cp_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS competitor_pins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            competitor_id INT NOT NULL,
            pin_id VARCHAR(40) NOT NULL,
            title VARCHAR(500) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            link VARCHAR(1000) DEFAULT NULL,
            domain VARCHAR(255) DEFAULT NULL,
            image_url VARCHAR(1000) DEFAULT NULL,
            board_name VARCHAR(255) DEFAULT NULL,
            board_url VARCHAR(500) DEFAULT NULL,
            saves INT DEFAULT NULL,
            is_video TINYINT(1) NOT NULL DEFAULT 0,
            pin_created_at DATETIME DEFAULT NULL,
            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cp_pin (competitor_id, pin_id),
            KEY idx_cp_saves (competitor_id, saves)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* retried next request */ }
}

/* ===================== Input ===================== */

/**
 * "username", "@username", "pinterest.com/username/", "https://www.pinterest.co.uk/username/board-name/"
 * → ['username' => ..., 'board' => 'board-name' | null] or null when it isn't a Pinterest profile.
 */
function cp_parse_input(string $in): ?array
{
    $in = trim($in);
    if ($in === '') return null;
    if (preg_match('~^(?:https?://)?(?:[a-z]{2,3}\.)?(?:www\.)?pinterest\.[a-z.]{2,6}/([^/?#]+)(?:/([^/?#]+))?~i', $in, $m)) {
        $user = $m[1];
        $board = $m[2] ?? null;
        if (in_array(strtolower($user), ['pin', 'search', 'ideas', 'today', 'business', 'categories', 'explore', 'resource', 'settings'], true)) return null;
        if ($board !== null && in_array(strtolower($board), ['_created', '_saved', '_shop', 'pins', 'boards', '_profile'], true)) $board = null;
    } else {
        $user = ltrim($in, '@');
        $board = null;
    }
    $user = strtolower(trim($user));
    if (!preg_match('/^[a-z0-9_.-]{2,60}$/', $user)) return null;
    return ['username' => $user, 'board' => $board ? strtolower(urldecode($board)) : null];
}

/* ===================== Fetching ===================== */

function cp_http_get(string $url, int $timeout = 20): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json, application/rss+xml, text/xml, */*', 'Accept-Language: en-US,en;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => $body !== false && $code >= 200 && $code < 300, 'code' => $code, 'body' => $body === false ? '' : (string)$body, 'error' => $err];
}

function cp_domain(?string $url): string
{
    if (!$url) return '';
    $h = strtolower((string)parse_url($url, PHP_URL_HOST));
    return preg_replace('/^www\./', '', $h);
}

/** One Pin from the public widget JSON → our row shape. */
function cp_pin_from_widget(array $p, ?array $boardInfo = null): ?array
{
    $id = (string)($p['id'] ?? '');
    if ($id === '' || !preg_match('/^\d+$/', $id)) return null;
    $img = $p['images']['564x']['url'] ?? $p['images']['474x']['url'] ?? $p['images']['237x']['url'] ?? null;
    if (!$img && !empty($p['images']) && is_array($p['images'])) { $first = reset($p['images']); $img = $first['url'] ?? null; }
    $board = is_array($p['board'] ?? null) ? $p['board'] : ($boardInfo ?: []);
    $link = (string)($p['link'] ?? '');
    $saves = $p['repin_count'] ?? ($p['aggregated_pin_data']['aggregated_stats']['saves'] ?? null);
    $title = trim((string)($p['grid_title'] ?? $p['title'] ?? ''));
    $desc = trim((string)($p['description'] ?? ''));
    return [
        'pin_id' => $id,
        'title' => mb_substr($title !== '' ? $title : cp_title_from_text($desc), 0, 500),
        'description' => $desc,
        'link' => mb_substr($link, 0, 1000),
        'domain' => mb_substr((string)($p['domain'] ?? '') ?: cp_domain($link), 0, 255),
        'image_url' => $img,
        'board_name' => mb_substr((string)($board['name'] ?? ''), 0, 255) ?: null,
        'board_url' => mb_substr((string)($board['url'] ?? ''), 0, 500) ?: null,
        'saves' => $saves !== null ? (int)$saves : null,
        'is_video' => !empty($p['is_video']) || !empty($p['videos']) || !empty($p['story_pin_data']['pages'][0]['blocks'][0]['video']) ? 1 : 0,
        'pin_created_at' => !empty($p['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$p['created_at'])) : null,
    ];
}

/** Short title from a description (first sentence, ≤ 100 chars) when a Pin has no title. */
function cp_title_from_text(string $t): string
{
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($t)));
    if ($t === '') return '';
    $s = preg_split('/(?<=[.!?|])\s/', $t)[0];
    return mb_strlen($s) > 100 ? rtrim(mb_substr($s, 0, 97)) . '…' : $s;
}

/** Public widget JSON for a profile ($board = null) or one board. Returns ['ok', 'pins', 'user', 'board', 'error']. */
function cp_fetch_widget(string $username, ?string $board = null): array
{
    $path = $board === null
        ? 'users/' . rawurlencode($username) . '/pins/'
        : 'boards/' . rawurlencode($username) . '/' . rawurlencode($board) . '/pins/';
    $last = null;
    foreach (['https://widgets.pinterest.com/v3/pidgets/', 'https://api.pinterest.com/v3/pidgets/'] as $base) {
        $r = cp_http_get($base . $path);
        if (!$r['ok']) { $last = $r; continue; }
        $j = json_decode($r['body'], true);
        if (!is_array($j) || !isset($j['data'])) { $last = $r; continue; }
        $data = $j['data'];
        $boardInfo = is_array($data['board'] ?? null) ? $data['board'] : null;
        $pins = [];
        foreach ((array)($data['pins'] ?? []) as $p) if (is_array($p) && ($row = cp_pin_from_widget($p, $boardInfo))) $pins[] = $row;
        return ['ok' => true, 'pins' => $pins, 'user' => is_array($data['user'] ?? null) ? $data['user'] : null, 'board' => $boardInfo, 'error' => null];
    }
    $code = $last['code'] ?? 0;
    return ['ok' => false, 'pins' => [], 'user' => null, 'board' => null,
        'error' => $code === 404 ? 'Not found on Pinterest.' : 'Pinterest did not answer (' . ($code ?: ($last['error'] ?? 'no response')) . ').'];
}

/** Public RSS feed of a profile or board. Returns ['ok', 'pins' (with dates), 'title', 'error']. */
function cp_fetch_rss(string $username, ?string $board = null): array
{
    $url = 'https://www.pinterest.com/' . rawurlencode($username) . '/' . ($board === null ? 'feed.rss' : rawurlencode($board) . '.rss');
    $r = cp_http_get($url);
    if (!$r['ok'] || stripos($r['body'], '<rss') === false) {
        return ['ok' => false, 'pins' => [], 'title' => null, 'error' => $r['code'] === 404 ? 'Not found on Pinterest.' : 'RSS feed unavailable (' . ($r['code'] ?: $r['error']) . ').'];
    }
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($r['body'], 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$xml || !isset($xml->channel)) return ['ok' => false, 'pins' => [], 'title' => null, 'error' => 'The RSS feed could not be read.'];
    $pins = [];
    foreach ($xml->channel->item as $it) {
        $link = (string)$it->link;
        if (!preg_match('~/pin/(\d+)~', $link . ' ' . (string)$it->guid, $m)) continue;
        $descHtml = (string)$it->description;
        $img = preg_match('~<img[^>]+src="([^"]+)"~i', $descHtml, $im) ? html_entity_decode($im[1]) : null;
        $text = trim(html_entity_decode(strip_tags($descHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = trim(html_entity_decode((string)$it->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $pins[] = [
            'pin_id' => $m[1], 'title' => mb_substr($title !== '' ? $title : cp_title_from_text($text), 0, 500), 'description' => $text,
            'link' => '', 'domain' => '', 'image_url' => $img ? str_replace('/236x/', '/564x/', $img) : null,
            'board_name' => null, 'board_url' => $board !== null ? '/' . $username . '/' . $board . '/' : null,
            'saves' => null, 'is_video' => 0,
            'pin_created_at' => ($t = strtotime((string)$it->pubDate)) ? date('Y-m-d H:i:s', $t) : null,
        ];
    }
    return ['ok' => true, 'pins' => $pins, 'title' => (string)$xml->channel->title, 'error' => null];
}

/** Board slug from a board URL like "/username/small-bedroom-ideas/". */
function cp_board_slug(?string $url, string $username): ?string
{
    if (!$url) return null;
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    if (count($parts) < 2 || strtolower($parts[0]) !== strtolower($username)) return null;
    return strtolower($parts[1]);
}

/**
 * Collects a competitor's public Pins (profile + boards; widget JSON, then RSS for dates / fallback)
 * and stores them. Returns ['ok', 'error', 'new', 'total'].
 */
function cp_refresh(PDO $pdo, array $comp, ?string $onlyBoard = null): array
{
    cp_ensure_schema($pdo);
    $u = $comp['username'];
    $all = [];           // pin_id => row (later sources fill gaps)
    $merge = function (array $rows) use (&$all) {
        foreach ($rows as $r) {
            $id = $r['pin_id'];
            if (!isset($all[$id])) { $all[$id] = $r; continue; }
            foreach ($r as $k => $v) if (($all[$id][$k] === null || $all[$id][$k] === '') && $v !== null && $v !== '') $all[$id][$k] = $v;
        }
    };
    $errors = [];
    $profile = null;
    $boards = [];

    $w = cp_fetch_widget($u, $onlyBoard);
    if ($w['ok']) {
        $merge($w['pins']);
        $profile = $w['user'];
        if ($w['board']) $boards[strtolower((string)($w['board']['url'] ?? ''))] = $w['board'];
    } else $errors[] = 'Widget data: ' . $w['error'];

    $rss = cp_fetch_rss($u, $onlyBoard);
    if ($rss['ok']) $merge($rss['pins']); else $errors[] = 'RSS: ' . $rss['error'];
    if (!$all && !$w['ok'] && !$rss['ok']) {
        $pdo->prepare("UPDATE competitors SET status = 'error', last_error = ?, fetched_at = NOW() WHERE id = ?")->execute([implode(' ', $errors), $comp['id']]);
        return ['ok' => false, 'error' => 'Could not read this Pinterest profile — check the username, and that the profile is public. (' . implode(' ', $errors) . ')', 'new' => 0, 'total' => 0];
    }

    // Every board seen on the Pins → its own widget data + RSS (more Pins per board, and publish dates).
    if ($onlyBoard === null) {
        $slugs = [];
        foreach ($all as $p) if ($s = cp_board_slug($p['board_url'], $u)) $slugs[$s] = $p['board_name'];
        $start = time();
        foreach (array_slice(array_keys($slugs), 0, CP_MAX_BOARDS) as $slug) {
            if (time() - $start > 90) break;
            $bw = cp_fetch_widget($u, $slug);
            if ($bw['ok']) {
                $merge($bw['pins']);
                if ($bw['board']) $boards[strtolower((string)($bw['board']['url'] ?? '/' . $u . '/' . $slug . '/'))] = $bw['board'];
            }
            $br = cp_fetch_rss($u, $slug);
            if ($br['ok']) {
                foreach ($br['pins'] as &$bp) $bp['board_name'] = $slugs[$slug] ?: ucwords(str_replace('-', ' ', $slug));
                unset($bp);
                $merge($br['pins']);
            }
        }
    }

    // Store.
    $ins = $pdo->prepare("INSERT INTO competitor_pins (competitor_id, pin_id, title, description, link, domain, image_url, board_name, board_url, saves, is_video, pin_created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE title = COALESCE(NULLIF(VALUES(title), ''), title), description = COALESCE(NULLIF(VALUES(description), ''), description),
            link = COALESCE(NULLIF(VALUES(link), ''), link), domain = COALESCE(NULLIF(VALUES(domain), ''), domain),
            image_url = COALESCE(VALUES(image_url), image_url), board_name = COALESCE(VALUES(board_name), board_name),
            board_url = COALESCE(VALUES(board_url), board_url), saves = COALESCE(VALUES(saves), saves),
            is_video = GREATEST(VALUES(is_video), is_video), pin_created_at = COALESCE(pin_created_at, VALUES(pin_created_at)), fetched_at = NOW()");
    $before = (int)$pdo->query("SELECT COUNT(*) FROM competitor_pins WHERE competitor_id = " . (int)$comp['id'])->fetchColumn();
    foreach ($all as $p) {
        $ins->execute([$comp['id'], $p['pin_id'], $p['title'], $p['description'], $p['link'], $p['domain'], $p['image_url'], $p['board_name'],
            $p['board_url'], $p['saves'], $p['is_video'], $p['pin_created_at']]);
    }
    $total = (int)$pdo->query("SELECT COUNT(*) FROM competitor_pins WHERE competitor_id = " . (int)$comp['id'])->fetchColumn();

    $boardList = array_values(array_map(fn($b) => [
        'name' => (string)($b['name'] ?? ''), 'url' => (string)($b['url'] ?? ''), 'pins' => isset($b['pin_count']) ? (int)$b['pin_count'] : null,
        'followers' => isset($b['follower_count']) ? (int)$b['follower_count'] : null,
    ], $boards));
    $pdo->prepare("UPDATE competitors SET display_name = COALESCE(?, display_name), avatar_url = COALESCE(?, avatar_url), about = COALESCE(?, about),
            followers = COALESCE(?, followers), total_pins = COALESCE(?, total_pins), boards_json = COALESCE(?, boards_json),
            status = 'ok', last_error = ?, fetched_at = NOW() WHERE id = ?")
        ->execute([
            $profile['full_name'] ?? ($rss['ok'] ? preg_replace('/\s*\(.*$/', '', (string)$rss['title']) : null),
            $profile['image_small_url'] ?? ($profile['image_medium_url'] ?? null),
            $profile['about'] ?? null,
            isset($profile['follower_count']) ? (int)$profile['follower_count'] : null,
            isset($profile['pin_count']) ? (int)$profile['pin_count'] : null,
            $boardList ? json_encode($boardList) : null,
            $errors ? implode(' ', $errors) : null,
            $comp['id'],
        ]);
    return ['ok' => true, 'error' => null, 'new' => max(0, $total - $before), 'total' => $total];
}

/* ===================== Analytics engine ===================== */

function cp_stopwords(): array
{
    static $s = null;
    if ($s !== null) return $s;
    $s = array_flip(explode(' ', 'a an the and or but for nor so yet of to in on at by with from into onto over under up down out about as is are was were be been being this that these those it its it\'s i me my we our you your yours he she they them their his her him what which who whom whose when where why how all any both each few more most other some such no not only own same than too very can will just should now get got make made use using also here there best top new easy ideas idea diy via one two three four five six seven eight nine ten amp quot www com http https pinterest pin pins click see find learn more check love great good perfect amazing awesome beautiful cute simple need want like try must way ways every day days year years time things thing get lot lots save saved saving later follow visit read tap post blog discover explore today repin board link bio shop'));
    return $s;
}

function cp_tokens(string $text): array
{
    $t = mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $t = preg_replace('~https?://\S+~', ' ', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s\'-]+/u', ' ', $t);
    $out = [];
    foreach (preg_split('/\s+/u', $t) as $w) {
        $w = trim($w, "'-");
        if ($w === '' || mb_strlen($w) < 3 || is_numeric($w)) { $out[] = null; continue; }   // null breaks phrases
        $out[] = $w;
    }
    return $out;
}

/** Top single keywords and 2–3 word phrases across the Pins' titles + descriptions. */
function cp_keywords(array $pins, int $limit = 25): array
{
    $stop = cp_stopwords();
    $uni = []; $bi = []; $tri = [];
    foreach ($pins as $p) {
        $toks = cp_tokens(($p['title'] ?? '') . ' . ' . ($p['description'] ?? ''));
        $seen = [];
        $n = count($toks);
        for ($i = 0; $i < $n; $i++) {
            $w = $toks[$i];
            if ($w === null) continue;
            if (!isset($stop[$w]) && !isset($seen['u' . $w])) { $uni[$w] = ($uni[$w] ?? 0) + 1; $seen['u' . $w] = 1; }
            $w2 = $toks[$i + 1] ?? null;
            if ($w2 !== null && !isset($stop[$w]) && !isset($stop[$w2])) {
                $k = "$w $w2";
                if (!isset($seen['b' . $k])) { $bi[$k] = ($bi[$k] ?? 0) + 1; $seen['b' . $k] = 1; }
                $w3 = $toks[$i + 2] ?? null;
                if ($w3 !== null && !isset($stop[$w3])) {
                    $k3 = "$k $w3";
                    if (!isset($seen['t' . $k3])) { $tri[$k3] = ($tri[$k3] ?? 0) + 1; $seen['t' . $k3] = 1; }
                }
            }
        }
    }
    arsort($uni); arsort($bi); arsort($tri);
    $min = count($pins) >= 20 ? 2 : 1;
    $f = fn($a) => array_slice(array_filter($a, fn($c) => $c >= $min), 0, $limit, true);
    return ['words' => $f($uni), 'phrases' => $f($bi), 'long_phrases' => $f($tri)];
}

/** Content patterns (before/after, how-to, list posts, budget, …) found in titles/descriptions. */
function cp_patterns(array $pins): array
{
    $rules = [
        'Numbered list ("17 …")' => '/^\s*\d{1,3}\+?\s/u',
        'Before / after' => '/before\s*(and|&|\/)?\s*after/i',
        'How-to / tutorial' => '/\bhow to\b|\btutorial\b|\bstep[- ]by[- ]step\b/i',
        'Tips & hacks' => '/\btips?\b|\bhacks?\b|\btricks?\b/i',
        'Ideas & inspiration' => '/\bideas?\b|\binspiration\b|\binspo\b/i',
        'DIY' => '/\bdiy\b|do it yourself/i',
        'Budget / cheap' => '/\bbudget\b|\bcheap\b|\baffordable\b|\bunder \$?\d+/i',
        'Small spaces' => '/\bsmall\b|\btiny\b|\bcompact\b/i',
        'Minimalist / neutral' => '/\bminimalis|\bneutral\b|\bscandi|\bclean\b/i',
        'Cozy / aesthetic' => '/\bcozy\b|\bcosy\b|\baesthetic\b/i',
        'Storage / organization' => '/\bstorage\b|\borgani[sz]/i',
        'Seasonal / holiday' => '/christmas|halloween|thanksgiving|easter|valentine|spring|summer|fall|autumn|winter|holiday/i',
        'Recipe' => '/\brecipes?\b|\bingredients?\b/i',
        'Shopping / product' => '/\bshop\b|\bbuy\b|\bamazon\b|\bproducts?\b|\bsale\b/i',
        'Question hook' => '/\?\s*$/u',
        'Year in title' => '/\b20[2-3]\d\b/',
    ];
    $out = [];
    foreach ($rules as $label => $re) {
        $hits = array_values(array_filter($pins, fn($p) => preg_match($re, (string)($p['title'] ?? '')) || ($label !== 'Numbered list ("17 …")' && $label !== 'Question hook' && preg_match($re, (string)($p['description'] ?? '')))));
        if (!$hits) continue;
        $saves = array_filter(array_map(fn($p) => $p['saves'], $hits), fn($v) => $v !== null);
        $out[] = ['pattern' => $label, 'pins' => count($hits), 'share' => round(count($hits) / max(1, count($pins)) * 100, 1),
            'avg_saves' => $saves ? round(array_sum($saves) / count($saves), 1) : null];
    }
    usort($out, fn($a, $b) => $b['pins'] <=> $a['pins']);
    return $out;
}

/**
 * Topic clusters: the most common phrases (then words) become topics; each Pin joins the first topic it
 * mentions. Returns up to $max clusters with pin count, average saves and example titles.
 */
function cp_clusters(array $pins, array $kw, int $max = 8, array $exclude = []): array
{
    // Keyword research: the searched words themselves aren't a sub-topic ("small bedroom" → storage, decor, layout …).
    $ex = array_flip(array_map(fn($w) => rtrim(mb_strtolower($w), 's'), $exclude));
    $isSearched = function (string $seed) use ($ex) {
        if (!$ex) return false;
        foreach (explode(' ', $seed) as $w) if (!isset($ex[rtrim($w, 's')])) return false;
        return true;
    };
    $kw['phrases'] = array_filter($kw['phrases'], fn($k) => !$isSearched($k), ARRAY_FILTER_USE_KEY);
    $kw['words'] = array_filter($kw['words'], fn($k) => !$isSearched($k), ARRAY_FILTER_USE_KEY);
    $seeds = array_keys($kw['phrases']);
    foreach (array_keys($kw['words']) as $w) {
        $covered = false;
        foreach ($seeds as $s) if (strpos($s, $w) !== false) { $covered = true; break; }
        if (!$covered) $seeds[] = $w;
    }
    $seeds = array_slice($seeds, 0, $max * 2);
    $clusters = [];
    foreach ($pins as $p) {
        $text = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['description'] ?? ''));
        foreach ($seeds as $s) {
            if (mb_strpos($text, $s) !== false) { $clusters[$s][] = $p; break; }
        }
    }
    uasort($clusters, fn($a, $b) => count($b) <=> count($a));
    $out = [];
    foreach (array_slice($clusters, 0, $max, true) as $topic => $list) {
        $saves = array_filter(array_map(fn($p) => $p['saves'], $list), fn($v) => $v !== null);
        usort($list, fn($a, $b) => ($b['saves'] ?? -1) <=> ($a['saves'] ?? -1));
        $out[] = ['topic' => $topic, 'pins' => count($list), 'avg_saves' => $saves ? round(array_sum($saves) / count($saves), 1) : null,
            'examples' => array_slice(array_values(array_filter(array_map(fn($p) => $p['title'], $list))), 0, 3)];
    }
    return $out;
}

/** Posting pattern from the Pins that have a publish date. */
function cp_posting(array $pins): array
{
    $dates = array_values(array_filter(array_map(fn($p) => $p['pin_created_at'] ? strtotime($p['pin_created_at']) : null, $pins)));
    if (count($dates) < 2) return ['dated' => count($dates), 'per_week' => null, 'days' => [], 'hours' => [], 'first' => null, 'last' => null];
    sort($dates);
    $span = max(1, (end($dates) - $dates[0]) / 86400);
    $days = array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 0);
    $hours = array_fill(0, 24, 0);
    foreach ($dates as $t) { $days[gmdate('D', $t)]++; $hours[(int)gmdate('G', $t)]++; }
    // Recent pace: Pins in the last 30 days of the collected range.
    $recent = count(array_filter($dates, fn($t) => $t >= end($dates) - 30 * 86400));
    return [
        'dated' => count($dates),
        'per_week' => round(count($dates) / ($span / 7), 1),
        'per_week_recent' => round($recent / (min(30, $span) / 7), 1),
        'days' => $days, 'hours' => $hours,
        'first' => date('Y-m-d', $dates[0]), 'last' => date('Y-m-d', end($dates)),
    ];
}

/** Full report for a list of Pin rows (one competitor, or the Pins matching a keyword). */
function cp_report(array $pins, array $excludeWords = []): array
{
    $total = count($pins);
    $withSaves = array_values(array_filter($pins, fn($p) => $p['saves'] !== null));
    $top = $pins;
    usort($top, fn($a, $b) => ($b['saves'] ?? -1) <=> ($a['saves'] ?? -1));
    $domains = [];
    foreach ($pins as $p) if (!empty($p['domain'])) $domains[$p['domain']] = ($domains[$p['domain']] ?? 0) + 1;
    arsort($domains);
    $boards = [];
    foreach ($pins as $p) {
        $b = $p['board_name'] ?: '';
        if ($b === '') continue;
        $boards[$b]['pins'] = ($boards[$b]['pins'] ?? 0) + 1;
        if ($p['saves'] !== null) { $boards[$b]['saves'][] = (int)$p['saves']; }
    }
    $boardRows = [];
    foreach ($boards as $name => $b) {
        $boardRows[] = ['board' => $name, 'pins' => $b['pins'], 'avg_saves' => !empty($b['saves']) ? round(array_sum($b['saves']) / count($b['saves']), 1) : null];
    }
    usort($boardRows, fn($a, $b) => $b['pins'] <=> $a['pins']);
    $videos = count(array_filter($pins, fn($p) => !empty($p['is_video'])));
    $kw = cp_keywords($pins);
    return [
        'total' => $total,
        'with_saves' => count($withSaves),
        'total_saves' => array_sum(array_map(fn($p) => (int)$p['saves'], $withSaves)),
        'avg_saves' => $withSaves ? round(array_sum(array_map(fn($p) => (int)$p['saves'], $withSaves)) / count($withSaves), 1) : null,
        'top' => array_slice($top, 0, 20),
        'keywords' => $kw,
        'content' => ['image' => $total - $videos, 'video' => $videos],
        'domains' => array_slice($domains, 0, 15, true),
        'boards' => array_slice($boardRows, 0, 30),
        'posting' => cp_posting($pins),
        'clusters' => cp_clusters($pins, $kw, 8, $excludeWords),
        'patterns' => cp_patterns($pins),
    ];
}

/* ===================== Data access ===================== */

function cp_user_competitors(PDO $pdo, int $userId): array
{
    cp_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM competitor_pins p WHERE p.competitor_id = c.id) AS pins_collected
        FROM competitors c WHERE c.user_id = ? ORDER BY c.created_at DESC");
    $st->execute([$userId]);
    return $st->fetchAll();
}

function cp_get(PDO $pdo, int $userId, int $id): ?array
{
    cp_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT * FROM competitors WHERE id = ? AND user_id = ?");
    $st->execute([$id, $userId]);
    return $st->fetch() ?: null;
}

function cp_pins(PDO $pdo, int $competitorId): array
{
    $st = $pdo->prepare("SELECT * FROM competitor_pins WHERE competitor_id = ? ORDER BY COALESCE(pin_created_at, fetched_at) DESC LIMIT 5000");
    $st->execute([$competitorId]);
    return $st->fetchAll();
}

/**
 * Keyword research over every competitor Pin this user has collected (+ their own synced Pins for comparison).
 * A Pin matches when its title/description contains the phrase, or all of the keyword's words.
 */
function cp_keyword_research(PDO $pdo, int $userId, string $keyword): array
{
    cp_ensure_schema($pdo);
    $kw = mb_strtolower(trim($keyword));
    $words = array_values(array_filter(preg_split('/\s+/u', $kw), fn($w) => mb_strlen($w) >= 2 && !isset(cp_stopwords()[$w])));
    if (!$words) $words = [$kw];
    // Pre-filter in SQL on the rarest-looking (longest) word, then match precisely in PHP.
    $probe = $words;
    usort($probe, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], rtrim($probe[0], 's')) . '%';
    $st = $pdo->prepare("SELECT p.*, c.username, c.display_name FROM competitor_pins p JOIN competitors c ON c.id = p.competitor_id
        WHERE c.user_id = ? AND (p.title LIKE ? OR p.description LIKE ?) LIMIT 20000");
    $st->execute([$userId, $like, $like]);
    $stems = array_map(fn($w) => mb_strlen($w) > 3 ? rtrim($w, 's') : $w, $words);
    $match = function ($p) use ($kw, $stems) {
        $t = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['description'] ?? ''));
        if (mb_strpos($t, $kw) !== false) return true;
        foreach ($stems as $s) if (mb_strpos($t, $s) === false) return false;
        return true;
    };
    $pins = array_values(array_filter($st->fetchAll(), $match));

    $competitors = [];
    foreach ($pins as $p) {
        $u = $p['username'];
        $competitors[$u]['username'] = $u;
        $competitors[$u]['name'] = $p['display_name'] ?: $u;
        $competitors[$u]['pins'] = ($competitors[$u]['pins'] ?? 0) + 1;
        if ($p['saves'] !== null) $competitors[$u]['saves'][] = (int)$p['saves'];
    }
    foreach ($competitors as &$c) {
        $c['avg_saves'] = !empty($c['saves']) ? round(array_sum($c['saves']) / count($c['saves']), 1) : null;
        $c['total_saves'] = !empty($c['saves']) ? array_sum($c['saves']) : null;
        unset($c['saves']);
    }
    unset($c);
    usort($competitors, fn($a, $b) => [$b['pins'], $b['avg_saves'] ?? 0] <=> [$a['pins'], $a['avg_saves'] ?? 0]);

    // Your own Pins on this topic (from Pinterest Analytics sync), for comparison.
    $own = ['pins' => 0, 'avg_saves' => null];
    try {
        $st = $pdo->prepare("SELECT pp.title, pp.description, pp.saves_life FROM pa_pins pp JOIN pinterest_accounts a ON a.id = pp.pinterest_account_id
            WHERE a.user_id = ? AND pp.is_own = 1 AND (pp.title LIKE ? OR pp.description LIKE ?) LIMIT 5000");
        $st->execute([$userId, $like, $like]);
        $mine = array_values(array_filter($st->fetchAll(), $match));
        $s = array_filter(array_map(fn($r) => $r['saves_life'], $mine), fn($v) => $v !== null);
        $own = ['pins' => count($mine), 'avg_saves' => $s ? round(array_sum($s) / count($s), 1) : null];
    } catch (Throwable $e) { /* analytics tables missing */ }

    $report = cp_report($pins, $words);
    return ['keyword' => $keyword, 'pins' => $pins, 'competitors' => $competitors, 'own' => $own, 'report' => $report];
}

/* ===================== CSV ===================== */

function cp_csv_out(string $filename, array $sections): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // Excel: UTF-8
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;   // no spreadsheet formulas
    foreach ($sections as $title => $rows) {
        if ($title !== '') fputcsv($out, [$title]);
        foreach ($rows as $r) fputcsv($out, array_map($safe, $r));
        fputcsv($out, []);
    }
    fclose($out);
    exit;
}

/** Report → CSV sections (summary, keywords, clusters, patterns, domains, boards, posting). */
function cp_report_csv_sections(array $rep): array
{
    $s = [];
    $post = $rep['posting'];
    $s['Summary'] = [['Analysis', 'Result'],
        ['Total Pins collected', $rep['total']],
        ['Pins with save counts', $rep['with_saves']],
        ['Total saves', $rep['total_saves']],
        ['Average saves per Pin', $rep['avg_saves']],
        ['Images / Videos', $rep['content']['image'] . ' / ' . $rep['content']['video']],
        ['Posting pattern (Pins/week, all dated Pins)', $post['per_week'] ?? 'n/a'],
        ['Posting pattern (Pins/week, last 30 days)', $post['per_week_recent'] ?? 'n/a'],
        ['Dated Pins range', ($post['first'] ?? '') . ' – ' . ($post['last'] ?? '')],
        ['Topic clusters', count($rep['clusters'])],
    ];
    $s['Common keywords'] = array_merge([['Keyword', 'Pins']], array_map(fn($k, $v) => [$k, $v], array_keys($rep['keywords']['words']), $rep['keywords']['words']));
    $s['Common phrases'] = array_merge([['Phrase', 'Pins']], array_map(fn($k, $v) => [$k, $v], array_keys($rep['keywords']['phrases'] + $rep['keywords']['long_phrases']), $rep['keywords']['phrases'] + $rep['keywords']['long_phrases']));
    $s['Topic clusters'] = array_merge([['Topic', 'Pins', 'Avg saves', 'Example titles']], array_map(fn($c) => [$c['topic'], $c['pins'], $c['avg_saves'], implode(' | ', $c['examples'])], $rep['clusters']));
    $s['Content patterns'] = array_merge([['Pattern', 'Pins', 'Share %', 'Avg saves']], array_map(fn($p) => [$p['pattern'], $p['pins'], $p['share'], $p['avg_saves']], $rep['patterns']));
    $s['Destination domains'] = array_merge([['Domain', 'Pins']], array_map(fn($k, $v) => [$k, $v], array_keys($rep['domains']), $rep['domains']));
    $s['Boards'] = array_merge([['Board', 'Pins collected', 'Avg saves']], array_map(fn($b) => [$b['board'], $b['pins'], $b['avg_saves']], $rep['boards']));
    if (!empty($post['days'])) $s['Posting days (UTC)'] = [array_keys($post['days']), array_values($post['days'])];
    $s['Top Pins'] = array_merge([['Pin ID', 'Title', 'Saves', 'Board', 'Destination', 'Pin URL']],
        array_map(fn($p) => [$p['pin_id'], $p['title'], $p['saves'], $p['board_name'], $p['link'], 'https://www.pinterest.com/pin/' . $p['pin_id'] . '/'], $rep['top']));
    return $s;
}

/** Every Pin as CSV rows. */
function cp_pins_csv_rows(array $pins, bool $withCompetitor = false): array
{
    $head = ['Pin ID', 'Title', 'Description', 'Saves', 'Type', 'Board', 'Destination link', 'Domain', 'Published', 'Image', 'Pin URL'];
    if ($withCompetitor) array_unshift($head, 'Competitor');
    $rows = [$head];
    foreach ($pins as $p) {
        $r = [$p['pin_id'], $p['title'], mb_substr((string)$p['description'], 0, 1000), $p['saves'], $p['is_video'] ? 'Video' : 'Image',
            $p['board_name'], $p['link'], $p['domain'], $p['pin_created_at'], $p['image_url'], 'https://www.pinterest.com/pin/' . $p['pin_id'] . '/'];
        if ($withCompetitor) array_unshift($r, $p['username'] ?? '');
        $rows[] = $r;
    }
    return $rows;
}
