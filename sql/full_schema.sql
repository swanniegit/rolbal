-- Rolbal Database Schema

CREATE DATABASE IF NOT EXISTS rolbal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rolbal;

-- Sessions table
CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hand ENUM('L', 'R') NOT NULL COMMENT '15: Left or Right handed',
    session_date DATE NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Rolls table
CREATE TABLE rolls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    delivery TINYINT UNSIGNED NOT NULL COMMENT '13=backhand, 14=forehand',
    end_length TINYINT UNSIGNED NOT NULL COMMENT '9=long, 10=middle, 11=short',
    result TINYINT UNSIGNED NOT NULL COMMENT '1-8,12: bowl position',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT chk_delivery CHECK (delivery IN (13, 14)),
    CONSTRAINT chk_end_length CHECK (end_length IN (9, 10, 11)),
    CONSTRAINT chk_result CHECK (result IN (1, 2, 3, 4, 5, 6, 7, 8, 12))
) ENGINE=InnoDB;

-- Indexes
CREATE INDEX idx_rolls_session ON rolls(session_id);
CREATE INDEX idx_sessions_date ON sessions(session_date);
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
-- Challenge System Migration
-- Run this migration after add_clubs.sql

-- Challenge templates (pre-defined by admin)
CREATE TABLE challenges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'intermediate',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sequences within a challenge
CREATE TABLE challenge_sequences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    sequence_order TINYINT UNSIGNED NOT NULL,
    end_length TINYINT UNSIGNED NOT NULL,  -- 9=long, 10=middle, 11=short
    delivery TINYINT UNSIGNED NOT NULL,     -- 13=backhand, 14=forehand
    bowl_count TINYINT UNSIGNED NOT NULL,   -- typically 2-4
    description VARCHAR(100),
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    INDEX idx_challenge_order (challenge_id, sequence_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Player attempts at challenges
CREATE TABLE challenge_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED,  -- link to regular session for roll storage
    total_score INT DEFAULT 0,
    max_possible_score INT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    INDEX idx_player_challenge (player_id, challenge_id),
    INDEX idx_challenge_completed (challenge_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add delivery column to rolls table (skip if already exists)
-- ALTER TABLE rolls ADD COLUMN delivery TINYINT UNSIGNED NULL AFTER end_length;
-- Note: Run this only if the column doesn't exist. Check with:
-- SHOW COLUMNS FROM rolls LIKE 'delivery';

-- Update result constraint to allow miss values (20-23)
-- These represent bowls more than 2 mat lengths from jack
ALTER TABLE rolls DROP CONSTRAINT chk_result;
ALTER TABLE rolls ADD CONSTRAINT chk_result CHECK (result IN (1, 2, 3, 4, 5, 6, 7, 8, 12, 20, 21, 22, 23));

-- ============================================
-- Sample Challenge: "Full Routine"
-- A comprehensive practice covering all combinations
-- ============================================

INSERT INTO challenges (name, description, difficulty) VALUES
('Full Routine', 'Complete practice routine covering all end lengths and deliveries. 24 bowls total - perfect for a focused training session.', 'intermediate');

SET @challenge_id = LAST_INSERT_ID();

-- Sequence 1-6: 4 bowls each, all combinations
INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 9, 14, 4, 'Long End - Forehand'),
(@challenge_id, 2, 9, 13, 4, 'Long End - Backhand'),
(@challenge_id, 3, 10, 14, 4, 'Middle End - Forehand'),
(@challenge_id, 4, 10, 13, 4, 'Middle End - Backhand'),
(@challenge_id, 5, 11, 14, 4, 'Short End - Forehand'),
(@challenge_id, 6, 11, 13, 4, 'Short End - Backhand');

-- ============================================
-- Sample Challenge: "Quick Draw"
-- Short challenge for warm-up
-- ============================================

INSERT INTO challenges (name, description, difficulty) VALUES
('Quick Draw', 'Quick warm-up challenge focusing on drawing to the jack. 12 bowls - great for a fast practice.', 'beginner');

SET @challenge_id = LAST_INSERT_ID();

INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 10, 14, 4, 'Middle End - Forehand'),
(@challenge_id, 2, 10, 13, 4, 'Middle End - Backhand'),
(@challenge_id, 3, 11, 14, 2, 'Short End - Forehand'),
(@challenge_id, 4, 11, 13, 2, 'Short End - Backhand');

