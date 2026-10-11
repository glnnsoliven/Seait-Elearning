<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Reports';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$courses   = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1",[$tid]);
$courseId  = getInt('course_id');
if (!$courseId && !empty($courses)) $courseId = (int)$courses[0]['id'];
$course    = $courseId ? Database::fetchOne("SELECT * FROM courses WHERE id=?",[$courseId]) : null;

$stats = [];
if ($courseId) {
    $stats = [
        'enrolled'   => Database::fetchOne("SELECT COUNT(*) c FROM enrollments WHERE course_id=? AND status='active'",[$courseId])['c'],
        'avg_score'  => Database::fetchOne("SELECT ROUND(AVG(qa.percentage),1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed'",[$courseId])['v'] ?? 0,
        'pass_rate'  => Database::fetchOne("SELECT ROUND(COUNT(CASE WHEN qa.percentage>=60 THEN 1 END)/NULLIF(COUNT(*),0)*100,1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed'",[$courseId])['v'] ?? 0,
        'at_risk'    => Database::fetchOne("SELECT COUNT(*) c FROM at_risk_students WHERE course_id=? AND is_resolved=0",[$courseId])['c'],
    ];
}

$quizStats = $courseId ? Database::fetchAll(
    "SELECT q.title, q.is_published, COUNT(DISTINCT qa.student_id) students_attempted,
            ROUND(AVG(qa.percentage),1) avg_score, COUNT(qq.id) question_count
     FROM quizzes q
     LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
     LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id
     WHERE q.course_id=?
     GROUP BY q.id ORDER BY q.created_at DESC",[$courseId]) : [];

$studentStats = $courseId ? Database::fetchAll(
    "SELECT CONCAT(u.first_name,' ',u.last_name) name, s.student_id sid,
            ROUND(AVG(qa.percentage),1) avg_score, COUNT(qa.id) attempts,
            COUNT(DISTINCT sp.lesson_id) lessons_done
     FROM students s JOIN users u ON u.id=s.user_id
     JOIN enrollments e ON e.student_id=s.id AND e.course_id=?
     LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     LEFT JOIN student_progress sp ON sp.student_id=s.id AND sp.course_id=? AND sp.status='completed'
     GROUP BY s.id ORDER BY avg_score DESC",[$courseId,$courseId]) : [];
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
        <h2 class="text-xl font-bold text-gray-800">Class Report</h2>
        <p class="text-sm text-gray-500">Generated: <?= date('F j, Y g:i A') ?></p>
    </div>
    <div class="flex gap-2">
        <select onchange="location.href='?course_id='+this.value"
                class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
            <?php foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="window.print()" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print
        </button>
    </div>
</div>

<?php if ($course): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Enrolled',   $stats['enrolled'],      'text-blue-600'],
        ['Avg Score',  $stats['avg_score'].'%', 'text-orange-600'],
        ['Pass Rate',  $stats['pass_rate'].'%', 'text-green-600'],
        ['At-Risk',    $stats['at_risk'],        'text-red-600'],
    ] as [$l,$v,$c]): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
        <p class="text-3xl font-bold <?= $c ?>"><?= e($v) ?></p>
        <p class="text-sm text-gray-500 mt-1"><?= $l ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quiz Stats Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6">
    <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Quiz Performance Summary</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Quiz</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Questions</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Attempted</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Avg Score</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($quizStats as $q): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800"><?= e($q['title']) ?></td>
                <td class="px-5 py-3 text-center text-gray-600"><?= $q['question_count'] ?></td>
                <td class="px-5 py-3 text-center text-gray-600"><?= $q['students_attempted'] ?></td>
                <td class="px-5 py-3 text-center font-semibold <?= masteryColor((int)($q['avg_score']??0)) ?>"><?= $q['avg_score']??'N/A' ?></td>
                <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= $q['is_published']?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>"><?= $q['is_published']?'Published':'Draft' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Student Stats -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Student Performance</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Avg Score</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Attempts</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Lessons Done</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($studentStats as $ri => $s): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-bold text-orange-600"><?= $ri+1 ?></td>
                <td class="px-5 py-3 font-medium text-gray-800"><?= e($s['name']) ?><span class="text-xs text-gray-400 ml-1"><?= e($s['sid']??'') ?></span></td>
                <td class="px-5 py-3 text-center font-semibold <?= masteryColor((int)($s['avg_score']??0)) ?>"><?= $s['avg_score']??'N/A' ?></td>
                <td class="px-5 py-3 text-center text-gray-600"><?= $s['attempts'] ?></td>
                <td class="px-5 py-3 text-center text-gray-600"><?= $s['lessons_done'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<style>@media print { #sidebar,header,select,button{display:none!important} .md\:ml-64{margin-left:0!important} body{background:white!important} }</style>
</body>
</html>
