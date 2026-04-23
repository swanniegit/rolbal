<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

if (!RateLimit::attempt('feedback', $_SERVER['REMOTE_ADDR'] ?? '', 5, 3600)) {
    ApiResponse::error('Too many messages. Please try again later.', 429);
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$message) {
    ApiResponse::error('Name and message are required', 400);
}

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ApiResponse::error('Invalid email address', 400);
}

if (strlen($message) > 2000) {
    ApiResponse::error('Message too long (max 2000 characters)', 400);
}

$fromEmail = $email ?: 'noreply@bowlstracker.co.za';
$sent = Mailer::sendFeedback($name, $fromEmail, $message);

if (!$sent) {
    ApiResponse::error('Failed to send message. Please try WhatsApp or email directly.', 500);
}

ApiResponse::success(['message' => 'Thanks for your feedback!']);
