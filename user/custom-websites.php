<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'custom-websites';
$pageTitle = 'Custom Websites';
$returnTo = 'custom-websites';

$connectId = (int)($_GET['connect_id'] ?? $_POST['connect_id'] ?? 0);
$target = $connectId ? get_user_website($pdo, $connectId, (int)$user['id']) : null;
if (!$target) $connectId = 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteUrl = normalize_site_url((string)($_POST['site_url'] ?? ''));
    $siteName = trim($_POST['site_name'] ?? '');
    $webhookUrl = trim($_POST['webhook_url'] ?? '');

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($siteUrl === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter a valid website URL, for example https://mysite.com';
    } elseif ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter a valid webhook URL, for example https://mysite.com/pinscheduler-webhook.php';
    } elseif (!platform_url_is_safe($webhookUrl)) {
        $errors[] = 'The webhook URL must be a public http(s) address that this server can reach.';
    }

    if (empty($errors)) {
        @set_time_limit(90);
        // Keep the existing secret when reconnecting the same site (so the receiving script keeps working), unless a new one is requested.
        $secret = '';
        if ($target && $target['platform'] === 'custom' && $target['site_key'] !== '' && empty($_POST['regenerate_secret'])) $secret = $target['site_key'];
        if ($secret === '') $secret = 'whsec_' . bin2hex(random_bytes(20));

        $id = website_save_connection($pdo, (int)$user['id'], $connectId ?: null, 'custom', [
            'site_url' => $siteUrl, 'site_name' => $siteName, 'site_key' => $secret, 'webhook_url' => $webhookUrl,
        ]);
        $site = get_user_website($pdo, $id, (int)$user['id']);
        $r = website_recheck($pdo, $site);
        if ($r['ok']) {
            flash_set('success', 'Custom website connected: ' . ($r['site_name'] ?: $siteUrl));
            redirect('custom-websites');
        }
        $errors[] = 'Saved, but the webhook test failed: ' . e((string)$r['error']) . ' Fix your receiving script (see the guide below) and click Re-check.';
    }
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND platform = 'custom' ORDER BY connected_at DESC, id DESC");
$stmt->execute([$user['id']]);
$siteRows = $stmt->fetchAll();

$receiverSample = <<<'PHPCODE'
<?php
// pinscheduler-webhook.php  -  put this on YOUR website and use its URL as the Webhook URL.
$secret = 'PASTE_YOUR_SECRET_KEY_HERE';

$body = file_get_contents('php://input');
$ts   = $_SERVER['HTTP_X_PINSCHEDULER_TIMESTAMP'] ?? '';
$sig  = $_SERVER['HTTP_X_PINSCHEDULER_SIGNATURE'] ?? '';

// 1) Make sure the request really comes from the app (and is fresh).
$expected = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
if (!hash_equals($expected, $sig) || abs(time() - (int)$ts) > 300) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($body, true);
header('Content-Type: application/json');

// 2) Connection test.
if ($event['event'] === 'ping') {
    echo json_encode([
        'ok' => true,
        'site_name' => 'My Website',
        'categories' => [['id' => 1, 'name' => 'News'], ['id' => 2, 'name' => 'Recipes']],
    ]);
    exit;
}

// 3) A new article to publish.
if ($event['event'] === 'article.publish') {
    $a = $event['article'];   // title, content_html, category, tags, status, slug, meta_description, featured_image_url
    // ... save it to your database / CMS here ...
    echo json_encode(['ok' => true, 'id' => 123, 'url' => 'https://mysite.com/' . $a['slug']]);
    exit;
}

echo json_encode(['ok' => true]);
PHPCODE;

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header"><h1>Custom Websites</h1><a href="websites" class="btn-secondary">← All Websites</a></div>

<?= flash_render() ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= $err ?></div><?php endforeach; ?>

