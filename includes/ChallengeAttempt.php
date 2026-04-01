<?php
/**
 * Challenge Attempt Model
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Challenge.php';
require_once __DIR__ . '/Session.php';

class ChallengeAttempt {

    public static function create(int $challengeId, int $playerId): int {
        $db = Database::getInstance();

        // Get challenge max score
        $challenge = Challenge::findWithSequences($challengeId);
        $maxPossible = $challenge ? $challenge['max_possible_score'] : 0;

        // Create a hidden session for storing rolls
        $sessionId = self::createChallengeSession($playerId, $challenge);

        $stmt = $db->prepare('
            INSERT INTO challenge_attempts (challenge_id, player_id, session_id, max_possible_score)
            VALUES (:challenge_id, :player_id, :session_id, :max_possible)
        ');
        $stmt->execute([
            'challenge_id' => $challengeId,
            'player_id' => $playerId,
            'session_id' => $sessionId,
            'max_possible' => $maxPossible
        ]);
        return (int) $db->lastInsertId();
    }

    private static function createChallengeSession(int $playerId, array $challenge): int {
        $db = Database::getInstance();

        // Calculate total bowls and ends for the challenge
        $totalBowls = $challenge['total_bowls'];
        $bowlsPerEnd = 4; // Use standard 4 bowls per end
        $totalEnds = ceil($totalBowls / $bowlsPerEnd);

        $stmt = $db->prepare('
            INSERT INTO sessions (hand, bowls_per_end, total_ends, session_date, description, player_id, is_public)
            VALUES (:hand, :bowls_per_end, :total_ends, :date, :description, :player_id, 0)
        ');
        $stmt->execute([
            'hand' => 'R', // Default, not relevant for challenges
            'bowls_per_end' => $bowlsPerEnd,
            'total_ends' => $totalEnds,
            'date' => date('Y-m-d'),
            'description' => 'Challenge: ' . $challenge['name'],
            'player_id' => $playerId
        ]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT ca.*, c.name as challenge_name, c.description as challenge_description,
                   c.difficulty
            FROM challenge_attempts ca
            JOIN challenges c ON c.id = ca.challenge_id
            WHERE ca.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findWithDetails(int $id): ?array {
        $attempt = self::find($id);
        if (!$attempt) {
            return null;
        }

        // Get challenge sequences
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM challenge_sequences
            WHERE challenge_id = :challenge_id
            ORDER BY sequence_order ASC
        ');
        $stmt->execute(['challenge_id' => $attempt['challenge_id']]);
        $attempt['sequences'] = $stmt->fetchAll();

        // Get rolls for this attempt
        if ($attempt['session_id']) {
            $stmt = $db->prepare('
                SELECT * FROM rolls
                WHERE session_id = :session_id
                ORDER BY created_at ASC
            ');
            $stmt->execute(['session_id' => $attempt['session_id']]);
            $attempt['rolls'] = $stmt->fetchAll();
        } else {
            $attempt['rolls'] = [];
        }

        return $attempt;
    }

    public static function addRoll(int $attemptId, int $endLength, int $delivery, int $result, int $toucher = 0): array {
        $attempt = self::find($attemptId);
        if (!$attempt || !$attempt['session_id']) {
            throw new Exception('Invalid attempt');
        }

        if ($attempt['completed_at']) {
            throw new Exception('Attempt already completed');
        }

        $db = Database::getInstance();

        // Get current roll count
        $stmt = $db->prepare('SELECT COUNT(*) FROM rolls WHERE session_id = :session_id');
        $stmt->execute(['session_id' => $attempt['session_id']]);
        $rollCount = (int) $stmt->fetchColumn();

        // Calculate end number (1-based)
        $endNumber = floor($rollCount / 4) + 1;

        // Insert roll with delivery
        $stmt = $db->prepare('
            INSERT INTO rolls (session_id, end_number, end_length, delivery, result, toucher)
            VALUES (:session_id, :end_number, :end_length, :delivery, :result, :toucher)
        ');
        $stmt->execute([
            'session_id' => $attempt['session_id'],
            'end_number' => $endNumber,
            'end_length' => $endLength,
            'delivery' => $delivery,
            'result' => $result,
            'toucher' => $toucher
        ]);
        $rollId = (int) $db->lastInsertId();

        // Calculate score for this roll
        $score = Challenge::calculateScore($result, $toucher);

        // Update total score
        $stmt = $db->prepare('
            UPDATE challenge_attempts
            SET total_score = total_score + :score
            WHERE id = :id
        ');
        $stmt->execute([
            'score' => $score,
            'id' => $attemptId
        ]);

        return [
            'roll_id' => $rollId,
            'score' => $score,
            'total_score' => $attempt['total_score'] + $score,
            'roll_count' => $rollCount + 1
        ];
    }

    public static function undoLastRoll(int $attemptId): bool {
        $attempt = self::find($attemptId);
        if (!$attempt || !$attempt['session_id']) {
            return false;
        }

        if ($attempt['completed_at']) {
            return false;
        }

        $db = Database::getInstance();

        // Get last roll
        $stmt = $db->prepare('
            SELECT * FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute(['session_id' => $attempt['session_id']]);
        $lastRoll = $stmt->fetch();

        if (!$lastRoll) {
            return false;
        }

        // Calculate score to subtract
        $score = Challenge::calculateScore($lastRoll['result'], $lastRoll['toucher']);

        // Delete the roll
        $stmt = $db->prepare('DELETE FROM rolls WHERE id = :id');
        $stmt->execute(['id' => $lastRoll['id']]);

        // Update total score
        $stmt = $db->prepare('
            UPDATE challenge_attempts
            SET total_score = GREATEST(0, total_score - :score)
            WHERE id = :id
        ');
        $stmt->execute([
            'score' => $score,
            'id' => $attemptId
        ]);

        return true;
    }

    public static function complete(int $attemptId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE challenge_attempts
            SET completed_at = NOW()
            WHERE id = :id AND completed_at IS NULL
        ');
        return $stmt->execute(['id' => $attemptId]);
    }

    public static function getProgress(int $attemptId): array {
        $attempt = self::findWithDetails($attemptId);
        if (!$attempt) {
            return ['error' => 'Attempt not found'];
        }

        $rollCount = count($attempt['rolls']);
        $sequences = $attempt['sequences'];

        // Calculate which sequence and bowl we're on
        $currentSequenceIndex = 0;
        $currentBowlInSequence = 0;
        $bowlsProcessed = 0;

        foreach ($sequences as $index => $seq) {
            $seqBowls = (int) $seq['bowl_count'];
            if ($bowlsProcessed + $seqBowls > $rollCount) {
                $currentSequenceIndex = $index;
                $currentBowlInSequence = $rollCount - $bowlsProcessed;
                break;
            }
            $bowlsProcessed += $seqBowls;
            if ($bowlsProcessed >= $rollCount && $index === count($sequences) - 1) {
                $currentSequenceIndex = $index;
                $currentBowlInSequence = $seqBowls;
            }
        }

        // Calculate total bowls
        $totalBowls = 0;
        foreach ($sequences as $seq) {
            $totalBowls += (int) $seq['bowl_count'];
        }

        $currentSequence = $sequences[$currentSequenceIndex] ?? null;
        $isComplete = $rollCount >= $totalBowls;

        return [
            'attempt_id' => $attemptId,
            'total_score' => (int) $attempt['total_score'],
            'max_possible_score' => (int) $attempt['max_possible_score'],
            'roll_count' => $rollCount,
            'total_bowls' => $totalBowls,
            'current_sequence_index' => $currentSequenceIndex,
            'current_sequence_number' => $currentSequenceIndex + 1,
            'total_sequences' => count($sequences),
            'current_bowl_in_sequence' => $currentBowlInSequence + 1,
            'current_sequence' => $currentSequence,
            'is_complete' => $isComplete,
            'completed_at' => $attempt['completed_at'],
            'percent_complete' => $totalBowls > 0 ? round(($rollCount / $totalBowls) * 100, 1) : 0
        ];
    }

    public static function forPlayer(int $playerId, int $limit = 20): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT ca.*, c.name as challenge_name, c.difficulty
            FROM challenge_attempts ca
            JOIN challenges c ON c.id = ca.challenge_id
            WHERE ca.player_id = :player_id
            ORDER BY ca.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function forPlayerChallenge(int $playerId, int $challengeId, int $limit = 10): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT ca.*,
                   ROUND((ca.total_score / ca.max_possible_score) * 100, 1) as percentage
            FROM challenge_attempts ca
            WHERE ca.player_id = :player_id
              AND ca.challenge_id = :challenge_id
              AND ca.completed_at IS NOT NULL
            ORDER BY ca.completed_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue('challenge_id', $challengeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getScoreBreakdown(int $attemptId): array {
        $attempt = self::findWithDetails($attemptId);
        if (!$attempt) {
            return [];
        }

        $sequences = $attempt['sequences'];
        $rolls = $attempt['rolls'];
        $breakdown = [];

        $rollIndex = 0;
        foreach ($sequences as $seq) {
            $seqBowls = (int) $seq['bowl_count'];
            $seqScore = 0;
            $seqMaxScore = $seqBowls * Challenge::getMaxScorePerBowl();
            $seqRolls = [];

            for ($i = 0; $i < $seqBowls && $rollIndex < count($rolls); $i++, $rollIndex++) {
                $roll = $rolls[$rollIndex];
                $rollScore = Challenge::calculateScore($roll['result'], $roll['toucher']);
                $seqScore += $rollScore;
                $seqRolls[] = [
                    'result' => $roll['result'],
                    'toucher' => $roll['toucher'],
                    'score' => $rollScore
                ];
            }

            $breakdown[] = [
                'sequence_order' => $seq['sequence_order'],
                'description' => $seq['description'],
                'end_length' => $seq['end_length'],
                'delivery' => $seq['delivery'],
                'bowl_count' => $seqBowls,
                'rolls' => $seqRolls,
                'score' => $seqScore,
                'max_score' => $seqMaxScore,
                'percentage' => $seqMaxScore > 0 ? round(($seqScore / $seqMaxScore) * 100, 1) : 0
            ];
        }

        return $breakdown;
    }

    public static function getActiveAttempt(int $playerId, int $challengeId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM challenge_attempts
            WHERE player_id = :player_id
              AND challenge_id = :challenge_id
              AND completed_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([
            'player_id' => $playerId,
            'challenge_id' => $challengeId
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
