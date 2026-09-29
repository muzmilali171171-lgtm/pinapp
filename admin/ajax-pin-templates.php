<?php
/**
 * AJAX backend for Admin → Canva:
 *   - the template editor (admin/pin-template-editor.php runs the user's Canva editor, pointed here):
 *     templates, list, load, save (draft), upload, uploads, delete_upload, stock_search, import_url, elements
 *   - publish     — saves the design + the layers/spec exported by assets/js/admin-template-editor.js
 *   - toggle      — switch a built-in or admin template on/off
 *   - delete      — delete an admin template
 *   - fonts       — list the Google fonts a spec needs (downloaded to TTF on publish)
 */
@set_time_limit(180);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/../includes/pin_template_registry.php';
require_once __DIR__ . '/includes/admin-auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['admin_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
at_ensure_schema($pdo);
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

function atx_out(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function atx_upload_dir(): string
{
    $d = __DIR__ . '/../uploads/designs/admin/';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

try {
    switch ($action) {
        /* ---------- editor: template lists ---------- */
        case 'templates': {
            // "Published" tab: the designs users published as templates (to start from).
            $st = $pdo->query("SELECT id, title, width, height, thumb_path, template_category FROM user_designs WHERE is_published = 1 ORDER BY published_at DESC LIMIT 500");
            atx_out(['ok' => true, 'templates' => $st->fetchAll()]);
        }
        case 'list': {
            // "My designs" tab: the admin's own pin templates (ids are negative so they never clash with user designs).
            $rows = $pdo->query("SELECT id, name AS title, width, height, thumb_path, status, updated_at FROM admin_pin_templates ORDER BY updated_at DESC LIMIT 500")->fetchAll();
            foreach ($rows as &$r) { $r['id'] = -(int)$r['id']; $r['is_published'] = $r['status'] === 'active' ? 1 : 0; }
            atx_out(['ok' => true, 'designs' => $rows]);
        }
        case 'load': {
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id < 0) {   // admin templates use negative ids inside the editor
                $st = $pdo->prepare("SELECT * FROM admin_pin_templates WHERE id = ?");
                $st->execute([abs($id)]);
                $row = $st->fetch();
                if (!$row || !$row['design_json']) atx_out(['ok' => false, 'error' => 'Template not found.']);
                atx_out(['ok' => true, 'design' => [
                    'id' => -(int)$row['id'], 'title' => $row['name'], 'width' => (int)$row['width'], 'height' => (int)$row['height'],
                    'json' => $row['design_json'], 'mine' => true,
                    'meta' => ['name' => $row['name'], 'category' => $row['category'], 'priority' => (int)$row['priority'],
                        'tags' => $row['tags'], 'status' => $row['status']],
                ]]);
            }
            $st = $pdo->prepare("SELECT * FROM user_designs WHERE id = ? AND is_published = 1");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) atx_out(['ok' => false, 'error' => 'Design not found.']);
            atx_out(['ok' => true, 'design' => ['id' => (int)$row['id'], 'title' => $row['title'], 'width' => (int)$row['width'],
                'height' => (int)$row['height'], 'json' => $row['design_json'], 'mine' => false]]);
        }

        /* ---------- editor: Save (draft) ---------- */
        case 'save': {
            $id = abs((int)($_POST['id'] ?? 0));
            $name = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120) ?: 'Untitled template';
            $w = max(50, min(8000, (int)($_POST['width'] ?? 1000)));
            $h = max(50, min(8000, (int)($_POST['height'] ?? 1500)));
            $json = (string)($_POST['json'] ?? '');
            if ($json === '' || strlen($json) > DESIGN_MAX_JSON_BYTES || json_decode($json) === null) atx_out(['ok' => false, 'error' => 'The design data is invalid or too large.']);
            if ($id) {
                $st = $pdo->prepare("UPDATE admin_pin_templates SET name = ?, width = ?, height = ?, design_json = ? WHERE id = ?");
                $st->execute([$name, $w, $h, $json, $id]);
                if (!$pdo->query("SELECT 1 FROM admin_pin_templates WHERE id = " . $id)->fetchColumn()) atx_out(['ok' => false, 'error' => 'Template not found.']);
            } else {
                $pdo->prepare("INSERT INTO admin_pin_templates (name, width, height, design_json, status) VALUES (?, ?, ?, ?, 'draft')")->execute([$name, $w, $h, $json]);
                $id = (int)$pdo->lastInsertId();
            }
            $thumb = (string)($_POST['thumb'] ?? '');
            if ($thumb !== '') {
                $dir = at_dir($id);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (at_save_data_url($thumb, $dir . 'thumb.jpg')) {
                    $pdo->prepare("UPDATE admin_pin_templates SET thumb_path = ? WHERE id = ?")->execute(['uploads/pin-templates/at_' . $id . '/thumb.jpg', $id]);
                }
            }
            atx_out(['ok' => true, 'id' => -$id]);
        }

        /* ---------- editor: Publish ---------- */
        case 'publish': {
            $id = abs((int)($_POST['id'] ?? 0));
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
            if ($name === '') atx_out(['ok' => false, 'error' => 'Please enter a template name.']);
            $priority = max(1, min(10, (int)($_POST['priority'] ?? 5)));
            $category = mb_substr(trim((string)($_POST['category'] ?? '')), 0, 80);
            if ($category !== '' && !in_array($category, pin_template_categories_all(), true)) $category = '';
            $tags = [];
            foreach (explode(',', (string)($_POST['tags'] ?? '')) as $t) {
                $t = mb_substr(trim(strip_tags($t)), 0, 30);
                if ($t !== '' && !in_array(mb_strtolower($t), array_map('mb_strtolower', $tags), true)) $tags[] = $t;
            }
            $w = max(50, min(8000, (int)($_POST['width'] ?? 1000)));
            $h = max(50, min(8000, (int)($_POST['height'] ?? 1500)));
            $json = (string)($_POST['json'] ?? '');
            if ($json === '' || strlen($json) > DESIGN_MAX_JSON_BYTES || json_decode($json) === null) atx_out(['ok' => false, 'error' => 'The design data is invalid or too large.']);
            $spec = json_decode((string)($_POST['spec'] ?? ''), true);
            if (!is_array($spec)) atx_out(['ok' => false, 'error' => 'The template layout could not be read.']);

            // Clean the spec: frames (boxes) and typed texts.
            $frames = [];
            foreach (array_slice((array)($spec['frames'] ?? []), 0, 12) as $f) {
                $frames[] = ['x' => (float)$f['x'], 'y' => (float)$f['y'], 'w' => max(1, (float)$f['w']), 'h' => max(1, (float)$f['h']), 'rect' => !empty($f['rect'])];
            }
            $texts = [];
            $roles = [];
            foreach (array_slice((array)($spec['texts'] ?? []), 0, 20) as $t) {
                $role = in_array($t['role'] ?? '', ['main', 'number', 'cta', 'website'], true) ? $t['role'] : null;
                if (!$role) continue;
                $roles[$role] = true;
                $texts[] = [
                    'role' => $role, 'x' => (float)$t['x'], 'y' => (float)$t['y'], 'w' => max(5, (float)$t['w']), 'h' => max(5, (float)$t['h']),
                    'font' => mb_substr((string)($t['font'] ?? 'Poppins'), 0, 60), 'weight' => (int)($t['weight'] ?? 400), 'italic' => !empty($t['italic']),
                    'size' => max(6, (float)($t['size'] ?? 60)), 'fill' => mb_substr((string)($t['fill'] ?? '#111111'), 0, 40),
                    'align' => in_array($t['align'] ?? '', ['left', 'right', 'center'], true) ? $t['align'] : 'center',
                    'line_height' => (float)($t['line_height'] ?? 1.16), 'upper' => !empty($t['upper']),
                    'stroke' => mb_substr((string)($t['stroke'] ?? ''), 0, 40), 'stroke_width' => (float)($t['stroke_width'] ?? 0),
                    'shadow' => is_array($t['shadow'] ?? null) ? ['color' => mb_substr((string)($t['shadow']['color'] ?? ''), 0, 40), 'x' => (float)($t['shadow']['x'] ?? 0), 'y' => (float)($t['shadow']['y'] ?? 0)] : null,
                    'highlight' => mb_substr((string)($t['highlight'] ?? ''), 0, 40),
                ];
            }
            if (empty($roles['main'])) atx_out(['ok' => false, 'error' => 'Mark one text as "Main title" — that is where each pin\'s title goes.']);

            if ($id) {
                if (!$pdo->query("SELECT 1 FROM admin_pin_templates WHERE id = " . $id)->fetchColumn()) atx_out(['ok' => false, 'error' => 'Template not found.']);
            } else {
                $pdo->prepare("INSERT INTO admin_pin_templates (name, status) VALUES (?, 'draft')")->execute([$name]);
                $id = (int)$pdo->lastInsertId();
            }
            $dir = at_dir($id);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            foreach (array_merge(glob($dir . 'mask_*.png') ?: [], [$dir . 'below.png', $dir . 'above.png']) as $old) if (is_file($old)) @unlink($old);
            if (!empty($_POST['below']) && !at_save_data_url((string)$_POST['below'], $dir . 'below.png')) atx_out(['ok' => false, 'error' => 'The background layer could not be saved.']);
            if (!empty($_POST['above']) && !at_save_data_url((string)$_POST['above'], $dir . 'above.png')) atx_out(['ok' => false, 'error' => 'The top layer could not be saved.']);
            $masks = (array)json_decode((string)($_POST['masks'] ?? '[]'), true);
            foreach ($frames as $i => $f) {
                if (!$f['rect'] && !empty($masks[$i]) && !at_save_data_url((string)$masks[$i], $dir . 'mask_' . $i . '.png')) $frames[$i]['rect'] = true;
            }
            if (!empty($_POST['thumb']) && at_save_data_url((string)$_POST['thumb'], $dir . 'thumb.jpg')) $thumbPath = 'uploads/pin-templates/at_' . $id . '/thumb.jpg';

            // Fonts: download each typed text's Google font once as TTF (GD can't use web fonts).
            foreach ($texts as &$t) {
                $file = at_font_file($t['font'], $t['weight'], $t['italic'], true);
                $root = realpath(__DIR__ . '/..');
                $real = realpath($file);
                $t['font_file'] = ($real && $root && strpos($real, $root) === 0) ? ltrim(str_replace('\\', '/', substr($real, strlen($root))), '/') : '';
            }
            unset($t);

            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE admin_pin_templates SET name = ?, category = ?, priority = ?, tags = ?, status = ?, width = ?, height = ?,
                    frames = ?, numbered = ?, design_json = ?, spec_json = ?, thumb_path = COALESCE(?, thumb_path), updated_at = NOW() WHERE id = ?")
                ->execute([$name, $category, $priority, implode(',', $tags), $status, $w, $h, count($frames), empty($roles['number']) ? 0 : 1,
                    $json, json_encode(['frames' => $frames, 'texts' => $texts]), $thumbPath ?? null, $id]);
            log_event($pdo, 'system', "Admin published pin template #$id \"$name\"");
            atx_out(['ok' => true, 'id' => $id, 'key' => 'at_' . $id,
                'preview' => '../pin-template-preview?s=at_' . $id . '&v=' . substr(md5(microtime()), 0, 8)]);
        }

        /* ---------- list page: switch on/off, delete ---------- */
        case 'toggle': {
            $key = (string)($_POST['key'] ?? '');
            $on = !empty($_POST['active']);
            if (preg_match('/^at_(\d+)$/', $key, $m)) {
                $pdo->prepare("UPDATE admin_pin_templates SET status = ? WHERE id = ? AND status <> 'draft'")->execute([$on ? 'active' : 'inactive', (int)$m[1]]);
            } else {
                if (!isset(pin_template_registry()[$key])) atx_out(['ok' => false, 'error' => 'Unknown template.']);
                $pdo->prepare("INSERT INTO pin_template_status (tkey, active) VALUES (?, ?) ON DUPLICATE KEY UPDATE active = VALUES(active)")->execute([$key, $on ? 1 : 0]);
            }
            atx_out(['ok' => true, 'active' => $on]);
        }
        case 'bulk_toggle': {
            $keys = array_slice((array)json_decode((string)($_POST['keys'] ?? '[]'), true), 0, 2000);
            $on = !empty($_POST['active']);
            $reg = pin_template_registry();
            $st = $pdo->prepare("INSERT INTO pin_template_status (tkey, active) VALUES (?, ?) ON DUPLICATE KEY UPDATE active = VALUES(active)");
            $at = $pdo->prepare("UPDATE admin_pin_templates SET status = ? WHERE id = ? AND status <> 'draft'");
            $n = 0;
            foreach ($keys as $k) {
                $k = (string)$k;
                if (preg_match('/^at_(\d+)$/', $k, $m)) { $at->execute([$on ? 'active' : 'inactive', (int)$m[1]]); $n++; }
                elseif (isset($reg[$k])) { $st->execute([$k, $on ? 1 : 0]); $n++; }
            }
            atx_out(['ok' => true, 'count' => $n]);
        }
        case 'delete': {
            $id = abs((int)($_POST['id'] ?? 0));
            $pdo->prepare("DELETE FROM admin_pin_templates WHERE id = ?")->execute([$id]);
            $dir = at_dir($id);
            foreach (glob($dir . '*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
            log_event($pdo, 'system', "Admin deleted pin template #$id");
            atx_out(['ok' => true]);
        }

        /* ---------- editor: uploads / stock photos / elements ---------- */
        case 'upload': {
            if (empty($_FILES['files'])) atx_out(['ok' => false, 'error' => 'No file received.']);
            $files = $_FILES['files'];
            $count = is_array($files['name']) ? count($files['name']) : 1;
            $saved = []; $errors = [];
            $dir = atx_upload_dir();
            for ($i = 0; $i < $count; $i++) {
                $f = is_array($files['name'])
                    ? ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]]
                    : $files;
                [$ok, $extOrErr] = design_check_upload($f);
                if (!$ok) { $errors[] = $f['name'] . ': ' . $extOrErr; continue; }
                $name = 'up_' . bin2hex(random_bytes(8)) . '.' . $extOrErr;
                if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { $errors[] = $f['name'] . ': could not be saved.'; continue; }
                @chmod($dir . $name, 0644);
                [$w, $h] = @getimagesize($dir . $name) ?: [0, 0];
                $saved[] = ['id' => crc32($name), 'path' => 'uploads/designs/admin/' . $name, 'width' => $w, 'height' => $h];
            }
            atx_out(['ok' => (bool)$saved, 'uploads' => $saved, 'error' => $errors ? implode(' ', $errors) : null]);
        }
        case 'uploads': {
            $list = [];
            $files = array_values(array_filter(glob(atx_upload_dir() . '*') ?: [], fn($f) => preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)));
            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($files, 0, 300) as $f) {
                [$w, $h] = @getimagesize($f) ?: [0, 0];
                $list[] = ['id' => crc32(basename($f)), 'path' => 'uploads/designs/admin/' . basename($f), 'width' => $w, 'height' => $h];
            }
            atx_out(['ok' => true, 'uploads' => $list]);
        }
        case 'delete_upload': {
            // Uploads are matched by their id (crc32 of the file name).
            $id = (int)($_POST['id'] ?? 0);
            foreach (glob(atx_upload_dir() . '*') ?: [] as $f) if (crc32(basename($f)) === $id) @unlink($f);
            atx_out(['ok' => true]);
        }
        case 'stock_search': {
            $q = trim((string)($_POST['query'] ?? ''));
            if ($q === '') atx_out(['ok' => false, 'error' => 'Type something to search.']);
            atx_out(pexels_search($pdo, $q, max(1, (int)($_POST['page'] ?? 1)), 24));
        }
        case 'import_url': {
            $url = trim((string)($_POST['url'] ?? ''));
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if (!preg_match('#^https://#i', $url) || !preg_match('/(^|\.)pexels\.com$/', $host)) atx_out(['ok' => false, 'error' => 'Only stock photos can be imported.']);
            $bytes = at_http_get($url);
            $gd = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
            if (!$gd) atx_out(['ok' => false, 'error' => 'Could not download that photo.']);
            $name = 'stock_' . bin2hex(random_bytes(8)) . '.jpg';
            imagejpeg($gd, atx_upload_dir() . $name, 92);
            $w = imagesx($gd); $h = imagesy($gd);
            imagedestroy($gd);
            atx_out(['ok' => true, 'upload' => ['id' => crc32($name), 'path' => 'uploads/designs/admin/' . $name, 'width' => $w, 'height' => $h]]);
        }
        case 'elements': {
            design_ensure_element_type($pdo);
            $st = $pdo->query("SELECT id, name, element_type AS etype, category, file_path AS path, file_type AS type FROM design_elements WHERE status = 'active' ORDER BY element_type, category, id DESC LIMIT 3000");
            atx_out(['ok' => true, 'elements' => $st->fetchAll()]);
        }
        case 'share_status':
            atx_out(['ok' => true, 'shared' => false]);
    }
    atx_out(['ok' => false, 'error' => 'Not available in the template editor.']);
} catch (Throwable $e) {
    error_log('ajax-pin-templates: ' . $e->getMessage());
    atx_out(['ok' => false, 'error' => 'Something went wrong: ' . $e->getMessage()]);
}
