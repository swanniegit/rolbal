-- Migration: Add players table and modify sessions table
-- Run this script to add user registration and login support

-- Players/Users table
CREATE TABLE players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    hand ENUM('L', 'R') DEFAULT 'R',
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64) DEFAULT NULL,
    token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modify sessions table to link to players
ALTER TABLE sessions
ADD COLUMN player_id INT UNSIGNED NULL AFTER id,
ADD COLUMN is_public TINYINT(1) DEFAULT 1 COMMENT '1=visible to all, 0=private',
ADD CONSTRAINT fk_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL;

-- Index for player lookups
CREATE INDEX idx_sessions_player ON sessions(player_id);
