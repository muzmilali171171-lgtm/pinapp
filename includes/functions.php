<?php
if (!defined('SITE_BRAND')) {
    define('SITE_BRAND', (defined('APP_NAME') && trim(APP_NAME) !== '' && !preg_match('/web\s*to\s*pin|webtopin/i', APP_NAME)) ? APP_NAME : 'AutomatedPin');
}
/**
 * Shared helpers: logging, Pinterest API v5 calls (OAuth token exchange,
 * refresh, boards list, pin creation), and small utilities.
 *
 * Pinterest API v5 reference points used here:
 *   Token exchange / refresh : POST https://api.pinterest.com/v5/oauth/token
 *   Authorize (user consent) : https://www.pinterest.com/oauth/
 *   List boards              : GET  https://api.pinterest.com/v5/boards
 *   Create pin                : POST https://api.pinterest.com/v5/pins
 */

const PINTEREST_API_BASE = 'https://api.pinterest.com/v5';
const PINTEREST_OAUTH_AUTHORIZE_URL = 'https://www.pinterest.com/oauth/';

require_once __DIR__ . '/team_functions.php'; // team_effective_owner_id() used by the credit helpers below

function log_event(PDO $pdo, string $type, string $message, ?int $userId = null): void
{
    $stmt = $pdo->prepare("INSERT INTO logs (type, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$type, $userId, $message]);
}

function get_pinterest_settings(PDO $pdo): ?array
{
    $row = $pdo->query("SELECT * FROM pinterest_settings ORDER BY id DESC LIMIT 1")->fetch();
    return $row ?: null;
}

function pinterest_configured(PDO $pdo): bool
{
    $s = get_pinterest_settings($pdo);
    return $s && !empty($s['client_id']) && !empty($s['client_secret']) && !empty($s['redirect_uri']);
}

/** Build the URL that sends the user to Pinterest to approve access. */
function pinterest_build_authorize_url(PDO $pdo, string $state): ?string
{
    $s = get_pinterest_settings($pdo);
    if (!$s || empty($s['client_id']) || empty($s['redirect_uri'])) {
        return null;
    }
    $params = [
        'client_id' => $s['client_id'],
        'redirect_uri' => $s['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'boards:read,boards:write,pins:read,pins:write,user_accounts:read',
        'state' => $state,
    ];
    return PINTEREST_OAUTH_AUTHORIZE_URL . '?' . http_build_query($params);
}

/** Low-level cURL wrapper used by every Pinterest API call below. */
function pinterest_http_request(string $method, string $url, array $headers = [], $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'code' => 0, 'data' => null, 'error' => $curlError];
    }
    $decoded = json_decode($response, true);
    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'code' => $httpCode,
        'data' => $decoded,
        'error' => $httpCode >= 300 ? $response : null,
    ];
}

/** Exchange the OAuth "code" from the callback for an access + refresh token. */
function pinterest_exchange_code(PDO $pdo, string $code): array
{
    $s = get_pinterest_settings($pdo);
    $basicAuth = base64_encode($s['client_id'] . ':' . $s['client_secret']);

    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $s['redirect_uri'],
    ]);

    return pinterest_http_request('POST', PINTEREST_API_BASE . '/oauth/token', [
        'Authorization: Basic ' . $basicAuth,
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);
}

/** Use a stored refresh_token to get a fresh access_token when the old one has expired. */
function pinterest_refresh_access_token(PDO $pdo, string $refreshToken): array
{
    $s = get_pinterest_settings($pdo);
    $basicAuth = base64_encode($s['client_id'] . ':' . $s['client_secret']);

    $body = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    return pinterest_http_request('POST', PINTEREST_API_BASE . '/oauth/token', [
        'Authorization: Basic ' . $basicAuth,
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);
}

/** If the account's token is expired (or close to it), refresh and persist the new one. */
function pinterest_ensure_fresh_token(PDO $pdo, array $account): array
{
    $expiresAt = $account['token_expires_at'];
    $isExpiring = !$expiresAt || strtotime($expiresAt) <= time() + 120;

    if (!$isExpiring) {
        return $account;
    }
    if (empty($account['refresh_token'])) {
        return $account; // nothing we can do, caller will get a 401 and mark it as error
    }

    $result = pinterest_refresh_access_token($pdo, $account['refresh_token']);
    if ($result['ok'] && !empty($result['data']['access_token'])) {
        $newAccessToken = $result['data']['access_token'];
        $newRefreshToken = $result['data']['refresh_token'] ?? $account['refresh_token'];
        $expiresIn = $result['data']['expires_in'] ?? 3600;
        $newExpiresAt = date('Y-m-d H:i:s', time() + (int)$expiresIn);

        $stmt = $pdo->prepare("UPDATE pinterest_accounts SET access_token = ?, refresh_token = ?, token_expires_at = ?, status = 'connected' WHERE id = ?");
        $stmt->execute([$newAccessToken, $newRefreshToken, $newExpiresAt, $account['id']]);

        $account['access_token'] = $newAccessToken;
        $account['refresh_token'] = $newRefreshToken;
        $account['token_expires_at'] = $newExpiresAt;
    } else {
        $stmt = $pdo->prepare("UPDATE pinterest_accounts SET status = 'error' WHERE id = ?");
        $stmt->execute([$account['id']]);
        log_event($pdo, 'oauth', 'Token refresh failed for account #' . $account['id'] . ': ' . json_encode($result['data'] ?? $result['error']), $account['user_id']);
    }

    return $account;
}

/** Fetch (and cache) the list of boards for a connected account. */
function pinterest_fetch_boards(PDO $pdo, array $account): array
{
    $account = pinterest_ensure_fresh_token($pdo, $account);

    $result = pinterest_http_request('GET', PINTEREST_API_BASE . '/boards?page_size=100', [
        'Authorization: Bearer ' . $account['access_token'],
    ]);

    if (!$result['ok']) {
        log_event($pdo, 'api', 'Failed to fetch boards for account #' . $account['id'] . ': ' . json_encode($result['data'] ?? $result['error']), $account['user_id']);
        return [];
    }

    $boards = $result['data']['items'] ?? [];

    // Refresh the cache of real ('ready') boards only — never touch rows the user has
    // drafted locally that are still waiting to be created on Pinterest ('pending_creation')
    // or that failed to create ('create_failed').
    //
    // IMPORTANT: update-in-place by board_id rather than delete-then-reinsert. A pin
    // scheduler form embeds the chosen board as "row:<id>" (the local pinterest_boards.id).
    // If this refresh deleted and reinserted every board on every page load, any board
    // that still exists would get a brand-new id — so by the time the form's POST is
    // handled (which refreshes the boards again before resolving board_choice), the id
    // the form submitted would already be gone, wrongly failing with "Selected board
    // was not found." Keeping the same row id for boards that still exist avoids that.
    $existingStmt = $pdo->prepare("SELECT id, board_id FROM pinterest_boards WHERE pinterest_account_id = ? AND status = 'ready'");
    $existingStmt->execute([$account['id']]);
    $existingByBoardId = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $existingByBoardId[$row['board_id']] = $row['id'];
    }

    $seenBoardIds = [];
    $update = $pdo->prepare("UPDATE pinterest_boards SET board_name = ?, fetched_at = CURRENT_TIMESTAMP WHERE id = ?");
    $insert = $pdo->prepare("INSERT INTO pinterest_boards (pinterest_account_id, board_id, board_name, status) VALUES (?, ?, ?, 'ready')");
    foreach ($boards as $b) {
        $seenBoardIds[] = $b['id'];
        if (isset($existingByBoardId[$b['id']])) {
            $update->execute([$b['name'], $existingByBoardId[$b['id']]]);
        } else {
            $insert->execute([$account['id'], $b['id'], $b['name']]);
        }
    }

    // Remove cached 'ready' rows for boards that no longer come back from Pinterest
    // (deleted/renamed-away on Pinterest's side), without touching the ones just kept above.
    $staleBoardIds = array_diff(array_keys($existingByBoardId), $seenBoardIds);
    if (!empty($staleBoardIds)) {
        $placeholders = implode(',', array_fill(0, count($staleBoardIds), '?'));
        $params = array_merge([$account['id']], array_values($staleBoardIds));
        $pdo->prepare("DELETE FROM pinterest_boards WHERE pinterest_account_id = ? AND status = 'ready' AND board_id IN ($placeholders)")->execute($params);
    }

    return $boards;
}

