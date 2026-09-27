<?php
require_once __DIR__ . '/functions.php';

/**
 * Page Crawler — discovers a website's pages via its XML sitemap(s) (the
 * primary, reliable method), falling back to a crawler API (Firecrawl, admin-
 * configured) when no sitemap can be found. This is the shared foundation for
 * both "Auto Website to Daily Pin" and Storage's "Website Images" scan.
 */

/* ===================== Crawl sites ===================== */

function get_or_create_crawl_site(PDO $pdo, int $userId, string $siteUrl, ?int $websiteId = null): array
{
    $siteUrl = rtrim(trim($siteUrl), '/');
    // CSV archives are excluded: a user can have several archives built from the
    // same domain, and a scan should never absorb one of them.
    try {
        $stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE user_id = ? AND site_url = ? AND source <> 'csv'");
        $stmt->execute([$userId, $siteUrl]);
    } catch (PDOException $e) {
        // Install that hasn't run migrate.php yet — no `source` column.
        $stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE user_id = ? AND site_url = ?");
        $stmt->execute([$userId, $siteUrl]);
    }
    $existing = $stmt->fetch();
    if ($existing) {
        if ($websiteId && empty($existing['website_id'])) {
            $pdo->prepare("UPDATE crawl_sites SET website_id = ? WHERE id = ?")->execute([$websiteId, $existing['id']]);
            $existing['website_id'] = $websiteId;
        }
        return $existing;
    }

    $siteName = parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl;
    $pdo->prepare("INSERT INTO crawl_sites (user_id, website_id, site_url, site_name) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $websiteId, $siteUrl, $siteName]);
    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function get_user_crawl_sites(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT cs.*,
        (SELECT COUNT(*) FROM crawl_pages cp WHERE cp.crawl_site_id = cs.id) AS total_pages,
        (SELECT COUNT(*) FROM crawl_pages cp WHERE cp.crawl_site_id = cs.id AND cp.active = 1) AS active_pages
        FROM crawl_sites cs WHERE cs.user_id = ? ORDER BY cs.created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/* ===================== Sitemap discovery + parsing ===================== */

/** Tries robots.txt and common sitemap paths to find a site's sitemap(s). Returns a list of sitemap URLs. */
function discover_sitemap_urls(string $siteUrl): array
{
    $siteUrl = rtrim($siteUrl, '/');
    $found = [];

    // 1) robots.txt usually points straight at the real sitemap(s).
    $robots = http_get_text($siteUrl . '/robots.txt');
    if ($robots) {
        if (preg_match_all('/^Sitemap:\s*(\S+)/mi', $robots, $m)) {
            foreach ($m[1] as $url) $found[] = trim($url);
        }
    }

    // 2) Common conventional paths, if robots.txt didn't have anything.
    if (empty($found)) {
        $candidates = ['/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml'];
        foreach ($candidates as $path) {
            $ch = curl_init($siteUrl . $path);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                $found[] = $siteUrl . $path;
                break;
            }
        }
    }

    return array_values(array_unique($found));
}

/**
 * Fetches and parses one sitemap XML file. If it's a sitemap INDEX (points at
 * other sitemaps), returns ['type'=>'index','sitemaps'=>[...]]. If it's a
 * regular urlset, returns ['type'=>'urlset','pages'=>[['url','lastmod'],...]].
 */
function fetch_and_parse_sitemap(string $sitemapUrl): array
{
    $xml = http_get_text($sitemapUrl);
    if (!$xml) {
        return ['ok' => false, 'type' => null, 'error' => 'Could not fetch the sitemap.'];
    }
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc) {
        return ['ok' => false, 'type' => null, 'error' => 'Could not parse the sitemap XML.'];
    }

    $rootName = strtolower($doc->getName());
    if ($rootName === 'sitemapindex') {
        $sitemaps = [];
        foreach ($doc->sitemap as $s) {
            $loc = trim((string)$s->loc);
            if ($loc !== '') $sitemaps[] = $loc;
        }
        return ['ok' => true, 'type' => 'index', 'sitemaps' => $sitemaps, 'error' => null];
    }

    $pages = [];
    foreach ($doc->url as $u) {
        $loc = trim((string)$u->loc);
        if ($loc === '') continue;
        $lastmod = trim((string)$u->lastmod);
        $pages[] = ['url' => $loc, 'lastmod' => $lastmod !== '' ? substr($lastmod, 0, 10) : null];
    }
    return ['ok' => true, 'type' => 'urlset', 'pages' => $pages, 'error' => null];
}

/** Infers rough category/tag labels from a URL's path segments (e.g. /category/hairstyles/post-slug/ -> "category, hairstyles"). */
function infer_category_tags_from_url(string $url): string
{
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    if ($path === '') return '';
    $segments = explode('/', $path);
    array_pop($segments); // drop the final slug — that's the page itself, not a category
    $segments = array_filter($segments, fn($s) => $s !== '' && !preg_match('/^\d+$/', $s));
    $segments = array_map(fn($s) => str_replace('-', ' ', $s), $segments);
    return implode(', ', array_slice($segments, 0, 3));
}

/**
 * Full scan: discovers sitemaps, recursively parses any sitemap index, and
 * stores every page found (skipping ones already stored) with an inferred
 * category/tag label. Returns a summary. This is the primary discovery path;
 * crawl_site_via_firecrawl() is the fallback when no sitemap exists at all.
 */
function scan_website_sitemaps(PDO $pdo, int $crawlSiteId, string $siteUrl): array
{
    $sitemapUrls = discover_sitemap_urls($siteUrl);
    if (empty($sitemapUrls)) {
        return ['ok' => false, 'sitemaps_found' => 0, 'pages_added' => 0, 'error' => 'No sitemap found.'];
    }

    $pagesAdded = 0;
    $sitemapsStored = 0;
    $queue = $sitemapUrls;
    $visited = [];
    $guard = 0;

    while (!empty($queue) && $guard < 40) {
        $guard++;
        $sitemapUrl = array_shift($queue);
        if (in_array($sitemapUrl, $visited, true)) continue;
        $visited[] = $sitemapUrl;

        $parsed = fetch_and_parse_sitemap($sitemapUrl);
        if (!$parsed['ok']) continue;

        if ($parsed['type'] === 'index') {
            foreach ($parsed['sitemaps'] as $child) $queue[] = $child;
            continue;
        }

        $pageCount = count($parsed['pages']);
        $pdo->prepare("INSERT INTO crawl_sitemaps (crawl_site_id, sitemap_url, page_count) VALUES (?, ?, ?)")
            ->execute([$crawlSiteId, $sitemapUrl, $pageCount]);
        $sitemapId = (int)$pdo->lastInsertId();
        $sitemapsStored++;

        $insertStmt = $pdo->prepare("INSERT IGNORE INTO crawl_pages (crawl_site_id, sitemap_id, url, url_hash, source, last_modified, category_tags)
            VALUES (?, ?, ?, ?, 'sitemap', ?, ?)");
        foreach ($parsed['pages'] as $p) {
            $hash = md5($p['url']);
            $inserted = $insertStmt->execute([$crawlSiteId, $sitemapId, $p['url'], $hash, $p['lastmod'], infer_category_tags_from_url($p['url'])]);
            if ($inserted && $insertStmt->rowCount() > 0) $pagesAdded++;
        }
    }

    $pdo->prepare("UPDATE crawl_sites SET last_scanned_at = NOW() WHERE id = ?")->execute([$crawlSiteId]);

    return ['ok' => $sitemapsStored > 0, 'sitemaps_found' => $sitemapsStored, 'pages_added' => $pagesAdded, 'error' => $sitemapsStored > 0 ? null : 'No readable sitemap found.'];
}

/**
 * Fallback for sites with no sitemap: crawls via Firecrawl's /v1/map endpoint
 * (admin-configured API key under admin → Page Crawler). Firecrawl's API may
 * change — this is built from its documented shape but hasn't been tested
 * against a live account, so double-check against current Firecrawl docs if
 * it doesn't behave as expected.
 */
function crawl_site_via_firecrawl(PDO $pdo, int $crawlSiteId, string $siteUrl): array
{
    $stmt = $pdo->prepare("SELECT api_key FROM crawler_providers WHERE provider = 'firecrawl'");
    $stmt->execute();
    $apiKey = $stmt->fetchColumn();
    if (!$apiKey) {
        return ['ok' => false, 'pages_added' => 0, 'error' => 'This site has no sitemap and could not be crawled right now. Please try again later.'];
    }

    $ch = curl_init('https://api.firecrawl.dev/v1/map');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['url' => $siteUrl]),
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode >= 300) {
        return ['ok' => false, 'pages_added' => 0, 'error' => 'Crawler request failed (HTTP ' . $httpCode . ').'];
    }
    $data = json_decode($body, true);
    $links = $data['links'] ?? $data['data'] ?? [];
    if (empty($links)) {
        return ['ok' => false, 'pages_added' => 0, 'error' => 'The crawler found no pages.'];
    }

    $pagesAdded = 0;
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO crawl_pages (crawl_site_id, url, url_hash, source, category_tags)
        VALUES (?, ?, ?, 'crawl', ?)");
    foreach ($links as $link) {
        $url = is_array($link) ? ($link['url'] ?? '') : $link;
        if (!$url) continue;
        $inserted = $insertStmt->execute([$crawlSiteId, $url, md5($url), infer_category_tags_from_url($url)]);
        if ($inserted && $insertStmt->rowCount() > 0) $pagesAdded++;
    }

    $pdo->prepare("UPDATE crawl_sites SET last_scanned_at = NOW() WHERE id = ?")->execute([$crawlSiteId]);
    return ['ok' => $pagesAdded > 0, 'pages_added' => $pagesAdded, 'error' => $pagesAdded > 0 ? null : 'No new pages found.'];
}

