<?php
/**
 * SEAIT E-Learning — Teacher Controller
 *
 * Handles teacher-specific business logic.
 * Module pages in modules/teacher/ include this as needed.
 */
class TeacherController extends BaseController
{
    private array  $teacher;
    private int    $tid;

    public function __construct()
    {
        $this->requireRole('teacher');
        $this->teacher = getTeacherProfile(Auth::id());
        if (!$this->teacher) {
            flashSet('error', 'Teacher profile not found.');
            $this->redirect(BASE_URL . '/modules/auth/logout.php');
        }
        $this->tid = (int) $this->teacher['id'];
    }

    public function getTeacher(): array { return $this->teacher; }
    public function getTid(): int       { return $this->tid; }

    /**
     * Fetch all active courses owned by this teacher.
     */
    public function getCourses(): array
    {
        return Database::fetchAll(
            "SELECT id, title, code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title",
            [$this->tid]
        );
    }

    /**
     * Fetch quizzes for a course, with aggregate stats.
     */
    public function getQuizzesForCourse(int $courseId): array
    {
        return Database::fetchAll(
            "SELECT q.*, COUNT(DISTINCT qq.id) question_count, COUNT(DISTINCT qa.id) attempt_count,
                    ROUND(AVG(qa.percentage),1) avg_score
             FROM quizzes q
             LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id
             LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
             WHERE q.teacher_id=? AND q.course_id=?
             GROUP BY q.id ORDER BY q.created_at DESC",
            [$this->tid, $courseId]
        );
    }

    /**
     * Get at-risk students for all teacher's courses.
     */
    public function getAtRiskStudents(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT ar.*, CONCAT(u.first_name,' ',u.last_name) student_name, c.title course_title
             FROM at_risk_students ar
             JOIN students s ON s.id=ar.student_id
             JOIN users u ON u.id=s.user_id
             JOIN courses c ON c.id=ar.course_id
             WHERE c.teacher_id=? AND ar.is_resolved=0
             ORDER BY ar.risk_score DESC LIMIT ?",
            [$this->tid, $limit]
        );
    }
}
