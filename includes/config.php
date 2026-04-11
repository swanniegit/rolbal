<?php
/**
 * BowlsTracker Configuration
 * Loads settings from .env file with fallbacks for local development
 */

require_once __DIR__ . '/env.php';
Env::load();

// Database
define('DB_HOST', 'sql40.jnb2.host-h.net');
define('DB_NAME', 'rolbal');
define('DB_USER', 'myrolbal');
define('DB_PASS', '4f7J6C7wj40081');
define('DB_CHARSET', 'utf8mb4');

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

// Mobile JWT — set JWT_SECRET in .env for production (min 32 chars)
define('JWT_SECRET', Env::get('JWT_SECRET', 'local-dev-secret-change-in-production-!!'));

// WhatsApp Cloud API
define('WHATSAPP_VERIFY_TOKEN', Env::get('WHATSAPP_VERIFY_TOKEN', ''));
define('WHATSAPP_ACCESS_TOKEN', Env::get('WHATSAPP_ACCESS_TOKEN', ''));
define('WHATSAPP_PHONE_ID', Env::get('WHATSAPP_PHONE_ID', ''));
define('WHATSAPP_APP_SECRET', Env::get('WHATSAPP_APP_SECRET', ''));
