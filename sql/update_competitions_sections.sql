-- Section-Based Competition System Updates
-- Run after add_competitions.sql

-- Add section configuration to competitions
ALTER TABLE competitions
ADD COLUMN rink_count TINYINT UNSIGNED DEFAULT 6 AFTER group_count,
ADD COLUMN qualifiers_per_section TINYINT UNSIGNED DEFAULT 2 AFTER rink_count,
ADD COLUMN teams_per_section TINYINT UNSIGNED DEFAULT 4 AFTER qualifiers_per_section;

-- Add detailed scoring to fixtures (For/Against per team)
ALTER TABLE competition_fixtures
ADD COLUMN rink_number TINYINT UNSIGNED DEFAULT NULL AFTER scheduled_at,
ADD COLUMN participant1_for INT UNSIGNED DEFAULT NULL AFTER score1,
ADD COLUMN participant1_against INT UNSIGNED DEFAULT NULL AFTER participant1_for,
ADD COLUMN participant2_for INT UNSIGNED DEFAULT NULL AFTER score2,
ADD COLUMN participant2_against INT UNSIGNED DEFAULT NULL AFTER participant2_for;

-- Note: participant1_for = participant2_against (inverse relationship)
-- score1/score2 remain for points (2/1/0), new columns track shots
