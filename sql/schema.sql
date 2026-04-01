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
