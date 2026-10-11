<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

Auth::verifyCsrf();

$attemptId  = postInt('attempt_id');
$questionId = postInt('question_id');
$quizId     = postInt('quiz_id');
$answer     = trim($_POST['answer'] ?? '');
$startTime  = postInt('start_time');
$responseTime = $startTime > 0 ? (time() - $startTime) : 0;

$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];

// Validate attempt belongs to this student
$attempt = Database::fetchOne("SELECT * FROM quiz_attempts WHERE id=? AND student_id=? AND status='in_progress'",[$attemptId,$sid]);
if (!$attempt) {
    flashSet('error','Invalid quiz session.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

if (empty($answer)) {
    flashSet('error','Please select or enter an answer.');
    redirect(BASE_URL.'/modules/student/take_quiz.php?quiz_id='.$quizId);
}

// Evaluate and store answer
AdaptiveEngine::evaluateAnswer($attemptId, $questionId, $answer, $responseTime);

// Continue taking the quiz
redirect(BASE_URL.'/modules/student/take_quiz.php?quiz_id='.$quizId.'&attempt_id='.$attemptId);
