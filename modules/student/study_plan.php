<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$pageTitle = 'Study Plan & Calendar';

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');
    if ($action === 'complete') {
        StudyPlan::complete(postInt('plan_id'), $sid);
        flashSet('success', 'Activity marked as complete!');
    } elseif ($action === 'regenerate') {
        StudyPlan::generate($sid);
        flashSet('success', 'Study plan regenerated!');
    }
    redirect($_SERVER['PHP_SELF'] . '?tab=' . (getStr('tab') ?: 'plan'));
}

// ── Auto-generate if no upcoming plan ────────────────────────
$existing = Database::fetchOne(
    "SELECT id FROM study_plans WHERE student_id=? AND plan_date >= CURDATE() LIMIT 1", [$sid]
);
if (!$existing) StudyPlan::generate($sid);

// ── Study Plan data (7-day list) ──────────────────────────────
$today   = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+13 days')); // 2 weeks
$plans   = StudyPlan::getByRange($sid, $today, $weekEnd);
$grouped = [];
foreach ($plans as $p) $grouped[$p['plan_date']][] = $p;

$priorityColors = [
    'high'   => 'border-red-300 bg-red-50',
    'medium' => 'border-blue-300 bg-blue-50',
    'low'    => 'border-gray-200 bg-gray-50',
];
$priorityBadge  = [
    'high'   => 'bg-red-100 text-red-700',
    'medium' => 'bg-blue-100 text-blue-700',
    'low'    => 'bg-gray-100 text-gray-600',
];

// ── Calendar data ─────────────────────────────────────────────
$year  = getInt('year')  ?: (int)date('Y');
$month = getInt('month') ?: (int)date('n');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstDay    = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startDow    = (int)date('w', $firstDay);
$monthStart  = date('Y-m-01', $firstDay);
$monthEnd    = date('Y-m-t',  $firstDay);
$prevMonth   = $month - 1 ?: 12;
$prevYear    = $month - 1 ? $year : $year - 1;
$nextMonth   = $month % 12 + 1;
$nextYear    = $month === 12 ? $year + 1 : $year;
$dayNames    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// Plans grouped by date for calendar
$calPlans = Database::fetchAll(
    "SELECT plan_date, COUNT(*) total, SUM(is_completed) done
     FROM study_plans WHERE student_id=? AND plan_date BETWEEN ? AND ?
     GROUP BY plan_date",
    [$sid, $monthStart, $monthEnd]
);
$calPlansByDate = [];
foreach ($calPlans as $p) $calPlansByDate[$p['plan_date']] = $p;

