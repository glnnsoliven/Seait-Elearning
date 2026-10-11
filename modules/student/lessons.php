<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];
$lid     = getInt('id');
if (!$lid) redirect(BASE_URL.'/modules/student/courses.php');

$lesson = Database::fetchOne(
    "SELECT l.*, c.id course_id, c.title course_title, c.code course_code
     FROM lessons l JOIN courses c ON c.id=l.course_id
     WHERE l.id=? AND l.is_published=1",[$lid]
);
if (!$lesson) { flashSet('error','Lesson not found.'); redirect(BASE_URL.'/modules/student/courses.php'); }

// Verify enrollment
$enrolled = Database::fetchOne("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$sid,$lesson['course_id']]);
if (!$enrolled) { flashSet('error','Not enrolled in this course.'); redirect(BASE_URL.'/modules/student/courses.php'); }

$pageTitle = $lesson['title'];

// Topics with materials
$topics    = Database::fetchAll("SELECT * FROM topics WHERE lesson_id=? ORDER BY sort_order",[$lid]);
$materials = Database::fetchAll("SELECT * FROM learning_materials WHERE lesson_id=? ORDER BY sort_order, id",[$lid]);

// Group materials by topic
$byTopic = [];
foreach ($materials as $m) {
    $key = $m['topic_id'] ?? 'general';
    $byTopic[$key][] = $m;
}

// Mark lesson as in_progress (or completed if revisiting)
$progress = Database::fetchOne("SELECT * FROM student_progress WHERE student_id=? AND course_id=? AND lesson_id=?",[$sid,$lesson['course_id'],$lid]);
if (!$progress) {
    Database::insert('student_progress',[
        'student_id'  => $sid, 'course_id' => $lesson['course_id'], 'lesson_id' => $lid,
        'status'      => 'in_progress', 'completion_pct' => 0,
        'last_accessed' => date('Y-m-d H:i:s'),
    ]);
} else {
    Database::update('student_progress',['last_accessed'=>date('Y-m-d H:i:s')],'id=?',[$progress['id']]);
}

// Sibling lessons for prev/next navigation
$siblings = Database::fetchAll("SELECT id,title FROM lessons WHERE course_id=? AND is_published=1 ORDER BY sort_order",[$lesson['course_id']]);
$sibIdx   = array_search($lid, array_column($siblings,'id'));
$prevLesson = $sibIdx > 0                    ? $siblings[$sibIdx-1] : null;
$nextLesson = $sibIdx < count($siblings)-1   ? $siblings[$sibIdx+1] : null;

$typeIcons = ['pdf'=>'📄','pptx'=>'📊','video'=>'🎬','text'=>'📝','link'=>'🔗','other'=>'📁'];
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-4 flex-wrap">
    <a href="<?= BASE_URL ?>/modules/student/courses.php" class="hover:text-blue-600">Courses</a>
    <span>›</span>
    <a href="<?= BASE_URL ?>/modules/student/course_view.php?id=<?= $lesson['course_id'] ?>" class="hover:text-blue-600"><?= e($lesson['course_title']) ?></a>
    <span>›</span>
    <span class="text-gray-800 font-medium"><?= e($lesson['title']) ?></span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <h2 class="text-2xl font-bold text-gray-800 mb-1"><?= e($lesson['title']) ?></h2>
            <?php if ($lesson['description']): ?>
            <p class="text-gray-500 text-sm mb-4"><?= e($lesson['description']) ?></p>
            <?php endif; ?>

            <!-- Topics & Materials -->
            <?php if (!empty($topics)): ?>
                <?php foreach ($topics as $t): ?>
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 text-base mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs text-blue-700 font-bold"><?= $t['sort_order'] ?></span>
                        <?= e($t['title']) ?>
                    </h3>
                    <?php if (!empty($byTopic[$t['id']])): ?>
                    <div class="space-y-3 ml-4">
                        <?php foreach ($byTopic[$t['id']] as $m): ?>
                        <?php include __DIR__ . '/partials/material_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Uncategorised materials -->
            <?php if (!empty($byTopic['general'])): ?>
            <div class="space-y-3">
                <?php foreach ($byTopic['general'] as $m): ?>
                <?php include __DIR__ . '/partials/material_card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($materials)): ?>
            <p class="text-gray-400 text-center py-8">No materials added yet.</p>
            <?php endif; ?>
        </div>

        <!-- Mark Complete -->
        <?php if (($progress['status'] ?? '') !== 'completed'): ?>
        <form method="POST" action="<?= BASE_URL ?>/modules/api/mark_lesson_complete.php">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
            <input type="hidden" name="course_id" value="<?= $lesson['course_id'] ?>">
            <button type="submit"
                    class="w-full py-3 bg-gradient-to-r from-blue-600 to-orange-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-orange-600 transition shadow-md">
                ✓ Mark Lesson as Complete
            </button>
        </form>
        <?php else: ?>
        <div class="w-full py-3 bg-green-100 text-green-700 font-semibold rounded-xl text-center">
            ✅ Lesson Completed!
        </div>
        <?php endif; ?>

        <!-- Prev / Next Navigation -->
        <div class="flex items-center justify-between mt-4">
            <?php if ($prevLesson): ?>
            <a href="?id=<?= $prevLesson['id'] ?>"
               class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 text-sm">
                ← <?= e(mb_strimwidth($prevLesson['title'],0,30,'…')) ?>
            </a>
            <?php else: ?><div></div><?php endif; ?>

            <?php if ($nextLesson): ?>
            <a href="?id=<?= $nextLesson['id'] ?>"
               class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm ml-auto">
                <?= e(mb_strimwidth($nextLesson['title'],0,30,'…')) ?> →
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Sidebar outline -->
    <div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sticky top-20">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">All Lessons</p>
            <div class="space-y-1">
                <?php foreach ($siblings as $sib):
                    $sibProg = Database::fetchOne("SELECT status FROM student_progress WHERE student_id=? AND lesson_id=?",[$sid,$sib['id']]);
                    $icon = $sibProg ? ($sibProg['status']==='completed' ? '✅' : '🔄') : '⭕';
                ?>
                <a href="?id=<?= $sib['id'] ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition
                          <?= $sib['id']==$lid ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <span class="text-xs"><?= $icon ?></span>
                    <span class="truncate"><?= e($sib['title']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
