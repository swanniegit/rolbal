<?php
/**
 * Competition Fixture Model - Fixture management, match linking, result recording
 */

require_once __DIR__ . '/db.php';

class CompetitionFixture {

    const STAGES = [
        'group' => 'Group Stage',
        'play_in' => 'Play-In',
        'round_of_64' => 'Round of 64',
        'round_of_32' => 'Round of 32',
        'round_of_16' => 'Round of 16',
        'quarter_final' => 'Quarter Final',
        'semi_final' => 'Semi Final',
        'third_place' => '3rd Place',
        'final' => 'Final'
    ];

    // ========== Core CRUD ==========

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT f.*, c.format, c.club_id, c.game_type, c.bowls_per_player,
                   c.scoring_mode, c.target_score
            FROM competition_fixtures f
            JOIN competitions c ON c.id = f.competition_id
            WHERE f.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findWithDetails(int $id): ?array {
        $fixture = self::find($id);
        if (!$fixture) {
            return null;
        }

        require_once __DIR__ . '/CompetitionParticipant.php';

        if ($fixture['participant1_id']) {
            $p1 = CompetitionParticipant::findWithPlayers($fixture['participant1_id']);
            $fixture['participant1'] = $p1;
            $fixture['participant1_name'] = $p1 ? CompetitionParticipant::getDisplayName($p1) : 'TBD';
        } else {
            $fixture['participant1_name'] = self::getPendingLabel($fixture, 1);
        }

        if ($fixture['participant2_id']) {
            $p2 = CompetitionParticipant::findWithPlayers($fixture['participant2_id']);
            $fixture['participant2'] = $p2;
            $fixture['participant2_name'] = $p2 ? CompetitionParticipant::getDisplayName($p2) : 'TBD';
        } else {
            $fixture['participant2_name'] = self::getPendingLabel($fixture, 2);
        }

        return $fixture;
    }

    /**
     * Get label for pending participant (e.g., "Winner of QF1")
     */
    private static function getPendingLabel(array $fixture, int $slot): string {
        $sourceFixtureId = $slot === 1 ? $fixture['winner_from_fixture_1'] : $fixture['winner_from_fixture_2'];

        if (!$sourceFixtureId) {
            return 'TBD';
        }

        $source = self::find($sourceFixtureId);
        if (!$source) {
            return 'TBD';
        }

        $stageName = self::STAGES[$source['stage']] ?? $source['stage'];
        return "Winner of " . $stageName . " #" . $source['bracket_position'];
    }

    public static function create(array $data): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO competition_fixtures (
                competition_id, stage, round_number, bracket_position, group_id,
                participant1_id, participant2_id, winner_from_fixture_1, winner_from_fixture_2,
                scheduled_at, rink_number, status
            ) VALUES (
                :competition_id, :stage, :round_number, :bracket_position, :group_id,
                :p1, :p2, :wf1, :wf2, :scheduled_at, :rink_number, :status
            )
        ');

