<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'free-tools';
$pageTitle = 'Free Tools';

$tools = [
    ['name' => 'Pinterest Pin Maker', 'icon' => '📌'],
    ['name' => 'Pinterest Hashtag Generator', 'icon' => '#️⃣'],
    ['name' => 'Pinterest Title, Description Generator', 'icon' => '📝'],
    ['name' => 'Pinterest Bio Generator', 'icon' => '👤'],
    ['name' => 'Pinterest Board Name Generator', 'icon' => '🗂️'],
    ['name' => 'Pinterest Username Generator', 'icon' => '🔤'],
    ['name' => 'Pinterest Alt Text Generator', 'icon' => '🖼️'],
    ['name' => 'Pinterest Keyword Research Tool', 'icon' => '🔍'],
    ['name' => 'Pinterest Font Generator', 'icon' => '🔠'],
    ['name' => 'Etsy Tools', 'icon' => '🛍️'],
    ['name' => 'Etsy Tags Generator', 'icon' => '🏷️'],
    ['name' => 'Etsy Shop Announcement Generator', 'icon' => '📢'],
    ['name' => 'Etsy QR Code Generator', 'icon' => '🔳'],
];

include __DIR__ . '/includes/user-header.php';
?>
<div class="page-header">
    <h1>Free Tools</h1>
</div>

<div class="free-tools-grid">
    <?php foreach ($tools as $t): ?>
        <a href="coming-soon?feature=<?= urlencode($t['name']) ?>&active=free-tools" class="free-tool-btn">
            <span class="free-tool-icon"><?= $t['icon'] ?></span>
            <span><?= e($t['name']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
