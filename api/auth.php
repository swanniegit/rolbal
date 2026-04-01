<?php
/**
 * Auth API - Login, Logout, Register
 */

require_once __DIR__ . '/../includes/Player.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Captcha.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if ($action === 'register') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $name = $_POST['name'] ?? '';
            $hand = $_POST['hand'] ?? 'R';
            $captcha = $_POST['captcha'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';

            if (!Auth::validateCsrfToken($csrf)) {
                throw new Exception('Invalid form submission');
            }

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Valid email address is required');
            }

            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }

            if ($password !== $confirmPassword) {
                throw new Exception('Passwords do not match');
            }

            if (!$name || strlen($name) < 2) {
                throw new Exception('Name must be at least 2 characters');
            }

            if (!in_array($hand, ['L', 'R'])) {
                throw new Exception('Invalid hand selection');
            }

            if (!Captcha::validate($captcha)) {
                throw new Exception('Incorrect answer to math question');
            }

            if (Player::findByEmail($email)) {
                throw new Exception('An account with this email already exists');
            }

            $playerId = Player::create($email, $password, $name, $hand);
            $player = Player::find($playerId);

            ApiResponse::success([
                'message' => 'Registration successful! Please check your email to verify your account.',
                'player_id' => $playerId,
                'token' => $player['verification_token']
            ]);

        } elseif ($action === 'login') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';

            if (!Auth::validateCsrfToken($csrf)) {
                throw new Exception('Invalid form submission');
            }

            if (!$email || !$password) {
                throw new Exception('Email and password are required');
            }

            $player = Player::validatePassword($email, $password);

            if (!$player) {
                throw new Exception('Invalid email or password');
            }

            if (!$player['email_verified']) {
                throw new Exception('Please verify your email before logging in');
            }

            Auth::login($player);

            ApiResponse::success([
                'message' => 'Login successful',
                'player' => [
                    'id' => $player['id'],
                    'name' => $player['name'],
                    'email' => $player['email']
                ]
            ]);

        } elseif ($action === 'logout') {
            Auth::logout();
            ApiResponse::success(['message' => 'Logged out']);

        } else {
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($action === 'status') {
            ApiResponse::success([
                'logged_in' => Auth::check(),
                'player' => Auth::check() ? [
                    'id' => Auth::id(),
                    'name' => Auth::name()
                ] : null
            ]);

        } elseif ($action === 'captcha') {
            $captcha = Captcha::generate();
            ApiResponse::success(['question' => $captcha['question']]);

        } elseif ($action === 'csrf') {
            ApiResponse::success(['token' => Auth::generateCsrfToken()]);

        } else {
            ApiResponse::error('Invalid action');
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
