<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/free_tool_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/model-pickers.php';
require_admin_login();

$activePage = 'classic-wizard-settings';
$pageTitle = 'Classic Wizard';

$settings = get_article_settings($pdo);
$success = false;

$textOptions = $pdo->query("SELECT * FROM ai_providers WHERE model_type = 'text' AND api_key IS NOT NULL AND api_key != ''")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$textProvider, $textModel] = admin_text_model_posted('text');

    $fields = [
        'freetool_text_provider' => $textProvider ?: null,
        'freetool_text_model' => $textModel ?: null,
        'freetool_max_attempts' => max(1, (int)($_POST['max_attempts'] ?? 10)),
        'pinmaker_attempts' => max(1, (int)($_POST['pinmaker_attempts'] ?? 3)),
        'freetool_ai_design' => !empty($_POST['ai_design']) ? 1 : 0,
        'freetool_template_count' => max(1, min(12, (int)($_POST['template_count'] ?? 4))),
        'freetool_coupon_code' => trim($_POST['coupon_code'] ?? '') ?: 'PIN20',
        'freetool_discount_percent' => max(0, min(100, (int)($_POST['discount_percent'] ?? 20))),
        'freetool_marketing_heading' => trim($_POST['marketing_heading'] ?? '') ?: 'Want to generate Pins on autopilot?',
        'freetool_marketing_body' => trim($_POST['marketing_body'] ?? '') ?: 'Create hundreds of Pinterest Pins for your website in minutes. Choose from multiple templates, customize fonts and colors, and schedule 30 days in advance.',
    ];

    if ($settings) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE article_settings SET $set WHERE id = ?")->execute([...array_values($fields), $settings['id']]);
    } else {
        $cols = implode(', ', array_keys($fields));
        $ph = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO article_settings ($cols) VALUES ($ph)")->execute(array_values($fields));
    }
    log_event($pdo, 'system', 'Classic Wizard settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Classic Wizard Settings</h1></div>
<p class="muted">Controls the public, no-login Pinterest Pin Maker (<code>/free-tools/pinterest-pin-maker</code>) and the
logged-in user's Classic Wizard.</p>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<?php if (empty($textOptions)): ?>
    <div class="alert alert-info">No text model has an API key saved yet. Go to <a href="ai-api">Models</a> first and add at least one key.</div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <div class="bs-panel-title" style="margin-top:0;">Platform / Model</div>
        <?php render_text_model_picker($pdo, 'text', $settings['freetool_text_provider'] ?? null, $settings['freetool_text_model'] ?? null, 'Text Model (writes pin titles & descriptions)', '-- None / plain URL-derived titles --'); ?>

        <div class="bs-panel-title">Design</div>
        <div class="two-col">
            <div class="form-row">
                <label>Default Template Set Count</label>
                <input type="number" name="template_count" min="1" max="12" value="<?= (int)($settings['freetool_template_count'] ?? 4) ?>">
                <p class="muted" style="margin-bottom:0;">How many pin templates are shown per generation, before the visitor picks their own.</p>
            </div>
            <div class="form-row">
                <label class="checkbox-row" style="margin-top:26px;">
                    <input type="checkbox" name="ai_design" value="1" <?= !empty($settings['freetool_ai_design'] ?? 1) ? 'checked' : '' ?>>
                    AI Pin Design
                </label>
                <p class="muted" style="margin-bottom:0;">When on, AI gives each page's pins a unique design by making the title/wording distinct per pin instead of reusing the same one.</p>
            </div>
        </div>

        <div class="bs-panel-title">Logged-Out Usage</div>
        <div class="form-row">
            <label>Pin Maker — free generations per visitor</label>
            <input type="number" name="pinmaker_attempts" min="1" value="<?= (int)($settings['pinmaker_attempts'] ?? 3) ?>">
            <p class="muted" style="margin-bottom:0;">How many times one visitor can click “Generate pins” on <code>/free-tools/pinterest-pin-maker</code> (counted per browser and per IP, whichever is higher). Each generation covers up to 5 pages × 3 pins.</p>
        </div>
        <input type="hidden" name="max_attempts" value="<?= (int)($settings['freetool_max_attempts'] ?? 10) ?>">

        <div class="bs-panel-title">Marketing Template (shown below the free tool)</div>
        <div class="two-col">
            <div class="form-row">
                <label>Coupon Code</label>
                <input type="text" name="coupon_code" value="<?= e($settings['freetool_coupon_code'] ?? 'PIN20') ?>">
            </div>
            <div class="form-row">
                <label>Discount Badge (%)</label>
                <input type="number" name="discount_percent" min="0" max="100" value="<?= (int)($settings['freetool_discount_percent'] ?? 20) ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Heading</label>
            <input type="text" name="marketing_heading" value="<?= e($settings['freetool_marketing_heading'] ?? 'Want to generate Pins on autopilot?') ?>">
        </div>
        <div class="form-row">
            <label>Body Text</label>
            <textarea name="marketing_body" rows="3"><?= e($settings['freetool_marketing_body'] ?? 'Create hundreds of Pinterest Pins for your website in minutes. Choose from multiple templates, customize fonts and colors, and schedule 30 days in advance.') ?></textarea>
        </div>

        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
