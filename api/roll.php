<?php
/**
 * Roll API
 *
 * Supports both browser (FormData + CSRF + session auth)
 * and mobile (JSON body + Bearer token auth, no CSRF).
 */

require_once __DIR__ . '/../includes/Roll.php';
require_once __DIR__ . '/../includes/RollValidator.php';
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Cors.php';

Cors::handle();

/**
 * Parse request body — JSON (mobile) or $_POST (browser).
 */
function parseBody(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
    return $_POST;
}

/**
 * Verify the caller owns the session (or is an anonymous user on an anonymous session).
 */
function verifySessionOwnership(int $sessionId): void {
    $session = Session::find($sessionId);
    if (!$session) {
        ApiResponse::notFound('Session not found');
    }

    $playerId       = Auth::idFromRequest();
    $sessionOwnerId = $session['player_id'];

    if ($sessionOwnerId === null && $playerId === null) {
        return; // Anonymous session + anonymous user
    }
    if ($sessionOwnerId !== null && $sessionOwnerId == $playerId) {
        return; // Owner matches
    }

    ApiResponse::forbidden('Not authorized to modify this session');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = parseBody();
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $input['csrf_token'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        $endNumber = isset($input['end_number']) ? (int)$input['end_number'] : 1;
        $endLength = isset($input['end_length']) ? (int)$input['end_length'] : 0;
        $result    = isset($input['result'])     ? (int)$input['result']     : 0;
        $toucher   = isset($input['toucher'])    ? (int)$input['toucher']    : 0;

        if (!$sessionId) {
            throw new Exception('Session ID required');
        }

        verifySessionOwnership($sessionId);
        RollValidator::validateEndLength($endLength);
        RollValidator::validateResult($result);

        $id = Roll::create($sessionId, $endNumber, $endLength, $result, $toucher);

        ApiResponse::success(['id' => $id]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

        if (!$sessionId) {
            throw new Exception('Session ID required');
        }

        $session = Session::find($sessionId);
        if (!$session) {
            ApiResponse::notFound('Session not found');
        }

        $playerId = Auth::idFromRequest();
        if (!$session['is_public'] && $session['player_id'] !== null && $session['player_id'] != $playerId) {
            ApiResponse::forbidden('Cannot view this session');
        }

        $rolls = Roll::forSession($sessionId);
        ApiResponse::success(['rolls' => $rolls]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        $undo      = isset($_GET['undo']);

        if ($undo && $sessionId) {
            verifySessionOwnership($sessionId);
            Roll::undoLast($sessionId);
            ApiResponse::success();
        } else {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Roll ID required');
            }
            $roll = Roll::find($id);
            if (!$roll) {
                ApiResponse::notFound('Roll not found');
            }
            verifySessionOwnership($roll['session_id']);
            Roll::delete($id);
            ApiResponse::success();
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
