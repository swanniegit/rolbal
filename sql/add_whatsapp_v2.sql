-- WhatsApp Practice Sessions Support
-- Run this after add_whatsapp.sql

-- Add more states and fields for practice flow
ALTER TABLE whatsapp_sessions
MODIFY COLUMN state ENUM(
    'idle',
    'main_menu',
    'selecting_fixture',
    'entering_score1',
    'entering_score2',
    'practice_hand',
    'practice_length',
    'practice_result',
    'practice_toucher',
    'practice_continue'
) DEFAULT 'idle',
ADD COLUMN session_id INT UNSIGNED DEFAULT NULL,
ADD COLUMN current_hand CHAR(1) DEFAULT NULL,
ADD COLUMN current_end_length TINYINT UNSIGNED DEFAULT NULL,
ADD COLUMN current_end_number TINYINT UNSIGNED DEFAULT 1,
ADD COLUMN bowl_count TINYINT UNSIGNED DEFAULT 0,
ADD FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL;
