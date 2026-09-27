<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'plan-pricing';
$pageTitle = 'Create Plan';

if (!plan_pricing_tables_ready($pdo)) {
    include __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="page-header"><h1>Create Plan</h1></div>
    <div class="card">
        <div class="alert alert-error">Plan Pricing's database tables/columns aren't set up yet on this site.
        Please visit <a href="../migrate.php">migrate.php</a> once to create them, then reload this page.</div>
    </div>
    <?php
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$plan = $editId ? get_plan($pdo, $editId) : null;
if ($editId && !$plan) { redirect('plans'); }
$featureRows = $editId ? get_plan_feature_rows($pdo, $editId) : [];

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') $errors[] = 'Plan name is required.';

    if (empty($errors)) {
        $planId = save_plan($pdo, $editId, $_POST);
        $rows = [];
        foreach ($_POST['feature_text'] ?? [] as $i => $text) {
            $rows[] = [
                'text' => $text, 'tooltip' => $_POST['feature_tooltip'][$i] ?? '',
                'checkmark_type' => $_POST['feature_checkmark_type'][$i] ?? 'check',
                'text_size' => $_POST['feature_text_size'][$i] ?? '14px', 'text_color' => $_POST['feature_text_color'][$i] ?? '#1a1a1a',
                'font' => $_POST['feature_font'][$i] ?? '', 'checkmark_size' => $_POST['feature_checkmark_size'][$i] ?? '16px',
                'checkmark_color' => $_POST['feature_checkmark_color'][$i] ?? '#16a34a',
            ];
        }
        save_plan_feature_rows($pdo, $planId, $rows);
        log_event($pdo, 'system', ($editId ? 'Updated' : 'Created') . " pricing plan: $name");
        redirect('plans?saved=1');
    }
}

