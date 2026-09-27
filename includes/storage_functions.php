<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pricing_functions.php';

/**
 * Storage — a unified media library with quota tracking, backed by whichever cloud
 * provider the admin configures (Backblaze B2, Amazon S3, Cloudflare R2 — all
 * S3-compatible, so one signed-request client handles all three) with automatic
 * rollover across multiple provider accounts, falling back to local disk storage
 * when nothing is configured.
 *
 * Quota is plan-based (Admin → Plan Pricing → Create Plan → Cloud Storage) as of the
 * split-credit update — see get_user_storage_quota_bytes() below. STORAGE_QUOTA_BYTES
 * (1GB) is now only the fallback for a user with no plan assigned at all.
 */

const STORAGE_QUOTA_BYTES = 1073741824; // 1 GB — fallback only, see get_user_storage_quota_bytes()

/** The user's (or their team owner's) storage quota in bytes, from their current plan's
 *  Cloud Storage setting. Falls back to the 1GB default if they have no plan or the plan's
 *  storage is left at 0 (unset). */
function get_user_storage_quota_bytes(PDO $pdo, int $userId): int
{
    try {
        $plan = get_user_plan($pdo, $userId);
        if ($plan && (int)($plan['cloud_storage_mb'] ?? 0) > 0) {
            return (int)$plan['cloud_storage_mb'] * 1048576;
        }
    } catch (Throwable $e) {
        // fall through to default
    }
    return STORAGE_QUOTA_BYTES;
}

/* ===================== S3-compatible client (AWS Signature Version 4) ===================== */

/**
 * Signs and sends a PUT (upload) request to an S3-compatible endpoint using
 * AWS SigV4 — the standard signing algorithm shared by AWS S3, Cloudflare R2,
 * and Backblaze B2's S3-compatible API. Built from the documented spec, not
 * tested against a live account from this environment — verify against your
 * provider's own quickstart if uploads don't succeed on the first try.
 */
function s3_put_object(array $cfg, string $key, string $body, string $contentType): array
{
    $host = parse_url($cfg['endpoint'], PHP_URL_HOST);
    $path = '/' . rawurlencode($cfg['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    $path = str_replace('%2F', '/', $path);

    $now = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $region = $cfg['region'] ?: 'auto';
    $service = 's3';
    $payloadHash = hash('sha256', $body);

    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $now,
        'content-type' => $contentType,
    ];
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) $canonicalHeaders .= "$k:$v\n";
    $signedHeaders = implode(';', array_keys($headers));

    $canonicalRequest = "PUT\n$path\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
    $credentialScope = "$date/$region/$service/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n$now\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $signingKey = s3_signing_key($cfg['secret_key'], $date, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $authHeader = "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $url = rtrim($cfg['endpoint'], '/') . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $now,
            'Content-Type: ' . $contentType,
            'Authorization: ' . $authHeader,
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 300) {
        return ['ok' => false, 'error' => $err ?: ('Upload failed (HTTP ' . $httpCode . '): ' . mb_substr((string)$response, 0, 300))];
    }
    return ['ok' => true, 'error' => null];
}

function s3_delete_object(array $cfg, string $key): array
{
    $host = parse_url($cfg['endpoint'], PHP_URL_HOST);
    $path = '/' . rawurlencode($cfg['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    $path = str_replace('%2F', '/', $path);

    $now = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $region = $cfg['region'] ?: 'auto';
    $payloadHash = hash('sha256', '');

    $headers = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $now];
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) $canonicalHeaders .= "$k:$v\n";
    $signedHeaders = implode(';', array_keys($headers));

    $canonicalRequest = "DELETE\n$path\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
    $credentialScope = "$date/$region/s3/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n$now\n$credentialScope\n" . hash('sha256', $canonicalRequest);
    $signingKey = s3_signing_key($cfg['secret_key'], $date, $region, 's3');
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $authHeader = "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $ch = curl_init(rtrim($cfg['endpoint'], '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Host: ' . $host, 'x-amz-content-sha256: ' . $payloadHash, 'x-amz-date: ' . $now, 'Authorization: ' . $authHeader],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $response !== false && $httpCode < 300, 'error' => $httpCode >= 300 ? "HTTP $httpCode" : null];
}

