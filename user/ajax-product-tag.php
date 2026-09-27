<?php
/**
 * Bulk Pin Scheduler → Tag products side panel.
 *   action=search&q=…   products/pages from the user's own scanned websites & Shopify stores
 *   action=preview&url=  title + image of a product link (for "Use a link")
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
$userId = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? '');

if ($action === 'search') {
    $q = trim((string)($_POST['q'] ?? ''));
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $params = [$userId];
    $where = "cs.user_id = ?";
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 100)) . '%';
        $where .= " AND (cp.meta_title LIKE ? OR cp.url LIKE ? OR cp.keywords LIKE ?)";
        array_push($params, $like, $like, $like);
    }
    $st = $pdo->prepare("SELECT cp.url, cp.meta_title, cp.image_url, cp.item_type FROM crawl_pages cp
        JOIN crawl_sites cs ON cs.id = cp.crawl_site_id
        WHERE $where
        ORDER BY (cp.item_type = 'product') DESC, (cp.image_url IS NOT NULL AND cp.image_url <> '') DESC, cp.id DESC
        LIMIT 25 OFFSET $offset");
    $st->execute($params);
    $items = array_map(fn($r) => [
        'url' => $r['url'],
        'title' => $r['meta_title'] ?: preg_replace('#^https?://(www\.)?#', '', $r['url']),
        'image' => $r['image_url'] ?: '',
        'type' => $r['item_type'],
    ], $st->fetchAll());
    echo json_encode(['ok' => true, 'items' => $items, 'more' => count($items) === 25]);
    exit;
}

if ($action === 'preview') {
    $url = trim((string)($_POST['url'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !platform_url_is_safe($url)) {
        echo json_encode(['ok' => false, 'error' => 'Enter a full product link, e.g. https://shop.com/product']);
        exit;
    }
    $out = ['ok' => true, 'url' => $url, 'title' => '', 'image' => ''];
    // Fetch the page ourselves, following at most 4 redirects — every hop must be a public address,
    // and curl is pinned to the IP that was checked (no DNS tricks to reach internal hosts).
    $html = '';
    $cur = $url;
    for ($hop = 0; $hop < 5; $hop++) {
        $host = parse_url($cur, PHP_URL_HOST);
        $port = parse_url($cur, PHP_URL_PORT) ?: (strtolower((string)parse_url($cur, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname((string)$host);
        if (!platform_url_is_safe($cur) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) break;
        $html = '';
        $ch = curl_init($cur);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutomatedPin/1.0; +' . rtrim(APP_URL, '/') . ')',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$html) { $html .= $chunk; return strlen($html) > 1500000 ? 0 : strlen($chunk); },
        ]);
        curl_exec($ch);
        $next = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 300 && $code < 400 && $next !== '') { $cur = $next; continue; }
        break;
    }
    if ($html !== '') {
        $meta = function (array $names) use ($html) {
            foreach ($names as $n) {
                if (preg_match('/<meta[^>]+(?:property|name)=["\']' . preg_quote($n, '/') . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)
                    || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote($n, '/') . '["\']/i', $html, $m)) {
                    return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                }
            }
            return '';
        };
        $out['title'] = $meta(['og:title', 'twitter:title']);
        if ($out['title'] === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) $out['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        $img = $meta(['og:image:secure_url', 'og:image', 'twitter:image']);
        if ($img !== '') {
            if (strpos($img, '//') === 0) $img = 'https:' . $img;
            elseif (!preg_match('#^https?://#i', $img)) $img = preg_replace('#^(https?://[^/]+).*$#', '$1', $url) . '/' . ltrim($img, '/');
            if (filter_var($img, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $img)) $out['image'] = $img;
        }
        $out['title'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($out['title']))), 0, 200);
    }
    if ($out['title'] === '') $out['title'] = preg_replace('#^https?://(www\.)?#', '', $url);
    echo json_encode($out);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
