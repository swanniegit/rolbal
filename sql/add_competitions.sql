-- Club Competition System - Round Robin & Knockout Tournaments
-- Run this migration after add_matches.sql

-- Competition definitions
CREATE TABLE IF NOT EXISTS competitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    format ENUM('round_robin', 'knockout', 'combined') NOT NULL,
    game_type ENUM('singles', 'pairs', 'trips', 'fours') NOT NULL,
    bowls_per_player TINYINT UNSIGNED NOT NULL DEFAULT 4,
    scoring_mode ENUM('ends', 'first_to') DEFAULT 'ends',
    target_score TINYINT UNSIGNED NOT NULL DEFAULT 21,
    max_participants INT UNSIGNED DEFAULT NULL,
    knockout_qualifiers TINYINT UNSIGNED DEFAULT 2 COMMENT 'For combined format: top N from each group advance',
    group_count TINYINT UNSIGNED DEFAULT NULL COMMENT 'Number of groups for round_robin/combined',
    status ENUM('draft', 'registration', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
    registration_opens DATETIME DEFAULT NULL,
    registration_closes DATETIME DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES players(id),
    INDEX idx_club_status (club_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participants (individuals or teams)
CREATE TABLE IF NOT EXISTS competition_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED NOT NULL,
    team_name VARCHAR(100) DEFAULT NULL,
    seed INT UNSIGNED DEFAULT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    withdrawn_at TIMESTAMP NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    INDEX idx_competition (competition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Players in each participant/team
CREATE TABLE IF NOT EXISTS competition_participant_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    position ENUM('skip', 'third', 'second', 'lead') NOT NULL,
    FOREIGN KEY (participant_id) REFERENCES competition_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant_position (participant_id, position),
    UNIQUE KEY unique_participant_player (participant_id, player_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups for round robin
CREATE TABLE IF NOT EXISTS competition_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED NOT NULL,
    group_name VARCHAR(50) NOT NULL,
    group_number TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_competition_group (competition_id, group_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group assignments
CREATE TABLE IF NOT EXISTS competition_group_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    participant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (group_id) REFERENCES competition_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES competition_participants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_participant (group_id, participant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixtures (scheduled matches)
CREATE TABLE IF NOT EXISTS competition_fixtures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED NOT NULL,
    stage ENUM('group', 'play_in', 'round_of_64', 'round_of_32', 'round_of_16', 'quarter_final', 'semi_final', 'third_place', 'final') NOT NULL,
    round_number TINYINT UNSIGNED DEFAULT 1 COMMENT 'Round within stage (for group: match day)',
    bracket_position INT UNSIGNED DEFAULT NULL COMMENT 'Position in bracket for knockout stages',
    group_id INT UNSIGNED DEFAULT NULL,
    participant1_id INT UNSIGNED DEFAULT NULL,
    participant2_id INT UNSIGNED DEFAULT NULL,
    winner_from_fixture_1 INT UNSIGNED DEFAULT NULL COMMENT 'Fixture ID whose winner fills participant1',
    winner_from_fixture_2 INT UNSIGNED DEFAULT NULL COMMENT 'Fixture ID whose winner fills participant2',
    match_id INT UNSIGNED DEFAULT NULL COMMENT 'Linked live match',
    scheduled_at DATETIME DEFAULT NULL,
    winner_id INT UNSIGNED DEFAULT NULL,
    score1 INT UNSIGNED DEFAULT NULL,
    score2 INT UNSIGNED DEFAULT NULL,
    status ENUM('pending', 'scheduled', 'live', 'completed', 'walkover', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES competition_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (participant1_id) REFERENCES competition_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (participant2_id) REFERENCES competition_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_from_fixture_1) REFERENCES competition_fixtures(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_from_fixture_2) REFERENCES competition_fixtures(id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES competition_participants(id) ON DELETE SET NULL,
    INDEX idx_competition_stage (competition_id, stage),
    INDEX idx_match (match_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standings cache for round robin
CREATE TABLE IF NOT EXISTS competition_standings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED DEFAULT NULL,
    participant_id INT UNSIGNED NOT NULL,
    played INT UNSIGNED DEFAULT 0,
    won INT UNSIGNED DEFAULT 0,
    lost INT UNSIGNED DEFAULT 0,
    drawn INT UNSIGNED DEFAULT 0,
    ends_for INT UNSIGNED DEFAULT 0,
    ends_against INT UNSIGNED DEFAULT 0,
    shots_for INT UNSIGNED DEFAULT 0,
    shots_against INT UNSIGNED DEFAULT 0,
    points INT UNSIGNED DEFAULT 0,
    position TINYINT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES competition_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (participant_id) REFERENCES competition_participants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_standings (competition_id, group_id, participant_id),
    INDEX idx_competition_group (competition_id, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
