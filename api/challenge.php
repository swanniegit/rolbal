<?php
/**
 * Challenge API
 *
 * Supports both browser (FormData + CSRF + session auth)
 * and mobile (JSON body + Bearer token auth, no CSRF).
 */

require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/ChallengeAttempt.php';
require_once __DIR__ . '/../includes/RollValidator.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Cors.php';

Cors::handle();

function parseBody(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
    return $_POST;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action   = $_GET['action'] ?? 'list';
        $playerId = Auth::idFromRequest();

        if ($action === 'list') {
            $challenges = $playerId
                ? Challenge::allWithPlayerBest($playerId)
                : Challenge::all();
            ApiResponse::success(['challenges' => $challenges]);

        } elseif ($action === 'get') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('Challenge ID required');

            $challenge = Challenge::findWithSequences($id);
            if (!$challenge) ApiResponse::notFound('Challenge not found');

            if ($playerId) {
                $challenge['best_score'] = Challenge::getBestScoreForPlayer($id, $playerId);
            }
            ApiResponse::success(['challenge' => $challenge]);

        } elseif ($action === 'progress') {
            $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
            if (!$attemptId) throw new Exception('Attempt ID required');
            $progress = ChallengeAttempt::getProgress($attemptId);
            ApiResponse::success(['progress' => $progress]);

        } elseif ($action === 'leaderboard') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('Challenge ID required');
            $leaderboard = Challenge::getLeaderboard($id);
            ApiResponse::success(['leaderboard' => $leaderboard]);

        } elseif ($action === 'history') {
            if (!$playerId) ApiResponse::unauthorized();
            $attempts = ChallengeAttempt::forPlayer($playerId);
            ApiResponse::success(['attempts' => $attempts]);

        } elseif ($action === 'breakdown') {
            $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
            if (!$attemptId) throw new Exception('Attempt ID required');
            $breakdown = ChallengeAttempt::getScoreBreakdown($attemptId);
            $attempt   = ChallengeAttempt::find($attemptId);
            ApiResponse::success(['attempt' => $attempt, 'breakdown' => $breakdown]);

        } else {
            ApiResponse::invalidAction();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = parseBody();
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $input['csrf_token'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $playerId = Auth::idFromRequest();
        if (!$playerId) ApiResponse::unauthorized();

        $action = $input['action'] ?? '';

        if ($action === 'start') {
            $challengeId = isset($input['challenge_id']) ? (int)$input['challenge_id'] : 0;
            if (!$challengeId) throw new Exception('Challenge ID required');

            $challenge = Challenge::find($challengeId);
            if (!$challenge) ApiResponse::notFound('Challenge not found');
            if (!$challenge['is_active']) throw new Exception('This challenge is not available');

            $existing = ChallengeAttempt::getActiveAttempt($playerId, $challengeId);
            if ($existing) {
                ApiResponse::success([
                    'attempt_id' => $existing['id'],
                    'resumed'    => true,
                    'progress'   => ChallengeAttempt::getProgress($existing['id']),
                ]);
            }

            $attemptId = ChallengeAttempt::create($challengeId, $playerId);
            ApiResponse::success([
                'attempt_id' => $attemptId,
                'resumed'    => false,
                'progress'   => ChallengeAttempt::getProgress($attemptId),
            ]);

        } elseif ($action === 'roll') {
            $attemptId = isset($input['attempt_id']) ? (int)$input['attempt_id'] : 0;
            $endLength = isset($input['end_length']) ? (int)$input['end_length'] : 0;
            $delivery  = isset($input['delivery'])   ? (int)$input['delivery']   : 0;
            $result    = isset($input['result'])     ? (int)$input['result']     : 0;
            $toucher   = isset($input['toucher'])    ? (int)$input['toucher']    : 0;

            if (!$attemptId || !$endLength || !$delivery || !$result) {
                throw new Exception('Missing required fields');
            }

            RollValidator::validateEndLength($endLength);
            RollValidator::validateDelivery($delivery);
            RollValidator::validateResult($result);

            $attempt = ChallengeAttempt::find($attemptId);
            if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
                ApiResponse::forbidden('Not your attempt');
            }

            $rollResult = ChallengeAttempt::addRoll($attemptId, $endLength, $delivery, $result, $toucher);
            $progress   = ChallengeAttempt::getProgress($attemptId);

            if ($progress['is_complete'] && !$progress['completed_at']) {
                ChallengeAttempt::complete($attemptId);
                $progress['completed_at'] = date('Y-m-d H:i:s');
            }

            ApiResponse::success(['roll' => $rollResult, 'progress' => $progress]);

        } elseif ($action === 'complete') {
            $attemptId = isset($input['attempt_id']) ? (int)$input['attempt_id'] : 0;
            if (!$attemptId) throw new Exception('Attempt ID required');

            $attempt = ChallengeAttempt::find($attemptId);
            if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
                ApiResponse::forbidden('Not your attempt');
            }

            ChallengeAttempt::complete($attemptId);
            ApiResponse::success();

        } else {
            ApiResponse::invalidAction();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $isMobile = Auth::hasBearerHeader();

        if (!$isMobile) {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!Auth::validateCsrfToken($csrf)) {
                ApiResponse::forbidden('Invalid security token');
            }
        }

        $playerId = Auth::idFromRequest();
        if (!$playerId) ApiResponse::unauthorized();

        $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
        if (!$attemptId) throw new Exception('Attempt ID required');

        $attempt = ChallengeAttempt::find($attemptId);
        if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
            ApiResponse::forbidden('Not your attempt');
        }

        if (isset($_GET['undo'])) {
            $result = ChallengeAttempt::undoLastRoll($attemptId);
            if (!$result) {
                throw new Exception('Cannot undo — no rolls to undo or attempt is complete');
            }
            $progress = ChallengeAttempt::getProgress($attemptId);
            ApiResponse::success(['progress' => $progress]);
        } else {
            ApiResponse::invalidAction();
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
