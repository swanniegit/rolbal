<?php
/**
 * Competition Round Robin - Scheduling using Circle Method
 *
 * Generates round robin fixtures where every participant plays every other participant.
 */

require_once __DIR__ . '/db.php';

class CompetitionRoundRobin {

    /**
     * Generate round robin fixtures for a competition
     *
     * Uses the Circle Method (aka Berger tables):
     * - N participants need N-1 rounds (if N is even) or N rounds (if odd, with BYE)
     * - Each round has N/2 matches
     * - Participant 1 is fixed, others rotate
     *
     * @param int $competitionId Competition ID
     * @param int|null $groupId Optional group ID (for combined format)
     * @return array Created fixture IDs
     */
    public static function generateFixtures(int $competitionId, ?int $groupId = null): array {
        $db = Database::getInstance();

        // Get participants
        if ($groupId) {
            require_once __DIR__ . '/CompetitionParticipant.php';
            $participants = CompetitionParticipant::getByGroup($groupId);
        } else {
            require_once __DIR__ . '/CompetitionParticipant.php';
            $participants = CompetitionParticipant::listByCompetition($competitionId);
        }

        $n = count($participants);
        if ($n < 2) {
            return [];
        }

        // Extract IDs
        $ids = array_column($participants, 'id');

        // Add BYE if odd number
        $hasBye = ($n % 2 !== 0);
        if ($hasBye) {
            $ids[] = null; // null represents BYE
            $n++;
        }

        $rounds = $n - 1;
        $matchesPerRound = $n / 2;

        $fixtureIds = [];

        $db->beginTransaction();

        try {
            $stmt = $db->prepare('
                INSERT INTO competition_fixtures
                (competition_id, stage, round_number, group_id, participant1_id, participant2_id, status)
                VALUES (:competition_id, :stage, :round_number, :group_id, :p1, :p2, "pending")
            ');

            for ($round = 1; $round <= $rounds; $round++) {
                $matchups = self::getRoundMatchups($ids, $round);

                foreach ($matchups as $match) {
                    // Skip BYE matches
                    if ($match[0] === null || $match[1] === null) {
                        continue;
                    }

                    $stmt->execute([
                        'competition_id' => $competitionId,
                        'stage' => 'group',
                        'round_number' => $round,
                        'group_id' => $groupId,
                        'p1' => $match[0],
                        'p2' => $match[1]
                    ]);
                    $fixtureIds[] = (int)$db->lastInsertId();
                }
            }

            // Initialize standings for all participants
            self::initializeStandings($competitionId, $groupId, $participants);

            $db->commit();
            return $fixtureIds;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get matchups for a specific round using circle method
     */
    private static function getRoundMatchups(array $ids, int $round): array {
        $n = count($ids);

        // Create rotation array - first element fixed, rest rotate
        $rotation = $ids;

        // Rotate (round - 1) times
        for ($i = 1; $i < $round; $i++) {
            $rotation = self::rotateArray($rotation);
        }

        $matchups = [];
        $half = $n / 2;

        for ($i = 0; $i < $half; $i++) {
            $home = $rotation[$i];
            $away = $rotation[$n - 1 - $i];

            // Alternate home/away for balance
            if ($round % 2 === 0) {
                $matchups[] = [$away, $home];
            } else {
                $matchups[] = [$home, $away];
            }
        }

        return $matchups;
    }

    /**
     * Rotate array keeping first element fixed
     */
    private static function rotateArray(array $arr): array {
        if (count($arr) <= 1) {
            return $arr;
        }

        $first = $arr[0];
        $last = $arr[count($arr) - 1];

        // Shift all elements except first
        $rotated = [$first];
        $rotated[] = $last;

        for ($i = 1; $i < count($arr) - 1; $i++) {
            $rotated[] = $arr[$i];
        }

        return $rotated;
    }

    /**
     * Initialize standings for all participants
     */
    private static function initializeStandings(int $competitionId, ?int $groupId, array $participants): void {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT IGNORE INTO competition_standings
            (competition_id, group_id, participant_id, played, won, lost, drawn, points)
            VALUES (:competition_id, :group_id, :participant_id, 0, 0, 0, 0, 0)
        ');

        foreach ($participants as $p) {
            $stmt->execute([
                'competition_id' => $competitionId,
                'group_id' => $groupId,
                'participant_id' => $p['id']
            ]);
        }
    }

    /**
     * Generate fixtures for all groups in a competition
     */
    public static function generateAllGroupFixtures(int $competitionId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM competition_groups WHERE competition_id = :competition_id');
        $stmt->execute(['competition_id' => $competitionId]);
        $groups = $stmt->fetchAll();

        $allFixtures = [];
        foreach ($groups as $group) {
            $fixtures = self::generateFixtures($competitionId, $group['id']);
            $allFixtures = array_merge($allFixtures, $fixtures);
        }

        return $allFixtures;
    }

    /**
     * Create groups for a competition and distribute participants
     *
     * @param int $competitionId Competition ID
     * @param int $groupCount Number of groups
     * @return array Group IDs
     */
    public static function createGroups(int $competitionId, int $groupCount): array {
        $db = Database::getInstance();

        require_once __DIR__ . '/CompetitionParticipant.php';
        $participants = CompetitionParticipant::listByCompetition($competitionId);

        if (count($participants) < $groupCount * 2) {
            throw new Exception('Not enough participants for ' . $groupCount . ' groups');
        }

        $db->beginTransaction();

        try {
            // Create groups
            $groupIds = [];
            $groupNames = range('A', chr(ord('A') + $groupCount - 1));

            $stmtGroup = $db->prepare('
                INSERT INTO competition_groups (competition_id, group_name, group_number)
                VALUES (:competition_id, :name, :number)
            ');

            for ($i = 0; $i < $groupCount; $i++) {
                $stmtGroup->execute([
                    'competition_id' => $competitionId,
                    'name' => 'Group ' . $groupNames[$i],
                    'number' => $i + 1
                ]);
                $groupIds[] = (int)$db->lastInsertId();
            }

            // Distribute participants snake-style based on seed
            // Seeds 1,2,3,4 go to groups 1,2,3,4
            // Seeds 5,6,7,8 go to groups 4,3,2,1
            // etc.
            $stmtAssign = $db->prepare('
                INSERT INTO competition_group_participants (group_id, participant_id)
                VALUES (:group_id, :participant_id)
            ');

            $direction = 1;
            $groupIndex = 0;

            foreach ($participants as $p) {
                $stmtAssign->execute([
                    'group_id' => $groupIds[$groupIndex],
                    'participant_id' => $p['id']
                ]);

                // Snake distribution
                $groupIndex += $direction;
                if ($groupIndex >= $groupCount) {
                    $groupIndex = $groupCount - 1;
                    $direction = -1;
                } elseif ($groupIndex < 0) {
                    $groupIndex = 0;
                    $direction = 1;
                }
            }

            $db->commit();
            return $groupIds;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get groups for a competition
     */
    public static function getGroups(int $competitionId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT cg.*,
                   (SELECT COUNT(*) FROM competition_group_participants WHERE group_id = cg.id) as participant_count
            FROM competition_groups cg
            WHERE cg.competition_id = :competition_id
            ORDER BY cg.group_number
        ');
        $stmt->execute(['competition_id' => $competitionId]);

        return $stmt->fetchAll();
    }

    /**
     * Get a specific group with participants
     */
    public static function getGroupWithParticipants(int $groupId): ?array {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM competition_groups WHERE id = :id');
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch();

        if (!$group) {
            return null;
        }

        require_once __DIR__ . '/CompetitionParticipant.php';
        $group['participants'] = CompetitionParticipant::getByGroup($groupId);

        return $group;
    }

    /**
     * Calculate total rounds needed for N participants
     */
    public static function getRoundCount(int $participantCount): int {
        if ($participantCount < 2) {
            return 0;
        }
        return ($participantCount % 2 === 0) ? $participantCount - 1 : $participantCount;
    }

    /**
     * Calculate total matches needed for N participants
     */
    public static function getMatchCount(int $participantCount): int {
        if ($participantCount < 2) {
            return 0;
        }
        return ($participantCount * ($participantCount - 1)) / 2;
    }
}
