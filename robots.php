<?php
/**
 * Dynamic robots.txt — reflects the "Allow search engines to index this
 * website" toggle in Admin → SEO Setting.
 *
 * The admin page also writes a static /robots.txt on save (which web servers
 * serve directly). This file is the fallback: add the rewrite below to your
 * root .htaccess if you'd rather serve it dynamically.
 *
 *   RewriteEngine On
 *   RewriteRule ^robots\.txt$ robots.php [L]
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_functions.php';

header('Content-Type: text/plain; charset=utf-8');
echo seo_robots_txt($pdo);
