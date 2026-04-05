<?php
/**
 * Session API
 *
 * Security: CSRF validation on state-changing operations
 * Authorization: Ownership checks on all operations
 */

require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF validation for state-changing operation
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $hand = $_POST['hand'] ?? '';
        $date = $_POST['session_date'] ?? '';
        $bowlsPerEnd = isset($_POST['bowls_per_end']) ? (int)$_POST['bowls_per_end'] : 4;
        $totalEnds = isset($_POST['total_ends']) ? (int)$_POST['total_ends'] : 15;
        $description = $_POST['description'] ?? null;

        if (!in_array($hand, ['L', 'R'])) {
            throw new Exception('Invalid hand selection');
        }

        if (!$date) {
            throw new Exception('Date is required');
        }

        if (!in_array($bowlsPerEnd, [2, 3, 4])) {
            throw new Exception('Bowls per end must be 2, 3, or 4');
        }

        if ($totalEnds < 1 || $totalEnds > 30) {
            throw new Exception('Invalid number of ends');
        }

        $playerId = Auth::id();
        $id = Session::create($hand, $date, $bowlsPerEnd, $totalEnds, $description, $playerId);

        ApiResponse::success(['id' => $id]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $playerId = Auth::id();

        if ($id) {
            $session = Session::find($id);
            if (!$session) {
                ApiResponse::notFound('Session not found');
            }
            // Allow viewing if: public, anonymous session, or owner
            if (!$session['is_public'] && $session['player_id'] !== null && $session['player_id'] != $playerId) {
                ApiResponse::forbidden('Cannot view this session');
            }
            ApiResponse::success(['session' => $session]);
        } else {
            // List sessions: only public sessions or user's own sessions
            $sessions = Session::allPublic();
            ApiResponse::success(['sessions' => $sessions]);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // CSRF validation via header for DELETE requests
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$id) {
            throw new Exception('Session ID required');
        }

        $playerId = Auth::id();
        $result = Session::delete($id, $playerId);

        if (!$result) {
            ApiResponse::forbidden('Cannot delete this session');
        }

        ApiResponse::success();

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true);

        // CSRF validation
        $csrf = $input['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $id = $input['id'] ?? 0;
        $action = $input['action'] ?? '';

        if (!$id) {
            throw new Exception('Session ID required');
        }

        $playerId = Auth::id();
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        if ($action === 'toggle_visibility') {
            $result = Session::toggleVisibility($id, $playerId);
            if (!$result) {
                ApiResponse::forbidden('Cannot modify this session');
            }
            $session = Session::find($id);
            ApiResponse::success(['is_public' => (bool) $session['is_public']]);
        } else {
            ApiResponse::error('Invalid action');
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
