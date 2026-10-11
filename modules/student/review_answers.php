<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$attemptId = getInt('attempt_id');
if (!$attemptId) redirect(BASE_URL.'/modules/student/quiz_list.php');

$attempt = Database::fetchOne(
    "SELECT qa.*, q.title quiz_title, q.course_id FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE qa.id=? AND qa.student_id=?",
    [$attemptId,$sid]
);
if (!$attempt) redirect(BASE_URL.'/modules/student/quiz_list.php');

$pageTitle = 'Review Answers';

$answers = Database::fetchAll(
    "SELECT sa.*, qq.question_text, qq.question_type, qq.choices, qq.correct_answer,
            qq.explanation, qq.difficulty, qq.bloom_level, t.title topic_title
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE sa.attempt_id=?
     ORDER BY sa.answered_at",[$attemptId]
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Answer Review</h2>
        <p class="text-sm text-gray-500"><?= e($attempt['quiz_title']) ?> — <?= $attempt['correct_count'] ?>/<?= $attempt['total_questions'] ?> correct</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/student/quiz_result.php?attempt_id=<?= $attemptId ?>"
       class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700">
        ← Results
    </a>
</div>

<div class="space-y-4">
<?php foreach ($answers as $i => $a):
    $choices = $a['choices'] ? json_decode($a['choices'],true) : [];
?>
<div class="bg-white rounded-2xl shadow-sm border <?= $a['is_correct']?'border-green-200':'border-red-200' ?> p-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 mb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-bold text-gray-500">Q<?= $i+1 ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs <?= $a['is_correct']?'bg-green-100 text-green-700':'bg-red-100 text-red-600' ?>">
                <?= $a['is_correct'] ? '✓ Correct' : '✗ Incorrect' ?>
            </span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"><?= ucfirst($a['difficulty']) ?></span>
            <?php if ($a['topic_title']): ?>
            <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-600"><?= e($a['topic_title']) ?></span>
            <?php endif; ?>
        </div>
        <span class="text-xs text-gray-400 flex-shrink-0">+<?= $a['points_earned'] ?> pts</span>
    </div>

    <p class="text-sm font-medium text-gray-800 mb-4"><?= nl2br(e($a['question_text'])) ?></p>

    <!-- Choices with highlighting -->
    <?php if (!empty($choices)): ?>
    <div class="space-y-2 mb-4">
        <?php foreach ($choices as $ch):
            $isCorrect  = $ch['label'] === $a['correct_answer'];
            $isStudents = $ch['label'] === $a['student_answer'];
            $cls = 'border-gray-100 bg-gray-50';
            if ($isCorrect)  $cls = 'border-green-300 bg-green-50';
            if ($isStudents && !$isCorrect) $cls = 'border-red-300 bg-red-50';
        ?>
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $cls ?>">
            <span class="w-7 h-7 rounded-full border-2 flex items-center justify-center text-xs font-bold flex-shrink-0
                <?= $isCorrect?'border-green-500 text-green-700':($isStudents?'border-red-400 text-red-600':'border-gray-300 text-gray-400') ?>">
                <?= e($ch['label']) ?>
            </span>
            <span class="text-sm <?= $isCorrect?'text-green-700 font-medium':($isStudents&&!$isCorrect?'text-red-600 line-through':'text-gray-700') ?>">
                <?= e($ch['text']) ?>
            </span>
            <?php if ($isCorrect): ?><span class="ml-auto text-xs text-green-600 font-semibold">✓ Correct</span><?php endif; ?>
            <?php if ($isStudents && !$isCorrect): ?><span class="ml-auto text-xs text-red-500 font-semibold">Your answer</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Text answer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 text-sm">
        <div class="bg-red-50 rounded-xl px-3 py-2">
            <p class="text-xs text-red-600 font-semibold mb-0.5">Your Answer</p>
            <p class="text-red-700"><?= e($a['student_answer'] ?? '(No answer)') ?></p>
        </div>
        <div class="bg-green-50 rounded-xl px-3 py-2">
            <p class="text-xs text-green-600 font-semibold mb-0.5">Correct Answer</p>
            <p class="text-green-700"><?= e($a['correct_answer']) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Explanation -->
    <?php if ($a['explanation']): ?>
    <div class="bg-blue-50 rounded-xl px-4 py-3 text-sm">
        <p class="text-blue-700 font-semibold text-xs mb-1">💡 Explanation</p>
        <p class="text-blue-600"><?= e($a['explanation']) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