/** Orchestrates a full scan: sitemap first, crawler fallback if no sitemap exists at all. */
function scan_website_for_pages(PDO $pdo, int $userId, string $siteUrl, ?int $websiteId = null): array
{
    $site = get_or_create_crawl_site($pdo, $userId, $siteUrl, $websiteId);
    $result = scan_website_sitemaps($pdo, (int)$site['id'], $site['site_url']);
    if (!$result['ok']) {
        $fallback = crawl_site_via_firecrawl($pdo, (int)$site['id'], $site['site_url']);
        if ($fallback['ok']) {
            $result = ['ok' => true, 'sitemaps_found' => 0, 'pages_added' => $fallback['pages_added'], 'error' => null, 'via' => 'crawler'];
        } else {
            $result['error'] = $result['error'] . ' ' . $fallback['error'];
        }
    }
    $result['crawl_site_id'] = $site['id'];
    return $result;
}

/* ===================== URL CSV archives ===================== */

/**
 * Parse a CSV of URLs into normalised rows.
 *
 * Only "url" is required. Recognised (case-insensitive) headers:
 *   url, title, description, alt, keywords, image_url, category, priority
 * A headerless file is accepted too — then the first column is treated as the URL.
 *
 * Returns ['ok' => bool, 'rows' => [...], 'skipped' => int, 'error' => ?string].
 */