/** Create a brand-new board on Pinterest. Returns the raw API result (see pinterest_http_request). */
function pinterest_create_board_api(PDO $pdo, array $account, string $name, string $description): array
{
    $account = pinterest_ensure_fresh_token($pdo, $account);
    $payload = ['name' => $name];
    if ($description !== '') $payload['description'] = $description;

    return pinterest_http_request('POST', PINTEREST_API_BASE . '/boards', [
        'Authorization: Bearer ' . $account['access_token'],
        'Content-Type: application/json',
    ], json_encode($payload));
}

/**
 * List the boards available to pick from for a connected account: real boards
 * (freshly fetched from Pinterest, cached in pinterest_boards with status
 * 'ready') plus any boards the user has drafted locally that don't exist on
 * Pinterest yet ('pending_creation' — will be auto-created by the cron job).
 * Returns rows shaped like pinterest_boards, keyed by their internal id.
 */
function get_boards_for_account(PDO $pdo, array $account): array
{
    $liveBoards = pinterest_fetch_boards($pdo, $account); // also refreshes the 'ready' cache rows

    $stmt = $pdo->prepare("SELECT * FROM pinterest_boards WHERE pinterest_account_id = ? ORDER BY status = 'pending_creation' DESC, board_name ASC");
    $stmt->execute([$account['id']]);
    $rows = $stmt->fetchAll();

    // If the live fetch failed (e.g. rate limited), fall back to whatever is cached as 'ready'.
    if (empty($liveBoards)) {
        return $rows;
    }
    return $rows;
}

/** Comparable form of a board name: lowercase, no punctuation/extra spaces ("Bad Bunny  Concert-Outfits!" == "bad bunny concert outfits"). */
function board_name_key(string $name): string
{
    $n = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $n = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}

/** An existing (ready or pending) board on this account with the same name, or null. */
function board_find_by_name(PDO $pdo, int $accountId, string $name): ?array
{
    $key = board_name_key($name);
    if ($key === '') return null;
    $stmt = $pdo->prepare("SELECT * FROM pinterest_boards WHERE pinterest_account_id = ? AND status <> 'create_failed'");
    $stmt->execute([$accountId]);
    foreach ($stmt->fetchAll() as $row) {
        if (board_name_key((string)$row['board_name']) === $key) return $row;
    }
    return null;
}

/**
 * Cleans an AI-written board name to the house rule: main keyword only — no numbers, quotes,
 * hashtags, emojis or trailing hook words; Title Case; at most 50 characters on a word boundary.
 * Falls back to board_name_from_title($fallbackTitle) if nothing usable is left.
 */
