<?php
/**
 * Roll Model
 */

require_once __DIR__ . '/db.php';

class Roll {

    public static function create(int $sessionId, int $endNumber, int $endLength, int $result, int $toucher = 0): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO rolls (session_id, end_number, end_length, result, toucher)
            VALUES (:session_id, :end_number, :end_length, :result, :toucher)
        ');
        $stmt->execute([
            'session_id' => $sessionId,
            'end_number' => $endNumber,
            'end_length' => $endLength,
            'result' => $result,
            'toucher' => $toucher
        ]);
        return (int) $db->lastInsertId();
    }

    public static function forSession(int $sessionId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at ASC
        ');
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM rolls WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Undo (delete) the last roll in a session
     */
    public static function undoLast(int $sessionId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            DELETE FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public static function stats(int $sessionId): array {
        $db = Database::getInstance();

        // Total rolls
        $stmt = $db->prepare('SELECT COUNT(*) FROM rolls WHERE session_id = :id');
        $stmt->execute(['id' => $sessionId]);
        $total = (int) $stmt->fetchColumn();

        // Touchers
        $stmt = $db->prepare('SELECT COUNT(*) FROM rolls WHERE session_id = :id AND toucher = 1');
        $stmt->execute(['id' => $sessionId]);
        $touchers = (int) $stmt->fetchColumn();

        // Result breakdown
        $stmt = $db->prepare('
            SELECT result, COUNT(*) as count
            FROM rolls WHERE session_id = :id
            GROUP BY result
        ');
        $stmt->execute(['id' => $sessionId]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // End length breakdown
        $stmt = $db->prepare('
            SELECT end_length, COUNT(*) as count
            FROM rolls WHERE session_id = :id
            GROUP BY end_length
        ');
        $stmt->execute(['id' => $sessionId]);
        $endLengths = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // By end
        $stmt = $db->prepare('
            SELECT end_number, result, COUNT(*) as count
            FROM rolls WHERE session_id = :id
            GROUP BY end_number, result
            ORDER BY end_number
        ');
        $stmt->execute(['id' => $sessionId]);
        $byEnd = $stmt->fetchAll();

        return [
            'total' => $total,
            'touchers' => $touchers,
            'results' => $results,
            'end_lengths' => $endLengths,
            'by_end' => $byEnd
        ];
    }
}
