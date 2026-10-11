<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');

$pageTitle = 'Student Dashboard';
$student   = getStudentProfile(Auth::id());
if (!$student) { flashSet('error','Student profile not found.'); redirect(BASE_URL.'/modules/auth/logout.php'); }
$sid = (int)$student['id'];

// ── Stats ────────────────────────────────────────────────────
$enrolledCourses = Database::fetchOne("SELECT COUNT(*) c FROM enrollments WHERE student_id=? AND status='active'",[$sid])['c'];
$completedLessons= Database::fetchOne("SELECT COUNT(*) c FROM student_progress WHERE student_id=? AND status='completed'",[$sid])['c'];
$availableQuizzes= Database::fetchOne(
    "SELECT COUNT(*) c FROM quizzes q
     JOIN enrollments e ON e.course_id=q.course_id AND e.student_id=?
     LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed'
     WHERE q.is_published=1 AND qa.id IS NULL",[$sid,$sid])['c'];
$avgMastery = Database::fetchOne(
    "SELECT ROUND(AVG(mastery_pct),1) v FROM student_competencies WHERE student_id=?",[$sid])['v'] ?? 0;

// ── Upcoming deadlines ────────────────────────────────────────
$deadlines = Database::fetchAll(
    "SELECT q.title, q.deadline, c.title course_title
     FROM quizzes q JOIN enrollments e ON e.course_id=q.course_id AND e.student_id=?
     LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.student_id=?
     JOIN courses c ON c.id=q.course_id
     WHERE q.is_published=1 AND q.deadline > NOW() AND qa.id IS NULL
     ORDER BY q.deadline ASC LIMIT 5",[$sid,$sid]
);

// ── Weak topics ───────────────────────────────────────────────
$weakTopics = Database::fetchAll(
    "SELECT t.title, ROUND(AVG(CASE WHEN sa.is_correct THEN 100.0 ELSE 0 END),1) accuracy
     FROM student_answers sa
     JOIN quiz_questions qq ON qq.id=sa.question_id
     JOIN quiz_attempts qa ON qa.id=sa.attempt_id AND qa.student_id=?
     LEFT JOIN topics t ON t.id=qq.topic_id
     WHERE qa.status='completed'
     GROUP BY t.id HAVING accuracy < 60
     ORDER BY accuracy ASC LIMIT 4",[$sid]
);

// ── Recommendations ───────────────────────────────────────────
Recommendation::generate($sid);
$recs = Recommendation::get($sid, 4);

// ── Recent quiz scores ────────────────────────────────────────
$recentScores = Database::fetchAll(
    "SELECT qa.percentage, qa.correct_count, qa.total_questions, qa.submitted_at, q.title quiz_title
     FROM quiz_attempts qa JOIN quizzes q ON q.id=qa.quiz_id
     WHERE qa.student_id=? AND qa.status='completed'
     ORDER BY qa.submitted_at DESC LIMIT 6",[$sid]
);

// ── Progress trend (last 6 months) ───────────────────────────
$trendLabels = $trendData = [];
for ($i=5;$i>=0;$i--) {
    $m = date('Y-m',strtotime("-{$i} months"));
    $row = Database::fetchOne(
        "SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE student_id=? AND status='completed' AND DATE_FORMAT(submitted_at,'%Y-%m')=?",[$sid,$m]);
    $trendLabels[] = date('M',strtotime("-{$i} months"));
    $trendData[]   = (float)($row['v'] ?? 0);
}

// ── Enrolled courses progress ─────────────────────────────────
$courses = Database::fetchAll(
    "SELECT c.id, c.title, c.code,
            COUNT(DISTINCT l.id) total_lessons,
            COUNT(DISTINCT CASE WHEN sp.status='completed' THEN sp.lesson_id END) done_lessons
     FROM enrollments e
     JOIN courses c ON c.id=e.course_id
     LEFT JOIN lessons l ON l.course_id=c.id AND l.is_published=1
     LEFT JOIN student_progress sp ON sp.course_id=c.id AND sp.student_id=?
     WHERE e.student_id=? AND e.status='active'
     GROUP BY c.id ORDER BY c.title LIMIT 4",[$sid,$sid]
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
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<!-- Welcome Banner -->
<div class="rounded-2xl p-5 mb-6 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #e67e22 0%, #d35400 50%, #1a1a2e 100%);">
    <!-- Decorative circles -->
    <div class="absolute -top-6 -right-6 w-32 h-32 rounded-full opacity-10" style="background:white"></div>
    <div class="absolute -bottom-8 right-20 w-24 h-24 rounded-full opacity-10" style="background:white"></div>
    <div class="flex items-center justify-between relative z-10">
        <div>
            <h2 class="text-2xl font-bold">Welcome back, <?= e($student['first_name']) ?>! &#128075;</h2>
            <p class="text-orange-100 text-sm mt-1"><?= date('l, F j, Y') ?> &mdash; Keep up the great work!</p>
        </div>
        <div class="hidden md:block text-right">
            <p class="text-3xl font-black text-white"><?= $avgMastery ?>%</p>
            <p class="text-orange-200 text-xs">Overall Mastery</p>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Overall Mastery',   $avgMastery.'%',   '#e67e22', 'M13 10V3L4 14h7v7l9-11h-7z'],
        ['Enrolled Courses',  $enrolledCourses,  '#2563eb', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['Completed Lessons', $completedLessons, '#10b981', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Available Quizzes', $availableQuizzes, '#f59e0b', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ] as [$label,$val,$hex,$icon]): ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 hover:shadow-md transition">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3"
             style="background:<?= $hex ?>20">
            <svg class="w-5 h-5" style="color:<?= $hex ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
            </svg>
        </div>
        <p class="text-2xl font-extrabold text-gray-800"><?= e($val) ?></p>
        <p class="text-xs text-gray-500 mt-0.5"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Progress + Deadlines -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Progress Chart -->
    <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Learning Progress (6 Months)</h3>
        <canvas id="progressChart" height="80"></canvas>
    </div>

    <!-- Upcoming Deadlines -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Upcoming Deadlines</h3>
        <?php if (empty($deadlines)): ?>
        <p class="text-sm text-gray-400 text-center py-4">No upcoming deadlines. 🎉</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($deadlines as $d):
                $daysLeft = (int)ceil((strtotime($d['deadline']) - time()) / 86400);
                $urgency  = $daysLeft <= 1 ? 'text-red-600 bg-red-50' : ($daysLeft <= 3 ? 'text-amber-600 bg-amber-50' : 'text-blue-600 bg-blue-50');
            ?>
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 <?= $urgency ?> rounded-xl flex items-center justify-center flex-shrink-0 text-xs font-bold">
                    <?= $daysLeft ?>d
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= e($d['title']) ?></p>
                    <p class="text-xs text-gray-500"><?= e($d['course_title']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/student/quiz_list.php"
           class="mt-4 block text-center text-xs font-medium hover:underline" style="color:#e67e22">View All Quizzes &rarr;</a>
    </div>
</div>

<!-- Course Progress + Weak Topics -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Course Progress -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">Subject Progress</h3>
            <a href="<?= BASE_URL ?>/modules/student/courses.php" class="text-xs font-medium hover:underline" style="color:#e67e22">All Subjects</a>
        </div>
        <div class="space-y-4">
        <?php foreach ($courses as $c):
            $pct = $c['total_lessons'] > 0 ? round(($c['done_lessons']/$c['total_lessons'])*100) : 0;
        ?>
        <div>
            <div class="flex items-center justify-between mb-1">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= e($c['title']) ?></p>
                    <p class="text-xs text-gray-500"><?= $c['done_lessons'] ?>/<?= $c['total_lessons'] ?> lessons</p>
                </div>
                <span class="text-sm font-bold <?= masteryColor($pct) ?>"><?= $pct ?>%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full <?= masteryBarColor($pct) ?> rounded-full transition-all" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
        <p class="text-center text-gray-400 text-sm py-4">Not enrolled in any courses yet.</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- Weak Topics + Recommendations -->
    <div class="space-y-4">
        <!-- Weak Topics -->
        <?php if (!empty($weakTopics)): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-red-100">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800">⚠ Weak Topics</h3>
                <a href="<?= BASE_URL ?>/modules/student/weak_topics.php" class="text-xs text-red-600 hover:underline">View All</a>
            </div>
            <div class="space-y-2">
            <?php foreach ($weakTopics as $t): ?>
            <div class="flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 truncate"><?= e($t['title'] ?? 'General') ?></p>
                    <div class="h-1.5 bg-gray-100 rounded-full mt-1">
                        <div class="h-full bg-red-400 rounded-full" style="width:<?= $t['accuracy'] ?>%"></div>
                    </div>
                </div>
                <span class="text-xs font-bold text-red-600 flex-shrink-0"><?= $t['accuracy'] ?>%</span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800">💡 Recommended</h3>
                <a href="<?= BASE_URL ?>/modules/student/recommendations.php" class="text-xs font-medium hover:underline" style="color:#e67e22">View All</a>
            </div>
            <div class="space-y-2">
            <?php foreach ($recs as $r): ?>
            <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-xl">
                <span class="text-xl flex-shrink-0"><?= $r['type']==='quiz' ? '✏️' : '📖' ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= e($r['title']) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2"><?= e($r['reason']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recs)): ?>
            <p class="text-center text-gray-400 text-sm py-2">Complete quizzes to get recommendations.</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Quiz Scores -->
<?php if (!empty($recentScores)): ?>
<div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
    <h3 class="font-semibold text-gray-800 mb-4">Recent Quiz Scores</h3>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <?php foreach ($recentScores as $s): ?>
        <div class="text-center bg-gray-50 rounded-2xl p-3">
            <!-- Circular progress -->
            <div class="relative w-14 h-14 mx-auto mb-2">
                <svg class="w-14 h-14 -rotate-90" viewBox="0 0 56 56">
                    <circle cx="28" cy="28" r="22" fill="none" stroke="#e5e7eb" stroke-width="4"/>
                    <circle cx="28" cy="28" r="22" fill="none"
                            stroke="<?= $s['percentage']>=60?'#e67e22':'#ef4444' ?>"
                            stroke-width="4"
                            stroke-dasharray="<?= round(2*pi()*22) ?>"
                            stroke-dashoffset="<?= round(2*pi()*22*(1-$s['percentage']/100)) ?>"
                            stroke-linecap="round"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold <?= $s['percentage']>=60?'text-orange-600':'text-red-500' ?>">
                    <?= $s['percentage'] ?>%
                </span>
            </div>
            <p class="text-xs font-medium text-gray-700 truncate"><?= e($s['quiz_title']) ?></p>
            <p class="text-xs text-gray-400"><?= formatDate($s['submitted_at'],'M d') ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</main>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
new Chart(document.getElementById('progressChart'), {
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
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, ticks: { callback: v => v+'%' } } }
    }
});
</script>
</body>
</html>