// Quiz deadlines for calendar
$deadlines = Database::fetchAll(
    "SELECT q.id, q.title, DATE(q.deadline) deadline_date
     FROM quizzes q
     JOIN enrollments e ON e.course_id=q.course_id AND e.student_id=?
     WHERE q.deadline BETWEEN ? AND ? AND q.is_published=1
     ORDER BY q.deadline",
    [$sid, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']
);
$deadlinesByDate = [];
foreach ($deadlines as $d) $deadlinesByDate[$d['deadline_date']][] = $d;

// Active tab
$activeTab = getStr('tab') ?: 'plan';

// Stats
$totalTasks     = array_sum(array_map('count', $grouped));
$completedTasks = array_sum(array_column($plans, 'is_completed'));
$upcomingQuizzes = count(array_filter($plans, fn($p) => $p['quiz_id'] && !$p['is_completed']));
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

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Study Plan &amp; Calendar</h2>
        <p class="text-sm text-gray-500">Personalized schedule powered by spaced repetition</p>
    </div>
    <form method="POST" class="flex items-center gap-2">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="regenerate">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        <button type="submit"
                class="px-4 py-2 bg-orange-50 text-orange-600 text-sm font-medium rounded-xl hover:bg-orange-100 transition flex items-center gap-2">
            🔄 Regenerate Plan
        </button>
    </form>
</div>

<!-- ── Stats Row ─────────────────────────────────────────────── -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
        <p class="text-2xl font-extrabold text-orange-600"><?= $totalTasks ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Scheduled Tasks</p>
    </div>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
        <p class="text-2xl font-extrabold text-green-600"><?= $completedTasks ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Completed</p>
    </div>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
        <p class="text-2xl font-extrabold text-blue-600"><?= $upcomingQuizzes ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Pending Quizzes</p>
    </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────────────── -->
<div class="flex gap-1 bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 mb-6 w-fit">
    <a href="?tab=plan"
       class="px-5 py-2 text-sm font-semibold rounded-xl transition
              <?= $activeTab === 'plan' ? 'bg-orange-500 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' ?>">
        📋 Study Plan
    </a>
    <a href="?tab=calendar&month=<?= $month ?>&year=<?= $year ?>"
       class="px-5 py-2 text-sm font-semibold rounded-xl transition
              <?= $activeTab === 'calendar' ? 'bg-orange-500 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' ?>">
        📅 Calendar
    </a>
</div>

<?php if ($activeTab === 'plan'): ?>
<!-- ════════════════════════════════════════════
     STUDY PLAN TAB
════════════════════════════════════════════ -->
<?php if (empty($plans)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-4">📅</div>
    <p class="text-gray-600 font-medium">No study activities yet.</p>
    <p class="text-gray-400 text-sm mt-1">Complete some quizzes so the plan can personalize your schedule.</p>
</div>
<?php else: ?>
<div class="space-y-6">
<?php foreach ($grouped as $date => $dayPlans):
    $isToday  = $date === $today;
    $dayLabel = $isToday ? 'Today' : date('l', strtotime($date));
    $dayFmt   = date('F j', strtotime($date));
    $total    = count($dayPlans);
    $done     = count(array_filter($dayPlans, fn($p) => $p['is_completed']));
?>
<div>
    <!-- Day header -->
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 <?= $isToday ? 'bg-orange-500' : 'bg-gray-200' ?> rounded-xl flex flex-col items-center justify-center flex-shrink-0">
            <p class="text-<?= $isToday ? 'white' : 'gray-600' ?> text-xs font-bold leading-none"><?= date('d', strtotime($date)) ?></p>
            <p class="text-<?= $isToday ? 'white/80' : 'gray-400' ?> text-[9px] leading-none"><?= date('M', strtotime($date)) ?></p>
        </div>
        <div>
            <p class="font-semibold text-gray-800"><?= $dayLabel ?> — <?= $dayFmt ?></p>
            <p class="text-xs text-gray-400"><?= $done ?>/<?= $total ?> completed</p>
        </div>
        <!-- Progress bar -->
        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden hidden sm:block">
            <div class="h-full bg-orange-400 rounded-full transition-all"
                 style="width:<?= $total > 0 ? round($done/$total*100) : 0 ?>%"></div>
        </div>
    </div>

    <div class="space-y-2">
    <?php foreach ($dayPlans as $p):
        $planMaterials = [];
        if ($p['lesson_id']) {
            $planMaterials = Database::fetchAll(
                "SELECT id,title,type,file_path,external_url,file_size
                 FROM learning_materials WHERE lesson_id=? ORDER BY sort_order,id",
                [$p['lesson_id']]
            );
        }

        // For quiz items — fetch the latest completed attempt so student can review
        $latestAttempt = null;
        $quizScore     = null;
        if ($p['quiz_id']) {
            $latestAttempt = Database::fetchOne(
                "SELECT id, percentage, correct_count, total_questions, submitted_at
                 FROM quiz_attempts
                 WHERE quiz_id=? AND student_id=? AND status='completed'
                 ORDER BY submitted_at DESC LIMIT 1",
                [$p['quiz_id'], $sid]
            );
            if ($latestAttempt) {
                $quizScore = (float)$latestAttempt['percentage'];
            }
        }

        $matIcons = ['pdf'=>'📄','pptx'=>'📊','ppt'=>'📊','video'=>'🎬','text'=>'📝','link'=>'🔗','other'=>'📁'];
    ?>
    <div class="bg-white rounded-xl border-l-4 <?= $priorityColors[$p['priority']] ?> shadow-sm <?= $p['is_completed'] ? 'opacity-60' : '' ?>">
        <div class="px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="text-lg flex-shrink-0">
                    <?= $p['lesson_id'] ? '📖' : ($p['quiz_id'] ? '✏️' : '🎯') ?>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate <?= $p['is_completed'] ? 'line-through text-gray-400' : '' ?>">
                        <?= e($p['title']) ?>
                    </p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-xs text-gray-400">⏱ <?= $p['estimated_minutes'] ?> min</span>
                        <span class="px-1.5 py-0.5 rounded text-xs <?= $priorityBadge[$p['priority']] ?>">
                            <?= ucfirst($p['priority']) ?>
                        </span>
                        <?php if (!empty($planMaterials)): ?>
                        <button onclick="togglePlanFiles('pf-<?= $p['id'] ?>')"
                                class="text-xs text-orange-600 hover:text-orange-800 transition">
                            📎 <?= count($planMaterials) ?> file<?= count($planMaterials) != 1 ? 's' : '' ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">

                <?php if ($p['quiz_id']): ?>
                    <?php if ($latestAttempt): ?>
                    <!-- Quiz already attempted — show score badge + Study/Review buttons -->
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold
                        <?= $quizScore >= 75 ? 'bg-green-100 text-green-700' : ($quizScore >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600') ?>">
                        <?= $quizScore ?>%
                    </span>
                    <a href="<?= BASE_URL ?>/modules/student/review_answers.php?attempt_id=<?= $latestAttempt['id'] ?>"
                       class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition font-medium flex items-center gap-1">
                        📖 Study / Review
                    </a>
                    <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $p['quiz_id'] ?>"
                       class="px-3 py-1.5 bg-orange-500 text-white text-xs rounded-lg hover:bg-orange-600 transition font-medium">
                        🔄 Retake
                    </a>
                    <?php else: ?>
                    <!-- Quiz not yet attempted -->
                    <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $p['quiz_id'] ?>"
                       class="px-3 py-1.5 bg-orange-500 text-white text-xs rounded-lg hover:bg-orange-600 transition font-medium">
                        ✏️ Take Quiz
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($p['is_completed']): ?>
                <span class="text-green-500 text-xl font-bold" title="Completed">✓</span>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="tab" value="plan">
                    <button type="submit"
                            class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs rounded-lg hover:bg-gray-200 transition">
                        Done ✓
                    </button>
                </form>
                <?php endif; ?>

            </div>
        </div>

        <!-- Lesson materials (collapsible) -->
        <?php if (!empty($planMaterials)): ?>
        <div id="pf-<?= $p['id'] ?>" class="hidden border-t border-gray-100 px-4 py-3 bg-gray-50 rounded-b-xl">
            <div class="space-y-1.5">
            <?php foreach ($planMaterials as $mat): ?>
            <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-gray-100">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="text-base"><?= $matIcons[$mat['type']] ?? '📁' ?></span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate"><?= e($mat['title']) ?></p>
                        <p class="text-xs text-gray-400"><?= strtoupper($mat['type']) ?>
                            <?php if ($mat['file_size']): ?>· <?= $mat['file_size'] >= 1048576 ? round($mat['file_size']/1048576,1).' MB' : round($mat['file_size']/1024).' KB' ?><?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex gap-1.5 flex-shrink-0">
                    <?php if ($mat['file_path']): ?>
                    <button onclick="openFileViewer('<?= e($mat['title']) ?>','<?= e(fileUrl($mat['file_path'])) ?>','<?= e($mat['type']) ?>')"
                            class="text-xs px-2.5 py-1 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
                        Open
                    </button>
                    <?php elseif ($mat['external_url']): ?>
                    <a href="<?= e($mat['external_url']) ?>" target="_blank"
                       class="text-xs px-2.5 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        Open Link
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════
     CALENDAR TAB
════════════════════════════════════════════ -->
<!-- Month navigation -->
<div class="flex items-center justify-between mb-5">
    <a href="?tab=calendar&month=<?= $prevMonth ?>&year=<?= $prevYear ?>"
       class="p-2 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <p class="font-bold text-gray-800 text-base"><?= date('F Y', $firstDay) ?></p>
    <a href="?tab=calendar&month=<?= $nextMonth ?>&year=<?= $nextYear ?>"
       class="p-2 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<!-- Legend -->
<div class="flex flex-wrap gap-4 mb-4 text-xs">
    <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-orange-400 rounded-full"></div> Study Plan</div>
    <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-red-400 rounded-full"></div> Quiz Deadline</div>
    <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-green-400 rounded-full"></div> All Done</div>
</div>

<!-- Calendar grid -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="grid grid-cols-7 border-b border-gray-100">
        <?php foreach ($dayNames as $dn): ?>
        <div class="py-3 text-center text-xs font-semibold text-gray-500 uppercase"><?= $dn ?></div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-7">
        <?php for ($i = 0; $i < $startDow; $i++): ?>
        <div class="min-h-[90px] p-2 border-b border-r border-gray-50 bg-gray-50/50"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $ds          = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isToday     = $ds === $today;
            $plan        = $calPlansByDate[$ds] ?? null;
            $hasDl       = !empty($deadlinesByDate[$ds]);
            $allDone     = $plan && (int)$plan['done'] >= (int)$plan['total'] && (int)$plan['total'] > 0;
        ?>
        <div class="min-h-[90px] p-2 border-b border-r border-gray-100 <?= $isToday ? 'bg-orange-50' : 'hover:bg-gray-50' ?> transition cursor-default">
            <div class="mb-1">
                <span class="inline-flex w-7 h-7 items-center justify-center rounded-full text-sm font-medium
                    <?= $isToday ? 'bg-orange-500 text-white' : 'text-gray-700' ?>">
                    <?= $day ?>
                </span>
            </div>
            <?php if ($plan): ?>
            <div class="flex items-center gap-1 <?= $allDone ? 'bg-green-100' : 'bg-orange-100' ?> rounded px-1.5 py-0.5 mb-1">
                <div class="w-1.5 h-1.5 <?= $allDone ? 'bg-green-500' : 'bg-orange-500' ?> rounded-full flex-shrink-0"></div>
                <span class="text-xs <?= $allDone ? 'text-green-700' : 'text-orange-700' ?> truncate">
                    <?= $plan['done'] ?>/<?= $plan['total'] ?> tasks
                </span>
            </div>
            <?php endif; ?>
            <?php if ($hasDl): ?>
            <?php foreach (array_slice($deadlinesByDate[$ds], 0, 2) as $dl): ?>
            <div class="flex items-center gap-1 bg-red-100 rounded px-1.5 py-0.5 mb-0.5">
                <div class="w-1.5 h-1.5 bg-red-500 rounded-full flex-shrink-0"></div>
                <span class="text-xs text-red-700 truncate"><?= e(substr($dl['title'], 0, 14)) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Upcoming deadlines list this month -->
<?php $monthDeadlines = array_merge(...array_values($deadlinesByDate)); ?>
<?php if (!empty($monthDeadlines)): ?>
<div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">📌 Quiz Deadlines — <?= date('F Y', $firstDay) ?></h3>
    </div>
    <div class="divide-y divide-gray-50">
    <?php foreach ($deadlinesByDate as $ds => $dls): ?>
    <?php foreach ($dls as $dl): ?>
    <?php
        $daysLeft  = (int)ceil((strtotime($ds) - time()) / 86400);
        $isPast    = $daysLeft < 0;
        $urgency   = $isPast ? 'text-gray-400' : ($daysLeft <= 1 ? 'text-red-600 font-semibold' : ($daysLeft <= 3 ? 'text-amber-600' : 'text-blue-600'));
    ?>
    <div class="px-5 py-3 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-800"><?= e($dl['title']) ?></p>
            <p class="text-xs text-gray-500"><?= date('l, F j, Y', strtotime($ds)) ?></p>
        </div>
        <span class="text-xs <?= $urgency ?>">
            <?= $isPast ? 'Expired' : ($daysLeft === 0 ? 'Due today!' : "In {$daysLeft} day".($daysLeft>1?'s':'')) ?>
        </span>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; /* end calendar tab */ ?>

</main>
</div>

<!-- File Viewer Modal -->
<div id="fileViewerModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <span id="viewerIcon" class="text-xl"></span>
                <h3 id="viewerTitle" class="font-semibold text-gray-800 truncate text-sm"></h3>
            </div>
            <div class="flex items-center gap-2 ml-3">
                <a id="viewerDownload" href="#" download class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs rounded-lg hover:bg-gray-200 transition">Download</a>
                <button onclick="closeFileViewer()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        <div id="viewerBody" class="flex-1 overflow-auto min-h-0 bg-gray-50"></div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function togglePlanFiles(id) {
    document.getElementById(id)?.classList.toggle('hidden');
}

const typeIcons = {pdf:'📄',pptx:'📊',ppt:'📊',video:'🎬',text:'📝',link:'🔗',other:'📁'};

function openFileViewer(title, url, type) {
    document.getElementById('viewerTitle').textContent = title;
    document.getElementById('viewerIcon').textContent  = typeIcons[type] || '📁';
    document.getElementById('viewerDownload').href     = url;
    const body = document.getElementById('viewerBody');
    if (type === 'pdf') {
        body.innerHTML = `<iframe src="${url}" class="w-full h-full min-h-[70vh]"></iframe>`;
    } else if (type === 'video') {
        body.innerHTML = `<div class="flex items-center justify-center h-full p-4 min-h-[50vh]">
            <video controls class="w-full max-h-[65vh] rounded-xl"><source src="${url}"></video></div>`;
    } else if (type === 'pptx' || type === 'ppt') {
        body.innerHTML = `<div class="flex flex-col items-center justify-center min-h-[30vh] gap-4 p-8">
            <div class="text-6xl">📊</div>
            <a href="${url}" target="_blank" class="px-6 py-3 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition text-sm font-semibold">Open Presentation</a>
            <iframe src="${url}" class="w-full min-h-[55vh] rounded-xl border border-gray-200" onerror="this.style.display='none'"></iframe></div>`;
    } else if (type === 'text') {
        body.innerHTML = `<div class="p-6 text-gray-400 text-sm">Loading…</div>`;
        fetch(url).then(r => r.text()).then(t => {
            body.innerHTML = `<div class="p-6"><pre class="text-sm text-gray-800 whitespace-pre-wrap bg-white rounded-xl border p-5 overflow-auto max-h-[60vh]">${t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre></div>`;
        }).catch(() => { body.innerHTML = `<iframe src="${url}" class="w-full min-h-[60vh] border-0"></iframe>`; });
    } else {
        const ext = url.split('.').pop().toLowerCase();
        body.innerHTML = ['jpg','jpeg','png','gif','webp'].includes(ext)
            ? `<div class="flex items-center justify-center p-4 min-h-[50vh]"><img src="${url}" class="max-w-full max-h-[65vh] rounded-xl object-contain"></div>`
            : `<div class="flex flex-col items-center justify-center min-h-[30vh] gap-4 p-8"><div class="text-6xl">📁</div><a href="${url}" download class="px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">⬇ Download File</a></div>`;
    }
    document.getElementById('fileViewerModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeFileViewer() {
    document.getElementById('fileViewerModal').classList.add('hidden');
    document.getElementById('viewerBody').innerHTML = '';
    document.body.style.overflow = '';
}
document.getElementById('fileViewerModal').addEventListener('click', function(e) { if (e.target === this) closeFileViewer(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFileViewer(); });
</script>
</body>
</html>
