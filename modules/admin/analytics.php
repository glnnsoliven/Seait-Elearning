<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Academic Analytics';

// ── Overview numbers ──────────────────────────────────────────
$overview = [
    'avg_score'    => Database::fetchOne("SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'pass_rate'    => Database::fetchOne("SELECT ROUND(COUNT(CASE WHEN percentage>=60 THEN 1 END)/COUNT(*)*100,1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'total_attempts'=> Database::fetchOne("SELECT COUNT(*) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'at_risk'      => Database::fetchOne("SELECT COUNT(*) v FROM at_risk_students WHERE is_resolved=0")['v'] ?? 0,
];

// ── Monthly quiz attempts last 6 months ───────────────────────
$monthlyLabels = $monthlyData = $monthlyScores = [];
for ($i=5;$i>=0;$i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $l = date('M Y', strtotime("-{$i} months"));
    $row = Database::fetchOne(
        "SELECT COUNT(*) cnt, ROUND(AVG(percentage),1) avg_pct FROM quiz_attempts WHERE status='completed' AND DATE_FORMAT(submitted_at,'%Y-%m')=?", [$m]
    );
    $monthlyLabels[]  = $l;
    $monthlyData[]    = (int)($row['cnt'] ?? 0);
    $monthlyScores[]  = (float)($row['avg_pct'] ?? 0);
}

// ── Score distribution ────────────────────────────────────────
$distLabels = ['0-20','21-40','41-60','61-80','81-100'];
$distData   = [];
foreach ([[0,20],[21,40],[41,60],[61,80],[81,100]] as [$lo,$hi]) {
    $distData[] = (int) Database::fetchOne(
        "SELECT COUNT(*) c FROM quiz_attempts WHERE status='completed' AND percentage BETWEEN ? AND ?", [$lo,$hi]
    )['c'];
}

// ── At-risk students ──────────────────────────────────────────
$atRisk = Database::fetchAll(
    "SELECT ar.*, CONCAT(u.first_name,' ',u.last_name) AS student_name, c.title course_title,
            d.name dept_name
     FROM at_risk_students ar
     JOIN students s ON s.id=ar.student_id
     JOIN users u ON u.id=s.user_id
     JOIN courses c ON c.id=ar.course_id
     LEFT JOIN departments d ON d.id=s.department_id
     WHERE ar.is_resolved=0
     ORDER BY ar.risk_score DESC"
);

// ── Department performance ────────────────────────────────────
$deptStats = Database::fetchAll(
    "SELECT d.name, ROUND(AVG(qa.percentage),1) avg_score,
            COUNT(DISTINCT s.id) student_count,
            COUNT(qa.id) attempts
     FROM departments d
     JOIN students s ON s.department_id=d.id
     JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     GROUP BY d.id ORDER BY avg_score DESC"
);

// ── Top performing courses ────────────────────────────────────
$topCourses = Database::fetchAll(
    "SELECT c.title, c.code, ROUND(AVG(qa.percentage),1) avg_score,
            COUNT(DISTINCT e.student_id) enrolled
     FROM courses c
     JOIN quizzes q ON q.course_id=c.id
     JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
     JOIN enrollments e ON e.course_id=c.id
     GROUP BY c.id ORDER BY avg_score DESC LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">Academic Analytics</h2>
    <p class="text-sm text-gray-500">Institution-wide performance overview</p>
</div>

<!-- Overview Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['label'=>'Average Score',    'value'=>$overview['avg_score'].'%',    'color'=>'text-orange-600', 'bg'=>'bg-orange-50'],
        ['label'=>'Pass Rate',        'value'=>$overview['pass_rate'].'%',    'color'=>'text-green-600',  'bg'=>'bg-green-50'],
        ['label'=>'Total Attempts',   'value'=>$overview['total_attempts'],   'color'=>'text-blue-600',   'bg'=>'bg-blue-50'],
        ['label'=>'At-Risk Students', 'value'=>$overview['at_risk'],          'color'=>'text-red-600',    'bg'=>'bg-red-50'],
    ] as $card): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <p class="text-3xl font-bold <?= $card['color'] ?>"><?= $card['value'] ?></p>
        <p class="text-sm text-gray-500 mt-1"><?= $card['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Monthly Quiz Attempts</h3>
        <canvas id="attemptsChart" height="110"></canvas>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Score Distribution</h3>
        <canvas id="distChart" height="110"></canvas>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Average Score Trend</h3>
        <canvas id="scoresTrend" height="110"></canvas>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Department Performance</h3>
        <canvas id="deptChart" height="110"></canvas>
    </div>
</div>

<!-- At-Risk + Top Courses -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- At-Risk Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">At-Risk Students</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Course</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Risk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php foreach ($atRisk as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-medium text-gray-800"><?= e($r['student_name']) ?></td>
                    <td class="px-4 py-2.5 text-gray-600 text-xs"><?= e($r['course_title']) ?></td>
                    <td class="px-4 py-2.5">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= riskBadgeClass($r['risk_level']) ?>">
                            <?= ucfirst($r['risk_level']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($atRisk)): ?>
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No at-risk students.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Courses -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Top Performing Courses</h3>
        </div>
        <div class="p-5 space-y-4">
        <?php foreach ($topCourses as $c): ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= e($c['title']) ?></p>
                    <p class="text-xs text-gray-500"><?= e($c['code']) ?> · <?= $c['enrolled'] ?> students</p>
                </div>
                <span class="text-sm font-bold <?= masteryColor((int)$c['avg_score']) ?>"><?= $c['avg_score'] ?>%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full <?= masteryBarColor((int)$c['avg_score']) ?> rounded-full" style="width: <?= $c['avg_score'] ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topCourses)): ?>
        <p class="text-center text-gray-400 text-sm py-4">No data yet.</p>
        <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
const labels = <?= json_encode($monthlyLabels) ?>;
const attempts = <?= json_encode($monthlyData) ?>;
const scores = <?= json_encode($monthlyScores) ?>;

new Chart(document.getElementById('attemptsChart'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Attempts', data: attempts, backgroundColor: '#818cf8', borderRadius: 6 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('distChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($distLabels) ?>,
        datasets: [{ data: <?= json_encode($distData) ?>, backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#e67e22'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'right' } } }
});

new Chart(document.getElementById('scoresTrend'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'Avg Score', data: scores, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true, tension: 0.4, pointBackgroundColor: '#10b981' }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } }
});

new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($deptStats, 'name')) ?>,
        datasets: [{ label: 'Avg Score', data: <?= json_encode(array_column($deptStats, 'avg_score')) ?>, backgroundColor: '#e67e22', borderRadius: 6 }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { min: 0, max: 100 } } }
});
</script>
</body>
</html>
