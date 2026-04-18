<?php
/**
 * BowlsTracker Configuration
 * Loads settings from .env file with fallbacks for local development
 */

require_once __DIR__ . '/env.php';
Env::load();

// Database — required, no fallbacks; app fails loudly if .env is missing
define('DB_HOST',    Env::required('DB_HOST'));
define('DB_NAME',    Env::required('DB_NAME'));
define('DB_USER',    Env::required('DB_USER'));
define('DB_PASS',    Env::required('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// App
define('APP_NAME', Env::get('APP_NAME', 'BowlsTracker'));
define('APP_URL',  Env::get('APP_URL',  'http://localhost/rolbal'));
define('APP_ENV',  Env::get('APP_ENV',  'local'));
define('APP_VERSION', '1.0.0');

// Debug — default false; only enable explicitly in local .env
define('DEBUG', Env::bool('DEBUG', false));

// Error reporting based on debug mode
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// JWT secret — must be set to a strong random value in .env (min 32 chars)
$_jwtSecret = Env::get('JWT_SECRET', '');
if (strlen($_jwtSecret) < 32) {
    if (APP_ENV === 'production') {
        // Hard stop: a weak/missing secret on production is a critical security fault
        http_response_code(500);
        exit('Server configuration error');
    }
    // Local dev fallback — not used on production
    $_jwtSecret = 'local-dev-secret-change-in-production-!!';
}
define('JWT_SECRET', $_jwtSecret);
unset($_jwtSecret);

// WhatsApp Cloud API
define('WHATSAPP_VERIFY_TOKEN', Env::get('WHATSAPP_VERIFY_TOKEN', ''));
define('WHATSAPP_ACCESS_TOKEN', Env::get('WHATSAPP_ACCESS_TOKEN', ''));
define('WHATSAPP_PHONE_ID', Env::get('WHATSAPP_PHONE_ID', ''));
define('WHATSAPP_APP_SECRET', Env::get('WHATSAPP_APP_SECRET', ''));
