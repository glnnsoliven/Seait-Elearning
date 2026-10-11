<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');

$pageTitle = 'Teacher Dashboard';
$teacher   = getTeacherProfile(Auth::id());
if (!$teacher) { flashSet('error','Teacher profile not found.'); redirect(BASE_URL.'/modules/auth/logout.php'); }
$tid = (int)$teacher['id'];

// ── Stats ────────────────────────────────────────────────────
$stats = [
    'total_students' => Database::fetchOne(
        "SELECT COUNT(DISTINCT e.student_id) c FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE c.teacher_id=? AND e.status='active'",[$tid])['c'],
    'active_courses' => Database::fetchOne(
        "SELECT COUNT(*) c FROM courses WHERE teacher_id=? AND is_active=1",[$tid])['c'],
    'published_quizzes' => Database::fetchOne(
        "SELECT COUNT(*) c FROM quizzes WHERE teacher_id=? AND is_published=1",[$tid])['c'],
    'pending_quizzes' => Database::fetchOne(
        "SELECT COUNT(*) c FROM quizzes WHERE teacher_id=? AND is_published=0",[$tid])['c'],
    'avg_score' => Database::fetchOne(
        "SELECT ROUND(AVG(qa.percentage),1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id WHERE q.teacher_id=? AND qa.status='completed'",[$tid])['v'] ?? 0,
    'at_risk' => Database::fetchOne(
        "SELECT COUNT(*) c FROM at_risk_students ar JOIN courses c ON c.id=ar.course_id WHERE c.teacher_id=? AND ar.is_resolved=0",[$tid])['c'],
];

// ── Recent quiz attempts ──────────────────────────────────────
$recentAttempts = Database::fetchAll(
    "SELECT qa.*, q.title quiz_title, CONCAT(u.first_name,' ',u.last_name) student_name
     FROM quiz_attempts qa
     JOIN quizzes q ON q.id=qa.quiz_id
     JOIN students s ON s.id=qa.student_id
     JOIN users u ON u.id=s.user_id
     WHERE q.teacher_id=? AND qa.status='completed'
     ORDER BY qa.submitted_at DESC LIMIT 8",[$tid]
);

