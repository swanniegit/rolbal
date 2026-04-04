-- BowlsTracker Complete Database Schema
-- Generated from local database, reordered for clean import
--
-- IMPORTANT: Run this on a fresh/empty database

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================
-- 1. PLAYERS (base table, FK added later)
-- ============================================

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hand` enum('L','R') DEFAULT 'R',
  `primary_club_id` int(10) unsigned DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`verification_token`),
  KEY `fk_primary_club` (`primary_club_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. CLUBS
-- ============================================

DROP TABLE IF EXISTS `clubs`;
CREATE TABLE `clubs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(10) unsigned NOT NULL,
  `icon_filename` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_owner` (`owner_id`),
  CONSTRAINT `clubs_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add players FK to clubs (circular dependency)
ALTER TABLE `players` ADD CONSTRAINT `fk_primary_club` FOREIGN KEY (`primary_club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL;

-- ============================================
-- 3. SESSIONS
-- ============================================

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `player_id` int(10) unsigned DEFAULT NULL,
  `hand` enum('L','R') NOT NULL,
  `bowls_per_end` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `total_ends` tinyint(3) unsigned NOT NULL DEFAULT 15,
  `session_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_public` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_date` (`session_date`),
  KEY `idx_sessions_player` (`player_id`),
  CONSTRAINT `fk_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ROLLS
-- ============================================

DROP TABLE IF EXISTS `rolls`;
CREATE TABLE `rolls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `end_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `delivery` tinyint(3) unsigned DEFAULT NULL,
  `end_length` tinyint(3) unsigned NOT NULL,
  `result` tinyint(3) unsigned NOT NULL,
  `toucher` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rolls_session` (`session_id`),
  CONSTRAINT `rolls_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_end_length` CHECK (`end_length` in (9,10,11)),
  CONSTRAINT `chk_result` CHECK (`result` in (1,2,3,4,5,6,7,8,12,20,21,22,23))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CLUB MEMBERS
-- ============================================

DROP TABLE IF EXISTS `club_members`;
CREATE TABLE `club_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `club_id` int(10) unsigned NOT NULL,
  `player_id` int(10) unsigned NOT NULL,
  `role` enum('owner','admin','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_membership` (`club_id`,`player_id`),
  KEY `idx_club` (`club_id`),
  KEY `idx_player` (`player_id`),
  CONSTRAINT `club_members_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `club_members_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. CHALLENGES
-- ============================================

DROP TABLE IF EXISTS `challenges`;
CREATE TABLE `challenges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'intermediate',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CHALLENGE SEQUENCES
-- ============================================

DROP TABLE IF EXISTS `challenge_sequences`;
CREATE TABLE `challenge_sequences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) unsigned NOT NULL,
  `sequence_order` tinyint(3) unsigned NOT NULL,
  `end_length` tinyint(3) unsigned NOT NULL,
  `delivery` tinyint(3) unsigned NOT NULL,
  `bowl_count` tinyint(3) unsigned NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_challenge_order` (`challenge_id`,`sequence_order`),
  CONSTRAINT `challenge_sequences_ibfk_1` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. CHALLENGE ATTEMPTS
-- ============================================

DROP TABLE IF EXISTS `challenge_attempts`;
CREATE TABLE `challenge_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) unsigned NOT NULL,
  `player_id` int(10) unsigned NOT NULL,
  `session_id` int(10) unsigned DEFAULT NULL,
  `total_score` int(11) DEFAULT 0,
  `max_possible_score` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `idx_player_challenge` (`player_id`,`challenge_id`),
  KEY `idx_challenge_completed` (`challenge_id`,`completed_at`),
  CONSTRAINT `challenge_attempts_ibfk_1` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`),
  CONSTRAINT `challenge_attempts_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_attempts_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. MATCHES
-- ============================================

DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `club_id` int(10) unsigned NOT NULL,
  `game_type` enum('singles','pairs','trips','fours') NOT NULL,
  `bowls_per_player` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `scoring_mode` enum('ends','first_to') DEFAULT 'ends',
  `target_score` tinyint(3) unsigned NOT NULL DEFAULT 21,
  `status` enum('setup','live','completed') DEFAULT 'setup',
  `created_by` int(10) unsigned NOT NULL,
  `scorer_id` int(10) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_club_status` (`club_id`,`status`),
  CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. MATCH TEAMS
-- ============================================

DROP TABLE IF EXISTS `match_teams`;
CREATE TABLE `match_teams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(10) unsigned NOT NULL,
  `team_number` tinyint(3) unsigned NOT NULL,
  `team_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match_team` (`match_id`,`team_number`),
  CONSTRAINT `match_teams_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. MATCH PLAYERS
-- ============================================

DROP TABLE IF EXISTS `match_players`;
CREATE TABLE `match_players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int(10) unsigned NOT NULL,
  `position` enum('skip','third','second','lead') NOT NULL,
  `player_name` varchar(100) NOT NULL,
  `player_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `match_players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `match_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. MATCH ENDS
-- ============================================

DROP TABLE IF EXISTS `match_ends`;
CREATE TABLE `match_ends` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(10) unsigned NOT NULL,
  `end_number` tinyint(3) unsigned NOT NULL,
  `scoring_team` tinyint(3) unsigned NOT NULL,
  `shots` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match_end` (`match_id`,`end_number`),
  CONSTRAINT `match_ends_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. COMPETITIONS
-- ============================================

DROP TABLE IF EXISTS `competitions`;
CREATE TABLE `competitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `club_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `format` enum('round_robin','knockout','combined') NOT NULL,
  `game_type` enum('singles','pairs','trips','fours') NOT NULL,
  `bowls_per_player` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `scoring_mode` enum('ends','first_to') DEFAULT 'ends',
  `target_score` tinyint(3) unsigned NOT NULL DEFAULT 21,
  `max_participants` int(10) unsigned DEFAULT NULL,
  `knockout_qualifiers` tinyint(3) unsigned DEFAULT 2,
  `group_count` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('draft','registration','in_progress','completed','cancelled') DEFAULT 'draft',
  `registration_opens` datetime DEFAULT NULL,
  `registration_closes` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_club_status` (`club_id`,`status`),
  CONSTRAINT `competitions_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. COMPETITION PARTICIPANTS
-- ============================================

DROP TABLE IF EXISTS `competition_participants`;
CREATE TABLE `competition_participants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` int(10) unsigned NOT NULL,
  `team_name` varchar(100) DEFAULT NULL,
  `seed` int(10) unsigned DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_competition` (`competition_id`),
  CONSTRAINT `competition_participants_ibfk_1` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15. COMPETITION PARTICIPANT PLAYERS
-- ============================================

DROP TABLE IF EXISTS `competition_participant_players`;
CREATE TABLE `competition_participant_players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int(10) unsigned NOT NULL,
  `player_id` int(10) unsigned NOT NULL,
  `position` enum('skip','third','second','lead') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant_position` (`participant_id`,`position`),
  UNIQUE KEY `unique_participant_player` (`participant_id`,`player_id`),
  KEY `idx_player` (`player_id`),
  CONSTRAINT `competition_participant_players_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `competition_participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competition_participant_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16. COMPETITION GROUPS
-- ============================================

DROP TABLE IF EXISTS `competition_groups`;
CREATE TABLE `competition_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` int(10) unsigned NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `group_number` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_competition_group` (`competition_id`,`group_number`),
  CONSTRAINT `competition_groups_ibfk_1` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 17. COMPETITION GROUP PARTICIPANTS
-- ============================================

DROP TABLE IF EXISTS `competition_group_participants`;
CREATE TABLE `competition_group_participants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `participant_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_participant` (`group_id`,`participant_id`),
  KEY `participant_id` (`participant_id`),
  CONSTRAINT `competition_group_participants_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `competition_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competition_group_participants_ibfk_2` FOREIGN KEY (`participant_id`) REFERENCES `competition_participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 18. COMPETITION FIXTURES
-- ============================================

DROP TABLE IF EXISTS `competition_fixtures`;
CREATE TABLE `competition_fixtures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` int(10) unsigned NOT NULL,
  `stage` enum('group','play_in','round_of_64','round_of_32','round_of_16','quarter_final','semi_final','third_place','final') NOT NULL,
  `round_number` tinyint(3) unsigned DEFAULT 1,
  `bracket_position` int(10) unsigned DEFAULT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `participant1_id` int(10) unsigned DEFAULT NULL,
  `participant2_id` int(10) unsigned DEFAULT NULL,
  `winner_from_fixture_1` int(10) unsigned DEFAULT NULL,
  `winner_from_fixture_2` int(10) unsigned DEFAULT NULL,
  `match_id` int(10) unsigned DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `winner_id` int(10) unsigned DEFAULT NULL,
  `score1` int(10) unsigned DEFAULT NULL,
  `score2` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','scheduled','live','completed','walkover','cancelled') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `participant1_id` (`participant1_id`),
  KEY `participant2_id` (`participant2_id`),
  KEY `winner_from_fixture_1` (`winner_from_fixture_1`),
  KEY `winner_from_fixture_2` (`winner_from_fixture_2`),
  KEY `winner_id` (`winner_id`),
  KEY `idx_competition_stage` (`competition_id`,`stage`),
  KEY `idx_match` (`match_id`),
  CONSTRAINT `competition_fixtures_ibfk_1` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competition_fixtures_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `competition_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_3` FOREIGN KEY (`participant1_id`) REFERENCES `competition_participants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_4` FOREIGN KEY (`participant2_id`) REFERENCES `competition_participants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_5` FOREIGN KEY (`winner_from_fixture_1`) REFERENCES `competition_fixtures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_6` FOREIGN KEY (`winner_from_fixture_2`) REFERENCES `competition_fixtures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_7` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_fixtures_ibfk_8` FOREIGN KEY (`winner_id`) REFERENCES `competition_participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 19. COMPETITION STANDINGS
