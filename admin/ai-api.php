<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'ai-api';
$pageTitle = 'Models';

$textProviders = [
    'chatgpt' => 'ChatGPT (OpenAI)',
    'claude' => 'Claude (Anthropic)',
    'openrouter' => 'OpenRouter',
    'deepinfra' => 'DeepInfra',
    'google' => 'Google (Gemini)',
];
$imageProviders = [
    'deepinfra' => 'DeepInfra',
];

$success = false;
$cfError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_keys';

    if ($action === 'save_keys') {
        $rows = $_POST['providers'] ?? [];
        foreach ($rows as $key => $row) {
            [$provider, $modelType] = explode('|', $key);
            $apiKey = trim($row['api_key'] ?? '');

            $stmt = $pdo->prepare("SELECT id FROM ai_providers WHERE provider = ? AND model_type = ?");
            $stmt->execute([$provider, $modelType]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->prepare("UPDATE ai_providers SET api_key = ? WHERE id = ?")->execute([$apiKey, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO ai_providers (provider, model_type, api_key, default_model) VALUES (?, ?, ?, '')")
                    ->execute([$provider, $modelType, $apiKey]);
            }
        }
        log_event($pdo, 'system', 'Model API keys updated by admin');
        $success = true;
    }

    if ($action === 'save_cloudflare') {
        $id = (int)($_POST['cf_id'] ?? 0);
        $workerUrl = trim($_POST['worker_url'] ?? '');
        $apiKey = trim($_POST['cf_api_key'] ?? '');
        $dailyLimit = max(1, (int)($_POST['daily_limit'] ?? 30));

        if ($workerUrl === '' || !filter_var($workerUrl, FILTER_VALIDATE_URL)) {
            $cfError = 'Please enter a valid Worker URL.';
        } else {
            if ($id > 0) {
                $pdo->prepare("UPDATE cloudflare_accounts SET worker_url = ?, api_key = ?, daily_limit = ? WHERE id = ?")
                    ->execute([$workerUrl, $apiKey, $dailyLimit, $id]);
            } else {
                $pdo->prepare("INSERT INTO cloudflare_accounts (worker_url, api_key, daily_limit) VALUES (?, ?, ?)")
                    ->execute([$workerUrl, $apiKey, $dailyLimit]);
            }
            log_event($pdo, 'system', 'Cloudflare image account ' . ($id > 0 ? 'updated' : 'added') . ' by admin');
            redirect('ai-api?saved_cf=1');
        }
    }

    if ($action === 'toggle_cloudflare') {
        $id = (int)($_POST['cf_id'] ?? 0);
        $pdo->prepare("UPDATE cloudflare_accounts SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ?")->execute([$id]);
        redirect('ai-api');
    }

    if ($action === 'delete_cloudflare') {
        $id = (int)($_POST['cf_id'] ?? 0);
        $pdo->prepare("DELETE FROM cloudflare_accounts WHERE id = ?")->execute([$id]);
        redirect('ai-api');
    }

    if ($action === 'import_settings') {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $cfError = 'Please choose a valid exported settings file to import.';
        } else {
            $raw = file_get_contents($_FILES['import_file']['tmp_name']);
            $data = json_decode($raw, true);
            if (!is_array($data) || (!isset($data['ai_providers']) && !isset($data['cloudflare_accounts']))) {
                $cfError = 'That file doesn\'t look like a Models/Cloudflare export.';
            } else {
                $providersImported = 0;
                foreach (($data['ai_providers'] ?? []) as $row) {
                    $provider = trim($row['provider'] ?? '');
                    $modelType = trim($row['model_type'] ?? '');
                    $apiKey = trim($row['api_key'] ?? '');
                    if ($provider === '' || $modelType === '' || $apiKey === '') continue;
                    $stmt = $pdo->prepare("SELECT id FROM ai_providers WHERE provider = ? AND model_type = ?");
                    $stmt->execute([$provider, $modelType]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        $pdo->prepare("UPDATE ai_providers SET api_key = ?, default_model = ? WHERE id = ?")
                            ->execute([$apiKey, $row['default_model'] ?? '', $existing['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO ai_providers (provider, model_type, api_key, default_model) VALUES (?, ?, ?, ?)")
                            ->execute([$provider, $modelType, $apiKey, $row['default_model'] ?? '']);
                    }
                    $providersImported++;
                }

                $cfImported = 0;
                foreach (($data['cloudflare_accounts'] ?? []) as $row) {
                    $workerUrl = trim($row['worker_url'] ?? '');
                    if ($workerUrl === '' || !filter_var($workerUrl, FILTER_VALIDATE_URL)) continue;
                    $apiKey = trim($row['api_key'] ?? '');
                    $dailyLimit = max(1, (int)($row['daily_limit'] ?? 30));
                    $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE worker_url = ?");
                    $stmt->execute([$workerUrl]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        $pdo->prepare("UPDATE cloudflare_accounts SET api_key = ?, daily_limit = ? WHERE id = ?")
                            ->execute([$apiKey, $dailyLimit, $existing['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO cloudflare_accounts (worker_url, api_key, daily_limit) VALUES (?, ?, ?)")
                            ->execute([$workerUrl, $apiKey, $dailyLimit]);
                    }
                    $cfImported++;
                }

                log_event($pdo, 'system', "Admin imported Models/Cloudflare settings: $providersImported provider key(s), $cfImported Cloudflare account(s)");
                redirect('ai-api?imported=' . $providersImported . '-' . $cfImported);
            }
        }
    }
}
if (isset($_GET['saved_cf'])) $success = true;
$imported = null;
if (isset($_GET['imported'])) {
    [$impProviders, $impCf] = array_pad(explode('-', $_GET['imported']), 2, 0);
    $imported = "Imported $impProviders provider key(s) and $impCf Cloudflare account(s).";
}

$allRows = $pdo->query("SELECT * FROM ai_providers")->fetchAll();
$lookup = [];
foreach ($allRows as $r) {
    $lookup[$r['provider'] . '|' . $r['model_type']] = $r;
}

function ai_api_key_row($key, $label, $lookup) {
    $existing = $lookup[$key] ?? ['api_key' => ''];
    echo '<div class="form-row">';
    echo '<label>' . e($label) . ' — API Key</label>';
    echo '<input type="text" name="providers[' . e($key) . '][api_key]" value="' . e($existing['api_key'] ?? '') . '" placeholder="paste API key">';
    echo '</div>';
}

$cloudflareAccounts = get_cloudflare_accounts($pdo);
$editCf = null;
if (!empty($_GET['edit_cf'])) {
    $stmt = $pdo->prepare("SELECT * FROM cloudflare_accounts WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_cf']]);
    $editCf = $stmt->fetch();
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Models</h1></div>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($imported): ?><div class="alert alert-success"><?= e($imported) ?></div><?php endif; ?>
<?php if ($cfError): ?><div class="alert alert-error"><?= e($cfError) ?></div><?php endif; ?>
<p class="muted">Add your platform API keys here. Which <strong>model</strong> to use for each feature is chosen
under <strong>AI Setting By Features</strong> (e.g. Bulk Pin Scheduler) — not here.</p>

<div class="card">
    <h2>Export / Import Settings</h2>
    <p class="muted">Moving to a new install, or setting this up on another site? Export your provider API keys
    and Cloudflare Worker accounts here, then import that file on the new install instead of re-entering
    everything by hand. Import updates a matching provider/worker if one already exists, and adds new ones
    otherwise — nothing is deleted, and per-account daily usage isn't included (it's a daily counter, not a
    setting).</p>
    <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">
        <a href="ai-api-export" class="btn-secondary">⬇ Download Settings (Models + Cloudflare)</a>
        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="action" value="import_settings">
            <input type="file" name="import_file" accept="application/json" required>
            <button type="submit" class="btn-primary">⬆ Import Settings</button>
        </form>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="action" value="save_keys">
    <div class="card">
        <h2>Text Platforms</h2>
        <?php foreach ($textProviders as $key => $label): ?>
            <?php ai_api_key_row("$key|text", $label, $lookup); ?>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Image Platform</h2>
        <?php foreach ($imageProviders as $key => $label): ?>
            <?php ai_api_key_row("$key|image", $label, $lookup); ?>
        <?php endforeach; ?>
        <p class="muted">DeepInfra (FLUX-1-schnell) is the paid image option. For a free option, add one or more
        Cloudflare Worker accounts below.</p>
    </div>

    <button type="submit" class="btn-primary">Save API Keys</button>
</form>

<div class="card">
    <h2>Cloudflare Accounts <span class="muted" style="font-weight:400;">(free image generation)</span></h2>
    <p class="muted">Add unlimited Cloudflare Worker accounts here. Each has its own daily image limit; once one
    account hits its limit for the day, generation automatically rolls over to the next account, and so on.
    Any feature that's set to use the free Cloudflare model (e.g. Bulk Pin Scheduler's AI pin images) draws from
    this pool at 0.2 credits/image.</p>

    <?php if (empty($cloudflareAccounts)): ?>
        <p class="muted">No Cloudflare accounts added yet.</p>
    <?php else: ?>
    <table>
        <tr><th>ID</th><th>Worker URL</th><th>Daily Limit</th><th>Used Today</th><th>Status</th><th></th></tr>
        <?php foreach ($cloudflareAccounts as $acc): ?>
        <tr>
            <td>#<?= (int)$acc['id'] ?></td>
            <td style="word-break:break-all;"><?= e($acc['worker_url']) ?></td>
            <td><?= (int)$acc['daily_limit'] ?>/day</td>
            <td><?= (int)$acc['used_today'] ?></td>
            <td><span class="badge badge-<?= $acc['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($acc['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="?edit_cf=<?= (int)$acc['id'] ?>" class="btn-secondary btn-small">Edit</a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_cloudflare">
                    <input type="hidden" name="cf_id" value="<?= (int)$acc['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small"><?= $acc['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this Cloudflare account?');">
                    <input type="hidden" name="action" value="delete_cloudflare">
                    <input type="hidden" name="cf_id" value="<?= (int)$acc['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h3 style="margin-top:22px;"><?= $editCf ? 'Edit Account #' . (int)$editCf['id'] : 'Add Account' ?></h3>
    <form method="POST">
        <input type="hidden" name="action" value="save_cloudflare">
        <input type="hidden" name="cf_id" value="<?= $editCf ? (int)$editCf['id'] : 0 ?>">
        <div class="form-row">
            <label>Worker URL</label>
            <input type="url" name="worker_url" value="<?= e($editCf['worker_url'] ?? '') ?>" placeholder="https://flux-generator-2.example.workers.dev/" required>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>API Key</label>
                <input type="text" name="cf_api_key" value="<?= e($editCf['api_key'] ?? '') ?>" placeholder="e.g. 1234Mu@">
            </div>
            <div class="form-row">
                <label>Daily Image Limit</label>
                <input type="number" name="daily_limit" value="<?= e((string)($editCf['daily_limit'] ?? 30)) ?>" min="1">
            </div>
        </div>
        <button type="submit" class="btn-primary"><?= $editCf ? 'Save Changes' : '+ Add More Accounts' ?></button>
        <?php if ($editCf): ?><a href="ai-api" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
