<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/footer_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'footer-settings';
$pageTitle = 'Footer Settings';
$errors = [];

footer_ensure_defaults($pdo);
$rowId = footer_ensure_settings_row($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------------------------------------------------------- branding/social
    if ($action === 'save_branding') {
        $logo = footer_handle_upload('logo_file', $errors);
        $fields = [
            'logo_text' => mb_substr(trim($_POST['logo_text'] ?? ''), 0, 100),
            'description' => mb_substr(trim($_POST['description'] ?? ''), 0, 1000),
            'footer_text' => trim($_POST['footer_text'] ?? ''),
        ];
        foreach (array_keys(footer_social_platforms()) as $key) {
            $fields[$key] = mb_substr(trim($_POST[$key] ?? ''), 0, 500);
        }
        if ($logo) $fields['logo_path'] = $logo;
        if (!empty($_POST['remove_logo'])) $fields['logo_path'] = '';

        if (!$errors) {
            $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
            $pdo->prepare("UPDATE footer_settings SET $set WHERE id = ?")
                ->execute([...array_values($fields), $rowId]);
            log_event($pdo, 'system', 'Admin updated footer branding/social settings');
            redirect('footer-settings?saved=1');
        }

    // ------------------------------------------------------- rename a column
    } elseif ($action === 'save_column_heading') {
        $columnId = (int)($_POST['column_id'] ?? 0);
        $heading = mb_substr(trim($_POST['heading'] ?? ''), 0, 100);
        if ($columnId && $heading !== '') {
            $pdo->prepare("UPDATE footer_menu_columns SET heading = ? WHERE id = ?")->execute([$heading, $columnId]);
            log_event($pdo, 'system', 'Admin renamed a footer menu column');
        }
        redirect('footer-settings?saved=1');

    // --------------------------------------------------- add/update an item
    } elseif ($action === 'save_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $columnId = (int)($_POST['column_id'] ?? 0);
        $label = mb_substr(trim($_POST['label'] ?? ''), 0, 150);
        $url = mb_substr(trim($_POST['url'] ?? ''), 0, 500);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($columnId && $label !== '' && $url !== '') {
            if ($itemId > 0) {
                $pdo->prepare("UPDATE footer_menu_items SET label = ?, url = ?, sort_order = ? WHERE id = ? AND column_id = ?")
                    ->execute([$label, $url, $sortOrder, $itemId, $columnId]);
            } else {
                if ($sortOrder === 0) {
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM footer_menu_items WHERE column_id = ?");
                    $stmt->execute([$columnId]);
                    $sortOrder = (int)$stmt->fetchColumn() + 1;
                }
                $pdo->prepare("INSERT INTO footer_menu_items (column_id, label, url, sort_order) VALUES (?, ?, ?, ?)")
                    ->execute([$columnId, $label, $url, $sortOrder]);
            }
            log_event($pdo, 'system', 'Admin saved a footer menu item');
        } else {
            $errors[] = 'Label and link are both required.';
        }
        if (!$errors) redirect('footer-settings?saved=1');

    // ------------------------------------------------------------ delete item
    } elseif ($action === 'delete_item') {
        $pdo->prepare("DELETE FROM footer_menu_items WHERE id = ?")->execute([(int)($_POST['item_id'] ?? 0)]);
        log_event($pdo, 'system', 'Admin deleted a footer menu item');
        redirect('footer-settings?saved=1');
    }
}

$settings = get_footer_settings($pdo);
$columns = get_footer_columns($pdo);

