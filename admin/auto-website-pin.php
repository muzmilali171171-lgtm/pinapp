<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/image-quality-models.php';
require_admin_login();

$activePage = 'auto-website-pin';
$pageTitle = 'Auto Website to Daily Pin — AI Settings';

$settings = get_article_settings($pdo);
$success = false;

$textModelCatalog = [
    'chatgpt' => ['gpt-4o-mini', 'gpt-4o'],
    'claude' => ['claude-haiku-4-5-20251001', 'claude-sonnet-5'],
    'google' => ['gemini-2.0-flash', 'gemini-2.5-flash'],
    'openrouter' => ['google/gemma-4-31b-it:free', 'nvidia/nemotron-3-super-120b-a12b:free', 'google/gemma-4-26b-a4b-it:free', 'z-ai/glm-5.2:free', 'deepseek/deepseek-v4-flash-0731:free'],
    'deepinfra' => ['deepseek-ai/DeepSeek-V4.1-Flash', 'meta-llama/Llama-Guard-4-12B', 'openai/gpt-oss-20b', 'openai/gpt-oss-120b', 'mistralai/Mistral-Small-24B-Instruct-2501'],
];
$textPlatformLabels = ['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'google' => 'Google (Gemini)', 'openrouter' => 'OpenRouter', 'deepinfra' => 'DeepInfra'];
$cloudflareCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE status = 'active'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $textProvider = trim($_POST['text_platform'] ?? '') ?: null;
    $textModel = trim($_POST['text_model'] ?? '') ?: null;

    $imageMode = trim($_POST['image_mode'] ?? 'deepinfra_ultra');
    if ($imageMode === 'cloudflare') {
        $imageProvider = 'cloudflare';
        $imageModel = null;
        $imageIterations = null;
    } else {
        $imageProvider = 'deepinfra';
        $imageModel = 'black-forest-labs/FLUX-1-schnell';
        $imageIterations = ['deepinfra_budget' => 2, 'deepinfra_high' => 3, 'deepinfra_ultra' => 4][$imageMode] ?? 4;
    }

    $fields = [
        'wpin_text_provider' => $textProvider, 'wpin_text_model' => $textModel,
    ];
    if ($settings) {
        $sql = "UPDATE article_settings SET " . implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields))) . " WHERE id = ?";
        $pdo->prepare($sql)->execute([...array_values($fields), $settings['id']]);
    } else {
        $sql = "INSERT INTO article_settings (" . implode(', ', array_keys($fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($fields));
    }
    image_quality_models_save_posted($pdo, ['website_pin']);
    log_event($pdo, 'system', 'Auto Website to Daily Pin AI settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}

$currentImageMode = 'deepinfra_ultra';
if (($settings['wpin_image_provider'] ?? '') === 'cloudflare') {
    $currentImageMode = 'cloudflare';
} elseif ($settings) {
    $currentImageMode = ['2' => 'deepinfra_budget', '3' => 'deepinfra_high', '4' => 'deepinfra_ultra'][(string)($settings['wpin_image_iterations'] ?? 4)] ?? 'deepinfra_ultra';
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Auto Website to Daily Pin — AI Settings</h1></div>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<p class="muted">These models are used only by <strong>Auto Website to Daily Pin</strong> — separate from Bulk Pin
Scheduler / Auto Article's own model settings, so you can run a different (e.g. cheaper or higher-quality) model
for this feature specifically.</p>

<form method="POST">
    <div class="card">
        <h2>Pin Writer <span class="muted" style="font-weight:400;">(title, description, alt text, keywords, board names)</span></h2>
        <div class="two-col">
            <div class="form-row">
                <label>Text Platform</label>
                <select name="text_platform" id="textPlatform">
                    <option value="">-- select --</option>
                    <?php foreach ($textPlatformLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($settings['wpin_text_provider'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
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

    <?php render_image_quality_models($pdo, 'website_pin', 'Image Settings', 'AI pin images for Auto Website to Daily Pin'); ?>

    <button type="submit" class="btn-primary">Save</button>
</form>

<script>
const TEXT_MODEL_CATALOG = <?= json_encode($textModelCatalog, JSON_HEX_TAG) ?>;
const platformSelect = document.getElementById('textPlatform');
const modelSelect = document.getElementById('textModel');
function populateTextModels() {
    const models = TEXT_MODEL_CATALOG[platformSelect.value] || [];
    modelSelect.innerHTML = models.length
        ? models.map(m => `<option value="${m}" ${m === <?= json_encode($settings['wpin_text_model'] ?? '', JSON_HEX_TAG) ?> ? 'selected' : ''}>${m}</option>`).join('')
        : '<option value="">-- select a platform --</option>';
}
platformSelect.addEventListener('change', populateTextModels);
populateTextModels();
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
