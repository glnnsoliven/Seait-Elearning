<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$attemptId = getInt('attempt_id');
if (!$attemptId) redirect(BASE_URL.'/modules/student/quiz_list.php');

$attempt = Database::fetchOne(
    "SELECT qa.*, q.title quiz_title, q.passing_score, q.course_id, q.id quiz_id,
            q.time_limit, q.deadline, c.title course_title
     FROM quiz_attempts qa
     JOIN quizzes q ON q.id=qa.quiz_id
     JOIN courses c ON c.id=q.course_id
     WHERE qa.id=? AND qa.student_id=?",[$attemptId,$sid]
);
if (!$attempt || $attempt['status'] !== 'completed') {
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

// Block result view until deadline has passed
if ($attempt['deadline'] && strtotime($attempt['deadline']) > time()) {
    flashSet('error', 'Results are not available yet. They will be released after the deadline: ' . formatDate($attempt['deadline'], 'M d, Y g:i A') . '.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

$pageTitle = 'Quiz Result';
$passed    = $attempt['percentage'] >= $attempt['passing_score'];

// Topic performance breakdown
$topicPerf = Database::fetchAll(
    "SELECT t.title topic_name,
            COUNT(sa.id) total,
            SUM(sa.is_correct) correct,
            ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE sa.attempt_id=?
     GROUP BY t.id ORDER BY accuracy ASC",[$attemptId]
);

// Difficulty performance
$diffPerf = Database::fetchAll(
    "SELECT qq.difficulty,
            COUNT(sa.id) total,
            SUM(sa.is_correct) correct,
            ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     WHERE sa.attempt_id=?
     GROUP BY qq.difficulty",[$attemptId]
);

// Diagnostic feedback (wrong answers)
$feedback = Database::fetchAll(
    "SELECT df.*, qq.question_text, qq.question_type, qq.choices
     FROM diagnostic_feedback df
     JOIN quiz_questions qq ON qq.id=df.question_id
     WHERE df.student_id=? AND df.student_answer_id IN (
         SELECT id FROM student_answers WHERE attempt_id=?
     )
     ORDER BY df.id",[$sid,$attemptId]
);

// Recommendations
$recs = Recommendation::get($sid, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<!-- Result Hero Card -->
<div class="bg-gradient-to-br <?= $passed ? 'from-green-500 to-emerald-600' : 'from-red-500 to-rose-600' ?> rounded-2xl p-6 mb-6 text-white">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="text-4xl mb-2"><?= $passed ? '🎉' : '😓' ?></div>
            <h2 class="text-2xl font-bold"><?= $passed ? 'Congratulations!' : 'Keep Practicing!' ?></h2>
            <p class="text-white/80 mt-1"><?= e($attempt['quiz_title']) ?></p>
            <p class="text-white/60 text-sm"><?= e($attempt['course_title']) ?></p>
        </div>
        <!-- Circular Score -->
        <div class="text-center flex-shrink-0">
            <div class="relative w-28 h-28 mx-auto">
                <svg class="w-28 h-28 -rotate-90" viewBox="0 0 112 112">
                    <circle cx="56" cy="56" r="44" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="8"/>
                    <circle cx="56" cy="56" r="44" fill="none" stroke="white" stroke-width="8"
                            stroke-dasharray="<?= round(2*pi()*44) ?>"
                            stroke-dashoffset="<?= round(2*pi()*44*(1-$attempt['percentage']/100)) ?>"
                            stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-black"><?= $attempt['percentage'] ?>%</span>
                </div>
            </div>
            <p class="text-white/80 text-sm mt-2"><?= $passed ? 'PASSED' : 'FAILED' ?></p>
        </div>
    </div>
</div>

<!-- Score Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Score',     $attempt['percentage'].'%',                         'text-orange-600'],
        ['Correct',   $attempt['correct_count'].'/'.$attempt['total_questions'], 'text-green-600'],
        ['Incorrect', ($attempt['total_questions']-$attempt['correct_count']).'/'.$attempt['total_questions'], 'text-red-500'],
        ['Time Spent',$attempt['time_spent'] ? gmdate('i:s',$attempt['time_spent']) : 'N/A',                  'text-blue-600'],
    ] as [$label,$val,$color]): ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
        <p class="text-2xl font-bold <?= $color ?>"><?= e($val) ?></p>
        <p class="text-xs text-gray-500 mt-0.5"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Topic Performance -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="font-semibold text-gray-800 mb-4">Performance by Topic</h3>
        <?php if (!empty($topicPerf)): ?>
        <div class="space-y-3">
            <?php foreach ($topicPerf as $t): ?>
            <div>
                <div class="flex items-center justify-between mb-1 text-sm">
                    <span class="text-gray-700"><?= e($t['topic_name'] ?? 'General') ?></span>
                    <span class="font-semibold <?= masteryColor((int)$t['accuracy']) ?>"><?= $t['accuracy'] ?>%</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full <?= masteryBarColor((int)$t['accuracy']) ?> rounded-full" style="width:<?= $t['accuracy'] ?>%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-0.5"><?= $t['correct'] ?>/<?= $t['total'] ?> correct</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-400 text-sm text-center py-4">No topic data.</p>
        <?php endif; ?>
    </div>

    <!-- Difficulty Chart -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="font-semibold text-gray-800 mb-4">Performance by Difficulty</h3>
        <canvas id="diffChart" height="160"></canvas>
    </div>
</div>

<!-- Diagnostic Feedback -->
<?php if (!empty($feedback)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">❌ Incorrect Answers & Feedback</h3>
    </div>
    <div class="divide-y divide-gray-50">
        <?php foreach ($feedback as $f): ?>
        <div class="p-5">
            <p class="text-sm font-medium text-gray-800 mb-2"><?= e($f['question_text']) ?></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs mb-3">
                <div class="bg-red-50 rounded-xl px-3 py-2">
                    <p class="text-red-600 font-semibold mb-0.5">Your Answer</p>
                    <p class="text-red-700"><?= e($f['student_answer'] ?? 'No answer') ?></p>
                </div>
                <div class="bg-green-50 rounded-xl px-3 py-2">
                    <p class="text-green-600 font-semibold mb-0.5">Correct Answer</p>
                    <p class="text-green-700"><?= e($f['correct_answer']) ?></p>
                </div>
            </div>
            <?php if ($f['explanation']): ?>
            <div class="bg-blue-50 rounded-xl px-3 py-2 mb-2 text-xs">
                <p class="text-blue-700 font-semibold mb-0.5">💡 Explanation</p>
                <p class="text-blue-600"><?= e($f['explanation']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($f['misconception']): ?>
            <div class="bg-amber-50 rounded-xl px-3 py-2 text-xs">
                <p class="text-amber-700 font-semibold mb-0.5">⚠ Possible Misconception</p>
                <p class="text-amber-600"><?= e($f['misconception']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recommendations -->
<?php if (!empty($recs)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">📚 Recommended for You</h3>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
        <?php foreach ($recs as $r): ?>
        <div class="bg-orange-50 rounded-xl p-4">
            <span class="text-2xl"><?= $r['type']==='quiz'?'✏️':'📖' ?></span>
            <p class="text-sm font-medium text-gray-800 mt-2"><?= e($r['title']) ?></p>
            <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= e($r['reason']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="flex flex-col sm:flex-row gap-3">
    <a href="<?= BASE_URL ?>/modules/student/review_answers.php?attempt_id=<?= $attemptId ?>"
       class="flex-1 py-3 bg-white border-2 border-gray-200 text-gray-700 font-medium text-sm rounded-xl hover:bg-gray-50 transition text-center">
        🔍 Review All Answers
    </a>
    <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $attempt['quiz_id'] ?>"
       class="flex-1 py-3 bg-blue-600 text-white font-medium text-sm rounded-xl hover:bg-blue-700 transition text-center">
        🔄 Retake Quiz
    </a>
    <a href="<?= BASE_URL ?>/modules/student/course_view.php?id=<?= $attempt['course_id'] ?>"
       class="flex-1 py-3 bg-orange-500 text-white font-medium text-sm rounded-xl hover:bg-orange-700 transition text-center">
        📚 Back to Course
    </a>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
new Chart(document.getElementById('diffChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d)=>ucfirst($d['difficulty']),$diffPerf)) ?>,
        datasets: [{
            label: 'Accuracy %',
            data: <?= json_encode(array_column($diffPerf,'accuracy')) ?>,
            backgroundColor: <?= json_encode(array_map(fn($d)=>match($d['difficulty']){'easy'=>'#22c55e','medium'=>'#eab308','hard'=>'#ef4444',default=>'#e67e22'},$diffPerf)) ?>,
            borderRadius: 8,
        }]
    },
    options: { responsive: true, plugins:{legend:{display:false}}, scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'}}} }
});
</script>
</body>
</html>
