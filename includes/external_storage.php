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