function board_name_clean(string $name, string $fallbackTitle = ''): string
{
    $n = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $n = preg_replace('/["“”«»„][^"“”«»„]*["“”«»„]/u', ' ', $n);          // quoted sub-titles ("Fix You")
    $n = preg_replace("/(^|\s)['‘’][^'‘’]+['‘’](?=\s|$)/u", ' ', $n);     // 'single-quoted' parts
    $n = preg_replace('/#\S+/u', ' ', $n);                                   // hashtags
    $n = board_name_strip_numbers($n);                                       // list counts & years; keeps "Over 50", "50s"
    $n = preg_replace("/[^\p{L}\p{N}\s&'’\-]/u", ' ', $n);                      // emojis & other punctuation
    $n = preg_replace('/\s+-+\s*|\s*-+\s+/u', ' ', $n);                   // spaced dashes only; keep "Dump-and-Bake"
    $n = trim(preg_replace('/\s+/u', ' ', $n), " '’&-");
    // Drop dangling connector words left at the ends ("Coldplay Concert Looks That" -> "Coldplay Concert Looks").
    $edge = '(?:and|or|for|of|the|a|an|to|with|that|which|in|on|at|by|from|your|you|&)';
    $n = preg_replace('/^(' . $edge . '\s+)+/iu', '', $n);
    $n = preg_replace('/(\s+' . $edge . ')+$/iu', '', $n);
    $n = trim($n);
    if ($n !== '') {
        $small = ['and', 'or', 'for', 'of', 'the', 'a', 'an', 'to', 'with', 'in', 'on', 'at', 'by'];
        $words = preg_split('/\s+/u', $n);
        foreach ($words as $i => $w) {
            $lw = function_exists('mb_strtolower') ? mb_strtolower($w, 'UTF-8') : strtolower($w);
            if ($i > 0 && in_array($lw, $small, true)) { $words[$i] = $lw; continue; }
            // keep existing capitals inside words (e.g. "iPhone", "DIY"); only lift the first letter
            $first = function_exists('mb_substr') ? mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($w, 1, null, 'UTF-8') : ucfirst($w);
            $words[$i] = $first;
        }
        $n = implode(' ', $words);
        while ((function_exists('mb_strlen') ? mb_strlen($n) : strlen($n)) > 50 && strpos($n, ' ') !== false) {
            $n = substr($n, 0, strrpos($n, ' '));
        }
    }
    if ((function_exists('mb_strlen') ? mb_strlen($n) : strlen($n)) < 3) {
        return $fallbackTitle !== '' ? board_name_from_title($fallbackTitle) : 'Pin Ideas';
    }
    return $n;
}

/**
 * Removes list counts ("17 …", "10+"), years (2026) and other stray numbers from a board name, but keeps
 * age/decade phrases that ARE the keyword: "Over 50", "After 40", "In Your 60s", "50s", "80's".
 */
function board_name_strip_numbers(string $n): string
{
    $keep = [];
    $n = preg_replace_callback("/\b(?:(?:over|under|after|before|past|at|turning|in\s+your|in\s+their)\s+\d{2}(?:['’]?s)?|\d{2}['’]?s)\b/iu",
        function ($m) use (&$keep) { $keep[] = $m[0]; return ' ZZKEEP' . chr(65 + count($keep) - 1) . 'ZZ '; }, $n);
    $n = preg_replace('/\d+(\.\d+)?\s*(\+|%|k\b)?/iu', ' ', $n);
    foreach ($keep as $i => $v) $n = str_replace('ZZKEEP' . chr(65 + $i) . 'ZZ', $v, $n);
    return $n;
}

/**
 * Rule-based board name from a pin/page title, used when AI isn't available:
 * "18 Adorable Coldplay Concert Looks That Outshine “Fix You”" -> "Adorable Coldplay Concert Looks".
 */
function board_name_from_title(string $title): string
{
    $t = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s*[|:–—]\s.*$/u', '', $t);                          // "Title | Site Name", "Title: subtitle"
    $t = preg_replace('/["“”«»„][^"“”«»„]*["“”«»„]/u', ' ', $t);
    $t = preg_replace('/\([^)]*\)|\[[^\]]*\]/u', ' ', $t);
    $t = board_name_strip_numbers($t);
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    $hooks = '(?:that|which|who|you|you\'ll|you’ll|will|we|i|so|when|this|these|everyone|anyone|guaranteed|ever|right|now|without)';
    $words = preg_split('/\s+/u', $t);
    $keep = [];
    foreach ($words as $w) {
        if (count($keep) >= 2 && preg_match('/^' . $hooks . '$/iu', trim($w, ",.!?"))) break;
        $keep[] = $w;
        if (count($keep) >= 7) break;
    }
    $n = implode(' ', $keep);
    $clean = board_name_clean($n, '');
    return $clean !== 'Pin Ideas' ? $clean : (trim($t) !== '' ? mb_substr(trim($t), 0, 50) : 'Pin Ideas');
}

/** Board-name rules shared by every AI prompt that creates boards. */
function board_name_ai_rules(): string
{
    return 'BOARD NAME RULES: use ONLY the main keyword/topic of the pin title — 2 to 5 words, Title Case, under 50 characters. '
        . 'NEVER include the list count at the start or a year (no "17", no "2026"), and no hook phrases ("That Are Warm…", "You\'ll Love", "To Try"), '
        . 'but DO keep an age or audience phrase that is part of the keyword, e.g. "for Women Over 50", "in Your 40s". Also no '
        . 'quoted song/product sub-titles, emojis, hashtags or punctuation. Keep the core subject words as they appear in the title. '
        . 'Examples: pin title "18 Adorable Coldplay Concert Looks That Outshine “Fix You”" -> board "Adorable Coldplay Concert"; '
        . 'pin title "29 Bad Bunny Concert Outfits That Bring Serious Heat" -> board "Bad Bunny Concert Outfits"; '
        . 'pin title "17 Wool Outfits for Women Over 50 That Are Warm Without Being Heavy" -> board "Wool Outfits for Women Over 50". '
        . 'Every new board name must be unique: never repeat or slightly re-word an existing board name — if the topic is the same '
        . 'as an existing board, use that board instead of creating a new one.';
}

/**
 * Resolve a board selection coming from a schedule form (single pin or bulk):
 * either an existing pinterest_boards row (value "row:<id>") or a brand-new
 * board name + description to draft locally (value "__new__"), which will be
 * created on Pinterest automatically ~5 minutes before its first pin is due.
 *
 * Returns ['ok'=>bool, 'board_row_id'=>?int, 'board_id'=>?string, 'board_name'=>?string, 'error'=>?string].
 */
