<?php
/** Boards for the Regenerate popup (incl. each board's Pinterest id, to preselect the original pin's board). */
@set_time_limit(60);
require_once __DIR__ . '/includes/pa-ajax.php';

$rows = get_boards_for_account($pdo, $paAccount);
pa_json(['ok' => true, 'boards' => array_map(fn($b) => [
    'value' => 'row:' . $b['id'],
    'board_id' => $b['board_id'],
    'name' => $b['board_name'],
    'status' => $b['status'],
], $rows)]);
