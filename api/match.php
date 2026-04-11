<?php
/**
 * Match API - Live Match Scoring System
 *
 * Supports both browser (FormData + CSRF + session auth)
 * and mobile (JSON body + Bearer token auth, no CSRF).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/ClubMember.php';
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
        if (!$playerId) ApiResponse::unauthorized();

        if ($action === 'list') {
            $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
            if (!$clubId) throw new Exception('Club ID required');

            ClubMember::requireMembership($clubId, $playerId);
            $canCreate = GameMatch::canCreate($playerId, $clubId);

            if (isset($_GET['status'])) {
                // Single-status filter (browser compat)
                $matches = GameMatch::listByClub($clubId, $_GET['status']);
                ApiResponse::success(['matches' => $matches, 'can_create' => $canCreate]);
            } else {
                // All statuses in one response (mobile)
                ApiResponse::success([
                    'live'       => GameMatch::listByClub($clubId, 'live'),
                    'setup'      => GameMatch::listByClub($clubId, 'setup'),
                    'completed'  => GameMatch::listByClub($clubId, 'completed', 10),
                    'can_create' => $canCreate,
                ]);
            }

        } elseif ($action === 'get') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('Match ID required');

            if (!GameMatch::canView($playerId, $id)) {
                ApiResponse::forbidden('Not authorized to view this match');
            }

            $match = GameMatch::findWithDetails($id);
            if (!$match) ApiResponse::notFound('Match not found');

            $match['can_score']  = GameMatch::canScore($playerId, $id);
            $match['can_delete'] = GameMatch::canDelete($playerId, $id);
            ApiResponse::success(['match' => $match]);

        } elseif ($action === 'scores') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('Match ID required');

            if (!GameMatch::canView($playerId, $id)) {
                ApiResponse::forbidden('Not authorized to view this match');
            }

            $scores = GameMatch::getScores($id);
            if (!$scores) ApiResponse::notFound('Match not found');
            ApiResponse::success($scores);

        } elseif ($action === 'game_types') {
            ApiResponse::success(['game_types' => GameMatch::getGameTypes()]);

        } elseif ($action === 'live_all') {
            $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
            if (!$clubId) throw new Exception('Club ID required');

            ClubMember::requireMembership($clubId, $playerId);
            $matches = GameMatch::getLiveMatchesForClub($clubId);
            ApiResponse::success(['matches' => $matches]);

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

        if ($action === 'create') {
            $clubId         = isset($input['club_id'])         ? (int)$input['club_id']         : 0;
            $gameType       = $input['game_type']              ?? '';
            $bowlsPerPlayer = isset($input['bowls_per_player']) ? (int)$input['bowls_per_player'] : 0;
            $scoringMode    = $input['scoring_mode']            ?? 'ends';
            $targetScore    = isset($input['target_score'])     ? (int)$input['target_score']     : 21;

            if (!$clubId) throw new Exception('Club ID required');

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
            if (!in_array($scoringMode, ['ends', 'first_to'])) $scoringMode = 'ends';
            if ($targetScore < 1 || $targetScore > 50) $targetScore = 21;

            $db = Database::getInstance();
            $db->beginTransaction();
            try {
                $matchId = GameMatch::create($clubId, $playerId, $gameType, $bowlsPerPlayer, $scoringMode, $targetScore);

                $team1Name = trim($input['team1_name'] ?? 'Team 1');
                $team2Name = trim($input['team2_name'] ?? 'Team 2');
                $team1Id   = GameMatch::createTeam($matchId, 1, $team1Name);
                $team2Id   = GameMatch::createTeam($matchId, 2, $team2Name);

                foreach ($config['positions'] as $position) {
                    $t1 = trim($input["team1_{$position}"] ?? '');
                    $t2 = trim($input["team2_{$position}"] ?? '');
                    if ($t1) GameMatch::addPlayer($team1Id, $position, $t1);
                    if ($t2) GameMatch::addPlayer($team2Id, $position, $t2);
                }

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            ApiResponse::success(['match_id' => $matchId]);

        } elseif ($action === 'start') {
            $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
            if (!$matchId) throw new Exception('Match ID required');
            if (!GameMatch::canScore($playerId, $matchId)) ApiResponse::forbidden('Not authorized to start this match');
            if (!GameMatch::start($matchId)) throw new Exception('Cannot start match - may already be started');
            ApiResponse::success();

        } elseif ($action === 'end') {
            $matchId     = isset($input['match_id'])     ? (int)$input['match_id']     : 0;
            $endNumber   = isset($input['end_number'])   ? (int)$input['end_number']   : 0;
            $scoringTeam = isset($input['scoring_team']) ? (int)$input['scoring_team'] : 0;
            $shots       = isset($input['shots'])        ? (int)$input['shots']        : 0;

            if (!$matchId || !$endNumber || !$scoringTeam || !$shots) {
                throw new Exception('Missing required fields');
            }

            RollValidator::validateTeam($scoringTeam);
            RollValidator::validateShots($shots);

            if (!GameMatch::canScore($playerId, $matchId)) ApiResponse::forbidden('Not authorized to score this match');

            $match = GameMatch::find($matchId);
            if ($match['status'] !== 'live') throw new Exception('Match is not live');

            GameMatch::recordEnd($matchId, $endNumber, $scoringTeam, $shots);
            $scores = GameMatch::getScores($matchId);

            if ($match['scoring_mode'] === 'first_to' &&
                ($scores['team1_score'] >= $match['target_score'] || $scores['team2_score'] >= $match['target_score'])) {
                GameMatch::complete($matchId);
                $scores['status'] = 'completed';
            }

            ApiResponse::success($scores);

        } elseif ($action === 'complete') {
            $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
            if (!$matchId) throw new Exception('Match ID required');
            if (!GameMatch::canScore($playerId, $matchId)) ApiResponse::forbidden('Not authorized to complete this match');
            if (!GameMatch::complete($matchId)) throw new Exception('Cannot complete match - may not be live');
            ApiResponse::success();

        } elseif ($action === 'undo') {
            $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
            if (!$matchId) throw new Exception('Match ID required');
            if (!GameMatch::canScore($playerId, $matchId)) ApiResponse::forbidden('Not authorized to undo');
            $match = GameMatch::find($matchId);
            if ($match['status'] !== 'live') throw new Exception('Match is not live');
            if (!GameMatch::undoLastEnd($matchId)) throw new Exception('No ends to undo');
            $scores = GameMatch::getScores($matchId);
            ApiResponse::success($scores);

        } elseif ($action === 'claim_scorer') {
            $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
            if (!$matchId) throw new Exception('Match ID required');
            if (!GameMatch::canClaimScorer($playerId, $matchId)) ApiResponse::forbidden('Cannot claim scorer');
            if (!GameMatch::claimScorer($matchId, $playerId)) throw new Exception('Failed to claim scorer role');
            ApiResponse::success();

        } elseif ($action === 'release_scorer') {
            $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
            if (!$matchId) throw new Exception('Match ID required');
            if (!GameMatch::releaseScorer($matchId, $playerId)) throw new Exception('Cannot release scorer - not authorized');
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

        $matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$matchId) throw new Exception('Match ID required');
        if (!GameMatch::canDelete($playerId, $matchId)) ApiResponse::forbidden('Not authorized to delete this match');

        GameMatch::delete($matchId);
        ApiResponse::success();

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