function resolve_board_selection(PDO $pdo, int $accountId, string $boardChoice, string $newBoardName, string $newBoardDescription): array
{
    if ($boardChoice === '__new__') {
        $newBoardName = trim($newBoardName);
        if ($newBoardName === '') {
            return ['ok' => false, 'board_row_id' => null, 'board_id' => null, 'board_name' => null, 'error' => 'Please enter a name for the new board.'];
        }
        // Board names are unique per Pinterest account: if this account already has (or is about to
        // create) a board with the same name, reuse it instead of queueing a duplicate that Pinterest
        // would reject — e.g. several pins in one batch that all got "Bad Bunny Concert Outfits".
        $existing = board_find_by_name($pdo, $accountId, $newBoardName);
        if ($existing) {
            return ['ok' => true, 'board_row_id' => (int)$existing['id'], 'board_id' => $existing['board_id'], 'board_name' => $existing['board_name'], 'error' => null];
        }
        $stmt = $pdo->prepare("INSERT INTO pinterest_boards (pinterest_account_id, board_id, board_name, board_description, status) VALUES (?, NULL, ?, ?, 'pending_creation')");
        $stmt->execute([$accountId, $newBoardName, trim($newBoardDescription)]);
        $rowId = (int)$pdo->lastInsertId();
        return ['ok' => true, 'board_row_id' => $rowId, 'board_id' => null, 'board_name' => $newBoardName, 'error' => null];
    }

    if (strpos($boardChoice, 'row:') === 0) {
        $rowId = (int)substr($boardChoice, 4);
        $stmt = $pdo->prepare("SELECT * FROM pinterest_boards WHERE id = ? AND pinterest_account_id = ?");
        $stmt->execute([$rowId, $accountId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'board_row_id' => null, 'board_id' => null, 'board_name' => null, 'error' => 'Selected board was not found.'];
        }
        return ['ok' => true, 'board_row_id' => (int)$row['id'], 'board_id' => $row['board_id'], 'board_name' => $row['board_name'], 'error' => null];
    }

    return ['ok' => false, 'board_row_id' => null, 'board_id' => null, 'board_name' => null, 'error' => 'Please choose a board.'];
}

/**
 * Create any boards that are due (status 'pending_creation' and their earliest
 * pending pin is within $leadMinutes of publishing). Called once per cron run,
 * before pins are published, so a newly-created board's id is ready in time.
 */
function process_pending_board_creations(PDO $pdo, int $leadMinutes = 5): void
{
    $threshold = date('Y-m-d H:i:s', time() + $leadMinutes * 60);

    $sql = "SELECT pb.*, MIN(sp.publish_at) AS first_publish_at
            FROM pinterest_boards pb
            JOIN scheduled_pins sp ON sp.board_row_id = pb.id AND sp.status = 'pending'
            WHERE pb.status = 'pending_creation'
            GROUP BY pb.id
            HAVING first_publish_at <= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$threshold]);
    $dueBoards = $stmt->fetchAll();

    foreach ($dueBoards as $board) {
        pinterest_create_pending_board($pdo, $board);
    }
}

/** Creates one locally-drafted board on Pinterest now (used by the scheduler and by "publish now"). */
function pinterest_create_pending_board(PDO $pdo, array $board): void
{
        $accStmt = $pdo->prepare("SELECT * FROM pinterest_accounts WHERE id = ?");
        $accStmt->execute([$board['pinterest_account_id']]);
        $account = $accStmt->fetch();

        if (!$account || $account['status'] !== 'connected') {
            // Count it like any failure, so its pins end up 'failed' with a clear reason instead of pending forever.
            $attempts = (int)$board['create_attempts'] + 1;
            $newStatus = $attempts >= 3 ? 'create_failed' : 'pending_creation';
            $pdo->prepare("UPDATE pinterest_boards SET create_attempts = ?, create_error = ?, status = ? WHERE id = ?")
                ->execute([$attempts, 'Pinterest account is not connected.', $newStatus, $board['id']]);
            if ($newStatus === 'create_failed') {
                $pdo->prepare("UPDATE scheduled_pins SET status = 'failed', last_error = ? WHERE board_row_id = ? AND status = 'pending'")
                    ->execute(['Pinterest account is not connected — reconnect it on Pinterest Accounts, then retry.', $board['id']]);
            }
            log_event($pdo, 'publish', "Board '{$board['board_name']}' (#{$board['id']}) could not be created: account not connected");
            return;
        }

        $result = pinterest_create_board_api($pdo, $account, $board['board_name'], (string)$board['board_description']);

        if ($result['ok'] && !empty($result['data']['id'])) {
            $newBoardId = $result['data']['id'];
            $pdo->prepare("UPDATE pinterest_boards SET board_id = ?, status = 'ready', create_error = NULL WHERE id = ?")
                ->execute([$newBoardId, $board['id']]);
            $pdo->prepare("UPDATE scheduled_pins SET board_id = ?, board_name = ? WHERE board_row_id = ?")
                ->execute([$newBoardId, $board['board_name'], $board['id']]);
            log_event($pdo, 'publish', "Board '{$board['board_name']}' created on Pinterest (id $newBoardId) ahead of its first scheduled pin", $account['user_id']);
        } else {
            $attempts = (int)$board['create_attempts'] + 1;
            $errorMsg = is_array($result['data'] ?? null) ? json_encode($result['data']) : ($result['error'] ?? 'Unknown error');
            $newStatus = $attempts >= 3 ? 'create_failed' : 'pending_creation';
            $pdo->prepare("UPDATE pinterest_boards SET create_attempts = ?, create_error = ?, status = ? WHERE id = ?")
                ->execute([$attempts, $errorMsg, $newStatus, $board['id']]);
            log_event($pdo, 'publish', "Board '{$board['board_name']}' (#{$board['id']}) creation failed (attempt $attempts): $errorMsg", $account['user_id']);
            if ($newStatus === 'create_failed') {
                $pdo->prepare("UPDATE scheduled_pins SET status = 'failed', last_error = ? WHERE board_row_id = ? AND status = 'pending'")
                    ->execute(['Board could not be created on Pinterest: ' . $errorMsg, $board['id']]);
            }
        }
}

/**
 * Create a Pin on Pinterest using a publicly reachable image URL
 * (the uploaded file's URL on this site, e.g. https://yoursite.com/uploads/pins/xyz.jpg).
 */
