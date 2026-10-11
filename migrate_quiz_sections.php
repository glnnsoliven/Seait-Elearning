<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=seait_elearning;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Create quiz_sections table
$pdo->exec("
CREATE TABLE IF NOT EXISTS quiz_sections (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL DEFAULT 'Test 1',
    question_type   ENUM('multiple_choice','true_false','identification','short_answer') NOT NULL DEFAULT 'multiple_choice',
    question_count  TINYINT UNSIGNED NOT NULL DEFAULT 10,
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz (quiz_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "quiz_sections table created.\n";

// Add section_id to quiz_questions if not exists
$col = $pdo->query("SHOW COLUMNS FROM quiz_questions LIKE 'section_id'")->fetchAll();
if (empty($col)) {
    $pdo->exec("ALTER TABLE quiz_questions ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER quiz_id");
    $pdo->exec("ALTER TABLE quiz_questions ADD CONSTRAINT fk_qq_section FOREIGN KEY (section_id) REFERENCES quiz_sections(id) ON DELETE SET NULL");
    echo "section_id added to quiz_questions.\n";
} else {
    echo "section_id already exists.\n";
}

echo "Done.\n";
