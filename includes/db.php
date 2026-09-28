<?php
$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    die('App is not installed yet. Please open <a href="/install.php">install.php</a> in your browser first.');
}

/** True when the visitor reached us over HTTPS (also behind Cloudflare / a load balancer / LiteSpeed proxy). */
function request_is_https(): bool
{
    if (PHP_SAPI === 'cli') return false;
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return true;
    if (stripos((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"') !== false) return true;
    return false;
}
if (request_is_https()) $_SERVER['HTTPS'] = 'on';

require_once $configFile;

// SSL: the site address in config.php may still be http:// (typed that way at install). When the site is
// opened over HTTPS on that same domain, every link / image / favicon must be https:// too, otherwise the
// browser blocks them ("Mixed Content") and shows "Not secure".
if (PHP_SAPI !== 'cli' && defined('APP_URL')) {
    $__appHost = strtolower((string)parse_url(APP_URL, PHP_URL_HOST));
    $__reqHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $__sameSite = $__appHost !== '' && preg_replace('/^www\./', '', $__appHost) === preg_replace('/^www\./', '', $__reqHost);
    if ($__sameSite && request_is_https()) {
        // Browsers load any leftover http:// resource of this page over https instead of blocking it.
        header('Content-Security-Policy: upgrade-insecure-requests');
        header('Strict-Transport-Security: max-age=31536000');
        if (stripos(APP_URL, 'http://') === 0) {
            // Fix config.php once so APP_URL is https:// from now on…
            $__cfg = @file_get_contents($configFile);
            if (is_string($__cfg) && is_writable($configFile)) {
                $__new = preg_replace("~(define\(\s*'APP_URL'\s*,\s*')http://~i", '$1https://', $__cfg, 1, $__n);
                if ($__n) @file_put_contents($configFile, $__new, LOCK_EX);
            }
            // …and for this request, rewrite http://<this domain> to https:// in the page output.
            ob_start(function ($buf) use ($__appHost) {
                $ct = '';
                foreach (headers_list() as $h) if (stripos($h, 'Content-Type:') === 0) $ct = strtolower($h);
                if ($ct !== '' && !preg_match('~text/|json|xml|javascript~', $ct)) return $buf;
                $h = preg_quote(preg_replace('/^www\./', '', $__appHost), '~');
                return preg_replace(['~http://((?:www\.)?' . $h . ')~i', '~http:\\/\\/((?:www\.)?' . $h . ')~i'], ['https://$1', 'https:\/\/$1'], $buf);
            });
        }
    }
    unset($__appHost, $__reqHost, $__sameSite, $__cfg, $__new, $__n);
}

// Brand shown everywhere on the site. The old name ("Web To Pin" / WebToPin) is replaced by AutomatedPin;
// any other APP_NAME set in config.php is used as is.
if (!defined('SITE_BRAND')) {
    define('SITE_BRAND', (defined('APP_NAME') && trim(APP_NAME) !== '' && !preg_match('/web\s*to\s*pin|webtopin/i', APP_NAME)) ? APP_NAME : 'AutomatedPin');
}

// Scheduling uses PHP's time zone (config.php). If config.php doesn't set one, use Pakistan time.
if (!ini_get('date.timezone') && date_default_timezone_get() === 'UTC' && !defined('APP_TIMEZONE_UTC')) {
    date_default_timezone_set('Asia/Karachi');
}

function create_pdo_connection(): PDO
{
    return new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // MySQL closes idle connections after its own wait_timeout (often just
            // 60s on shared hosting). Long AI/image-generation requests can easily
            // outlast that, so ask MySQL to keep this connection open longer.
            // Also run MySQL in the same time zone as PHP, so NOW() / CURRENT_TIMESTAMP and PHP's
            // date() agree (a VPS MySQL usually runs in UTC while the app schedules in Asia/Karachi).
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=600, interactive_timeout=600, time_zone='" . date('P') . "'",
        ]
    );
}

/**
 * Call this right before a DB write that follows a long-running operation
 * (AI calls, image downloads, etc.) — reconnects transparently if MySQL has
 * dropped the idle connection in the meantime ("MySQL server has gone away").
 */
function ensure_db_connection(PDO &$pdo): void
{
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        $pdo = create_pdo_connection();
    }
}

try {
    $pdo = create_pdo_connection();
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
