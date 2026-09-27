<?php
/**
 * Contact Us: public settings (support email, WhatsApp, address) shown on
 * the /contact.php page, plus the submission inbox managed from
 * Admin -> Contacts.
 */

function contact_ensure_settings_row(PDO $pdo): int
{
    $id = $pdo->query("SELECT id FROM contact_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($id) return (int)$id;
    $pdo->exec("INSERT INTO contact_settings (support_email) VALUES (NULL)");
    return (int)$pdo->lastInsertId();
}

function get_contact_settings(PDO $pdo): array
{
    contact_ensure_settings_row($pdo);
    $row = $pdo->query("SELECT * FROM contact_settings ORDER BY id ASC LIMIT 1")->fetch();
    return $row ?: [];
}

/** A wa.me link from a saved WhatsApp number (digits only, any spacing/format accepted on save). */
function contact_whatsapp_link(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number);
    return $digits !== '' ? 'https://wa.me/' . $digits : '';
}

function contact_unread_count(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'new'")->fetchColumn();
}
