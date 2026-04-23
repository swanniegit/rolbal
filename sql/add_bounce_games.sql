-- Bounce Game support
-- Run after add_matches.sql

-- Allow club_id NULL (bounce games have no club)
ALTER TABLE matches MODIFY COLUMN club_id INT UNSIGNED NULL;

-- Add 'bounce' game type
ALTER TABLE matches MODIFY COLUMN game_type
  ENUM('singles','pairs','trips','fours','bounce') NOT NULL;

-- Bounce-specific columns on matches
ALTER TABLE matches
  ADD COLUMN match_name       VARCHAR(100)     NULL AFTER game_type,
  ADD COLUMN players_per_team TINYINT UNSIGNED NULL AFTER bowls_per_player,
  ADD COLUMN share_token      VARCHAR(64)      NULL UNIQUE AFTER scorer_id;

-- Allow custom position labels in match_players
ALTER TABLE match_players
  ADD COLUMN position_label VARCHAR(100) NULL AFTER position;

ALTER TABLE match_players
  MODIFY COLUMN position ENUM('skip','third','second','lead','player') NOT NULL DEFAULT 'skip';

-- Index for fast token lookups
ALTER TABLE matches ADD INDEX idx_share_token (share_token);
ALTER TABLE matches ADD INDEX idx_bounce_status (game_type, status);
