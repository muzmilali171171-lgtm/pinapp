<?php
/**
 * Admin login security: custom login URL (slug) and authenticator-app two-factor codes (TOTP,
 * RFC 6238 — works with Google Authenticator, Microsoft Authenticator, Authy, 1Password…).
 */
require_once __DIR__ . '/site_settings.php';

/* ---------- Custom admin login URL ---------- */

/** '' = default /admin/login; otherwise a single path segment like "team-access". */
function admin_login_slug(PDO $pdo): string
{
    return (string)site_setting_get($pdo, 'admin_login_slug', '');
}

function admin_login_url(PDO $pdo): string
{
    $slug = admin_login_slug($pdo);
    return rtrim(APP_URL, '/') . '/' . ($slug !== '' ? $slug : 'admin/login');
}

function admin_login_slug_matches(PDO $pdo, string $requested): bool
{
    $slug = admin_login_slug($pdo);
    return $slug !== '' && hash_equals($slug, strtolower(trim($requested, '/')));
}

/** Returns an error message, or null when the slug can be used. */
function admin_login_slug_problem(PDO $pdo, string $slug): ?string
{
    if ($slug === '') return null;
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{2,38})[a-z0-9]$/', $slug)) {
        return 'Use 4–40 characters: lowercase letters, numbers and dashes (no slashes).';
    }
    $root = dirname(__DIR__);
    if (file_exists("$root/$slug.php") || is_dir("$root/$slug")) return 'That address is already used by a page on your site.';
    $reserved = ['admin', 'user', 'auth', 'blog', 'pricing', 'login', 'register', 'signup', 'dashboard', 'wp-admin', 'administrator'];
    if (in_array($slug, $reserved, true)) return 'That address is too easy to guess — choose something unique.';
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM blog_categories WHERE slug = ?");
        $s->execute([$slug]);
        if ((int)$s->fetchColumn() > 0) return 'A blog category already uses that address.';
    } catch (Throwable $e) { /* blog not installed */ }
    return null;
}

/* ---------- TOTP two-factor ---------- */

function totp_base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function totp_base32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $v = strpos($alpha, $c);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_new_secret(): string
{
    return totp_base32_encode(random_bytes(20));
}

function totp_code(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, totp_base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $num = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16) | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code and one step either side (clock drift). */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $now = time();
    foreach ([-30, 0, 30] as $d) {
        if (hash_equals(totp_code($secret, $now + $d), $code)) return true;
    }
    return false;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = defined('SITE_BRAND') ? SITE_BRAND : 'Admin';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/* ---------- Login throttling (per browser session + IP) ---------- */

function admin_login_blocked(): int
{
    $f = $_SESSION['admin_login_fail'] ?? ['n' => 0, 't' => 0];
    if ($f['n'] >= 5 && time() - $f['t'] < 900) return 900 - (time() - $f['t']);
    return 0;
}

function admin_login_failed(): void
{
    $f = $_SESSION['admin_login_fail'] ?? ['n' => 0, 't' => 0];
    if (time() - $f['t'] > 900) $f = ['n' => 0, 't' => time()];
    $f['n']++;
    $f['t'] = time();
    $_SESSION['admin_login_fail'] = $f;
}
