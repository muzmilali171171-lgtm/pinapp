<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/image-quality-models.php';
require_admin_login();

$activePage = 'bulk-scheduler';
$pageTitle = 'Bulk Pin Scheduler — AI Settings';

$settings = get_article_settings($pdo);
$success = false;

// Fixed catalogs matching the models made available on each platform.
$textModelCatalog = [
    'chatgpt' => ['gpt-4o-mini', 'gpt-4o'],
    'claude' => ['claude-haiku-4-5-20251001', 'claude-sonnet-5'],
    'google' => ['gemini-2.0-flash', 'gemini-2.5-flash'],
    'openrouter' => ['google/gemma-4-31b-it:free', 'nvidia/nemotron-3-super-120b-a12b:free', 'google/gemma-4-26b-a4b-it:free', 'z-ai/glm-5.2:free', 'deepseek/deepseek-v4-flash-0731:free'],
    'deepinfra' => ['deepseek-ai/DeepSeek-V4.1-Flash', 'meta-llama/Llama-Guard-4-12B', 'openai/gpt-oss-20b', 'openai/gpt-oss-120b', 'mistralai/Mistral-Small-24B-Instruct-2501'],
];
$textPlatformLabels = ['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'google' => 'Google (Gemini)', 'openrouter' => 'OpenRouter', 'deepinfra' => 'DeepInfra'];

// Delete an entire bulk batch (all its still-pending pins).
if (isset($_GET['delete_batch'])) {
    $batchId = $_GET['delete_batch'];
    $pdo->prepare("DELETE FROM scheduled_pins WHERE batch_id = ? AND status = 'pending'")->execute([$batchId]);
    log_event($pdo, 'system', "Admin deleted pending pins for batch $batchId");
    redirect('bulk-scheduler');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pinTextProvider = trim($_POST['text_platform'] ?? '') ?: null;
    $pinTextModel = trim($_POST['text_model'] ?? '') ?: null;

    // Image models are saved per quality tier (image_model_settings) — the legacy single-model columns
    // are left untouched because Auto Article's pins still fall back to them until that page is saved.
    if ($settings) {
        $pdo->prepare("UPDATE article_settings SET pin_text_provider = ?, pin_text_model = ? WHERE id = ?")
            ->execute([$pinTextProvider, $pinTextModel, $settings['id']]);
    } else {
        $pdo->prepare("INSERT INTO article_settings (pin_text_provider, pin_text_model) VALUES (?, ?)")
            ->execute([$pinTextProvider, $pinTextModel]);
    }
    image_quality_models_save_posted($pdo, ['bulk_pin']);
    log_event($pdo, 'system', 'Bulk Pin Scheduler AI settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}

// Overview of bulk batches across all users.
$batches = $pdo->query("SELECT sp.batch_id, u.name AS user_name, sp.board_name,
        COUNT(*) AS total, SUM(sp.status = 'pending') AS pending, SUM(sp.status = 'published') AS published,
        SUM(sp.status = 'failed') AS failed, MIN(sp.publish_at) AS first_at, MAX(sp.publish_at) AS last_at,
        MAX(sp.created_at) AS created_at
    FROM scheduled_pins sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.source = 'bulk' AND sp.batch_id IS NOT NULL
    GROUP BY sp.batch_id, u.name, sp.board_name
    ORDER BY created_at DESC LIMIT 50")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Bulk Pin Scheduler — AI Settings</h1></div>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<form method="POST">
    <div class="card">
        <h2>Text Model <span class="muted" style="font-weight:400;">(pin titles, descriptions, alt text, keywords &amp; board suggestions)</span></h2>
        <div class="two-col">
            <div class="form-row">
                <label>Text Platform</label>
                <select name="text_platform" id="textPlatform">
                    <option value="">-- Use Article Write default --</option>
                    <?php foreach ($textPlatformLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($settings['pin_text_provider'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Model</label>
                <select name="text_model" id="textModel"></select>
            </div>
        </div>
        <p class="muted">Add the matching platform's API key under <a href="ai-api">Models</a> first.</p>
    </div>

    <?php render_image_quality_models($pdo, 'bulk_pin', 'Image Settings', 'AI pin images — also used by Single Pin Scheduler, Regenerate and Keyword Research'); ?>

    <button type="submit" class="btn-primary">Save</button>
</form>

<div class="card">
    <h2>Bulk Batches (<?= count($batches) ?>)</h2>
    <?php if (empty($batches)): ?>
        <div class="empty-state">No bulk pin batches yet.</div>
    <?php else: ?>
    <table>
        <tr><th>User</th><th>Board</th><th>Pins</th><th>Pending</th><th>Published</th><th>Failed</th><th>First → Last</th><th></th></tr>
        <?php foreach ($batches as $b): ?>
        <tr>
            <td><?= e($b['user_name']) ?></td>
            <td><?= e($b['board_name'] ?: 'Being created…') ?></td>
            <td><?= (int)$b['total'] ?></td>
            <td><span class="badge badge-pending"><?= (int)$b['pending'] ?></span></td>
            <td><span class="badge badge-published"><?= (int)$b['published'] ?></span></td>
            <td><span class="badge badge-failed"><?= (int)$b['failed'] ?></span></td>
            <td class="muted"><?= format_datetime($b['first_at']) ?> → <?= format_datetime($b['last_at']) ?></td>
            <td>
                <?php if ((int)$b['pending'] > 0): ?>
                <a href="?delete_batch=<?= urlencode($b['batch_id']) ?>" class="btn-secondary btn-small" onclick="return confirm('Cancel all still-pending pins in this batch?')">Cancel pending</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<script>
const TEXT_MODEL_CATALOG = <?= json_encode($textModelCatalog, JSON_HEX_TAG) ?>;
const CURRENT_TEXT_MODEL = <?= json_encode($settings['pin_text_model'] ?? '', JSON_HEX_TAG) ?>;
const platformSelect = document.getElementById('textPlatform');
const modelSelect = document.getElementById('textModel');
function populateTextModels() {
    const models = TEXT_MODEL_CATALOG[platformSelect.value] || [];
    modelSelect.innerHTML = models.length
        ? models.map(m => `<option value="${m}" ${m === CURRENT_TEXT_MODEL ? 'selected' : ''}>${m}</option>`).join('')
        : '<option value="">-- select a platform --</option>';
}
platformSelect.addEventListener('change', populateTextModels);
populateTextModels();
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
