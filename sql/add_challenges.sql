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

-- ============================================
-- Challenge: "Trail & Rest Drill"
-- Weight control drill: touch/trail the jack or rest dead on a target bowl
-- Based on "Why Most Bowlers Struggle with Weight Control"
-- ============================================

INSERT INTO challenges (name, description, difficulty, scoring_type) VALUES
('Trail & Rest Drill', 'Weight control drill. Place the jack at medium length with a target bowl 3m beyond it. For each bowl aim to: TOUCH the jack, TRAIL it gently (within 50cm), or REST DEAD against the target bowl. 8 bowls total — track how many times you achieve each goal.', 'intermediate', 'trail_rest');

SET @challenge_id = LAST_INSERT_ID();

INSERT INTO challenge_sequences (challenge_id, sequence_order, end_length, delivery, bowl_count, description) VALUES
(@challenge_id, 1, 10, 14, 4, 'Middle Length - Forehand'),
(@challenge_id, 2, 10, 13, 4, 'Middle Length - Backhand');
