<?php
/**
 * AJAX backend for the Bulk Pin Scheduler's CSV import — downloads a remote
 * imageUrl (from a CSV row) and saves it locally under uploads/pins/, the
 * same way a directly-uploaded image is stored, since Pinterest can only
 * pull images from a URL on our own domain.
 */
@set_time_limit(60);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$url = trim($_POST['image_url'] ?? '');
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid image URL.']);
    exit;
}

// Many sites (blogs, stock-photo hosts, even Pinterest's own CDN) reject a plain server-to-server
// fetch with a 403 unless it looks like it came from a real browser on that same site — a bare
// User-Agent with no Referer/Accept is exactly what triggers that hotlink protection. Sending a
// same-origin Referer and a normal browser Accept header fixes the vast majority of those cases.
$originParts = parse_url($url);
$referer = isset($originParts['scheme'], $originParts['host'])
    ? $originParts['scheme'] . '://' . $originParts['host'] . '/'
    : $url;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        'Referer: ' . $referer,
    ],
]);
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($data === false) {
    echo json_encode(['ok' => false, 'error' => 'Could not reach that URL' . ($curlError ? " ($curlError)" : '') . '.']);
    exit;
}
if ($httpCode >= 400) {
    echo json_encode(['ok' => false, 'error' => "That URL returned an error (HTTP $httpCode) — the site may be blocking automated downloads or the link may be dead."]);
    exit;
}
if ($data === '') {
    echo json_encode(['ok' => false, 'error' => 'That URL returned an empty response.']);
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'bps_');
file_put_contents($tmpFile, $data);

// Prefer sniffing the actual file bytes (works even if the server's fileinfo extension is
// missing/misconfigured); fall back to the Content-Type response header if that's inconclusive.
$mime = @mime_content_type($tmpFile);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!isset($allowed[$mime])) {
    $sig = substr($data, 0, 12);
    if (substr($sig, 0, 3) === "\xFF\xD8\xFF") { $mime = 'image/jpeg'; }
    elseif (substr($sig, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") { $mime = 'image/png'; }
    elseif (substr($sig, 0, 6) === 'GIF87a' || substr($sig, 0, 6) === 'GIF89a') { $mime = 'image/gif'; }
    elseif (substr($sig, 0, 4) === 'RIFF' && substr($sig, 8, 4) === 'WEBP') { $mime = 'image/webp'; }
    elseif ($contentType && isset($allowed[strtolower(trim(explode(';', $contentType)[0]))])) {
        $mime = strtolower(trim(explode(';', $contentType)[0]));
    }
}

if (!isset($allowed[$mime])) {
    @unlink($tmpFile);
    $got = $mime ?: ($contentType ?: 'unknown type');
    echo json_encode(['ok' => false, 'error' => "URL did not return a supported image (JPG/PNG/WEBP/GIF) — got $got."]);
    exit;
}

$ext = $allowed[$mime];
$filename = 'pin_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destDir = __DIR__ . '/../uploads/pins/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

if (!rename($tmpFile, $destDir . $filename)) {
    @unlink($tmpFile);
    echo json_encode(['ok' => false, 'error' => 'Failed to save the downloaded image.']);
    exit;
}
// Same fix as ajax-bulk-upload: force world-readable so the webserver (which may run
// as a different user than PHP on shared hosting) can actually serve the saved file instead
// of returning a 403 for it.
@chmod($destDir . $filename, 0644);
@chmod($destDir, 0755);

echo json_encode([
    'ok' => true,
    'path' => 'uploads/pins/' . $filename,
    'filename' => basename(parse_url($url, PHP_URL_PATH) ?: $filename),
]);
