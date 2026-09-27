<?php
/**
 * Downloads the admin's Models (AI provider API keys) and Cloudflare Worker
 * accounts as a JSON file, so setting up a new install of this app doesn't
 * mean re-typing every key and worker URL by hand — export here, then use
 * the "Import Settings" upload on the Models page on the new install.
 * (Deliberately excludes per-account daily usage — that's a daily counter,
 * not a setting, and wouldn't make sense carried over to a new install.)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$providers = $pdo->query("SELECT provider, model_type, api_key, default_model FROM ai_providers")->fetchAll();
$cloudflareAccounts = $pdo->query("SELECT worker_url, api_key, daily_limit FROM cloudflare_accounts")->fetchAll();

$export = [
    'exported_at' => date('c'),
    'app' => 'videoconvertly-pinterest-scheduler',
    'export_version' => 1,
    'ai_providers' => $providers,
    'cloudflare_accounts' => $cloudflareAccounts,
];

log_event($pdo, 'system', 'Admin exported Models/Cloudflare settings');

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="pinterest-scheduler-models-' . date('Y-m-d') . '.json"');
echo json_encode($export, JSON_PRETTY_PRINT);
