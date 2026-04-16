<?php
/**
 * Challenge Rolls - Roll recording and undo for challenges
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Challenge.php';

class ChallengeRolls {

    public static function addRoll(int $attemptId, int $sessionId, int $totalScore, int $endLength, int $delivery, int $result, int $toucher = 0, string $scoringType = 'standard'): array {
        $db = Database::getInstance();

        // Use transaction to ensure roll insert + score update are atomic
        $db->beginTransaction();
        try {
            // Get current roll count
            $stmt = $db->prepare('SELECT COUNT(*) FROM rolls WHERE session_id = :session_id');
            $stmt->execute(['session_id' => $sessionId]);
            $rollCount = (int) $stmt->fetchColumn();

            // Calculate end number (1-based)
            $endNumber = floor($rollCount / 4) + 1;

            // Insert roll with delivery
            $stmt = $db->prepare('
                INSERT INTO rolls (session_id, end_number, end_length, delivery, result, toucher)
                VALUES (:session_id, :end_number, :end_length, :delivery, :result, :toucher)
            ');
            $stmt->execute([
                'session_id' => $sessionId,
                'end_number' => $endNumber,
                'end_length' => $endLength,
                'delivery' => $delivery,
                'result' => $result,
                'toucher' => $toucher
            ]);
            $rollId = (int) $db->lastInsertId();

            // Calculate score for this roll
            $score = Challenge::calculateScore($result, $toucher, $scoringType);

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

            $db->commit();

            return [
                'roll_id' => $rollId,
                'score' => $score,
                'total_score' => $totalScore + $score,
                'roll_count' => $rollCount + 1
            ];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function undoLastRoll(int $attemptId, int $sessionId, string $scoringType = 'standard'): bool {
        $db = Database::getInstance();

        // Get last roll (outside transaction for read)
        $stmt = $db->prepare('
            SELECT * FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute(['session_id' => $sessionId]);
        $lastRoll = $stmt->fetch();

        if (!$lastRoll) {
            return false;
        }

        // Calculate score to subtract
        $score = Challenge::calculateScore($lastRoll['result'], $lastRoll['toucher'], $scoringType);

        // Use transaction to ensure roll delete + score update are atomic
        $db->beginTransaction();
        try {
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

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
