<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/admin_security.php';

function current_admin(PDO $pdo): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_admin_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        global $pdo;
        // With a custom login address, never reveal it — show the normal 404 page instead.
        if ($pdo instanceof PDO && admin_login_slug($pdo) !== '') {
            http_response_code(404);
            require __DIR__ . '/../../404.php';
            exit;
        }
        redirect(rtrim(APP_URL, '/') . '/admin/login');
    }
}