        $stmt->execute([
            'competition_id' => $data['competition_id'],
            'stage' => $data['stage'],
            'round_number' => $data['round_number'] ?? 1,
            'bracket_position' => $data['bracket_position'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'p1' => $data['participant1_id'] ?? null,
            'p2' => $data['participant2_id'] ?? null,
            'wf1' => $data['winner_from_fixture_1'] ?? null,
            'wf2' => $data['winner_from_fixture_2'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'rink_number' => $data['rink_number'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ]);

        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getInstance();

        $allowed = ['scheduled_at', 'status', 'participant1_id', 'participant2_id', 'rink_number'];
        $updates = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE competition_fixtures SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Record detailed match score with For/Against shots
     * Used for section-based round robin scoring
     *
     * @param int $fixtureId
     * @param int $participant1For Shots scored by participant 1
     * @param int $participant2For Shots scored by participant 2
     * @return bool
     */
    public static function recordDetailedScore(int $fixtureId, int $participant1For, int $participant2For): bool {
        $fixture = self::find($fixtureId);
        if (!$fixture) {
            return false;
        }

        require_once __DIR__ . '/Competition.php';

        $db = Database::getInstance();

        // Calculate points using Competition's point system
        $drawAllowed = Competition::isDrawAllowed($fixture['game_type']);
        $points1 = Competition::calculatePoints($participant1For, $participant2For, $drawAllowed);
        $points2 = Competition::calculatePoints($participant2For, $participant1For, $drawAllowed);

        // Determine winner (null for draw)
        $winnerId = null;
        if ($participant1For > $participant2For) {
            $winnerId = $fixture['participant1_id'];
        } elseif ($participant2For > $participant1For) {
            $winnerId = $fixture['participant2_id'];
        }

        $stmt = $db->prepare('
            UPDATE competition_fixtures
            SET status = "completed",
                winner_id = :winner_id,
                score1 = :points1,
                score2 = :points2,
                participant1_for = :p1_for,
                participant1_against = :p1_against,
                participant2_for = :p2_for,
                participant2_against = :p2_against
            WHERE id = :id
        ');

        $result = $stmt->execute([
            'id' => $fixtureId,
            'winner_id' => $winnerId,
            'points1' => $points1,
            'points2' => $points2,
            'p1_for' => $participant1For,
            'p1_against' => $participant2For,
            'p2_for' => $participant2For,
            'p2_against' => $participant1For
        ]);

        if (!$result) {
            return false;
        }

        // Update standings for group stage
        if ($fixture['stage'] === 'group') {
            require_once __DIR__ . '/CompetitionStandings.php';
            CompetitionStandings::updateFromDetailedScore($fixtureId);
        }

        // Progress winner in knockout (if applicable)
        if ($winnerId && in_array($fixture['stage'], ['play_in', 'round_of_64', 'round_of_32', 'round_of_16', 'quarter_final', 'semi_final'])) {
            self::progressWinner($fixtureId, $winnerId);
        }

        // Check if competition is complete
        self::checkCompetitionComplete($fixture['competition_id']);

        return true;
    }

    /**
     * Get detailed fixture result (for section card display)
     */
    public static function getDetailedResult(int $fixtureId): ?array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.id, f.participant1_id, f.participant2_id,
                   f.participant1_for, f.participant1_against,
                   f.participant2_for, f.participant2_against,
                   f.score1 as points1, f.score2 as points2,
                   f.winner_id, f.status, f.rink_number
            FROM competition_fixtures f
            WHERE f.id = :id
        ');
        $stmt->execute(['id' => $fixtureId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all match results for a participant in a section (for card display)
     * Returns results against each opponent with For/Against/Agg/Points
     */
    public static function getParticipantSectionResults(int $participantId, int $groupId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.id, f.participant1_id, f.participant2_id,
                   f.participant1_for, f.participant1_against,
                   f.participant2_for, f.participant2_against,
                   f.score1, f.score2, f.winner_id, f.status
            FROM competition_fixtures f
            WHERE f.group_id = :group_id
            AND (f.participant1_id = :p1 OR f.participant2_id = :p2)
            AND f.status IN ("completed", "walkover")
            ORDER BY f.round_number
        ');
        $stmt->execute(['group_id' => $groupId, 'p1' => $participantId, 'p2' => $participantId]);
        $fixtures = $stmt->fetchAll();

        $results = [];
        foreach ($fixtures as $f) {
            $isParticipant1 = ((int)$f['participant1_id'] === $participantId);

            $opponentId = $isParticipant1 ? $f['participant2_id'] : $f['participant1_id'];
            $shotsFor = $isParticipant1 ? (int)$f['participant1_for'] : (int)$f['participant2_for'];
            $shotsAgainst = $isParticipant1 ? (int)$f['participant1_against'] : (int)$f['participant2_against'];
            $points = $isParticipant1 ? (int)$f['score1'] : (int)$f['score2'];

            $results[] = [
                'fixture_id' => $f['id'],
                'opponent_id' => $opponentId,
                'for' => $shotsFor,
                'against' => $shotsAgainst,
                'agg' => $shotsFor - $shotsAgainst,
                'points' => $points
            ];
        }

        return $results;
    }

    // ========== Match Integration ==========

    /**
     * Create a live match for this fixture
     */
    public static function createMatch(int $fixtureId): ?int {
        $fixture = self::findWithDetails($fixtureId);
        if (!$fixture) {
            return null;
        }

        if (!$fixture['participant1_id'] || !$fixture['participant2_id']) {
            return null; // Can't create match until both participants are known
        }

        if ($fixture['match_id']) {
            return $fixture['match_id']; // Match already exists
        }

        require_once __DIR__ . '/GameMatch.php';
        require_once __DIR__ . '/CompetitionParticipant.php';

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Create the match
            $matchId = GameMatch::create(
                $fixture['club_id'],
                $fixture['participant1']['players'][0]['player_id'], // Creator = skip of team 1
                $fixture['game_type'],
                $fixture['bowls_per_player'],
                $fixture['scoring_mode'],
                $fixture['target_score']
            );

            // Create teams
            $p1 = $fixture['participant1'];
            $p2 = $fixture['participant2'];

            $team1Id = GameMatch::createTeam($matchId, 1, CompetitionParticipant::getDisplayName($p1));
            $team2Id = GameMatch::createTeam($matchId, 2, CompetitionParticipant::getDisplayName($p2));

            // Add players
            foreach ($p1['players'] as $player) {
                GameMatch::addPlayer($team1Id, $player['position'], $player['player_name'], $player['player_id']);
            }

            foreach ($p2['players'] as $player) {
                GameMatch::addPlayer($team2Id, $player['position'], $player['player_name'], $player['player_id']);
            }

            // Link match to fixture
            $stmt = $db->prepare('UPDATE competition_fixtures SET match_id = :match_id WHERE id = :id');
            $stmt->execute(['match_id' => $matchId, 'id' => $fixtureId]);

            $db->commit();
            return $matchId;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Called when a linked match is completed
     * Updates fixture result with detailed For/Against scores and progresses winner in bracket
     */
    public static function onMatchComplete(int $matchId): bool {
        $db = Database::getInstance();

        // Find fixture linked to this match
        $stmt = $db->prepare('SELECT id FROM competition_fixtures WHERE match_id = :match_id');
        $stmt->execute(['match_id' => $matchId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false; // Not a competition match
        }

        $fixtureId = $row['id'];
        $fixture = self::find($fixtureId);

        // Get match scores (these are the actual shots/points scored)
        require_once __DIR__ . '/GameMatch.php';
        $scores = GameMatch::getScores($matchId);

        if (!$scores) {
            return false;
        }

        // The match scores are the shots/points (For/Against)
        $participant1For = $scores['team1_score'];
        $participant2For = $scores['team2_score'];

        require_once __DIR__ . '/Competition.php';

        // Calculate competition points (2/1/0) based on match result
        $drawAllowed = Competition::isDrawAllowed($fixture['game_type']);
        $points1 = Competition::calculatePoints($participant1For, $participant2For, $drawAllowed);
        $points2 = Competition::calculatePoints($participant2For, $participant1For, $drawAllowed);

        // Determine winner
        $winnerId = null;
        if ($participant1For > $participant2For) {
            $winnerId = $fixture['participant1_id'];
        } elseif ($participant2For > $participant1For) {
            $winnerId = $fixture['participant2_id'];
        }
        // Draw: no winner (for round robin this is fine)

        // Update fixture with detailed scores
        $stmt = $db->prepare('
            UPDATE competition_fixtures
            SET status = "completed",
                winner_id = :winner_id,
                score1 = :points1,
                score2 = :points2,
                participant1_for = :p1_for,
                participant1_against = :p1_against,
                participant2_for = :p2_for,
                participant2_against = :p2_against
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $fixtureId,
            'winner_id' => $winnerId,
            'points1' => $points1,
            'points2' => $points2,
            'p1_for' => $participant1For,
            'p1_against' => $participant2For,
            'p2_for' => $participant2For,
            'p2_against' => $participant1For
        ]);

        // Update standings for group stage using detailed scores
        if ($fixture['stage'] === 'group') {
            require_once __DIR__ . '/CompetitionStandings.php';
            CompetitionStandings::updateFromDetailedScore($fixtureId);
        }

        // Progress winner in knockout
        if ($winnerId && in_array($fixture['stage'], ['play_in', 'round_of_64', 'round_of_32', 'round_of_16', 'quarter_final', 'semi_final'])) {
            self::progressWinner($fixtureId, $winnerId);
        }

        // Check if competition is complete
        self::checkCompetitionComplete($fixture['competition_id']);

        return true;
    }

    /**
     * Progress winner to next round fixture
     */
    private static function progressWinner(int $fixtureId, int $winnerId): void {
        $db = Database::getInstance();

        // Find fixtures where this fixture's winner goes
        $stmt = $db->prepare('
            SELECT id, winner_from_fixture_1, winner_from_fixture_2
            FROM competition_fixtures
            WHERE winner_from_fixture_1 = :fixture_id OR winner_from_fixture_2 = :fixture_id2
        ');
        $stmt->execute(['fixture_id' => $fixtureId, 'fixture_id2' => $fixtureId]);
        $nextFixtures = $stmt->fetchAll();

        foreach ($nextFixtures as $next) {
            $field = ($next['winner_from_fixture_1'] == $fixtureId) ? 'participant1_id' : 'participant2_id';

            $stmtUpdate = $db->prepare("UPDATE competition_fixtures SET $field = :winner_id WHERE id = :id");
            $stmtUpdate->execute(['winner_id' => $winnerId, 'id' => $next['id']]);
        }
    }

    /**
     * Record a walkover (forfeit)
     * Winner gets default score (e.g., 21), loser gets 0
     */
    public static function recordWalkover(int $fixtureId, int $winnerId, int $defaultScore = 21): bool {
        $db = Database::getInstance();

        $fixture = self::find($fixtureId);
        if (!$fixture) {
            return false;
        }

        // Validate winner is a participant
        if ($winnerId !== $fixture['participant1_id'] && $winnerId !== $fixture['participant2_id']) {
            return false;
        }

        require_once __DIR__ . '/Competition.php';

        // Set For/Against scores (winner gets default score, loser gets 0)
        $isParticipant1Winner = ($winnerId === $fixture['participant1_id']);
        $participant1For = $isParticipant1Winner ? $defaultScore : 0;
        $participant2For = $isParticipant1Winner ? 0 : $defaultScore;

        // Points: winner gets 2, loser gets 0
        $points1 = $isParticipant1Winner ? Competition::POINTS_WIN : Competition::POINTS_LOSS;
        $points2 = $isParticipant1Winner ? Competition::POINTS_LOSS : Competition::POINTS_WIN;

        $stmt = $db->prepare('
            UPDATE competition_fixtures
            SET status = "walkover",
                winner_id = :winner_id,
                score1 = :points1,
                score2 = :points2,
                participant1_for = :p1_for,
                participant1_against = :p1_against,
                participant2_for = :p2_for,
                participant2_against = :p2_against
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $fixtureId,
            'winner_id' => $winnerId,
            'points1' => $points1,
            'points2' => $points2,
            'p1_for' => $participant1For,
            'p1_against' => $participant2For,
            'p2_for' => $participant2For,
            'p2_against' => $participant1For
        ]);

        // Update standings for group stage using detailed scores
        if ($fixture['stage'] === 'group') {
            require_once __DIR__ . '/CompetitionStandings.php';
            CompetitionStandings::updateFromDetailedScore($fixtureId);
        }

        // Progress winner
        if (in_array($fixture['stage'], ['play_in', 'round_of_64', 'round_of_32', 'round_of_16', 'quarter_final', 'semi_final'])) {
            self::progressWinner($fixtureId, $winnerId);
        }

        return true;
    }

    /**
     * Check if competition is complete (all fixtures done)
     */
    private static function checkCompetitionComplete(int $competitionId): void {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM competition_fixtures
            WHERE competition_id = :competition_id
            AND status NOT IN ("completed", "walkover", "cancelled")
        ');
        $stmt->execute(['competition_id' => $competitionId]);

        if ((int)$stmt->fetchColumn() === 0) {
            require_once __DIR__ . '/Competition.php';
            Competition::complete($competitionId);
        }
    }

    // ========== Queries ==========

    public static function listByCompetition(int $competitionId, ?string $stage = null): array {
        $db = Database::getInstance();

        $sql = '
            SELECT f.*
            FROM competition_fixtures f
            WHERE f.competition_id = :competition_id
        ';
        $params = ['competition_id' => $competitionId];

        if ($stage) {
            $sql .= ' AND f.stage = :stage';
            $params['stage'] = $stage;
        }

        $sql .= ' ORDER BY f.stage, f.round_number, f.bracket_position';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $fixtures = $stmt->fetchAll();

        // Add participant names
        require_once __DIR__ . '/CompetitionParticipant.php';
        foreach ($fixtures as &$f) {
            $f['participant1_name'] = 'TBD';
            $f['participant2_name'] = 'TBD';

            if ($f['participant1_id']) {
                $p1 = CompetitionParticipant::findWithPlayers($f['participant1_id']);
                if ($p1) {
                    $f['participant1_name'] = CompetitionParticipant::getDisplayName($p1);
                }
            }

            if ($f['participant2_id']) {
                $p2 = CompetitionParticipant::findWithPlayers($f['participant2_id']);
                if ($p2) {
                    $f['participant2_name'] = CompetitionParticipant::getDisplayName($p2);
                }
            }
        }

        return $fixtures;
    }

    public static function listByGroup(int $groupId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.*
            FROM competition_fixtures f
            WHERE f.group_id = :group_id
            ORDER BY f.round_number, f.id
        ');
        $stmt->execute(['group_id' => $groupId]);

        return $stmt->fetchAll();
    }

    public static function getUpcoming(int $competitionId, int $limit = 10): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.*
            FROM competition_fixtures f
            WHERE f.competition_id = :competition_id
            AND f.status IN ("pending", "scheduled")
            AND f.participant1_id IS NOT NULL
            AND f.participant2_id IS NOT NULL
            ORDER BY f.scheduled_at ASC, f.stage, f.round_number
            LIMIT :limit
        ');
        $stmt->bindValue('competition_id', $competitionId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getLive(int $competitionId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.*
            FROM competition_fixtures f
            WHERE f.competition_id = :competition_id
            AND f.status = "live"
        ');
        $stmt->execute(['competition_id' => $competitionId]);

        return $stmt->fetchAll();
    }

    public static function getByParticipant(int $participantId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.*
            FROM competition_fixtures f
            WHERE f.participant1_id = :p1 OR f.participant2_id = :p2
            ORDER BY f.stage, f.round_number
        ');
        $stmt->execute(['p1' => $participantId, 'p2' => $participantId]);

        return $stmt->fetchAll();
    }

    public static function findByMatch(int $matchId): ?array {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM competition_fixtures WHERE match_id = :match_id');
        $stmt->execute(['match_id' => $matchId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getStageName(string $stage): string {
        return self::STAGES[$stage] ?? $stage;
    }
}
