<?php
/**
 * CORS helper for API endpoints called by the Android app.
 *
 * Call Cors::handle() at the top of any API file that the mobile client
 * will reach. It's a no-op for same-origin browser requests.
 */

class Cors {

    private static array $allowed = [
        'https://bowlstracker.co.za',
        'capacitor://localhost',   // Capacitor Android (older scheme)
        'https://localhost',       // Capacitor Android 6+ (new scheme)
        'http://localhost:3000',   // Vite dev server
        'http://10.0.2.2',        // Android emulator → host machine
    ];

    public static function handle(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, self::$allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        // Preflight — respond immediately with no body
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
