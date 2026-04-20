<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'College Hostel Management System');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/hostel-management');
define('SESSION_TIMEOUT_SECONDS', 1800);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hostel_management');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Kolkata');

