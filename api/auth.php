<?php
/**
 * Auth API — Login, Logout, Register, Token refresh
 *
 * Standard mode  (browser):  POST action=login              → sets session + CSRF
 * Token mode     (mobile):   POST action=login&mode=token   → returns JWT + refresh token
 * Refresh        (mobile):   POST action=refresh            → new access token
 * Revoke         (mobile):   POST action=revoke             → invalidates refresh token
 */

require_once __DIR__ . '/../includes/Player.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/TokenStore.php';
require_once __DIR__ . '/../includes/Captcha.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Cors.php';

Cors::handle();

try {
    // Parse JSON body once (mobile sends application/json; browser sends form data)
    $isJson   = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    $input    = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : $_POST;

    $action = $input['action'] ?? $_GET['action'] ?? '';
    $mode   = $_GET['mode']    ?? $input['mode']  ?? '';  // 'token' for mobile

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if ($action === 'register') {
            $email           = $input['email']            ?? '';
            $password        = $input['password']         ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';
            $name            = $input['name']             ?? '';
            $hand            = $input['hand']             ?? 'R';
            $captcha         = $input['captcha']          ?? '';
            $csrf            = $input['csrf_token']       ?? '';

            // CSRF only required for browser (session) mode
            if ($mode !== 'token' && !Auth::validateCsrfToken($csrf)) {
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
            if ($mode !== 'token' && !Captcha::validate($captcha)) {
                throw new Exception('Incorrect answer to math question');
            }
            if (Player::findByEmail($email)) {
                throw new Exception('An account with this email already exists');
            }

            $playerId = Player::create($email, $password, $name, $hand);
            $player   = Player::find($playerId);

            ApiResponse::success([
                'message'  => 'Registration successful! Please check your email to verify your account.',
                'player_id'=> $playerId,
                'token'    => $player['verification_token'],
            ]);

        } elseif ($action === 'login') {
            $email    = $input['email']      ?? '';
            $password = $input['password']   ?? '';
            $csrf     = $input['csrf_token'] ?? '';

            if ($mode !== 'token' && !Auth::validateCsrfToken($csrf)) {
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

            if ($mode === 'token') {
                // Mobile: return JWT + refresh token, no session
                $tokens = TokenStore::issue((int) $player['id']);
                ApiResponse::success([
                    'access_token'  => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_in'    => $tokens['expires_in'],
                    'player' => [
                        'id'   => $player['id'],
                        'name' => $player['name'],
                        'hand' => $player['hand'],
                    ],
                ]);
            } else {
                // Browser: set session as before
                Auth::login($player);
                ApiResponse::success([
                    'message' => 'Login successful',
                    'player'  => [
                        'id'    => $player['id'],
                        'name'  => $player['name'],
                        'email' => $player['email'],
                    ],
                ]);
            }

        } elseif ($action === 'refresh') {
            // Mobile: exchange refresh token for new access token
            $refreshToken = $input['refresh_token'] ?? '';
            if (!$refreshToken) {
                throw new Exception('refresh_token required');
            }

            $tokens = TokenStore::refresh($refreshToken);
            if (!$tokens) {
                ApiResponse::error('Refresh token expired or invalid', 401);
            }

            ApiResponse::success($tokens);

        } elseif ($action === 'revoke') {
            // Mobile: logout — invalidate refresh token
            $refreshToken = $input['refresh_token'] ?? '';
            if ($refreshToken) {
                TokenStore::revoke($refreshToken);
            }
            ApiResponse::success(['message' => 'Token revoked']);

        } elseif ($action === 'logout') {
            // Browser logout
            Auth::logout();
            ApiResponse::success(['message' => 'Logged out']);

        } else {
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($action === 'me') {
            $playerId = Auth::idFromRequest();
            if (!$playerId) ApiResponse::unauthorized();
            $player = Player::find($playerId);
            if (!$player) ApiResponse::notFound('Player not found');
            ApiResponse::success(['player' => [
                'id'    => $player['id'],
                'name'  => $player['name'],
                'email' => $player['email'],
                'hand'  => $player['hand'],
            ]]);

        } elseif ($action === 'status') {
            $playerId = Auth::idFromRequest();
            ApiResponse::success([
                'logged_in' => $playerId !== null,
                'player'    => $playerId ? [
                    'id'   => $playerId,
                    'name' => Auth::name() ?? Player::find($playerId)['name'] ?? null,
                ] : null,
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