function pinterest_create_pin(PDO $pdo, array $account, array $pinRow): array
{
    $account = pinterest_ensure_fresh_token($pdo, $account);

    // External storage URL when the image is on R2/S3/B2 (uploaded now if needed), else the hosting URL.
    $imageUrl = media_url((string)$pinRow['image_path']);

    $description = (string)($pinRow['description'] ?? '');
    // Pinterest's public API has no generic "product tag" endpoint for arbitrary
    // retailer links, and no dedicated keywords field on a pin — fold keywords
    // into the description (where Pinterest actually indexes them for search)
    // if they aren't already present there.
    if (!empty($pinRow['keywords'])) {
        $kwList = array_filter(array_map('trim', explode(',', $pinRow['keywords'])));
        if ($kwList && stripos($description, $kwList[0]) === false) {
            $description = trim($description . "\n" . implode(', ', $kwList));
        }
    }

    $payload = [
        'board_id' => $pinRow['board_id'],
        'title' => $pinRow['title'],
        'description' => $description,
        'media_source' => [
            'source_type' => 'image_url',
            'url' => $imageUrl,
        ],
    ];
    // A tagged product link is the pin's outbound destination when no explicit
    // link was set for this pin.
    $link = $pinRow['dest_link'] ?: ($pinRow['product_link'] ?? '');
    if (!empty($link)) {
        $payload['link'] = $link;
    }
    if (!empty($pinRow['alt_text'])) {
        $payload['alt_text'] = $pinRow['alt_text'];
    }

    $result = pinterest_http_request('POST', PINTEREST_API_BASE . '/pins', [
        'Authorization: Bearer ' . $account['access_token'],
        'Content-Type: application/json',
    ], json_encode($payload));

    // Always carry the exact image URL we sent Pinterest, so a failure (e.g. error 2786
    // "Unable to reach the URL") can be logged and re-tested manually instead of being a
    // dead end — Pinterest's own servers fetch this URL, so it has to be reachable from
    // the public internet, not just from this server.
    $result['image_url'] = $imageUrl;
    return $result;
}

/* ===================== Bulk Pin Scheduler — batches ===================== */

/** Generate a fresh random batch id (same format used for both drafts and scheduled batches). */
function new_batch_id(): string
{
    return bin2hex(random_bytes(12));
}

/**
 * List a user's bulk-scheduler batches with computed progress/status. Each
 * row gets: pin_count, published_count, failed_count, first_publish_at,
 * last_publish_at, pinterest_username, and computed_status
 * ('draft' | 'scheduled' | 'completed').
 */
function get_user_batches(PDO $pdo, int $userId, string $filter = 'all'): array
{
    $sql = "SELECT b.*, pa.pinterest_username,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS pin_count,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id AND sp.status = 'published') AS published_count,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id AND sp.status = 'failed') AS failed_count,
        (SELECT MIN(sp.publish_at) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS first_publish_at,
        (SELECT MAX(sp.publish_at) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS last_publish_at
        FROM pin_batches b
        LEFT JOIN pinterest_accounts pa ON pa.id = b.pinterest_account_id
        WHERE b.user_id = ?
        ORDER BY b.updated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        if ($r['status'] === 'draft') {
            $r['computed_status'] = 'draft';
        } elseif ((int)$r['pin_count'] > 0 && (int)$r['published_count'] === (int)$r['pin_count']) {
            $r['computed_status'] = 'completed';
        } else {
            $r['computed_status'] = 'scheduled';
        }
    }
    unset($r);

    if ($filter !== 'all') {
        $rows = array_values(array_filter($rows, fn($r) => $r['computed_status'] === $filter));
    }
    return $rows;
}

/** Fetch one batch row (must belong to $userId), or null. */
function get_batch(PDO $pdo, string $batchId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM pin_batches WHERE batch_id = ? AND user_id = ?");
    $stmt->execute([$batchId, $userId]);
    return $stmt->fetch() ?: null;
}

/** Upsert a batch's draft autosave. Creates the row on first save. Returns the batch_id used. */
function save_batch_draft(PDO $pdo, int $userId, ?string $batchId, string $name, string $draftJson): string
{
    if ($batchId) {
        $existing = get_batch($pdo, $batchId, $userId);
    } else {
        $existing = null;
    }
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE pin_batches SET name = ?, draft_json = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name ?: null, $draftJson, $existing['id']]);
        return $existing['batch_id'];
    }
    $batchId = $batchId ?: new_batch_id();
    $stmt = $pdo->prepare("INSERT INTO pin_batches (user_id, batch_id, name, status, draft_json) VALUES (?, ?, ?, 'draft', ?)");
    $stmt->execute([$userId, $batchId, $name ?: null, $draftJson]);
    return $batchId;
}

/**
 * Turn a batch into (or update) an 'active' (scheduled) batch once "Schedule
 * All Pins" has run. Reuses the existing row if $batchId was a draft the
 * user was resuming, otherwise creates a fresh row for a brand-new batch.
 */
function activate_batch(PDO $pdo, int $userId, ?string $batchId, string $name, int $accountId, ?int $boardRowId, ?string $boardName): string
{
    $existing = $batchId ? get_batch($pdo, $batchId, $userId) : null;
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE pin_batches SET name = ?, pinterest_account_id = ?, board_row_id = ?, board_name = ?, status = 'active', draft_json = NULL, scheduled_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name ?: null, $accountId, $boardRowId, $boardName, $existing['id']]);
        return $existing['batch_id'];
    }
    $batchId = $batchId ?: new_batch_id();
    $stmt = $pdo->prepare("INSERT INTO pin_batches (user_id, batch_id, name, pinterest_account_id, board_row_id, board_name, status, scheduled_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$userId, $batchId, $name ?: null, $accountId, $boardRowId, $boardName]);
    return $batchId;
}

