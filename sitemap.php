<?php
/**
 * XML sitemaps (Admin → SEO Setting lists them):
 *   /sitemap.xml             sitemap index
 *   /sitemap-pages.xml       home, pricing, about, contact, legal, blog, tutorials, hubs
 *   /sitemap-use-cases.xml   every use case page
 *   /sitemap-free-tools.xml  every free tool
 *   /sitemap-blog.xml        blog categories + published posts
 * .htaccess routes these URLs here (?type=pages|use-cases|free-tools|blog).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/sitemap_functions.php';

$type = preg_replace('/[^a-z-]/', '', (string)($_GET['type'] ?? ''));
$s = get_seo_settings($pdo);

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

// Indexing switched off in Admin → SEO Setting: publish an empty sitemap.
if (empty($s['robots_index'])) {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
    exit;
}

$sections = sitemap_sections();
if ($type === '' || !isset($sections[$type])) {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($sections as $key => $label) {
        $urls = sitemap_urls($pdo, $key);
        $last = $urls ? max(array_map(fn($u) => $u['lastmod'] ?? 0, $urls)) : time();
        echo "  <sitemap><loc>" . htmlspecialchars(sitemap_file_url($key), ENT_XML1) . "</loc><lastmod>" . gmdate('Y-m-d\TH:i:s\Z', $last ?: time()) . "</lastmod></sitemap>\n";
    }
    echo "</sitemapindex>\n";
    exit;
}

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach (sitemap_urls($pdo, $type) as $u) {
    echo "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod'])) echo "    <lastmod>" . gmdate('Y-m-d\TH:i:s\Z', $u['lastmod']) . "</lastmod>\n";
    if (!empty($u['image'])) echo "    <image:image><image:loc>" . htmlspecialchars($u['image'], ENT_XML1) . "</image:loc></image:image>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
