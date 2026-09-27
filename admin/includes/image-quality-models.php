<?php
/**
 * Shared admin UI: "which image model does each quality tier use" for one feature.
 * Used by admin/bulk-scheduler.php, admin/auto-website-pin.php and admin/auto-article-pin.php.
 * Posts imgq[<feature>][low|medium|high][model|steps]; save with image_quality_models_save_posted().
 */
require_once __DIR__ . '/../../includes/image_model_functions.php';
require_once __DIR__ . '/../../includes/pricing_functions.php';

function image_quality_models_save_posted(PDO $pdo, array $allowedFeatures): void
{
    foreach ((array)($_POST['imgq'] ?? []) as $feature => $tiers) {
        if (in_array($feature, $allowedFeatures, true) && is_array($tiers)) {
            image_model_settings_save($pdo, $feature, $tiers);
        }
    }
}

function render_image_quality_models(PDO $pdo, string $feature, string $title, string $subtitle = ''): void
{
    static $scriptPrinted = false;
    $catalog = image_model_catalog();
    $saved = image_model_settings_get($pdo, $feature);
    $pricing = credit_pricing_get($pdo);
    $cloudflareCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE status = 'active'")->fetchColumn();
    ?>
    <div class="card">
        <h2><?= e($title) ?><?php if ($subtitle): ?> <span class="muted" style="font-weight:400;">(<?= e($subtitle) ?>)</span><?php endif; ?></h2>
        <p class="muted" style="margin-top:0;">Pick the model each quality tier uses. What users pay per image for each tier is set under
            <a href="plan-settings">Plan Pricing → Setting</a> and is shown here for reference.</p>
        <table class="imgq-table">
            <tr><th>Quality tier</th><th>User pays</th><th>Model</th><th>Steps</th></tr>
            <?php foreach (image_quality_tiers() as $tier => $t):
                $row = $saved[$tier];
                if ($row) {
                    $current = $row['provider'] === 'cloudflare' ? 'cloudflare' : $row['model'];
                    $steps = $row['steps'];
                } else {
                    // Not saved yet: show what is used today (legacy single-model setting).
                    $now = image_model_for($pdo, $feature, $t['user']);
                    $current = $now['provider'] === 'cloudflare' ? 'cloudflare' : $now['model'];
                    $steps = $now['iterations'];
                }
                $name = 'imgq[' . $feature . '][' . $tier . ']';
            ?>
            <tr>
                <td><b><?= e($t['label']) ?></b><br><span class="muted" style="font-size:12px;">user's “<?= e(ucfirst($t['user'])) ?>” option</span></td>
                <?php $price = (float)$pricing[$t['pricing_key']]; ?>
                <td><?= e(rtrim(rtrim(number_format($price, 2), '0'), '.')) ?> credit<?= $price == 1 ? '' : 's' ?>/image</td>
                <td>
                    <select name="<?= e($name) ?>[model]" class="imgq-model">
                        <?php foreach ($catalog as $key => $m): ?>
                            <option value="<?= e($key) ?>" data-steps="<?= $m['steps'] ? 1 : 0 ?>" data-default="<?= (int)($m['default_steps'] ?? 0) ?>"
                                data-min="<?= (int)($m['min_steps'] ?? 0) ?>" data-max="<?= (int)($m['max_steps'] ?? 0) ?>" data-note="<?= e($m['price_note']) ?>"
                                <?= $current === $key ? 'selected' : '' ?>>DeepInfra — <?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="cloudflare" data-steps="0" data-note="Free for you — rotates across your Cloudflare accounts" <?= $current === 'cloudflare' ? 'selected' : '' ?>>
                            Cloudflare Worker (<?= $cloudflareCount ?> active account<?= $cloudflareCount === 1 ? '' : 's' ?>)</option>
                    </select>
                    <div class="muted imgq-note" style="font-size:12px; margin-top:4px;"></div>
                </td>
                <td>
                    <input type="number" name="<?= e($name) ?>[steps]" class="imgq-steps" value="<?= e((string)($steps ?: '')) ?>" style="width:80px;">
                    <span class="muted imgq-nosteps" style="font-size:12px;">fixed</span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($cloudflareCount === 0): ?>
            <p class="muted" style="font-size:13px;">No active Cloudflare accounts yet — add one under <a href="ai-api">Models</a> before choosing Cloudflare.</p>
        <?php endif; ?>
    </div>
    <?php if (!$scriptPrinted): $scriptPrinted = true; ?>
    <style>
    .imgq-table { width: 100%; }
    .imgq-table td { vertical-align: top; }
    .imgq-table select { width: 100%; max-width: 420px; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.imgq-model').forEach(function (sel) {
            var row = sel.closest('tr');
            var steps = row.querySelector('.imgq-steps'), fixed = row.querySelector('.imgq-nosteps'), note = row.querySelector('.imgq-note');
            function sync(changed) {
                var o = sel.options[sel.selectedIndex];
                var has = o.getAttribute('data-steps') === '1';
                steps.style.display = has ? '' : 'none';
                fixed.style.display = has ? 'none' : '';
                note.textContent = o.getAttribute('data-note') || '';
                if (has) {
                    steps.min = o.getAttribute('data-min'); steps.max = o.getAttribute('data-max');
                    var v = parseInt(steps.value, 10);
                    if (changed || !v || v < +steps.min || v > +steps.max) steps.value = o.getAttribute('data-default');
                }
            }
            sel.addEventListener('change', function () { sync(true); });
            sync(false);
        });
    });
    </script>
    <?php endif;
}
