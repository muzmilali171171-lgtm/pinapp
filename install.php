<?php
/**
 * One-time installer.
 * Visit this file in your browser (e.g. https://videoconvertly.com/install.php),
 * enter your Hostinger MySQL database details, and it will:
 *   1. Write config/config.php for you
 *   2. Create every table from database/schema.sql automatically
 *   3. Create the default admin login (username: admin / password: 1234)
 *
 * Delete this file (or it will refuse to run again) once installation is done.
 */

session_start();
$configPath = __DIR__ . '/config/config.php';
$alreadyInstalled = file_exists($configPath);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$alreadyInstalled || isset($_POST['force_reinstall']))) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $appName = trim($_POST['app_name'] ?? 'Pinterest Auto Scheduler');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Karachi');

    if ($dbName === '' || $dbUser === '' || $appUrl === '') {
        $errors[] = 'Database name, database user, and site URL are required.';
    }

    if (empty($errors)) {
        try {
            // Connect WITHOUT a database first so we can create it if it doesn't exist.
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Run schema.sql — auto-creates every table.
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            // Strip full-line "-- comment" lines first so a semicolon inside a
            // comment can't be mistaken for the end of a CREATE TABLE statement.
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            // Seed the default admin login, only if admin_users is empty.
            $count = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            if ($count == 0) {
                $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute(['admin', 'admin@mail.com', password_hash('1234', PASSWORD_DEFAULT)]);
            }

            // Seed an empty pinterest_settings row so the admin panel has one row to update.
            $count2 = $pdo->query("SELECT COUNT(*) FROM pinterest_settings")->fetchColumn();
            if ($count2 == 0) {
                $pdo->exec("INSERT INTO pinterest_settings (client_id, client_secret, redirect_uri) VALUES (NULL, NULL, NULL)");
            }

            // Seed the SEO settings row (indexing stays OFF until the admin enables it).
            $count3 = $pdo->query("SELECT COUNT(*) FROM seo_settings")->fetchColumn();
            if ($count3 == 0) {
                $stmt = $pdo->prepare("INSERT INTO seo_settings (meta_title, app_name, app_url, publisher_name, publisher_url, robots_index) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$appName, $appName, rtrim($appUrl, '/') . '/', $appName, rtrim($appUrl, '/') . '/']);
            }

            // Folder for favicon / logo uploads.
            if (!is_dir(__DIR__ . '/uploads/seo')) {
                @mkdir(__DIR__ . '/uploads/seo', 0775, true);
            }

            // Seed one example Blog Post (Admin -> Blog Post) so /growth-guide has
            // content from day one. blog_categories was already seeded by schema.sql.
            $growthCatId = $pdo->query("SELECT id FROM blog_categories WHERE slug = 'growth-guide'")->fetchColumn();
            $seedContentPath = __DIR__ . '/database/seed-pinterest-growth.html';
            if ($growthCatId && is_file($seedContentPath)) {
                $pdo->prepare(
                    "INSERT INTO blog_posts
                    (title, subtitle, meta_title, meta_description, slug, category_id, feature_image, content, editor_mode, status, author, published_at)
                    VALUES (?, ?, ?, ?, 'pinterest-growth', ?, NULL, ?, 'html', 'published', ?, NOW())"
                )->execute([
                    'How to Go Viral on Pinterest in 2026 (The Complete Growth Playbook)',
                    'A step-by-step, data-backed guide to turning your website content into consistent Pinterest traffic — no ad spend required.',
                    'How to Go Viral on Pinterest in 2026 — Complete Growth Guide',
                    'A complete, data-backed playbook for growing Pinterest traffic in 2026: design, SEO, publishing cadence, and the exact 8-step system to break out of the sandbox and scale.',
                    (int)$growthCatId,
                    file_get_contents($seedContentPath),
                    'AutomatedPin Team',
                ]);
            }

            // Seed the rest of the default Growth Guide series the same way —
            // see database/blog-seed-manifest.php.
            require_once __DIR__ . '/includes/blog_functions.php';
            blog_seed_default_posts($pdo, 'growth-guide', 'AutomatedPin Team');
            blog_seed_default_posts($pdo, 'compare', 'AutomatedPin Team', 'blog-compare-manifest.php', 'seed-compare');

            // Write config.php
            $appSecret = bin2hex(random_bytes(32));
            $configContents = "<?php\n"
                . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                . "define('APP_URL', " . var_export($appUrl, true) . ");\n"
                . "define('APP_NAME', " . var_export($appName, true) . ");\n"
                . "define('APP_SECRET', " . var_export($appSecret, true) . ");\n"
                . "date_default_timezone_set(" . var_export($timezone, true) . ");\n";

            if (!is_dir(__DIR__ . '/config')) {
                mkdir(__DIR__ . '/config', 0755, true);
            }
            file_put_contents($configPath, $configContents);

            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Install — Pinterest Auto Scheduler</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card" style="max-width:620px;">
    <h1>Installer</h1>

    <?php if ($alreadyInstalled && !$success): ?>
        <div class="alert alert-info">
            A <code>config/config.php</code> already exists, so the app looks installed.
            If you really want to re-run the installer (this will re-check/re-create tables,
            but will NOT delete existing data), submit the form below with "Force reinstall" checked.
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Installed successfully!</strong><br>
            Database tables created and <code>config/config.php</code> written.<br><br>
            Admin panel login: <strong>admin</strong> / <strong>1234</strong> — go to
            <a href="admin/login">admin/login.php</a> and change the password from there is recommended
            (or update the <code>admin_users</code> table directly).<br><br>
            <strong>Important:</strong> delete <code>install.php</code> from your server now for security.<br><br>
            Next step: log into the admin panel and enter your Pinterest App's Client ID, Client Secret
            and Redirect URL under <em>Pinterest Settings</em>.
        </div>
    <?php else: ?>
        <form method="POST">
            <?php if ($alreadyInstalled): ?>
                <label class="checkbox-row"><input type="checkbox" name="force_reinstall" value="1"> Force reinstall</label>
            <?php endif; ?>

            <label>Database Host</label>
            <input type="text" name="db_host" value="localhost" required>

            <label>Database Name</label>
            <input type="text" name="db_name" placeholder="u123456789_pinterest" required>

            <label>Database User</label>
            <input type="text" name="db_user" placeholder="u123456789_dbuser" required>

            <label>Database Password</label>
            <input type="password" name="db_pass">

            <label>Site URL (no trailing slash)</label>
            <input type="text" name="app_url" placeholder="https://videoconvertly.com" required>

            <label>App Name</label>
            <input type="text" name="app_name" value="VideoConvertly Pinterest Scheduler">

            <label>Timezone</label>
            <input type="text" name="timezone" value="Asia/Karachi">

            <button type="submit" class="btn-primary">Install</button>
        </form>
        <p class="muted">On Hostinger: hPanel → Databases → MySQL Databases. Create a database + user there first,
        then enter those exact details above — this installer will create the database if it does not exist yet
        (as long as the DB user has privileges) and will create every table automatically.</p>
    <?php endif; ?>
</div>
</body>
</html>
