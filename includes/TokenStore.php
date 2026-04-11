<?php
/**
 * Refresh token store — DB-backed, hashed at rest.
 *
 * Flow:
 *   1. Login (mobile) → TokenStore::issue() → { access_token, refresh_token, expires_in }
 *   2. Access token expires → TokenStore::refresh($refreshToken) → { access_token, expires_in }
 *   3. Logout → TokenStore::revoke($refreshToken)
 *
 * Access tokens are short-lived JWTs (15 min, stateless).
 * Refresh tokens are 64-char hex strings stored as SHA-256 hashes (30 days).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Jwt.php';

class TokenStore {

    const ACCESS_TTL  = 900;      // 15 minutes
    const REFRESH_TTL = 2592000;  // 30 days

    public static function issue(int $playerId): array {
        $accessToken  = self::makeAccessToken($playerId);
        $refreshToken = bin2hex(random_bytes(32));
        $hash         = hash('sha256', $refreshToken);
        $expires      = date('Y-m-d H:i:s', time() + self::REFRESH_TTL);

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO player_tokens (player_id, token_hash, expires_at)
            VALUES (:player_id, :hash, :expires_at)
        ');
        $stmt->execute([
            'player_id' => $playerId,
            'hash'      => $hash,
            'expires_at'=> $expires,
        ]);

        // Prune expired tokens ~1% of the time to avoid a cron dependency
        if (random_int(1, 100) === 1) {
            self::cleanup();
        }

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => self::ACCESS_TTL,
        ];
    }

    /**
     * Exchange a valid refresh token for a new access token.
     * Refresh token itself is NOT rotated (simpler for mobile offline scenarios).
     */
    public static function refresh(string $refreshToken): ?array {
        $hash = hash('sha256', $refreshToken);

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT player_id FROM player_tokens
            WHERE token_hash = :hash AND expires_at > NOW()
        ');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'access_token' => self::makeAccessToken((int) $row['player_id']),
            'expires_in'   => self::ACCESS_TTL,
        ];
    }

    public static function revoke(string $refreshToken): void {
        $hash = hash('sha256', $refreshToken);
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM player_tokens WHERE token_hash = :hash');
        $stmt->execute(['hash' => $hash]);
    }

    public static function revokeAll(int $playerId): void {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM player_tokens WHERE player_id = :player_id');
        $stmt->execute(['player_id' => $playerId]);
    }

    private static function makeAccessToken(int $playerId): string {
        return Jwt::encode([
            'sub' => $playerId,
            'iat' => time(),
            'exp' => time() + self::ACCESS_TTL,
        ], JWT_SECRET);
    }

    private static function cleanup(): void {
        Database::getInstance()->exec('DELETE FROM player_tokens WHERE expires_at < NOW()');
    }
}
