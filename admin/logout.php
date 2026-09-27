<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_2fa_pending']);
// With a custom login address, go to the home page rather than revealing it.
redirect(admin_login_slug($pdo) !== '' ? rtrim(APP_URL, '/') . '/' : admin_login_url($pdo));