function parse_url_csv(string $csvText): array
{
    $csvText = preg_replace('/^\xEF\xBB\xBF/', '', $csvText); // strip UTF-8 BOM
    $csvText = str_replace(["\r\n", "\r"], "\n", $csvText);
    if (trim($csvText) === '') {
        return ['ok' => false, 'rows' => [], 'skipped' => 0, 'error' => 'The CSV file is empty.'];
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csvText);
    rewind($handle);

    $known = ['url', 'title', 'description', 'alt', 'keywords', 'image_url', 'category', 'priority'];
    $headers = null;
    $rows = [];
    $skipped = 0;

    while (($cols = fgetcsv($handle, 0, ',')) !== false) {
        if ($cols === [null] || $cols === false) continue;
        $cols = array_map(static fn($c) => trim((string)$c), $cols);
        if (!array_filter($cols, static fn($c) => $c !== '')) continue;

        // First non-empty line: treat as a header row if it names any known column.
        if ($headers === null) {
            $lower = array_map(static fn($c) => strtolower(str_replace([' ', '-'], '_', $c)), $cols);
            if (array_intersect($lower, $known)) {
                $headers = $lower;
                continue;
            }
            $headers = ['url']; // headerless file — column 1 is the URL
        }

        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = $cols[$i] ?? '';
        }

        $url = trim($row['url'] ?? '');
        if ($url === '') { $skipped++; continue; }
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
        if (!filter_var($url, FILTER_VALIDATE_URL)) { $skipped++; continue; }

        $priority = strtolower(trim($row['priority'] ?? ''));
        $rows[] = [
            'url' => $url,
            'title' => mb_substr(trim($row['title'] ?? ''), 0, 255),
            'description' => mb_substr(trim($row['description'] ?? ''), 0, 500),
            'alt' => mb_substr(trim($row['alt'] ?? ''), 0, 500),
            'keywords' => mb_substr(trim($row['keywords'] ?? ''), 0, 500),
            'image_url' => mb_substr(trim($row['image_url'] ?? ''), 0, 700),
            'category' => mb_substr(trim($row['category'] ?? ''), 0, 500),
            'priority' => in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal',
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        return ['ok' => false, 'rows' => [], 'skipped' => $skipped,
                'error' => 'No valid URLs found. Make sure the file has a "url" column.'];
    }
    return ['ok' => true, 'rows' => $rows, 'skipped' => $skipped, 'error' => null];
}

