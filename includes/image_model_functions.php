<?php
/**
 * Image model registry + "which model does each quality tier use" per feature.
 *
 * Admin → (Bulk Pin Scheduler | Auto Website to Daily Pin | Auto Article Pin) → Image Settings
 * lets the admin pick, for every quality tier, either a DeepInfra model or Cloudflare:
 *   Low quality    (user's "Budget")  → e.g. FLUX-1-schnell, 2 steps
 *   Medium quality (user's "High")    → e.g. FLUX-1-dev, 25 steps
 *   High quality   (user's "Ultra")   → e.g. FLUX-2-klein-9b / FLUX-2-pro
 * What the USER pays per tier is unchanged: Plan Pricing → Setting (image_quality_* multipliers).
 *
 * Stored in the image_model_settings table (feature, quality) → provider/model/steps.
 * Until the admin saves a feature, image_model_for() falls back to the old single-model columns in
 * article_settings, so upgrading changes nothing by itself.
 */

/**
 * Supported image models. 'api' = which DeepInfra endpoint the model uses:
 *   native → /v1/inference/{model}            honours num_inference_steps (+ width/height)
 *   openai → /v1/openai/images/generations    fixed quality, no step count
 * 'strict' = follows instructions well enough that an explicit "no text" line helps (for FLUX-1
 * and Cloudflare's diffusion models, mentioning text at all makes them MORE likely to draw it).
 */
function image_model_catalog(): array
{
    return [
        'black-forest-labs/FLUX-1-schnell' => [
            'label' => 'FLUX-1 schnell (fast, cheapest)', 'api' => 'native', 'steps' => true,
            'default_steps' => 4, 'min_steps' => 1, 'max_steps' => 8, 'strict' => false,
            'price_note' => 'DeepInfra price × 4 iters (price scales with steps)',
        ],
        'black-forest-labs/FLUX-1-dev' => [
            'label' => 'FLUX-1 dev (detailed)', 'api' => 'native', 'steps' => true,
            'default_steps' => 25, 'min_steps' => 10, 'max_steps' => 50, 'strict' => false,
            'price_note' => 'DeepInfra price × (iters ÷ 25)',
        ],
        'black-forest-labs/FLUX-2-klein-9b' => [
            'label' => 'FLUX-2 klein 9B (fast, great quality)', 'api' => 'openai', 'steps' => false, 'strict' => true,
            'price_note' => 'DeepInfra price × (width/1024) × (height/1024), no step count',
        ],
        'black-forest-labs/FLUX-2-pro' => [
            'label' => 'FLUX-2 pro (production quality)', 'api' => 'openai', 'steps' => false, 'strict' => true,
            'price_note' => 'Fixed DeepInfra price per image',
        ],
        'black-forest-labs/FLUX-2-max' => [
            'label' => 'FLUX-2 max (top quality)', 'api' => 'openai', 'steps' => false, 'strict' => true,
            'price_note' => 'Fixed DeepInfra price per image',
        ],
        'google/nano-banana-2-lite' => [
            'label' => 'Google Nano Banana 2 Lite', 'api' => 'openai', 'steps' => false, 'strict' => true,
            'square_only' => true, 'price_note' => 'DeepInfra token price per image',
        ],
    ];
}

/** Features that have their own per-quality image settings. */
function image_model_features(): array
{
    return [
        'bulk_pin' => 'Bulk Pin Scheduler (also Single Pin, Regenerate & Keyword Research pins)',
        'website_pin' => 'Auto Website to Daily Pin',
        'article_pin' => 'Auto Article — pin images',
        'article_feature' => 'Auto Article — featured image',
        'article_content' => 'Auto Article — images inside articles',
    ];
}

/** Tier keys: admin/pricing names (low/medium/high) ↔ the user-facing quality values. */
function image_quality_tiers(): array
{
    return [
        'low' => ['user' => 'budget', 'label' => 'Low quality', 'pricing_key' => 'image_quality_low'],
        'medium' => ['user' => 'high', 'label' => 'Medium quality', 'pricing_key' => 'image_quality_medium'],
        'high' => ['user' => 'ultra', 'label' => 'High quality', 'pricing_key' => 'image_quality_high'],
    ];
}

