<?php
/**
 * URLs listed in the XML sitemaps (sitemap.php) — only public, indexable pages, each with its
 * canonical URL. Used by sitemap.php and Admin → SEO Setting.
 */

/** Sitemap sections: key => label. */
function sitemap_sections(): array
{
    return ['pages' => 'Main pages', 'use-cases' => 'Use cases', 'free-tools' => 'Free tools', 'blog' => 'Blog'];
}

function sitemap_file_url(string $key): string
{
    return rtrim(APP_URL, '/') . '/sitemap-' . $key . '.xml';
}

/** [['loc' => absolute URL, 'lastmod' => unix time, 'image' => optional absolute URL], …] */
function sitemap_urls(PDO $pdo, string $key): array
{
    $base = rtrim(APP_URL, '/');
    $root = realpath(__DIR__ . '/..');
    $mtime = fn(string $rel) => @filemtime($root . '/' . $rel) ?: time();
    $out = [];

    if ($key === 'pages') {
        $pages = [
            ['/', 'index.php'], ['/pricing', 'pricing.php'], ['/use-cases/', 'use-cases/index.php'],
            ['/free-tools/', 'free-tools/index.php'], ['/blog', 'blog.php'], ['/tutorials', 'tutorials.php'],
            ['/about', 'about.php'], ['/contact', 'contact.php'], ['/affiliate', 'affiliate.php'],
            ['/privacy-policy', 'privacy-policy.php'], ['/terms', 'terms.php'],
        ];
        foreach ($pages as [$path, $file]) {
            if (!is_file($root . '/' . $file)) continue;
            $u = ['loc' => $base . $path, 'lastmod' => $mtime($file)];
            if ($path === '/') $u['image'] = SEO_DEFAULT_IMAGE;
            $out[] = $u;
        }
    } elseif ($key === 'use-cases') {
        require_once __DIR__ . '/use_case_functions.php';
        foreach (array_keys(uc_catalog()) as $slug) {
            if (!is_file($root . '/use-cases/' . $slug . '/index.php')) continue;
            $out[] = ['loc' => $base . '/use-cases/' . $slug . '/', 'lastmod' => max($mtime('use-cases/' . $slug . '/index.php'), $mtime('includes/use_case_functions.php'))];
        }
    } elseif ($key === 'free-tools') {
        foreach (glob($root . '/free-tools/*/index.php') ?: [] as $f) {
            $slug = basename(dirname($f));
            $out[] = ['loc' => $base . '/free-tools/' . $slug . '/', 'lastmod' => filemtime($f)];
        }
    } elseif ($key === 'blog') {
        try {
            $cats = $pdo->query("SELECT c.slug, MAX(COALESCE(p.updated_at, p.published_at)) m FROM blog_categories c
                JOIN blog_posts p ON p.category_id = c.id AND p.status = 'published' GROUP BY c.id, c.slug ORDER BY c.slug")->fetchAll();
            foreach ($cats as $c) $out[] = ['loc' => $base . '/' . $c['slug'], 'lastmod' => strtotime((string)$c['m']) ?: time()];
            $posts = $pdo->query("SELECT p.slug, p.feature_image, COALESCE(p.updated_at, p.published_at, p.created_at) m, c.slug cat FROM blog_posts p
                JOIN blog_categories c ON c.id = p.category_id WHERE p.status = 'published' ORDER BY p.published_at DESC LIMIT 45000")->fetchAll();
            foreach ($posts as $p) {
                $u = ['loc' => $base . '/' . $p['cat'] . '/' . $p['slug'], 'lastmod' => strtotime((string)$p['m']) ?: time()];
                if (!empty($p['feature_image'])) $u['image'] = seo_asset_url($p['feature_image']);
                $out[] = $u;
            }
        } catch (Throwable $e) { /* blog tables missing */ }
    }
    return $out;
}
