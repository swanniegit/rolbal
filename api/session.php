<?php
/**
 * Session API
 *
 * Supports both browser (FormData + CSRF + session auth)
 * and mobile (JSON body + Bearer token auth, no CSRF).
 */

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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = parseBody();
        $isMobile = Auth::hasBearerHeader();

        // CSRF required for browser session auth only
        if (!$isMobile) {
            $csrf = $input['csrf_token'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $hand        = $input['hand']          ?? '';
        $date        = $input['session_date']  ?? '';
        $bowlsPerEnd = isset($input['bowls_per_end']) ? (int)$input['bowls_per_end'] : 4;
        $totalEnds   = isset($input['total_ends'])    ? (int)$input['total_ends']    : 15;
        $description = $input['description']   ?? null;

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

        $playerId = Auth::idFromRequest();
        $id = Session::create($hand, $date, $bowlsPerEnd, $totalEnds, $description, $playerId);

        ApiResponse::success(['id' => $id]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $playerId = Auth::idFromRequest();

        if ($id) {
            $session = Session::find($id);
            if (!$session) {
                ApiResponse::notFound('Session not found');
            }
            if (!$session['is_public'] && $session['player_id'] !== null && $session['player_id'] != $playerId) {
                ApiResponse::forbidden('Cannot view this session');
            }
            ApiResponse::success(['session' => $session]);
        } elseif (isset($_GET['mine']) && $playerId) {
            $sessions = Session::forPlayer($playerId);
            ApiResponse::success(['sessions' => $sessions]);
        } else {
            $sessions = Session::allPublic();
            ApiResponse::success(['sessions' => $sessions]);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $playerId = Auth::idFromRequest();

        if (!$id) {
            throw new Exception('Session ID required');
        }

        $result = Session::delete($id, $playerId);
        if (!$result) {
            ApiResponse::forbidden('Cannot delete this session');
        }

        ApiResponse::success();

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $input['csrf_token'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $id       = $input['id']     ?? 0;
        $action   = $input['action'] ?? '';
        $playerId = Auth::idFromRequest();

        if (!$id) {
            throw new Exception('Session ID required');
        }
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        if ($action === 'toggle_visibility') {
            $result = Session::toggleVisibility($id, $playerId);
            if (!$result) {
                ApiResponse::forbidden('Cannot modify this session');
            }
            $session = Session::find($id);
            ApiResponse::success(['is_public' => (bool)$session['is_public']]);
        } else {
            ApiResponse::error('Invalid action');
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