-- ============================================

DROP TABLE IF EXISTS `competition_standings`;
CREATE TABLE `competition_standings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `competition_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `participant_id` int(10) unsigned NOT NULL,
  `played` int(10) unsigned DEFAULT 0,
  `won` int(10) unsigned DEFAULT 0,
  `lost` int(10) unsigned DEFAULT 0,
  `drawn` int(10) unsigned DEFAULT 0,
  `ends_for` int(10) unsigned DEFAULT 0,
  `ends_against` int(10) unsigned DEFAULT 0,
  `shots_for` int(10) unsigned DEFAULT 0,
  `shots_against` int(10) unsigned DEFAULT 0,
  `points` int(10) unsigned DEFAULT 0,
  `position` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_standings` (`competition_id`,`group_id`,`participant_id`),
  KEY `group_id` (`group_id`),
  KEY `participant_id` (`participant_id`),
  KEY `idx_competition_group` (`competition_id`,`group_id`),
  CONSTRAINT `competition_standings_ibfk_1` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competition_standings_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `competition_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competition_standings_ibfk_3` FOREIGN KEY (`participant_id`) REFERENCES `competition_participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA: Challenges
-- ============================================

INSERT INTO `challenges` (`id`, `name`, `description`, `difficulty`, `is_active`) VALUES
(1, 'Full Routine', 'Complete practice routine covering all end lengths and deliveries. 24 bowls total - perfect for a focused training session.', 'intermediate', 1),
(2, 'Quick Draw', 'Quick warm-up challenge focusing on drawing to the jack. 12 bowls - great for a fast practice.', 'beginner', 1),
(3, 'Long Game Master', 'Master the long end with this intensive challenge. Requires precision and weight control. 16 bowls.', 'advanced', 1),
(4, 'Switch Hands', 'Alternate between forehand and backhand on each bowl to build versatility. 18 bowls across all lengths.', 'intermediate', 1),
(5, 'Weight Challenge', 'Master your weight control! Bowl to four jacks at different distances - Long, 3/4, 2/4, and Short. One bowl to each, repeated 12 times. 48 bowls total.', 'advanced', 1);

INSERT INTO `challenge_sequences` (`id`, `challenge_id`, `sequence_order`, `end_length`, `delivery`, `bowl_count`, `description`) VALUES
(1, 1, 1, 9, 14, 4, 'Long End - Forehand'),
(2, 1, 2, 9, 13, 4, 'Long End - Backhand'),
(3, 1, 3, 10, 14, 4, 'Middle End - Forehand'),
(4, 1, 4, 10, 13, 4, 'Middle End - Backhand'),
(5, 1, 5, 11, 14, 4, 'Short End - Forehand'),
(6, 1, 6, 11, 13, 4, 'Short End - Backhand'),
(7, 2, 1, 10, 14, 4, 'Middle End - Forehand'),
(8, 2, 2, 10, 13, 4, 'Middle End - Backhand'),
(9, 2, 3, 11, 14, 2, 'Short End - Forehand'),
(10, 2, 4, 11, 13, 2, 'Short End - Backhand'),
(11, 3, 1, 9, 14, 4, 'Long End - Forehand'),
(12, 3, 2, 9, 13, 4, 'Long End - Backhand'),
(13, 3, 3, 9, 14, 4, 'Long End - Forehand'),
(14, 3, 4, 9, 13, 4, 'Long End - Backhand'),
(15, 4, 1, 9, 14, 3, 'Long End - Forehand'),
(16, 4, 2, 9, 13, 3, 'Long End - Backhand'),
(17, 4, 3, 10, 14, 3, 'Middle End - Forehand'),
(18, 4, 4, 10, 13, 3, 'Middle End - Backhand'),
(19, 4, 5, 11, 14, 3, 'Short End - Forehand'),
(20, 4, 6, 11, 13, 3, 'Short End - Backhand'),
(21, 5, 1, 9, 14, 12, 'Long Jack - Forehand'),
(22, 5, 2, 9, 13, 12, '3/4 Jack - Backhand'),
(23, 5, 3, 10, 14, 12, '2/4 Jack - Forehand'),
(24, 5, 4, 11, 13, 12, 'Short Jack - Backhand');

SET FOREIGN_KEY_CHECKS = 1;
