<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/blog_functions.php';

$user = current_user($pdo);
$categorySlug = trim($_GET['category'] ?? '');
$slug = trim($_GET['slug'] ?? '');
$post = ($categorySlug && $slug) ? blog_get_post_by_slug($pdo, $categorySlug, $slug) : null;

if (!$post || $post['status'] !== 'published') {
    // Wrong / unpublished slug -> friendly 404 page.
    require __DIR__ . '/404.php';
    exit;
}

blog_increment_views($pdo, (int)$post['id']);
$content = blog_inject_heading_ids(clean_php_links_in_html($post['content'] ?? ''));
$toc = blog_extract_toc($content);
$related = blog_related_posts($pdo, (int)$post['id'], (int)$post['category_id'], 3);
$readingTime = blog_reading_time($content);
$isGrowthArticle = $post['slug'] === 'pinterest-growth';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
$postUrl = rtrim(APP_URL, '/') . '/' . $post['category_slug'] . '/' . $post['slug'];
seo_render_head($pdo, [
    'title' => ($post['meta_title'] ?: $post['title']) . ' | ' . SITE_BRAND,
    'description' => $post['meta_description'] ?: blog_excerpt($content, 160),
    'image' => $post['feature_image'] ?: null,
    'image_alt' => $post['title'],
    'canonical' => $postUrl,
    'og_type' => 'article',
    'breadcrumbs' => [['Blog', 'blog'], [$post['category_name'], $post['category_slug']], [$post['title'], $postUrl]],
]);
$articleLd = [
    '@context' => 'https://schema.org', '@type' => 'BlogPosting',
    'headline' => mb_substr($post['title'], 0, 110),
    'description' => blog_excerpt($post['meta_description'] ?: $content, 200),
    'mainEntityOfPage' => $postUrl, 'url' => $postUrl,
    'datePublished' => date('c', strtotime($post['published_at'] ?: $post['created_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'] ?: ($post['published_at'] ?: $post['created_at']))),
    'author' => ['@type' => $post['author'] && stripos($post['author'], 'team') === false ? 'Person' : 'Organization', 'name' => $post['author'] ?: SITE_BRAND],
    'publisher' => ['@type' => 'Organization', 'name' => SITE_BRAND, 'url' => rtrim(APP_URL, '/') . '/'],
];
if ($post['feature_image']) $articleLd['image'] = seo_asset_url($post['feature_image']);
?>
<script type="application/ld+json"><?= json_encode($articleLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
<?php if ($isGrowthArticle): ?>
<link rel="stylesheet" href="/assets/css/rg-effects.css?v=<?= @filemtime(__DIR__ . '/assets/css/rg-effects.css') ?: time() ?>">
<?php endif; ?>
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<article class="blog-post <?= $isGrowthArticle ? 'blog-post-rg' : '' ?>">
    <div class="container blog-post-head">
        <a href="/<?= e($post['category_slug']) ?>" class="blog-post-cat-link"><?= e($post['category_name']) ?></a>
        <h1><?= e($post['title']) ?></h1>
        <?php if ($post['subtitle']): ?><p class="blog-post-subtitle"><?= e($post['subtitle']) ?></p><?php endif; ?>
        <div class="blog-post-meta">
            <?php if ($post['author']): ?><span>By <?= e($post['author']) ?></span><span>·</span><?php endif; ?>
            <span><?= e(date('M j, Y', strtotime($post['published_at'] ?: $post['created_at']))) ?></span>
            <span>·</span>
            <span><?= $readingTime ?> min read</span>
        </div>
    </div>

    <?php if ($post['feature_image']): ?>
        <div class="container blog-post-feature-img">
            <img src="/<?= e($post['feature_image']) ?>" alt="<?= e($post['title']) ?>">
        </div>
    <?php endif; ?>

    <div class="container blog-post-layout">
        <?php if (!empty($toc)): ?>
        <aside class="blog-post-toc">
            <div class="blog-post-toc-inner">
                <h4>Table of Contents</h4>
                <ul id="blogToc">
                    <?php foreach ($toc as $t): ?>
                        <li class="toc-level-<?= $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
        <?php endif; ?>

        <div class="blog-post-content"><?= $content ?></div>

        <aside class="blog-post-cta-side">
            <div class="blog-post-cta-card <?= $isGrowthArticle ? 'rg-border-glow' : '' ?>">
                <div class="blog-post-cta-icon">📌</div>
                <h4>Grow your traffic with Pinterest</h4>
                <ul>
                    <li>✓ Turn your pages into pins</li>
                    <li>✓ Rank in Pinterest search</li>
                    <li>✓ Drive clicks back to your site</li>
                </ul>
                <a href="<?= $user ? '/user/dashboard' : '/auth/register' ?>" class="btn-primary <?= $isGrowthArticle ? 'rg-shine' : '' ?>" style="display:block;text-align:center;">Start free now</a>
                <span class="blog-post-cta-note">No credit card required</span>
            </div>
        </aside>
    </div>
</article>

<section class="blog-post-bottom-cta">
    <div class="container">
        <h2>Ready to Drive More Traffic with Pinterest?</h2>
        <p>Turn the content you already have into eye-catching Pins that drive clicks and bring more visitors back to your website.</p>
        <a href="<?= $user ? '/user/dashboard' : '/auth/register' ?>" class="btn-primary">Start free now</a>
    </div>
</section>

<?php if (!empty($related)): ?>
<section class="container blog-related">
    <h2>Related Posts</h2>
    <div class="blog-grid">
        <?php foreach ($related as $r):
            $img = $r['feature_image'] ? '/' . $r['feature_image'] : 'https://images.unsplash.com/photo-1611926653458-09294b3142bf?w=600&q=60';
            $rdate = date('M j, Y', strtotime($r['published_at'] ?: $r['created_at']));
        ?>
        <a class="blog-card" href="/<?= e($r['category_slug']) ?>/<?= e($r['slug']) ?>">
            <div class="blog-card-img"><img src="<?= e($img) ?>" alt="<?= e($r['title']) ?>" loading="lazy"></div>
            <div class="blog-card-body">
                <span class="blog-card-cat"><?= e($r['category_name']) ?></span>
                <h3><?= e($r['title']) ?></h3>
                <span class="blog-card-date"><?= e($rdate) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php render_site_footer($pdo); ?>

<script>
(function () {
    var links = document.querySelectorAll('#blogToc a');
    var headings = Array.prototype.slice.call(document.querySelectorAll('.blog-post-content h2[id], .blog-post-content h3[id]'));
    links.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.getElementById(this.getAttribute('href').slice(1));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    if (!headings.length || !('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                links.forEach(function (a) { a.classList.remove('active'); });
                var link = document.querySelector('#blogToc a[href="#' + entry.target.id + '"]');
                if (link) link.classList.add('active');
            }
        });
    }, { rootMargin: '-20% 0px -70% 0px' });
    headings.forEach(function (h) { io.observe(h); });
})();
</script>

</body>
</html>
