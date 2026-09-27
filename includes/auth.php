<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect(rtrim(APP_URL, '/') . '/auth/login');
    }
    // A session can outlive the user row it points to (e.g. testing against a reset/reseeded
    // database while an old login cookie is still around) — without this check, every page that
    // reads $user['id'] after current_user() returns null crashes instead of just re-prompting
    // for login, since current_user() itself has no authority to redirect.
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            session_unset();
            session_destroy();
            redirect(rtrim(APP_URL, '/') . '/auth/login');
        }
    }
}