function format_datetime(?string $dt): string
{
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ===================== Credits ===================== */

/** A team member spends and views the TEAM OWNER's credits, not their own — see team_functions.php.
 *  DEPRECATED as of the split image/text credit system below — kept only for any code path not
 *  yet migrated to the split pools; new code should use get_user_image_credits()/get_user_text_credits(). */
function get_user_credits(PDO $pdo, int $userId): float
{
    return get_user_image_credits($pdo, $userId) + get_user_text_credits($pdo, $userId);
}

/** Deducts credits if the user (or their team owner) has enough. Returns true on success, false if insufficient. */
function deduct_user_credits(PDO $pdo, int $userId, float $amount): bool
{
    $userId = team_effective_owner_id($pdo, $userId);
    $stmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
    $stmt->execute([$amount, $userId, $amount]);
    return $stmt->rowCount() > 0;
}

function add_user_credits(PDO $pdo, int $userId, float $amount): void
{
    $userId = team_effective_owner_id($pdo, $userId);
    $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")->execute([$amount, $userId]);
}

/* ===================== Split AI credits (Plan Pricing: Image AI vs Text AI) =====================
 * Each plan sets separate monthly Image AI and Text AI credit allowances (Admin → Plan Pricing →
 * Create Plan). Balances reset to the plan's allowance on activation/renewal (see
 * activate_plan_for_user()/assign_free_plan_to_new_user() in pricing_functions.php) — they do NOT
 * accumulate month to month. A team member draws from their team owner's balance, same sharing
 * model as everything else in Settings → Team. All functions here are resilient to the columns
 * not existing yet (pre-migration): getters return 0, deduct treats it as "allow" so a missed
 * migration never silently blocks every AI action app-wide. */

function get_user_image_credits(PDO $pdo, int $userId): float
{
    try {
        $userId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("SELECT image_credits_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function get_user_text_credits(PDO $pdo, int $userId): float
{
    try {
        $userId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("SELECT text_credits_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Returns true (allowed) if the deduction succeeded OR if the split-credit columns aren't migrated yet. */
function deduct_image_credits(PDO $pdo, int $userId, float $amount): bool
{
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("UPDATE users SET image_credits_balance = image_credits_balance - ? WHERE id = ? AND image_credits_balance >= ?");
        $stmt->execute([$amount, $ownerId, $amount]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return true;
    }
}

function deduct_text_credits(PDO $pdo, int $userId, float $amount): bool
{
    try {
        $ownerId = team_effective_owner_id($pdo, $userId);
        $stmt = $pdo->prepare("UPDATE users SET text_credits_balance = text_credits_balance - ? WHERE id = ? AND text_credits_balance >= ?");
        $stmt->execute([$amount, $ownerId, $amount]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return true;
    }
}

function add_image_credits(PDO $pdo, int $userId, float $amount): void
{
    try {
        $userId = team_effective_owner_id($pdo, $userId);
        $pdo->prepare("UPDATE users SET image_credits_balance = image_credits_balance + ? WHERE id = ?")->execute([$amount, $userId]);
    } catch (Throwable $e) {
    }
}

function add_text_credits(PDO $pdo, int $userId, float $amount): void
{
    try {
        $userId = team_effective_owner_id($pdo, $userId);
        $pdo->prepare("UPDATE users SET text_credits_balance = text_credits_balance + ? WHERE id = ?")->execute([$amount, $userId]);
    } catch (Throwable $e) {
    }
}

/* ===================== Cloudflare accounts (free AI pin-image generation) ===================== */

function get_cloudflare_accounts(PDO $pdo): array
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT ca.*,
        COALESCE((SELECT count FROM cloudflare_usage cu WHERE cu.account_id = ca.id AND cu.usage_date = ?), 0) AS used_today
        FROM cloudflare_accounts ca ORDER BY ca.id ASC");
    $stmt->execute([$today]);
    return $stmt->fetchAll();
}

/**
 * Picks the first active Cloudflare account (in ascending id order) that
 * hasn't hit its daily_limit yet today, so accounts fill up sequentially and
 * automatically roll over to the next one once the current one is exhausted.
 * $excludeIds skips accounts already tried in this same request (e.g. one
 * that just failed) without touching their stored daily usage. Returns null
 * if every account is exhausted, excluded, or none are configured.
 */
function get_available_cloudflare_account(PDO $pdo, array $excludeIds = []): ?array
{
    $today = date('Y-m-d');
    $accounts = $pdo->query("SELECT * FROM cloudflare_accounts WHERE status = 'active' ORDER BY id ASC")->fetchAll();
    foreach ($accounts as $acc) {
        if (in_array((int)$acc['id'], $excludeIds, true)) continue;
        $stmt = $pdo->prepare("SELECT count FROM cloudflare_usage WHERE account_id = ? AND usage_date = ?");
        $stmt->execute([$acc['id'], $today]);
        $used = (int)($stmt->fetchColumn() ?: 0);
        if ($used < (int)$acc['daily_limit']) {
            return $acc;
        }
    }
    return null;
}

function increment_cloudflare_usage(PDO $pdo, int $accountId): void
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO cloudflare_usage (account_id, usage_date, count) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->execute([$accountId, $today]);
}

/**
 * Forces an account's used-today count up to its daily_limit, so it's skipped
 * for the rest of the day — used when Cloudflare's own side (not our internal
 * counter) reports the account is out of capacity (e.g. its neuron/rate quota),
 * which can happen even when our own counter still shows room.
 */
function mark_cloudflare_account_exhausted_today(PDO $pdo, int $accountId): void
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT daily_limit FROM cloudflare_accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $limit = (int)($stmt->fetchColumn() ?: 1);
    $pdo->prepare("INSERT INTO cloudflare_usage (account_id, usage_date, count) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count))")
        ->execute([$accountId, $today, $limit]);
}

/** True if an error string looks like Cloudflare's own rate/neuron/quota limit rather than a one-off network/auth problem. */
function looks_like_cloudflare_limit_error(string $error): bool
{
    $needles = ['limit', 'quota', 'neuron', 'rate limit', '429', 'exceeded', 'too many requests'];
    $lower = strtolower($error);
    foreach ($needles as $n) {
        if (strpos($lower, $n) !== false) return true;
    }
    return false;
}

/**
 * Pinterest hard-rejects pins over its own field limits (100 chars for title,
 * 500 for description/alt text/keywords) — this trims cleanly at a word
 * boundary rather than mid-word wherever possible, as the last line of
 * defense regardless of whether the AI actually followed the prompt's
 * character-count instructions. Lives here (not ai_functions.php) since
 * functions.php is the one file everything else already depends on.
 */
function pin_enforce_max_chars(string $text, int $max): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $truncated = mb_substr($text, 0, $max);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false && $lastSpace > $max * 0.6) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return rtrim($truncated);
}

/* ===================== Pinterest bulk-upload CSV export ===================== */

/**
 * Streams pins as Pinterest's own official bulk-upload CSV format (Title,
 * Media URL, Pinterest board, Thumbnail, Description, Link, Publish date,
 * Keywords), so the file can be uploaded directly to Pinterest's native bulk
 * pin creation tool as an alternative/backup to this app's own auto-publishing.
 * $pins rows need: title, image_path (or media_url), board_name, description,
 * dest_link (or link), publish_at, keywords.
 */
function output_pinterest_bulk_csv(array $pins, string $filename): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Title', 'Media URL', 'Pinterest board', 'Thumbnail', 'Description', 'Link', 'Publish date', 'Keywords']);

    foreach ($pins as $p) {
        $mediaUrl = $p['media_url'] ?? (!empty($p['image_path']) ? media_url((string)$p['image_path']) : '');
        $publishDate = '';
        if (!empty($p['publish_at'])) {
            $ts = is_numeric($p['publish_at']) ? (int)$p['publish_at'] : strtotime($p['publish_at']);
            if ($ts) $publishDate = date('Y-m-d\TH:i:s', $ts);
        }
        fputcsv($out, [
            pin_enforce_max_chars((string)($p['title'] ?? ''), 100),
            $mediaUrl,
            $p['board_name'] ?? '',
            '', // thumbnail — image pins only, left blank per Pinterest's own format spec
            pin_enforce_max_chars((string)($p['description'] ?? ''), 500),
            $p['dest_link'] ?? ($p['link'] ?? ''),
            $publishDate,
            $p['keywords'] ?? '',
        ]);
    }
    fclose($out);
}

/** Simple GET-and-return-body-as-text helper (used by the page crawler for robots.txt/sitemaps/page scans).
 * Uses a real browser User-Agent — many sites' security plugins/WAFs (Wordfence, Cloudflare bot
 * protection, etc.) block requests that self-identify as a bot, which silently returns nothing
 * useful to parse. This is reading pages the calling user owns/manages, not evading anything.
 * Returns null on failure. */
function http_get_text(string $url, int $timeout = 15): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
        CURLOPT_ENCODING => '', // accept gzip — sitemaps and pages are commonly served compressed
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    return $body;
}

/* ===================== Clean URLs ===================== */

/**
 * Turn an internal ".php" URL into its clean form (the site no longer serves
 * ".php" URLs — they show the 404 page). Used for links stored in the database
 * before the switch: blog content, notifications, footer items.
 *   "/pricing.php?x=1" -> "/pricing?x=1", "free-tools/a/index.php" -> "free-tools/a/"
 * External sites and OAuth callbacks are left untouched.
 */
function clean_php_url(string $url): string
{
    if ($url === '' || stripos($url, '.php') === false) return $url;
    if (preg_match('#^(?:https?:)?//([^/]+)#i', $url, $m)) {
        $ownHost = parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST);
        if (!$ownHost || strcasecmp($m[1], $ownHost) !== 0) return $url; // other website
    }
    if (stripos($url, '/oauth/') !== false) return $url;
    $url = preg_replace('#(^|/)index\.php(?=$|[?\#])#i', '$1', $url);
    return preg_replace('#\.php(?=$|[?\#])#i', '', $url);
}