$editItem = null;
if (!empty($_GET['edit_item'])) {
    $stmt = $pdo->prepare("SELECT * FROM footer_menu_items WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_item']]);
    $editItem = $stmt->fetch() ?: null;
}

$v = static fn(string $k, $default = '') => e((string)($settings[$k] ?? $default));

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Footer Settings</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
    <h2>Branding &amp; Social Links</h2>
    <p class="muted">Shown in the left column of the footer on every public page.</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">

        <div class="two-col">
            <div class="form-row">
                <label>Logo Text <span class="muted">(used if no logo image is uploaded)</span></label>
                <input type="text" name="logo_text" maxlength="100" value="<?= $v('logo_text') ?>" placeholder="<?= e(APP_NAME) ?>">
            </div>
            <div class="form-row">
                <label>Logo Image</label>
                <input type="file" name="logo_file" accept="image/*">
                <?php if (!empty($settings['logo_path'])): ?>
                    <p style="margin:8px 0 0;"><img src="<?= e(footer_url($settings['logo_path'])) ?>" alt="" style="height:36px;border:1px solid var(--border);border-radius:6px;padding:4px;background:#fff;"></p>
                    <label class="checkbox-row"><input type="checkbox" name="remove_logo" value="1"> Remove current logo image</label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <label>Short Description</label>
            <textarea name="description" rows="3" maxlength="1000" placeholder="Automate Pinterest marketing for your website..."><?= $v('description') ?></textarea>
        </div>

        <h3 style="margin-top:22px;font-size:15px;">Social Media Links</h3>
        <div class="two-col">
            <?php foreach (footer_social_platforms() as $key => $label): ?>
                <div class="form-row">
                    <label><?= e($label) ?></label>
                    <input type="url" name="<?= e($key) ?>" value="<?= $v($key) ?>" placeholder="https://...">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-row">
            <label>Footer Text <span class="muted">(bottom line — leave blank for the default copyright line)</span></label>
            <textarea name="footer_text" rows="2"><?= $v('footer_text') ?></textarea>
        </div>

        <button type="submit" class="btn-primary">Save Branding</button>
    </form>
</div>

<?php foreach ($columns as $col): ?>
<div class="card">
    <h2>
        <form method="POST" style="display:inline-flex;gap:8px;align-items:center;">
            <input type="hidden" name="action" value="save_column_heading">
            <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
            <input type="text" name="heading" value="<?= e($col['heading']) ?>" style="font-size:16px;font-weight:700;padding:6px 10px;max-width:240px;">
            <button type="submit" class="btn-secondary btn-small">Rename</button>
        </form>
    </h2>

    <?php if (!$col['items']): ?>
        <p class="muted">No menu items yet — this column shows just its heading on the site. Add one below.</p>
    <?php else: ?>
    <table>
        <tr><th>Label</th><th>Link</th><th>Order</th><th></th></tr>
        <?php foreach ($col['items'] as $item): ?>
        <tr>
            <td><?= e($item['label']) ?></td>
            <td class="muted"><?= e($item['url']) ?></td>
            <td><?= (int)$item['sort_order'] ?></td>
            <td style="white-space:nowrap;">
                <a href="?edit_item=<?= (int)$item['id'] ?>#col-<?= (int)$col['id'] ?>" class="btn-secondary btn-small">Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this menu item?');">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h3 id="col-<?= (int)$col['id'] ?>" style="margin-top:18px;font-size:14px;">
        <?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? 'Edit Menu Item' : 'Add Menu Item' ?>
    </h3>
    <form method="POST">
        <input type="hidden" name="action" value="save_item">
        <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
        <input type="hidden" name="item_id" value="<?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? (int)$editItem['id'] : 0 ?>">
        <div class="two-col">
            <div class="form-row">
                <label>Label</label>
                <input type="text" name="label" maxlength="150" value="<?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? e($editItem['label']) : '' ?>" placeholder="Pricing">
            </div>
            <div class="form-row">
                <label>Link <span class="muted">(internal path like <code>pricing</code>, or a full https:// URL)</span></label>
                <input type="text" name="url" maxlength="500" value="<?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? e($editItem['url']) : '' ?>" placeholder="pricing">
            </div>
        </div>
        <div class="form-row" style="max-width:160px;">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? (int)$editItem['sort_order'] : 0 ?>">
        </div>
        <button type="submit" class="btn-primary"><?= ($editItem && (int)$editItem['column_id'] === (int)$col['id']) ? 'Update Item' : 'Add Item' ?></button>
        <?php if ($editItem && (int)$editItem['column_id'] === (int)$col['id']): ?><a href="footer-settings#col-<?= (int)$col['id'] ?>" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
