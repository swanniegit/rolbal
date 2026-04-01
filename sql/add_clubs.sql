-- Migration: Add clubs and club memberships
-- Run this script to add bowling club support

-- Clubs table
CREATE TABLE clubs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    owner_id INT UNSIGNED NOT NULL,
    icon_filename VARCHAR(255) DEFAULT NULL,
    is_public TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES players(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Club memberships table
CREATE TABLE club_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (club_id, player_id),
    INDEX idx_club (club_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add primary club reference to players
ALTER TABLE players
ADD COLUMN primary_club_id INT UNSIGNED NULL AFTER hand,
ADD CONSTRAINT fk_primary_club FOREIGN KEY (primary_club_id) REFERENCES clubs(id) ON DELETE SET NULL;
