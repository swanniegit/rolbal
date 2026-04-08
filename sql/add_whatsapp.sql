-- WhatsApp Score Entry Integration
-- Run this after add_competitions.sql

-- Add WhatsApp number to players table
ALTER TABLE players
ADD COLUMN whatsapp_number VARCHAR(20) DEFAULT NULL,
ADD UNIQUE KEY idx_whatsapp (whatsapp_number);

-- WhatsApp conversation sessions for state management
CREATE TABLE whatsapp_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    player_id INT UNSIGNED DEFAULT NULL,
    state ENUM('idle', 'selecting_fixture', 'entering_score1', 'entering_score2') DEFAULT 'idle',
    fixture_id INT UNSIGNED DEFAULT NULL,
    score1 TINYINT UNSIGNED DEFAULT NULL,
    competition_id INT UNSIGNED DEFAULT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_phone (phone_number),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (fixture_id) REFERENCES competition_fixtures(id) ON DELETE SET NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE SET NULL,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log WhatsApp messages for debugging and audit
CREATE TABLE whatsapp_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_type VARCHAR(50) NOT NULL,
    message_id VARCHAR(100) DEFAULT NULL,
    payload TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_created (phone_number, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
