<?php
/**
 * Club Member Model
 */

require_once __DIR__ . '/db.php';

class ClubMember {

    public static function add(int $clubId, int $playerId, string $role = 'member'): bool {
        $db = Database::getInstance();

        if (self::isMember($clubId, $playerId)) {
            return false;
        }

        $stmt = $db->prepare('
            INSERT INTO club_members (club_id, player_id, role)
            VALUES (:club_id, :player_id, :role)
        ');
        return $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId,
            'role' => $role
        ]);
    }

    public static function remove(int $clubId, int $playerId): bool {
        $db = Database::getInstance();

        $role = self::getRole($clubId, $playerId);
        if ($role === 'owner') {
            return false;
        }

        $stmt = $db->prepare('
            DELETE FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        return $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
    }

    public static function updateRole(int $clubId, int $playerId, string $role): bool {
        $db = Database::getInstance();

        if (!in_array($role, ['admin', 'member'])) {
            return false;
        }

        $currentRole = self::getRole($clubId, $playerId);
        if ($currentRole === 'owner') {
            return false;
        }

        $stmt = $db->prepare('
            UPDATE club_members
            SET role = :role
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        return $stmt->execute([
            'role' => $role,
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
    }

    public static function isMember(int $clubId, int $playerId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT 1 FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
        return (bool) $stmt->fetch();
    }

    public static function getRole(int $clubId, int $playerId): ?string {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT role FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
        $result = $stmt->fetch();
        return $result ? $result['role'] : null;
    }

    public static function getPlayerClubs(int $playerId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, cm.role, cm.joined_at,
                   (SELECT COUNT(*) FROM club_members WHERE club_id = c.id) as member_count
            FROM club_members cm
            JOIN clubs c ON cm.club_id = c.id
            WHERE cm.player_id = :player_id
            ORDER BY cm.joined_at DESC
        ');
        $stmt->execute(['player_id' => $playerId]);
        return $stmt->fetchAll();
    }

    public static function setPrimaryClub(int $playerId, ?int $clubId): bool {
        $db = Database::getInstance();

        if ($clubId && !self::isMember($clubId, $playerId)) {
            return false;
        }

        $stmt = $db->prepare('
            UPDATE players
            SET primary_club_id = :club_id
            WHERE id = :player_id
        ');
        return $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
    }

    public static function getPrimaryClub(int $playerId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*
            FROM players p
            JOIN clubs c ON p.primary_club_id = c.id
            WHERE p.id = :player_id
        ');
        $stmt->execute(['player_id' => $playerId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
