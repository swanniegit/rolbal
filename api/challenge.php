<?php
/**
 * Challenge API
 */

require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/ChallengeAttempt.php';
require_once __DIR__ . '/../includes/RollValidator.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            $playerId = Auth::id();
            if ($playerId) {
                $challenges = Challenge::allWithPlayerBest($playerId);
            } else {
                $challenges = Challenge::all();
            }
            ApiResponse::success(['challenges' => $challenges]);

        } elseif ($action === 'get') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Challenge ID required');
            }

            $challenge = Challenge::findWithSequences($id);
            if (!$challenge) {
                ApiResponse::notFound('Challenge not found');
            }

            $playerId = Auth::id();
            if ($playerId) {
                $challenge['best_score'] = Challenge::getBestScoreForPlayer($id, $playerId);
            }

            ApiResponse::success(['challenge' => $challenge]);

        } elseif ($action === 'progress') {
            $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
            if (!$attemptId) {
                throw new Exception('Attempt ID required');
            }

            $progress = ChallengeAttempt::getProgress($attemptId);
            ApiResponse::success(['progress' => $progress]);

        } elseif ($action === 'leaderboard') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Challenge ID required');
            }

            $leaderboard = Challenge::getLeaderboard($id);
            ApiResponse::success(['leaderboard' => $leaderboard]);

        } elseif ($action === 'history') {
            $playerId = ApiResponse::requireAuth();
            $attempts = ChallengeAttempt::forPlayer($playerId);
            ApiResponse::success(['attempts' => $attempts]);

        } elseif ($action === 'breakdown') {
            $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
            if (!$attemptId) {
                throw new Exception('Attempt ID required');
            }

            $breakdown = ChallengeAttempt::getScoreBreakdown($attemptId);
            $attempt = ChallengeAttempt::find($attemptId);

            ApiResponse::success([
                'attempt' => $attempt,
                'breakdown' => $breakdown
            ]);

        } else {
            ApiResponse::invalidAction();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $playerId = ApiResponse::requireAuth();
        $action = $_POST['action'] ?? '';

        if ($action === 'start') {
            $challengeId = isset($_POST['challenge_id']) ? (int)$_POST['challenge_id'] : 0;
            if (!$challengeId) {
                throw new Exception('Challenge ID required');
            }

            $challenge = Challenge::find($challengeId);
            if (!$challenge) {
                ApiResponse::notFound('Challenge not found');
            }

            if (!$challenge['is_active']) {
                throw new Exception('This challenge is not available');
            }

            $existing = ChallengeAttempt::getActiveAttempt($playerId, $challengeId);
            if ($existing) {
                ApiResponse::success([
                    'attempt_id' => $existing['id'],
                    'resumed' => true,
                    'progress' => ChallengeAttempt::getProgress($existing['id'])
                ]);
            }

            $attemptId = ChallengeAttempt::create($challengeId, $playerId);
            ApiResponse::success([
                'attempt_id' => $attemptId,
                'resumed' => false,
                'progress' => ChallengeAttempt::getProgress($attemptId)
            ]);

        } elseif ($action === 'roll') {
            $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
            $endLength = isset($_POST['end_length']) ? (int)$_POST['end_length'] : 0;
            $delivery = isset($_POST['delivery']) ? (int)$_POST['delivery'] : 0;
            $result = isset($_POST['result']) ? (int)$_POST['result'] : 0;
            $toucher = isset($_POST['toucher']) ? (int)$_POST['toucher'] : 0;

            if (!$attemptId || !$endLength || !$delivery || !$result) {
                throw new Exception(ApiResponse::ERR_MISSING_FIELDS);
            }

            RollValidator::validateEndLength($endLength);
            RollValidator::validateDelivery($delivery);
            RollValidator::validateResult($result);

            $attempt = ChallengeAttempt::find($attemptId);
            if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
                ApiResponse::forbidden('Not your attempt');
            }

            $rollResult = ChallengeAttempt::addRoll($attemptId, $endLength, $delivery, $result, $toucher);
            $progress = ChallengeAttempt::getProgress($attemptId);

            if ($progress['is_complete'] && !$progress['completed_at']) {
                ChallengeAttempt::complete($attemptId);
                $progress['completed_at'] = date('Y-m-d H:i:s');
            }

            ApiResponse::success([
                'roll' => $rollResult,
                'progress' => $progress
            ]);

        } elseif ($action === 'complete') {
            $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
            if (!$attemptId) {
                throw new Exception('Attempt ID required');
            }

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
        $playerId = ApiResponse::requireAuth();

        $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
        $undo = isset($_GET['undo']);

        if (!$attemptId) {
            throw new Exception('Attempt ID required');
        }

        $attempt = ChallengeAttempt::find($attemptId);
        if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
            ApiResponse::forbidden('Not your attempt');
        }

        if ($undo) {
            $result = ChallengeAttempt::undoLastRoll($attemptId);
            if (!$result) {
                throw new Exception('Cannot undo - no rolls to undo or attempt completed');
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
