-- Live Match Scoring System
-- Run this migration after add_challenges.sql

-- Live matches
CREATE TABLE IF NOT EXISTS matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id INT UNSIGNED NOT NULL,
    game_type ENUM('singles', 'pairs', 'trips', 'fours') NOT NULL,
    bowls_per_player TINYINT UNSIGNED NOT NULL DEFAULT 4,
    total_ends TINYINT UNSIGNED NOT NULL DEFAULT 21,
    status ENUM('setup', 'live', 'completed') DEFAULT 'setup',
    created_by INT UNSIGNED NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES players(id),
    INDEX idx_club_status (club_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Match teams (2 teams per match)
CREATE TABLE IF NOT EXISTS match_teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL,
    team_number TINYINT UNSIGNED NOT NULL, -- 1 or 2
    team_name VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_team (match_id, team_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team players with positions
CREATE TABLE IF NOT EXISTS match_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    position ENUM('skip', 'third', 'second', 'lead') NOT NULL,
    player_name VARCHAR(100) NOT NULL,
    player_id INT UNSIGNED NULL, -- optional link to registered player
    FOREIGN KEY (team_id) REFERENCES match_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Match ends (scores per end)
CREATE TABLE IF NOT EXISTS match_ends (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL,
    end_number TINYINT UNSIGNED NOT NULL,
    scoring_team TINYINT UNSIGNED NOT NULL, -- 1 or 2
    shots TINYINT UNSIGNED NOT NULL DEFAULT 1,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_end (match_id, end_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
