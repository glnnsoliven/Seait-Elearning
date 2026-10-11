<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Reports';

// Aggregate stats for report
$stats = [
    'total_users'    => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE is_active=1")['c'],
    'total_students' => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='student' AND is_active=1")['c'],
    'total_teachers' => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'],
    'total_courses'  => Database::fetchOne("SELECT COUNT(*) c FROM courses WHERE is_active=1")['c'],
    'total_quizzes'  => Database::fetchOne("SELECT COUNT(*) c FROM quizzes WHERE is_published=1")['c'],
    'total_attempts' => Database::fetchOne("SELECT COUNT(*) c FROM quiz_attempts WHERE status='completed'")['c'],
    'avg_score'      => Database::fetchOne("SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'pass_rate'      => Database::fetchOne("SELECT ROUND(COUNT(CASE WHEN percentage>=60 THEN 1 END)/NULLIF(COUNT(*),0)*100,1) v FROM quiz_attempts WHERE status='completed'")['v'] ?? 0,
    'at_risk'        => Database::fetchOne("SELECT COUNT(*) c FROM at_risk_students WHERE is_resolved=0")['c'],
];

$departments = Database::fetchAll(
    "SELECT d.name, COUNT(DISTINCT s.id) students, COUNT(DISTINCT t.id) teachers,
            COUNT(DISTINCT c.id) courses, ROUND(AVG(qa.percentage),1) avg_score
     FROM departments d
     LEFT JOIN students s ON s.department_id=d.id
     LEFT JOIN teachers t ON t.department_id=d.id
     LEFT JOIN courses c ON c.department_id=d.id
     LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     GROUP BY d.id ORDER BY d.name"
);

$topStudents = Database::fetchAll(
    "SELECT CONCAT(u.first_name,' ',u.last_name) name,
            ROUND(AVG(qa.percentage),1) avg_score, COUNT(qa.id) attempts
     FROM users u
     JOIN students s ON s.user_id=u.id
     JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     GROUP BY u.id ORDER BY avg_score DESC LIMIT 10"
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Institution Report</h2>
        <p class="text-sm text-gray-500">Generated: <?= date('F j, Y g:i A') ?></p>
    </div>
    <button onclick="window.print()"
            class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Print Report
    </button>
</div>

<div id="printArea">

<!-- Header for print -->
<div class="text-center mb-6 hidden print:block">
    <h1 class="text-2xl font-bold">South East Asian Institute of Technology</h1>
    <p class="text-lg">AI-Driven E-Learning Platform — Institution Report</p>
    <p class="text-sm text-gray-500"><?= date('F j, Y') ?></p>
</div>

<!-- Overall Stats -->
<div class="grid grid-cols-3 md:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Total Users',    $stats['total_users']],
        ['Students',       $stats['total_students']],
        ['Teachers',       $stats['total_teachers']],
        ['Courses',        $stats['total_courses']],
        ['Quizzes',        $stats['total_quizzes']],
        ['Attempts',       $stats['total_attempts']],
        ['Avg Score',      $stats['avg_score'].'%'],
        ['Pass Rate',      $stats['pass_rate'].'%'],
        ['At-Risk',        $stats['at_risk']],
    ] as [$label,$val]): ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
        <p class="text-2xl font-bold text-orange-600"><?= e($val) ?></p>
        <p class="text-xs text-gray-500 mt-0.5"><?= $label ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Department Performance -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Department Performance Summary</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <?php foreach (['Department','Students','Teachers','Courses','Avg Score'] as $h): ?>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($departments as $d): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800"><?= e($d['name']) ?></td>
                <td class="px-5 py-3 text-gray-600"><?= $d['students'] ?></td>
                <td class="px-5 py-3 text-gray-600"><?= $d['teachers'] ?></td>
                <td class="px-5 py-3 text-gray-600"><?= $d['courses'] ?></td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold <?= masteryColor((int)($d['avg_score']??0)) ?>"><?= $d['avg_score'] ?? 'N/A' ?>%</span>
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden min-w-[60px]">
                            <div class="h-full <?= masteryBarColor((int)($d['avg_score']??0)) ?> rounded-full" style="width:<?= min(100, $d['avg_score']??0) ?>%"></div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Students -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Top 10 Performing Students</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Avg Score</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Attempts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($topStudents as $i => $s): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-bold text-orange-600"><?= $i+1 ?></td>
                <td class="px-5 py-3 font-medium text-gray-800"><?= e($s['name']) ?></td>
                <td class="px-5 py-3 font-semibold <?= masteryColor((int)$s['avg_score']) ?>"><?= $s['avg_score'] ?>%</td>
                <td class="px-5 py-3 text-gray-600"><?= $s['attempts'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /printArea -->

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<style>
@media print {
    #sidebar, header, button { display: none !important; }
    .md\:ml-64 { margin-left: 0 !important; }
    body { background: white !important; }
}
</style>
</body>
</html>
