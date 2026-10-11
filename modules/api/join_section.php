<?php
/**
 * SEAIT E-Learning — Join Subject Section by Code
 *
 * POST { join_code, _csrf_token }
 * 1. Validate code → look up course_sections row
 * 2. Check section is active and course is published
 * 3. Enroll student in the course (or reactivate) AND set section_id
 * 4. Redirect to student courses page with a success flash
 */
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/student/courses.php');
}

Auth::verifyCsrf();

$student = getStudentProfile(Auth::id());
$sid     = (int) $student['id'];

// ── 1. Sanitise & validate the code ──────────────────────────
$rawCode  = strtoupper(trim($_POST['join_code'] ?? ''));
$joinCode = preg_replace('/[^A-Z0-9]/', '', $rawCode);

if (strlen($joinCode) !== 6) {
    flashSet('error', 'Invalid code. Codes are exactly 6 characters.');
    redirect(BASE_URL . '/modules/student/courses.php');
}

// ── 2. Look up the section ────────────────────────────────────
$section = Database::fetchOne(
    "SELECT cs.*, c.title course_title, c.code course_code,
            c.is_published, c.is_active course_active,
            CONCAT(u.first_name,' ',u.last_name) teacher_name
     FROM course_sections cs
     JOIN courses c  ON c.id  = cs.course_id
     JOIN teachers t ON t.id  = c.teacher_id
     JOIN users u    ON u.id  = t.user_id
     WHERE cs.join_code = ?
     LIMIT 1",
    [$joinCode]
);

if (!$section) {
    flashSet('error', 'No section found with that code. Please check and try again.');
    redirect(BASE_URL . '/modules/student/courses.php');
}

if (!$section['is_active']) {
    flashSet('error', 'That section is no longer active. Ask your teacher for a new code.');
    redirect(BASE_URL . '/modules/student/courses.php');
}

if (!$section['course_active']) {
    flashSet('error', 'That subject is currently unavailable.');
    redirect(BASE_URL . '/modules/student/courses.php');
}

if (!$section['is_published']) {
    flashSet('error', 'That subject has not been published yet. Ask your teacher to publish it first.');
    redirect(BASE_URL . '/modules/student/courses.php');
}

$courseId  = (int) $section['course_id'];
$sectionId = (int) $section['id'];

// ── 3. Check existing enrollment ─────────────────────────────
$existing = Database::fetchOne(
    "SELECT id, status, section_id FROM enrollments WHERE student_id = ? AND course_id = ?",
    [$sid, $courseId]
);

if ($existing) {
    if ($existing['status'] === 'active' && (int)$existing['section_id'] === $sectionId) {
        // Already in this exact section
        flashSet('error', 'You are already enrolled in this section.');
        redirect(BASE_URL . '/modules/student/courses.php');
    }

    // Update: reactivate and/or move to the correct section
    Database::update(
        'enrollments',
        ['status' => 'active', 'section_id' => $sectionId, 'enrolled_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$existing['id']]
    );
    AuditLog::write(
        Auth::id(), 'rejoin_section', 'enrollments',
        "Student rejoined section [{$joinCode}] ({$section['name']}) in course {$courseId}"
    );
    flashSet('success', "Welcome back! You've joined section {$section['name']} for {$section['course_title']}.");
} else {
    // Fresh enrollment
    Database::insert('enrollments', [
        'student_id'  => $sid,
        'course_id'   => $courseId,
        'section_id'  => $sectionId,
        'enrolled_at' => date('Y-m-d H:i:s'),
        'status'      => 'active',
    ]);
    AuditLog::write(
        Auth::id(), 'join_section', 'enrollments',
        "Student joined section [{$joinCode}] ({$section['name']}) in course {$courseId}"
    );
    flashSet('success', "You've joined {$section['course_title']} — Section: {$section['name']}. Welcome!");
}

// ── 4. Send to their subject page ─────────────────────────────
redirect(BASE_URL . '/modules/student/courses.php');
