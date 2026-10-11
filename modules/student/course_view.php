<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];
$cid     = getInt('id');
if (!$cid) redirect(BASE_URL.'/modules/student/courses.php');

// Verify enrollment
$enrollment = Database::fetchOne("SELECT * FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$sid,$cid]);
if (!$enrollment) { flashSet('error','You are not enrolled in this course.'); redirect(BASE_URL.'/modules/student/courses.php'); }

$course  = Database::fetchOne("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) teacher_name FROM courses c JOIN teachers t ON t.id=c.teacher_id JOIN users u ON u.id=t.user_id WHERE c.id=?",[$cid]);
$pageTitle = $course['title'];

$lessons = Database::fetchAll(
    "SELECT l.*, COUNT(DISTINCT m.id) material_count,
            COUNT(DISTINCT t.id) topic_count,
            sp.status AS progress_status, sp.completion_pct
     FROM lessons l
     LEFT JOIN learning_materials m ON m.lesson_id=l.id
     LEFT JOIN topics t ON t.lesson_id=l.id
     LEFT JOIN student_progress sp ON sp.lesson_id=l.id AND sp.student_id=?
     WHERE l.course_id=? AND l.is_published=1
     GROUP BY l.id ORDER BY l.sort_order",[$sid,$cid]
);

$quizzes = Database::fetchAll(
    "SELECT q.*, COUNT(DISTINCT qq.id) question_count,
            qa.id attempt_id, qa.percentage last_score, qa.status attempt_status,
            COUNT(DISTINCT qa2.id) attempt_count
     FROM quizzes q
     LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id
     LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed'
     LEFT JOIN quiz_attempts qa2 ON qa2.quiz_id=q.id AND qa2.student_id=?
     WHERE q.course_id=? AND q.is_published=1
     GROUP BY q.id ORDER BY q.created_at",[$sid,$sid,$cid]
);

// Announcements for this course
$announcements = Database::fetchAll(
    "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) author_name
     FROM announcements a JOIN users u ON u.id=a.author_id
     WHERE (a.course_id=? OR a.course_id IS NULL) AND a.is_active=1
     ORDER BY a.created_at DESC LIMIT 3",[$cid]
);

$totalLessons = count($lessons);
$doneLessons  = count(array_filter($lessons, fn($l) => $l['progress_status'] === 'completed'));
$pct          = $totalLessons > 0 ? round(($doneLessons/$totalLessons)*100) : 0;
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

<!-- Course Header -->
<div class="bg-gradient-to-r from-blue-600 to-orange-600 rounded-2xl p-6 mb-6 text-white">
    <a href="<?= BASE_URL ?>/modules/student/courses.php" class="text-blue-200 text-sm hover:text-white mb-2 inline-block">← My Courses</a>
    <h2 class="text-2xl font-bold"><?= e($course['title']) ?></h2>
    <p class="text-blue-200 text-sm mt-1"><?= e($course['code']) ?> · <?= e($course['teacher_name']) ?></p>
    <div class="mt-4">
        <div class="flex items-center justify-between text-sm mb-1">
            <span class="text-blue-100">Course Progress</span>
            <span class="font-semibold"><?= $pct ?>%</span>
        </div>
        <div class="h-2 bg-white/30 rounded-full overflow-hidden">
            <div class="h-full bg-white rounded-full transition-all" style="width:<?= $pct ?>%"></div>
        </div>
        <p class="text-blue-200 text-xs mt-1"><?= $doneLessons ?> of <?= $totalLessons ?> lessons completed</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Lessons -->
    <div class="lg:col-span-2 space-y-4">
        <h3 class="font-semibold text-gray-800">Lessons</h3>
        <?php foreach ($lessons as $i => $lesson):
            $statusIcon  = match ($lesson['progress_status']) { 'completed' => '✅', 'in_progress' => '🔄', default => '⭕' };
            $statusColor = match ($lesson['progress_status']) { 'completed' => 'border-green-200 bg-green-50', 'in_progress' => 'border-blue-200 bg-blue-50', default => 'border-gray-100' };
        ?>
        <div class="bg-white rounded-2xl border <?= $statusColor ?> p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-blue-700 text-sm">
                    <?= $lesson['sort_order'] ?: $i+1 ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-800"><?= e($lesson['title']) ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <?= $lesson['topic_count'] ?> topics · <?= $lesson['material_count'] ?> materials
                            </p>
                        </div>
                        <span class="text-lg flex-shrink-0"><?= $statusIcon ?></span>
                    </div>
                    <?php if ($lesson['description']): ?>
                    <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?= e($lesson['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="<?= BASE_URL ?>/modules/student/lessons.php?id=<?= $lesson['id'] ?>"
                           class="inline-block px-4 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition">
                            <?= $lesson['progress_status']==='completed' ? 'Review' : ($lesson['progress_status']==='in_progress' ? 'Continue' : 'Start') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($lessons)): ?>
        <div class="bg-white rounded-2xl p-10 text-center border border-gray-100">
            <p class="text-gray-400">No lessons published yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Quizzes + Announcements -->
    <div class="space-y-5">
        <!-- Quizzes -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Quizzes</h3>
            <div class="space-y-3">
            <?php foreach ($quizzes as $q):
                $taken = $q['attempt_id'] !== null;
                $over  = $q['deadline'] && strtotime($q['deadline']) < time();
                $limit = $q['attempt_count'] >= $q['max_attempts'];
            ?>
            <div class="border border-gray-100 rounded-xl p-3">
                <p class="text-sm font-medium text-gray-800"><?= e($q['title']) ?></p>
                <p class="text-xs text-gray-500 mt-0.5">
                    <?= $q['question_count'] ?> questions
                    <?php if ($q['time_limit']): ?> · <?= $q['time_limit'] ?> min<?php endif; ?>
                    <?php if ($taken): ?> · <span class="<?= masteryColor((int)$q['last_score']) ?> font-semibold">Best: <?= $q['last_score'] ?>%</span><?php endif; ?>
                </p>
                <?php if ($q['deadline']): ?>
                <p class="text-xs <?= $over?'text-red-500':'text-amber-500' ?> mt-0.5">
                    <?= $over?'Deadline passed:':'Due:' ?> <?= formatDate($q['deadline'],'M d, Y') ?>
                </p>
                <?php endif; ?>
                <div class="mt-2">
                    <?php if ($over || $limit): ?>
                    <span class="text-xs text-gray-400"><?= $over?'Expired':'Max attempts reached' ?></span>
                    <?php elseif ($taken): ?>
                    <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $q['id'] ?>"
                       class="text-xs px-3 py-1 bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100">Retake</a>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/modules/student/take_quiz.php?quiz_id=<?= $q['id'] ?>"
                       class="text-xs px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Take Quiz</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
            <p class="text-xs text-gray-400 text-center py-3">No quizzes yet.</p>
            <?php endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <?php if (!empty($announcements)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Announcements</h3>
            <div class="space-y-3">
            <?php foreach ($announcements as $a): ?>
            <div class="bg-blue-50 rounded-xl p-3">
                <p class="text-sm font-medium text-blue-800"><?= e($a['title']) ?></p>
                <p class="text-xs text-blue-600 mt-0.5 line-clamp-2"><?= e($a['content']) ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= timeAgo($a['created_at']) ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
