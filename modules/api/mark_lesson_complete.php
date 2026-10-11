<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL.'/modules/student/courses.php');
}

Auth::verifyCsrf();

$lessonId = postInt('lesson_id');
$courseId = postInt('course_id');
$student  = getStudentProfile(Auth::id());
$sid      = (int)$student['id'];

// Verify lesson & enrollment
$lesson   = Database::fetchOne("SELECT id,course_id FROM lessons WHERE id=? AND course_id=?",[$lessonId,$courseId]);
$enrolled = Database::fetchOne("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$sid,$courseId]);

if ($lesson && $enrolled) {
    $now = date('Y-m-d H:i:s');
    $existing = Database::fetchOne("SELECT id,status FROM student_progress WHERE student_id=? AND course_id=? AND lesson_id=?",[$sid,$courseId,$lessonId]);
    if ($existing) {
        if ($existing['status'] !== 'completed') {
            Database::update('student_progress',['status'=>'completed','completion_pct'=>100,'completed_at'=>$now,'last_accessed'=>$now],'id=?',[$existing['id']]);
        }
    } else {
        Database::insert('student_progress',[
            'student_id'=>$sid,'course_id'=>$courseId,'lesson_id'=>$lessonId,
            'status'=>'completed','completion_pct'=>100,
            'completed_at'=>$now,'last_accessed'=>$now,
        ]);
    }

    // Check if course is fully completed
    $total = Database::fetchOne("SELECT COUNT(*) c FROM lessons WHERE course_id=? AND is_published=1",[$courseId])['c'];
    $done  = Database::fetchOne("SELECT COUNT(*) c FROM student_progress WHERE student_id=? AND course_id=? AND status='completed'",[$sid,$courseId])['c'];
    if ($total > 0 && $done >= $total) {
        Notification::send(Auth::id(),'achievement','Course Progress','You completed all lessons in this course! 🎉',BASE_URL.'/modules/student/course_view.php?id='.$courseId);
    }

    AuditLog::write(Auth::id(),'complete_lesson','progress',"Lesson $lessonId completed");
    flashSet('success','Lesson marked as complete!');
}

redirect(BASE_URL.'/modules/student/lessons.php?id='.$lessonId);
