<?php
/**
 * SEAIT E-Learning — Admin Controller
 */
class AdminController extends BaseController
{
    public function __construct()
    {
        $this->requireRole('admin');
    }

    /**
     * Get institution-wide overview stats.
     */
    public function getOverviewStats(): array
    {
        return [
            'total_students'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='student' AND is_active=1")['c'],
            'total_teachers'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'],
            'total_courses'   => Database::fetchOne("SELECT COUNT(*) c FROM courses WHERE is_active=1")['c'],
            'total_quizzes'   => Database::fetchOne("SELECT COUNT(*) c FROM quizzes WHERE is_published=1")['c'],
            'total_attempts'  => Database::fetchOne("SELECT COUNT(*) c FROM quiz_attempts")['c'],
            'avg_performance' => Database::fetchOne(
                "SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
            'at_risk_count'   => Database::fetchOne(
                "SELECT COUNT(*) c FROM at_risk_students WHERE is_resolved=0 AND risk_level IN ('medium','high')")['c'],
            'total_departments' => Database::fetchOne(
                "SELECT COUNT(*) c FROM departments WHERE is_active=1")['c'],
        ];
    }

    /**
     * Get department performance rankings.
     */
    public function getDepartmentPerformance(): array
    {
        return Database::fetchAll(
            "SELECT d.name, COUNT(DISTINCT s.id) student_count,
                    ROUND(AVG(qa.percentage),1) avg_score
             FROM departments d
             LEFT JOIN students s ON s.department_id=d.id
             LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
             GROUP BY d.id ORDER BY avg_score DESC LIMIT 10"
        );
    }
}
