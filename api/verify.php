<?php
/**
 * Email Verification API
 */

require_once __DIR__ . '/../includes/Player.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            throw new Exception('Verification token is required');
        }

        if (Player::verify($token)) {
            ApiResponse::success([
                'message' => 'Email verified successfully! You can now log in.'
            ]);
        } else {
            throw new Exception('Invalid or expired verification token');
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
