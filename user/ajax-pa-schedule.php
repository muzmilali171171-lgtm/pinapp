<?php
/**
 * Regenerate popup → Publish / Schedule one regenerated pin.
 * POST account_id, source_pin_id, board (row:<id> | __new__), new_board_name,
 *      title, description, link, alt_text, keywords,
 *      image_mode (ai | original), image_path (ai), original_image_url (original),
 *      mode (publish | schedule), publish_at (Y-m-d\TH:i, schedule only), draft_id (optional — removes that Regen Draft)
 * The pin lands in Scheduled Pins (source "regenerate"). "Publish" also tries to publish it
 * immediately; if Pinterest fails it stays queued and cron/scheduler.php retries it.
 */
@set_time_limit(120);
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];
$sourcePinId = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)($_POST['source_pin_id'] ?? ''));
$title = pin_enforce_max_chars(trim((string)($_POST['title'] ?? '')), 100);
$description = pin_enforce_max_chars(trim((string)($_POST['description'] ?? '')), 500);
$link = trim((string)($_POST['link'] ?? ''));
$altText = pin_enforce_max_chars(trim((string)($_POST['alt_text'] ?? '')), 500);
$keywords = mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 500);
$mode = ($_POST['mode'] ?? 'schedule') === 'publish' ? 'publish' : 'schedule';

$isKeywordPin = ($_POST['origin'] ?? '') === 'keyword';
if ($title === '') pa_json(['ok' => false, 'error' => 'Please add a title.']);
if ($isKeywordPin && $link === '') pa_json(['ok' => false, 'error' => 'Please add the page link this pin should send people to.']);
if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) pa_json(['ok' => false, 'error' => 'The destination link isn\'t a valid URL.']);

// Publish time.
if ($mode === 'publish') {
    $publishAt = date('Y-m-d H:i:s');
} else {
    // Prefer the absolute timestamp from the browser (the user's own timezone); the plain
    // publish_at string is only a fallback and is read in the app's timezone.
    $ts = (int)($_POST['publish_ts'] ?? 0);
    if ($ts <= 0) {
        $raw = trim((string)($_POST['publish_at'] ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
    }
    if (!$ts) pa_json(['ok' => false, 'error' => 'Please pick a date and time to schedule this pin.']);
    if ($ts < time() - 60) pa_json(['ok' => false, 'error' => 'The scheduled time is in the past — pick a future time or choose Publish now.']);
    $publishAt = date('Y-m-d H:i:s', $ts);
}

// Image (AI image path validated before anything is written or downloaded).
$imageMode = ($_POST['image_mode'] ?? 'ai') === 'original' ? 'original' : 'ai';
$imagePath = trim((string)($_POST['image_path'] ?? ''));
if ($imageMode === 'ai' && !pa_valid_local_pin_image($imagePath)) {
    pa_json(['ok' => false, 'error' => 'Generate the new pin image first (Regenerate Pin Image), or switch to "Keep original image".']);
}

// Plan limit (daily / monthly pin scheduling).
$limit = check_pin_scheduling_limit($pdo, (int)$user['id']);
if (!$limit['allowed']) pa_json(['ok' => false, 'error' => $limit['message']]);

if ($imageMode === 'original') {
    $dl = pa_download_pin_image(trim((string)($_POST['original_image_url'] ?? '')));
    if (!$dl['ok']) pa_json(['ok' => false, 'error' => $dl['error']]);
    $imagePath = $dl['path'];
}

// Board (resolved last, so a rejected request never drafts an orphan "new board" row).
$boardChoice = (string)($_POST['board'] ?? '');
if ($boardChoice === '') pa_json(['ok' => false, 'error' => 'Please choose a board.']);
ensure_db_connection($pdo);
$board = resolve_board_selection($pdo, $accountId, $boardChoice, (string)($_POST['new_board_name'] ?? ''), '');
if (!$board['ok']) pa_json(['ok' => false, 'error' => $board['error']]);

$pdo->prepare("INSERT INTO scheduled_pins
        (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, publish_at, source)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([
        $user['id'], $accountId, $board['board_id'], $board['board_name'], $board['board_row_id'],
        $imagePath, $title, $description, $link ?: null, $altText ?: null, $keywords ?: null, $publishAt,
        $isKeywordPin ? 'keyword' : 'regenerate',
    ]);
$scheduledId = (int)$pdo->lastInsertId();

if ($sourcePinId !== '') {
    $pdo->prepare("INSERT INTO pa_regenerated_pins (user_id, pinterest_account_id, source_pin_id, scheduled_pin_id) VALUES (?, ?, ?, ?)")
        ->execute([$user['id'], $accountId, $sourcePinId, $scheduledId]);
}
// A pin sent from a Regen Draft is no longer a draft.
$draftId = (int)($_POST['draft_id'] ?? 0);
if ($draftId) {
    $pdo->prepare("DELETE FROM pa_regen_drafts WHERE id = ? AND user_id = ? AND pinterest_account_id = ?")->execute([$draftId, $user['id'], $accountId]);
}
log_event($pdo, 'system', "Pinterest Analytics: regenerated pin #$scheduledId " . ($mode === 'publish' ? 'queued to publish now' : "scheduled for $publishAt") . ($sourcePinId ? " (from pin $sourcePinId)" : ''), (int)$user['id']);

$published = false;
$publishError = null;
if ($mode === 'publish' || strtotime((string)$publishAt) <= time()) { // overdue time → publish now
    $r = pa_publish_pin_now($pdo, $scheduledId);
    $published = $r['ok'];
    $publishError = $r['error'];
}

pa_json([
    'ok' => true,
    'scheduled_pin_id' => $scheduledId,
    'mode' => $mode,
    'published' => $published,
    'publish_error' => $publishError,
    'publish_at' => format_datetime($publishAt),
    'board_name' => $board['board_name'],
]);
