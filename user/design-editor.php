<?php
/**
 * Custom Design editor — Canva-style drag & drop editor (Fabric.js).
 *   design-editor              → new blank design (1000×1500)
 *   design-editor?id=12        → edit your design #12
 *   design-editor?template=34  → start a new design from published template #34
 *   design-editor?w=1080&h=1920 → new blank design at that size
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Shared design link (design-share?t=…): logged-in visitors open it straight in the editor
// (their own copy, or the original for its owner). Not logged in yet → back to the preview page.
if (!empty($_GET['shared'])) {
    $shared = design_by_share_token($pdo, (string)$_GET['shared']);
    if (!$shared) redirect('designs');
    if (empty($_SESSION['user_id'])) redirect(design_share_url($shared['share_token']));
}
require_login();
$user = current_user($pdo);
if (!empty($shared)) redirect('design-editor?id=' . design_open_shared($pdo, $shared, (int)$user['id']));

$boot = ['id' => 0, 'template' => 0, 'width' => 1000, 'height' => 1500, 'title' => 'Untitled design'];
if (!empty($_GET['id'])) {
    $row = design_row_for_user($pdo, (int)$_GET['id'], (int)$user['id']);
    if ($row) $boot = ['id' => (int)$row['id'], 'template' => 0, 'width' => (int)$row['width'], 'height' => (int)$row['height'], 'title' => $row['title']];
} elseif (!empty($_GET['template'])) {
    $boot['template'] = (int)$_GET['template'];
} else {
    if (!empty($_GET['w'])) $boot['width'] = max(50, min(8000, (int)$_GET['w']));
    if (!empty($_GET['h'])) $boot['height'] = max(50, min(8000, (int)$_GET['h']));
}
$v = function (string $rel) { return @filemtime(__DIR__ . '/../' . $rel) ?: time(); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Design Editor — <?= e(SITE_BRAND) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Alfa+Slab+One&family=Amatic+SC:wght@700&family=Anton&family=Archivo+Black&family=Bangers&family=Bebas+Neue&family=Caveat:wght@400;700&family=Cinzel:wght@400;700&family=DM+Serif+Display:ital@0;1&family=Dancing+Script:wght@400;700&family=Fredoka:wght@400;700&family=Great+Vibes&family=Josefin+Sans:ital,wght@0,400;0,700;1,400&family=Kaushan+Script&family=Lato:ital,wght@0,400;0,900;1,400&family=Lobster&family=Luckiest+Guy&family=Merriweather:ital,wght@0,400;0,900;1,400&family=Montserrat:ital,wght@0,400;0,800;1,400;1,800&family=Nunito:ital,wght@0,400;0,900;1,400&family=Open+Sans:ital,wght@0,400;0,800;1,400&family=Oswald:wght@400;700&family=Pacifico&family=Permanent+Marker&family=Playfair+Display:ital,wght@0,400;0,900;1,400;1,900&family=Poppins:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Quicksand:wght@400;700&family=Raleway:ital,wght@0,400;0,800;1,400&family=Righteous&family=Roboto:ital,wght@0,400;0,900;1,400&family=Sacramento&family=Satisfy&family=Shadows+Into+Light&family=Titan+One&display=swap">
<link rel="stylesheet" href="../assets/css/design-editor.css?v=<?= $v('assets/css/design-editor.css') ?>">
</head>
<body class="de-body">

<?php include __DIR__ . '/../includes/design_editor_markup.php'; ?>

<script>
window.DE_BOOT = <?= json_encode($boot) ?>;
window.DE_AJAX = 'ajax-design';
window.DE_BASE = '../';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script charset="utf-8" src="../assets/js/design-elements-data.js?v=<?= $v('assets/js/design-elements-data.js') ?>"></script>
<script src="../assets/js/design-editor.js?v=<?= $v('assets/js/design-editor.js') ?>"></script>
</body>
</html>
