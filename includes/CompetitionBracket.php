<?php
/**
 * Competition Bracket - Knockout bracket generation with play-ins
 *
 * Handles single-elimination brackets for any number of participants,
 * using play-in rounds for non-power-of-2 sizes.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/CompetitionFixture.php';

class CompetitionBracket {

    /**
     * Generate knockout bracket for a competition
     *
     * Algorithm for N participants:
     * 1. Find next power of 2 (P) >= N
     * 2. Main bracket needs P/2 participants in Round 1
     * 3. Excess = N - P/2
     * 4. If excess > 0:
     *    - Top (P/2 - excess) seeds get byes
     *    - Bottom 2*excess seeds play in play-in round
     *    - Play-in winners join bye recipients in Round 1
     *
     * Example: 12 participants
     * - P = 16, Round 1 needs 8
     * - Excess = 12 - 8 = 4
     * - Seeds 1-4: Bye to Round 1
     * - Seeds 5-8: vs Play-in winners
     * - Seeds 9-12: Play-in round (4 matches)
     *
     * @param int $competitionId Competition ID
     * @param array|null $participants Optional pre-sorted participants (for combined format)
     * @return array Created fixture IDs
     */
    public static function generateBracket(int $competitionId, ?array $participants = null): array {
        require_once __DIR__ . '/CompetitionParticipant.php';

        if ($participants === null) {
            $participants = CompetitionParticipant::listByCompetition($competitionId);
        }

        $n = count($participants);
        if ($n < 2) {
            return [];
        }

        // Sort by seed
        usort($participants, function($a, $b) {
            return ($a['seed'] ?? PHP_INT_MAX) - ($b['seed'] ?? PHP_INT_MAX);
        });

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $fixtureIds = [];

            // Calculate bracket structure
            $nextPow2 = self::nextPowerOf2($n);
            $round1Slots = $nextPow2 / 2;
            $playInCount = $n - $round1Slots;

            // Determine stages needed
            $stages = self::getStagesForBracket($nextPow2);

            if ($playInCount > 0) {
                // Generate play-in round
                $playInFixtures = self::generatePlayInRound($competitionId, $participants, $playInCount);
                $fixtureIds = array_merge($fixtureIds, $playInFixtures['fixture_ids']);

                // Generate main bracket with byes and play-in references
                $mainFixtures = self::generateMainBracket(
                    $competitionId,
                    $participants,
                    $playInFixtures['round1_setup'],
                    $stages
                );
                $fixtureIds = array_merge($fixtureIds, $mainFixtures);
            } else {
                // Perfect power of 2 - direct bracket
                $mainFixtures = self::generateMainBracketDirect($competitionId, $participants, $stages);
                $fixtureIds = array_merge($fixtureIds, $mainFixtures);
            }

            $db->commit();
            return $fixtureIds;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Generate play-in round
     */
    private static function generatePlayInRound(int $competitionId, array $participants, int $playInMatchCount): array {
        $n = count($participants);
        $fixtureIds = [];
        $round1Setup = []; // Maps bracket position to participant or play-in fixture

        // Seeds that get byes (top seeds)
        $byeCount = count($participants) - 2 * $playInMatchCount;

        for ($i = 0; $i < $byeCount; $i++) {
            $round1Setup[$i + 1] = ['type' => 'participant', 'id' => $participants[$i]['id']];
        }

        // Play-in matches: remaining seeds paired
        $playInSeeds = array_slice($participants, $byeCount);

        for ($i = 0; $i < $playInMatchCount; $i++) {
            // Pair highest remaining vs lowest remaining
            $high = $playInSeeds[$i];
            $low = $playInSeeds[2 * $playInMatchCount - 1 - $i];

            $fixtureId = CompetitionFixture::create([
                'competition_id' => $competitionId,
                'stage' => 'play_in',
                'round_number' => 1,
                'bracket_position' => $i + 1,
                'participant1_id' => $high['id'],
                'participant2_id' => $low['id']
            ]);

            $fixtureIds[] = $fixtureId;

            // Play-in winner goes to next bracket position after byes
            $round1Setup[$byeCount + $i + 1] = ['type' => 'fixture', 'id' => $fixtureId];
        }

        return [
            'fixture_ids' => $fixtureIds,
            'round1_setup' => $round1Setup
        ];
    }

    /**
     * Generate main bracket with references to play-in winners
     */
    private static function generateMainBracket(
        int $competitionId,
        array $participants,
        array $round1Setup,
        array $stages
    ): array {
        $fixtureIds = [];
        $totalSlots = count($round1Setup);
        $currentRoundFixtures = [];

        // First round of main bracket
        $stage = $stages[0];
        $matchCount = $totalSlots / 2;

        for ($i = 0; $i < $matchCount; $i++) {
            $pos = $i + 1;
            $slot1 = $round1Setup[2 * $i + 1] ?? null;
            $slot2 = $round1Setup[$totalSlots - 2 * $i] ?? null;

            $data = [
                'competition_id' => $competitionId,
                'stage' => $stage,
                'round_number' => 1,
                'bracket_position' => $pos
            ];

            // Set participant or winner reference for slot 1
            if ($slot1) {
                if ($slot1['type'] === 'participant') {
                    $data['participant1_id'] = $slot1['id'];
                } else {
                    $data['winner_from_fixture_1'] = $slot1['id'];
                }
            }

            // Set participant or winner reference for slot 2
            if ($slot2) {
                if ($slot2['type'] === 'participant') {
                    $data['participant2_id'] = $slot2['id'];
                } else {
                    $data['winner_from_fixture_2'] = $slot2['id'];
                }
            }

            $fixtureId = CompetitionFixture::create($data);
            $fixtureIds[] = $fixtureId;
            $currentRoundFixtures[] = $fixtureId;
        }

        // Generate subsequent rounds
        for ($stageIdx = 1; $stageIdx < count($stages); $stageIdx++) {
            $stage = $stages[$stageIdx];
            $nextRoundFixtures = [];
            $matchCount = count($currentRoundFixtures) / 2;

            for ($i = 0; $i < $matchCount; $i++) {
                $fixtureId = CompetitionFixture::create([
                    'competition_id' => $competitionId,
                    'stage' => $stage,
                    'round_number' => 1,
                    'bracket_position' => $i + 1,
                    'winner_from_fixture_1' => $currentRoundFixtures[2 * $i],
                    'winner_from_fixture_2' => $currentRoundFixtures[2 * $i + 1]
                ]);
                $fixtureIds[] = $fixtureId;
                $nextRoundFixtures[] = $fixtureId;
            }

            $currentRoundFixtures = $nextRoundFixtures;
        }

        return $fixtureIds;
    }

    /**
     * Generate bracket for perfect power of 2 participants
     */
    private static function generateMainBracketDirect(int $competitionId, array $participants, array $stages): array {
        $fixtureIds = [];
        $n = count($participants);

        // Seed the first round using standard bracket seeding
        // 1 vs 16, 8 vs 9, 5 vs 12, 4 vs 13, etc.
        $seeded = self::seedBracket($participants);
        $currentRoundFixtures = [];

        // First round
        $stage = $stages[0];
        $matchCount = $n / 2;

        for ($i = 0; $i < $matchCount; $i++) {
            $fixtureId = CompetitionFixture::create([
                'competition_id' => $competitionId,
                'stage' => $stage,
                'round_number' => 1,
                'bracket_position' => $i + 1,
                'participant1_id' => $seeded[2 * $i]['id'],
                'participant2_id' => $seeded[2 * $i + 1]['id']
            ]);
            $fixtureIds[] = $fixtureId;
            $currentRoundFixtures[] = $fixtureId;
        }

        // Subsequent rounds
        for ($stageIdx = 1; $stageIdx < count($stages); $stageIdx++) {
            $stage = $stages[$stageIdx];
            $nextRoundFixtures = [];
            $matchCount = count($currentRoundFixtures) / 2;

            for ($i = 0; $i < $matchCount; $i++) {
                $fixtureId = CompetitionFixture::create([
                    'competition_id' => $competitionId,
                    'stage' => $stage,
                    'round_number' => 1,
                    'bracket_position' => $i + 1,
                    'winner_from_fixture_1' => $currentRoundFixtures[2 * $i],
                    'winner_from_fixture_2' => $currentRoundFixtures[2 * $i + 1]
                ]);
                $fixtureIds[] = $fixtureId;
                $nextRoundFixtures[] = $fixtureId;
            }

            $currentRoundFixtures = $nextRoundFixtures;
        }

        return $fixtureIds;
    }

    /**
     * Apply standard bracket seeding (1v16, 8v9, 5v12, 4v13, 6v11, 3v14, 7v10, 2v15)
     */
    private static function seedBracket(array $participants): array {
        $n = count($participants);
        if ($n < 2) {
            return $participants;
        }

        // Generate bracket order recursively
        $order = self::getBracketOrder($n);
        $seeded = [];

        foreach ($order as $seed) {
            $seeded[] = $participants[$seed - 1];
        }

        return $seeded;
    }

    /**
     * Get bracket seeding order for N participants
     * Returns array of 1-based seed positions
     */
    private static function getBracketOrder(int $n): array {
        if ($n === 2) {
            return [1, 2];
        }

        $half = self::getBracketOrder($n / 2);
        $result = [];

        foreach ($half as $seed) {
            $result[] = $seed;
            $result[] = $n + 1 - $seed;
        }

        return $result;
    }

    /**
     * Get stage names for a bracket of given size
     */
    private static function getStagesForBracket(int $bracketSize): array {
        $stages = [];

        switch ($bracketSize) {
            case 2:
                $stages = ['final'];
                break;
            case 4:
                $stages = ['semi_final', 'final'];
                break;
            case 8:
                $stages = ['quarter_final', 'semi_final', 'final'];
                break;
            case 16:
                $stages = ['round_of_16', 'quarter_final', 'semi_final', 'final'];
                break;
            case 32:
                $stages = ['round_of_32', 'round_of_16', 'quarter_final', 'semi_final', 'final'];
                break;
            case 64:
                $stages = ['round_of_64', 'round_of_32', 'round_of_16', 'quarter_final', 'semi_final', 'final'];
                break;
            default:
                // For larger brackets, use generic naming
                $rounds = log($bracketSize, 2);
                for ($i = 0; $i < $rounds; $i++) {
                    if ($i === $rounds - 1) {
                        $stages[] = 'final';
                    } elseif ($i === $rounds - 2) {
                        $stages[] = 'semi_final';
                    } elseif ($i === $rounds - 3) {
                        $stages[] = 'quarter_final';
                    } else {
                        $stages[] = 'round_of_' . pow(2, $rounds - $i);
                    }
                }
        }

        return $stages;
    }

    /**
     * Find next power of 2 >= n
     */
    private static function nextPowerOf2(int $n): int {
        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Generate knockout bracket from group stage qualifiers
     * Used for combined format competitions
     */
    public static function generateFromGroupStage(int $competitionId): array {
        require_once __DIR__ . '/Competition.php';
        require_once __DIR__ . '/CompetitionStandings.php';

        $competition = Competition::find($competitionId);
        if (!$competition) {
            throw new Exception('Competition not found');
        }

        if ($competition['format'] !== 'combined') {
            throw new Exception('Not a combined format competition');
        }

        // Check group stage is complete
        if (!CompetitionStandings::isGroupStageComplete($competitionId)) {
            throw new Exception('Group stage not complete');
        }

        // Get qualifiers from each group
        $qualifiersPerGroup = $competition['knockout_qualifiers'] ?? 2;
        $qualifiers = CompetitionStandings::getQualifiers($competitionId, $qualifiersPerGroup);

        // Flatten and seed for knockout
        // Standard seeding: A1, B2, C1, D2, B1, A2, D1, C2 (for 4 groups, 2 qualifiers)
        $participants = self::seedQualifiers($qualifiers);

        // Assign seeds
        $seed = 1;
        foreach ($participants as &$p) {
            $p['seed'] = $seed++;
        }

        return self::generateBracket($competitionId, $participants);
    }

    /**
     * Seed qualifiers from groups for knockout draw
     * Ensures group winners don't meet in early rounds
     */
    private static function seedQualifiers(array $qualifiersByGroup): array {
        $groupNames = array_keys($qualifiersByGroup);
        $groupCount = count($groupNames);

        if ($groupCount === 0) {
            return [];
        }

        $qualifiersPerGroup = count($qualifiersByGroup[$groupNames[0]]);
        $total = $groupCount * $qualifiersPerGroup;

        $seeded = [];

        // For 2 groups: A1, B2, B1, A2
        // For 4 groups: A1, D2, B1, C2, C1, B2, D1, A2
        // Pattern: Winners from different halves, runners-up from opposite groups

        if ($groupCount === 2) {
            $seeded[] = $qualifiersByGroup[$groupNames[0]][0]; // A1
            $seeded[] = $qualifiersByGroup[$groupNames[1]][1]; // B2
            $seeded[] = $qualifiersByGroup[$groupNames[1]][0]; // B1
            $seeded[] = $qualifiersByGroup[$groupNames[0]][1]; // A2
        } else {
            // Generic seeding: interleave winners and runners-up
            for ($pos = 0; $pos < $qualifiersPerGroup; $pos++) {
                for ($g = 0; $g < $groupCount; $g++) {
                    $groupIdx = ($pos % 2 === 0) ? $g : $groupCount - 1 - $g;
                    $seeded[] = $qualifiersByGroup[$groupNames[$groupIdx]][$pos];
                }
            }
        }

        return $seeded;
    }

    /**
     * Get bracket structure for visualization
     */
    public static function getBracketStructure(int $competitionId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT f.*, c.format
            FROM competition_fixtures f
            JOIN competitions c ON c.id = f.competition_id
            WHERE f.competition_id = :id
            AND f.stage != "group"
            ORDER BY
                CASE f.stage
                    WHEN "play_in" THEN 1
                    WHEN "round_of_64" THEN 2
                    WHEN "round_of_32" THEN 3
                    WHEN "round_of_16" THEN 4
                    WHEN "quarter_final" THEN 5
                    WHEN "semi_final" THEN 6
                    WHEN "third_place" THEN 7
                    WHEN "final" THEN 8
                END,
                f.bracket_position
        ');
        $stmt->execute(['id' => $competitionId]);
        $fixtures = $stmt->fetchAll();

        // Group by stage
        $byStage = [];
        foreach ($fixtures as $f) {
            if (!isset($byStage[$f['stage']])) {
                $byStage[$f['stage']] = [];
            }
            $byStage[$f['stage']][] = CompetitionFixture::findWithDetails($f['id']);
        }

        return $byStage;
    }

    /**
     * Add third place playoff
     */
    public static function addThirdPlaceMatch(int $competitionId): ?int {
        $db = Database::getInstance();

        // Get semi-final fixtures
        $stmt = $db->prepare('
            SELECT id FROM competition_fixtures
            WHERE competition_id = :id AND stage = "semi_final"
            ORDER BY bracket_position
        ');
        $stmt->execute(['id' => $competitionId]);
        $semis = $stmt->fetchAll();

        if (count($semis) !== 2) {
            return null;
        }

        // Create third place match with losers from semis
        // Note: Losers are tracked differently - we'd need to add loser_from_fixture fields
        // For simplicity, this match gets populated manually or after semis complete

        $fixtureId = CompetitionFixture::create([
            'competition_id' => $competitionId,
            'stage' => 'third_place',
            'round_number' => 1,
            'bracket_position' => 1
            // participant IDs set after semi-finals complete
        ]);

        return $fixtureId;
    }
}