/**
 * Import parsed CSV rows into a crawl site ("archive").
 *
 * $existingSiteId = null creates a brand-new archive named $archiveName; pass an
 * id to append into an archive the user already has. Duplicate URLs inside the
 * same archive are updated rather than inserted twice.
 */
function import_urls_from_csv(PDO $pdo, int $userId, array $rows, string $archiveName, ?int $existingSiteId = null, bool $activate = true): array
{
    $archiveName = trim($archiveName);

    if ($existingSiteId) {
        $stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE id = ? AND user_id = ?");
        $stmt->execute([$existingSiteId, $userId]);
        $site = $stmt->fetch();
        if (!$site) {
            return ['ok' => false, 'error' => 'That archive was not found.'];
        }
    } else {
        // Derive a site_url from the first row's host so downstream code (which
        // uses it for the "website text" on images) still has something sensible.
        $firstHost = parse_url($rows[0]['url'], PHP_URL_SCHEME) . '://' . parse_url($rows[0]['url'], PHP_URL_HOST);
        if ($archiveName === '') {
            $archiveName = (parse_url($rows[0]['url'], PHP_URL_HOST) ?: 'Imported URLs') . ' — CSV ' . date('d M Y');
        }
        try {
            $pdo->prepare("INSERT INTO crawl_sites (user_id, site_url, site_name, source) VALUES (?, ?, ?, 'csv')")
                ->execute([$userId, $firstHost, mb_substr($archiveName, 0, 255)]);
        } catch (PDOException $e) {
            // Older installs that haven't run migrate.php yet have no `source` column.
            $pdo->prepare("INSERT INTO crawl_sites (user_id, site_url, site_name) VALUES (?, ?, ?)")
                ->execute([$userId, $firstHost, mb_substr($archiveName, 0, 255)]);
        }
        $newId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM crawl_sites WHERE id = ?");
        $stmt->execute([$newId]);
        $site = $stmt->fetch();
    }

    $insert = $pdo->prepare("INSERT INTO crawl_pages
        (crawl_site_id, url, url_hash, source, item_type, image_url, category_tags, keywords, meta_title, meta_description, priority, active)
        VALUES (?, ?, ?, 'csv', 'page', ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            image_url = COALESCE(NULLIF(VALUES(image_url), ''), image_url),
            category_tags = COALESCE(NULLIF(VALUES(category_tags), ''), category_tags),
            keywords = COALESCE(NULLIF(VALUES(keywords), ''), keywords),
            meta_title = COALESCE(NULLIF(VALUES(meta_title), ''), meta_title),
            meta_description = COALESCE(NULLIF(VALUES(meta_description), ''), meta_description),
            priority = VALUES(priority),
            active = VALUES(active)");

    $added = 0;
    $updated = 0;
    foreach ($rows as $r) {
        // "alt" has no column of its own — keep it with the description so the
        // schedule builder can still reuse it, without changing the table shape.
        $metaDescription = $r['description'] !== '' ? $r['description'] : $r['alt'];
        $category = $r['category'] !== '' ? $r['category'] : infer_category_tags_from_url($r['url']);

        $insert->execute([
            (int)$site['id'],
            $r['url'],
            md5($r['url']),
            $r['image_url'] ?: null,
            $category,
            $r['keywords'] ?: null,
            $r['title'] ?: null,
            $metaDescription ?: null,
            $r['priority'],
            $activate ? 1 : 0,
        ]);
        // rowCount() is 1 for a fresh insert and 2 for an updated duplicate.
        if ($insert->rowCount() === 1) $added++; else $updated++;
    }

    $pdo->prepare("UPDATE crawl_sites SET last_scanned_at = NOW() WHERE id = ?")->execute([(int)$site['id']]);

    return [
        'ok' => true,
        'crawl_site_id' => (int)$site['id'],
        'site_name' => $site['site_name'],
        'pages_added' => $added,
        'pages_updated' => $updated,
        'error' => null,
    ];
}
