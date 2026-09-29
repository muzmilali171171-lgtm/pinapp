<?php
/**
 * External image storage for EVERY image the app creates or receives — pin images (uploaded,
 * AI-made, Classic Wizard, Auto Article, Website pins), article images, Storage-library uploads
 * and design files — on the admin's connected accounts (Cloudflare R2 / Amazon S3 / Backblaze B2,
 * Admin → Storage Settings).
 *
 * Rules:
 *  - External storage ON (default) → each image is uploaded to the first active account that
 *    still has room (priority order, free before paid). If that upload fails, the next account
 *    is tried.
 *  - No account connected, every account at its limit, or an upload error → the image simply
 *    stays on hosting (nothing breaks) and the reason/error is shown in Storage Settings.
 *  - Files are still written to hosting first (all the image tools work on local files); the
 *    public URL used for Pinterest / WordPress / the app is the external one once uploaded.
 *  - Optional: remove the hosting copy N days after it's safely on external storage. Any request
 *    for a removed file is redirected to the external copy (media.php + .htaccess), and code that
 *    needs the bytes again gets them re-downloaded via media_local_file().
 */

const EXT_CATEGORIES = [
    'pins'     => ['label' => 'Pin images (uploaded, AI-made, bulk, website, article & wizard pins)', 'dirs' => ['uploads/pins/']],
    'articles' => ['label' => 'Article images (featured + in-article)', 'dirs' => ['uploads/articles/', 'uploads/blog/']],
    'uploads'  => ['label' => 'Storage library uploads', 'dirs' => ['uploads/storage/']],
    'designs'  => ['label' => 'Designs & design thumbnails', 'dirs' => ['uploads/designs/']],
];
const EXT_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

/* ===================== Schema + settings ===================== */

function ext_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_extstorage_v1';
    if (is_file($flag)) return;
    $ok = true;
    $sql = [
        "CREATE TABLE IF NOT EXISTS storage_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS external_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            local_path VARCHAR(500) NOT NULL,
            category VARCHAR(20) NOT NULL,
            provider_row_id INT DEFAULT NULL,
            object_key VARCHAR(600) DEFAULT NULL,
            public_url VARCHAR(800) DEFAULT NULL,
            size_bytes BIGINT NOT NULL DEFAULT 0,
            status ENUM('uploaded','hosting') NOT NULL DEFAULT 'hosting',
            reason VARCHAR(30) DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            attempts INT NOT NULL DEFAULT 0,
            local_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_local_path (local_path(255)),
            KEY idx_status (status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS storage_error_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_row_id INT DEFAULT NULL,
            local_path VARCHAR(500) DEFAULT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($sql as $q) {
        try { $pdo->exec($q); } catch (Throwable $e) { $ok = false; }
    }
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    foreach ([['last_error', 'last_error TEXT DEFAULT NULL'], ['last_error_at', 'last_error_at DATETIME DEFAULT NULL']] as [$col, $def]) {
        try {
            $check->execute(['storage_providers', $col]);
            if ((int)$check->fetchColumn() === 0) $pdo->exec("ALTER TABLE storage_providers ADD COLUMN $def");
        } catch (Throwable $e) { $ok = false; }
    }
    if ($ok) {
        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0755, true);
        @file_put_contents($flag, date('c'));
    }
}

/** All settings with defaults: external storage ON, every image type ON, keep hosting copy. */
function ext_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    ext_ensure_schema($pdo);
    $s = [
        'external_enabled' => 1,
        'store_pins' => 1, 'store_articles' => 1, 'store_uploads' => 1, 'store_designs' => 1,
        'delete_local_after_days' => 0,
        // Remove the hosting copy N days after a pin is published / N hours after an article is published (0 = off).
        'pin_delete_after_days' => 5,
        'article_delete_after_hours' => 1,
    ];
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM storage_settings")->fetchAll() as $r) {
            if (array_key_exists($r['setting_key'], $s)) $s[$r['setting_key']] = (int)$r['setting_value'];
        }
    } catch (Throwable $e) { /* table missing → defaults */ }
    return $cache = $s;
}

