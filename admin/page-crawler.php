<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'page-crawler';
$pageTitle = 'Page Crawler';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = trim($_POST['firecrawl_key'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM crawler_providers WHERE provider = 'firecrawl'");
    $stmt->execute();
    $existing = $stmt->fetch();
    if ($existing) {
        $pdo->prepare("UPDATE crawler_providers SET api_key = ? WHERE id = ?")->execute([$apiKey, $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO crawler_providers (provider, api_key) VALUES ('firecrawl', ?)")->execute([$apiKey]);
    }
    log_event($pdo, 'system', 'Admin updated Page Crawler (Firecrawl) settings');
    redirect('page-crawler?saved=1');
}

$stmt = $pdo->prepare("SELECT api_key FROM crawler_providers WHERE provider = 'firecrawl'");
$stmt->execute();
$currentKey = $stmt->fetchColumn();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Page Crawler</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="card">
    <h2>How page discovery works</h2>
    <p class="muted">When a user scans a website (under <strong>Auto Website to Daily Pin</strong> or Storage's
    Website Images), the app first looks for the site's own XML sitemap — checking <code>robots.txt</code>, then
    common paths like <code>/sitemap_index.xml</code>. This works for the vast majority of sites and needs no
    setup here. A crawler API is only used as a <strong>fallback</strong> for sites with no discoverable sitemap
    at all.</p>
</div>

<div class="card">
    <h2>Firecrawl (fallback crawler)</h2>
    <p class="muted">
        <a href="https://www.firecrawl.dev/" target="_blank" rel="noopener">Firecrawl</a> has a free tier and a
        simple API. To set it up:
    </p>
    <ol class="muted" style="line-height:1.9;">
        <li>Create a free account at <a href="https://www.firecrawl.dev/" target="_blank" rel="noopener">firecrawl.dev</a>.</li>
        <li>Open your dashboard and copy your API key (starts with <code>fc-</code>).</li>
        <li>Paste it below and save.</li>
    </ol>
    <form method="POST">
        <div class="form-row">
            <label>Firecrawl API Key</label>
            <input type="text" name="firecrawl_key" value="<?= e($currentKey ?? '') ?>" placeholder="fc-...">
        </div>
        <button type="submit" class="btn-primary">Save</button>
    </form>
    <p class="muted" style="margin-top:14px;">This integration is built from Firecrawl's documented API shape
    (<code>POST /v1/map</code>) but hasn't been exercised against a live account — if pages aren't coming back
    as expected, check Firecrawl's current API docs against <code>crawl_site_via_firecrawl()</code> in
    <code>includes/page_crawler_functions.php</code>.</p>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
