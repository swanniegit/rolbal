<?php
/**
 * GameMatch Model - Live Match Scoring System
 * Note: Named GameMatch instead of Match because 'match' is a reserved keyword in PHP 8.0+
 *
 * This is the main facade that delegates to specialized classes:
 * - MatchScoring: Start, record ends, complete matches
 * - MatchPermissions: Access control and scorer claiming
 * - MatchQueries: Complex queries and listings
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/MatchScoring.php';
require_once __DIR__ . '/MatchPermissions.php';
require_once __DIR__ . '/MatchQueries.php';

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

    // ========== Core CRUD ==========

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT m.*, c.name as club_name, p.name as created_by_name
            FROM matches m
            LEFT JOIN clubs c ON c.id = m.club_id
            JOIN players p ON p.id = m.created_by
            WHERE m.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function createBounce(int $createdBy, ?string $matchName, int $playersPerTeam, int $bowlsPerPlayer, string $scoringMode, int $targetScore, ?int $clubId = null): array {
        $db = Database::getInstance();
        $shareToken = bin2hex(random_bytes(32));

        $stmt = $db->prepare('
            INSERT INTO matches (club_id, created_by, game_type, match_name, bowls_per_player, players_per_team, scoring_mode, target_score, share_token, status)
            VALUES (:club_id, :created_by, "bounce", :match_name, :bowls_per_player, :players_per_team, :scoring_mode, :target_score, :share_token, "setup")
        ');
        $stmt->execute([
            'club_id'          => $clubId,
            'created_by'       => $createdBy,
            'match_name'       => $matchName ?: null,
            'bowls_per_player' => $bowlsPerPlayer,
            'players_per_team' => $playersPerTeam,
            'scoring_mode'     => $scoringMode,
            'target_score'     => $targetScore,
            'share_token'      => $shareToken
        ]);

        return ['match_id' => (int)$db->lastInsertId(), 'share_token' => $shareToken];
    }

    public static function findByToken(string $token): ?array {
        if (strlen($token) < 16) return null;
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT m.*, p.name as created_by_name
            FROM matches m
            JOIN players p ON p.id = m.created_by
            WHERE m.share_token = :token AND m.game_type = "bounce"
        ');
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function addBouncePlayer(int $teamId, int $playerNumber, string $playerName): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO match_players (team_id, position, position_label, player_name)
            VALUES (:team_id, "player", :position_label, :player_name)
        ');
        $stmt->execute([
            'team_id'        => $teamId,
            'position_label' => 'Player ' . $playerNumber,
            'player_name'    => $playerName
        ]);
        return (int)$db->lastInsertId();
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

    public static function delete(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('DELETE FROM matches WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ========== Scoring (delegated to MatchScoring) ==========

    public static function start(int $id): bool {
        return MatchScoring::start($id);
    }

    public static function recordEnd(int $matchId, int $endNumber, int $scoringTeam, int $shots): int {
        return MatchScoring::recordEnd($matchId, $endNumber, $scoringTeam, $shots);
    }

    public static function undoLastEnd(int $matchId): bool {
        return MatchScoring::undoLastEnd($matchId);
    }

    public static function complete(int $id): bool {
        return MatchScoring::complete($id);
    }

    public static function getScores(int $id): ?array {
        return MatchScoring::getScores($id);
    }

    // ========== Queries (delegated to MatchQueries) ==========

    public static function findWithDetails(int $id): ?array {
        return MatchQueries::findWithDetails($id);
    }

    public static function findBounceWithDetails(string $token): ?array {
        return MatchQueries::findBounceWithDetails($token);
    }

    public static function getLiveMatchesForClub(int $clubId): array {
        return MatchQueries::getLiveMatchesForClub($clubId);
    }

    public static function getLiveBounceGames(): array {
        return MatchQueries::getLiveBounceGames();
    }

    public static function listByClub(int $clubId, ?string $status = null, int $limit = 20): array {
        return MatchQueries::listByClub($clubId, $status, $limit);
    }

    // ========== Permissions (delegated to MatchPermissions) ==========

    public static function canCreate(int $playerId, int $clubId): bool {
        return MatchPermissions::canCreate($playerId, $clubId);
    }

    public static function isPaidMember(int $playerId): bool {
        return MatchPermissions::isPaidMember($playerId);
    }

    public static function claimScorer(int $matchId, int $playerId): bool {
        return MatchPermissions::claimScorer($matchId, $playerId);
    }

    public static function releaseScorer(int $matchId, int $playerId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }
        return MatchPermissions::releaseScorer($matchId, $playerId, $match);
    }

    public static function canScore(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }
        return MatchPermissions::canScore($playerId, $matchId, $match);
    }

    public static function canClaimScorer(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }
        return MatchPermissions::canClaimScorer($playerId, $match);
    }

    public static function canView(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }
        if ($match['game_type'] === 'bounce') {
            return true; // any logged-in user can view bounce games
        }
        return MatchPermissions::canView($playerId, $match['club_id']);
    }

    public static function canDelete(int $playerId, int $matchId): bool {
        $match = self::find($matchId);
        if (!$match) {
            return false;
        }
        return MatchPermissions::canDelete($playerId, $match);
    }
}
