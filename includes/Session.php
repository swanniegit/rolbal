<?php
/**
 * Session Model
 */

require_once __DIR__ . '/db.php';

class Session {

    public static function create(string $hand, string $date, int $bowlsPerEnd = 4, int $totalEnds = 15, ?string $description = null, ?int $playerId = null): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO sessions (hand, bowls_per_end, total_ends, session_date, description, player_id, is_public)
            VALUES (:hand, :bowls_per_end, :total_ends, :date, :description, :player_id, 1)
        ');
        $stmt->execute([
            'hand' => $hand,
            'bowls_per_end' => $bowlsPerEnd,
            'total_ends' => $totalEnds,
            'date' => $date,
            'description' => $description,
            'player_id' => $playerId
        ]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function all(int $limit = 50): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*, COUNT(r.id) as roll_count
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            GROUP BY s.id
            ORDER BY s.session_date DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function delete(int $id, ?int $playerId = null): bool {
        $db = Database::getInstance();

        // Verify ownership before deletion
        $stmt = $db->prepare('SELECT player_id FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        if (!$session) {
            return false;
        }

        // Allow deletion if:
        // 1. Session has no owner (anonymous) and no playerId provided
        // 2. Session owner matches the provided playerId
        $sessionOwnerId = $session['player_id'];

        if ($sessionOwnerId === null && $playerId === null) {
            // Anonymous session, allow deletion (for now - could add cookie check)
        } elseif ($sessionOwnerId !== null && (int)$sessionOwnerId === $playerId) {
            // Owner matches
        } else {
            // Not authorized
            return false;
        }

        $stmt = $db->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function toggleVisibility(int $id, int $playerId): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT is_public FROM sessions WHERE id = :id AND player_id = :player_id');
        $stmt->execute(['id' => $id, 'player_id' => $playerId]);
        $session = $stmt->fetch();

        if (!$session) {
            return false;
        }

        $newValue = $session['is_public'] ? 0 : 1;
        $stmt = $db->prepare('UPDATE sessions SET is_public = :is_public WHERE id = :id');
        return $stmt->execute(['is_public' => $newValue, 'id' => $id]);
    }

    public static function allPublic(int $limit = 50): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*, COUNT(r.id) as roll_count, p.name as player_name
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            LEFT JOIN players p ON s.player_id = p.id
            WHERE s.is_public = 1 OR s.player_id IS NULL
            GROUP BY s.id
            ORDER BY s.session_date DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function forPlayer(int $playerId, int $limit = 50): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*, COUNT(r.id) as roll_count
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            WHERE s.player_id = :player_id
            GROUP BY s.id
            ORDER BY s.session_date DESC
            LIMIT :limit
        ');
        $stmt->bindValue('player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
