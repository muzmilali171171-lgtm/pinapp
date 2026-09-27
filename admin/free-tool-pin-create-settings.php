<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/model-pickers.php';
require_admin_login();

$activePage = 'free-tool-pin-create';
$pageTitle = 'AI Pinterest Pin Create';

$settings = get_article_settings($pdo);
$success = false;
$cloudflareCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE status = 'active'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$imageProvider, $imageModel] = admin_image_model_posted('image');
    if ($imageProvider === null) { $imageProvider = 'deepinfra'; $imageModel = 'black-forest-labs/FLUX-1-schnell'; }

    $logoPath = $settings['pincreate_watermark_logo_path'] ?? null;
    if (!empty($_FILES['watermark_logo']['tmp_name']) && $_FILES['watermark_logo']['error'] === UPLOAD_ERR_OK) {
        $destDir = __DIR__ . '/../uploads/branding/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['watermark_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $filename = 'watermark_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['watermark_logo']['tmp_name'], $destDir . $filename)) {
                $logoPath = 'uploads/branding/' . $filename;
            }
        }
    }
    if (!empty($_POST['remove_logo'])) {
        $logoPath = null;
    }

    $fields = [
        'pincreate_image_provider' => $imageProvider,
        'pincreate_image_model' => $imageModel,
        'pincreate_max_pins' => max(1, (int)($_POST['max_pins'] ?? 20)),
        'pincreate_watermark_text' => trim($_POST['watermark_text'] ?? '') ?: null,
        'pincreate_watermark_logo_path' => $logoPath,
    ];

    if ($settings) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE article_settings SET $set WHERE id = ?")->execute([...array_values($fields), $settings['id']]);
    } else {
        $cols = implode(', ', array_keys($fields));
        $ph = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO article_settings ($cols) VALUES ($ph)")->execute(array_values($fields));
    }
    log_event($pdo, 'system', 'AI Pinterest Pin Create settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}


include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>AI Pinterest Pin Create — Settings</h1></div>
<p class="muted">Controls the public, no-login tool at <code>/free-tools/ai-pinterest-pin-create</code>.</p>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <div class="bs-panel-title" style="margin-top:0;">Image Generation</div>
        <?php render_image_model_picker($pdo, 'image', $settings['pincreate_image_provider'] ?? 'deepinfra', $settings['pincreate_image_model'] ?? null, 'Platform / Model'); ?>

        <div class="bs-panel-title">Usage Limit</div>
        <div class="form-row" style="max-width:260px;">
            <label>Free Pins Per Visitor</label>
            <input type="number" name="max_pins" min="1" value="<?= (int)($settings['pincreate_max_pins'] ?? 20) ?>">
        </div>

        <div class="bs-panel-title">Watermark</div>
        <p class="muted">Applied to every free (logged-out) pin. Upload a logo to use it instead of text.</p>
        <div class="form-row">
            <label>Watermark Text <span class="muted">(used when no logo is set)</span></label>
            <input type="text" name="watermark_text" value="<?= e($settings['pincreate_watermark_text'] ?? SITE_BRAND) ?>">
        </div>
        <div class="form-row">
            <label>Watermark Logo</label>
            <input type="file" name="watermark_logo" accept=".png,.jpg,.jpeg,.webp">
            <?php if (!empty($settings['pincreate_watermark_logo_path'])): ?>
                <p class="muted">Current: <img src="../<?= e($settings['pincreate_watermark_logo_path']) ?>" style="height:28px;vertical-align:middle;margin-left:6px;">
                    <label style="margin-left:10px;"><input type="checkbox" name="remove_logo" value="1"> Remove logo (use text instead)</label>
                </p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
