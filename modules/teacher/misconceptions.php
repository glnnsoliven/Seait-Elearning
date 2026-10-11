<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Misconception Analytics';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$courses   = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1",[$tid]);
$courseId  = getInt('course_id');
if (!$courseId && !empty($courses)) $courseId = (int)$courses[0]['id'];

$misconceptions = $courseId ? Database::fetchAll(
    "SELECT qq.id, qq.question_text, qq.question_type, qq.difficulty, t.title topic_title,
            COUNT(sa.id) wrong_count,
            GROUP_CONCAT(DISTINCT sa.student_answer ORDER BY sa.answered_at DESC SEPARATOR ' | ') common_wrong_answers
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN quizzes q ON q.id=qq.quiz_id
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE q.course_id=? AND sa.is_correct=0
     GROUP BY qq.id ORDER BY wrong_count DESC LIMIT 20",[$courseId]) : [];

$topicMisconceptions = $courseId ? Database::fetchAll(
    "SELECT t.title topic, COUNT(sa.id) wrong_count, ROUND(COUNT(sa.id)/COUNT(*)*100,1) wrong_pct
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN quizzes q ON q.id=qq.quiz_id
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE q.course_id=?
     GROUP BY t.id ORDER BY wrong_pct DESC LIMIT 8",[$courseId]) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_teacher.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Misconception Analytics</h2>
        <p class="text-sm text-gray-500">Questions students frequently answer incorrectly</p>
    </div>
    <select onchange="location.href='?course_id='+this.value"
            class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Topic misconception chart -->
<?php if (!empty($topicMisconceptions)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4">Error Rate by Topic</h3>
    <div class="space-y-3">
        <?php foreach ($topicMisconceptions as $tm): ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm text-gray-700"><?= e($tm['topic'] ?? 'General') ?></p>
                <span class="text-sm font-bold text-red-600"><?= $tm['wrong_pct'] ?>% errors</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-red-400 rounded-full" style="width: <?= min(100,$tm['wrong_pct']) ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Question-level list -->
<div class="space-y-3">
<?php foreach ($misconceptions as $m): ?>
<div class="bg-white rounded-2xl shadow-sm border border-red-100 p-5">
    <div class="flex items-start justify-between gap-3 mb-2">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-1 rounded-full text-xs bg-red-100 text-red-700 font-semibold"><?= $m['wrong_count'] ?> wrong answers</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"><?= ucwords(str_replace('_',' ',$m['question_type'])) ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs <?= $m['topic_title'] ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-500' ?>"><?= e($m['topic_title'] ?? 'General') ?></span>
        </div>
    </div>
    <p class="text-sm font-medium text-gray-800 mb-2"><?= e($m['question_text']) ?></p>
    <?php if ($m['common_wrong_answers']): ?>
    <div class="bg-red-50 rounded-xl px-3 py-2">
        <p class="text-xs text-red-600 font-medium mb-0.5">Common wrong answers:</p>
        <p class="text-xs text-red-500"><?= e(substr($m['common_wrong_answers'], 0, 200)) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (empty($misconceptions)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400">No misconception data yet. Students need to attempt quizzes first.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
