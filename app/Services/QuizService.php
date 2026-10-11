<?php
/**
 * SEAIT E-Learning — Quiz Service
 *
 * High-level quiz operations used by both teacher and student modules.
 */
class QuizService
{
    /**
     * Create a quiz with its sections in one transaction.
     */
    public static function createWithSections(array $quizData, array $sections): int
    {
        Database::beginTransaction();
        try {
            $quizId = Database::insert('quizzes', $quizData);
            foreach ($sections as $i => $sec) {
                if (empty(trim($sec['name']))) continue;
                Database::insert('quiz_sections', [
                    'quiz_id'        => $quizId,
                    'name'           => trim($sec['name']),
                    'question_type'  => $sec['type']  ?? 'multiple_choice',
                    'question_count' => (int)($sec['count'] ?? 10),
                    'sort_order'     => $i,
                ]);
            }
            Database::commit();
            return $quizId;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Publish a quiz and notify enrolled students.
     */
    public static function publish(int $quizId, int $courseId, string $quizTitle): void
    {
        Database::update('quizzes', ['is_published' => 1], 'id=?', [$quizId]);
        Notification::broadcastToCourse(
            $courseId,
            'new_quiz',
            'New Quiz: ' . $quizTitle,
            'A new quiz is available for you.',
            BASE_URL . '/modules/student/take_quiz.php?quiz_id=' . $quizId
        );
    }

    /**
     * Delete a quiz and all its associated data cleanly.
     */
    public static function delete(int $quizId): void
    {
        Database::beginTransaction();
        try {
            // Questions cascade via FK — delete sections first
            Database::delete('quiz_sections',  'quiz_id=?', [$quizId]);
            Database::delete('quiz_questions', 'quiz_id=?', [$quizId]);
            Database::delete('quiz_attempts',  'quiz_id=?', [$quizId]);
            Database::delete('quizzes',        'id=?',      [$quizId]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Get full quiz attempt results with topic breakdown.
     */
    public static function getAttemptResult(int $attemptId, int $studentId): ?array
    {
        $attempt = Database::fetchOne(
            "SELECT qa.*, q.title quiz_title, q.passing_score, q.course_id,
                    q.id quiz_id, c.title course_title
             FROM quiz_attempts qa
             JOIN quizzes q ON q.id=qa.quiz_id
             JOIN courses c ON c.id=q.course_id
             WHERE qa.id=? AND qa.student_id=? AND qa.status='completed'",
            [$attemptId, $studentId]
        );
        if (!$attempt) return null;

        $attempt['passed']      = $attempt['percentage'] >= $attempt['passing_score'];
        $attempt['topic_perf']  = Database::fetchAll(
            "SELECT t.title topic_name, COUNT(sa.id) total, SUM(sa.is_correct) correct,
                    ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy
             FROM student_answers sa JOIN quiz_questions qq ON qq.id=sa.question_id
             LEFT JOIN topics t ON t.id=qq.topic_id
             WHERE sa.attempt_id=? GROUP BY t.id ORDER BY accuracy ASC",
            [$attemptId]
        );
        $attempt['diff_perf']   = Database::fetchAll(
            "SELECT qq.difficulty, COUNT(sa.id) total, SUM(sa.is_correct) correct,
                    ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy
             FROM student_answers sa JOIN quiz_questions qq ON qq.id=sa.question_id
             WHERE sa.attempt_id=? GROUP BY qq.difficulty",
            [$attemptId]
        );

        return $attempt;
    }
}
