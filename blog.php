<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/blog_functions.php';

$user = current_user($pdo);
$categorySlug = trim($_GET['category'] ?? '') ?: null;

// Custom admin login address (Admin → Account Settings) is a single path segment, which the
// .htaccess routes here like a blog category.
require_once __DIR__ . '/includes/admin_security.php';
if ($categorySlug !== null && admin_login_slug_matches($pdo, $categorySlug)) {
    define('ADMIN_LOGIN_VIA_SLUG', true);
    require __DIR__ . '/admin/login.php';
    exit;
}
$search = trim($_GET['q'] ?? '');
$perPage = 21;
$offset = max(0, (int)($_GET['offset'] ?? 0));

$activeCategory = $categorySlug ? blog_get_category_by_slug($pdo, $categorySlug) : null;

// Unknown slug (e.g. /some-typo is routed here as a category) -> friendly 404 page.
if ($categorySlug !== null && !$activeCategory) {
    require __DIR__ . '/404.php';
    exit;
}
$categories = blog_get_categories($pdo, true);

$result = blog_public_list_posts($pdo, $categorySlug, $search, $offset, $perPage);
$posts = $result['rows'];
$hasMore = ($offset + count($posts)) < $result['total'];

/** Renders one post card. Shared between the first page render and the AJAX "Load More" response. */
function blog_render_card(array $p): string
{
    $img = $p['feature_image'] ? '/' . e($p['feature_image']) : 'https://images.unsplash.com/photo-1611926653458-09294b3142bf?w=600&q=60';
    $href = e($p['category_slug']) . '/' . e($p['slug']);
    $date = date('M j, Y', strtotime($p['published_at'] ?: $p['created_at']));
    ob_start();
    ?>
    <a class="blog-card" href="/<?= $href ?>">
        <div class="blog-card-img"><img src="<?= $img ?>" alt="<?= e($p['title']) ?>" loading="lazy"></div>
        <div class="blog-card-body">
            <span class="blog-card-cat"><?= e($p['category_name']) ?></span>
            <h3><?= e($p['title']) ?></h3>
            <p><?= e(blog_excerpt($p['subtitle'] ?: $p['content'], 110)) ?></p>
            <span class="blog-card-date"><?= e($date) ?></span>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $html = '';
    foreach ($posts as $p) $html .= blog_render_card($p);
    echo json_encode(['html' => $html, 'hasMore' => $hasMore]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
// One URL per listing: /blog for all posts, /<category> for a category (/blog/<category> points there too).
$blogCanonical = rtrim(APP_URL, '/') . '/' . ($activeCategory ? $activeCategory['slug'] : 'blog');
seo_render_head($pdo, [
    'title' => $activeCategory
        ? $activeCategory['name'] . ': Pinterest Guides & Articles | ' . SITE_BRAND
        : 'Pinterest Marketing Blog & Growth Guides | ' . SITE_BRAND,
    'description' => $activeCategory
        ? $activeCategory['name'] . ' articles from the ' . SITE_BRAND . ' blog: practical Pinterest marketing tips, step-by-step guides and honest comparisons to grow your traffic.'
        : 'Pinterest marketing guides, growth strategies, tool comparisons and how-to tutorials to get more traffic from Pinterest with automation and AI.',
    'canonical' => $blogCanonical,
    'noindex' => $search !== '',   // search result pages stay out of the index
    'breadcrumbs' => $activeCategory ? [['Blog', 'blog'], [$activeCategory['name'], $activeCategory['slug']]] : [['Blog', 'blog']],
]); ?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="container blog-archive">
    <div class="blog-archive-head">
        <h1><?= $activeCategory ? e($activeCategory['name']) : 'Our Blog' ?></h1>
        <p>Tips, guides, and strategies for Pinterest marketing.</p>
    </div>

    <div class="blog-pill-row">
        <a href="/blog" class="blog-pill <?= !$categorySlug ? 'active' : '' ?>">All <span>(<?= array_sum(array_column($categories, 'post_count')) ?>)</span></a>
        <?php foreach ($categories as $c): ?>
            <a href="/<?= e($c['slug']) ?>" class="blog-pill <?= $categorySlug === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?> <span>(<?= (int)$c['post_count'] ?>)</span></a>
        <?php endforeach; ?>
    </div>

    <form class="blog-search" method="GET">
        <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search articles...">
        <button type="submit">Search</button>
    </form>

    <div class="blog-grid" id="blogGrid">
        <?php if (empty($posts)): ?>
            <p class="muted">No articles found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
        <?php else: foreach ($posts as $p): echo blog_render_card($p); endforeach; endif; ?>
    </div>

    <?php if ($hasMore): ?>
        <div style="text-align:center;margin-top:30px;">
            <button type="button" class="btn-secondary" id="blogLoadMore">Load More</button>
        </div>
    <?php endif; ?>
</section>

<?php render_site_footer($pdo); ?>

<script>
(function () {
    var btn = document.getElementById('blogLoadMore');
    if (!btn) return;
    var offset = <?= (int)($offset + count($posts)) ?>;
    var params = new URLSearchParams(window.location.search);
    btn.addEventListener('click', function () {
        btn.textContent = 'Loading...';
        btn.disabled = true;
        var url = new URL(window.location.href);
        url.searchParams.set('ajax', '1');
        url.searchParams.set('offset', offset);
        fetch(url.toString()).then(function (r) { return r.json(); }).then(function (data) {
            document.getElementById('blogGrid').insertAdjacentHTML('beforeend', data.html);
            offset += 21;
            if (!data.hasMore) { btn.remove(); } else { btn.textContent = 'Load More'; btn.disabled = false; }
        }).catch(function () { btn.textContent = 'Load More'; btn.disabled = false; });
    });
})();
</script>

</body>
</html>
