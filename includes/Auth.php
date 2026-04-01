<?php
/**
 * Authentication Helper
 */

require_once __DIR__ . '/Player.php';

class Auth {

    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $player): void {
        self::init();
        session_regenerate_id(true);

        $_SESSION['player_id'] = $player['id'];
        $_SESSION['player_name'] = $player['name'];
        $_SESSION['player_email'] = $player['email'];
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout(): void {
        self::init();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool {
        self::init();
        return isset($_SESSION['player_id']);
    }

    public static function user(): ?array {
        self::init();

        if (!self::check()) {
            return null;
        }

        return Player::find($_SESSION['player_id']);
    }

    public static function id(): ?int {
        self::init();
        return $_SESSION['player_id'] ?? null;
    }

    public static function name(): ?string {
        self::init();
        return $_SESSION['player_name'] ?? null;
    }

    public static function generateCsrfToken(): string {
        self::init();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool {
        self::init();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function flash(string $type, string $message): void {
        self::init();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function getFlash(): ?array {
        self::init();

        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
}
