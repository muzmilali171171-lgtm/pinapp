<?php
/**
 * Shared admin UI: "Platform → Model" dropdowns (same behaviour as Bulk Pin Scheduler).
 *
 * Text:  render_text_model_picker()  posts  <prefix>_platform + <prefix>_model
 * Image: render_image_model_picker() posts  <prefix>_model  ('cloudflare' or a DeepInfra catalog key)
 *
 * Used by: free-tool-text-settings, free-tool-etsy-settings, classic-wizard-settings (Pinterest Pin Maker),
 * free-tool-pin-create-settings, free-tool-image-creator-settings, article-settings.
 */
require_once __DIR__ . '/../../includes/image_model_functions.php';

/** Text models offered per platform — same list as Bulk Pin Scheduler. */
function admin_text_model_catalog(): array
{
    return [
        'chatgpt' => ['gpt-4o-mini', 'gpt-4o'],
        'claude' => ['claude-haiku-4-5-20251001', 'claude-sonnet-5'],
        'google' => ['gemini-2.0-flash', 'gemini-2.5-flash'],
        'openrouter' => ['google/gemma-4-31b-it:free', 'nvidia/nemotron-3-super-120b-a12b:free', 'google/gemma-4-26b-a4b-it:free', 'z-ai/glm-5.2:free', 'deepseek/deepseek-v4-flash-0731:free'],
        'deepinfra' => ['deepseek-ai/DeepSeek-V4.1-Flash', 'meta-llama/Llama-Guard-4-12B', 'openai/gpt-oss-20b', 'openai/gpt-oss-120b', 'mistralai/Mistral-Small-24B-Instruct-2501'],
    ];
}

function admin_text_platform_labels(): array
{
    return ['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'google' => 'Google (Gemini)', 'openrouter' => 'OpenRouter', 'deepinfra' => 'DeepInfra'];
}

/** Reads the posted text platform/model. Returns [provider|null, model|null]. */
function admin_text_model_posted(string $prefix): array
{
    $provider = trim((string)($_POST[$prefix . '_platform'] ?? ''));
    $model = trim((string)($_POST[$prefix . '_model'] ?? ''));
    if ($provider === '' || !isset(admin_text_platform_labels()[$provider])) return [null, null];
    return [$provider, $model !== '' ? $model : null];
}

/** Reads the posted image choice. Returns [provider, model|null]. */
function admin_image_model_posted(string $prefix, string $default = 'black-forest-labs/FLUX-1-schnell'): array
{
    $choice = trim((string)($_POST[$prefix . '_model'] ?? ''));
    if ($choice === 'cloudflare') return ['cloudflare', null];
    if ($choice === '') return [null, null];
    $catalog = image_model_catalog();
    return ['deepinfra', isset($catalog[$choice]) ? $choice : $default];
}

/** Which text platforms have an admin API key saved (Models page). */
function admin_text_platforms_with_keys(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query("SELECT provider FROM ai_providers WHERE model_type = 'text' AND api_key IS NOT NULL AND api_key != ''")->fetchAll() as $r) {
        $out[$r['provider']] = true;
    }
    return $out;
}

/**
 * Platform + Model dropdowns. $emptyLabel = label of the "no platform" option (null = platform required).
 */
