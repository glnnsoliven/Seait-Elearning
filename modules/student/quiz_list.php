<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student  = getStudentProfile(Auth::id());
$sid      = (int)$student['id'];
$pageTitle= 'My Quizzes';

$courseId = getInt('course_id');
$where    = "WHERE (q.is_published=1 OR (q.scheduled_at IS NOT NULL AND q.scheduled_at <= NOW())) AND e.student_id=? AND e.status='active'";
$params   = [$sid];
if ($courseId) { $where .= " AND q.course_id=?"; $params[] = $courseId; }

$quizzes = Database::fetchAll(
    "SELECT q.*, c.title course_title, c.code course_code, l.title lesson_title,
            COUNT(DISTINCT qq.id) question_count,
            COALESCE(best.best_score,NULL) best_score,
            COALESCE(att.attempt_count,0) attempt_count,
            in_prog.id in_progress_id
     FROM quizzes q
     JOIN courses c ON c.id=q.course_id
     JOIN enrollments e ON e.course_id=c.id
     LEFT JOIN lessons l ON l.id=q.lesson_id
     LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id
     LEFT JOIN (
         SELECT quiz_id, MAX(percentage) best_score
         FROM quiz_attempts WHERE student_id=? AND status='completed' GROUP BY quiz_id
     ) best ON best.quiz_id=q.id
     LEFT JOIN (
         SELECT quiz_id, COUNT(*) attempt_count FROM quiz_attempts WHERE student_id=? GROUP BY quiz_id
     ) att ON att.quiz_id=q.id
     LEFT JOIN (
         SELECT id, quiz_id FROM quiz_attempts WHERE student_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1
     ) in_prog ON in_prog.quiz_id=q.id
     {$where}
     GROUP BY q.id ORDER BY q.deadline ASC, q.created_at DESC",
    array_merge([$sid,$sid,$sid], $params)
);

$courses = Database::fetchAll(
    "SELECT DISTINCT c.id,c.title,c.code FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.student_id=? AND e.status='active' ORDER BY c.title",[$sid]
);

$now = time();
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">My Quizzes</h2>
    <p class="text-sm text-gray-500"><?= count($quizzes) ?> quizzes available</p>
</div>

<!-- Course Filter Tabs -->
<div class="flex gap-2 overflow-x-auto pb-1 mb-5">
    <a href="<?= BASE_URL ?>/modules/student/quiz_list.php"
       class="px-4 py-2 rounded-xl text-sm whitespace-nowrap flex-shrink-0 <?= !$courseId?'bg-blue-600 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        All
    </a>
    <?php foreach ($courses as $c): ?>
    <a href="?course_id=<?= $c['id'] ?>"
       class="px-4 py-2 rounded-xl text-sm whitespace-nowrap flex-shrink-0 <?= $courseId==$c['id']?'bg-blue-600 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= e($c['code']) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="space-y-3">
<?php foreach ($quizzes as $q):
    $taken     = $q['best_score'] !== null;
    $expired   = $q['deadline'] && strtotime($q['deadline']) < $now;
    $maxed     = (int)$q['attempt_count'] >= (int)$q['max_attempts'];
    $inProg    = $q['in_progress_id'];
    $canTake   = !$expired && !$maxed;
    $daysLeft  = $q['deadline'] ? (int)ceil((strtotime($q['deadline'])-$now)/86400) : null;
?>
<div class="bg-white rounded-2xl shadow-sm border <?= $inProg?'border-amber-200':($taken?'border-green-100':'border-gray-100') ?> p-5">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-gray-800"><?= e($q['title']) ?></h3>
                <?php if ($taken): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Submitted</span>
                <?php elseif ($inProg && !$taken): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">In Progress</span>
                <?php endif; ?>
                <?php if ($q['is_adaptive']): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700">Adaptive</span>
                <?php endif; ?>
                <?php if ($expired): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-600">Expired</span>
                <?php endif; ?>
            </div>

            <p class="text-xs text-gray-500 mb-1">
                <?= e($q['course_code']) ?>
                <?php if ($q['lesson_title']): ?> · <?= e($q['lesson_title']) ?><?php endif; ?>
                · <?= $q['question_count'] ?> questions
                <?php if ($q['time_limit']): ?> · <?= $q['time_limit'] ?> min<?php endif; ?>
                · <?= $q['attempt_count'] ?>/<?= $q['max_attempts'] ?> attempts
            </p>

            <?php
            // Only show score/pass status after deadline has passed (or no deadline set)
            $resultVisible = !$q['deadline'] || strtotime($q['deadline']) < $now;
            ?>
            <?php if ($taken && $resultVisible): ?>
            <div class="flex items-center gap-3 mt-1">
                <span class="text-sm font-bold <?= masteryColor((int)$q['best_score']) ?>">Best: <?= $q['best_score'] ?>%</span>
                <span class="text-xs <?= $q['best_score']>=$q['passing_score']?'text-green-600':'text-red-500' ?>">
                    <?= $q['best_score']>=$q['passing_score']?'Passed':'Failed' ?>
                </span>
            </div>
            <?php elseif ($taken && !$resultVisible): ?>
            <p class="text-xs text-blue-600 mt-1 flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Result will be available after the deadline
            </p>
            <?php endif; ?>

            <?php if ($q['deadline']): ?>
            <p class="text-xs mt-1 <?= $expired?'text-red-500':($daysLeft<=2?'text-amber-600':'text-gray-400') ?>">
                <?= $expired ? 'Deadline passed' : "Due in {$daysLeft} day".($daysLeft!==1?'s':'') ?> — <?= formatDate($q['deadline'],'M d, Y g:i A') ?>
            </p>
            <?php endif; ?>
            <?php if ($q['scheduled_at'] && !$q['is_published'] && strtotime($q['scheduled_at']) > $now): ?>
            <p class="text-xs mt-1 text-blue-500">
                Starts <?= formatDate($q['scheduled_at'],'M d, Y g:i A') ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            <?php if ($canTake && !$taken && !$inProg): ?>
            <!-- Not yet taken — show Take Quiz -->
            <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $q['id'] ?>"
               class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">
                Take Quiz
            </a>
            <?php elseif ($inProg && !$taken): ?>
            <!-- Started but never completed — allow continuation only this once -->
            <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $q['id'] ?>"
               class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">
                Continue Quiz
            </a>
            <?php endif; ?>

            <?php if ($taken && $resultVisible): ?>
            <a href="<?= BASE_URL ?>/modules/student/quiz_result.php?attempt_id=<?= Database::fetchOne("SELECT id FROM quiz_attempts WHERE student_id=? AND quiz_id=? AND status='completed' ORDER BY id DESC LIMIT 1", [$sid, $q['id']])['id'] ?? 0 ?>"
               class="px-4 py-2 bg-green-50 text-green-700 text-sm rounded-xl hover:bg-green-100 transition">
                View Result
            </a>
            <a href="<?= BASE_URL ?>/modules/student/review_answers.php?attempt_id=<?= Database::fetchOne("SELECT id FROM quiz_attempts WHERE student_id=? AND quiz_id=? AND status='completed' ORDER BY id DESC LIMIT 1", [$sid, $q['id']])['id'] ?? 0 ?>"
               class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-xl hover:bg-gray-200 transition">
                Review
            </a>
            <?php elseif ($taken && !$resultVisible): ?>
            <span class="px-4 py-2 bg-gray-100 text-gray-400 text-sm rounded-xl cursor-not-allowed">
                🔒 Results Pending
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($quizzes)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-4">&#9999;&#65039;</div>
    <p class="text-gray-500">No quizzes available yet.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
