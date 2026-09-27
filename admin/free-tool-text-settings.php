<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/model-pickers.php';
require_admin_login();

$activePage = 'free-tool-text-settings';
$pageTitle = 'Free Tools — Text Generators';

$settings = get_article_settings($pdo);
$success = false;

$textOptions = $pdo->query("SELECT * FROM ai_providers WHERE model_type = 'text' AND api_key IS NOT NULL AND api_key != ''")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$textProvider, $textModel] = admin_text_model_posted('text');
    $fields = [
        'freetext_provider' => $textProvider,
        'freetext_model' => $textModel,
        'freetext_max_attempts' => max(1, (int)($_POST['max_attempts'] ?? 30)),
    ];

    if ($settings) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $pdo->prepare("UPDATE article_settings SET $set WHERE id = ?")->execute([...array_values($fields), $settings['id']]);
    } else {
        $cols = implode(', ', array_keys($fields));
        $ph = implode(', ', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO article_settings ($cols) VALUES ($ph)")->execute(array_values($fields));
    }
    log_event($pdo, 'system', 'Free Tools text-generator settings updated by admin');
    $settings = get_article_settings($pdo);
    $success = true;
}

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Free Tools — Text Generators</h1></div>
<p class="muted">Shared text model for the public, no-login <strong>Hashtag Generator</strong>, <strong>Title &amp; Description
Generator</strong>, <strong>Bio Generator</strong>, <strong>Board Name Generator</strong>, <strong>Username Generator</strong>,
<strong>Alt Text Generator</strong>, and <strong>Keyword Research Tool</strong>. The Alt Text Generator specifically needs a
model that supports image input (e.g. GPT-4o, or a vision-capable Claude/Gemini model) — a text-only model will error on that
tool but still work fine for the others.</p>
<?php if ($success): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<?php if (empty($textOptions)): ?>
    <div class="alert alert-info">No text model has an API key saved yet. Go to <a href="ai-api">Models</a> first and add at least one key.</div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <?php render_text_model_picker($pdo, 'text', $settings['freetext_provider'] ?? null, $settings['freetext_model'] ?? null, 'Text Model', null); ?>

        <div class="form-row" style="max-width:260px;">
            <label>Free Generations Per Visitor <span class="muted">(per tool)</span></label>
            <input type="number" name="max_attempts" min="1" value="<?= (int)($settings['freetext_max_attempts'] ?? 30) ?>">
        </div>

        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