function s3_signing_key(string $secretKey, string $date, string $region, string $service): string
{
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

/* ===================== Provider selection (with automatic rollover) ===================== */

/** Picks the first active provider account under its own capacity, in priority order — free tiers before paid. */
function get_active_storage_provider(PDO $pdo): ?array
{
    $stmt = $pdo->query("SELECT * FROM storage_providers WHERE status = 'active' ORDER BY (tier = 'paid'), priority ASC, id ASC");
    $providers = $stmt->fetchAll();
    foreach ($providers as $p) {
        $capacityBytes = (float)$p['capacity_gb'] * 1073741824;
        if ((int)$p['used_bytes'] < $capacityBytes) return $p;
    }
    return null;
}

function storage_provider_config(array $row): array
{
    return [
        'endpoint' => $row['endpoint'],
        'region' => $row['region'],
        'bucket' => $row['bucket'],
        'access_key' => $row['access_key'],
        'secret_key' => $row['secret_key'],
    ];
}

/* ===================== Save / delete, with quota + provider rollover ===================== */

function get_user_storage_used(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(size_bytes), 0) FROM storage_images WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Saves image bytes for a user — to the active cloud provider if one is
 * configured (with automatic rollover to the next account once one fills
 * up), or to local disk otherwise. Enforces the user's plan storage quota before saving.
 */
function storage_save_image(PDO $pdo, int $userId, string $binaryData, string $filename, string $contentType, string $source, ?string $tags = null, ?string $sourcePageUrl = null): array
{
    $size = strlen($binaryData);
    $quota = get_user_storage_quota_bytes($pdo, $userId);
    if (get_user_storage_used($pdo, $userId) + $size > $quota) {
        $quotaGb = round($quota / 1073741824, 2);
        return ['ok' => false, 'error' => "Storage limit reached ({$quotaGb}GB) — upgrade your plan for more space, or delete some images first."];
    }

    $key = 'storage/' . $userId . '/' . date('Y/m') . '/' . bin2hex(random_bytes(8)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

    // External storage (Admin → Storage Settings): tries each account with room in order; if none is
    // connected, all are full, or uploads fail, the image is kept on hosting and the reason is logged there.
    if (function_exists('ext_category_enabled') && ext_category_enabled($pdo, 'uploads')) {
        $result = ext_put_bytes($pdo, $binaryData, $key, $contentType, 'storage library: ' . $filename);
        if ($result['ok']) {
            $stmt = $pdo->prepare("INSERT INTO storage_images (user_id, source, storage_provider, provider_row_id, file_path, public_url, filename, size_bytes, tags, source_page_url)
                VALUES (?, ?, 'remote', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $source, $result['provider']['id'], $key, $result['public_url'], $filename, $size, $tags, $sourcePageUrl]);
            return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'error' => null];
        }
        // fall through to hosting
    }

    $destDir = __DIR__ . '/../uploads/storage/' . $userId . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $localName = bin2hex(random_bytes(8)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    file_put_contents($destDir . $localName, $binaryData);
    // Same permissions fix as the bulk-scheduler's own upload endpoints — on shared hosting
    // where PHP and the webserver run as different users, a file written by PHP can end up
    // unreadable by the webserver (403) unless explicitly made world-readable.
    @chmod($destDir . $localName, 0644);
    @chmod($destDir, 0755);
    $relPath = 'uploads/storage/' . $userId . '/' . $localName;

    $stmt = $pdo->prepare("INSERT INTO storage_images (user_id, source, storage_provider, file_path, filename, size_bytes, tags, source_page_url)
        VALUES (?, ?, 'local', ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $source, $relPath, $filename, $size, $tags, $sourcePageUrl]);
    return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'error' => null];
}

/** Resolves a stored image's URL, whether local or remote. */
function storage_image_url(array $row): string
{
    if ($row['storage_provider'] === 'remote' && $row['public_url']) return $row['public_url'];
    if ($row['storage_provider'] === 'remote' && !empty($row['provider_row_id'])) {
        // Stored on an account without a Public Base URL — rebuild it if the admin has added one since.
        global $pdo;
        if ($pdo instanceof PDO) {
            $s = $pdo->prepare("SELECT public_base_url FROM storage_providers WHERE id = ?");
            $s->execute([$row['provider_row_id']]);
            $base = $s->fetchColumn();
            if ($base) return rtrim($base, '/') . '/' . ltrim($row['file_path'], '/');
        }
    }
    if (function_exists('media_url')) return media_url((string)$row['file_path'], false);
    return rtrim(APP_URL, '/') . '/' . ltrim($row['file_path'], '/');
}

function storage_delete_image(PDO $pdo, array $row): bool
{
    if ($row['storage_provider'] === 'remote' && $row['provider_row_id']) {
        $stmt = $pdo->prepare("SELECT * FROM storage_providers WHERE id = ?");
        $stmt->execute([$row['provider_row_id']]);
        $provider = $stmt->fetch();
        if ($provider) {
            s3_delete_object(storage_provider_config($provider), $row['file_path']);
            $pdo->prepare("UPDATE storage_providers SET used_bytes = GREATEST(0, used_bytes - ?) WHERE id = ?")->execute([$row['size_bytes'], $provider['id']]);
        }
    } else {
        $fullPath = __DIR__ . '/../' . $row['file_path'];
        if (is_file($fullPath)) @unlink($fullPath);
        // Its external copy (if the background upload already sent it) goes too.
        try {
            $x = $pdo->prepare("SELECT * FROM external_files WHERE local_path = ? AND status = 'uploaded'");
            $x->execute([$row['file_path']]);
            if ($ext = $x->fetch()) {
                $p = $pdo->prepare("SELECT * FROM storage_providers WHERE id = ?");
                $p->execute([$ext['provider_row_id']]);
                if ($prov = $p->fetch()) {
                    s3_delete_object(storage_provider_config($prov), $ext['object_key']);
                    $pdo->prepare("UPDATE storage_providers SET used_bytes = GREATEST(0, used_bytes - ?) WHERE id = ?")->execute([$ext['size_bytes'], $prov['id']]);
                }
                $pdo->prepare("DELETE FROM external_files WHERE id = ?")->execute([$ext['id']]);
            }
        } catch (Throwable $e) { /* best effort */ }
    }
    $pdo->prepare("DELETE FROM storage_images WHERE id = ?")->execute([$row['id']]);
    return true;
}

/* ===================== Pexels stock photo search ===================== */

function pexels_search(PDO $pdo, string $query, int $page = 1, int $perPage = 10): array
{
    $stmt = $pdo->query("SELECT api_key FROM pexels_settings LIMIT 1");
    $apiKey = $stmt->fetchColumn();
    if (!$apiKey) {
        return ['ok' => false, 'results' => [], 'error' => 'Photo search is not available right now. Please try again later.'];
    }
    $url = 'https://api.pexels.com/v1/search?' . http_build_query(['query' => $query, 'per_page' => $perPage, 'page' => $page]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey], CURLOPT_TIMEOUT => 20]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $httpCode >= 300) {
        return ['ok' => false, 'results' => [], 'error' => 'Pexels request failed (HTTP ' . $httpCode . ').'];
    }
    $data = json_decode($body, true);
    $results = array_map(fn($p) => [
        'id' => $p['id'],
        'thumb' => $p['src']['medium'],
        'full' => $p['src']['large2x'] ?? $p['src']['original'],
        'photographer' => $p['photographer'] ?? '',
    ], $data['photos'] ?? []);
    return ['ok' => true, 'results' => $results, 'total_results' => $data['total_results'] ?? 0, 'error' => null];
}

