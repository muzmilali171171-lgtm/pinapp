<?php
/** Site-wide key/value settings (table site_settings). Safe before migration: falls back to defaults. */

function site_setting_get(PDO $pdo, string $key, $default = null)
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $s = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $cache[$key] = ($v === false || $v === null) ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function site_setting_set(PDO $pdo, string $key, ?string $value): void
{
    $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value]);
}

/** JSON-encoded settings group, merged over defaults. */
function site_setting_json(PDO $pdo, string $key, array $defaults): array
{
    $raw = site_setting_get($pdo, $key, '');
    $data = $raw ? json_decode($raw, true) : [];
    return array_merge($defaults, is_array($data) ? $data : []);
}
