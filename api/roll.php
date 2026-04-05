<?php
/**
 * Roll API
 *
 * Security: CSRF validation on state-changing operations
 * Authorization: Session ownership checks on all modifications
 */

require_once __DIR__ . '/../includes/Roll.php';
require_once __DIR__ . '/../includes/RollValidator.php';
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

/**
 * Verify current user owns the session (or session is anonymous and user is anonymous)
 */
function verifySessionOwnership(int $sessionId): void {
    $session = Session::find($sessionId);
    if (!$session) {
        ApiResponse::notFound('Session not found');
    }

    $playerId = Auth::id();
    $sessionOwnerId = $session['player_id'];

    // Allow if: anonymous session with anonymous user, OR authenticated owner
    if ($sessionOwnerId === null && $playerId === null) {
        return; // Anonymous session, anonymous user - allowed
    }
    if ($sessionOwnerId !== null && $sessionOwnerId == $playerId) {
        return; // Owner matches - allowed
    }

    ApiResponse::forbidden('Not authorized to modify this session');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF validation
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $endNumber = isset($_POST['end_number']) ? (int)$_POST['end_number'] : 1;
        $endLength = isset($_POST['end_length']) ? (int)$_POST['end_length'] : 0;
        $result = isset($_POST['result']) ? (int)$_POST['result'] : 0;
        $toucher = isset($_POST['toucher']) ? (int)$_POST['toucher'] : 0;

        if (!$sessionId) {
            throw new Exception('Session ID required');
        }

        // Verify ownership before creating roll
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

        // Check session visibility for viewing rolls
        $session = Session::find($sessionId);
        if (!$session) {
            ApiResponse::notFound('Session not found');
        }

        $playerId = Auth::id();
        // Allow viewing if: public, anonymous session, or owner
        if (!$session['is_public'] && $session['player_id'] !== null && $session['player_id'] != $playerId) {
            ApiResponse::forbidden('Cannot view this session');
        }

        $rolls = Roll::forSession($sessionId);
        ApiResponse::success(['rolls' => $rolls]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // CSRF validation via header
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        $undo = isset($_GET['undo']);

        if ($undo && $sessionId) {
            // Verify ownership before undo
            verifySessionOwnership($sessionId);
            Roll::undoLast($sessionId);
            ApiResponse::success();
        } else {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Roll ID required');
            }
            // Get roll's session to verify ownership
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
