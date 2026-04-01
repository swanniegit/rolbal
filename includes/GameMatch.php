<?php
/**
 * GameMatch Model - Live Match Scoring System
 * Note: Named GameMatch instead of Match because 'match' is a reserved keyword in PHP 8.0+
 */

require_once __DIR__ . '/db.php';

class GameMatch {

    // Game type configurations
    const GAME_TYPES = [
        'singles' => [
            'players_per_team' => 1,
            'default_bowls' => 4,
            'allowed_bowls' => [4],
            'positions' => ['skip']
        ],
        'pairs' => [
            'players_per_team' => 2,
            'default_bowls' => 4,
            'allowed_bowls' => [3, 4],
            'positions' => ['skip', 'lead']
        ],
        'trips' => [
            'players_per_team' => 3,
            'default_bowls' => 3,
            'allowed_bowls' => [2, 3],
            'positions' => ['skip', 'third', 'lead']
        ],
        'fours' => [
            'players_per_team' => 4,
            'default_bowls' => 2,
            'allowed_bowls' => [2],
            'positions' => ['skip', 'third', 'second', 'lead']
        ]
    ];

    public static function getGameTypes(): array {
        return self::GAME_TYPES;
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT m.*, c.name as club_name, p.name as created_by_name
            FROM matches m
            JOIN clubs c ON c.id = m.club_id
            JOIN players p ON p.id = m.created_by
            WHERE m.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findWithDetails(int $id): ?array {
        $match = self::find($id);
        if (!$match) {
            return null;
        }

        $db = Database::getInstance();

        // Get teams
        $stmt = $db->prepare('SELECT * FROM match_teams WHERE match_id = :id ORDER BY team_number');
        $stmt->execute(['id' => $id]);
        $teams = $stmt->fetchAll();

        // Get players for each team
        foreach ($teams as &$team) {
            $stmt = $db->prepare('SELECT * FROM match_players WHERE team_id = :id ORDER BY FIELD(position, "skip", "third", "second", "lead")');
            $stmt->execute(['id' => $team['id']]);
            $team['players'] = $stmt->fetchAll();
        }
        $match['teams'] = $teams;

        // Get ends
        $stmt = $db->prepare('SELECT * FROM match_ends WHERE match_id = :id ORDER BY end_number');
        $stmt->execute(['id' => $id]);
        $match['ends'] = $stmt->fetchAll();

        // Calculate totals
        $match['team1_score'] = 0;
        $match['team2_score'] = 0;
        foreach ($match['ends'] as $end) {
            if ($end['scoring_team'] == 1) {
                $match['team1_score'] += $end['shots'];
            } else {
                $match['team2_score'] += $end['shots'];
            }
        }
        $match['current_end'] = count($match['ends']) + 1;

        return $match;
    }

    public static function getScores(int $id): ?array {
        $db = Database::getInstance();

        // Get match status
        $stmt = $db->prepare('SELECT status, scoring_mode, target_score FROM matches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $match = $stmt->fetch();
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
            'scoring_mode' => $match['scoring_mode'],
            'target_score' => $match['target_score'],
            'current_end' => count($ends) + 1,
            'ends' => $ends,
            'team1_score' => $team1_score,
            'team2_score' => $team2_score
        ];
    }

    public static function getLiveMatchesForClub(int $clubId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT m.id, m.game_type, m.total_ends, m.status, m.scorer_id,
                   t1.team_name as team1_name, t2.team_name as team2_name,
                   p.name as scorer_name
            FROM matches m
            LEFT JOIN match_teams t1 ON t1.match_id = m.id AND t1.team_number = 1
            LEFT JOIN match_teams t2 ON t2.match_id = m.id AND t2.team_number = 2
            LEFT JOIN players p ON p.id = m.scorer_id
            WHERE m.club_id = :club_id AND m.status = "live"
            ORDER BY m.started_at DESC
        ');
        $stmt->execute(['club_id' => $clubId]);
        $matches = $stmt->fetchAll();

        // Add scores to each match
        foreach ($matches as &$match) {
            $scores = self::getScores($match['id']);
            $match['team1_score'] = $scores['team1_score'];
            $match['team2_score'] = $scores['team2_score'];
            $match['current_end'] = $scores['current_end'];
            $match['ends'] = $scores['ends'];
        }

        return $matches;
    }

    public static function listByClub(int $clubId, ?string $status = null, int $limit = 20): array {
        $db = Database::getInstance();

        $sql = '
            SELECT m.*, p.name as created_by_name, s.name as scorer_name,
                   t1.team_name as team1_name, t2.team_name as team2_name
            FROM matches m
            JOIN players p ON p.id = m.created_by
            LEFT JOIN players s ON s.id = m.scorer_id
            LEFT JOIN match_teams t1 ON t1.match_id = m.id AND t1.team_number = 1
            LEFT JOIN match_teams t2 ON t2.match_id = m.id AND t2.team_number = 2
            WHERE m.club_id = :club_id
        ';

        $params = ['club_id' => $clubId];

        if ($status) {
            $sql .= ' AND m.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT :limit';

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $matches = $stmt->fetchAll();

        // Add scores to each match
        foreach ($matches as &$match) {
            $scores = self::getScores($match['id']);
            $match['team1_score'] = $scores['team1_score'];
            $match['team2_score'] = $scores['team2_score'];
            $match['current_end'] = $scores['current_end'];
        }

        return $matches;
    }

    public static function create(int $clubId, int $createdBy, string $gameType, int $bowlsPerPlayer, string $scoringMode, int $targetScore): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO matches (club_id, created_by, game_type, bowls_per_player, scoring_mode, target_score, status)
            VALUES (:club_id, :created_by, :game_type, :bowls_per_player, :scoring_mode, :target_score, "setup")
        ');
        $stmt->execute([
            'club_id' => $clubId,
            'created_by' => $createdBy,
            'game_type' => $gameType,
            'bowls_per_player' => $bowlsPerPlayer,
            'scoring_mode' => $scoringMode,
            'target_score' => $targetScore
        ]);

        return (int)$db->lastInsertId();
    }

    public static function createTeam(int $matchId, int $teamNumber, ?string $teamName): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO match_teams (match_id, team_number, team_name)
            VALUES (:match_id, :team_number, :team_name)
        ');
        $stmt->execute([
            'match_id' => $matchId,
            'team_number' => $teamNumber,
            'team_name' => $teamName
        ]);

        return (int)$db->lastInsertId();
    }

