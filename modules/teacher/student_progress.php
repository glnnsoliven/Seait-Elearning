<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Student Progress';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$studentId = getInt('student_id');
$courseId  = getInt('course_id');
if (!$studentId) redirect(BASE_URL.'/modules/teacher/students.php');

$student = Database::fetchOne(
    "SELECT s.*, u.first_name, u.last_name, u.email, u.avatar FROM students s JOIN users u ON u.id=s.user_id WHERE s.id=?",[$studentId]);
if (!$student) redirect(BASE_URL.'/modules/teacher/students.php');

$attempts = Database::fetchAll(
    "SELECT qa.*, q.title quiz_title, c.title course_title
     FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id JOIN courses c ON c.id=q.course_id
     WHERE qa.student_id=? AND c.teacher_id=? AND qa.status='completed'
     ORDER BY qa.submitted_at DESC",[$studentId,$tid]
);

$weakTopics = Database::fetchAll(
    "SELECT t.title, ROUND(SUM(sa.is_correct)/COUNT(sa.id)*100,1) accuracy, COUNT(sa.id) total
     FROM student_answers sa JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN topics t ON t.id=qq.topic_id JOIN quizzes q ON q.id=qq.quiz_id JOIN quiz_attempts qa ON qa.id=sa.attempt_id
     WHERE qa.student_id=? AND q.teacher_id=? AND qa.status='completed'
     GROUP BY t.id HAVING accuracy < 60 ORDER BY accuracy ASC",[$studentId,$tid]
);

$riskRecord = Database::fetchOne(
    "SELECT ar.* FROM at_risk_students ar JOIN courses c ON c.id=ar.course_id WHERE ar.student_id=? AND c.teacher_id=? AND ar.is_resolved=0",[$studentId,$tid]
);

$avatar = $student['avatar'] ? BASE_URL.'/'.$student['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($student['first_name'].' '.$student['last_name']).'&background=8b5cf6&color=fff&size=80';
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_teacher.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<a href="<?= BASE_URL ?>/modules/teacher/students.php" class="inline-flex items-center gap-1 text-sm text-orange-600 hover:underline mb-4">
    ← Back to Students
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
        <img src="<?= e($avatar) ?>" class="w-20 h-20 rounded-2xl mx-auto object-cover mb-3" alt="">
        <h3 class="font-bold text-gray-800 text-lg"><?= e($student['first_name'].' '.$student['last_name']) ?></h3>
        <p class="text-sm text-gray-500"><?= e($student['email']) ?></p>
        <?php if ($riskRecord): ?>
        <span class="mt-3 inline-block px-3 py-1 rounded-full text-sm font-semibold <?= riskBadgeClass($riskRecord['risk_level']) ?>">
            <?= ucfirst($riskRecord['risk_level']) ?> Risk
        </span>
        <?php endif; ?>

        <div class="mt-4 text-left space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Avg Score</span><span class="font-semibold <?= masteryColor((int)(array_sum(array_column($attempts,'percentage'))/max(1,count($attempts)))) ?>"><?= $attempts ? round(array_sum(array_column($attempts,'percentage'))/count($attempts),1).'%' : 'N/A' ?></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Total Attempts</span><span class="font-semibold text-gray-800"><?= count($attempts) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Weak Topics</span><span class="font-semibold text-red-600"><?= count($weakTopics) ?></span></div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Weak Topics -->
        <?php if (!empty($weakTopics)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Weak Topics</h3>
            <div class="space-y-2">
                <?php foreach ($weakTopics as $t): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700"><?= e($t['title']) ?></span>
                        <span class="font-semibold text-red-600"><?= $t['accuracy'] ?>%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-red-400 rounded-full" style="width:<?= $t['accuracy'] ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quiz History -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Quiz History</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Quiz</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    <?php foreach ($attempts as $a): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-medium text-gray-800"><?= e($a['quiz_title']) ?></td>
                        <td class="px-4 py-2.5 text-center font-semibold <?= masteryColor((int)$a['percentage']) ?>"><?= $a['percentage'] ?>%</td>
                        <td class="px-4 py-2.5 text-center text-gray-500 text-xs"><?= formatDate($a['submitted_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attempts)): ?>
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No attempts yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
