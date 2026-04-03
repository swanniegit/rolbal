<?php
/**
 * Competition Standings - Round Robin standings calculation with tie-breakers
 */

require_once __DIR__ . '/db.php';

class CompetitionStandings {

    // Points for results (configurable per competition in future)
    const POINTS_WIN = 2;
    const POINTS_DRAW = 1;
    const POINTS_LOSS = 0;

    /**
     * Update standings after a fixture is completed
     */
    public static function updateFromFixture(int $fixtureId): bool {
        $db = Database::getInstance();

        // Get fixture details
        $stmt = $db->prepare('
            SELECT f.*, c.format
            FROM competition_fixtures f
            JOIN competitions c ON c.id = f.competition_id
            WHERE f.id = :id
        ');
        $stmt->execute(['id' => $fixtureId]);
        $fixture = $stmt->fetch();

        if (!$fixture || $fixture['status'] !== 'completed') {
            return false;
        }

        // Only update standings for group stage fixtures
        if ($fixture['stage'] !== 'group') {
            return true;
        }

        $p1 = $fixture['participant1_id'];
        $p2 = $fixture['participant2_id'];
        $s1 = (int)$fixture['score1'];
        $s2 = (int)$fixture['score2'];
        $winner = $fixture['winner_id'];

        // Get ends played from linked match
        $endsPlayed = self::getEndsFromMatch($fixture['match_id']);

        $db->beginTransaction();

        try {
            // Update participant 1
            self::updateParticipantStats(
                $fixture['competition_id'],
                $fixture['group_id'],
                $p1,
                $s1, $s2,
                $endsPlayed['team1'] ?? 0, $endsPlayed['team2'] ?? 0,
                $winner === $p1 ? 'win' : ($winner === $p2 ? 'loss' : 'draw')
            );

            // Update participant 2
            self::updateParticipantStats(
                $fixture['competition_id'],
                $fixture['group_id'],
                $p2,
                $s2, $s1,
                $endsPlayed['team2'] ?? 0, $endsPlayed['team1'] ?? 0,
                $winner === $p2 ? 'win' : ($winner === $p1 ? 'loss' : 'draw')
            );

            // Recalculate positions
            self::recalculatePositions($fixture['competition_id'], $fixture['group_id']);

            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update a single participant's stats
     */
    private static function updateParticipantStats(
        int $competitionId,
        ?int $groupId,
        int $participantId,
        int $shotsFor,
        int $shotsAgainst,
        int $endsFor,
        int $endsAgainst,
        string $result
    ): void {
        $db = Database::getInstance();

        $points = match($result) {
            'win' => self::POINTS_WIN,
            'draw' => self::POINTS_DRAW,
            default => self::POINTS_LOSS
        };

        $stmt = $db->prepare('
            UPDATE competition_standings
            SET played = played + 1,
                won = won + :won,
                lost = lost + :lost,
                drawn = drawn + :drawn,
                shots_for = shots_for + :shots_for,
                shots_against = shots_against + :shots_against,
                ends_for = ends_for + :ends_for,
                ends_against = ends_against + :ends_against,
                points = points + :points
            WHERE competition_id = :competition_id
            AND (group_id = :group_id OR (group_id IS NULL AND :group_id2 IS NULL))
            AND participant_id = :participant_id
        ');

        $stmt->execute([
            'competition_id' => $competitionId,
            'group_id' => $groupId,
            'group_id2' => $groupId,
            'participant_id' => $participantId,
            'won' => $result === 'win' ? 1 : 0,
            'lost' => $result === 'loss' ? 1 : 0,
            'drawn' => $result === 'draw' ? 1 : 0,
            'shots_for' => $shotsFor,
            'shots_against' => $shotsAgainst,
            'ends_for' => $endsFor,
            'ends_against' => $endsAgainst,
            'points' => $points
        ]);
    }

    /**
     * Get ends won by each team from a match
     */
    private static function getEndsFromMatch(?int $matchId): array {
        if (!$matchId) {
            return ['team1' => 0, 'team2' => 0];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT scoring_team, COUNT(*) as ends_won
            FROM match_ends
            WHERE match_id = :match_id
            GROUP BY scoring_team
        ');
        $stmt->execute(['match_id' => $matchId]);
        $rows = $stmt->fetchAll();

        $ends = ['team1' => 0, 'team2' => 0];
        foreach ($rows as $row) {
            if ($row['scoring_team'] == 1) {
                $ends['team1'] = (int)$row['ends_won'];
            } else {
                $ends['team2'] = (int)$row['ends_won'];
            }
        }

        return $ends;
    }

    /**
     * Recalculate positions with tie-breakers
     *
     * Tie-breaker order:
     * 1. Points
     * 2. Shot difference (shots_for - shots_against)
     * 3. Shots for
     * 4. Head-to-head (if 2 teams tied)
     * 5. Ends difference
     */
    public static function recalculatePositions(int $competitionId, ?int $groupId = null): void {
        $db = Database::getInstance();

        // Get standings sorted by tie-breakers
        $sql = '
            SELECT id, participant_id, points, shots_for, shots_against,
                   (shots_for - shots_against) as shot_diff,
                   ends_for, ends_against,
                   (ends_for - ends_against) as end_diff
            FROM competition_standings
            WHERE competition_id = :competition_id
        ';
        $params = ['competition_id' => $competitionId];

        if ($groupId !== null) {
            $sql .= ' AND group_id = :group_id';
            $params['group_id'] = $groupId;
        } else {
            $sql .= ' AND group_id IS NULL';
        }

        $sql .= ' ORDER BY points DESC, shot_diff DESC, shots_for DESC, end_diff DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $standings = $stmt->fetchAll();

        // Assign positions
        $stmtUpdate = $db->prepare('UPDATE competition_standings SET position = :pos WHERE id = :id');

        $position = 1;
        foreach ($standings as $s) {
            $stmtUpdate->execute(['pos' => $position++, 'id' => $s['id']]);
        }
    }

    /**
     * Get standings for a competition or group
     */
    public static function getStandings(int $competitionId, ?int $groupId = null): array {
        $db = Database::getInstance();

        $sql = '
            SELECT cs.*,
                   (cs.shots_for - cs.shots_against) as shot_diff,
                   (cs.ends_for - cs.ends_against) as end_diff
            FROM competition_standings cs
            WHERE cs.competition_id = :competition_id
        ';
        $params = ['competition_id' => $competitionId];

        if ($groupId !== null) {
            $sql .= ' AND cs.group_id = :group_id';
            $params['group_id'] = $groupId;
        }

        $sql .= ' ORDER BY cs.position ASC, cs.points DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $standings = $stmt->fetchAll();

        // Add participant info
        require_once __DIR__ . '/CompetitionParticipant.php';
        foreach ($standings as &$s) {
            $participant = CompetitionParticipant::findWithPlayers($s['participant_id']);
            if ($participant) {
                $s['participant_name'] = CompetitionParticipant::getDisplayName($participant);
                $s['players'] = $participant['players'];
            }
        }

        return $standings;
    }

    /**
     * Get standings grouped by group
     */
    public static function getAllGroupStandings(int $competitionId): array {
        require_once __DIR__ . '/CompetitionRoundRobin.php';

        $groups = CompetitionRoundRobin::getGroups($competitionId);
        $result = [];

        foreach ($groups as $group) {
            $result[] = [
                'group' => $group,
                'standings' => self::getStandings($competitionId, $group['id'])
            ];
        }

        return $result;
    }

    /**
     * Get top N participants from each group (for knockout advancement)
     */
    public static function getQualifiers(int $competitionId, int $topN = 2): array {
        require_once __DIR__ . '/CompetitionRoundRobin.php';

        $groups = CompetitionRoundRobin::getGroups($competitionId);
        $qualifiers = [];

        foreach ($groups as $group) {
            $standings = self::getStandings($competitionId, $group['id']);
            $qualifiers[$group['group_name']] = array_slice($standings, 0, $topN);
        }

        return $qualifiers;
    }

    /**
     * Check if group stage is complete
     */
    public static function isGroupStageComplete(int $competitionId): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM competition_fixtures
            WHERE competition_id = :competition_id
            AND stage = "group"
            AND status NOT IN ("completed", "walkover", "cancelled")
        ');
        $stmt->execute(['competition_id' => $competitionId]);

        return (int)$stmt->fetchColumn() === 0;
    }

    /**
     * Reset standings for a competition (use with caution)
     */
    public static function resetStandings(int $competitionId, ?int $groupId = null): bool {
        $db = Database::getInstance();

        $sql = '
            UPDATE competition_standings
            SET played = 0, won = 0, lost = 0, drawn = 0,
                ends_for = 0, ends_against = 0,
                shots_for = 0, shots_against = 0,
                points = 0, position = NULL
            WHERE competition_id = :competition_id
        ';
        $params = ['competition_id' => $competitionId];

        if ($groupId !== null) {
            $sql .= ' AND group_id = :group_id';
            $params['group_id'] = $groupId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return true;
    }

    /**
     * Recalculate all standings from fixtures (recovery function)
     */
    public static function recalculateAllFromFixtures(int $competitionId): bool {
        $db = Database::getInstance();

        // Reset first
        self::resetStandings($competitionId);

        // Get all completed group fixtures
        $stmt = $db->prepare('
            SELECT id FROM competition_fixtures
            WHERE competition_id = :competition_id
            AND stage = "group"
            AND status = "completed"
        ');
        $stmt->execute(['competition_id' => $competitionId]);
        $fixtures = $stmt->fetchAll();

        // Replay each fixture
        foreach ($fixtures as $f) {
            self::updateFromFixture($f['id']);
        }

        return true;
    }
}
