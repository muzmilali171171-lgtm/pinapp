<?php
$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    die('App is not installed yet. Please open <a href="/install.php">install.php</a> in your browser first.');
}

require_once $configFile;

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