    public static function addPlayer(int $teamId, string $position, string $playerName, ?int $playerId = null): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO match_players (team_id, position, player_name, player_id)
            VALUES (:team_id, :position, :player_name, :player_id)
        ');
        $stmt->execute([
            'team_id' => $teamId,
            'position' => $position,
            'player_name' => $playerName,
            'player_id' => $playerId
        ]);

        return (int)$db->lastInsertId();
    }

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

        // Get the last end
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

    public static function delete(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('DELETE FROM matches WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

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
        $stmt = $db->prepare('SELECT is_paid FROM players WHERE id = :id');
        $stmt->execute(['id' => $playerId]);
        $player = $stmt->fetch();
        return $player && $player['is_paid'];
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

    public static function releaseScorer(int $matchId, int $playerId): bool {
        $db = Database::getInstance();

        // Only release if this player is the scorer or creator
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }

        if ($match['scorer_id'] != $playerId && $match['created_by'] != $playerId) {
            return false;
        }

        $stmt = $db->prepare('UPDATE matches SET scorer_id = NULL WHERE id = :id');
        $stmt->execute(['id' => $matchId]);

        return $stmt->rowCount() > 0;
    }

    public static function canScore(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }

        // If scorer is claimed, only that person can score
        if ($match['scorer_id']) {
            return $match['scorer_id'] == $playerId;
        }

        // Match creator can always score (before claimed)
        if ($match['created_by'] == $playerId) {
            return true;
        }

        // Club admins can score (before claimed)
        return self::canCreate($playerId, $match['club_id']);
    }

    public static function canClaimScorer(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }

        // Already claimed
        if ($match['scorer_id']) {
            return false;
        }

        // Must be paid member
        if (!self::isPaidMember($playerId)) {
            return false;
        }

        // Must be club member
        return self::canView($playerId, $matchId);
    }

    public static function canView(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }

        // Any club member can view
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT 1 FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id
        ');
        $stmt->execute([
            'club_id' => $match['club_id'],
            'player_id' => $playerId
        ]);

        return $stmt->fetch() !== false;
    }

    public static function canDelete(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }

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
}
