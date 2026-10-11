<?php
/**
 * Quiz Model
 */
class Quiz extends BaseModel
{
    protected static string $table      = 'quizzes';
    protected static string $primaryKey = 'id';

    public static function findPublished(int $courseId): array
    {
        return Database::fetchAll(
            "SELECT q.*, COUNT(qq.id) question_count
             FROM quizzes q
             LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id AND qq.is_active=1
             WHERE q.course_id=? AND q.is_published=1
             GROUP BY q.id ORDER BY q.deadline ASC, q.created_at DESC",
            [$courseId]
        );
    }

    public static function getAttemptStats(int $quizId, int $studentId): array
    {
        $count = (int) Database::fetchOne(
            "SELECT COUNT(*) c FROM quiz_attempts WHERE quiz_id=? AND student_id=?",
            [$quizId, $studentId])['c'];

        $best = Database::fetchOne(
            "SELECT MAX(percentage) best FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='completed'",
            [$quizId, $studentId]);

        return ['attempt_count' => $count, 'best_score' => (float)($best['best'] ?? 0)];
    }

    public static function publish(int $quizId): void
    {
        Database::update('quizzes', ['is_published' => 1], 'id=?', [$quizId]);
    }

    public static function unpublish(int $quizId): void
    {
        Database::update('quizzes', ['is_published' => 0], 'id=?', [$quizId]);
    }
}
