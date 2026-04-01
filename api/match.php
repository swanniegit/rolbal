<?php
/**
 * Match API - Live Match Scoring System
 */

require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // List matches for a club
            $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
            if (!$clubId) {
                throw new Exception('Club ID required');
            }

            $playerId = Auth::id();
            if (!$playerId) {
                ApiResponse::unauthorized();
            }

            // Verify club membership
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT 1 FROM club_members WHERE club_id = :club_id AND player_id = :player_id');
            $stmt->execute(['club_id' => $clubId, 'player_id' => $playerId]);
            if (!$stmt->fetch()) {
                ApiResponse::forbidden('Not a club member');
            }

            $status = $_GET['status'] ?? null;
            $matches = GameMatch::listByClub($clubId, $status);

            ApiResponse::success(['matches' => $matches]);

        } elseif ($action === 'get') {
            // Get match details
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Match ID required');
            }

            $playerId = Auth::id();
            if (!$playerId) {
                ApiResponse::unauthorized();
            }

            if (!GameMatch::canView($playerId, $id)) {
                ApiResponse::forbidden('Not authorized to view this match');
            }

            $match = GameMatch::findWithDetails($id);
            if (!$match) {
                ApiResponse::notFound('Match not found');
            }

            // Add permission flags
            $match['can_score'] = GameMatch::canScore($playerId, $id);
            $match['can_delete'] = GameMatch::canDelete($playerId, $id);

            ApiResponse::success(['match' => $match]);

        } elseif ($action === 'scores') {
            // Get scores only (for polling)
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Match ID required');
            }

            $playerId = Auth::id();
            if (!$playerId) {
                ApiResponse::unauthorized();
            }

            if (!GameMatch::canView($playerId, $id)) {
                ApiResponse::forbidden('Not authorized to view this match');
            }

            $scores = GameMatch::getScores($id);
            if (!$scores) {
                ApiResponse::notFound('Match not found');
            }

            ApiResponse::success($scores);

        } elseif ($action === 'game_types') {
            // Get game type configurations
            ApiResponse::success(['game_types' => GameMatch::getGameTypes()]);

        } elseif ($action === 'live_all') {
            // Get all live matches for a club (for multi-viewer)
            $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
            if (!$clubId) {
                throw new Exception('Club ID required');
            }

            $playerId = Auth::id();
            if (!$playerId) {
                ApiResponse::unauthorized();
            }

            // Verify club membership
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT 1 FROM club_members WHERE club_id = :club_id AND player_id = :player_id');
            $stmt->execute(['club_id' => $clubId, 'player_id' => $playerId]);
            if (!$stmt->fetch()) {
                ApiResponse::forbidden('Not a club member');
            }

            $matches = GameMatch::getLiveMatchesForClub($clubId);
            ApiResponse::success(['matches' => $matches]);

        } else {
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $playerId = Auth::id();
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            // Create new match
            $clubId = isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0;
            $gameType = $_POST['game_type'] ?? '';
            $bowlsPerPlayer = isset($_POST['bowls_per_player']) ? (int)$_POST['bowls_per_player'] : 0;
            $scoringMode = $_POST['scoring_mode'] ?? 'ends';
            $targetScore = isset($_POST['target_score']) ? (int)$_POST['target_score'] : 21;

            if (!$clubId) {
                throw new Exception('Club ID required');
            }

            if (!GameMatch::canCreate($playerId, $clubId)) {
                ApiResponse::forbidden('Only club admins can create matches');
            }

            $gameTypes = GameMatch::getGameTypes();
            if (!isset($gameTypes[$gameType])) {
                throw new Exception('Invalid game type');
            }

            $config = $gameTypes[$gameType];
            if (!in_array($bowlsPerPlayer, $config['allowed_bowls'])) {
                $bowlsPerPlayer = $config['default_bowls'];
            }

            if (!in_array($scoringMode, ['ends', 'first_to'])) {
                $scoringMode = 'ends';
            }

            if ($targetScore < 1 || $targetScore > 50) {
                $targetScore = 21;
            }

            $matchId = GameMatch::create($clubId, $playerId, $gameType, $bowlsPerPlayer, $scoringMode, $targetScore);

            // Create teams
            $team1Name = trim($_POST['team1_name'] ?? 'Team 1');
            $team2Name = trim($_POST['team2_name'] ?? 'Team 2');

            $team1Id = GameMatch::createTeam($matchId, 1, $team1Name);
            $team2Id = GameMatch::createTeam($matchId, 2, $team2Name);

            // Add players
            $positions = $config['positions'];
            foreach ($positions as $position) {
                $t1PlayerName = trim($_POST["team1_{$position}"] ?? '');
                $t2PlayerName = trim($_POST["team2_{$position}"] ?? '');

                if ($t1PlayerName) {
                    GameMatch::addPlayer($team1Id, $position, $t1PlayerName);
                }
                if ($t2PlayerName) {
                    GameMatch::addPlayer($team2Id, $position, $t2PlayerName);
                }
            }

            ApiResponse::success(['match_id' => $matchId]);

        } elseif ($action === 'start') {
            // Start match
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            if (!$matchId) {
                throw new Exception('Match ID required');
            }

            if (!GameMatch::canScore($playerId, $matchId)) {
                ApiResponse::forbidden('Not authorized to start this match');
            }

            $result = GameMatch::start($matchId);
            if (!$result) {
                throw new Exception('Cannot start match - may already be started');
            }

            ApiResponse::success();

        } elseif ($action === 'end') {
            // Record end score
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            $endNumber = isset($_POST['end_number']) ? (int)$_POST['end_number'] : 0;
            $scoringTeam = isset($_POST['scoring_team']) ? (int)$_POST['scoring_team'] : 0;
            $shots = isset($_POST['shots']) ? (int)$_POST['shots'] : 0;

            if (!$matchId || !$endNumber || !$scoringTeam || !$shots) {
                throw new Exception('Missing required fields');
            }

            if ($scoringTeam < 1 || $scoringTeam > 2) {
                throw new Exception('Invalid scoring team');
            }

            if ($shots < 1 || $shots > 8) {
                throw new Exception('Invalid shots count');
            }

            if (!GameMatch::canScore($playerId, $matchId)) {
                ApiResponse::forbidden('Not authorized to score this match');
            }

            // Verify match is live
            $match = GameMatch::find($matchId);
            if ($match['status'] !== 'live') {
                throw new Exception('Match is not live');
            }

            GameMatch::recordEnd($matchId, $endNumber, $scoringTeam, $shots);

            $scores = GameMatch::getScores($matchId);
            ApiResponse::success($scores);

        } elseif ($action === 'complete') {
            // Complete match
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            if (!$matchId) {
                throw new Exception('Match ID required');
            }

            if (!GameMatch::canScore($playerId, $matchId)) {
                ApiResponse::forbidden('Not authorized to complete this match');
            }

            $result = GameMatch::complete($matchId);
            if (!$result) {
                throw new Exception('Cannot complete match - may not be live');
            }

            ApiResponse::success();

        } elseif ($action === 'undo') {
            // Undo last end
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            if (!$matchId) {
                throw new Exception('Match ID required');
            }

            if (!GameMatch::canScore($playerId, $matchId)) {
                ApiResponse::forbidden('Not authorized to undo');
            }

            // Verify match is live
            $match = GameMatch::find($matchId);
            if ($match['status'] !== 'live') {
                throw new Exception('Match is not live');
            }

            $result = GameMatch::undoLastEnd($matchId);
            if (!$result) {
                throw new Exception('No ends to undo');
            }

            $scores = GameMatch::getScores($matchId);
            ApiResponse::success($scores);

        } elseif ($action === 'claim_scorer') {
            // Claim scorer role (paid members only)
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            if (!$matchId) {
                throw new Exception('Match ID required');
            }

            if (!GameMatch::canClaimScorer($playerId, $matchId)) {
                ApiResponse::forbidden('Cannot claim scorer - must be paid member and scorer not already claimed');
            }

            $result = GameMatch::claimScorer($matchId, $playerId);
            if (!$result) {
                throw new Exception('Failed to claim scorer role');
            }

            ApiResponse::success();

        } elseif ($action === 'release_scorer') {
            // Release scorer role
            $matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
            if (!$matchId) {
                throw new Exception('Match ID required');
            }

            $result = GameMatch::releaseScorer($matchId, $playerId);
            if (!$result) {
                throw new Exception('Cannot release scorer - not authorized');
            }

            ApiResponse::success();

        } else {
            ApiResponse::error('Invalid action');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $playerId = Auth::id();
        if (!$playerId) {
            ApiResponse::unauthorized();
        }

        $matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$matchId) {
            throw new Exception('Match ID required');
        }

        if (!GameMatch::canDelete($playerId, $matchId)) {
            ApiResponse::forbidden('Not authorized to delete this match');
        }

        GameMatch::delete($matchId);
        ApiResponse::success();

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
