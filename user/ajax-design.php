<?php
/**
 * AJAX backend for the Custom Design editor (user/design-editor.php) and "Your Designs" page.
 * POST action=… ; always returns JSON.
 */
@set_time_limit(120);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$userId = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

function out(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

try {
    switch ($action) {

        /* ---------- designs ---------- */
        case 'list': {
            $st = $pdo->prepare("SELECT id, title, width, height, thumb_path, is_published, updated_at FROM user_designs WHERE user_id = ? ORDER BY updated_at DESC LIMIT 500");
            $st->execute([$userId]);
            out(['ok' => true, 'designs' => $st->fetchAll()]);
        }
        case 'templates': {
            $st = $pdo->query("SELECT id, title, width, height, thumb_path, template_category FROM user_designs WHERE is_published = 1 ORDER BY published_at DESC LIMIT 500");
            out(['ok' => true, 'templates' => $st->fetchAll()]);
        }
        case 'load': {
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM user_designs WHERE id = ? AND (user_id = ? OR is_published = 1)");
            $st->execute([$id, $userId]);
            $row = $st->fetch();
            if (!$row) out(['ok' => false, 'error' => 'Design not found.']);
            out(['ok' => true, 'design' => [
                'id' => (int)$row['id'], 'title' => $row['title'], 'width' => (int)$row['width'], 'height' => (int)$row['height'],
                'json' => $row['design_json'], 'mine' => (int)$row['user_id'] === $userId,
            ]]);
        }
        case 'save': {
            $id = (int)($_POST['id'] ?? 0);
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255) ?: 'Untitled design';
            $w = max(50, min(8000, (int)($_POST['width'] ?? 1000)));
            $h = max(50, min(8000, (int)($_POST['height'] ?? 1500)));
            $json = (string)($_POST['json'] ?? '');
            if ($json === '' || strlen($json) > DESIGN_MAX_JSON_BYTES || json_decode($json) === null) out(['ok' => false, 'error' => 'The design data is invalid or too large.']);
            if ($id) {
                $row = design_row_for_user($pdo, $id, $userId);
                if (!$row) out(['ok' => false, 'error' => 'Design not found.']);
                $pdo->prepare("UPDATE user_designs SET title = ?, width = ?, height = ?, design_json = ? WHERE id = ?")->execute([$title, $w, $h, $json, $id]);
            } else {
                $pdo->prepare("INSERT INTO user_designs (user_id, title, width, height, design_json) VALUES (?, ?, ?, ?, ?)")->execute([$userId, $title, $w, $h, $json]);
                $id = (int)$pdo->lastInsertId();
                $row = null;
            }
            $thumb = (string)($_POST['thumb'] ?? '');
            if ($thumb !== '') {
                design_user_dir($userId);
                $rel = 'uploads/designs/' . $userId . '/thumb_' . $id . '_' . bin2hex(random_bytes(3)) . '.jpg';
                if (design_save_data_url($thumb, __DIR__ . '/../' . $rel, 3 * 1024 * 1024)) {
                    if ($row) design_delete_thumb($row['thumb_path']);
                    $pdo->prepare("UPDATE user_designs SET thumb_path = ? WHERE id = ?")->execute([$rel, $id]);
                }
            }
            out(['ok' => true, 'id' => $id]);
        }
        case 'share': {
            // Publishes the design to a public link right away (no approval needed).
            design_ensure_share_schema($pdo);
            $id = (int)($_POST['id'] ?? 0);
            $row = design_row_for_user($pdo, $id, $userId);
            if (!$row) out(['ok' => false, 'error' => 'Save the design first, then share it.']);
            $imgs = json_decode((string)($_POST['images'] ?? '[]'), true);
            $imgs = is_array($imgs) ? array_slice($imgs, 0, 10) : [];
            $saved = [];
            design_user_dir($userId);
            foreach ($imgs as $n => $data) {
                $rel = 'uploads/designs/' . $userId . '/share_' . $id . '_' . bin2hex(random_bytes(4)) . '_' . $n . '.jpg';
                if (is_string($data) && design_save_data_url($data, __DIR__ . '/../' . $rel, 6 * 1024 * 1024)) $saved[] = $rel;
            }
            if (!$saved && !$row['thumb_path']) out(['ok' => false, 'error' => 'Could not make a preview of this design. Try again.']);
            if ($saved) design_delete_share_images($row['share_images'] ?? null);
            $token = !empty($row['share_token']) ? $row['share_token'] : bin2hex(random_bytes(8));
            $pdo->prepare("UPDATE user_designs SET share_token = ?, shared_at = COALESCE(shared_at, NOW()), share_images = ? WHERE id = ?")
                ->execute([$token, $saved ? json_encode($saved) : ($row['share_images'] ?? null), $id]);
            $first = $saved[0] ?? ($row['thumb_path'] ?: '');
            out(['ok' => true, 'url' => design_share_url($token), 'image' => $first ? rtrim(APP_URL, '/') . '/' . $first : '', 'title' => $row['title']]);
        }
        case 'unshare': {
            design_ensure_share_schema($pdo);
            $row = design_row_for_user($pdo, (int)($_POST['id'] ?? 0), $userId);
            if (!$row) out(['ok' => false, 'error' => 'Design not found.']);
            design_delete_share_images($row['share_images'] ?? null);
            $pdo->prepare("UPDATE user_designs SET share_token = NULL, shared_at = NULL, share_images = NULL WHERE id = ?")->execute([(int)$row['id']]);
            out(['ok' => true]);
        }
        case 'share_status': {
            design_ensure_share_schema($pdo);
            $row = design_row_for_user($pdo, (int)($_POST['id'] ?? 0), $userId);
            out(['ok' => true, 'shared' => $row && !empty($row['share_token']), 'url' => $row && !empty($row['share_token']) ? design_share_url($row['share_token']) : '']);
        }
        case 'rename': {
            $id = (int)($_POST['id'] ?? 0);
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255) ?: 'Untitled design';
            $pdo->prepare("UPDATE user_designs SET title = ? WHERE id = ? AND user_id = ?")->execute([$title, $id, $userId]);
            out(['ok' => true]);
        }
        case 'duplicate': {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM user_designs WHERE id = ? AND (user_id = ? OR is_published = 1)");
            $st->execute([$id, $userId]);
            $row = $st->fetch();
            if (!$row) out(['ok' => false, 'error' => 'Design not found.']);
            $pdo->prepare("INSERT INTO user_designs (user_id, title, width, height, design_json, thumb_path) VALUES (?, ?, ?, ?, ?, NULL)")
                ->execute([$userId, mb_substr(($row['user_id'] == $userId ? 'Copy of ' : '') . $row['title'], 0, 255), $row['width'], $row['height'], $row['design_json']]);
            $newId = (int)$pdo->lastInsertId();
            // copy the thumbnail so the new design shows a preview straight away
            if ($row['thumb_path'] && is_file(__DIR__ . '/../' . $row['thumb_path'])) {
                design_user_dir($userId);
                $rel = 'uploads/designs/' . $userId . '/thumb_' . $newId . '_' . bin2hex(random_bytes(3)) . '.jpg';
                if (@copy(__DIR__ . '/../' . $row['thumb_path'], __DIR__ . '/../' . $rel)) {
                    $pdo->prepare("UPDATE user_designs SET thumb_path = ? WHERE id = ?")->execute([$rel, $newId]);
                }
            }
            out(['ok' => true, 'id' => $newId]);
        }
        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            $row = design_row_for_user($pdo, $id, $userId);
            if (!$row) out(['ok' => false, 'error' => 'Design not found.']);
            design_delete_thumb($row['thumb_path']);
            design_delete_share_images($row['share_images'] ?? null);
            $pdo->prepare("DELETE FROM user_designs WHERE id = ?")->execute([$id]);
            out(['ok' => true]);
        }

        /* ---------- "Use this design" → Bulk Pin Scheduler (one pin per chosen page) ---------- */
        case 'use_check': {
            require_once __DIR__ . '/../includes/pricing_functions.php';
            $count = max(1, (int)($_POST['count'] ?? 1));
            if ($count > 100) out(['ok' => false, 'error' => 'Choose up to 100 pages at a time.']);
            $bulk = require_plan_feature($pdo, $userId, 'bulk_scheduling');
            if (!$bulk['allowed'] && $count > 1) {
                out(['ok' => false, 'error' => 'Scheduling several pages at once needs the Bulk Pin Scheduler, which isn\'t in your plan. Choose one page, or upgrade your plan.']);
            }
            out(['ok' => true, 'bulk' => (bool)$bulk['allowed']]);
        }
        case 'use_finish': {
            require_once __DIR__ . '/../includes/pricing_functions.php';
            $items = json_decode((string)($_POST['items'] ?? ''), true);
            if (!is_array($items) || !$items) out(['ok' => false, 'error' => 'Nothing to schedule.']);
            $clean = [];
            foreach (array_slice($items, 0, 100) as $it) {
                $file = basename((string)($it['file'] ?? ''));
                if (!preg_match('/^design_[a-f0-9]{16}\.jpg$/', $file) || !is_file(__DIR__ . '/../uploads/pins/' . $file)) continue;
                $clean[] = ['file' => $file, 'name' => mb_substr(trim((string)($it['name'] ?? '')), 0, 100)];
            }
            if (!$clean) out(['ok' => false, 'error' => 'The pin images could not be prepared.']);
            $bulk = require_plan_feature($pdo, $userId, 'bulk_scheduling');
            if (!$bulk['allowed'] && count($clean) === 1) {
                out(['ok' => true, 'url' => 'schedule-create?design=' . rawurlencode($clean[0]['file'])]);
            }
            $token = bin2hex(random_bytes(8));
            if (!isset($_SESSION['design_use']) || !is_array($_SESSION['design_use'])) $_SESSION['design_use'] = [];
            $_SESSION['design_use'] = array_slice($_SESSION['design_use'], -5, null, true); // keep the session small
            $_SESSION['design_use'][$token] = ['items' => $clean, 'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150), 'at' => time()];
            out(['ok' => true, 'url' => 'bulk-schedule?designs=' . $token]);
        }

        /* ---------- "Use this design": save the rendered image as a pin image ---------- */
        case 'use': {
            $img = (string)($_POST['image'] ?? '');
            $dir = __DIR__ . '/../uploads/pins/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = 'design_' . bin2hex(random_bytes(8)) . '.jpg';
            // store as JPEG regardless of what the browser sent
            if (!preg_match('#^data:image/(png|jpe?g);base64,#i', $img)) out(['ok' => false, 'error' => 'Could not read the design image.']);
            $bytes = base64_decode(substr($img, strpos($img, ',') + 1), true);
            $gd = $bytes ? @imagecreatefromstring($bytes) : false;
            if (!$gd) out(['ok' => false, 'error' => 'Could not read the design image.']);
            $flat = imagecreatetruecolor(imagesx($gd), imagesy($gd));
            imagefilledrectangle($flat, 0, 0, imagesx($gd), imagesy($gd), imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $gd, 0, 0, 0, 0, imagesx($gd), imagesy($gd));
            imagejpeg($flat, $dir . $name, 93);
            imagedestroy($gd); imagedestroy($flat);
            // Analytics → Template Tracking: remember which custom design this pin image came from.
            $designId = (int)($_POST['design_id'] ?? 0);
            if ($designId && function_exists('tt_record_image')) {
                $dt = mb_substr(trim((string)($_POST['design_title'] ?? '')), 0, 200) ?: ('Design #' . $designId);
                tt_record_image((string)file_get_contents($dir . $name), 'design', 'design:' . $designId, $dt);
            }
            out(['ok' => true, 'file' => $name, 'path' => 'uploads/pins/' . $name]);
        }

        /* ---------- uploads ---------- */
        case 'upload': {
            if (empty($_FILES['files'])) out(['ok' => false, 'error' => 'No file received.']);
            $files = $_FILES['files'];
            $count = is_array($files['name']) ? count($files['name']) : 1;
            $saved = []; $errors = [];
            $dir = design_user_dir($userId);
            for ($i = 0; $i < $count; $i++) {
                $f = is_array($files['name'])
                    ? ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]]
                    : $files;
                [$ok, $extOrErr] = design_check_upload($f);
                if (!$ok) { $errors[] = $f['name'] . ': ' . $extOrErr; continue; }
                $name = 'up_' . bin2hex(random_bytes(8)) . '.' . $extOrErr;
                if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { $errors[] = $f['name'] . ': could not be saved.'; continue; }
                [$w, $h] = @getimagesize($dir . $name) ?: [0, 0];
                $rel = 'uploads/designs/' . $userId . '/' . $name;
                $pdo->prepare("INSERT INTO design_uploads (user_id, file_path, width, height) VALUES (?, ?, ?, ?)")->execute([$userId, $rel, $w, $h]);
                $saved[] = ['id' => (int)$pdo->lastInsertId(), 'path' => $rel, 'width' => $w, 'height' => $h];
            }
            out(['ok' => (bool)$saved, 'uploads' => $saved, 'error' => $errors ? implode(' ', $errors) : null]);
        }
        case 'uploads': {
            $st = $pdo->prepare("SELECT id, file_path AS path, width, height FROM design_uploads WHERE user_id = ? ORDER BY id DESC LIMIT 300");
            $st->execute([$userId]);
            out(['ok' => true, 'uploads' => $st->fetchAll()]);
        }
        case 'delete_upload': {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM design_uploads WHERE id = ? AND user_id = ?");
            $st->execute([$id, $userId]);
            $row = $st->fetch();
            if ($row) {
                $abs = __DIR__ . '/../' . $row['file_path'];
                if (strpos($row['file_path'], 'uploads/designs/') === 0 && is_file($abs)) @unlink($abs);
                $pdo->prepare("DELETE FROM design_uploads WHERE id = ?")->execute([$id]);
            }
            out(['ok' => true]);
        }

        /* ---------- stock photos (Pexels) → imported into the user's uploads so exports aren't blocked by CORS ---------- */
        case 'stock_search': {
            $q = trim((string)($_POST['query'] ?? ''));
            if ($q === '') out(['ok' => false, 'error' => 'Type something to search.']);
            out(pexels_search($pdo, $q, max(1, (int)($_POST['page'] ?? 1)), 24));
        }
        case 'import_url': {
            $url = trim((string)($_POST['url'] ?? ''));
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if (!preg_match('#^https://#i', $url) || !preg_match('/(^|\.)pexels\.com$/', $host)) out(['ok' => false, 'error' => 'Only stock photos can be imported.']);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 40, CURLOPT_MAXFILESIZE => DESIGN_MAX_UPLOAD_BYTES]);
            $bytes = curl_exec($ch);
            curl_close($ch);
            $gd = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
            if (!$gd) out(['ok' => false, 'error' => 'Could not download that photo.']);
            $dir = design_user_dir($userId);
            $name = 'stock_' . bin2hex(random_bytes(8)) . '.jpg';
            imagejpeg($gd, $dir . $name, 92);
            $w = imagesx($gd); $h = imagesy($gd);
            imagedestroy($gd);
            $rel = 'uploads/designs/' . $userId . '/' . $name;
            $pdo->prepare("INSERT INTO design_uploads (user_id, file_path, width, height) VALUES (?, ?, ?, ?)")->execute([$userId, $rel, $w, $h]);
            out(['ok' => true, 'upload' => ['id' => (int)$pdo->lastInsertId(), 'path' => $rel, 'width' => $w, 'height' => $h]]);
        }

        /* ---------- admin elements ---------- */
        case 'elements': {
            design_ensure_element_type($pdo);
            $st = $pdo->query("SELECT id, name, element_type AS etype, category, file_path AS path, file_type AS type FROM design_elements WHERE status = 'active' ORDER BY element_type, category, id DESC LIMIT 3000");
            out(['ok' => true, 'elements' => $st->fetchAll()]);
        }
    }
    out(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('ajax-design: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Something went wrong. Please try again in a few minutes.']);
}
