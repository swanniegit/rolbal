<?php
/**
 * Match Scoring - End scoring operations
 */

require_once __DIR__ . '/db.php';

class MatchScoring {

    public static function start(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE matches
            SET status = "live", started_at = NOW()
            WHERE id = :id AND status = "setup"
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function recordEnd(int $matchId, int $endNumber, int $scoringTeam, int $shots): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO match_ends (match_id, end_number, scoring_team, shots)
            VALUES (:match_id, :end_number, :scoring_team, :shots)
            ON DUPLICATE KEY UPDATE scoring_team = :scoring_team2, shots = :shots2
        ');
        $stmt->execute([
            'match_id' => $matchId,
            'end_number' => $endNumber,
            'scoring_team' => $scoringTeam,
            'shots' => $shots,
            'scoring_team2' => $scoringTeam,
            'shots2' => $shots
        ]);

        return (int)$db->lastInsertId();
    }

    public static function undoLastEnd(int $matchId): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT id FROM match_ends
            WHERE match_id = :match_id
            ORDER BY end_number DESC
            LIMIT 1
        ');
        $stmt->execute(['match_id' => $matchId]);
        $end = $stmt->fetch();

        if (!$end) {
            return false;
        }

        $stmt = $db->prepare('DELETE FROM match_ends WHERE id = :id');
        $stmt->execute(['id' => $end['id']]);

        return $stmt->rowCount() > 0;
    }

    public static function complete(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE matches
            SET status = "completed", completed_at = NOW()
            WHERE id = :id AND status = "live"
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function getScores(int $id): ?array {
        $db = Database::getInstance();

        // Get match status - handle both old and new schema
        try {
            $stmt = $db->prepare('SELECT status, scoring_mode, target_score FROM matches WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $match = $stmt->fetch();
        } catch (PDOException $e) {
            $stmt = $db->prepare('SELECT status, total_ends as target_score FROM matches WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $match = $stmt->fetch();
            if ($match) {
                $match['scoring_mode'] = 'ends';
            }
        }

        if (!$match) {
            return null;
        }

        // Get ends
        $stmt = $db->prepare('SELECT end_number, scoring_team, shots FROM match_ends WHERE match_id = :id ORDER BY end_number');
        $stmt->execute(['id' => $id]);
        $ends = $stmt->fetchAll();

        // Calculate totals
        $team1_score = 0;
        $team2_score = 0;
        foreach ($ends as $end) {
            if ($end['scoring_team'] == 1) {
                $team1_score += $end['shots'];
            } else {
                $team2_score += $end['shots'];
            }
        }

        return [
            'status' => $match['status'],
            'scoring_mode' => $match['scoring_mode'] ?? 'ends',
            'target_score' => $match['target_score'],
            'current_end' => count($ends) + 1,
            'ends' => $ends,
            'team1_score' => $team1_score,
            'team2_score' => $team2_score
        ];
    }
}
