<?php
/**
 * Talks to the "PinScheduler Publisher" companion WordPress plugin, which
 * exposes a small shared-secret-authenticated REST API on the user's own
 * WordPress site:
 *
 *   GET  /wp-json/pinscheduler/v1/ping     -> site name, categories, tags
 *   POST /wp-json/pinscheduler/v1/publish  -> creates a post (+ featured image)
 */

function website_http_request(string $method, string $url, string $siteKey, ?array $body = null): array
{
    $ch = curl_init($url);
    $headers = ['X-PinScheduler-Key: ' . $siteKey, 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'data' => null, 'error' => $curlError];
    }
    $decoded = json_decode($response, true);
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'data' => $decoded, 'error' => $httpCode >= 300 ? $response : null];
}

/** Verify the connection and pull the site's categories/tags. */
function website_ping(string $siteUrl, string $siteKey): array
{
    $url = rtrim($siteUrl, '/') . '/wp-json/pinscheduler/v1/ping';
    return website_http_request('GET', $url, $siteKey);
}

/**
 * Publish a post to the site. $payload keys: title, content (HTML), category,
 * tags (comma-separated string), status ('publish' or 'draft'), and optionally
 * featured_image_base64.
 */
function website_publish_post(string $siteUrl, string $siteKey, array $payload): array
{
    $url = rtrim($siteUrl, '/') . '/wp-json/pinscheduler/v1/publish';
    return website_http_request('POST', $url, $siteKey, $payload);
}

// Shopify / Wix / Custom webhook support (loads this file back safely via require_once).
require_once __DIR__ . '/platform_functions.php';
