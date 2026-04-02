<?php
/**
 * Match Permissions - Access control for matches
 */

require_once __DIR__ . '/db.php';

class MatchPermissions {

    public static function canCreate(int $playerId, int $clubId): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT role FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);
        $member = $stmt->fetch();

        if (!$member) {
            return false;
        }

        return in_array($member['role'], ['owner', 'admin']);
    }

    public static function isPaidMember(int $playerId): bool {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare('SELECT is_paid FROM players WHERE id = :id');
            $stmt->execute(['id' => $playerId]);
            $player = $stmt->fetch();
            return $player && $player['is_paid'];
        } catch (PDOException $e) {
            // Column doesn't exist yet - treat all as paid for now
            return true;
        }
    }

    public static function canScore(int $playerId, int $matchId, array $match): bool {
        // If scorer is claimed, only that person can score
        if (!empty($match['scorer_id'])) {
            return $match['scorer_id'] == $playerId;
        }

        // Match creator can always score (before claimed)
        if ($match['created_by'] == $playerId) {
            return true;
        }

        // Club admins can score (before claimed)
        return self::canCreate($playerId, $match['club_id']);
    }

    public static function canView(int $playerId, int $clubId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT 1 FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        $stmt->execute([
            'club_id' => $clubId,
            'player_id' => $playerId
        ]);

        return $stmt->fetch() !== false;
    }

    public static function canDelete(int $playerId, array $match): bool {
        // Match creator can delete
        if ($match['created_by'] == $playerId) {
            return true;
        }

        // Club owner can delete
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT 1 FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id AND role = "owner"
        ');
        $stmt->execute([
            'club_id' => $match['club_id'],
            'player_id' => $playerId
        ]);

        return $stmt->fetch() !== false;
    }

    public static function canClaimScorer(int $playerId, array $match): bool {
        // Already claimed
        if (!empty($match['scorer_id'])) {
            return false;
        }

        // Must be paid member
        if (!self::isPaidMember($playerId)) {
            return false;
        }

        // Must be club member
        return self::canView($playerId, $match['club_id']);
    }

    public static function claimScorer(int $matchId, int $playerId): bool {
        // Check if player is paid
        if (!self::isPaidMember($playerId)) {
            return false;
        }

        $db = Database::getInstance();

        // Only claim if not already claimed
        $stmt = $db->prepare('
            UPDATE matches
            SET scorer_id = :player_id
            WHERE id = :match_id AND scorer_id IS NULL
        ');
        $stmt->execute([
            'match_id' => $matchId,
            'player_id' => $playerId
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function releaseScorer(int $matchId, int $playerId, array $match): bool {
        if ($match['scorer_id'] != $playerId && $match['created_by'] != $playerId) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE matches SET scorer_id = NULL WHERE id = :id');
        $stmt->execute(['id' => $matchId]);

        return $stmt->rowCount() > 0;
    }
}
