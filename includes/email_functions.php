<?php
/**
 * Outgoing system email — password reset, email verification, email-change notices.
 * Settings (SMTP host/port/user/pass, from name/email, which emails are sent) are stored
 * in the generic platform_settings key-value store under email_* keys, edited from
 * Admin → Email Setting.
 */

function email_settings_get(PDO $pdo): array
{
    return [
        'smtp_enabled' => platform_setting($pdo, 'email_smtp_enabled', '0') === '1',
        'smtp_host' => platform_setting($pdo, 'email_smtp_host', ''),
        'smtp_port' => (int)platform_setting($pdo, 'email_smtp_port', '587'),
        'smtp_username' => platform_setting($pdo, 'email_smtp_username', ''),
        'smtp_password' => platform_setting($pdo, 'email_smtp_password', ''),
        'smtp_encryption' => platform_setting($pdo, 'email_smtp_encryption', 'tls'), // tls | ssl | none
        'from_name' => platform_setting($pdo, 'email_from_name', defined('APP_NAME') ? APP_NAME : 'App'),
        'from_email' => platform_setting($pdo, 'email_from_email', ''),
        'mailchimp_api_key' => platform_setting($pdo, 'email_mailchimp_api_key', ''),
        'mailchimp_audience_id' => platform_setting($pdo, 'email_mailchimp_audience_id', ''),
        'password_reset_enabled' => platform_setting($pdo, 'email_password_reset_enabled', '1') === '1',
        'verification_enabled' => platform_setting($pdo, 'email_verification_enabled', '0') === '1',
        'email_change_notify_enabled' => platform_setting($pdo, 'email_change_notify_enabled', '1') === '1',
    ];
}

/**
 * Sends one plain-ish HTML email. Uses the admin's SMTP settings if smtp_enabled + host are
 * set, otherwise falls back to PHP's built-in mail() (works out of the box on most cPanel/
 * Hostinger shared hosting, but is more likely to land in spam without SPF/DKIM configured).
 * Returns ['ok'=>bool,'error'=>?string].
 */
function send_app_email(PDO $pdo, string $to, string $subject, string $htmlBody): array
{
    $settings = email_settings_get($pdo);
    $fromEmail = $settings['from_email'] !== '' ? $settings['from_email'] : ('no-reply@' . preg_replace('#^https?://#', '', rtrim(defined('APP_URL') ? APP_URL : '', '/')));
    $fromName = $settings['from_name'] !== '' ? $settings['from_name'] : (defined('APP_NAME') ? APP_NAME : 'App');

    if ($settings['smtp_enabled'] && $settings['smtp_host'] !== '') {
        return smtp_send_mail($settings, $to, $subject, $htmlBody, $fromEmail, $fromName);
    }

    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . mb_encode_mimeheader($fromName) . " <$fromEmail>\r\n";
    $ok = @mail($to, mb_encode_mimeheader($subject), $htmlBody, $headers);
    return ['ok' => $ok, 'error' => $ok ? null : 'PHP mail() failed to send. Configure SMTP under Admin → Email Setting instead.'];
}

/**
 * Minimal dependency-free SMTP client (AUTH LOGIN, STARTTLS/SSL) — enough for Gmail SMTP,
 * Hostinger's mail servers, or any standard SMTP provider. Not a full RFC implementation
 * (no chunked attachments, no DKIM signing) but handles a plain HTML email reliably.
 */
