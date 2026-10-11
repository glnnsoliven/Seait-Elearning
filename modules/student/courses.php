<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$pageTitle = 'My Subjects';
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];

// Fetch enrolled subjects with section info and progress
$courses = Database::fetchAll(
    "SELECT c.*, d.name dept_name,
            CONCAT(u.first_name,' ',u.last_name) teacher_name,
            cs.name  section_name,
            cs.school_year,
            cs.semester,
            COUNT(DISTINCT l.id)   total_lessons,
            COUNT(DISTINCT q.id)   total_quizzes,
            COUNT(DISTINCT e2.student_id) enrolled_count,
            COUNT(DISTINCT CASE WHEN sp.status='completed' THEN sp.lesson_id END) done_lessons
     FROM enrollments e
     JOIN courses c    ON c.id  = e.course_id
     LEFT JOIN course_sections cs ON cs.id = e.section_id
     LEFT JOIN departments d  ON d.id  = c.department_id
     LEFT JOIN teachers t     ON t.id  = c.teacher_id
     LEFT JOIN users u        ON u.id  = t.user_id
     LEFT JOIN lessons l      ON l.course_id = c.id AND l.is_published = 1
     LEFT JOIN quizzes q      ON q.course_id = c.id AND q.is_published = 1
     LEFT JOIN enrollments e2 ON e2.course_id = c.id AND e2.status = 'active'
     LEFT JOIN student_progress sp ON sp.course_id = c.id AND sp.student_id = ?
     WHERE e.student_id = ? AND e.status = 'active'
     GROUP BY c.id, cs.id
     ORDER BY c.title",
    [$sid, $sid]
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

<!-- ── Join a Subject ──────────────────────────────────────── -->
<div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-2xl p-5 mb-6 text-white">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold">Join a Subject</h2>
            <p class="text-orange-200 text-sm mt-0.5">Enter the 6-character code given by your teacher to enroll in a subject section.</p>
        </div>
        <!-- Join form inline -->
        <form method="POST" action="<?= BASE_URL ?>/modules/api/join_section.php"
              class="flex items-center gap-2 flex-shrink-0">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="text"
                   name="join_code"
                   maxlength="6"
                   autocomplete="off"
                   placeholder="e.g. AB3X9Z"
                   oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
                   required
                   class="w-32 px-4 py-2.5 rounded-xl border-2 border-white/30 bg-white/10 text-white placeholder-violet-300
                          text-sm font-mono font-bold tracking-widest text-center uppercase
                          focus:outline-none focus:border-white focus:bg-white/20 transition">
            <button type="submit"
                    class="px-5 py-2.5 bg-white text-orange-700 text-sm font-bold rounded-xl hover:bg-orange-50 transition shadow">
                Join
            </button>
        </form>
    </div>
</div>

<!-- ── Enrolled Subjects ───────────────────────────────────── -->
<div class="flex items-center justify-between mb-4">
    <div>
        <h3 class="text-base font-bold text-gray-800">My Subjects</h3>
        <p class="text-sm text-gray-500"><?= count($courses) ?> enrolled</p>
    </div>
</div>

<?php if (empty($courses)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="w-16 h-16 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
    </div>
    <p class="text-gray-600 font-semibold">No subjects yet</p>
    <p class="text-gray-400 text-sm mt-1">Enter a section code above to join your first subject.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($courses as $c):
    $pct = $c['total_lessons'] > 0 ? round(($c['done_lessons'] / $c['total_lessons']) * 100) : 0;
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
    <!-- Card header -->
    <div class="bg-gradient-to-br from-blue-600 to-orange-600 px-5 py-4">
        <div class="flex items-start justify-between mb-2">
            <div class="min-w-0 flex-1">
                <p class="text-white font-bold leading-tight truncate"><?= e($c['title']) ?></p>
                <p class="text-blue-200 text-xs mt-0.5"><?= e($c['code']) ?></p>
            </div>
            <span class="ml-2 px-2 py-0.5 text-xs bg-white/20 text-white rounded-full flex-shrink-0"><?= $pct ?>%</span>
        </div>
        <!-- Progress bar -->
        <div class="h-1.5 bg-white/30 rounded-full overflow-hidden">
            <div class="h-full bg-white rounded-full transition-all" style="width:<?= $pct ?>%"></div>
        </div>
    </div>

    <div class="p-4 flex-1 flex flex-col">
        <!-- Section badge -->
        <?php if ($c['section_name']): ?>
        <div class="flex items-center gap-1.5 mb-2">
            <svg class="w-3.5 h-3.5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-xs font-semibold text-orange-700 bg-orange-50 px-2 py-0.5 rounded-full">
                <?= e($c['section_name']) ?>
                <?php if ($c['school_year']): ?>· <?= e($c['school_year']) ?> <?= e($c['semester']) ?> Sem<?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <p class="text-xs text-gray-500 mb-3">
            <span class="font-medium text-gray-700">Teacher:</span> <?= e($c['teacher_name'] ?? '—') ?>
        </p>
        <p class="text-xs text-gray-400 line-clamp-2 mb-3"><?= e($c['description'] ?? 'No description') ?></p>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-2 mb-4 text-center">
            <?php foreach ([[$c['total_lessons'],'Lessons'],[$c['total_quizzes'],'Quizzes'],[$c['enrolled_count'],'Students']] as [$v,$lbl]): ?>
            <div class="bg-gray-50 rounded-xl py-2">
                <p class="text-base font-bold text-gray-800"><?= $v ?></p>
                <p class="text-xs text-gray-500"><?= $lbl ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="mt-auto grid grid-cols-2 gap-2">
            <a href="<?= BASE_URL ?>/modules/student/course_view.php?id=<?= $c['id'] ?>"
               class="text-center py-2 text-xs bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium">
                Continue
            </a>
            <a href="<?= BASE_URL ?>/modules/student/quiz_list.php?course_id=<?= $c['id'] ?>"
               class="text-center py-2 text-xs bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition">
                Quizzes
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
