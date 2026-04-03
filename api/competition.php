<?php
/**
 * Competition API
 *
 * GET  action=list&club_id=X           List competitions for a club
 * GET  action=get&id=X                 Get competition details
 * GET  action=fixtures&id=X&stage=Y    Get fixtures by stage
 * GET  action=standings&id=X           Get standings
 * GET  action=bracket&id=X             Get bracket structure
 * GET  action=participants&id=X        Get participants list
 *
 * POST action=create                   Create competition
 * POST action=update                   Update competition (draft only)
 * POST action=register                 Register participant
 * POST action=withdraw                 Withdraw participant
 * POST action=open_registration        Open registration
 * POST action=close_registration       Close registration
 * POST action=generate_fixtures        Generate fixtures/bracket
 * POST action=start                    Start competition
 * POST action=create_match             Create match for fixture
 * POST action=walkover                 Record walkover
 * POST action=complete                 Complete competition
 * POST action=cancel                   Cancel competition
 *
 * DELETE id=X                          Delete competition (draft only)
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionParticipant.php';
require_once __DIR__ . '/../includes/CompetitionFixture.php';
require_once __DIR__ . '/../includes/CompetitionStandings.php';
require_once __DIR__ . '/../includes/CompetitionRoundRobin.php';
require_once __DIR__ . '/../includes/CompetitionBracket.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET Requests ==========
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            $clubId = (int)($_GET['club_id'] ?? 0);
            if (!$clubId) {
                ApiResponse::error('club_id required', 400);
            }

            // Require login for viewing club competitions
            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            $playerId = Auth::id();
            if (!ClubMember::isMember($clubId, $playerId)) {
                ApiResponse::forbidden('Not a club member');
            }

            $status = $_GET['status'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 20), 50);

            $competitions = Competition::listByClub($clubId, $status, $limit);
            ApiResponse::success(['competitions' => $competitions]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            $competition = Competition::getWithDetails($id);
            if (!$competition) {
                ApiResponse::notFound('Competition not found');
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            ApiResponse::success(['competition' => $competition]);
            break;

        case 'fixtures':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            $stage = $_GET['stage'] ?? null;
            $fixtures = CompetitionFixture::listByCompetition($id, $stage);
            ApiResponse::success(['fixtures' => $fixtures]);
            break;

        case 'standings':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

            if ($groupId) {
                $standings = CompetitionStandings::getStandings($id, $groupId);
            } else {
                $standings = CompetitionStandings::getAllGroupStandings($id);
            }

            ApiResponse::success(['standings' => $standings]);
            break;

        case 'bracket':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            $bracket = CompetitionBracket::getBracketStructure($id);
            ApiResponse::success(['bracket' => $bracket]);
            break;

        case 'participants':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            $participants = CompetitionParticipant::listByCompetition($id);
            ApiResponse::success(['participants' => $participants]);
            break;

        case 'groups':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Auth::check()) {
                ApiResponse::unauthorized();
            }

            if (!Competition::canView(Auth::id(), $id)) {
                ApiResponse::forbidden('Not a club member');
            }

            $groups = CompetitionRoundRobin::getGroups($id);
            ApiResponse::success(['groups' => $groups]);
            break;

        default:
            ApiResponse::error('Invalid action', 400);
    }
}

// ========== POST Requests ==========
elseif ($method === 'POST') {
    if (!Auth::check()) {
        ApiResponse::unauthorized();
    }

    $playerId = Auth::id();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'create':
            $clubId = (int)($data['club_id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $format = $data['format'] ?? '';
            $gameType = $data['game_type'] ?? '';

            if (!$clubId || !$name || !$format || !$gameType) {
                ApiResponse::error('club_id, name, format, and game_type required', 400);
            }

            if (!in_array($format, Competition::FORMATS)) {
                ApiResponse::error('Invalid format', 400);
            }

            if (!isset(GameMatch::GAME_TYPES[$gameType])) {
                ApiResponse::error('Invalid game_type', 400);
            }

            if (!Competition::canCreate($playerId, $clubId)) {
                ApiResponse::forbidden('Not authorized to create competitions');
            }

            $options = [];
            if (isset($data['description'])) $options['description'] = $data['description'];
            if (isset($data['bowls_per_player'])) $options['bowls_per_player'] = (int)$data['bowls_per_player'];
            if (isset($data['scoring_mode'])) $options['scoring_mode'] = $data['scoring_mode'];
            if (isset($data['target_score'])) $options['target_score'] = (int)$data['target_score'];
            if (isset($data['max_participants'])) $options['max_participants'] = (int)$data['max_participants'];
            if (isset($data['knockout_qualifiers'])) $options['knockout_qualifiers'] = (int)$data['knockout_qualifiers'];
            if (isset($data['group_count'])) $options['group_count'] = (int)$data['group_count'];
            if (isset($data['registration_opens'])) $options['registration_opens'] = $data['registration_opens'];
            if (isset($data['registration_closes'])) $options['registration_closes'] = $data['registration_closes'];

            $id = Competition::create($clubId, $playerId, $name, $format, $gameType, $options);
            ApiResponse::success(['id' => $id]);
            break;

        case 'update':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            unset($data['action'], $data['id']);
            $updated = Competition::update($id, $data);

            if (!$updated) {
                ApiResponse::error('Update failed (competition may not be in draft status)', 400);
            }

            ApiResponse::success(['updated' => true]);
            break;

        case 'register':
            $competitionId = (int)($data['competition_id'] ?? 0);
            $players = $data['players'] ?? [];
            $teamName = $data['team_name'] ?? null;

            if (!$competitionId || empty($players)) {
                ApiResponse::error('competition_id and players required', 400);
            }

            // Validate registration is open
            if (!Competition::canRegister($playerId, $competitionId)) {
                ApiResponse::forbidden('Registration not available');
            }

            // Validate players
            $validation = CompetitionParticipant::validatePlayers($competitionId, $players);
            if (!$validation['valid']) {
                ApiResponse::error($validation['error'], 400);
            }

            $participantId = CompetitionParticipant::register($competitionId, $players, $teamName);
            ApiResponse::success(['participant_id' => $participantId]);
            break;

        case 'withdraw':
            $participantId = (int)($data['participant_id'] ?? 0);
            if (!$participantId) {
                ApiResponse::error('participant_id required', 400);
            }

            $participant = CompetitionParticipant::find($participantId);
            if (!$participant) {
                ApiResponse::notFound('Participant not found');
            }

            // Check authorization (participant player or competition admin)
            $isPlayer = CompetitionParticipant::isPlayerRegistered($participant['competition_id'], $playerId);
            $isAdmin = Competition::canManage($playerId, $participant['competition_id']);

            if (!$isPlayer && !$isAdmin) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!CompetitionParticipant::withdraw($participantId)) {
                ApiResponse::error('Cannot withdraw (matches may have started)', 400);
            }

            ApiResponse::success(['withdrawn' => true]);
            break;

        case 'open_registration':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!Competition::openRegistration($id)) {
                ApiResponse::error('Could not open registration', 400);
            }

            ApiResponse::success(['registration_opened' => true]);
            break;

        case 'close_registration':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!Competition::closeRegistration($id)) {
                ApiResponse::error('Could not close registration', 400);
            }

            ApiResponse::success(['registration_closed' => true]);
            break;

        case 'generate_fixtures':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            $competition = Competition::find($id);
            if (!$competition) {
                ApiResponse::notFound('Competition not found');
            }

            $participantCount = Competition::getParticipantCount($id);
            if ($participantCount < 2) {
                ApiResponse::error('At least 2 participants required', 400);
            }

            // Auto-seed participants
            CompetitionParticipant::autoSeed($id);

            $fixtureIds = [];

            switch ($competition['format']) {
                case 'round_robin':
                    if ($competition['group_count'] && $competition['group_count'] > 1) {
                        CompetitionRoundRobin::createGroups($id, $competition['group_count']);
                        $fixtureIds = CompetitionRoundRobin::generateAllGroupFixtures($id);
                    } else {
                        $fixtureIds = CompetitionRoundRobin::generateFixtures($id);
                    }
                    break;

                case 'knockout':
                    $fixtureIds = CompetitionBracket::generateBracket($id);
                    break;

                case 'combined':
                    if (!$competition['group_count']) {
                        ApiResponse::error('group_count required for combined format', 400);
                    }
                    CompetitionRoundRobin::createGroups($id, $competition['group_count']);
                    $fixtureIds = CompetitionRoundRobin::generateAllGroupFixtures($id);
                    // Knockout fixtures generated after group stage completes
                    break;
            }

            ApiResponse::success(['fixture_count' => count($fixtureIds)]);
            break;

        case 'generate_knockout':
            // Generate knockout stage after group stage completes (for combined format)
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            $competition = Competition::find($id);
            if (!$competition || $competition['format'] !== 'combined') {
                ApiResponse::error('Only for combined format competitions', 400);
            }

            if (!CompetitionStandings::isGroupStageComplete($id)) {
                ApiResponse::error('Group stage not complete', 400);
            }

            $fixtureIds = CompetitionBracket::generateFromGroupStage($id);
            ApiResponse::success(['fixture_count' => count($fixtureIds)]);
            break;

        case 'start':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!Competition::start($id)) {
                ApiResponse::error('Could not start competition', 400);
            }

            ApiResponse::success(['started' => true]);
            break;

        case 'create_match':
            $fixtureId = (int)($data['fixture_id'] ?? 0);
            if (!$fixtureId) {
                ApiResponse::error('fixture_id required', 400);
            }

            $fixture = CompetitionFixture::find($fixtureId);
            if (!$fixture) {
                ApiResponse::notFound('Fixture not found');
            }

            if (!Competition::canManage($playerId, $fixture['competition_id'])) {
                ApiResponse::forbidden('Not authorized');
            }

            $matchId = CompetitionFixture::createMatch($fixtureId);
            if (!$matchId) {
                ApiResponse::error('Could not create match (participants may not be set)', 400);
            }

            ApiResponse::success(['match_id' => $matchId]);
            break;

        case 'walkover':
            $fixtureId = (int)($data['fixture_id'] ?? 0);
            $winnerId = (int)($data['winner_id'] ?? 0);

            if (!$fixtureId || !$winnerId) {
                ApiResponse::error('fixture_id and winner_id required', 400);
            }

            $fixture = CompetitionFixture::find($fixtureId);
            if (!$fixture) {
                ApiResponse::notFound('Fixture not found');
            }

            if (!Competition::canManage($playerId, $fixture['competition_id'])) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!CompetitionFixture::recordWalkover($fixtureId, $winnerId)) {
                ApiResponse::error('Could not record walkover', 400);
            }

            ApiResponse::success(['walkover_recorded' => true]);
            break;

        case 'complete':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!Competition::complete($id)) {
                ApiResponse::error('Could not complete competition', 400);
            }

            ApiResponse::success(['completed' => true]);
            break;

        case 'cancel':
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ApiResponse::error('id required', 400);
            }

            if (!Competition::canManage($playerId, $id)) {
                ApiResponse::forbidden('Not authorized');
            }

            if (!Competition::cancel($id)) {
                ApiResponse::error('Could not cancel competition', 400);
            }

            ApiResponse::success(['cancelled' => true]);
            break;

        default:
            ApiResponse::error('Invalid action', 400);
    }
}

// ========== DELETE Requests ==========
elseif ($method === 'DELETE') {
    if (!Auth::check()) {
        ApiResponse::unauthorized();
    }

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        ApiResponse::error('id required', 400);
    }

    if (!Competition::canManage(Auth::id(), $id)) {
        ApiResponse::forbidden('Not authorized');
    }

    if (!Competition::delete($id)) {
        ApiResponse::error('Could not delete (competition may not be in draft status)', 400);
    }

    ApiResponse::success(['deleted' => true]);
}

else {
    ApiResponse::error('Method not allowed', 405);
}
