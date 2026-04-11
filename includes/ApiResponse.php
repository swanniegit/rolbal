<?php
/**
 * API Response Helper - Centralized JSON response formatting
 */

require_once __DIR__ . '/Auth.php';

class ApiResponse {

    // Common error messages
    const ERR_LOGIN_REQUIRED = 'Login required';
    const ERR_ACCESS_DENIED = 'Access denied';
    const ERR_NOT_FOUND = 'Not found';
    const ERR_INVALID_ACTION = 'Invalid action';
    const ERR_METHOD_NOT_ALLOWED = 'Method not allowed';
    const ERR_MISSING_FIELDS = 'Missing required fields';
    const ERR_NOT_AUTHORIZED = 'Not authorized';

    public static function success(array $data = []): void {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true], $data));
        exit;
    }

    public static function error(string $message, int $httpCode = 400): void {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    public static function notFound(string $message = self::ERR_NOT_FOUND): void {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = self::ERR_LOGIN_REQUIRED): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = self::ERR_ACCESS_DENIED): void {
        self::error($message, 403);
    }

    public static function methodNotAllowed(): void {
        self::error(self::ERR_METHOD_NOT_ALLOWED, 405);
    }

    public static function invalidAction(): void {
        self::error(self::ERR_INVALID_ACTION);
    }

    /**
     * Require authenticated user — checks Bearer token (mobile) OR session (web).
     * Returns player ID or sends 401.
     */
    public static function requireAuth(): int {
        $playerId = Auth::idFromRequest();
        if (!$playerId) {
            self::unauthorized();
        }
        return $playerId;
    }
}
