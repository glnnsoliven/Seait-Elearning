-- SEAIT E-Learning — Migration 002: Quiz Sections
-- Date: 2026-09-21
-- Description: Add quiz_sections table and section_id to quiz_questions

CREATE TABLE IF NOT EXISTS quiz_sections (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id        INT UNSIGNED NOT NULL,
    name           VARCHAR(100) NOT NULL DEFAULT 'Test 1',
    question_type  ENUM('multiple_choice','true_false','identification','short_answer')
                   NOT NULL DEFAULT 'multiple_choice',
    question_count TINYINT UNSIGNED NOT NULL DEFAULT 10,
    sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz (quiz_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add section_id to quiz_questions if not exists
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'quiz_questions'
      AND COLUMN_NAME  = 'section_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE quiz_questions ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER quiz_id',
    'SELECT "section_id column already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO migrations (version, description) VALUES ('002', 'Quiz sections table');
