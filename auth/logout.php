<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
require_once __DIR__ . '/../config/config.php';
redirect(rtrim(APP_URL, '/') . '/');
