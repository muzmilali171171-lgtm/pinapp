<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/image-quality-models.php';
require_admin_login();

$activePage = 'auto-article-pin';
$pageTitle = 'Auto Article Pin — AI Settings';

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
    $articleTextProvider = trim($_POST['article_text_platform'] ?? '') ?: null;
    $articleTextModel = trim($_POST['article_text_model'] ?? '') ?: null;

    $contentImageMode = trim($_POST['content_image_mode'] ?? 'deepinfra');
    $imageProvider = $contentImageMode === 'cloudflare' ? 'cloudflare' : 'deepinfra';
    $imageModel = $contentImageMode === 'cloudflare' ? null : 'black-forest-labs/FLUX-1-schnell';

    $featureImageMode = trim($_POST['feature_image_mode'] ?? 'deepinfra');
    $featureImageProvider = $featureImageMode === 'cloudflare' ? 'cloudflare' : 'deepinfra';
    $featureImageModel = $featureImageMode === 'cloudflare' ? null : 'black-forest-labs/FLUX-1-schnell';

    $pinTextProvider = trim($_POST['pin_text_platform'] ?? '') ?: null;
    $pinTextModel = trim($_POST['pin_text_model'] ?? '') ?: null;

    $pinImageMode = trim($_POST['pin_image_mode'] ?? 'deepinfra_budget');
    if ($pinImageMode === 'cloudflare') {
        $pinImageProvider = 'cloudflare';
        $pinImageModel = null;
        $pinImageIterations = null;
    } else {
        $pinImageProvider = 'deepinfra';
        $pinImageModel = 'black-forest-labs/FLUX-1-schnell';
        $pinImageIterations = ['deepinfra_budget' => 2, 'deepinfra_high' => 3, 'deepinfra_ultra' => 4][$pinImageMode] ?? 2;
    }

    $fields = [
        'text_provider' => $articleTextProvider, 'text_model' => $articleTextModel,
        'pin_text_provider' => $pinTextProvider, 'pin_text_model' => $pinTextModel,
    ];

    if ($settings) {
        $sql = "UPDATE article_settings SET " . implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields))) . " WHERE id = ?";
        $pdo->prepare($sql)->execute([...array_values($fields), $settings['id']]);
    } else {
        $sql = "INSERT INTO article_settings (" . implode(', ', array_keys($fields)) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($fields));
    }
    image_quality_models_save_posted($pdo, ['article_pin', 'article_feature', 'article_content']);
    log_event($pdo, 'system', 'Auto Article Pin AI settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}

$currentContentImageMode = ($settings['image_provider'] ?? '') === 'cloudflare' ? 'cloudflare' : 'deepinfra';
$currentFeatureImageMode = ($settings['feature_image_provider'] ?? '') === 'cloudflare' ? 'cloudflare' : 'deepinfra';
$currentPinImageMode = 'deepinfra_budget';
if (($settings['pin_image_provider'] ?? '') === 'cloudflare') {
    $currentPinImageMode = 'cloudflare';
} elseif ($settings) {
    $currentPinImageMode = ['2' => 'deepinfra_budget', '3' => 'deepinfra_high', '4' => 'deepinfra_ultra'][(string)($settings['pin_image_iterations'] ?? 2)] ?? 'deepinfra_budget';
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Auto Article Pin — AI Settings</h1></div>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<p class="muted">These are the models the <strong>Auto Article</strong> feature uses to write and illustrate articles,
and to write and illustrate their auto-generated pins. The pin text model is shared with Bulk Pin Scheduler; the three
image settings below are this feature's own.</p>

<form method="POST">
    <div class="card">
        <h2>Article Writing <span class="muted" style="font-weight:400;">(outline, draft, titles, meta description)</span></h2>
        <div class="two-col">
            <div class="form-row">
                <label>Text Platform</label>
                <select name="article_text_platform" id="articleTextPlatform">
                    <option value="">-- select --</option>
                    <?php foreach ($textPlatformLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($settings['text_provider'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Model</label>
                <select name="article_text_model" id="articleTextModel"></select>
            </div>
        </div>
        <p class="muted">Add the matching platform's API key under <a href="ai-api">Models</a> first. This is the
        same setting as the Article Write page.</p>
    </div>

    <?php render_image_quality_models($pdo, 'article_content', 'Image Settings for Article Images', 'the plain photos inside Ideas/Recipe articles'); ?>
    <?php render_image_quality_models($pdo, 'article_feature', 'Image Settings for Feature Image', 'the WordPress featured image'); ?>

    <div class="card">
        <h2>Pin Content <span class="muted" style="font-weight:400;">(title, description, alt text, keywords, board suggestions — shared with Bulk Pin Scheduler)</span></h2>
        <div class="two-col">
            <div class="form-row">
                <label>Text Platform</label>
                <select name="pin_text_platform" id="pinTextPlatform">
                    <option value="">-- Use Article Write default --</option>
                    <?php foreach ($textPlatformLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($settings['pin_text_provider'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Model</label>
                <select name="pin_text_model" id="pinTextModel"></select>
            </div>
        </div>
    </div>

    <?php render_image_quality_models($pdo, 'article_pin', 'Image Settings for Pin Creating', 'the pins Auto Article creates for each article'); ?>

    <button type="submit" class="btn-primary">Save</button>
</form>

<script>
const TEXT_MODEL_CATALOG = <?= json_encode($textModelCatalog, JSON_HEX_TAG) ?>;
function wireCascade(platformId, modelId, current) {
    const platformSelect = document.getElementById(platformId);
    const modelSelect = document.getElementById(modelId);
    function populate() {
        const models = TEXT_MODEL_CATALOG[platformSelect.value] || [];
        modelSelect.innerHTML = models.length
            ? models.map(m => `<option value="${m}" ${m === current ? 'selected' : ''}>${m}</option>`).join('')
            : '<option value="">-- select a platform --</option>';
    }
    platformSelect.addEventListener('change', populate);
    populate();
}
wireCascade('articleTextPlatform', 'articleTextModel', <?= json_encode($settings['text_model'] ?? '', JSON_HEX_TAG) ?>);
wireCascade('pinTextPlatform', 'pinTextModel', <?= json_encode($settings['pin_text_model'] ?? '', JSON_HEX_TAG) ?>);
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
