<?php
/**
 * Challenge Model
 */

require_once __DIR__ . '/db.php';

class Challenge {

    // Scoring points by result position
    const SCORE_MAP = [
        8  => 10, // Centre - perfect (less than mat width from jack)
        3  => 7,  // Level Left - good draw
        4  => 7,  // Level Right - good draw
        7  => 5,  // Long Centre
        12 => 5,  // Short Centre
        5  => 3,  // Long Left
        6  => 3,  // Long Right
        1  => 2,  // Short Left
        2  => 2,  // Short Right
        // Misses - more than 2 mat lengths from jack
        20 => 0,  // Too Far Left
        21 => 0,  // Too Far Right
        22 => 0,  // Too Long/Ditch
        23 => 0,  // Too Short
    ];

    const TOUCHER_BONUS = 5;

    public static function all(bool $activeOnly = true): array {
        $db = Database::getInstance();

        $sql = '
            SELECT c.*,
                   SUM(cs.bowl_count) as total_bowls,
                   COUNT(cs.id) as sequence_count
            FROM challenges c
            LEFT JOIN challenge_sequences cs ON cs.challenge_id = c.id
        ';

        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }

        $sql .= ' GROUP BY c.id ORDER BY c.difficulty ASC, c.name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM challenges WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findWithSequences(int $id): ?array {
        $challenge = self::find($id);
        if (!$challenge) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM challenge_sequences
            WHERE challenge_id = :id
            ORDER BY sequence_order ASC
        ');
        $stmt->execute(['id' => $id]);
        $challenge['sequences'] = $stmt->fetchAll();

        // Calculate totals
        $totalBowls = 0;
        $maxScore = 0;
        foreach ($challenge['sequences'] as $seq) {
            $totalBowls += $seq['bowl_count'];
            // Max score is 10 (Centre) + 5 (Toucher bonus) per bowl
            $maxScore += $seq['bowl_count'] * (self::SCORE_MAP[8] + self::TOUCHER_BONUS);
        }
        $challenge['total_bowls'] = $totalBowls;
        $challenge['max_possible_score'] = $maxScore;

        return $challenge;
    }

    public static function calculateScore(int $result, int $toucher): int {
        $score = self::SCORE_MAP[$result] ?? 0;
        if ($toucher) {
            $score += self::TOUCHER_BONUS;
        }
        return $score;
    }

    public static function getMaxScorePerBowl(): int {
        return self::SCORE_MAP[8] + self::TOUCHER_BONUS;
    }

    public static function getBestScoreForPlayer(int $challengeId, int $playerId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT id, total_score, max_possible_score, completed_at
            FROM challenge_attempts
            WHERE challenge_id = :challenge_id
              AND player_id = :player_id
              AND completed_at IS NOT NULL
            ORDER BY total_score DESC
            LIMIT 1
        ');
        $stmt->execute([
            'challenge_id' => $challengeId,
            'player_id' => $playerId
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getLeaderboard(int $challengeId, int $limit = 10): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT ca.total_score, ca.max_possible_score, ca.completed_at,
                   p.name as player_name, p.id as player_id
            FROM challenge_attempts ca
            JOIN players p ON p.id = ca.player_id
            WHERE ca.challenge_id = :challenge_id
              AND ca.completed_at IS NOT NULL
            ORDER BY ca.total_score DESC
            LIMIT :limit
        ');
        $stmt->bindValue('challenge_id', $challengeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function allWithPlayerBest(int $playerId, bool $activeOnly = true): array {
        $challenges = self::all($activeOnly);

        foreach ($challenges as &$challenge) {
            $challenge['best_score'] = self::getBestScoreForPlayer($challenge['id'], $playerId);
            $challenge['active_attempt'] = self::getActiveAttemptForPlayer($challenge['id'], $playerId);
            // Calculate max possible score
            $challenge['max_possible_score'] = ($challenge['total_bowls'] ?? 0) * self::getMaxScorePerBowl();
        }

        return $challenges;
    }

    public static function getActiveAttemptForPlayer(int $challengeId, int $playerId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT ca.id, ca.total_score, ca.created_at,
                   (SELECT COUNT(*) FROM rolls r WHERE r.session_id = ca.session_id) as roll_count
            FROM challenge_attempts ca
            WHERE ca.challenge_id = :challenge_id
              AND ca.player_id = :player_id
              AND ca.completed_at IS NULL
            ORDER BY ca.created_at DESC
            LIMIT 1
        ');
        $stmt->execute([
            'challenge_id' => $challengeId,
            'player_id' => $playerId
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