function image_quality_tier_from_user(string $quality): string
{
    if ($quality === 'budget') return 'low';
    if ($quality === 'high') return 'medium';
    return 'high'; // 'ultra' and legacy values
}

/** The admin's saved rows for one feature: ['low' => row|null, 'medium' => ..., 'high' => ...]. */
function image_model_settings_get(PDO $pdo, string $feature): array
{
    $out = ['low' => null, 'medium' => null, 'high' => null];
    try {
        $stmt = $pdo->prepare("SELECT * FROM image_model_settings WHERE feature = ?");
        $stmt->execute([$feature]);
        foreach ($stmt->fetchAll() as $r) {
            if (array_key_exists($r['quality'], $out)) $out[$r['quality']] = $r;
        }
    } catch (Throwable $e) {
        // Table not migrated yet — callers fall back to the legacy columns.
    }
    return $out;
}

/**
 * Save one feature's three tiers from a posted form: $posted[tier] = ['model' => 'cloudflare'|<catalog key>, 'steps' => n].
 */
function image_model_settings_save(PDO $pdo, string $feature, array $posted): void
{
    $catalog = image_model_catalog();
    $stmt = $pdo->prepare("INSERT INTO image_model_settings (feature, quality, provider, model, steps) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model), steps = VALUES(steps)");
    foreach (array_keys(image_quality_tiers()) as $tier) {
        $choice = (string)($posted[$tier]['model'] ?? '');
        if ($choice === 'cloudflare') {
            $stmt->execute([$feature, $tier, 'cloudflare', null, null]);
            continue;
        }
        if (!isset($catalog[$choice])) continue;
        $m = $catalog[$choice];
        $steps = null;
        if ($m['steps']) {
            $steps = (int)($posted[$tier]['steps'] ?? $m['default_steps']);
            $steps = max($m['min_steps'], min($m['max_steps'], $steps ?: $m['default_steps']));
        }
        $stmt->execute([$feature, $tier, 'deepinfra', $choice, $steps]);
    }
}

/**
 * Which provider/model/steps to use for $feature at the user's $quality, plus the credit cost
 * (always from Plan Pricing → Setting). Returns ['provider','model','iterations','cost'].
 */
function image_model_for(PDO $pdo, string $feature, string $quality): array
{
    $tier = image_quality_tier_from_user($quality);
    $cost = image_quality_cost($pdo, image_quality_tiers()[$tier]['user']);
    $row = image_model_settings_get($pdo, $feature)[$tier];
    if ($row) {
        if ($row['provider'] === 'cloudflare') {
            return ['provider' => 'cloudflare', 'model' => '', 'iterations' => 4, 'cost' => $cost];
        }
        $catalog = image_model_catalog();
        $m = $catalog[$row['model']] ?? $catalog['black-forest-labs/FLUX-1-schnell'];
        $steps = $m['steps'] ? (int)($row['steps'] ?: $m['default_steps']) : 0;
        return ['provider' => 'deepinfra', 'model' => $row['model'], 'iterations' => $steps, 'cost' => $cost];
    }

    // Legacy fallback — exactly the behaviour before per-quality settings existed.
    $s = get_article_settings($pdo) ?: [];
    $map = [
        'bulk_pin' => ['pin_image_provider', 'pin_image_model'],
        'article_pin' => ['pin_image_provider', 'pin_image_model'],
        'website_pin' => ['wpin_image_provider', 'wpin_image_model'],
        'article_feature' => ['feature_image_provider', 'feature_image_model'],
        'article_content' => ['image_provider', 'image_model'],
    ][$feature] ?? ['pin_image_provider', 'pin_image_model'];
    $provider = $s[$map[0]] ?? 'deepinfra';
    $iterations = ['low' => 2, 'medium' => 3, 'high' => 4][$tier];
    if ($provider === 'cloudflare') {
        return ['provider' => 'cloudflare', 'model' => '', 'iterations' => $iterations, 'cost' => $cost];
    }
    return ['provider' => 'deepinfra', 'model' => $s[$map[1]] ?? 'black-forest-labs/FLUX-1-schnell', 'iterations' => $iterations, 'cost' => $cost];
}

/** True when the model follows instructions well enough to be told "no text" explicitly. */
function image_model_is_strict(string $provider, string $model): bool
{
    if ($provider !== 'deepinfra') return false;
    return !empty(image_model_catalog()[$model]['strict']);
}
