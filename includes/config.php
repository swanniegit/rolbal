<?php
/**
 * BowlsTracker Configuration
 * Loads settings from .env file with fallbacks for local development
 */

require_once __DIR__ . '/env.php';
Env::load();

// Database
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::get('DB_NAME', 'rolbal'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

// App
define('APP_NAME', Env::get('APP_NAME', 'BowlsTracker'));
define('APP_URL', Env::get('APP_URL', 'http://localhost/rolbal'));
define('APP_VERSION', '1.0.0');

// Environment
define('DEBUG', Env::bool('DEBUG', true));

// Error reporting based on debug mode
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
