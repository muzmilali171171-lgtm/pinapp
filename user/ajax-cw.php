<?php
/**
 * Classic Wizard (v2) — every AJAX action the wizard, the review screen and Drafts use.
 * POST action=<name>; always answers JSON {ok: bool, error?: string, ...}.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/cw_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
@set_time_limit(120);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Your session ended. Log in again to continue — your pins so far are saved in Drafts.']);
    exit;
}
$user = current_user($pdo);
$userId = (int)$user['id'];
$action = (string)($_POST['action'] ?? '');

function cw_out(array $data): void
{
    echo json_encode($data);
    exit;
}

function cw_require_project(PDO $pdo, int $userId, bool $draftOnly = true): array
{
    $project = cw_get_project($pdo, (int)($_POST['project_id'] ?? 0), $userId);
    if (!$project) cw_out(['ok' => false, 'error' => 'This wizard run was not found.']);
    if ($draftOnly && $project['status'] !== 'draft') cw_out(['ok' => false, 'error' => 'This run is already scheduled and can no longer be edited.']);
    return $project;
}

function cw_page_key(string $url): string
{
    return 'p' . substr(md5($url), 0, 12);
}

function cw_pin_row(array $p): array
{
    return [
        'id' => (int)$p['id'], 'key' => $p['page_key'], 'page_url' => $p['page_url'], 'page_title' => $p['page_title'],
        'pin_index' => (int)$p['pin_index'], 'image_path' => $p['image_path'], 'template_id' => $p['template_id'],
        'design' => json_decode($p['design_json'] ?? '', true) ?: null,
        'title' => $p['title'], 'description' => $p['description'], 'alt_text' => $p['alt_text'], 'keywords' => $p['keywords'],
        'board_row_id' => $p['board_row_id'] ? (int)$p['board_row_id'] : null, 'board_name' => $p['board_name'],
        'status' => $p['status'], 'publish_at' => $p['publish_at'],
        'updated' => strtotime((string)$p['updated_at']) ?: time(),
    ];
}

switch ($action) {

    /* ---------- Design step ---------- */

    case 'sample':
        $url = cw_sample_image();
        cw_out(['ok' => (bool)$url, 'url' => $url]);

    case 'page_images': {
        // Images (filtered + cached) and title of one page — used by the live preview.
        $url = trim((string)($_POST['url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) cw_out(['ok' => false, 'error' => 'That link is not a valid page URL.']);
        $page = cw_fetch_page($url);
        if (!$page['ok']) cw_out(['ok' => false, 'error' => $page['error']]);
        $imgs = cw_download_usable_images($page['images'], 4, 10);
        cw_out(['ok' => true, 'title' => $page['title'], 'images' => $imgs['images'], 'skipped' => $imgs['skipped']]);
    }

    case 'custom_templates':
        $stmt = $pdo->prepare("SELECT id, name, svg_content, text_position FROM cw_custom_templates WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        cw_out(['ok' => true, 'templates' => array_map(fn($t) => [
            'id' => 'c' . $t['id'], 'db_id' => (int)$t['id'], 'name' => $t['name'], 'svg' => $t['svg_content'], 'text_position' => $t['text_position'],
        ], $stmt->fetchAll())]);

    case 'custom_template_upload': {
        if (empty($_FILES['svg']['tmp_name']) || !is_uploaded_file($_FILES['svg']['tmp_name'])) cw_out(['ok' => false, 'error' => 'Choose an SVG file exported from Canva.']);
        if ($_FILES['svg']['size'] > 5 * 1024 * 1024) cw_out(['ok' => false, 'error' => 'That SVG is larger than 5 MB. Export it again with fewer embedded images.']);
        $svg = cw_sanitize_svg((string)file_get_contents($_FILES['svg']['tmp_name']));
        if (!$svg) cw_out(['ok' => false, 'error' => 'That file is not an SVG. In Canva choose Share → Download → SVG.']);
        $name = trim((string)($_POST['name'] ?? '')) ?: pathinfo($_FILES['svg']['name'], PATHINFO_FILENAME) ?: 'My design';
        $pos = in_array($_POST['text_position'] ?? '', ['none', 'top', 'center', 'bottom'], true) ? $_POST['text_position'] : 'none';
        $pdo->prepare("INSERT INTO cw_custom_templates (user_id, name, svg_content, text_position) VALUES (?, ?, ?, ?)")
            ->execute([$userId, mb_substr($name, 0, 255), $svg, $pos]);
        $id = (int)$pdo->lastInsertId();
        cw_out(['ok' => true, 'template' => ['id' => 'c' . $id, 'db_id' => $id, 'name' => $name, 'svg' => $svg, 'text_position' => $pos]]);
    }

    case 'custom_template_delete':
        $pdo->prepare("DELETE FROM cw_custom_templates WHERE id = ? AND user_id = ?")->execute([(int)($_POST['id'] ?? 0), $userId]);
        cw_out(['ok' => true]);

    /* ---------- Projects (drafts) ---------- */

    case 'project_save': {
        $config = json_decode((string)($_POST['config'] ?? ''), true);
        $pagesIn = json_decode((string)($_POST['pages'] ?? ''), true);
        if (!is_array($config)) cw_out(['ok' => false, 'error' => 'Invalid settings.']);
        $accountId = (int)($config['schedule']['account_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM pinterest_accounts WHERE id = ? AND user_id = ? AND status = 'connected'");
        $stmt->execute([$accountId, $userId]);
        if (!$stmt->fetch()) cw_out(['ok' => false, 'error' => 'Choose a connected Pinterest account in the Schedule step.']);

        $projectId = (int)($_POST['project_id'] ?? 0);
        $existing = $projectId ? cw_get_project($pdo, $projectId, $userId) : null;
        if ($existing && $existing['status'] !== 'draft') cw_out(['ok' => false, 'error' => 'This run is already scheduled.']);

        // Merge pages: keep status of pages that already exist in this draft.
        $old = $existing ? (json_decode($existing['pages_json'] ?? '', true) ?: []) : [];
        $oldByKey = [];
        foreach ($old as $pg) $oldByKey[$pg['key']] = $pg;
        $pages = [];
        foreach ((array)$pagesIn as $pg) {
            $url = trim((string)($pg['url'] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $key = cw_page_key($url);
            if (isset($pages[$key])) continue;
            $pages[$key] = $oldByKey[$key] ?? ['key' => $key, 'url' => $url, 'title' => mb_substr(trim((string)($pg['title'] ?? '')), 0, 255), 'crawl_page_id' => (int)($pg['id'] ?? 0) ?: null, 'status' => 'pending'];
        }
        $pages = array_values($pages);
        if (!$pages) cw_out(['ok' => false, 'error' => 'Select at least one page in the Design step.']);
        if (count($pages) > 1000) cw_out(['ok' => false, 'error' => 'Keep one run to 1,000 pages or fewer.']);

        $name = mb_substr(trim((string)($config['schedule']['name'] ?? '')), 0, 255) ?: null;
        $siteUrl = mb_substr((string)($config['site']['url'] ?? ''), 0, 500);
        $crawlSiteId = (int)($config['site']['crawl_site_id'] ?? 0) ?: null;
        if ($existing) {
            // Pins of pages the user unselected go too, so they can't be scheduled by accident.
            $keep = array_column($pages, 'key');
            $s = $pdo->prepare("SELECT id, page_key, image_path FROM cw_pins WHERE project_id = ? AND status = 'draft'");
            $s->execute([$existing['id']]);
            foreach ($s->fetchAll() as $pin) {
                if (!in_array($pin['page_key'], $keep, true)) {
                    cw_delete_pin_files([$pin['image_path']]);
                    $pdo->prepare("DELETE FROM cw_pins WHERE id = ?")->execute([$pin['id']]);
                }
            }
            $pdo->prepare("UPDATE cw_projects SET name = ?, site_url = ?, crawl_site_id = ?, pinterest_account_id = ?, config_json = ?, pages_json = ? WHERE id = ?")
                ->execute([$name, $siteUrl, $crawlSiteId, $accountId, json_encode($config), json_encode($pages), $existing['id']]);
            $projectId = (int)$existing['id'];
        } else {
            $pdo->prepare("INSERT INTO cw_projects (user_id, name, site_url, crawl_site_id, pinterest_account_id, config_json, pages_json, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')")
                ->execute([$userId, $name, $siteUrl, $crawlSiteId, $accountId, json_encode($config), json_encode($pages)]);
            $projectId = (int)$pdo->lastInsertId();
            log_event($pdo, 'system', 'Classic Wizard: new run started with ' . count($pages) . ' page(s)', $userId);
        }
        cw_out(['ok' => true, 'project_id' => $projectId, 'pages' => $pages]);
    }

    case 'project_load': {
        $project = cw_require_project($pdo, $userId, false);
        $stmt = $pdo->prepare("SELECT * FROM cw_pins WHERE project_id = ? ORDER BY page_key, pin_index, id");
        $stmt->execute([$project['id']]);
        cw_out([
            'ok' => true,
            'project' => ['id' => (int)$project['id'], 'status' => $project['status'], 'name' => $project['name'], 'pin_batch_id' => $project['pin_batch_id']],
            'config' => json_decode($project['config_json'] ?? '', true) ?: [],
            'pages' => json_decode($project['pages_json'] ?? '', true) ?: [],
            'pins' => array_map('cw_pin_row', $stmt->fetchAll()),
        ]);
    }

    case 'project_delete': {
        $project = cw_require_project($pdo, $userId);
        $stmt = $pdo->prepare("SELECT image_path FROM cw_pins WHERE project_id = ?");
        $stmt->execute([$project['id']]);
        cw_delete_pin_files($stmt->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM cw_projects WHERE id = ?")->execute([$project['id']]);
        cw_out(['ok' => true]);
    }

    case 'page_status': {
        $project = cw_require_project($pdo, $userId);
        $key = (string)($_POST['key'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['pending', 'done', 'skipped', 'failed'], true) ? $_POST['status'] : 'pending';
        $pages = json_decode($project['pages_json'] ?? '', true) ?: [];
        foreach ($pages as &$pg) {
            if ($pg['key'] === $key) { $pg['status'] = $status; $pg['error'] = mb_substr((string)($_POST['error'] ?? ''), 0, 300) ?: null; }
        }
        unset($pg);
        $pdo->prepare("UPDATE cw_projects SET pages_json = ? WHERE id = ?")->execute([json_encode($pages), $project['id']]);
        cw_out(['ok' => true]);
    }

    /* ---------- Generate step ---------- */

    case 'prepare_page': {
        // Images + AI text + board for one page of the run.
        $project = cw_require_project($pdo, $userId);
        $cfg = json_decode($project['config_json'] ?? '', true) ?: [];
        $sched = $cfg['schedule'] ?? [];
        $pages = json_decode($project['pages_json'] ?? '', true) ?: [];
        $key = (string)($_POST['key'] ?? '');
        $idx = null;
        foreach ($pages as $i => $pg) if ($pg['key'] === $key) $idx = $i;
        if ($idx === null) cw_out(['ok' => false, 'error' => 'Page not found in this run.']);
        $pg = $pages[$idx];

        $fetched = cw_fetch_page($pg['url']);
        if (!$fetched['ok']) cw_out(['ok' => false, 'error' => $fetched['error'], 'skip' => true]);
        $imgs = cw_download_usable_images($fetched['images'], 6, 14);
        if (!$imgs['images']) {
            cw_out(['ok' => false, 'skip' => true, 'error' => $imgs['skipped']
                ? "All {$imgs['skipped']} image(s) on this page are too small or too wide for a pin."
                : 'No images were found on this page.']);
        }

        $pageInfo = [
            'url' => $pg['url'],
            'title' => $fetched['title'] ?: ($pg['title'] ?: $pg['url']),
            'description' => $fetched['description'],
            'excerpt' => $fetched['excerpt'],
        ];
        $count = max(1, min(10, (int)($sched['pins_per_page'] ?? 3)));
        $accountId = (int)$project['pinterest_account_id'];
        $boardMode = ($sched['board_mode'] ?? 'selected') === 'ai' ? 'ai' : 'selected';
        $boardCfg = ['board_mode' => $boardMode, 'board_ids' => $sched['board_ids'] ?? [], 'ai_create' => !empty($sched['ai_create'])];

        // Board names the AI chooses from.
        if ($boardMode === 'selected') {
            $ids = array_values(array_filter(array_map('intval', (array)($sched['board_ids'] ?? []))));
            $names = [];
            if (count($ids) > 1) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $s = $pdo->prepare("SELECT board_name FROM pinterest_boards WHERE pinterest_account_id = ? AND id IN ($ph)");
                $s->execute([$accountId, ...$ids]);
                $names = $s->fetchAll(PDO::FETCH_COLUMN);
            }
            $aiBoardMode = count($ids) > 1 ? 'pick' : 'fixed';
        } else {
            $s = $pdo->prepare("SELECT board_name FROM pinterest_boards WHERE pinterest_account_id = ? AND status <> 'create_failed' ORDER BY board_name");
            $s->execute([$accountId]);
            $names = $s->fetchAll(PDO::FETCH_COLUMN);
            $aiBoardMode = 'pick';
        }

        $aiUsed = false;
        $notice = null;
        if (get_user_text_credits($pdo, $userId) >= 1) {
            $content = cw_ai_page_content($pdo, $userId, $pageInfo, $count, $names, $aiBoardMode, $boardMode === 'ai' && $boardCfg['ai_create']);
            if ($content['ok']) {
                deduct_text_credits($pdo, $userId, 1);
                $aiUsed = true;
            } else {
                $notice = $content['error'] . ' The page title was used instead — edit the pins if needed.';
                $content = cw_plain_page_content($pageInfo, $count);
            }
        } else {
            $notice = 'Text AI credits ran out, so the page title was used for these pins. Upgrade to get AI-written titles and descriptions.';
            $content = cw_plain_page_content($pageInfo, $count);
        }

        $board = cw_resolve_board($pdo, $accountId, $boardCfg, $content, $pageInfo['title']);

        $pages[$idx]['title'] = mb_substr($pageInfo['title'], 0, 255);
        $pdo->prepare("UPDATE cw_projects SET pages_json = ? WHERE id = ?")->execute([json_encode($pages), $project['id']]);

        cw_out([
            'ok' => true, 'key' => $key, 'title' => $pageInfo['title'],
            'images' => $imgs['images'], 'skipped_images' => $imgs['skipped'],
            'items' => $content['items'], 'category' => $content['category'],
            'board' => $board, 'ai' => $aiUsed, 'notice' => $notice,
            'text_credits' => round(get_user_text_credits($pdo, $userId), 1),
        ]);
    }

    case 'save_pin': {
        $project = cw_require_project($pdo, $userId);
        $pinId = (int)($_POST['pin_id'] ?? 0);
        $existing = null;
        if ($pinId) {
            $s = $pdo->prepare("SELECT * FROM cw_pins WHERE id = ? AND project_id = ?");
            $s->execute([$pinId, $project['id']]);
            $existing = $s->fetch();
            if (!$existing) cw_out(['ok' => false, 'error' => 'Pin not found.']);
        }

        $imagePath = $existing['image_path'] ?? null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $info = @getimagesize($_FILES['image']['tmp_name']);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) cw_out(['ok' => false, 'error' => 'The rendered pin image was not valid.']);
            $dir = __DIR__ . '/../uploads/pins/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $ext = $info[2] === IMAGETYPE_PNG ? 'png' : ($info[2] === IMAGETYPE_WEBP ? 'webp' : 'jpg');
            $file = 'cw_' . bin2hex(random_bytes(10)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $file)) cw_out(['ok' => false, 'error' => 'Could not store the pin image. Check that uploads/pins is writable.']);
            if ($imagePath) cw_delete_pin_files([$imagePath]);
            $imagePath = 'uploads/pins/' . $file;
        }
        if (!$imagePath) cw_out(['ok' => false, 'error' => 'Missing pin image.']);

        $boardRowId = (int)($_POST['board_row_id'] ?? 0) ?: null;
        $boardName = null;
        if ($boardRowId) {
            $s = $pdo->prepare("SELECT board_name FROM pinterest_boards WHERE id = ? AND pinterest_account_id = ?");
            $s->execute([$boardRowId, $project['pinterest_account_id']]);
            $boardName = $s->fetchColumn();
            if ($boardName === false) { $boardRowId = null; $boardName = null; }
        }

        $design = json_decode((string)($_POST['design'] ?? ''), true);
        $fields = [
            'template_id' => mb_substr((string)($_POST['template_id'] ?? ''), 0, 40),
            'design_json' => is_array($design) ? json_encode($design) : ($existing['design_json'] ?? null),
            'title' => pin_enforce_max_chars(trim((string)($_POST['title'] ?? '')), 100),
            'description' => pin_enforce_max_chars(trim((string)($_POST['description'] ?? '')), 500),
            'alt_text' => pin_enforce_max_chars(trim((string)($_POST['alt_text'] ?? '')), 500),
            'keywords' => pin_enforce_max_chars(trim((string)($_POST['keywords'] ?? '')), 500),
            'board_row_id' => $boardRowId,
            'board_name' => $boardName ?: null,
            'image_path' => $imagePath,
        ];

        if ($existing) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $pdo->prepare("UPDATE cw_pins SET $set WHERE id = ?")->execute([...array_values($fields), $existing['id']]);
            $pinId = (int)$existing['id'];
        } else {
            $pageUrl = trim((string)($_POST['page_url'] ?? ''));
            $runKeys = array_column(json_decode($project['pages_json'] ?? '', true) ?: [], 'key');
            if (!in_array(cw_page_key($pageUrl), $runKeys, true)) {
                if (!empty($_FILES['image']['tmp_name'])) cw_delete_pin_files([$imagePath]);
                cw_out(['ok' => false, 'error' => 'That page is not part of this run.']);
            }
            $pdo->prepare("INSERT INTO cw_pins (project_id, user_id, page_key, page_url, page_title, pin_index, image_path, template_id, design_json, title, description, alt_text, keywords, board_row_id, board_name, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')")
                ->execute([$project['id'], $userId, cw_page_key($pageUrl), $pageUrl, mb_substr((string)($_POST['page_title'] ?? ''), 0, 255), (int)($_POST['pin_index'] ?? 0),
                    $fields['image_path'], $fields['template_id'], $fields['design_json'], $fields['title'], $fields['description'], $fields['alt_text'], $fields['keywords'], $fields['board_row_id'], $fields['board_name']]);
            $pinId = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("UPDATE cw_projects SET updated_at = NOW() WHERE id = ?")->execute([$project['id']]);
        $s = $pdo->prepare("SELECT * FROM cw_pins WHERE id = ?");
        $s->execute([$pinId]);
        cw_out(['ok' => true, 'pin' => cw_pin_row($s->fetch())]);
    }

    case 'remove_pin': {
        $project = cw_require_project($pdo, $userId);
        $s = $pdo->prepare("SELECT image_path FROM cw_pins WHERE id = ? AND project_id = ? AND status = 'draft'");
        $s->execute([(int)($_POST['pin_id'] ?? 0), $project['id']]);
        $path = $s->fetchColumn();
        if ($path === false) cw_out(['ok' => false, 'error' => 'Pin not found.']);
        cw_delete_pin_files([$path]);
        $pdo->prepare("DELETE FROM cw_pins WHERE id = ?")->execute([(int)$_POST['pin_id']]);
        cw_out(['ok' => true]);
    }

    case 'upload_image': {
        // A user's own photo for one pin — the template is applied to it in the browser.
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) cw_out(['ok' => false, 'error' => 'Choose an image to upload.']);
        if ($_FILES['file']['size'] > CW_MAX_IMG_BYTES) cw_out(['ok' => false, 'error' => 'Images must be 12 MB or smaller.']);
        $info = @getimagesize($_FILES['file']['tmp_name']);
        $ext = $info ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
        if (!$ext) cw_out(['ok' => false, 'error' => 'Upload a JPG, PNG or WebP image.']);
        if ($info[0] < 300 || $info[1] < 300) cw_out(['ok' => false, 'error' => 'That image is too small for a pin — use one at least 600 px wide.']);
        $file = 'u' . $userId . '_' . md5_file($_FILES['file']['tmp_name']) . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], cw_src_abs_dir() . $file);
        cw_out(['ok' => true, 'image' => ['url' => CW_SRC_DIR . '/' . $file, 'w' => (int)$info[0], 'h' => (int)$info[1]]]);
    }

    case 'approve': {
        $project = cw_require_project($pdo, $userId);
        cw_out(cw_approve_project($pdo, $project, $userId));
    }

    case 'schedule_preview': {
        // How the pace settings play out (first/last publish date) before approving.
        $project = cw_require_project($pdo, $userId);
        $cfg = json_decode($project['config_json'] ?? '', true) ?: [];
        $s = $pdo->prepare("SELECT id, page_key, pin_index FROM cw_pins WHERE project_id = ? AND status = 'draft'");
        $s->execute([$project['id']]);
        $pins = $s->fetchAll();
        if (!$pins) cw_out(['ok' => true, 'count' => 0]);
        $times = cw_compute_schedule($pdo, (int)$project['pinterest_account_id'], $pins, $cfg['schedule'] ?? []);
        cw_out(['ok' => true, 'count' => count($pins), 'first' => min($times), 'last' => max($times)]);
    }
}

cw_out(['ok' => false, 'error' => 'Unknown action.']);
