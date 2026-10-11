<?php
/**
 * SEAIT E-Learning — Join Quiz by Code
 *
 * Accepts POST { join_code, _csrf_token }
 * 1. Validates the code and looks up the quiz
 * 2. Checks the quiz is published and not past its deadline
 * 3. Auto-enrolls the student in the course if not already enrolled
 * 4. Redirects to take_quiz.php
 */
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

Auth::verifyCsrf();

$student = getStudentProfile(Auth::id());
$sid     = (int) $student['id'];

$rawCode  = strtoupper(trim($_POST['join_code'] ?? ''));
$joinCode = preg_replace('/[^A-Z0-9]/', '', $rawCode);   // sanitize

// ── 1. Validate code ─────────────────────────────────────────
if (strlen($joinCode) !== 6) {
    flashSet('error', 'Invalid join code. Codes are 6 characters long.');
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

$quiz = Database::fetchOne(
    "SELECT q.*, c.title AS course_title
     FROM quizzes q
     JOIN courses c ON c.id = q.course_id
     WHERE q.join_code = ?
     LIMIT 1",
    [$joinCode]
);

if (!$quiz) {
    flashSet('error', 'No quiz found with that code. Please check and try again.');
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

// ── 2. Quiz availability checks ──────────────────────────────
if (!$quiz['is_published']) {
    flashSet('error', 'That quiz is not available yet. Ask your teacher to publish it first.');
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

if ($quiz['deadline'] && strtotime($quiz['deadline']) < time()) {
    flashSet('error', 'That quiz\'s deadline has passed.');
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

// Max attempts check
$attCount = (int) Database::fetchOne(
    "SELECT COUNT(*) c FROM quiz_attempts WHERE student_id = ? AND quiz_id = ?",
    [$sid, $quiz['id']]
)['c'];

if ($attCount >= $quiz['max_attempts']) {
    flashSet('error', 'You have already used all attempts for that quiz.');
    redirect(BASE_URL . '/modules/student/quiz_list.php');
}

// ── 3. Auto-enroll in course if not already enrolled ─────────
$enrolled = Database::fetchOne(
    "SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ?",
    [$sid, $quiz['course_id']]
);

if (!$enrolled) {
    // Enroll the student
    Database::insert('enrollments', [
        'student_id'  => $sid,
        'course_id'   => $quiz['course_id'],
        'enrolled_at' => date('Y-m-d H:i:s'),
        'status'      => 'active',
    ]);
    AuditLog::write(
        Auth::id(),
        'auto_enroll',
        'enrollments',
        "Auto-enrolled via quiz join code [{$joinCode}] into course {$quiz['course_id']}"
    );
    flashSet('success', 'You\'ve been enrolled in "' . $quiz['course_title'] . '" and your quiz is ready!');
} elseif ($enrolled['status'] !== 'active') {
    // Re-activate a dropped enrollment
    Database::update(
        'enrollments',
        ['status' => 'active'],
        'id = ?',
        [$enrolled['id']]
    );
    flashSet('success', 'Your enrollment has been reactivated. Starting quiz now.');
}
// If already active — no message needed, just redirect

// ── 4. Send to quiz ──────────────────────────────────────────
AuditLog::write(Auth::id(), 'join_quiz_by_code', 'quizzes', "Joined quiz {$quiz['id']} via code [{$joinCode}]");
redirect(BASE_URL . '/modules/student/take_quiz.php?quiz_id=' . $quiz['id']);
