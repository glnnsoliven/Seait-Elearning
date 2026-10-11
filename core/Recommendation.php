<?php
/**
 * SEAIT E-Learning — Personalized Recommendation Engine
 */

class Recommendation
{
    /**
     * Generate (or refresh) recommendations for a student.
     * Clears old undismissed ones then inserts fresh ones.
     */
    public static function generate(int $studentId): void
    {
        // Clear old recommendations
        Database::query(
            "DELETE FROM recommendations WHERE student_id = ? AND is_dismissed = 0",
            [$studentId]
        );

        $student = Database::fetchOne("SELECT * FROM students WHERE id = ?", [$studentId]);
        if (!$student) return;

        $enrollments = Database::fetchAll(
            "SELECT e.course_id, c.title AS course_title
             FROM enrollments e JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ? AND e.status = 'active'",
            [$studentId]
        );

        foreach ($enrollments as $enr) {
            self::recommendForCourse($studentId, (int) $enr['course_id']);
        }
    }

    private static function recommendForCourse(int $studentId, int $courseId): void
    {
        // 1. Weak topics → recommend the lesson containing that topic
        $weakTopics = AdaptiveEngine::identifyWeakTopics($studentId, $courseId);
        foreach (array_slice($weakTopics, 0, 3) as $topic) {
            $lesson = Database::fetchOne(
                "SELECT l.id, l.title FROM lessons l
                 JOIN topics t ON t.lesson_id = l.id
                 WHERE t.id = ? LIMIT 1",
                [$topic['id']]
            );
            if ($lesson) {
                self::insert($studentId, 'lesson', $lesson['id'],
                    'Review: ' . $lesson['title'],
                    'You scored ' . $topic['accuracy'] . '% on "' . $topic['title'] . '". Review this lesson to strengthen your understanding.',
                    9
                );
            }
        }

        // 2. Incomplete lessons → recommend the next incomplete lesson
        $incomplete = Database::fetchOne(
            "SELECT l.id, l.title
             FROM lessons l
             LEFT JOIN student_progress sp ON sp.lesson_id = l.id AND sp.student_id = ?
             WHERE l.course_id = ? AND l.is_published = 1
               AND (sp.status IS NULL OR sp.status != 'completed')
             ORDER BY l.sort_order ASC
             LIMIT 1",
            [$studentId, $courseId]
        );
        if ($incomplete) {
            self::insert($studentId, 'lesson', $incomplete['id'],
                'Continue: ' . $incomplete['title'],
                'Pick up where you left off in this lesson.',
                7
            );
        }

        // 3. Available quizzes not yet attempted
        $quiz = Database::fetchOne(
            "SELECT q.id, q.title
             FROM quizzes q
             LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
             WHERE q.course_id = ? AND q.is_published = 1
               AND qa.id IS NULL
             ORDER BY q.created_at ASC
             LIMIT 1",
            [$studentId, $courseId]
        );
        if ($quiz) {
            self::insert($studentId, 'quiz', $quiz['id'],
                'Practice Quiz: ' . $quiz['title'],
                'You have not attempted this quiz yet. Test your knowledge!',
                8
            );
        }
    }

    private static function insert(int $studentId, string $type, int $refId, string $title, string $reason, int $priority): void
    {
        try {
            Database::insert('recommendations', [
                'student_id'   => $studentId,
                'type'         => $type,
                'reference_id' => $refId,
                'title'        => $title,
                'reason'       => $reason,
                'priority'     => $priority,
                'is_dismissed' => 0,
            ]);
        } catch (Throwable) {}
    }

    /**
     * Return current active recommendations for a student.
     */
    public static function get(int $studentId, int $limit = 6): array
    {
        return Database::fetchAll(
            "SELECT * FROM recommendations
             WHERE student_id = ? AND is_dismissed = 0
             ORDER BY priority DESC, created_at DESC
             LIMIT ?",
            [$studentId, $limit]
        );
    }

    /**
     * Dismiss a recommendation.
     */
    public static function dismiss(int $id, int $studentId): void
    {
        Database::update('recommendations', ['is_dismissed' => 1], 'id = ? AND student_id = ?', [$id, $studentId]);
    }
}