function ext_save_settings(PDO $pdo, array $values): void
{
    ext_ensure_schema($pdo);
    $stmt = $pdo->prepare("INSERT INTO storage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($values as $k => $v) $stmt->execute([$k, (string)(int)$v]);
}

function ext_log_error(PDO $pdo, ?int $providerId, ?string $localPath, string $message): void
{
    try {
        $pdo->prepare("INSERT INTO storage_error_log (provider_row_id, local_path, message) VALUES (?, ?, ?)")
            ->execute([$providerId, $localPath, mb_substr($message, 0, 2000)]);
        if ($providerId) {
            $pdo->prepare("UPDATE storage_providers SET last_error = ?, last_error_at = NOW() WHERE id = ?")
                ->execute([mb_substr($message, 0, 2000), $providerId]);
        }
        // keep the log small
        if (random_int(1, 50) === 1) $pdo->exec("DELETE FROM storage_error_log WHERE created_at < (NOW() - INTERVAL 30 DAY)");
    } catch (Throwable $e) { /* best effort */ }
}

/* ===================== Helpers ===================== */

function ext_normalize_rel(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    $app = rtrim(defined('APP_URL') ? APP_URL : '', '/') . '/';
    if ($app !== '/' && strpos($rel, ltrim($app, '/')) === 0) $rel = substr($rel, strlen(ltrim($app, '/')));
    return $rel;
}

/** Which image type a relative path belongs to (null = not something we store externally). */
function ext_category_for(string $rel): ?string
{
    $rel = ext_normalize_rel($rel);
    if (strpos($rel, '..') !== false) return null;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, EXT_IMAGE_EXT, true)) return null;
    foreach (EXT_CATEGORIES as $cat => $info) {
        foreach ($info['dirs'] as $d) if (strpos($rel, $d) === 0) return $cat;
    }
    return null;
}

function ext_category_enabled(PDO $pdo, string $cat): bool
{
    $s = ext_settings($pdo);
    return !empty($s['external_enabled']) && !empty($s['store_' . $cat]);
}