$f = $plan ?: [
    'name' => '', 'is_free' => 0, 'price_monthly' => 0, 'discount_monthly' => 0, 'discount_yearly' => 0,
    'short_description' => '', 'tag' => '', 'tag_color' => 'red',
    'pay_button_text' => 'Choose Plan', 'pay_button_bg' => '#e60023', 'pay_button_text_color' => '#ffffff', 'pay_button_border_color' => '#e60023',
    'buy_button_position' => 'bottom',
    'image_ai_credits_monthly' => 0, 'text_ai_credits_monthly' => 0,
    'pin_scheduling_monthly_limit' => 0, 'pin_scheduling_daily_limit' => 0,
    'pinterest_accounts_limit' => 0, 'websites_limit' => 0, 'upload_pins_limit' => 0,
    'bulk_scheduling_enabled' => 0, 'invite_team_members_limit' => 0,
    'auto_website_daily_pin_enabled' => 0, 'auto_article_enabled' => 0, 'single_article_writer_enabled' => 0,
    'cloud_storage_mb' => 0, 'sort_order' => 0, 'status' => 'active',
];

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1><?= $editId ? 'Edit Plan' : 'Create Plan' ?></h1><a href="plans" class="btn-secondary">← All Plans</a></div>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="POST">
    <div class="card">
        <h2>Plan Basics</h2>
        <div class="two-col">
            <div class="form-row">
                <label>Plan name</label>
                <input type="text" name="name" value="<?= e($f['name']) ?>" required>
            </div>
            <div class="form-row" style="align-self:end;">
                <label class="checkbox-row"><input type="checkbox" name="is_free" value="1" <?= !empty($f['is_free']) ? 'checked' : '' ?>> This is the Free Plan (only one allowed — enabling this on Save will turn it off on any other plan)</label>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Price monthly ($)</label>
                <input type="number" step="0.01" name="price_monthly" value="<?= e($f['price_monthly']) ?>">
            </div>
            <div class="form-row">
                <label>Sort order (lower shows first)</label>
                <input type="number" name="sort_order" value="<?= e($f['sort_order'] ?? 0) ?>">
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Discount % — monthly billing</label>
                <input type="number" step="0.01" name="discount_monthly" value="<?= e($f['discount_monthly']) ?>">
            </div>
            <div class="form-row">
                <label>Discount % — yearly billing</label>
                <input type="number" step="0.01" name="discount_yearly" value="<?= e($f['discount_yearly']) ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Short description</label>
            <textarea name="short_description" rows="2"><?= e($f['short_description']) ?></textarea>
        </div>
        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= ($f['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (visible on Upgrade page)</option>
                <option value="inactive" <?= ($f['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option>
            </select>
        </div>
    </div>

    <div class="card">
        <h2>Plan Tag</h2>
        <div class="two-col">
            <div class="form-row">
                <label>Tag</label>
                <select name="tag">
                    <option value="">None</option>
                    <?php foreach (PLAN_TAG_OPTIONS as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= ($f['tag'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Tag color</label>
                <select name="tag_color">
                    <?php foreach (PLAN_TAG_COLORS as $val => $hex): ?>
                        <option value="<?= e($val) ?>" data-hex="<?= e($hex) ?>" <?= ($f['tag_color'] ?? 'red') === $val ? 'selected' : '' ?>><?= e(ucfirst($val)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Buy Button</h2>
        <div class="two-col">
            <div class="form-row">
                <label>Button text</label>
                <input type="text" name="pay_button_text" value="<?= e($f['pay_button_text']) ?>">
            </div>
            <div class="form-row">
                <label>Position on card</label>
                <select name="buy_button_position">
                    <option value="top" <?= ($f['buy_button_position'] ?? '') === 'top' ? 'selected' : '' ?>>Top</option>
                    <option value="bottom" <?= ($f['buy_button_position'] ?? 'bottom') === 'bottom' ? 'selected' : '' ?>>Bottom</option>
                    <option value="both" <?= ($f['buy_button_position'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                </select>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Background color</label><input type="color" name="pay_button_bg" value="<?= e($f['pay_button_bg'] ?: '#e60023') ?>"></div>
            <div class="form-row"><label>Text color</label><input type="color" name="pay_button_text_color" value="<?= e($f['pay_button_text_color'] ?: '#ffffff') ?>"></div>
            <div class="form-row"><label>Border color</label><input type="color" name="pay_button_border_color" value="<?= e($f['pay_button_border_color'] ?: '#e60023') ?>"></div>
        </div>
    </div>

    <div class="card">
        <h2>Feature Limits</h2>
        <div class="two-col">
            <div class="form-row"><label>Image AI credits / month</label><input type="number" name="image_ai_credits_monthly" value="<?= (int)$f['image_ai_credits_monthly'] ?>"></div>
            <div class="form-row"><label>Text AI credits / month</label><input type="number" name="text_ai_credits_monthly" value="<?= (int)$f['text_ai_credits_monthly'] ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Pin scheduling / month</label>
                <input type="number" name="pin_scheduling_monthly_limit" value="<?= (int)($f['pin_scheduling_monthly_limit'] ?? 0) ?>" <?= is_null($f['pin_scheduling_monthly_limit'] ?? 0) ? 'disabled' : '' ?>>
                <label class="checkbox-row"><input type="checkbox" name="pin_scheduling_monthly_unlimited" value="1" <?= is_null($f['pin_scheduling_monthly_limit'] ?? 0) ? 'checked' : '' ?> onchange="this.closest('.form-row').querySelector('input[type=number]').disabled=this.checked;"> Unlimited</label>
            </div>
            <div class="form-row">
                <label>Pin scheduling / day</label>
                <input type="number" name="pin_scheduling_daily_limit" value="<?= (int)($f['pin_scheduling_daily_limit'] ?? 0) ?>" <?= is_null($f['pin_scheduling_daily_limit'] ?? 0) ? 'disabled' : '' ?>>
                <label class="checkbox-row"><input type="checkbox" name="pin_scheduling_daily_unlimited" value="1" <?= is_null($f['pin_scheduling_daily_limit'] ?? 0) ? 'checked' : '' ?> onchange="this.closest('.form-row').querySelector('input[type=number]').disabled=this.checked;"> Unlimited</label>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Pinterest accounts</label>
                <input type="number" name="pinterest_accounts_limit" value="<?= (int)($f['pinterest_accounts_limit'] ?? 0) ?>" <?= is_null($f['pinterest_accounts_limit'] ?? 0) ? 'disabled' : '' ?>>
                <label class="checkbox-row"><input type="checkbox" name="pinterest_accounts_unlimited" value="1" <?= is_null($f['pinterest_accounts_limit'] ?? 0) ? 'checked' : '' ?> onchange="this.closest('.form-row').querySelector('input[type=number]').disabled=this.checked;"> Unlimited</label>
            </div>
            <div class="form-row">
                <label>Websites</label>
                <input type="number" name="websites_limit" value="<?= (int)($f['websites_limit'] ?? 0) ?>" <?= is_null($f['websites_limit'] ?? 0) ? 'disabled' : '' ?>>
                <label class="checkbox-row"><input type="checkbox" name="websites_unlimited" value="1" <?= is_null($f['websites_limit'] ?? 0) ? 'checked' : '' ?> onchange="this.closest('.form-row').querySelector('input[type=number]').disabled=this.checked;"> Unlimited</label>
            </div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Uploaded pins</label>
                <input type="number" name="upload_pins_limit" value="<?= (int)($f['upload_pins_limit'] ?? 0) ?>" <?= is_null($f['upload_pins_limit'] ?? 0) ? 'disabled' : '' ?>>
                <label class="checkbox-row"><input type="checkbox" name="upload_pins_unlimited" value="1" <?= is_null($f['upload_pins_limit'] ?? 0) ? 'checked' : '' ?> onchange="this.closest('.form-row').querySelector('input[type=number]').disabled=this.checked;"> Unlimited</label>
            </div>
            <div class="form-row">
                <label>Invite team members (0 = can't invite)</label>
                <input type="number" name="invite_team_members_limit" value="<?= (int)$f['invite_team_members_limit'] ?>">
            </div>
        </div>
        <div class="form-row">
            <label>Cloud storage</label>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="number" name="cloud_storage_gb" value="<?= intdiv((int)$f['cloud_storage_mb'], 1024) ?>" style="width:100px;"> GB
                <input type="number" name="cloud_storage_mb_extra" value="<?= (int)$f['cloud_storage_mb'] % 1024 ?>" style="width:100px;"> MB
            </div>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="bulk_scheduling_enabled" value="1" <?= !empty($f['bulk_scheduling_enabled']) ? 'checked' : '' ?>> Bulk scheduling available</label>
        <label class="checkbox-row"><input type="checkbox" name="auto_website_daily_pin_enabled" value="1" <?= !empty($f['auto_website_daily_pin_enabled']) ? 'checked' : '' ?>> Auto website-to-daily-pin available</label>
        <label class="checkbox-row"><input type="checkbox" name="auto_article_enabled" value="1" <?= !empty($f['auto_article_enabled']) ? 'checked' : '' ?>> Auto article available</label>
        <label class="checkbox-row"><input type="checkbox" name="single_article_writer_enabled" value="1" <?= !empty($f['single_article_writer_enabled']) ? 'checked' : '' ?>> Single article writer available</label>
    </div>

    <div class="card">
        <h2>Feature List (shown on the pricing card)</h2>
        <div id="feature-rows">
            <?php $rows = $featureRows ?: [null]; foreach ($rows as $fr): ?>
            <div class="feature-row-item" style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px;">
                <div class="two-col">
                    <div class="form-row"><label>Text</label><input type="text" name="feature_text[]" value="<?= e($fr['text'] ?? '') ?>"></div>
                    <div class="form-row"><label>Tooltip (optional)</label><input type="text" name="feature_tooltip[]" value="<?= e($fr['tooltip'] ?? '') ?>"></div>
                </div>
                <div class="two-col">
                    <div class="form-row">
                        <label>Checkmark</label>
                        <select name="feature_checkmark_type[]">
                            <?php foreach (PLAN_CHECKMARK_TYPES as $val => $sym): ?>
                                <option value="<?= e($val) ?>" <?= ($fr['checkmark_type'] ?? 'check') === $val ? 'selected' : '' ?>><?= $sym ?> <?= e($val) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Font (optional)</label><input type="text" name="feature_font[]" value="<?= e($fr['font'] ?? '') ?>" placeholder="e.g. Poppins"></div>
                </div>
                <div class="two-col">
                    <div class="form-row"><label>Text size</label><input type="text" name="feature_text_size[]" value="<?= e($fr['text_size'] ?? '14px') ?>"></div>
                    <div class="form-row"><label>Text color</label><input type="color" name="feature_text_color[]" value="<?= e($fr['text_color'] ?? '#1a1a1a') ?>"></div>
                </div>
                <div class="two-col">
                    <div class="form-row"><label>Checkmark size</label><input type="text" name="feature_checkmark_size[]" value="<?= e($fr['checkmark_size'] ?? '16px') ?>"></div>
                    <div class="form-row"><label>Checkmark color</label><input type="color" name="feature_checkmark_color[]" value="<?= e($fr['checkmark_color'] ?? '#16a34a') ?>"></div>
                </div>
                <button type="button" class="btn-danger btn-small" onclick="this.closest('.feature-row-item').remove();">Remove row</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-secondary btn-small" id="add-feature-row">+ Add feature row</button>
    </div>

    <button type="submit" class="btn-primary"><?= $editId ? 'Save Changes' : 'Create Plan' ?></button>
</form>

<template id="feature-row-template">
    <div class="feature-row-item" style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px;">
        <div class="two-col">
            <div class="form-row"><label>Text</label><input type="text" name="feature_text[]"></div>
            <div class="form-row"><label>Tooltip (optional)</label><input type="text" name="feature_tooltip[]"></div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Checkmark</label>
                <select name="feature_checkmark_type[]">
                    <?php foreach (PLAN_CHECKMARK_TYPES as $val => $sym): ?><option value="<?= e($val) ?>"><?= $sym ?> <?= e($val) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row"><label>Font (optional)</label><input type="text" name="feature_font[]" placeholder="e.g. Poppins"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Text size</label><input type="text" name="feature_text_size[]" value="14px"></div>
            <div class="form-row"><label>Text color</label><input type="color" name="feature_text_color[]" value="#1a1a1a"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Checkmark size</label><input type="text" name="feature_checkmark_size[]" value="16px"></div>
            <div class="form-row"><label>Checkmark color</label><input type="color" name="feature_checkmark_color[]" value="#16a34a"></div>
        </div>
        <button type="button" class="btn-danger btn-small" onclick="this.closest('.feature-row-item').remove();">Remove row</button>
    </div>
</template>
<script>
document.getElementById('add-feature-row').addEventListener('click', function () {
    var tpl = document.getElementById('feature-row-template').content.cloneNode(true);
    document.getElementById('feature-rows').appendChild(tpl);
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
