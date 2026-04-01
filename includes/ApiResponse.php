<?php
/**
 * API Response Helper - Centralized JSON response formatting
 */

class ApiResponse {

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

    public static function notFound(string $message = 'Not found'): void {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Login required'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Access denied'): void {
        self::error($message, 403);
    }

    public static function methodNotAllowed(): void {
        self::error('Method not allowed', 405);
    }
}
