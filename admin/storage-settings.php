<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'storage-settings';
$pageTitle = 'Storage Settings';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_provider') {
        $provider = trim($_POST['provider'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $endpoint = trim($_POST['endpoint'] ?? '');
        $region = trim($_POST['region'] ?? '') ?: 'auto';
        $bucket = trim($_POST['bucket'] ?? '');
        $accessKey = trim($_POST['access_key'] ?? '');
        $secretKey = trim($_POST['secret_key'] ?? '');
        $publicBaseUrl = trim($_POST['public_base_url'] ?? '');
        $capacityGb = (float)($_POST['capacity_gb'] ?? 10);
        $tier = trim($_POST['tier'] ?? 'free');
        $priority = (int)($_POST['priority'] ?? 0);

        if (!in_array($provider, ['b2', 's3', 'r2'], true) || $endpoint === '' || $bucket === '' || $accessKey === '' || $secretKey === '') {
            $error = 'Please fill in all required fields.';
        } else {
            $pdo->prepare("INSERT INTO storage_providers (provider, label, endpoint, region, bucket, access_key, secret_key, public_base_url, capacity_gb, tier, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$provider, $label, $endpoint, $region, $bucket, $accessKey, $secretKey, $publicBaseUrl ?: null, $capacityGb, $tier, $priority]);
            log_event($pdo, 'system', "Admin added storage provider account: $provider ($label)");
            redirect('storage-settings?saved=1');
        }
    } elseif ($action === 'delete_provider') {
        $pdo->prepare("DELETE FROM storage_providers WHERE id = ?")->execute([(int)($_POST['provider_id'] ?? 0)]);
        redirect('storage-settings');
    } elseif ($action === 'toggle_provider') {
        $id = (int)($_POST['provider_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT status FROM storage_providers WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        $pdo->prepare("UPDATE storage_providers SET status = ? WHERE id = ?")->execute([$current === 'active' ? 'disabled' : 'active', $id]);
        redirect('storage-settings');
    } elseif ($action === 'save_ext_settings') {
        $vals = ['external_enabled' => !empty($_POST['external_enabled']) ? 1 : 0];
        foreach (array_keys(EXT_CATEGORIES) as $cat) $vals['store_' . $cat] = !empty($_POST['store_' . $cat]) ? 1 : 0;
        $vals['delete_local_after_days'] = max(0, min(3650, (int)($_POST['delete_local_after_days'] ?? 0)));
        ext_save_settings($pdo, $vals);
        log_event($pdo, 'system', 'Admin updated external storage settings');
        redirect('storage-settings?saved=1');
    } elseif ($action === 'sync_existing') {
        @set_time_limit(120);
        $r = ext_sweep($pdo, 60, 0, 60);
        $_SESSION['storage_flash'] = "Checked {$r['checked']} image(s): {$r['uploaded']} moved to external storage, {$r['hosting']} kept on hosting."
            . ($r['checked'] >= 60 ? ' Click again to continue (the cron also keeps going in the background).' : '');
        redirect('storage-settings');
    } elseif ($action === 'test_provider') {
        $id = (int)($_POST['provider_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM storage_providers WHERE id = ?");
        $st->execute([$id]);
        if ($prov = $st->fetch()) {
            $key = 'webtopin/_connection_test_' . bin2hex(random_bytes(4)) . '.txt';
            $r = s3_put_object(storage_provider_config($prov), $key, 'ok ' . date('c'), 'text/plain');
            if ($r['ok']) {
                s3_delete_object(storage_provider_config($prov), $key);
                $pdo->prepare("UPDATE storage_providers SET last_error = NULL, last_error_at = NULL WHERE id = ?")->execute([$id]);
                $_SESSION['storage_flash'] = 'Connection OK — "' . ($prov['label'] ?: $prov['bucket']) . '" accepted a test upload.'
                    . ($prov['public_base_url'] ? '' : ' Note: no Public Base URL set, so images would still be served from hosting.');
            } else {
                ext_log_error($pdo, $id, null, 'Connection test failed: ' . $r['error']);
                $error = 'Connection test failed: ' . $r['error'];
            }
        }
        if (!$error) redirect('storage-settings');
    } elseif ($action === 'edit_public_url') {
        $pdo->prepare("UPDATE storage_providers SET public_base_url = ? WHERE id = ?")
            ->execute([trim($_POST['public_base_url'] ?? '') ?: null, (int)($_POST['provider_id'] ?? 0)]);
        redirect('storage-settings?saved=1');
    } elseif ($action === 'clear_errors') {
        $pdo->exec("DELETE FROM storage_error_log");
        $pdo->exec("UPDATE storage_providers SET last_error = NULL, last_error_at = NULL");
        redirect('storage-settings');
    } elseif ($action === 'save_pexels') {
        $key = trim($_POST['pexels_key'] ?? '');
        $stmt = $pdo->query("SELECT id FROM pexels_settings LIMIT 1");
        $existing = $stmt->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE pexels_settings SET api_key = ? WHERE id = ?")->execute([$key, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO pexels_settings (api_key) VALUES (?)")->execute([$key]);
        }
        log_event($pdo, 'system', 'Admin updated Pexels API key');
        redirect('storage-settings?saved=1');
    }
}

ext_ensure_schema($pdo);
$extSettings = ext_settings($pdo);
$extSummary = ext_status_summary($pdo);
$recentErrors = [];
try { $recentErrors = $pdo->query("SELECT e.*, p.label, p.bucket, p.provider FROM storage_error_log e LEFT JOIN storage_providers p ON p.id = e.provider_row_id ORDER BY e.id DESC LIMIT 20")->fetchAll(); } catch (Throwable $e) {}
$flash = $_SESSION['storage_flash'] ?? null;
unset($_SESSION['storage_flash']);
$providers = $pdo->query("SELECT * FROM storage_providers ORDER BY (tier = 'paid'), priority ASC, id ASC")->fetchAll();
$activeWithRoom = 0;
foreach ($providers as $pp) if ($pp['status'] === 'active' && (float)$pp['used_bytes'] < (float)$pp['capacity_gb'] * 1073741824) $activeWithRoom++;
$pexelsKey = $pdo->query("SELECT api_key FROM pexels_settings LIMIT 1")->fetchColumn();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Storage Settings</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>

<?php
// Where new images are going right now, in plain words.
if (!$extSettings['external_enabled']) { $whereNow = ['info', 'External storage is OFF — all images are stored on hosting.']; }
elseif (empty($providers)) { $whereNow = ['info', 'External storage is ON, but no account is connected yet — images are stored on hosting until you add one below.']; }
elseif ($activeWithRoom === 0) { $whereNow = ['error', 'All external accounts are full or disabled — new images are being stored on hosting. Add another account or raise a capacity.']; }
else { $whereNow = ['success', "External storage is ON — new images go to your connected account(s) ($activeWithRoom with free space)."]; }
?>
<div class="alert alert-<?= e($whereNow[0]) ?>"><?= e($whereNow[1]) ?></div>

<div class="card">
    <h2>External Image Storage</h2>
    <form method="POST">
        <input type="hidden" name="action" value="save_ext_settings">
        <label class="checkbox-row" style="font-weight:600;">
            <input type="checkbox" name="external_enabled" value="1" <?= $extSettings['external_enabled'] ? 'checked' : '' ?>>
            Store all image data on external storage (Cloudflare R2 / Amazon S3 / Backblaze B2)
        </label>
        <p class="muted" style="margin:4px 0 12px;">Default ON. Every image — created by AI, uploaded, made for pins, articles, the Storage library or designs — goes to the accounts below.
        No account connected, all accounts full, or an upload error → the image is kept on hosting automatically and the reason shows on this page.</p>

        <div style="font-weight:600; margin-bottom:6px;">Images</div>
        <?php foreach (EXT_CATEGORIES as $cat => $info): ?>
            <label class="checkbox-row"><input type="checkbox" name="store_<?= e($cat) ?>" value="1" <?= !empty($extSettings['store_' . $cat]) ? 'checked' : '' ?>> <?= e($info['label']) ?></label>
        <?php endforeach; ?>
        <p class="muted" style="margin:4px 0 12px;">Each is ON by default. Turn one off to keep that kind of image on hosting only.</p>

        <div class="form-row" style="max-width:420px;">
            <label>Remove the hosting copy after (days) <span class="muted">— 0 = keep a copy on hosting too</span></label>
            <input type="number" name="delete_local_after_days" min="0" max="3650" value="<?= (int)$extSettings['delete_local_after_days'] ?>">
            <p class="muted" style="margin:4px 0 0;">Only for images already on an account with a Public Base URL. Old links keep working — they redirect to the external copy.</p>
        </div>
        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>

<div class="card">
    <h2>Status</h2>
    <div class="two-col">
        <div>
            <p><strong><?= number_format($extSummary['uploaded']) ?></strong> image(s) on external storage (<?= number_format($extSummary['uploaded_bytes'] / 1048576, 1) ?> MB)<?= $extSummary['local_deleted'] ? ' · ' . number_format($extSummary['local_deleted']) . ' hosting copies removed' : '' ?></p>
            <p><strong><?= number_format($extSummary['hosting']) ?></strong> image(s) kept on hosting —
                <?= number_format($extSummary['no_provider']) ?> no account connected,
                <?= number_format($extSummary['limit']) ?> accounts full,
                <?= number_format($extSummary['error']) ?> upload error(s). They are retried automatically.</p>
        </div>
        <div>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="sync_existing">
                <button type="submit" class="btn-secondary">Move existing images now</button>
            </form>
            <p class="muted" style="margin-top:6px;">New images move automatically every minute (cron/scheduler.php). This button also moves older images already on hosting.</p>
        </div>
    </div>
</div>

<div class="card">
    <h2>How storage works</h2>
    <p class="muted">Each user gets 1GB of storage. If no provider below is configured, images are stored on this
    server's own disk (fine for smaller usage). Add one or more provider accounts to move storage off-server —
    when an account fills up (default: capacity minus a small safety margin), new uploads automatically roll over
    to the next active account in priority order, free-tier accounts before paid ones.</p>
</div>

<div class="card">
    <h2>Cloudflare R2 <span class="muted" style="font-weight:400;">(recommended — 10GB free per account, and you can add several)</span></h2>
    <ol class="muted" style="line-height:1.9;">
        <li>In your Cloudflare dashboard, go to <strong>R2</strong> → create a bucket.</li>
        <li>Under <strong>R2 → Manage API Tokens</strong>, create a token with Object Read & Write permissions — this gives you an Access Key ID and Secret Access Key.</li>
        <li>Your endpoint is <code>https://&lt;account_id&gt;.r2.cloudflarestorage.com</code> (find your account ID on the R2 overview page).</li>
        <li>To make images publicly viewable, enable the bucket's public access (R2.dev domain, or connect a custom domain) and paste that as the Public Base URL below.</li>
        <li><strong>To grow free capacity automatically:</strong> add several R2 accounts (different Cloudflare accounts, each with its own free 10GB) as separate rows below with capacity ~9.9GB — once one fills, uploads move to the next automatically.</li>
    </ol>
</div>
<div class="card">
    <h2>Backblaze B2</h2>
    <p class="muted">B2 has an S3-compatible API. Create a bucket and an application key (with read/write access to
    that bucket) from your B2 dashboard. The S3-compatible endpoint looks like
    <code>https://s3.&lt;region&gt;.backblazeb2.com</code> — your account's exact endpoint/region is shown on the
    bucket's details page.</p>
</div>
<div class="card">
    <h2>Amazon S3</h2>
    <p class="muted">Create a bucket and an IAM user with S3 read/write access to it. Endpoint is
    <code>https://s3.&lt;region&gt;.amazonaws.com</code>.</p>
</div>

<div class="card">
    <h2>Add a Provider Account</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add_provider">
        <div class="two-col">
            <div class="form-row">
                <label>Provider</label>
                <select name="provider" required>
                    <option value="r2">Cloudflare R2</option>
                    <option value="b2">Backblaze B2</option>
                    <option value="s3">Amazon S3</option>
                </select>
            </div>
            <div class="form-row"><label>Label</label><input type="text" name="label" placeholder="e.g. R2 Account 1"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Endpoint</label><input type="text" name="endpoint" placeholder="https://xxxx.r2.cloudflarestorage.com" required></div>
            <div class="form-row"><label>Region</label><input type="text" name="region" value="auto" placeholder="auto"></div>
        </div>
        <div class="form-row"><label>Bucket Name</label><input type="text" name="bucket" required></div>
        <div class="two-col">
            <div class="form-row"><label>Access Key ID</label><input type="text" name="access_key" required></div>
            <div class="form-row"><label>Secret Access Key</label><input type="password" name="secret_key" required></div>
        </div>
        <div class="form-row"><label>Public Base URL <span class="muted">(where uploaded files become viewable — optional)</span></label><input type="text" name="public_base_url" placeholder="https://pub-xxxx.r2.dev"></div>
        <div class="two-col">
            <div class="form-row"><label>Capacity (GB)</label><input type="number" step="0.1" name="capacity_gb" value="9.9"></div>
            <div class="form-row"><label>Tier</label>
                <select name="tier"><option value="free">Free</option><option value="paid">Paid</option></select>
            </div>
        </div>
        <div class="form-row"><label>Priority <span class="muted">(lower fills first)</span></label><input type="number" name="priority" value="0"></div>
        <button type="submit" class="btn-primary">Add Account</button>
    </form>
</div>

<div class="card">
    <h2>Configured Accounts</h2>
    <?php if (empty($providers)): ?>
        <p class="muted">None yet — uploads currently go to local disk.</p>
    <?php else: ?>
    <table>
        <tr><th>Provider</th><th>Label</th><th>Bucket</th><th>Used</th><th>Capacity</th><th>Tier</th><th>Status</th><th>Public URL</th><th>Last error</th><th></th></tr>
        <?php foreach ($providers as $p):
            $usedGb = $p['used_bytes'] / 1073741824;
        ?>
        <tr>
            <td><?= e(strtoupper($p['provider'])) ?></td>
            <td><?= e($p['label'] ?: '—') ?></td>
            <td><?= e($p['bucket']) ?></td>
            <td><?= number_format($usedGb, 2) ?> GB</td>
            <td><?= number_format((float)$p['capacity_gb'], 1) ?> GB</td>
            <td><?= e(ucfirst($p['tier'])) ?></td>
            <td>
                <span class="badge badge-<?= $p['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($p['status'])) ?></span>
                <?php if ($p['status'] === 'active' && (float)$p['used_bytes'] >= (float)$p['capacity_gb'] * 1073741824): ?><br><span class="badge badge-error">Limit reached</span><?php endif; ?>
            </td>
            <td style="min-width:200px;">
                <form method="POST" style="display:flex; gap:4px;">
                    <input type="hidden" name="action" value="edit_public_url">
                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                    <input type="text" name="public_base_url" value="<?= e($p['public_base_url'] ?? '') ?>" placeholder="https://pub-xxxx.r2.dev" style="min-width:140px;">
                    <button type="submit" class="btn-secondary btn-small">Save</button>
                </form>
            </td>
            <td style="max-width:260px; font-size:12px;">
                <?php if (!empty($p['last_error'])): ?>
                    <span style="color:#b91c1c;"><?= e(mb_substr($p['last_error'], 0, 220)) ?></span><br><span class="muted"><?= e(format_datetime($p['last_error_at'] ?? null)) ?></span>
                <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="test_provider">
                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small">Test</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_provider">
                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small"><?= $p['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this account? Existing images already stored there will keep working, but new uploads will stop using it.');">
                    <input type="hidden" name="action" value="delete_provider">
                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <h2 style="margin:0;">Storage Errors</h2>
        <?php if ($recentErrors): ?>
        <form method="POST"><input type="hidden" name="action" value="clear_errors"><button type="submit" class="btn-secondary btn-small">Clear</button></form>
        <?php endif; ?>
    </div>
    <?php if (!$recentErrors): ?>
        <p class="muted">No errors. 👍</p>
    <?php else: ?>
    <p class="muted">When an upload fails or an account is full, the image is saved on hosting instead — nothing is lost. Latest 20:</p>
    <div style="overflow-x:auto;">
    <table>
        <tr><th>When</th><th>Account</th><th>File</th><th>Error</th></tr>
        <?php foreach ($recentErrors as $er): ?>
        <tr>
            <td style="white-space:nowrap;"><?= e(format_datetime($er['created_at'])) ?></td>
            <td><?= $er['provider_row_id'] ? e(strtoupper((string)$er['provider']) . ' ' . ($er['label'] ?: $er['bucket'])) : '<span class="muted">—</span>' ?></td>
            <td style="font-size:12px; word-break:break-all;"><?= e((string)$er['local_path']) ?></td>
            <td style="font-size:12px; color:#b91c1c;"><?= e(mb_substr($er['message'], 0, 400)) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Pexels (Stock Images search)</h2>
    <p class="muted">Free API key from <a href="https://www.pexels.com/api/" target="_blank" rel="noopener">pexels.com/api</a> — create an account, request an API key, paste it below.</p>
    <form method="POST">
        <input type="hidden" name="action" value="save_pexels">
        <div class="form-row"><label>Pexels API Key</label><input type="text" name="pexels_key" value="<?= e($pexelsKey ?? '') ?>"></div>
        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