function render_text_model_picker(PDO $pdo, string $prefix, ?string $currentProvider, ?string $currentModel, string $label = 'Text Model', ?string $emptyLabel = '-- Select platform --'): void
{
    static $scriptPrinted = false;
    $catalog = admin_text_model_catalog();
    // Keep a previously saved model visible even if it isn't in the fixed list.
    if ($currentProvider && $currentModel && isset($catalog[$currentProvider]) && !in_array($currentModel, $catalog[$currentProvider], true)) {
        array_unshift($catalog[$currentProvider], $currentModel);
    }
    $withKeys = admin_text_platforms_with_keys($pdo);
    $platformId = $prefix . 'Platform';
    $modelId = $prefix . 'Model';
    ?>
    <div class="two-col">
        <div class="form-row">
            <label><?= e($label) ?> — Platform</label>
            <select name="<?= e($prefix) ?>_platform" id="<?= e($platformId) ?>" class="tmp-platform" data-model-select="<?= e($modelId) ?>" <?= $emptyLabel === null ? 'required' : '' ?>>
                <option value=""><?= e($emptyLabel ?? '-- Select platform --') ?></option>
                <?php foreach (admin_text_platform_labels() as $key => $plabel): ?>
                    <option value="<?= e($key) ?>" <?= $currentProvider === $key ? 'selected' : '' ?>>
                        <?= e($plabel) ?><?= empty($withKeys[$key]) ? ' (no API key)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Model</label>
            <select name="<?= e($prefix) ?>_model" id="<?= e($modelId) ?>" data-current="<?= e((string)$currentModel) ?>"
                    data-catalog="<?= e(json_encode($catalog)) ?>"></select>
        </div>
    </div>
    <?php if ($currentProvider && empty($withKeys[$currentProvider])): ?>
        <p class="muted" style="font-size:13px;margin-top:-6px;">No API key saved for this platform yet — add it under <a href="ai-api">Models</a>.</p>
    <?php endif; ?>
    <?php if (!$scriptPrinted): $scriptPrinted = true; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tmp-platform').forEach(function (platform) {
            var model = document.getElementById(platform.getAttribute('data-model-select'));
            var catalog = JSON.parse(model.getAttribute('data-catalog') || '{}');
            var current = model.getAttribute('data-current') || '';
            function fill() {
                var list = catalog[platform.value] || [];
                model.innerHTML = '';
                if (!list.length) {
                    var o = document.createElement('option');
                    o.value = ''; o.textContent = '-- select a platform first --';
                    model.appendChild(o);
                    model.disabled = true;
                    return;
                }
                model.disabled = false;
                list.forEach(function (m) {
                    var o = document.createElement('option');
                    o.value = m; o.textContent = m;
                    if (m === current) o.selected = true;
                    model.appendChild(o);
                });
            }
            platform.addEventListener('change', fill);
            fill();
        });
    });
    </script>
    <?php endif;
}

/**
 * Image model dropdown: every DeepInfra catalog model + Cloudflare Worker.
 * $emptyLabel non-null adds a "none" option (e.g. Article Write: web images only).
 */
function render_image_model_picker(PDO $pdo, string $prefix, ?string $currentProvider, ?string $currentModel, string $label = 'Image Model', ?string $emptyLabel = null): void
{
    $catalog = image_model_catalog();
    $cloudflareCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE status = 'active'")->fetchColumn();
    if ($currentProvider === 'cloudflare') {
        $current = 'cloudflare';
    } elseif ($currentProvider === 'deepinfra') {
        $current = isset($catalog[(string)$currentModel]) ? $currentModel : 'black-forest-labs/FLUX-1-schnell';
    } else {
        $current = $emptyLabel !== null ? '' : 'black-forest-labs/FLUX-1-schnell';
    }
    ?>
    <div class="form-row">
        <label><?= e($label) ?></label>
        <select name="<?= e($prefix) ?>_model">
            <?php if ($emptyLabel !== null): ?>
                <option value="" <?= $current === '' ? 'selected' : '' ?>><?= e($emptyLabel) ?></option>
            <?php endif; ?>
            <optgroup label="DeepInfra (paid)">
                <?php foreach ($catalog as $key => $m): ?>
                    <option value="<?= e($key) ?>" <?= $current === $key ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Free">
                <option value="cloudflare" <?= $current === 'cloudflare' ? 'selected' : '' ?>>
                    Cloudflare Worker (<?= $cloudflareCount ?> active account<?= $cloudflareCount === 1 ? '' : 's' ?>)
                </option>
            </optgroup>
        </select>
    </div>
    <?php if ($current === 'cloudflare' && $cloudflareCount === 0): ?>
        <div class="alert alert-info">No active Cloudflare accounts yet — add one under <a href="ai-api">Models</a>.</div>
    <?php endif;
}
