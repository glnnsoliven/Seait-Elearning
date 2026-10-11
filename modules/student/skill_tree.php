<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$pageTitle = 'Skill Tree';

$courseId  = getInt('course_id');
$courses   = Database::fetchAll("SELECT c.id,c.title,c.code FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.student_id=? AND e.status='active'",[$sid]);
if (!$courseId && !empty($courses)) $courseId = (int)$courses[0]['id'];

$competencies = $courseId ? Database::fetchAll(
    "SELECT comp.*, sc.mastery_pct, sc.status comp_status, sc.unlocked_at
     FROM competencies comp
     LEFT JOIN student_competencies sc ON sc.competency_id=comp.id AND sc.student_id=?
     WHERE comp.course_id=? ORDER BY comp.sort_order",[$sid,$courseId]) : [];

// Initialize missing rows
foreach ($competencies as $c) {
    $existing = Database::fetchOne("SELECT id FROM student_competencies WHERE student_id=? AND competency_id=?",[$sid,$c['id']]);
    if (!$existing) {
        $locked = $c['prerequisite_id'] !== null;
        Database::insert('student_competencies',[
            'student_id'=>$sid,'competency_id'=>$c['id'],'mastery_pct'=>0,
            'status'=>$locked?'locked':'learning',
        ]);
    }
}
// Re-fetch after init
$competencies = $courseId ? Database::fetchAll(
    "SELECT comp.*, sc.mastery_pct, sc.status comp_status FROM competencies comp
     LEFT JOIN student_competencies sc ON sc.competency_id=comp.id AND sc.student_id=?
     WHERE comp.course_id=? ORDER BY comp.sort_order",[$sid,$courseId]) : [];

$statusColors = [
    'mastered'  => 'border-green-500 bg-green-50',
    'completed' => 'border-blue-400 bg-blue-50',
    'learning'  => 'border-indigo-300 bg-orange-50',
    'locked'    => 'border-gray-200 bg-gray-100 opacity-60',
];
$statusIcons = ['mastered'=>'⭐','completed'=>'✅','learning'=>'📖','locked'=>'🔒'];
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Competency Skill Tree</h2>
        <p class="text-sm text-gray-500">Track your mastery progression</p>
    </div>
    <select onchange="location.href='?course_id='+this.value"
            class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Legend -->
<div class="flex flex-wrap gap-3 mb-6">
    <?php foreach ($statusIcons as $s => $icon): ?>
    <div class="flex items-center gap-1.5 text-xs text-gray-600">
        <span><?= $icon ?></span>
        <span class="capitalize"><?= $s ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Skill Tree Nodes -->
<?php if (empty($competencies)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-4">🌱</div>
    <p class="text-gray-500">No competencies defined for this course yet.</p>
</div>
<?php else: ?>
<div class="relative">
    <div class="flex flex-col items-center gap-0">
    <?php foreach ($competencies as $i => $comp):
        $status  = $comp['comp_status'] ?? 'locked';
        $mastery = (int)($comp['mastery_pct'] ?? 0);
        $cls     = $statusColors[$status] ?? $statusColors['locked'];
        $icon    = $statusIcons[$status] ?? '🔒';
        $radius  = 52;
        $circumference = round(2*pi()*$radius);
        $offset  = round($circumference * (1 - $mastery/100));
    ?>
    <!-- Connector line (not for first) -->
    <?php if ($i > 0): ?>
    <div class="w-0.5 h-8 <?= in_array($status,['learning','completed','mastered'])?'bg-blue-300':'bg-gray-200' ?>"></div>
    <?php endif; ?>

    <div class="flex items-center gap-5 bg-white rounded-2xl border-2 <?= $cls ?> p-5 shadow-sm w-full max-w-lg">
        <!-- Circular Progress Ring -->
        <div class="relative flex-shrink-0">
            <svg class="w-28 h-28 -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="<?= $radius ?>" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                <circle cx="60" cy="60" r="<?= $radius ?>" fill="none"
                        stroke="<?= match($status){'mastered'=>'#22c55e','completed'=>'#3b82f6','learning'=>'#e67e22',default=>'#d1d5db'} ?>"
                        stroke-width="8"
                        stroke-dasharray="<?= $circumference ?>"
                        stroke-dashoffset="<?= $offset ?>"
                        stroke-linecap="round"
                        class="progress-ring__circle"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl"><?= $icon ?></span>
                <span class="text-sm font-bold text-gray-700"><?= $mastery ?>%</span>
            </div>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <p class="font-bold text-gray-800"><?= e($comp['name']) ?></p>
            <?php if ($comp['description']): ?>
            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2"><?= e($comp['description']) ?></p>
            <?php endif; ?>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
                <span class="px-2 py-0.5 rounded-full text-xs capitalize
                    <?= match($status){'mastered'=>'bg-green-100 text-green-700','completed'=>'bg-blue-100 text-blue-700','learning'=>'bg-orange-100 text-orange-700',default=>'bg-gray-100 text-gray-500'} ?>">
                    <?= ucfirst($status) ?>
                </span>
                <span class="text-xs text-gray-400">Required: <?= $comp['required_mastery'] ?>%</span>
            </div>
            <?php if ($comp['prerequisite_id'] && $status === 'locked'): ?>
            <p class="text-xs text-red-400 mt-1">🔒 Unlock previous competency first</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
