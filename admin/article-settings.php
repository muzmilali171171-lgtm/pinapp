<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/model-pickers.php';
require_admin_login();

$activePage = 'article-settings';
$pageTitle = 'Article Write';

$settings = get_article_settings($pdo);
$success = false;
$error = null;

$textOptions = $pdo->query("SELECT * FROM ai_providers WHERE model_type = 'text' AND api_key IS NOT NULL AND api_key != ''")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$textProvider, $textModel] = admin_text_model_posted('text');
    [$imageProvider, $imageModel] = admin_image_model_posted('image');

    if (!$textProvider || !$textModel) {
        $error = 'Please choose a text platform and model.';
    } else {
        if ($settings) {
            $pdo->prepare("UPDATE article_settings SET text_provider = ?, text_model = ?, image_provider = ?, image_model = ? WHERE id = ?")
                ->execute([$textProvider, $textModel, $imageProvider, $imageModel, $settings['id']]);
        } else {
            $pdo->prepare("INSERT INTO article_settings (text_provider, text_model, image_provider, image_model) VALUES (?, ?, ?, ?)")
                ->execute([$textProvider, $textModel, $imageProvider, $imageModel]);
        }
        log_event($pdo, 'system', 'Article Write default settings updated by admin');
        $settings = get_article_settings($pdo);
        $success = true;
    }
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Article Write Settings</h1></div>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if (empty($textOptions)): ?>
    <div class="alert alert-info">No text model has an API key saved yet. Go to <a href="ai-api">Models</a> first and add at least one key.</div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <div class="bs-panel-title" style="margin-top:0;">Text <span class="muted" style="font-weight:400;">(writes article titles &amp; content)</span></div>
        <?php render_text_model_picker($pdo, 'text', $settings['text_provider'] ?? null, $settings['text_model'] ?? null, 'Default Text Model', null); ?>

        <div class="bs-panel-title">Images <span class="muted" style="font-weight:400;">(AI-generated images)</span></div>
        <?php render_image_model_picker($pdo, 'image', $settings['image_provider'] ?? null, $settings['image_model'] ?? null, 'Default Image Platform / Model', '-- None / web images only --'); ?>

        <button type="submit" class="btn-primary">Save Defaults</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
