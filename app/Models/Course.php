<?php
/**
 * Course Model
 */
class Course extends BaseModel
{
    protected static string $table      = 'courses';
    protected static string $primaryKey = 'id';

    public static function findByTeacher(int $teacherId, bool $activeOnly = true): array
    {
        $sql = "SELECT c.*, d.name dept_name,
                       COUNT(DISTINCT e.student_id) enrolled
                FROM courses c
                LEFT JOIN departments d ON d.id=c.department_id
                LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
                WHERE c.teacher_id=?";
        if ($activeOnly) $sql .= " AND c.is_active=1";
        $sql .= " GROUP BY c.id ORDER BY c.title";
        return Database::fetchAll($sql, [$teacherId]);
    }

    public static function findWithStats(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT c.*, d.name dept_name,
                    COUNT(DISTINCT e.student_id) enrolled,
                    COUNT(DISTINCT l.id) lesson_count,
                    COUNT(DISTINCT q.id) quiz_count,
                    ROUND(AVG(qa.percentage),1) avg_score
             FROM courses c
             LEFT JOIN departments d ON d.id=c.department_id
             LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
             LEFT JOIN lessons l ON l.course_id=c.id
             LEFT JOIN quizzes q ON q.course_id=c.id
             LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
             WHERE c.id=?
             GROUP BY c.id",
            [$id]
        );
    }

    public static function isOwnedByTeacher(int $courseId, int $teacherId): bool
    {
        return (bool) Database::fetchOne(
            "SELECT id FROM courses WHERE id=? AND teacher_id=? AND is_active=1",
            [$courseId, $teacherId]
        );
    }
}
