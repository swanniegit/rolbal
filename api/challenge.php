<?php
/**
 * Challenge API
 */

require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/ChallengeAttempt.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // List all active challenges
            $playerId = Auth::id();
            if ($playerId) {
                $challenges = Challenge::allWithPlayerBest($playerId);
            } else {
                $challenges = Challenge::all();
            }
            ApiResponse::success(['challenges' => $challenges]);

        } elseif ($action === 'get') {
            // Get single challenge with sequences
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
            // Get attempt progress
            $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
            if (!$attemptId) {
                throw new Exception('Attempt ID required');
            }

            $progress = ChallengeAttempt::getProgress($attemptId);
            ApiResponse::success(['progress' => $progress]);

        } elseif ($action === 'leaderboard') {
            // Get challenge leaderboard
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Challenge ID required');
            }

            $leaderboard = Challenge::getLeaderboard($id);
            ApiResponse::success(['leaderboard' => $leaderboard]);

        } elseif ($action === 'history') {
            // Get player's attempt history
            $playerId = Auth::id();
            if (!$playerId) {
                ApiResponse::unauthorized();
            }

            $attempts = ChallengeAttempt::forPlayer($playerId);
            ApiResponse::success(['attempts' => $attempts]);

        } elseif ($action === 'breakdown') {
            // Get score breakdown for an attempt
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
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $playerId = Auth::id();
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'start') {
            // Start a new challenge attempt
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

            // Check for existing incomplete attempt
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
            // Record a roll
            $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
            $endLength = isset($_POST['end_length']) ? (int)$_POST['end_length'] : 0;
            $delivery = isset($_POST['delivery']) ? (int)$_POST['delivery'] : 0;
            $result = isset($_POST['result']) ? (int)$_POST['result'] : 0;
            $toucher = isset($_POST['toucher']) ? (int)$_POST['toucher'] : 0;

            if (!$attemptId || !$endLength || !$delivery || !$result) {
                throw new Exception('Missing required fields');
            }

            // Validate end_length
            if (!in_array($endLength, [9, 10, 11])) {
                throw new Exception('Invalid end length');
            }

            // Validate delivery
            if (!in_array($delivery, [13, 14])) {
                throw new Exception('Invalid delivery');
            }

            // Validate result (1-8, 12 = on green, 20-23 = misses)
            if (!in_array($result, [1, 2, 3, 4, 5, 6, 7, 8, 12, 20, 21, 22, 23])) {
                throw new Exception('Invalid result position');
            }

            // Verify attempt belongs to this player
            $attempt = ChallengeAttempt::find($attemptId);
            if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
                ApiResponse::forbidden('Not your attempt');
            }

            $rollResult = ChallengeAttempt::addRoll($attemptId, $endLength, $delivery, $result, $toucher);

            // Get updated progress
            $progress = ChallengeAttempt::getProgress($attemptId);

            // Auto-complete if all bowls are done
            if ($progress['is_complete'] && !$progress['completed_at']) {
                ChallengeAttempt::complete($attemptId);
                $progress['completed_at'] = date('Y-m-d H:i:s');
            }

            ApiResponse::success([
                'roll' => $rollResult,
                'progress' => $progress
            ]);

        } elseif ($action === 'complete') {
            // Manually complete an attempt
            $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
            if (!$attemptId) {
                throw new Exception('Attempt ID required');
            }

            // Verify attempt belongs to this player
            $attempt = ChallengeAttempt::find($attemptId);
            if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
                ApiResponse::forbidden('Not your attempt');
            }

            ChallengeAttempt::complete($attemptId);
            ApiResponse::success();

        } else {
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $playerId = Auth::id();
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
        $undo = isset($_GET['undo']);

        if (!$attemptId) {
            throw new Exception('Attempt ID required');
        }

        // Verify attempt belongs to this player
        $attempt = ChallengeAttempt::find($attemptId);
        if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
            ApiResponse::forbidden('Not your attempt');
        }

        if ($undo) {
            // Undo last roll
            $result = ChallengeAttempt::undoLastRoll($attemptId);
            if (!$result) {
                throw new Exception('Cannot undo - no rolls to undo or attempt completed');
            }
            $progress = ChallengeAttempt::getProgress($attemptId);
            ApiResponse::success(['progress' => $progress]);
        } else {
            ApiResponse::error('Invalid delete action');
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
