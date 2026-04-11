-- Mobile JWT refresh token storage
-- Run after add_players.sql

CREATE TABLE IF NOT EXISTS player_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id   INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64) NOT NULL,        -- SHA-256 of the refresh token
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_player_id (player_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
