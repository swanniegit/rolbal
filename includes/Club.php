<?php
/**
 * Club Model
 */

require_once __DIR__ . '/db.php';

class Club {

    public static function create(string $name, int $ownerId, ?string $description = null, ?string $iconFilename = null): int {
        $db = Database::getInstance();

        $slug = self::generateSlug($name);

        $stmt = $db->prepare('
            INSERT INTO clubs (name, slug, description, owner_id, icon_filename)
            VALUES (:name, :slug, :description, :owner_id, :icon_filename)
        ');
        $stmt->execute([
            'name' => trim($name),
            'slug' => $slug,
            'description' => $description ? trim($description) : null,
            'owner_id' => $ownerId,
            'icon_filename' => $iconFilename
        ]);

        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, p.name as owner_name
            FROM clubs c
            JOIN players p ON c.owner_id = p.id
            WHERE c.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findBySlug(string $slug): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, p.name as owner_name
            FROM clubs c
            JOIN players p ON c.owner_id = p.id
            WHERE c.slug = :slug
        ');
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function all(int $limit = 50): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, p.name as owner_name,
                   (SELECT COUNT(*) FROM club_members WHERE club_id = c.id) as member_count
            FROM clubs c
            JOIN players p ON c.owner_id = p.id
            WHERE c.is_public = 1
            ORDER BY c.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function search(string $query, int $limit = 20): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT c.*, p.name as owner_name,
                   (SELECT COUNT(*) FROM club_members WHERE club_id = c.id) as member_count
            FROM clubs c
            JOIN players p ON c.owner_id = p.id
            WHERE c.is_public = 1
            AND (c.name LIKE :query OR c.description LIKE :query)
            ORDER BY c.name ASC
            LIMIT :limit
        ');
        $stmt->bindValue('query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getInstance();

        $allowed = ['name', 'description', 'icon_filename', 'is_public'];
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

        if (isset($data['name'])) {
            $updates[] = 'slug = :slug';
            $params['slug'] = self::generateSlug($data['name'], $id);
        }

        $sql = 'UPDATE clubs SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function delete(int $id, int $ownerId): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT icon_filename FROM clubs WHERE id = :id AND owner_id = :owner_id');
        $stmt->execute(['id' => $id, 'owner_id' => $ownerId]);
        $club = $stmt->fetch();

        if (!$club) {
            return false;
        }

        if ($club['icon_filename']) {
            require_once __DIR__ . '/Upload.php';
            Upload::deleteClubIcon($club['icon_filename']);
        }

        $stmt = $db->prepare('DELETE FROM clubs WHERE id = :id AND owner_id = :owner_id');
        return $stmt->execute(['id' => $id, 'owner_id' => $ownerId]);
    }

    public static function getMembers(int $clubId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT p.id, p.name, p.hand, p.whatsapp_number, cm.role, cm.joined_at
            FROM club_members cm
            JOIN players p ON cm.player_id = p.id
            WHERE cm.club_id = :club_id
            ORDER BY
                CASE cm.role
                    WHEN "owner" THEN 1
                    WHEN "admin" THEN 2
                    ELSE 3
                END,
                cm.joined_at ASC
        ');
        $stmt->execute(['club_id' => $clubId]);
        return $stmt->fetchAll();
    }

    public static function getMemberCount(int $clubId): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM club_members WHERE club_id = :club_id');
        $stmt->execute(['club_id' => $clubId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getStats(int $clubId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT
                COUNT(DISTINCT cm.player_id) as total_members,
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(r.id) as total_rolls
            FROM club_members cm
            LEFT JOIN sessions s ON s.player_id = cm.player_id AND s.is_public = 1
            LEFT JOIN rolls r ON r.session_id = s.id
            WHERE cm.club_id = :club_id
        ');
        $stmt->execute(['club_id' => $clubId]);
        return $stmt->fetch() ?: [
            'total_members' => 0,
            'total_sessions' => 0,
            'total_rolls' => 0
        ];
    }

    public static function isOwner(int $clubId, int $playerId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT 1 FROM clubs WHERE id = :id AND owner_id = :player_id');
        $stmt->execute(['id' => $clubId, 'player_id' => $playerId]);
        return (bool) $stmt->fetch();
    }

    public static function canManage(int $clubId, int $playerId): bool {
        $db = Database::getInstance();

        if (self::isOwner($clubId, $playerId)) {
            return true;
        }

        $stmt = $db->prepare('
            SELECT 1 FROM club_members
            WHERE club_id = :club_id AND player_id = :player_id AND role = "admin"
        ');
        $stmt->execute(['club_id' => $clubId, 'player_id' => $playerId]);
        return (bool) $stmt->fetch();
    }

    private static function generateSlug(string $name, ?int $excludeId = null): string {
        $db = Database::getInstance();

        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'club';
        }

        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $sql = 'SELECT 1 FROM clubs WHERE slug = :slug';
            $params = ['slug' => $slug];

            if ($excludeId) {
                $sql .= ' AND id != :exclude_id';
                $params['exclude_id'] = $excludeId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
