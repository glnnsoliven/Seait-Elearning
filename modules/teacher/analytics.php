<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Analytics';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');
$courses  = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1",[$tid]);

if (!$courseId && !empty($courses)) $courseId = (int)$courses[0]['id'];

// Stats
$stats = [];
if ($courseId) {
    $stats['avg_score']   = Database::fetchOne("SELECT ROUND(AVG(qa.percentage),1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed'",[$courseId])['v'] ?? 0;
    $stats['pass_rate']   = Database::fetchOne("SELECT ROUND(COUNT(CASE WHEN qa.percentage>=60 THEN 1 END)/NULLIF(COUNT(*),0)*100,1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed'",[$courseId])['v'] ?? 0;
    $stats['total_att']   = Database::fetchOne("SELECT COUNT(*) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed'",[$courseId])['v'] ?? 0;
    $stats['at_risk']     = Database::fetchOne("SELECT COUNT(*) v FROM at_risk_students WHERE course_id=? AND is_resolved=0",[$courseId])['v'] ?? 0;
}

// Score distribution
$distData = [];
foreach ([[0,20],[21,40],[41,60],[61,80],[81,100]] as [$lo,$hi]) {
    $distData[] = $courseId ? (int) Database::fetchOne(
        "SELECT COUNT(*) c FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.course_id=? AND qa.status='completed' AND qa.percentage BETWEEN ? AND ?",[$courseId,$lo,$hi])['c'] : 0;
}

// Per-quiz performance
$quizPerf = $courseId ? Database::fetchAll(
    "SELECT q.title, COUNT(qa.id) attempts, ROUND(AVG(qa.percentage),1) avg_score, ROUND(MAX(qa.percentage),1) max_score, ROUND(MIN(qa.percentage),1) min_score
     FROM quizzes q LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
     WHERE q.course_id=? GROUP BY q.id ORDER BY q.created_at",[$courseId]) : [];

// Student rankings
$students = $courseId ? Database::fetchAll(
    "SELECT CONCAT(u.first_name,' ',u.last_name) name, ROUND(AVG(qa.percentage),1) avg_score, COUNT(qa.id) attempts
     FROM students s JOIN users u ON u.id=s.user_id
     JOIN enrollments e ON e.student_id=s.id AND e.course_id=?
     LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     GROUP BY s.id ORDER BY avg_score DESC",[$courseId]) : [];

// Misconceptions
$misconceptions = $courseId ? Database::fetchAll(
    "SELECT qq.question_text, COUNT(sa.id) wrong_count, t.title topic_title
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN quizzes q ON q.id=qq.quiz_id
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE q.course_id=? AND sa.is_correct=0
     GROUP BY qq.id ORDER BY wrong_count DESC LIMIT 5",[$courseId]) : [];
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
    <h2 class="text-xl font-bold text-gray-800">Class Analytics</h2>
    <select onchange="location.href='?course_id='+this.value"
            class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Avg Score',     ($stats['avg_score']??0).'%',   'text-orange-600'],
        ['Pass Rate',     ($stats['pass_rate']??0).'%',   'text-green-600'],
        ['Total Attempts',($stats['total_att']??0),       'text-blue-600'],
        ['At-Risk',       ($stats['at_risk']??0),         'text-red-600'],
    ] as [$label,$val,$color]): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-center">
        <p class="text-3xl font-bold <?= $color ?>"><?= e($val) ?></p>
        <p class="text-sm text-gray-500 mt-1"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Score Distribution</h3>
        <canvas id="distChart" height="120"></canvas>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Quiz Performance</h3>
        <canvas id="quizChart" height="120"></canvas>
    </div>
</div>

<!-- Student Rankings + Misconceptions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Student Rankings</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Avg Score</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Attempts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php foreach ($students as $ri => $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-bold text-orange-600"><?= $ri+1 ?></td>
                    <td class="px-4 py-2.5 font-medium text-gray-800"><?= e($s['name']) ?></td>
                    <td class="px-4 py-2.5 text-center font-semibold <?= masteryColor((int)($s['avg_score']??0)) ?>"><?= $s['avg_score']??'N/A' ?></td>
                    <td class="px-4 py-2.5 text-center text-gray-600"><?= $s['attempts'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Common Misconceptions</h3>
        </div>
        <div class="p-5 space-y-3">
        <?php foreach ($misconceptions as $m): ?>
        <div class="border border-red-100 bg-red-50 rounded-xl p-3">
            <p class="text-sm font-medium text-red-800 line-clamp-2"><?= e($m['question_text']) ?></p>
            <div class="flex items-center justify-between mt-1">
                <p class="text-xs text-red-600"><?= e($m['topic_title'] ?? 'General') ?></p>
                <span class="text-xs font-bold text-red-700"><?= $m['wrong_count'] ?> wrong answers</span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($misconceptions)): ?>
        <p class="text-center text-gray-400 text-sm py-4">No misconceptions data yet.</p>
        <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
new Chart(document.getElementById('distChart'), {
    type: 'bar',
    data: {
        labels: ['0-20','21-40','41-60','61-80','81-100'],
        datasets: [{ label: 'Students', data: <?= json_encode($distData) ?>, backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#e67e22'], borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend:{ display:false } }, scales: { y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});
new Chart(document.getElementById('quizChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($q)=>substr($q['title'],0,20), $quizPerf)) ?>,
        datasets: [{ label: 'Avg Score', data: <?= json_encode(array_column($quizPerf,'avg_score')) ?>, backgroundColor: '#e67e22', borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend:{ display:false } }, scales: { y:{ min:0, max:100 } } }
});
</script>
</body>
</html>
