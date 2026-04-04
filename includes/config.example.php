<?php
/**
 * BowlsTracker Configuration
 * Copy this file to config.php and update credentials
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'rolbal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App
define('APP_NAME', 'BowlsTracker');
define('APP_VERSION', '1.0.0');

// Environment
define('DEBUG', true);

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
