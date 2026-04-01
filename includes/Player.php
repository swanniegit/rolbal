<?php
/**
 * Player Model
 */

require_once __DIR__ . '/db.php';

class Player {

    public static function create(string $email, string $password, string $name, string $hand = 'R'): int {
        $db = Database::getInstance();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $token = self::generateToken();
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $db->prepare('
            INSERT INTO players (email, password_hash, name, hand, verification_token, token_expires)
            VALUES (:email, :password_hash, :name, :hand, :token, :token_expires)
        ');
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'name' => trim($name),
            'hand' => $hand,
            'token' => $token,
            'token_expires' => $tokenExpires
        ]);

        return (int) $db->lastInsertId();
    }

    public static function findByEmail(string $email): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM players WHERE email = :email');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM players WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function verify(string $token): bool {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT id FROM players
            WHERE verification_token = :token
            AND token_expires > NOW()
            AND email_verified = 0
        ');
        $stmt->execute(['token' => $token]);
        $player = $stmt->fetch();

        if (!$player) {
            return false;
        }

        $stmt = $db->prepare('
            UPDATE players
            SET email_verified = 1, verification_token = NULL, token_expires = NULL
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $player['id']]);
    }

    public static function validatePassword(string $email, string $password): ?array {
        $player = self::findByEmail($email);

        if (!$player) {
            return null;
        }

        if (!password_verify($password, $player['password_hash'])) {
            return null;
        }

        return $player;
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }

    public static function getStats(int $playerId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(r.id) as total_rolls,
                MIN(s.session_date) as first_session,
                MAX(s.session_date) as last_session
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            WHERE s.player_id = :player_id
        ');
        $stmt->execute(['player_id' => $playerId]);
        return $stmt->fetch() ?: [
            'total_sessions' => 0,
            'total_rolls' => 0,
            'first_session' => null,
            'last_session' => null
        ];
    }

    public static function getSessions(int $playerId, int $limit = 20): array {
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

    public static function getPublicSessions(int $playerId, int $limit = 20): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*, COUNT(r.id) as roll_count
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            WHERE s.player_id = :player_id AND s.is_public = 1
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