// ── Score trend (last 6 months) ───────────────────────────────
$trendLabels = $trendData = [];
for ($i=5;$i>=0;$i--) {
    $m = date('Y-m',strtotime("-{$i} months"));
    $row = Database::fetchOne(
        "SELECT ROUND(AVG(qa.percentage),1) v FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id
         WHERE q.teacher_id=? AND qa.status='completed' AND DATE_FORMAT(qa.submitted_at,'%Y-%m')=?",[$tid,$m]);
    $trendLabels[] = date('M',strtotime("-{$i} months"));
    $trendData[]   = (float)($row['v'] ?? 0);
}

// ── At-risk students ──────────────────────────────────────────
$atRisk = Database::fetchAll(
    "SELECT ar.*, CONCAT(u.first_name,' ',u.last_name) student_name, c.title course_title
     FROM at_risk_students ar
     JOIN students s ON s.id=ar.student_id
     JOIN users u ON u.id=s.user_id
     JOIN courses c ON c.id=ar.course_id
     WHERE c.teacher_id=? AND ar.is_resolved=0
     ORDER BY ar.risk_score DESC LIMIT 5",[$tid]
);

// ── My courses ────────────────────────────────────────────────
$courses = Database::fetchAll(
    "SELECT c.*, COUNT(DISTINCT e.student_id) enrolled
     FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
     WHERE c.teacher_id=? AND c.is_active=1
     GROUP BY c.id ORDER BY c.created_at DESC LIMIT 4",[$tid]
);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_teacher.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<!-- Welcome -->
<div class="rounded-2xl p-5 mb-6 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #e67e22 0%, #d35400 50%, #1a1a2e 100%);">
    <div class="absolute -top-6 -right-6 w-32 h-32 rounded-full opacity-10" style="background:white"></div>
    <div class="absolute -bottom-8 right-24 w-24 h-24 rounded-full opacity-10" style="background:white"></div>
    <div class="relative z-10 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold">Welcome, <?= e($teacher['first_name']) ?>!</h2>
            <p class="text-orange-100 text-sm mt-1"><?= date('l, F j, Y') ?> &mdash; Your teaching overview</p>
        </div>
        <div class="hidden md:block text-right">
            <p class="text-3xl font-black"><?= $stats['avg_score'] ?>%</p>
            <p class="text-orange-200 text-xs">Avg Class Score</p>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <?php foreach ([
        ['Total Students',    $stats['total_students'],    '#e67e22'],
        ['Active Courses',    $stats['active_courses'],    '#2563eb'],
        ['Published Quizzes', $stats['published_quizzes'], '#7c3aed'],
        ['Pending Quizzes',   $stats['pending_quizzes'],   '#f59e0b'],
        ['Avg Class Score',   $stats['avg_score'].'%',     '#10b981'],
        ['At-Risk Students',  $stats['at_risk'],           '#ef4444'],
    ] as [$label,$val,$hex]): ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 hover:shadow-md transition">
        <p class="text-2xl font-extrabold text-gray-800"><?= e($val) ?></p>
        <p class="text-xs text-gray-500 mt-0.5"><?= $label ?></p>
        <div class="h-1 rounded-full mt-2" style="background:<?= $hex ?>;opacity:0.5"></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Score Trend -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Average Score Trend</h3>
        <canvas id="scoreTrend" height="100"></canvas>
    </div>
    <!-- Recent Attempts -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-800">Recent Quiz Submissions</h3>
            <a href="<?= BASE_URL ?>/modules/teacher/analytics.php" class="text-xs font-medium hover:underline" style="color:#e67e22">View All</a>
        </div>
        <div class="space-y-3 max-h-64 overflow-y-auto">
            <?php foreach ($recentAttempts as $a): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= e($a['student_name']) ?></p>
                    <p class="text-xs text-gray-500 truncate"><?= e($a['quiz_title']) ?></p>
                </div>
                <span class="ml-3 text-sm font-bold <?= masteryColor((int)$a['percentage']) ?>">
                    <?= $a['percentage'] ?>%
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentAttempts)): ?>
            <p class="text-center text-gray-400 text-sm py-6">No submissions yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- My Courses + At-Risk -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Quick Course Cards -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">My Courses</h3>
            <a href="<?= BASE_URL ?>/modules/teacher/courses.php" class="text-xs font-medium hover:underline" style="color:#e67e22">Manage All</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($courses as $c): ?>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-semibold text-sm text-gray-800 leading-tight"><?= e($c['title']) ?></p>
                        <p class="text-xs text-gray-500"><?= e($c['code']) ?></p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs <?= $c['is_published']?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
                        <?= $c['is_published']?'Live':'Draft' ?>
                    </span>
                </div>
                <p class="text-xs text-gray-500"><?= $c['enrolled'] ?> students enrolled</p>
                <a href="<?= BASE_URL ?>/modules/teacher/courses.php?id=<?= $c['id'] ?>"
                   class="mt-2 block text-center py-1.5 text-xs bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition">
                    Manage
                </a>
            </div>
            <?php endforeach; ?>
            <?php if (empty($courses)): ?>
            <div class="col-span-2 bg-white rounded-2xl p-8 text-center shadow-sm border border-gray-100">
                <p class="text-gray-400 text-sm mb-3">No courses yet.</p>
                <a href="<?= BASE_URL ?>/modules/teacher/create_course.php"
                   class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Create Course</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- At-Risk -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">At-Risk Students</h3>
            <a href="<?= BASE_URL ?>/modules/teacher/students.php" class="text-xs text-orange-600 hover:underline">View All</a>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if (empty($atRisk)): ?>
            <p class="px-5 py-8 text-center text-gray-400 text-sm">No at-risk students.</p>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($atRisk as $r): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?= e($r['student_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= e($r['course_title']) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= riskBadgeClass($r['risk_level']) ?>">
                            <?= ucfirst($r['risk_level']) ?>
                        </span>
                        <p class="text-xs text-gray-400 mt-0.5">Score: <?= $r['risk_score'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
new Chart(document.getElementById('scoreTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
            label: 'Avg Score',
            data: <?= json_encode($trendData) ?>,
            borderColor: '#e67e22',
            backgroundColor: 'rgba(230,126,34,0.08)',
            fill: true, tension: 0.4,
            pointBackgroundColor: '#e67e22', pointRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend:{ display:false } }, scales: { y:{ min:0, max:100 } } }
});
</script>
</body>
</html>
