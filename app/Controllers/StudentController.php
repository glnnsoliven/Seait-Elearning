<?php
/**
 * SEAIT E-Learning — Student Controller
 */
class StudentController extends BaseController
{
    private array $student;
    private int   $sid;

    public function __construct()
    {
        $this->requireRole('student');
        $this->student = getStudentProfile(Auth::id());
        if (!$this->student) {
            flashSet('error', 'Student profile not found.');
            $this->redirect(BASE_URL . '/modules/auth/logout.php');
        }
        $this->sid = (int) $this->student['id'];
    }

    public function getStudent(): array { return $this->student; }
    public function getSid(): int       { return $this->sid; }

    /**
     * Get enrolled courses with progress.
     */
    public function getEnrolledCourses(): array
    {
        return Database::fetchAll(
            "SELECT c.id, c.title, c.code,
                    COUNT(DISTINCT l.id) total_lessons,
                    COUNT(DISTINCT CASE WHEN sp.status='completed' THEN sp.lesson_id END) done_lessons
             FROM enrollments e
             JOIN courses c ON c.id=e.course_id
             LEFT JOIN lessons l ON l.course_id=c.id AND l.is_published=1
             LEFT JOIN student_progress sp ON sp.course_id=c.id AND sp.student_id=?
             WHERE e.student_id=? AND e.status='active'
             GROUP BY c.id ORDER BY c.title",
            [$this->sid, $this->sid]
        );
    }

    /**
     * Get dashboard summary stats.
     */
    public function getDashboardStats(): array
    {
        return [
            'enrolled_courses'  => Database::fetchOne(
                "SELECT COUNT(*) c FROM enrollments WHERE student_id=? AND status='active'",
                [$this->sid])['c'],
            'completed_lessons' => Database::fetchOne(
                "SELECT COUNT(*) c FROM student_progress WHERE student_id=? AND status='completed'",
                [$this->sid])['c'],
            'available_quizzes' => Database::fetchOne(
                "SELECT COUNT(*) c FROM quizzes q
                 JOIN enrollments e ON e.course_id=q.course_id AND e.student_id=?
                 LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed'
                 WHERE q.is_published=1 AND qa.id IS NULL",
                [$this->sid, $this->sid])['c'],
            'avg_mastery'       => Database::fetchOne(
                "SELECT ROUND(AVG(mastery_pct),1) v FROM student_competencies WHERE student_id=?",
                [$this->sid])['v'] ?? 0,
        ];
    }
}
