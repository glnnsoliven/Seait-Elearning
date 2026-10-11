<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Admin Dashboard';

// ── Statistics ───────────────────────────────────────────────
$stats = [
    'total_students'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='student' AND is_active=1")['c'],
    'total_teachers'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'],
    'total_courses'   => Database::fetchOne("SELECT COUNT(*) c FROM courses WHERE is_active=1")['c'],
    'total_quizzes'   => Database::fetchOne("SELECT COUNT(*) c FROM quizzes WHERE is_published=1")['c'],
    'total_attempts'  => Database::fetchOne("SELECT COUNT(*) c FROM quiz_attempts")['c'],
    'avg_performance' => Database::fetchOne("SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'at_risk_count'   => Database::fetchOne("SELECT COUNT(*) c FROM at_risk_students WHERE is_resolved=0 AND risk_level IN ('medium','high')")['c'],
];

// ── Recent audit logs ─────────────────────────────────────────
$recentActivity = Database::fetchAll(
    "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.role
     FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
     ORDER BY al.created_at DESC LIMIT 10"
);

// ── Monthly enrollment chart data (last 6 months) ────────────
$chartLabels  = [];
$chartData    = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $label = date('M Y', strtotime("-{$i} months"));
    $count = Database::fetchOne(
        "SELECT COUNT(*) c FROM enrollments WHERE DATE_FORMAT(enrolled_at,'%Y-%m') = ?",
        [$month]
    )['c'];
    $chartLabels[] = $label;
    $chartData[]   = (int)$count;
}

// ── Department performance ────────────────────────────────────
$deptPerf = Database::fetchAll(
    "SELECT d.name, COUNT(DISTINCT s.id) AS student_count,
            ROUND(AVG(qa.percentage),1) AS avg_score
     FROM departments d
     LEFT JOIN students s ON s.department_id = d.id
     LEFT JOIN quiz_attempts qa ON qa.student_id = s.id AND qa.status='completed'
     GROUP BY d.id ORDER BY avg_score DESC LIMIT 5"
);

// ── At-risk students ──────────────────────────────────────────
$atRisk = Database::fetchAll(
    "SELECT ar.*, CONCAT(u.first_name,' ',u.last_name) AS student_name,
             c.title AS course_title
     FROM at_risk_students ar
     JOIN students st ON st.id = ar.student_id
     JOIN users u ON u.id = st.user_id
     JOIN courses c ON c.id = ar.course_id
     WHERE ar.is_resolved = 0
     ORDER BY ar.risk_score DESC LIMIT 5"
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
        <?php include __DIR__ . '/../../includes/components/flash.php'; ?>

        <!-- Welcome -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?= e($_SESSION['user_name']) ?></h2>
            <p class="text-gray-500 text-sm mt-1"><?= date('l, F j, Y') ?> — Institution Overview</p>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?php
            $cards = [
                ['label'=>'Total Students',   'value'=>$stats['total_students'],  'color'=>'bg-blue-500',   'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['label'=>'Total Teachers',   'value'=>$stats['total_teachers'],  'color'=>'bg-orange-500', 'icon'=>'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label'=>'Total Subjects',    'value'=>$stats['total_courses'],   'color'=>'bg-orange-500', 'icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                ['label'=>'Published Quizzes','value'=>$stats['total_quizzes'],   'color'=>'bg-emerald-500','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ['label'=>'Quiz Attempts',    'value'=>$stats['total_attempts'],  'color'=>'bg-amber-500',  'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['label'=>'Avg Performance',  'value'=>$stats['avg_performance'].'%','color'=>'bg-cyan-500','icon'=>'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                ['label'=>'At-Risk Students', 'value'=>$stats['at_risk_count'],   'color'=>'bg-red-500',    'icon'=>'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                ['label'=>'Departments',      'value'=>Database::fetchOne("SELECT COUNT(*) c FROM departments WHERE is_active=1")['c'], 'color'=>'bg-rose-500','icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
            ];
            foreach ($cards as $card): ?>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 <?= $card['color'] ?> rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $card['icon'] ?>"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-800"><?= e($card['value']) ?></p>
                <p class="text-sm text-gray-500 mt-0.5"><?= e($card['label']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Enrollment Trend -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Enrollment Trend (6 Months)</h3>
                <canvas id="enrollmentChart" height="100"></canvas>
            </div>
            <!-- Department Performance -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Department Average Scores</h3>
                <canvas id="deptChart" height="100"></canvas>
            </div>
        </div>

        <!-- At-Risk + Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- At-Risk Students -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">At-Risk Students</h3>
                    <a href="<?= BASE_URL ?>/modules/admin/analytics.php" class="text-xs text-orange-600 hover:underline">View All</a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($atRisk)): ?>
                    <p class="px-5 py-8 text-center text-gray-400 text-sm">No at-risk students flagged.</p>
                    <?php else: ?>
                    <?php foreach ($atRisk as $r): ?>
                    <div class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= e($r['student_name']) ?></p>
                            <p class="text-xs text-gray-500"><?= e($r['course_title']) ?></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= riskBadgeClass($r['risk_level']) ?>">
                            <?= ucfirst($r['risk_level']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Recent Activity</h3>
                    <a href="<?= BASE_URL ?>/modules/admin/settings.php#audit" class="text-xs text-orange-600 hover:underline">View Logs</a>
                </div>
                <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <?php foreach ($recentActivity as $log): ?>
                    <div class="px-5 py-3">
                        <p class="text-sm text-gray-700">
                            <span class="font-medium"><?= e($log['user_name'] ?? 'System') ?></span>
                            — <?= e($log['description']) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($log['created_at']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
// Enrollment Trend Chart
new Chart(document.getElementById('enrollmentChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Enrollments',
            data: <?= json_encode($chartData) ?>,
            borderColor: '#e67e22',
            backgroundColor: 'rgba(230,126,34,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#e67e22',
            pointRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Department Chart
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($deptPerf, 'name')) ?>,
        datasets: [{
            label: 'Avg Score (%)',
            data: <?= json_encode(array_column($deptPerf, 'avg_score')) ?>,
            backgroundColor: ['#e67e22','#e67e22','#06b6d4','#10b981','#f59e0b'],
            borderRadius: 8,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
});
</script>
</body>
</html>