function ext_mime(string $path): string
{
    $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'avif' => 'image/avif'];
    return $map[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

/** Active accounts that still have room for $size bytes, in fill order. */
function ext_provider_candidates(PDO $pdo, int $size): array
{
    try {
        $rows = $pdo->query("SELECT * FROM storage_providers WHERE status = 'active' ORDER BY (tier = 'paid'), priority ASC, id ASC")->fetchAll();
    } catch (Throwable $e) {
        return ['providers' => [], 'any' => false];
    }
    $out = [];
    foreach ($rows as $p) {
        if ((float)$p['used_bytes'] + $size <= (float)$p['capacity_gb'] * 1073741824) $out[] = $p;
    }
    return ['providers' => $out, 'any' => !empty($rows)];
}

/**
 * Uploads $body to the first account that accepts it. Returns
 * ['ok'=>true,'provider'=>row,'key'=>..,'public_url'=>..] or ['ok'=>false,'reason'=>'no_provider'|'limit'|'error','error'=>..].
 */
function ext_put_bytes(PDO $pdo, string $body, string $key, string $contentType, ?string $localPathForLog = null): array
{
    $cand = ext_provider_candidates($pdo, strlen($body));
    if (!$cand['any']) return ['ok' => false, 'reason' => 'no_provider', 'error' => 'No external storage account is connected.'];
    if (!$cand['providers']) {
        ext_log_error($pdo, null, $localPathForLog, 'All external storage accounts have reached their capacity limit — saved on hosting instead.');
        return ['ok' => false, 'reason' => 'limit', 'error' => 'All storage accounts are full.'];
    }
    $lastErr = null;
    foreach ($cand['providers'] as $p) {
        $r = s3_put_object(storage_provider_config($p), $key, $body, $contentType);
        if ($r['ok']) {
            $pdo->prepare("UPDATE storage_providers SET used_bytes = used_bytes + ? WHERE id = ?")->execute([strlen($body), $p['id']]);
            $publicUrl = $p['public_base_url'] ? rtrim($p['public_base_url'], '/') . '/' . $key : null;
            return ['ok' => true, 'provider' => $p, 'key' => $key, 'public_url' => $publicUrl];
        }
        $lastErr = strtoupper($p['provider']) . ' "' . ($p['label'] ?: $p['bucket']) . '": ' . $r['error'];
        ext_log_error($pdo, (int)$p['id'], $localPathForLog, $lastErr);
        // Provider said it's out of space → mark it full so it's skipped from now on.
        if (preg_match('/quota|storage.?limit|insufficient|exceed/i', (string)$r['error'])) {
            $pdo->prepare("UPDATE storage_providers SET used_bytes = GREATEST(used_bytes, capacity_gb * 1073741824) WHERE id = ?")->execute([$p['id']]);
        }
    }
    return ['ok' => false, 'reason' => 'error', 'error' => $lastErr ?: 'Upload failed.'];
}

/* ===================== Offload one hosted file ===================== */

function ext_get_row(PDO $pdo, string $rel): ?array
{
    try {
        $s = $pdo->prepare("SELECT *, (updated_at > (NOW() - INTERVAL 30 MINUTE)) AS recent FROM external_files WHERE local_path = ?");
        $s->execute([$rel]);
        return $s->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Makes sure the hosted file $rel is on external storage (when enabled for its type).
 * Returns the external_files row (status 'uploaded' or 'hosting'), or null when not applicable.
 * Failed/limited files are retried at most every 30 minutes.
 */
function ext_offload_file(PDO $pdo, string $rel): ?array
{
    ext_ensure_schema($pdo);
    $rel = ext_normalize_rel($rel);
    $cat = ext_category_for($rel);
    if (!$cat || !ext_category_enabled($pdo, $cat)) return null;

    $row = ext_get_row($pdo, $rel);
    if ($row && $row['status'] === 'uploaded') return $row;
    if ($row && !empty($row['recent']) && $row['reason'] !== 'no_provider') return $row; // tried < 30 min ago

    $full = __DIR__ . '/../' . $rel;
    if (!is_file($full)) return $row;
    $body = @file_get_contents($full);
    if ($body === false || $body === '') return $row;

    $key = 'webtopin/' . $rel; // same path as on hosting, so it's easy to find
    $r = ext_put_bytes($pdo, $body, $key, ext_mime($rel), $rel);

    if ($r['ok']) {
        $pdo->prepare("INSERT INTO external_files (local_path, category, provider_row_id, object_key, public_url, size_bytes, status, reason, last_error, attempts)
            VALUES (?, ?, ?, ?, ?, ?, 'uploaded', NULL, NULL, 1)
            ON DUPLICATE KEY UPDATE provider_row_id = VALUES(provider_row_id), object_key = VALUES(object_key), public_url = VALUES(public_url),
                size_bytes = VALUES(size_bytes), status = 'uploaded', reason = NULL, last_error = NULL, attempts = attempts + 1")
            ->execute([$rel, $cat, $r['provider']['id'], $r['key'], $r['public_url'], strlen($body)]);
        if (!$r['public_url']) {
            ext_log_error($pdo, (int)$r['provider']['id'], $rel, 'Uploaded, but this account has no Public Base URL — images are still served from hosting. Add the bucket\'s public URL in Storage Settings.');
        }
    } else {
        $pdo->prepare("INSERT INTO external_files (local_path, category, size_bytes, status, reason, last_error, attempts)
            VALUES (?, ?, ?, 'hosting', ?, ?, 1)
            ON DUPLICATE KEY UPDATE status = 'hosting', reason = VALUES(reason), last_error = VALUES(last_error), attempts = attempts + 1, updated_at = NOW()")
            ->execute([$rel, $cat, strlen($body), $r['reason'], $r['error']]);
    }
    return ext_get_row($pdo, $rel);
}

/* ===================== URLs + local bytes ===================== */

/**
 * Public URL for an app image path ('uploads/pins/x.jpg'): the external copy when it's there
 * (uploading it first if needed), otherwise the hosting URL. Used for Pinterest, WordPress, CSV, UI.
 */
function media_url(string $rel, bool $offloadNow = true): string
{
    global $pdo;
    if (preg_match('#^https?://#i', $rel)) return $rel;
    $rel = ext_normalize_rel($rel);
    $hostUrl = rtrim(APP_URL, '/') . '/' . $rel;
    if (!($pdo instanceof PDO) || !ext_category_for($rel)) return $hostUrl;
    try {
        $row = ext_get_row($pdo, $rel);
        if ((!$row || $row['status'] !== 'uploaded') && $offloadNow) $row = ext_offload_file($pdo, $rel);
        if ($row && $row['status'] === 'uploaded' && !empty($row['public_url'])) return $row['public_url'];
    } catch (Throwable $e) { /* fall back to hosting */ }
    return $hostUrl;
}

/** Absolute local path for $rel; if the hosting copy was removed, re-downloads it from external storage first. */
function media_local_file(string $rel): ?string
{
    global $pdo;
    $rel = ext_normalize_rel($rel);
    $full = __DIR__ . '/../' . $rel;
    if (is_file($full)) return realpath($full) ?: $full;
    if (!($pdo instanceof PDO)) return null;
    $row = ext_get_row($pdo, $rel);
    if (!$row || $row['status'] !== 'uploaded' || empty($row['public_url'])) return null;
    $ch = curl_init($row['public_url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 300 || $body === '') return null;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0755, true);
    if (@file_put_contents($full, $body) === false) return null;
    @chmod($full, 0644);
    $pdo->prepare("UPDATE external_files SET local_deleted = 0 WHERE id = ?")->execute([$row['id']]);
    return realpath($full) ?: $full;
}

/* ===================== Background work (called from cron/scheduler.php) ===================== */

/** Uploads images created/uploaded in the last $days days that aren't on external storage yet. */
function ext_sweep(PDO $pdo, int $limit = 25, int $days = 3, int $timeBudget = 40): array
{
    $out = ['uploaded' => 0, 'hosting' => 0, 'checked' => 0];
    $s = ext_settings($pdo);
    if (empty($s['external_enabled'])) return $out;
    $start = time();
    $since = time() - $days * 86400;
    $root = realpath(__DIR__ . '/..');
    $candidates = [];
    foreach (EXT_CATEGORIES as $cat => $info) {
        if (empty($s['store_' . $cat])) continue;
        foreach ($info['dirs'] as $d) {
            $dir = $root . '/' . $d;
            if (!is_dir($dir)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile() || ($days > 0 && $f->getMTime() < $since)) continue;
                $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
                if (!ext_category_for($rel)) continue;
                $candidates[$rel] = $f->getMTime();
            }
        }
    }
    if (!$candidates) return $out;
    arsort($candidates); // newest first
    $paths = array_keys($candidates);

    // Skip files already uploaded (or recently tried) in one query per chunk.
    $done = [];
    foreach (array_chunk($paths, 500) as $chunk) {
        $q = $pdo->prepare("SELECT local_path FROM external_files WHERE local_path IN (" . implode(',', array_fill(0, count($chunk), '?')) . ")
            AND (status = 'uploaded' OR updated_at > (NOW() - INTERVAL 30 MINUTE))");
        $q->execute($chunk);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $p) $done[$p] = true;
    }
    foreach ($paths as $rel) {
        if (isset($done[$rel])) continue;
        if ($out['checked'] >= $limit || time() - $start > $timeBudget) break;
        $out['checked']++;
        $row = ext_offload_file($pdo, $rel);
        if ($row && $row['status'] === 'uploaded') $out['uploaded']++; else $out['hosting']++;
        if (($row['reason'] ?? null) === 'no_provider') break; // nothing to upload to — stop early
    }
    return $out;
}

/** Removes hosting copies older than the admin's "delete after N days" setting (0 = never). */
function ext_cleanup_local(PDO $pdo, int $limit = 200): int
{
    $days = (int)(ext_settings($pdo)['delete_local_after_days'] ?? 0);
    if ($days <= 0) return 0;
    $stmt = $pdo->prepare("SELECT * FROM external_files WHERE status = 'uploaded' AND local_deleted = 0 AND public_url IS NOT NULL
        AND created_at < (NOW() - INTERVAL $days DAY) ORDER BY id ASC LIMIT " . (int)$limit);
    $stmt->execute();
    $n = 0;
    foreach ($stmt->fetchAll() as $row) {
        $full = __DIR__ . '/../' . $row['local_path'];
        if (is_file($full)) @unlink($full);
        $pdo->prepare("UPDATE external_files SET local_deleted = 1 WHERE id = ?")->execute([$row['id']]);
        $n++;
    }
    return $n;
}

/* ===================== Remove hosting copies after publishing ===================== */

/**
 * Can hosting copies be removed at all? Only when external storage is ON and at least one active
 * account has a Public Base URL — otherwise nothing would be left to serve the image from.
 */
function ext_cleanup_possible(PDO $pdo): bool
{
    if (empty(ext_settings($pdo)['external_enabled'])) return false;
    try {
        return (bool)$pdo->query("SELECT 1 FROM storage_providers WHERE status = 'active' AND public_base_url IS NOT NULL AND public_base_url <> '' LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** One-time columns / indexes the publish cleanup needs. */
function ext_cleanup_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_extcleanup_v1';
    if (is_file($flag)) return;
    $ok = true;
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    try {
        $check->execute(['articles', 'local_images_cleaned']);
        if ((int)$check->fetchColumn() === 0) $pdo->exec("ALTER TABLE articles ADD COLUMN local_images_cleaned TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) { $ok = false; }
    $idx = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
    foreach ([['scheduled_pins', 'idx_sp_image_path', 'image_path(191)'], ['scheduled_pins', 'idx_sp_status_published', 'status, published_at']] as [$t, $name, $cols]) {
        try {
            $idx->execute([$t, $name]);
            if ((int)$idx->fetchColumn() === 0) $pdo->exec("ALTER TABLE $t ADD INDEX $name ($cols)");
        } catch (Throwable $e) { $ok = false; }
    }
    if ($ok) @file_put_contents($flag, date('c'));
}

/**
 * Removes the hosting copy of one image, keeping ONLY the external copy. If the image isn't on
 * external storage yet it is uploaded first; if that isn't possible the hosting copy is kept.
 * Returns 'deleted' | 'gone' (already removed / file missing) | 'kept'.
 */
function ext_remove_local_copy(PDO $pdo, string $rel): string
{
    $rel = ext_normalize_rel($rel);
    $cat = ext_category_for($rel);
    if (!$cat) return 'kept';
    $full = __DIR__ . '/../' . $rel;
    $row = ext_get_row($pdo, $rel);
    if ((!$row || $row['status'] !== 'uploaded') && is_file($full)) $row = ext_offload_file($pdo, $rel);
    if ($row && $row['status'] === 'uploaded' && !empty($row['public_url'])) {
        $had = is_file($full);
        if ($had) @unlink($full);
        if (!empty($row['id'])) $pdo->prepare("UPDATE external_files SET local_deleted = 1 WHERE id = ?")->execute([$row['id']]);
        return $had ? 'deleted' : 'gone';
    }
    if (!is_file($full)) {
        // Nothing on hosting and nothing external to move — remember it so it isn't checked again.
        $pdo->prepare("INSERT INTO external_files (local_path, category, status, reason, local_deleted) VALUES (?, ?, 'hosting', 'missing', 1)
            ON DUPLICATE KEY UPDATE local_deleted = 1")->execute([$rel, $cat]);
        return 'gone';
    }
    return 'kept';
}

/**
 * Pin images: removes the hosting copy N days (Admin setting, default 5) after the pin was
 * published — only when no other pending pin still uses the same image. Newest first per run.
 * $days overrides the setting (e.g. the admin's "Run now" button). Returns counts.
 */
function ext_cleanup_published_pins(PDO $pdo, int $limit = 300, int $timeBudget = 40, ?int $days = null): array
{
    $out = ['deleted' => 0, 'gone' => 0, 'kept' => 0, 'more' => false];
    $days = $days ?? (int)(ext_settings($pdo)['pin_delete_after_days'] ?? 0);
    if ($days <= 0 || !ext_cleanup_possible($pdo)) return $out;
    ext_cleanup_ensure_schema($pdo);
    $start = time();
    $stmt = $pdo->prepare("SELECT sp.image_path FROM scheduled_pins sp
        LEFT JOIN external_files ef ON ef.local_path = sp.image_path
        WHERE sp.status = 'published' AND sp.published_at IS NOT NULL AND sp.published_at < (NOW() - INTERVAL $days DAY)
          AND sp.image_path LIKE 'uploads/%'
          AND (ef.id IS NULL OR (ef.local_deleted = 0 AND NOT (ef.status = 'hosting' AND ef.updated_at > (NOW() - INTERVAL 30 MINUTE))))
          AND NOT EXISTS (SELECT 1 FROM scheduled_pins p2 WHERE p2.image_path = sp.image_path AND p2.status IN ('pending', 'processing'))
        GROUP BY sp.image_path
        LIMIT " . (int)$limit);
    $stmt->execute();
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paths as $rel) {
        if (time() - $start > $timeBudget) { $out['more'] = true; break; }
        $out[ext_remove_local_copy($pdo, (string)$rel)]++;
    }
    if (count($paths) >= $limit) $out['more'] = true;
    return $out;
}

/** Every image file an article uses (featured + in-article), from its row. */
function ext_article_image_paths(array $article): array
{
    $paths = [];
    if (!empty($article['featured_image_path'])) $paths[] = $article['featured_image_path'];
    $prog = json_decode((string)($article['image_progress_json'] ?? ''), true);
    if (is_array($prog)) {
        if (!empty($prog['featured_path'])) $paths[] = $prog['featured_path'];
        foreach ((array)($prog['inline'] ?? []) as $p) if (is_string($p) && $p !== '') $paths[] = $p;
    }
    return array_values(array_unique(array_filter($paths, fn($p) => strpos(ext_normalize_rel($p), 'uploads/') === 0)));
}

/**
 * Article images: removes the hosting copies N hours (Admin setting, default 1) after the article
 * was published. The article is marked done once every image is only on external storage.
 * $hours overrides the setting (the admin's "Run now" button). Returns counts.
 */
function ext_cleanup_published_articles(PDO $pdo, int $limit = 100, int $timeBudget = 40, ?int $hours = null): array
{
    $out = ['articles' => 0, 'deleted' => 0, 'gone' => 0, 'kept' => 0, 'more' => false];
    $hours = $hours ?? (int)(ext_settings($pdo)['article_delete_after_hours'] ?? 0);
    if ($hours <= 0 || !ext_cleanup_possible($pdo)) return $out;
    ext_cleanup_ensure_schema($pdo);
    $start = time();
    try {
        $stmt = $pdo->prepare("SELECT id, featured_image_path, image_progress_json FROM articles
            WHERE status = 'published' AND local_images_cleaned = 0 AND published_at IS NOT NULL
              AND published_at < (NOW() - INTERVAL $hours HOUR)
            ORDER BY published_at ASC LIMIT " . (int)$limit);
        $stmt->execute();
    } catch (Throwable $e) {
        return $out;
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as $a) {
        if (time() - $start > $timeBudget) { $out['more'] = true; break; }
        $allDone = true;
        foreach (ext_article_image_paths($a) as $rel) {
            $r = ext_remove_local_copy($pdo, $rel);
            $out[$r]++;
            if ($r === 'kept') $allDone = false;
        }
        if ($allDone) $pdo->prepare("UPDATE articles SET local_images_cleaned = 1 WHERE id = ?")->execute([$a['id']]);
        $out['articles']++;
    }
    if (count($rows) >= $limit) $out['more'] = true;
    return $out;
}

/**
 * Background storage work, called every minute by cron/scheduler.php and the built-in pins runner:
 * uploads new images to external storage, and every 10 minutes removes hosting copies that are due
 * (published pins / articles, and the older "remove after N days" rule).
 */
function ext_background_tick(PDO $pdo): array
{
    $msgs = [];
    try {
        $sw = ext_sweep($pdo, 25, 3, 40);
        if ($sw['checked']) $msgs[] = "External storage: {$sw['uploaded']} uploaded, {$sw['hosting']} kept on hosting.";
        $cleanFlag = __DIR__ . '/../uploads/.ext_cleanup_last';
        if (!is_file($cleanFlag) || filemtime($cleanFlag) < time() - 600) {
            @touch($cleanFlag);
            $removed = ext_cleanup_local($pdo);
            if ($removed) $msgs[] = "Removed $removed hosting copies (already on external storage).";
            $p = ext_cleanup_published_pins($pdo, 300, 30);
            if ($p['deleted']) $msgs[] = "Removed {$p['deleted']} published pin image(s) from hosting.";
            $a = ext_cleanup_published_articles($pdo, 100, 30);
            if ($a['deleted']) $msgs[] = "Removed {$a['deleted']} article image(s) from hosting.";
        }
    } catch (Throwable $e) {
        ext_log_error($pdo, null, null, 'Background storage error: ' . $e->getMessage());
    }
    return $msgs;
}

/* ===================== Database size + optimize ===================== */

/** Database size overview: total, reclaimable (free inside tables) and the biggest tables. */
function db_size_summary(PDO $pdo): array
{
    $out = ['total' => 0, 'data' => 0, 'index' => 0, 'free' => 0, 'tables' => 0, 'rows' => 0, 'top' => []];
    try {
        $rows = $pdo->query("SELECT table_name AS t, COALESCE(data_length,0) AS d, COALESCE(index_length,0) AS i,
                COALESCE(data_free,0) AS f, COALESCE(table_rows,0) AS r
            FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC")->fetchAll();
    } catch (Throwable $e) {
        return $out;
    }
    foreach ($rows as $r) {
        $out['data'] += (int)$r['d'];
        $out['index'] += (int)$r['i'];
        $out['free'] += (int)$r['f'];
        $out['rows'] += (int)$r['r'];
        $out['tables']++;
    }
    $out['total'] = $out['data'] + $out['index'];
    $out['top'] = array_slice($rows, 0, 10);
    return $out;
}

/** "Needs optimizing" when a good share of the space is reclaimable or old clutter is waiting. */
function db_needs_optimize(array $sum): bool
{
    return $sum['free'] > 50 * 1048576 || ($sum['total'] > 0 && $sum['free'] / max(1, $sum['total']) > 0.2);
}

/** Human-readable bytes. */
function fmt_bytes(float $b): string
{
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
        if ($b < 1024 || $u === 'TB') return ($u === 'B' ? (int)$b : number_format($b, 1)) . ' ' . $u;
        $b /= 1024;
    }
    return (string)$b;
}

/**
 * Cleans unwanted data and files, then optimizes every table (reclaims the free space):
 *   - old system logs (90+ days), storage error log (30+ days), expired Pinterest analytics cache (7+ days),
 *     read notifications (90+ days);
 *   - base64 page images kept inside finished Classic Wizard / website pin pages (not needed once the pins exist);
 *   - leftover lock files of batches that are finished or deleted, and old temporary files in uploads/.
 * Never touches pins, articles, users, images or settings. Returns a list of what was done.
 */
function db_optimize_run(PDO $pdo, int $timeBudget = 240): array
{
    $start = time();
    $done = [];
    $del = function (string $label, string $sql) use ($pdo, &$done) {
        try {
            $n = $pdo->exec($sql);
            if ($n) $done[] = number_format($n) . " $label removed.";
        } catch (Throwable $e) { /* table may not exist */ }
    };
    $del('old log entries (90+ days)', "DELETE FROM logs WHERE created_at < (NOW() - INTERVAL 90 DAY)");
    $del('old storage errors (30+ days)', "DELETE FROM storage_error_log WHERE created_at < (NOW() - INTERVAL 30 DAY)");
    $del('expired analytics cache rows', "DELETE FROM pa_api_cache WHERE fetched_at < (NOW() - INTERVAL 7 DAY)");
    $del('old read notifications (90+ days)', "DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 90 DAY)");

    // Base64 page images inside finished website-pin pages (Classic Wizard page scans) — only needed while making the pins.
    try {
        $st = $pdo->query("SELECT id, pin_data_json FROM website_pin_pages
            WHERE status NOT IN ('queued', 'generating_text', 'generating_images', 'ready') AND pin_data_json LIKE '%\"scanned_images\"%' LIMIT 5000");
        $up = $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ? WHERE id = ?");
        $n = 0;
        foreach ($st->fetchAll() as $r) {
            $j = json_decode((string)$r['pin_data_json'], true);
            if (!is_array($j) || !isset($j['scanned_images'])) continue;
            unset($j['scanned_images']);
            $up->execute([json_encode($j), $r['id']]);
            $n++;
        }
        if ($n) $done[] = "Page-image data cleaned from $n finished website pin page(s).";
    } catch (Throwable $e) { /* optional */ }

    // Leftover lock files and old temp files in uploads/.
    $uploads = __DIR__ . '/../uploads';
    $files = 0;
    $activeArticle = $activeWebsite = [];
    try { $activeArticle = array_flip($pdo->query("SELECT id FROM article_batches WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable $e) {}
    try { $activeWebsite = array_flip($pdo->query("SELECT id FROM website_pin_batches WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable $e) {}
    $lockFiles = array_merge((array)glob($uploads . '/.article_batch_*.lock'), (array)glob($uploads . '/.website_pin_batch_*.lock'));
    foreach ($lockFiles as $f) {
        if (!preg_match('/\.(article_batch|website_pin_batch)_(\d+)\.lock$/', $f, $m)) continue;
        $active = $m[1] === 'article_batch' ? isset($activeArticle[(int)$m[2]]) : isset($activeWebsite[(int)$m[2]]);
        if ($active) continue;
        $h = @fopen($f, 'c');
        if ($h && flock($h, LOCK_EX | LOCK_NB)) {   // not held by a running worker
            flock($h, LOCK_UN); fclose($h);
            if (@unlink($f)) $files++;
        } elseif ($h) { fclose($h); }
    }
    foreach (['tmp', 'temp'] as $d) {
        if (!is_dir("$uploads/$d")) continue;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$uploads/$d", FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getMTime() < time() - 86400 && @unlink($f->getPathname())) $files++;
        }
    }
    if ($files) $done[] = "$files leftover lock/temp file(s) removed.";

    // Reclaim the space: OPTIMIZE every table that has free space (biggest waste first).
    try {
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND data_free > 0 ORDER BY data_free DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $tables = [];
    }
    $opt = 0;
    foreach ($tables as $t) {
        if (time() - $start > $timeBudget) { $done[] = 'Time limit reached — click Run Optimize again to finish the remaining tables.'; break; }
        try {
            $q = $pdo->query('OPTIMIZE TABLE `' . str_replace('`', '', $t) . '`');
            $q->fetchAll();
            $q->closeCursor();
            $opt++;
        } catch (Throwable $e) { /* skip this table */ }
    }
    if ($opt) $done[] = "$opt table(s) optimized.";
    if (!$done) $done[] = 'Database is already clean — nothing to optimize.';
    return $done;
}

/** Numbers for the Storage Settings status card. */
function ext_status_summary(PDO $pdo): array
{
    ext_ensure_schema($pdo);
    $sum = ['uploaded' => 0, 'uploaded_bytes' => 0, 'hosting' => 0, 'no_provider' => 0, 'limit' => 0, 'error' => 0, 'local_deleted' => 0];
    try {
        foreach ($pdo->query("SELECT status, reason, COUNT(*) c, COALESCE(SUM(size_bytes),0) b, SUM(local_deleted) d FROM external_files GROUP BY status, reason")->fetchAll() as $r) {
            if ($r['status'] === 'uploaded') {
                $sum['uploaded'] += (int)$r['c'];
                $sum['uploaded_bytes'] += (int)$r['b'];
                $sum['local_deleted'] += (int)$r['d'];
            } else {
                $sum['hosting'] += (int)$r['c'];
                if (isset($sum[$r['reason']])) $sum[$r['reason']] += (int)$r['c'];
            }
        }
    } catch (Throwable $e) { /* empty */ }
    return $sum;
}
