<?php
/**
 * Competition Participant Model
 *
 * Handles registration, team composition, and participant management.
 */

require_once __DIR__ . '/db.php';

class CompetitionParticipant {

    // ========== Core CRUD ==========

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT cp.*, c.game_type, c.club_id, c.status as competition_status
            FROM competition_participants cp
            JOIN competitions c ON c.id = cp.competition_id
            WHERE cp.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findWithPlayers(int $id): ?array {
        $participant = self::find($id);
        if (!$participant) {
            return null;
        }

        $participant['players'] = self::getPlayers($id);
        return $participant;
    }

    /**
     * Register a new participant (individual or team)
     *
     * @param int $competitionId Competition ID
     * @param array $players Array of ['player_id' => X, 'position' => 'skip|third|second|lead']
     * @param string|null $teamName Optional team name (for pairs/trips/fours)
     * @return int Participant ID
     */
    public static function register(int $competitionId, array $players, ?string $teamName = null): int {
        $db = Database::getInstance();

        $db->beginTransaction();

        try {
            // Create participant entry
            $stmt = $db->prepare('
                INSERT INTO competition_participants (competition_id, team_name)
                VALUES (:competition_id, :team_name)
            ');
            $stmt->execute([
                'competition_id' => $competitionId,
                'team_name' => $teamName ? trim($teamName) : null
            ]);

            $participantId = (int)$db->lastInsertId();

            // Add players
            $stmt = $db->prepare('
                INSERT INTO competition_participant_players (participant_id, player_id, position)
                VALUES (:participant_id, :player_id, :position)
            ');

            foreach ($players as $player) {
                $stmt->execute([
                    'participant_id' => $participantId,
                    'player_id' => $player['player_id'],
                    'position' => $player['position']
                ]);
            }

            // Initialize standings record
            $stmt = $db->prepare('
                INSERT INTO competition_standings (competition_id, participant_id)
                VALUES (:competition_id, :participant_id)
            ');
            $stmt->execute([
                'competition_id' => $competitionId,
                'participant_id' => $participantId
            ]);

            $db->commit();
            return $participantId;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function withdraw(int $id): bool {
        $db = Database::getInstance();

        // Only allow withdrawal if no fixtures have started
        $participant = self::find($id);
        if (!$participant) {
            return false;
        }

        // Check if participant has any completed or live fixtures
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM competition_fixtures
            WHERE (participant1_id = :id OR participant2_id = :id)
            AND status IN ("live", "completed")
        ');
        $stmt->execute(['id' => $id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }

        $stmt = $db->prepare('
            UPDATE competition_participants
            SET withdrawn_at = NOW()
            WHERE id = :id AND withdrawn_at IS NULL
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function reinstate(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competition_participants
            SET withdrawn_at = NULL
            WHERE id = :id AND withdrawn_at IS NOT NULL
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool {
        $db = Database::getInstance();

        // Only allow deletion if competition is in draft
        $participant = self::find($id);
        if (!$participant || $participant['competition_status'] !== 'draft') {
            return false;
        }

        $stmt = $db->prepare('DELETE FROM competition_participants WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ========== Seeding ==========

    public static function setSeed(int $id, ?int $seed): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competition_participants
            SET seed = :seed
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'seed' => $seed]);

        return $stmt->rowCount() > 0;
    }

    public static function autoSeed(int $competitionId): bool {
        $db = Database::getInstance();

        // Assign seeds 1, 2, 3... based on registration order
        $stmt = $db->prepare('
            UPDATE competition_participants
            SET seed = (
                SELECT r FROM (
                    SELECT id, ROW_NUMBER() OVER (ORDER BY registered_at) as r
                    FROM competition_participants
                    WHERE competition_id = :competition_id AND withdrawn_at IS NULL
                ) t WHERE t.id = competition_participants.id
            )
            WHERE competition_id = :competition_id2 AND withdrawn_at IS NULL
        ');

        try {
            $stmt->execute([
                'competition_id' => $competitionId,
                'competition_id2' => $competitionId
            ]);
            return true;
        } catch (PDOException $e) {
            // Fallback for MySQL versions without window functions
            $participants = self::listByCompetition($competitionId);
            $seed = 1;
            foreach ($participants as $p) {
                self::setSeed($p['id'], $seed++);
            }
            return true;
        }
    }

    // ========== Queries ==========

    public static function count(int $competitionId, bool $includeWithdrawn = false): int {
        $db = Database::getInstance();

        $sql = 'SELECT COUNT(*) FROM competition_participants WHERE competition_id = :id';
        if (!$includeWithdrawn) {
            $sql .= ' AND withdrawn_at IS NULL';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $competitionId]);
        return (int)$stmt->fetchColumn();
    }

    public static function listByCompetition(int $competitionId, bool $includeWithdrawn = false): array {
        $db = Database::getInstance();

        $sql = '
            SELECT cp.*
            FROM competition_participants cp
            WHERE cp.competition_id = :competition_id
        ';
        if (!$includeWithdrawn) {
            $sql .= ' AND cp.withdrawn_at IS NULL';
        }
        $sql .= ' ORDER BY cp.seed ASC, cp.registered_at ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute(['competition_id' => $competitionId]);
        $participants = $stmt->fetchAll();

        // Add players to each participant
        foreach ($participants as &$p) {
            $p['players'] = self::getPlayers($p['id']);
            $p['display_name'] = self::getDisplayName($p);
        }

        return $participants;
    }

    public static function getPlayers(int $participantId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT cpp.*, p.name as player_name, p.hand
            FROM competition_participant_players cpp
            JOIN players p ON p.id = cpp.player_id
            WHERE cpp.participant_id = :participant_id
            ORDER BY FIELD(cpp.position, "skip", "third", "second", "lead")
        ');
        $stmt->execute(['participant_id' => $participantId]);
        return $stmt->fetchAll();
    }

    public static function findByPlayer(int $competitionId, int $playerId): ?array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT cp.*
            FROM competition_participants cp
            JOIN competition_participant_players cpp ON cpp.participant_id = cp.id
            WHERE cp.competition_id = :competition_id
            AND cpp.player_id = :player_id
            AND cp.withdrawn_at IS NULL
        ');
        $stmt->execute([
            'competition_id' => $competitionId,
            'player_id' => $playerId
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function isPlayerRegistered(int $competitionId, int $playerId): bool {
        return self::findByPlayer($competitionId, $playerId) !== null;
    }

    public static function getByGroup(int $groupId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT cp.*
            FROM competition_participants cp
            JOIN competition_group_participants cgp ON cgp.participant_id = cp.id
            WHERE cgp.group_id = :group_id
            AND cp.withdrawn_at IS NULL
            ORDER BY cp.seed ASC
        ');
        $stmt->execute(['group_id' => $groupId]);
        $participants = $stmt->fetchAll();

        foreach ($participants as &$p) {
            $p['players'] = self::getPlayers($p['id']);
            $p['display_name'] = self::getDisplayName($p);
        }

        return $participants;
    }

    // ========== Helpers ==========

    public static function getDisplayName(array $participant): string {
        if (!empty($participant['team_name'])) {
            return $participant['team_name'];
        }

        $players = $participant['players'] ?? self::getPlayers($participant['id']);

        if (count($players) === 1) {
            return $players[0]['player_name'];
        }

        $names = array_column($players, 'player_name');
        return implode(' / ', $names);
    }

    /**
     * Validate that players array matches competition game type requirements
     */
    public static function validatePlayers(int $competitionId, array $players): array {
        require_once __DIR__ . '/Competition.php';
        require_once __DIR__ . '/GameMatch.php';

        $competition = Competition::find($competitionId);
        if (!$competition) {
            return ['valid' => false, 'error' => 'Competition not found'];
        }

        $gameType = $competition['game_type'];
        $config = GameMatch::GAME_TYPES[$gameType] ?? null;

        if (!$config) {
            return ['valid' => false, 'error' => 'Invalid game type'];
        }

        $requiredPlayers = $config['players_per_team'];
        $validPositions = $config['positions'];

        if (count($players) !== $requiredPlayers) {
            return [
                'valid' => false,
                'error' => "Game type '$gameType' requires $requiredPlayers player(s)"
            ];
        }

        $positions = array_column($players, 'position');
        $playerIds = array_column($players, 'player_id');

        // Check positions match game type
        sort($positions);
        sort($validPositions);
        if ($positions !== $validPositions) {
            return [
                'valid' => false,
                'error' => 'Invalid positions for game type'
            ];
        }

        // Check for duplicate players
        if (count($playerIds) !== count(array_unique($playerIds))) {
            return ['valid' => false, 'error' => 'Duplicate players not allowed'];
        }

        // Check all players are club members
        require_once __DIR__ . '/ClubMember.php';
        foreach ($playerIds as $playerId) {
            if (!ClubMember::isMember($competition['club_id'], $playerId)) {
                return ['valid' => false, 'error' => 'All players must be club members'];
            }
        }

        // Check players aren't already registered
        foreach ($playerIds as $playerId) {
            if (self::isPlayerRegistered($competitionId, $playerId)) {
                return ['valid' => false, 'error' => 'One or more players already registered'];
            }
        }

        return ['valid' => true];
    }
}
