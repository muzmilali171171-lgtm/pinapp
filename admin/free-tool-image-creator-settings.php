<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/model-pickers.php';
require_admin_login();

$activePage = 'free-tool-image-creator';
$pageTitle = 'AI Image Creator';

$settings = get_article_settings($pdo);
$success = false;
$cloudflareCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE status = 'active'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$imageProvider, $imageModel] = admin_image_model_posted('image');
    if ($imageProvider === null) { $imageProvider = 'deepinfra'; $imageModel = 'black-forest-labs/FLUX-1-schnell'; }

    $fields = [
        'imagecreator_image_provider' => $imageProvider,
        'imagecreator_image_model' => $imageModel,
        'imagecreator_daily_limit' => max(1, (int)($_POST['daily_limit'] ?? 10)),
    ];

    if ($settings) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE article_settings SET $set WHERE id = ?")->execute([...array_values($fields), $settings['id']]);
    } else {
        $cols = implode(', ', array_keys($fields));
        $ph = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO article_settings ($cols) VALUES ($ph)")->execute(array_values($fields));
    }
    log_event($pdo, 'system', 'AI Image Creator settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}


include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>AI Image Creator — Settings</h1></div>
<p class="muted">Controls the public, no-login tool at <code>/free-tools/ai-image-creater</code>.</p>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="card">
    <form method="POST">
        <div class="bs-panel-title" style="margin-top:0;">Image Generation</div>
        <?php render_image_model_picker($pdo, 'image', $settings['imagecreator_image_provider'] ?? 'deepinfra', $settings['imagecreator_image_model'] ?? null, 'Platform / Model'); ?>

        <div class="bs-panel-title">Usage Limit</div>
        <div class="form-row" style="max-width:260px;">
            <label>Free Images Per Visitor, Per Day</label>
            <input type="number" name="daily_limit" min="1" value="<?= (int)($settings['imagecreator_daily_limit'] ?? 10) ?>">
        </div>

        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