/* ===================== Website image scan ===================== */

/**
 * Fetches a page and extracts every image reference it can find:
 * <img src>, srcset (picks the highest-resolution candidate), common
 * lazy-load attributes (data-src, data-lazy-src, data-original), the
 * Open Graph image, and inline CSS background-image URLs as a fallback.
 * WordPress featured images and gallery images need no special-casing —
 * they render as ordinary <img> tags in the page HTML, so the general
 * scan already picks them up.
 */
function scan_page_for_images(string $pageUrl): array
{
    $html = http_get_text($pageUrl, 20);
    if (!$html) return ['ok' => false, 'images' => [], 'error' => 'Could not fetch that page — it may be blocking automated requests, or the URL may be wrong.'];

    $urls = [];

    // og:image (order of attributes in the tag can vary, so match both property-then-content
    // and content-then-property forms).
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        $urls[] = $m[1];
    } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
        $urls[] = $m[1];
    }

    // Every <img ...> tag: pull out src, srcset, and the common lazy-load attributes together,
    // preferring the highest-resolution real source over a lazy-load placeholder.
    if (preg_match_all('/<img\b[^>]*>/i', $html, $imgTags)) {
        foreach ($imgTags[0] as $tag) {
            $candidates = [];
            if (preg_match('/\bsrcset=["\']([^"\']+)["\']/i', $tag, $m)) {
                // srcset is a comma-separated "url width" list — the last entry is conventionally the largest.
                $parts = array_map('trim', explode(',', $m[1]));
                $last = end($parts);
                $srcsetUrl = trim(explode(' ', $last)[0]);
                if ($srcsetUrl !== '') $candidates[] = $srcsetUrl;
            }
            foreach (['data-lazy-src', 'data-src', 'data-original'] as $attr) {
                if (preg_match('/\b' . $attr . '=["\']([^"\']+)["\']/i', $tag, $m)) $candidates[] = $m[1];
            }
            if (preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $m)) $candidates[] = $m[1];

            foreach ($candidates as $c) {
                if ($c !== '' && strpos($c, 'data:') !== 0) { $urls[] = $c; break; } // first usable candidate for this tag
            }
        }
    }

    // Inline CSS background-image, as a fallback for image content that isn't a plain <img> tag.
    if (preg_match_all('/background(?:-image)?\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i', $html, $m)) {
        foreach ($m[1] as $bgUrl) $urls[] = $bgUrl;
    }

    $resolved = [];
    foreach ($urls as $u) {
        $u = trim($u);
        if ($u === '' || strpos($u, 'data:') === 0) continue;
        if (strpos($u, '//') === 0) $u = 'https:' . $u;
        elseif (strpos($u, '/') === 0) {
            $parts = parse_url($pageUrl);
            $u = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $u;
        } elseif (!preg_match('#^https?://#i', $u)) {
            continue; // skip anything we can't confidently resolve to an absolute URL
        }
        $resolved[] = $u;
    }
    $resolved = array_values(array_unique($resolved));

    if (empty($resolved)) {
        return ['ok' => false, 'images' => [], 'error' => 'Fetched the page but found no images in it.'];
    }
    return ['ok' => true, 'images' => $resolved, 'error' => null];
}
