<?php
/**
 * Admin → Canva → Create New Template: the same Canva-style editor users have, for making pin templates.
 *   pin-template-editor          → new template (1000×1500)
 *   pin-template-editor?id=-12   → edit admin template #12 (negative ids = admin templates inside the editor)
 * Extra in admin mode (assets/js/admin-template-editor.js): a "Text type" panel for every text (Main title /
 * Only number / CTA / Website / Static) and Publish (name, category, priority, tags).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pin_template_registry.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();
at_ensure_schema($pdo);

$boot = ['id' => 0, 'template' => 0, 'width' => 1000, 'height' => 1500, 'title' => 'New pin template'];
$meta = ['name' => '', 'category' => '', 'priority' => 5, 'tags' => '', 'status' => 'draft'];
$id = abs((int)($_GET['id'] ?? 0));
if ($id) {
    $st = $pdo->prepare("SELECT * FROM admin_pin_templates WHERE id = ?");
    $st->execute([$id]);
    if ($row = $st->fetch()) {
        $boot = ['id' => -$id, 'template' => 0, 'width' => (int)$row['width'], 'height' => (int)$row['height'], 'title' => $row['name']];
        $meta = ['name' => $row['name'], 'category' => $row['category'], 'priority' => (int)$row['priority'], 'tags' => $row['tags'], 'status' => $row['status']];
    }
} else {
    if (!empty($_GET['w'])) $boot['width'] = max(200, min(4000, (int)$_GET['w']));
    if (!empty($_GET['h'])) $boot['height'] = max(200, min(6000, (int)$_GET['h']));
}
$v = function (string $rel) { return @filemtime(__DIR__ . '/../' . $rel) ?: time(); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pin Template Editor — <?= e(SITE_BRAND) ?> Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Alfa+Slab+One&family=Amatic+SC:wght@700&family=Anton&family=Archivo+Black&family=Bangers&family=Bebas+Neue&family=Caveat:wght@400;700&family=Cinzel:wght@400;700&family=DM+Serif+Display:ital@0;1&family=Dancing+Script:wght@400;700&family=Fredoka:wght@400;700&family=Great+Vibes&family=Josefin+Sans:ital,wght@0,400;0,700;1,400&family=Kaushan+Script&family=Lato:ital,wght@0,400;0,900;1,400&family=Lobster&family=Luckiest+Guy&family=Merriweather:ital,wght@0,400;0,900;1,400&family=Montserrat:ital,wght@0,400;0,800;1,400;1,800&family=Nunito:ital,wght@0,400;0,900;1,400&family=Open+Sans:ital,wght@0,400;0,800;1,400&family=Oswald:wght@400;700&family=Pacifico&family=Permanent+Marker&family=Playfair+Display:ital,wght@0,400;0,900;1,400;1,900&family=Poppins:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Quicksand:wght@400;700&family=Raleway:ital,wght@0,400;0,800;1,400&family=Righteous&family=Roboto:ital,wght@0,400;0,900;1,400&family=Sacramento&family=Satisfy&family=Shadows+Into+Light&family=Titan+One&display=swap">
<link rel="stylesheet" href="../assets/css/design-editor.css?v=<?= $v('assets/css/design-editor.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-template-editor.css?v=<?= $v('assets/css/admin-template-editor.css') ?>">
</head>
<body class="de-body at-admin">

<?php include __DIR__ . '/../includes/design_editor_markup.php'; ?>

<!-- Template info bar + text type panel (admin only) -->
<div class="at-info" id="atInfo"></div>
<div class="at-role" id="atRole" hidden>
    <div class="at-role-head">Text type <span class="at-role-hint">— what goes in this text on each pin</span></div>
    <div class="at-role-list">
        <?php foreach (AT_ROLES as $k => $label): ?>
            <label><input type="radio" name="atRole" value="<?= e($k) ?>"> <span><?= e($label) ?></span></label>
        <?php endforeach; ?>
    </div>
    <p class="at-role-note">Main title = the pin's title · Only number = the number from the title (makes this a numbered template) · CTA = button text · Website = the site's domain · Static = stays exactly as written.</p>
</div>

<!-- Publish -->
<div class="de-modal" id="atPublishModal" hidden>
    <div class="de-modal-box at-pub">
        <h3>🚀 Publish template</h3>
        <div id="atPubError"></div>
        <label class="at-field"><span>Template name</span><input type="text" id="atName" class="de-input" maxlength="120" value="<?= e($meta['name'] ?: $boot['title']) ?>"></label>
        <div class="at-two">
            <label class="at-field"><span>Priority <small>(higher = picked more often, shown first)</small></span>
                <select id="atPriority" class="de-input">
                    <?php for ($i = 10; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int)$meta['priority'] === $i ? 'selected' : '' ?>><?= $i ?><?= $i === 10 ? ' — highest' : ($i === 5 ? ' — normal' : ($i === 1 ? ' — lowest' : '')) ?></option><?php endfor; ?>
                </select>
            </label>
            <label class="at-field"><span>Category <small>(AI Auto uses it for matching titles)</small></span>
                <select id="atCategory" class="de-input">
                    <option value="">Any category (fits every title)</option>
                    <?php foreach (pin_template_categories_all() as $c): ?><option value="<?= e($c) ?>" <?= $meta['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="at-field"><span>Tags</span>
            <div class="at-tags" id="atTags">
                <?php $cur = array_map('trim', explode(',', (string)$meta['tags']));
                foreach (AT_TAG_PRESETS as $t): ?>
                    <label><input type="checkbox" value="<?= e($t) ?>" <?= in_array($t, $cur, true) ? 'checked' : '' ?>> <?= e($t) ?></label>
                <?php endforeach; ?>
            </div>
            <input type="text" id="atTagCustom" class="de-input" placeholder="Other tags, comma separated (e.g. Summer, Minimal)"
                   value="<?= e(implode(', ', array_diff(array_filter($cur), AT_TAG_PRESETS))) ?>">
        </div>
        <div class="at-summary" id="atSummary"></div>
        <label class="de-check"><input type="checkbox" id="atActive" <?= $meta['status'] === 'inactive' ? '' : 'checked' ?>> Active — users and AI Auto can use it right away</label>
        <div class="de-modal-actions">
            <button type="button" class="de-btn" data-close>Cancel</button>
            <span class="de-spacer"></span>
            <button type="button" class="de-btn de-primary" id="atPublishGo">🚀 Publish now</button>
        </div>
        <div id="atPubDone" hidden class="at-done">
            <img id="atPubPreview" alt="Template preview">
            <div>
                <strong>Published!</strong>
                <p>This is how the template looks on a real pin (drawn by the server with a sample photo and title).
                It now shows in Pin Templates &amp; Styles, the Classic Wizard and AI Auto.</p>
                <a class="de-btn" href="pin-templates">← All templates</a>
                <a class="de-btn" href="pin-template-editor">+ New template</a>
            </div>
        </div>
    </div>
</div>

<script>
window.DE_BOOT = <?= json_encode($boot) ?>;
window.DE_AJAX = 'ajax-pin-templates';
window.DE_BASE = '../';
window.DE_EDITOR_URL = 'pin-template-editor';
window.AT_META = <?= json_encode($meta) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script charset="utf-8" src="../assets/js/design-elements-data.js?v=<?= $v('assets/js/design-elements-data.js') ?>"></script>
<script src="../assets/js/design-editor.js?v=<?= $v('assets/js/design-editor.js') ?>"></script>
<script src="../assets/js/admin-template-editor.js?v=<?= $v('assets/js/admin-template-editor.js') ?>"></script>
</body>
</html>
