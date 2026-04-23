<?php
/**
 * Match Queries - Complex query operations
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/MatchScoring.php';

class MatchQueries {

    public static function findWithDetails(int $id): ?array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT m.*, c.name as club_name, p.name as created_by_name
            FROM matches m
            LEFT JOIN clubs c ON c.id = m.club_id
            JOIN players p ON p.id = m.created_by
            WHERE m.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $match = $stmt->fetch();

        if (!$match) {
            return null;
        }

        return self::attachTeamsAndEnds($match, $db);
    }

    public static function findBounceWithDetails(string $token): ?array {
        if (strlen($token) < 16) return null;
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT m.*, p.name as created_by_name
            FROM matches m
            JOIN players p ON p.id = m.created_by
            WHERE m.share_token = :token AND m.game_type = "bounce"
        ');
        $stmt->execute(['token' => $token]);
        $match = $stmt->fetch();

        if (!$match) return null;

        return self::attachTeamsAndEnds($match, $db);
    }

    private static function attachTeamsAndEnds(array $match, $db): array {
        $id = $match['id'];

        // Get teams
        $stmt = $db->prepare('SELECT * FROM match_teams WHERE match_id = :id ORDER BY team_number');
        $stmt->execute(['id' => $id]);
        $teams = $stmt->fetchAll();

        // Get players for each team
        foreach ($teams as &$team) {
            $stmt = $db->prepare('SELECT * FROM match_players WHERE team_id = :id ORDER BY FIELD(position, "skip", "third", "second", "lead", "player"), id');
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

    public static function getLiveBounceGames(): array {
        $db = Database::getInstance();

        // Only matches from the last 3 days, live or setup
        $stmt = $db->prepare('
            SELECT m.id, m.match_name, m.share_token, m.status, m.scoring_mode, m.target_score,
                   m.players_per_team, m.bowls_per_player, m.started_at, m.created_at,
                   t1.team_name as team1_name, t2.team_name as team2_name,
                   p.name as created_by_name, c.name as club_name
            FROM matches m
            LEFT JOIN match_teams t1 ON t1.match_id = m.id AND t1.team_number = 1
            LEFT JOIN match_teams t2 ON t2.match_id = m.id AND t2.team_number = 2
            LEFT JOIN clubs c ON c.id = m.club_id
            JOIN players p ON p.id = m.created_by
            WHERE m.game_type = "bounce"
              AND m.status IN ("live", "setup", "completed")
              AND m.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY m.started_at DESC, m.created_at DESC
        ');
        $stmt->execute();
        $matches = $stmt->fetchAll();

        foreach ($matches as &$match) {
            $scores = MatchScoring::getScores($match['id']);
            $match['team1_score'] = $scores['team1_score'];
            $match['team2_score'] = $scores['team2_score'];
            $match['current_end'] = $scores['current_end'];
            $match['ends']        = $scores['ends'];
        }

        return $matches;
    }

    public static function getLiveMatchesForClub(int $clubId): array {
        $db = Database::getInstance();

        try {
            // Try new schema first
            $stmt = $db->prepare('
                SELECT m.id, m.game_type, m.target_score, m.scoring_mode, m.status, m.scorer_id,
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
        } catch (PDOException $e) {
            // Fall back to old schema
            $stmt = $db->prepare('
                SELECT m.id, m.game_type, m.total_ends as target_score, m.status,
                       t1.team_name as team1_name, t2.team_name as team2_name
                FROM matches m
                LEFT JOIN match_teams t1 ON t1.match_id = m.id AND t1.team_number = 1
                LEFT JOIN match_teams t2 ON t2.match_id = m.id AND t2.team_number = 2
                WHERE m.club_id = :club_id AND m.status = "live"
                ORDER BY m.started_at DESC
            ');
            $stmt->execute(['club_id' => $clubId]);
        }
        $matches = $stmt->fetchAll();

        // Add scores to each match
        foreach ($matches as &$match) {
            $scores = MatchScoring::getScores($match['id']);
            $match['team1_score'] = $scores['team1_score'];
            $match['team2_score'] = $scores['team2_score'];
            $match['current_end'] = $scores['current_end'];
            $match['ends'] = $scores['ends'];
            $match['scorer_name'] = $match['scorer_name'] ?? null;
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
            $scores = MatchScoring::getScores($match['id']);
            $match['team1_score'] = $scores['team1_score'];
            $match['team2_score'] = $scores['team2_score'];
            $match['current_end'] = $scores['current_end'];
        }

        return $matches;
    }
}