<div class="card">
    <h2>How webhook connection works</h2>
    <div class="guide-box">
        <p>Any website — hand-coded, Laravel, Ghost, Webflow via a script, anything — can be connected with a <strong>webhook</strong>.
        When an article is ready, this app sends a signed <code>POST</code> request with the article as JSON to a URL on your site, and your site saves it.</p>
        <ol>
            <li>Add your website and its <strong>Webhook URL</strong> below. The app generates a <strong>secret key</strong> for you.</li>
            <li>Put the sample script (below) on your site at that URL and paste in the secret key.</li>
            <li>The app immediately sends a <code>ping</code> to test it. Your script must answer with HTTP 200 and JSON — the site then shows as <strong>Connected</strong>.</li>
        </ol>
        <h4>What your site receives</h4>
        <p>Every request has the headers <code>X-PinScheduler-Event</code>, <code>X-PinScheduler-Timestamp</code> and <code>X-PinScheduler-Signature</code>
        (<code>sha256=</code> + HMAC-SHA256 of <code>timestamp + "." + raw body</code> using your secret key).</p>
        <ul>
            <li><code>ping</code> — connection test. Optionally reply with <code>site_name</code> and <code>categories</code> (a list of <code>{"id", "name"}</code>) so they can be chosen when creating article batches.</li>
            <li><code>article.publish</code> — an <code>article</code> object with <code>title</code>, <code>content_html</code>, <code>category</code>, <code>tags</code>, <code>status</code>, <code>slug</code>, <code>meta_description</code> and <code>featured_image_url</code> (images inside the HTML are already absolute URLs). Reply with JSON containing the post's <code>id</code> and <code>url</code>. The <code>url</code> is used as the pin destination link when Pin Auto is on.</li>
        </ul>
    </div>
    <h4 style="margin-bottom:8px;">Sample receiving script (PHP)</h4>
    <pre class="code-block"><?= e($receiverSample) ?></pre>
</div>

<div class="card">
    <h2>Connect a custom website<?= $target ? ' — ' . e($target['site_url']) : '' ?></h2>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="connect_id" value="<?= (int)$connectId ?>">
        <div class="two-col">
            <div class="form-row">
                <label>Website URL</label>
                <input type="text" name="site_url" placeholder="https://mysite.com" value="<?= e($_POST['site_url'] ?? ($target['site_url'] ?? '')) ?>" required>
            </div>
            <div class="form-row">
                <label>Name <span class="muted">(optional)</span></label>
                <input type="text" name="site_name" placeholder="My Website" value="<?= e($_POST['site_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Webhook URL</label>
            <input type="text" name="webhook_url" placeholder="https://mysite.com/pinscheduler-webhook.php" value="<?= e($_POST['webhook_url'] ?? (($target && $target['platform'] === 'custom') ? (string)$target['webhook_url'] : '')) ?>" required>
        </div>
        <?php if ($target && $target['platform'] === 'custom'): ?>
            <label class="checkbox-row"><input type="checkbox" name="regenerate_secret" value="1"> Generate a new secret key (you will need to update your script)</label>
        <?php endif; ?>
        <button type="submit" class="btn-primary" style="margin-top:10px;">Connect Website</button>
    </form>
</div>

<div class="card">
    <h2>Your custom websites</h2>
    <?php $emptyText = 'No custom websites connected yet.'; include __DIR__ . '/includes/website-table.php'; ?>
</div>

<?php if (!empty($siteRows)): ?>
<div class="card">
    <h2>Webhook secret keys</h2>
    <p class="muted">Keep these private — anyone with a key can pretend to be this app when calling your webhook.</p>
    <table>
        <tr><th>Website</th><th>Webhook URL</th><th>Secret key</th></tr>
        <?php foreach ($siteRows as $s): ?>
        <tr>
            <td><?= e($s['site_name'] ?: $s['site_url']) ?></td>
            <td class="muted"><?= e((string)$s['webhook_url']) ?></td>
            <td>
                <div class="secret-field">
                    <input type="password" readonly value="<?= e($s['site_key']) ?>" style="width:260px;" id="secret<?= (int)$s['id'] ?>">
                    <button type="button" class="btn-secondary btn-small" onclick="var i=document.getElementById('secret<?= (int)$s['id'] ?>'); i.type = i.type==='password' ? 'text' : 'password';">Show</button>
                    <button type="button" class="btn-secondary btn-small" onclick="var i=document.getElementById('secret<?= (int)$s['id'] ?>'); navigator.clipboard.writeText(i.value); this.textContent='Copied';">Copy</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