/** Apply clean_php_url() to every href/action attribute inside an HTML string. */
function clean_php_links_in_html(string $html): string
{
    if (stripos($html, '.php') === false) return $html;
    return preg_replace_callback('#\b(href|action)\s*=\s*(["\'])(.*?)\2#is', function ($m) {
        return $m[1] . '=' . $m[2] . clean_php_url($m[3]) . $m[2];
    }, $html);
}

/* ===================== Shared frontend header ===================== */

/**
 * One header for every public page (home, pricing, blog, free tools, legal
 * pages, 404...). Absolute "/" links so it works at any URL depth.
 * Desktop: logo left, menu right. Tablet/mobile: logo left, hamburger right,
 * menu (with Log in / Sign up) in a slide-in drawer.
 */
function render_site_header(?PDO $pdo = null): void
{
    $u = $GLOBALS['user'] ?? null;
    if ($u === null && $pdo && function_exists('current_user')) {
        try { $u = current_user($pdo); } catch (Throwable $e) { $u = null; }
    }
    $path = '/' . trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    $links = [
        ['/growth-guide', 'Growth Guide'],
        ['/help', 'Help'],
        ['/use-cases/', 'Use Cases'],
        ['/tutorials', 'Tutorials'],
        ['/pricing', 'Pricing'],
        ['/affiliate', 'Affiliate'],
    ];
    $close = "var h=this.closest('.navbar');h.classList.remove('nav-open');document.body.classList.remove('nav-lock');h.querySelector('.nav-toggle').setAttribute('aria-expanded','false');";
    ?>
<header class="navbar">
    <div class="container">
        <div class="logo"><a href="/" style="color:inherit;text-decoration:none;">Automated<span>Pin</span></a></div>
        <button type="button" class="nav-toggle" aria-label="Open menu" aria-expanded="false" onclick="var h=this.closest('.navbar'),o=h.classList.toggle('nav-open');this.setAttribute('aria-expanded',o);document.body.classList.toggle('nav-lock',o);"><span></span><span></span><span></span></button>
        <nav>
            <div class="nav-drawer-head"><span class="nav-drawer-title">Automated<span>Pin</span></span><button type="button" class="nav-close" aria-label="Close menu" onclick="<?= $close ?>">&times;</button></div>
            <?php foreach ($links as [$href, $label]):
                $base = rtrim($href, '/');
                $isActive = ($path === $base || strpos($path, $base . '/') === 0); ?>
            <a href="<?= $href ?>"<?= $isActive ? ' class="active"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
            <?php if ($u): ?>
            <a href="/user/dashboard" class="btn-primary">Dashboard</a>
            <?php else: ?>
            <a href="/auth/login">Log in</a>
            <a href="/auth/register" class="btn-primary">Sign up free</a>
            <?php endif; ?>
        </nav>
        <div class="nav-backdrop" onclick="<?= $close ?>"></div>
    </div>
</header>
    <?php
}

/**
 * Saves (or refreshes) a Pinterest account + its tokens as fully 'connected' for $userId.
 * Used by "Connect Pinterest" AND by "Login with Pinterest", so a user who signs in with
 * Pinterest gets that same account connected and listed under Pinterest Accounts right away.
 * $isLogin: the account the user signed in with is always allowed as their FIRST account,
 * even on a plan whose Pinterest-account limit is 0; the limit still applies to extra accounts.
 * Returns ['ok' => bool, 'error' => ?string, 'account_id' => ?int, 'new' => bool].
 */
