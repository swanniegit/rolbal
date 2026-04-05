<?php
/**
 * Competition Model - Club Competition System
 *
 * Main facade for competition CRUD, status changes, and permissions.
 * Delegates to specialized classes for bracket generation, round robin, etc.
 */

require_once __DIR__ . '/db.php';

class Competition {

    const FORMATS = ['round_robin', 'knockout', 'combined'];

    const STATUSES = ['draft', 'registration', 'in_progress', 'completed', 'cancelled'];

    const STATUS_LABELS = [
        'draft' => 'Draft',
        'registration' => 'Registration Open',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];

    const FORMAT_LABELS = [
        'round_robin' => 'Round Robin',
        'knockout' => 'Knockout',
        'combined' => 'Group Stage + Knockout'
    ];

    // Points system for match results
    const POINTS_WIN = 2;
    const POINTS_DRAW = 1;
    const POINTS_LOSS = 0;

    // ========== Core CRUD ==========

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, cl.name as club_name, p.name as created_by_name
            FROM competitions c
            JOIN clubs cl ON cl.id = c.club_id
            JOIN players p ON p.id = c.created_by
            WHERE c.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(
        int $clubId,
        int $createdBy,
        string $name,
        string $format,
        string $gameType,
        array $options = []
    ): int {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO competitions (
                club_id, created_by, name, description, format, game_type,
                bowls_per_player, scoring_mode, target_score, max_participants,
                knockout_qualifiers, group_count, rink_count, qualifiers_per_section,
                teams_per_section, registration_opens, registration_closes
            ) VALUES (
                :club_id, :created_by, :name, :description, :format, :game_type,
                :bowls_per_player, :scoring_mode, :target_score, :max_participants,
                :knockout_qualifiers, :group_count, :rink_count, :qualifiers_per_section,
                :teams_per_section, :registration_opens, :registration_closes
            )
        ');

        $stmt->execute([
            'club_id' => $clubId,
            'created_by' => $createdBy,
            'name' => trim($name),
            'description' => isset($options['description']) ? trim($options['description']) : null,
            'format' => $format,
            'game_type' => $gameType,
            'bowls_per_player' => $options['bowls_per_player'] ?? 4,
            'scoring_mode' => $options['scoring_mode'] ?? 'ends',
            'target_score' => $options['target_score'] ?? 21,
            'max_participants' => $options['max_participants'] ?? null,
            'knockout_qualifiers' => $options['knockout_qualifiers'] ?? 2,
            'group_count' => $options['group_count'] ?? null,
            'rink_count' => $options['rink_count'] ?? 6,
            'qualifiers_per_section' => $options['qualifiers_per_section'] ?? 2,
            'teams_per_section' => $options['teams_per_section'] ?? 4,
            'registration_opens' => $options['registration_opens'] ?? null,
            'registration_closes' => $options['registration_closes'] ?? null
        ]);

        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getInstance();

        $allowed = [
            'name', 'description', 'bowls_per_player', 'scoring_mode', 'target_score',
            'max_participants', 'knockout_qualifiers', 'group_count', 'rink_count',
            'qualifiers_per_section', 'teams_per_section', 'registration_opens', 'registration_closes'
        ];

        $updates = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE competitions SET ' . implode(', ', $updates) . ' WHERE id = :id AND status = "draft"';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('DELETE FROM competitions WHERE id = :id AND status = "draft"');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ========== Status Changes ==========

    public static function openRegistration(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competitions
            SET status = "registration"
            WHERE id = :id AND status = "draft"
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function closeRegistration(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competitions
            SET status = "draft"
            WHERE id = :id AND status = "registration"
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function start(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competitions
            SET status = "in_progress", started_at = NOW()
            WHERE id = :id AND status IN ("draft", "registration")
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function complete(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competitions
            SET status = "completed", completed_at = NOW()
            WHERE id = :id AND status = "in_progress"
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function cancel(int $id): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE competitions
            SET status = "cancelled"
            WHERE id = :id AND status NOT IN ("completed", "cancelled")
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ========== Listings ==========

    public static function listByClub(int $clubId, ?string $status = null, int $limit = 20): array {
        $db = Database::getInstance();

        $sql = '
            SELECT c.*,
                   (SELECT COUNT(*) FROM competition_participants cp WHERE cp.competition_id = c.id AND cp.withdrawn_at IS NULL) as participant_count,
                   p.name as created_by_name
            FROM competitions c
            JOIN players p ON p.id = c.created_by
            WHERE c.club_id = :club_id
        ';
        $params = ['club_id' => $clubId];

        if ($status) {
            $sql .= ' AND c.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY c.created_at DESC LIMIT :limit';

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getActiveForClub(int $clubId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT c.*,
                   (SELECT COUNT(*) FROM competition_participants cp WHERE cp.competition_id = c.id AND cp.withdrawn_at IS NULL) as participant_count
            FROM competitions c
            WHERE c.club_id = :club_id
            AND c.status IN ("registration", "in_progress")
            ORDER BY c.started_at DESC, c.created_at DESC
        ');
        $stmt->execute(['club_id' => $clubId]);

        return $stmt->fetchAll();
    }

    public static function getCompetitionsForPlayer(int $playerId, int $limit = 10): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT DISTINCT c.*, cl.name as club_name
            FROM competitions c
            JOIN clubs cl ON cl.id = c.club_id
            JOIN competition_participants cp ON cp.competition_id = c.id
            JOIN competition_participant_players cpp ON cpp.participant_id = cp.id
            WHERE cpp.player_id = :player_id
            AND cp.withdrawn_at IS NULL
            ORDER BY c.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ========== Permissions ==========

    public static function canCreate(int $playerId, int $clubId): bool {
        require_once __DIR__ . '/Club.php';
        return Club::canManage($clubId, $playerId);
    }

    public static function canManage(int $playerId, int $competitionId): bool {
        $competition = self::find($competitionId);
        if (!$competition) {
            return false;
        }

        require_once __DIR__ . '/Club.php';
        return Club::canManage($competition['club_id'], $playerId);
    }

    public static function canView(int $playerId, int $competitionId): bool {
        $competition = self::find($competitionId);
        if (!$competition) {
            return false;
        }

        require_once __DIR__ . '/ClubMember.php';
        return ClubMember::isMember($competition['club_id'], $playerId);
    }

    public static function canRegister(int $playerId, int $competitionId): bool {
        $competition = self::find($competitionId);
        if (!$competition) {
            return false;
        }

        if ($competition['status'] !== 'registration') {
            return false;
        }

        require_once __DIR__ . '/ClubMember.php';
        if (!ClubMember::isMember($competition['club_id'], $playerId)) {
            return false;
        }

        // Check if registration dates apply
        $now = date('Y-m-d H:i:s');
        if ($competition['registration_opens'] && $now < $competition['registration_opens']) {
            return false;
        }
        if ($competition['registration_closes'] && $now > $competition['registration_closes']) {
            return false;
        }

        // Check if max participants reached
        if ($competition['max_participants']) {
            require_once __DIR__ . '/CompetitionParticipant.php';
            $count = CompetitionParticipant::count($competitionId);
            if ($count >= $competition['max_participants']) {
                return false;
            }
        }

        return true;
    }

    // ========== Utilities ==========

    public static function getStatusLabel(string $status): string {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function getFormatLabel(string $format): string {
        return self::FORMAT_LABELS[$format] ?? $format;
    }

    public static function getParticipantCount(int $id): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM competition_participants
            WHERE competition_id = :id AND withdrawn_at IS NULL
        ');
        $stmt->execute(['id' => $id]);
        return (int)$stmt->fetchColumn();
    }

    public static function getWithDetails(int $id): ?array {
        $competition = self::find($id);
        if (!$competition) {
            return null;
        }

        require_once __DIR__ . '/CompetitionParticipant.php';
        $competition['participants'] = CompetitionParticipant::listByCompetition($id);
        $competition['participant_count'] = count($competition['participants']);

        return $competition;
    }

    /**
     * Calculate match points based on scores
     * @param int $forScore - Shots scored
     * @param int $againstScore - Shots conceded
     * @param bool $drawAllowed - Whether draws are allowed (false for singles first-to)
     * @return int Points awarded (2=win, 1=draw, 0=loss)
     */
    public static function calculatePoints(int $forScore, int $againstScore, bool $drawAllowed = true): int {
        if ($forScore > $againstScore) {
            return self::POINTS_WIN;
        }
        if ($forScore < $againstScore) {
            return self::POINTS_LOSS;
        }
        // Draw
        return $drawAllowed ? self::POINTS_DRAW : self::POINTS_WIN; // If no draws, someone must win
    }

    /**
     * Check if draws are allowed based on game type
     * Singles = first-to-X = no draws
     */
    public static function isDrawAllowed(string $gameType): bool {
        return $gameType !== 'singles';
    }
}
