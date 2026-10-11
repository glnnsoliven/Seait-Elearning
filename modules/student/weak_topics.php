<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$pageTitle = 'Weak Topics';

$weakTopics = Database::fetchAll(
    "SELECT t.id, t.title, l.title lesson_title, c.title course_title, c.id course_id,
            COUNT(sa.id) total_answers,
            SUM(sa.is_correct) correct_answers,
            ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN topics t ON t.id=qq.topic_id
     JOIN lessons l ON l.id=t.lesson_id
     JOIN courses c ON c.id=l.course_id
     JOIN quiz_attempts qa ON qa.id=sa.attempt_id AND qa.student_id=? AND qa.status='completed'
     GROUP BY t.id
     HAVING accuracy < 70
     ORDER BY accuracy ASC",[$sid]
);
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
    <h2 class="text-xl font-bold text-gray-800">Weak Topics</h2>
    <p class="text-sm text-gray-500">Topics where your accuracy is below 70%</p>
</div>

<?php if (empty($weakTopics)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-4">🏆</div>
    <p class="text-gray-500 font-medium">No weak topics found! Keep it up!</p>
</div>
<?php else: ?>
<div class="space-y-3">
<?php foreach ($weakTopics as $t):
    $urgency = $t['accuracy'] < 40 ? 'High' : ($t['accuracy'] < 60 ? 'Medium' : 'Low');
    $urgColor = ['High'=>'bg-red-100 text-red-700','Medium'=>'bg-amber-100 text-amber-700','Low'=>'bg-yellow-100 text-yellow-700'][$urgency];
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-gray-800"><?= e($t['title']) ?></h3>
                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $urgColor ?>"><?= $urgency ?></span>
            </div>
            <p class="text-xs text-gray-500">
                <?= e($t['course_title']) ?> › <?= e($t['lesson_title']) ?>
            </p>
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="text-gray-500">Accuracy: <?= $t['correct_answers'] ?>/<?= $t['total_answers'] ?> correct</span>
                    <span class="font-bold text-red-600"><?= $t['accuracy'] ?>%</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-red-400 rounded-full" style="width:<?= $t['accuracy'] ?>%"></div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <a href="<?= BASE_URL ?>/modules/student/lessons.php?id=<?= $t['id'] ?>"
               class="px-4 py-2 bg-blue-600 text-white text-xs font-medium rounded-xl hover:bg-blue-700 transition">
                Review Lesson
            </a>
            <a href="<?= BASE_URL ?>/modules/student/quiz_list.php?course_id=<?= $t['course_id'] ?>"
               class="px-4 py-2 bg-gray-100 text-gray-600 text-xs rounded-xl hover:bg-gray-200 transition">
                Practice Quiz
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