function pinterest_store_connected_account(PDO $pdo, int $userId, array $tokenData, string $pinterestUserId, ?string $username, bool $isLogin = false): array
{
    $accessToken = (string)($tokenData['access_token'] ?? '');
    if ($accessToken === '' || $pinterestUserId === '') {
        return ['ok' => false, 'error' => 'Missing Pinterest token or profile.', 'account_id' => null, 'new' => false];
    }
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresAt = date('Y-m-d H:i:s', time() + (int)($tokenData['expires_in'] ?? 3600));

    $existing = $pdo->prepare("SELECT id FROM pinterest_accounts WHERE user_id = ? AND pinterest_user_id = ?");
    $existing->execute([$userId, $pinterestUserId]);
    $existingId = (int)$existing->fetchColumn();

    if ($existingId) {
        $pdo->prepare("UPDATE pinterest_accounts SET pinterest_username = ?, access_token = ?, refresh_token = COALESCE(?, refresh_token), token_expires_at = ?, status = 'connected' WHERE id = ?")
            ->execute([$username, $accessToken, $refreshToken, $expiresAt, $existingId]);
        return ['ok' => true, 'error' => null, 'account_id' => $existingId, 'new' => false];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pinterest_accounts WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $have = (int)$countStmt->fetchColumn();
    if (!($isLogin && $have === 0) && function_exists('get_user_plan')) {
        $plan = get_user_plan($pdo, $userId);
        $limit = plan_limit_value($plan, 'pinterest_accounts_limit');
        if ($limit !== null && $have >= (int)$limit) {
            return ['ok' => false, 'error' => "Your plan allows up to $limit Pinterest account(s). Upgrade your plan to connect more.", 'account_id' => null, 'new' => false];
        }
    }

    $pdo->prepare("INSERT INTO pinterest_accounts
        (user_id, pinterest_user_id, pinterest_username, access_token, refresh_token, token_expires_at, status)
        VALUES (?, ?, ?, ?, ?, ?, 'connected')")
        ->execute([$userId, $pinterestUserId, $username, $accessToken, $refreshToken, $expiresAt]);
    return ['ok' => true, 'error' => null, 'account_id' => (int)$pdo->lastInsertId(), 'new' => true];
}


require_once __DIR__ . '/pin_publisher.php';
require_once __DIR__ . '/storage_functions.php';
require_once __DIR__ . '/external_storage.php';
require_once __DIR__ . '/template_tracking_functions.php';

/* ===================== User-facing error messages ===================== */

/**
 * The site is used by the public, so users must never see technical or admin-side details
 * (admin settings, cron jobs, migrate.php, API keys, AI provider names, raw HTTP/database errors).
 * Turns such a message into a short, friendly one. Normal messages are returned unchanged.
 * The original message is still stored (articles.last_error, logs) for the admin.
 */
function user_facing_error(?string $msg): string
{
    $msg = trim((string)$msg);
    if ($msg === '') return '';
    $tech = '/site admin|\badmin(istrator)?\b|\bcron\b|crontab|cyberpanel|migrate(\.php)?\b|\.php\b|sqlstate|pdoexception|\bmysql|'
        . 'stack trace|uncaught|fatal error|allowed memory|maximum execution time|config\.php|exec\(\)|'
        . 'api key|api_key|openrouter|cloudflare|deepinfra|replicate|fal\.ai|together\.ai|groq|gemini|openai|anthropic|'
        . '\bprovider\b|\bmodel\b|worker returned|no endpoints|\bcurl\b|\bhttp \d{3}\b|\bjson\b|[{}]/i';
    $pinterest = stripos($msg, 'pinterest') !== false;
    if ($pinterest && preg_match('/\b401\b|unauthori[sz]ed|access token|token (has )?expired|invalid token|reconnect/i', $msg)) {
        return 'Your Pinterest connection has expired. Please reconnect your Pinterest account and try again.';
    }
    if (!preg_match($tech, $msg)) return $msg;
    if (preg_match('/credit|quota|insufficient|balance/i', $msg) && !preg_match('/openrouter|api key/i', $msg)) {
        return 'Not enough credits for this. Upgrade your plan or try again later.';
    }
    $retry = stripos($msg, 'retrying') === 0;
    $out = $retry
        ? 'A temporary problem happened — it is being retried automatically.'
        : 'Something went wrong on our side. Please try again in a few minutes. If it keeps happening, contact support.';
    if (!empty($_SESSION['user_id']) && preg_match('/openrouter|api key|\b401\b|\b402\b/i', $msg)) {
        $out .= ' If you use your own AI key (Settings), check that it is valid and has credit.';
    }
    return $out;
}

/**
 * For the user area and the free tools: every JSON reply passes its error / notice / message fields
 * through user_facing_error(), so no endpoint can show technical or admin-side details.
 */
function user_json_error_filter(string $buf): string
{
    $t = ltrim($buf);
    if ($t === '' || $t[0] !== '{') return $buf;
    $data = json_decode($t, true);
    if (!is_array($data)) return $buf;
    $changed = false;
    $walk = function (&$node) use (&$walk, &$changed) {
        foreach ($node as $k => &$v) {
            if (is_array($v)) { $walk($v); continue; }
            if (is_string($v) && in_array((string)$k, ['error', 'last_error', 'notice', 'warning', 'message', 'msg', 'reason'], true)) {
                $clean = user_facing_error($v);
                if ($clean !== $v) { $v = $clean; $changed = true; }
            }
        }
    };
    $walk($data);
    return $changed ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $buf;
}

(function () {
    if (PHP_SAPI === 'cli') return;
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $root = realpath(__DIR__ . '/..') ?: '';
    if ($script === '' || $root === '') return;
    if (preg_match('/download|export|csv/i', basename($script))) return;   // file downloads: never buffer
    foreach (['/user/', '/free-tools/', '/auth/'] as $area) {
        if (strpos($script, $root . $area) === 0) { ob_start('user_json_error_filter'); return; }
    }
})();