function smtp_send_mail(array $s, string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName): array
{
    $host = $s['smtp_encryption'] === 'ssl' ? 'ssl://' . $s['smtp_host'] : $s['smtp_host'];
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($host . ':' . $s['smtp_port'], $errno, $errstr, 15);
    if (!$fp) return ['ok' => false, 'error' => "Could not connect to SMTP server: $errstr"];

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $write = function (string $cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $read(); // greeting
    $write('EHLO ' . (parse_url(defined('APP_URL') ? APP_URL : 'http://localhost', PHP_URL_HOST) ?: 'localhost'));
    $read();

    if ($s['smtp_encryption'] === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (strpos($resp, '220') !== 0) { fclose($fp); return ['ok' => false, 'error' => 'STARTTLS was rejected by the server.']; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'TLS handshake failed.'];
        }
        $write('EHLO ' . (parse_url(defined('APP_URL') ? APP_URL : 'http://localhost', PHP_URL_HOST) ?: 'localhost'));
        $read();
    }

    if ($s['smtp_username'] !== '') {
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($s['smtp_username']));
        $read();
        $write(base64_encode($s['smtp_password']));
        $resp = $read();
        if (strpos($resp, '235') !== 0) { fclose($fp); return ['ok' => false, 'error' => 'SMTP authentication failed. Check the username/password (for Gmail, use an App Password).']; }
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $resp = $read();
    if (strpos($resp, '250') !== 0) { fclose($fp); return ['ok' => false, 'error' => 'Recipient was rejected by the SMTP server.']; }

    $write('DATA');
    $read();
    $headers = "From: " . mb_encode_mimeheader($fromName) . " <$fromEmail>\r\n"
        . "To: <$to>\r\n"
        . "Subject: " . mb_encode_mimeheader($subject) . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $body = str_replace("\r\n.\r\n", "\r\n..\r\n", $htmlBody); // dot-stuffing safety
    $write($headers . "\r\n" . $body . "\r\n.");
    $resp = $read();
    $write('QUIT');
    fclose($fp);

    if (strpos($resp, '250') !== 0) return ['ok' => false, 'error' => 'The SMTP server rejected the message.'];
    return ['ok' => true, 'error' => null];
}

/** Static setup guide text for the Admin → Email Setting page. */
function email_setup_guide(string $which): string
{
    if ($which === 'hostinger') {
        return '<p>To send email from your own domain on Hostinger:</p>'
            . '<ol><li>In hPanel go to <strong>Emails → Email Accounts</strong> and create an address, e.g. <code>no-reply@yourdomain.com</code>.</li>'
            . '<li>Open that account\'s <strong>Configure Email Client</strong> page and copy the <strong>Outgoing Server (SMTP)</strong> host and port (usually <code>smtp.hostinger.com</code>, port <code>465</code> with SSL or <code>587</code> with TLS).</li>'
            . '<li>Enter that host, port, the full email address as username, and its mailbox password below, then set the encryption to match (SSL for 465, TLS for 587).</li>'
            . '<li>Set <strong>From Email</strong> to the same address so SPF/DKIM (already set up by Hostinger for your domain) lines up and messages don\'t land in spam.</li></ol>';
    }
    if ($which === 'gmail') {
        return '<p>To send email through a Gmail/Google Workspace account:</p>'
            . '<ol><li>Turn on <strong>2-Step Verification</strong> on the Google account (required for App Passwords).</li>'
            . '<li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a>, create an App Password for "Mail", and copy the 16-character code.</li>'
            . '<li>Below, set Host to <code>smtp.gmail.com</code>, Port <code>587</code>, Encryption <strong>TLS</strong>, Username to the full Gmail address, and Password to the App Password (not the normal Google password).</li></ol>'
            . '<p>Gmail SMTP has a sending-volume limit (about 500/day on a free account) — fine for account emails, not for bulk newsletters (use Mailchimp for that).</p>';
    }
    if ($which === 'mailchimp') {
        return '<p>Mailchimp is for marketing/newsletter sends, not for the transactional emails above (password reset, verification) — those always go through SMTP.</p>'
            . '<ol><li>In Mailchimp, go to <strong>Account → Extras → API keys</strong> and create a key.</li>'
            . '<li>Go to <strong>Audience → All contacts → Settings → Audience name and defaults</strong> and copy the <strong>Audience ID</strong>.</li>'
            . '<li>Paste both below. This makes the key/ID available for future newsletter-signup integrations on your sites.</li></ol>';
    }
    return '';
}
