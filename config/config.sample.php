<?php
/**
 * Copy this file to config.php and fill in your details,
 * OR just run install.php in the browser and it will create
 * config.php for you automatically — you don't have to edit
 * this file by hand.
 */

// --- Database (Hostinger: MySQL Databases section in hPanel) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// --- App ---
define('APP_URL', 'https://videoconvertly.com'); // no trailing slash
define('APP_NAME', 'Web To Pin');
define('APP_SECRET', 'change_this_to_a_random_string');

// Timezone used for scheduling
date_default_timezone_set('Asia/Karachi');