-- ============================================
-- Sample Challenge: "Long Game Master"
-- Advanced challenge focusing on long ends
-- ============================================

INSERT INTO challenges (name, description, difficulty) VALUES
('Long Game Master', 'Master the long end with this intensive challenge. Requires precision and weight control. 16 bowls.', 'advanced');

SET @challenge_id = LAST_INSERT_ID();

INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 9, 14, 4, 'Long End - Forehand'),
(@challenge_id, 2, 9, 13, 4, 'Long End - Backhand'),
(@challenge_id, 3, 9, 14, 4, 'Long End - Forehand'),
(@challenge_id, 4, 9, 13, 4, 'Long End - Backhand');

-- ============================================
-- Sample Challenge: "Switch Hands"
-- Alternating deliveries for versatility
-- ============================================

INSERT INTO challenges (name, description, difficulty) VALUES
('Switch Hands', 'Alternate between forehand and backhand on each bowl to build versatility. 18 bowls across all lengths.', 'intermediate');

SET @challenge_id = LAST_INSERT_ID();

INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 9, 14, 3, 'Long End - Forehand'),
(@challenge_id, 2, 9, 13, 3, 'Long End - Backhand'),
(@challenge_id, 3, 10, 14, 3, 'Middle End - Forehand'),
(@challenge_id, 4, 10, 13, 3, 'Middle End - Backhand'),
(@challenge_id, 5, 11, 14, 3, 'Short End - Forehand'),
(@challenge_id, 6, 11, 13, 3, 'Short End - Backhand');

-- ============================================
-- Sample Challenge: "Weight Challenge"
-- Four jacks at different distances, one bowl each, repeat 12 ends
-- Tests weight control across the full range
-- ============================================

INSERT INTO challenges (name, description, difficulty) VALUES
('Weight Challenge', 'Master your weight control! Bowl to four jacks at different distances - Long, 3/4, 2/4, and Short. One bowl to each, repeated 12 times. 48 bowls total.', 'advanced');

SET @challenge_id = LAST_INSERT_ID();

INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 9, 14, 12, 'Long Jack - Forehand'),
(@challenge_id, 2, 9, 13, 12, '3/4 Jack - Backhand'),
(@challenge_id, 3, 10, 14, 12, '2/4 Jack - Forehand'),
(@challenge_id, 4, 11, 13, 12, 'Short Jack - Backhand');
-- Live Match Scoring System
-- Run this migration after add_challenges.sql

-- Add is_paid column to players if not exists
ALTER TABLE players ADD COLUMN is_paid TINYINT(1) DEFAULT 0 AFTER email_verified;

-- Live matches
CREATE TABLE IF NOT EXISTS matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id INT UNSIGNED NOT NULL,
    game_type ENUM('singles', 'pairs', 'trips', 'fours') NOT NULL,
    bowls_per_player TINYINT UNSIGNED NOT NULL DEFAULT 4,
    scoring_mode ENUM('ends', 'first_to') DEFAULT 'ends' COMMENT 'ends=play X ends, first_to=first to X points',
    target_score TINYINT UNSIGNED NOT NULL DEFAULT 21 COMMENT 'Number of ends OR points target',
    status ENUM('setup', 'live', 'completed') DEFAULT 'setup',
    created_by INT UNSIGNED NOT NULL,
    scorer_id INT UNSIGNED NULL COMMENT 'Player who claimed scorer role',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES players(id),
    FOREIGN KEY (scorer_id) REFERENCES players(id) ON DELETE SET NULL,
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
