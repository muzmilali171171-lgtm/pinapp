<?php
/**
 * Pinterest Analytics → Regen Draft.
 *   GET                          → drafts for this account
 *   POST action=save, draft_id?  → create/update a draft from the Regenerate popup
 *   POST action=delete, ids=[…]  → delete drafts
 */
require_once __DIR__ . '/includes/pa-ajax.php';

$accountId = (int)$paAccount['id'];
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pa_json(['ok' => true, 'drafts' => pa_drafts_list($pdo, $uid, $accountId)]);
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $imagePath = trim((string)($_POST['image_path'] ?? ''));
    if ($imagePath !== '' && !pa_valid_local_pin_image($imagePath)) $imagePath = '';
    $board = (string)($_POST['board'] ?? '');
    if ($board !== '' && $board !== '__new__' && !preg_match('/^row:\d+$/', $board)) $board = '';

    // Keep only the fields the popup needs to rebuild the source pin.
    $src = json_decode((string)($_POST['source_json'] ?? ''), true);
    $keep = ['pin_id', 'title', 'description', 'link', 'image_url', 'board_id', 'impressions', 'outbound', 'saves', 'clicks', 'url', 'origin', 'keyword', 'mom', 'yoy'];
    $src = is_array($src) ? array_intersect_key($src, array_flip($keep)) : null;

    $fields = [
        mb_substr(trim((string)($_POST['title'] ?? '')), 0, 500),
        mb_substr((string)($_POST['description'] ?? ''), 0, 5000),
        mb_substr(trim((string)($_POST['link'] ?? '')), 0, 1000),
        mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 500),
        mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 500),
        ($_POST['image_mode'] ?? 'ai') === 'original' ? 'original' : 'ai',
        $imagePath ?: null,
        $board ?: null,
        mb_substr(trim((string)($_POST['new_board_name'] ?? '')), 0, 255) ?: null,
        $src ? json_encode($src) : null,
    ];
    $draftId = (int)($_POST['draft_id'] ?? 0);
    if ($draftId) {
        $upd = $pdo->prepare("UPDATE pa_regen_drafts SET title = ?, description = ?, link = ?, alt_text = ?, keywords = ?, image_mode = ?, image_path = ?, board = ?, new_board_name = ?,
            source_json = COALESCE(?, source_json), updated_at = ? WHERE id = ? AND user_id = ? AND pinterest_account_id = ?");
        $upd->execute(array_merge($fields, [date('Y-m-d H:i:s'), $draftId, $uid, $accountId]));
        $chk = $pdo->prepare("SELECT id FROM pa_regen_drafts WHERE id = ? AND user_id = ? AND pinterest_account_id = ?");
        $chk->execute([$draftId, $uid, $accountId]);
        if ($chk->fetchColumn()) pa_json(['ok' => true, 'draft_id' => $draftId]);
        // The draft was deleted meanwhile (e.g. in another tab) — save it as a new one.
    }
    $sourcePinId = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)($_POST['source_pin_id'] ?? '')) ?: null;
    $pdo->prepare("INSERT INTO pa_regen_drafts (title, description, link, alt_text, keywords, image_mode, image_path, board, new_board_name, source_json, user_id, pinterest_account_id, source_pin_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute(array_merge($fields, [$uid, $accountId, $sourcePinId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]));
    pa_json(['ok' => true, 'draft_id' => (int)$pdo->lastInsertId()]);
}

if ($action === 'delete') {
    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
    $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM pa_regen_drafts WHERE user_id = ? AND pinterest_account_id = ? AND id IN ($in)")
            ->execute(array_merge([$uid, $accountId], $ids));
    }
    pa_json(['ok' => true, 'count' => pa_drafts_count($pdo, $uid, $accountId)]);
}

pa_json(['ok' => false, 'error' => 'Unknown action.']);
