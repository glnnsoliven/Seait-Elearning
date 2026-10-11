<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'My Courses';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'toggle_publish') {
        $cid = postInt('course_id');
        $row = Database::fetchOne("SELECT is_published FROM courses WHERE id=? AND teacher_id=?",[$cid,$tid]);
        if ($row) {
            $new = $row['is_published'] ? 0 : 1;
            Database::update('courses',['is_published'=>$new],'id=?',[$cid]);
            if ($new) {
                Notification::broadcastToCourse($cid,'new_lesson',
                    'Course Published',
                    'A new course is now available for you.',
                    BASE_URL.'/modules/student/course_view.php?id='.$cid
                );
            }
            AuditLog::write(Auth::id(),'toggle_course_publish','courses',"Course ID $cid");
            flashSet('success','Course publication status updated.');
        }
    } elseif ($action === 'delete') {
        $cid = postInt('course_id');
        Database::update('courses',['is_active'=>0,'is_published'=>0],'id=? AND teacher_id=?',[$cid,$tid]);
        flashSet('success','Course removed.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$search  = getStr('search');
$where   = "WHERE c.teacher_id=? AND c.is_active=1";
$params  = [$tid];
if ($search) { $where .= " AND (c.title LIKE ? OR c.code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$courses = Database::fetchAll(
    "SELECT c.*, d.name dept_name,
            COUNT(DISTINCT e.student_id) enrolled,
            COUNT(DISTINCT l.id) lessons,
            COUNT(DISTINCT q.id) quizzes
     FROM courses c
     LEFT JOIN departments d ON d.id=c.department_id
     LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
     LEFT JOIN lessons l ON l.course_id=c.id
     LEFT JOIN quizzes q ON q.course_id=c.id
     {$where}
     GROUP BY c.id ORDER BY c.created_at DESC",$params
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">My Subjects</h2>
        <p class="text-sm text-gray-500"><?= count($courses) ?> subjects assigned to you</p>
    </div>
    <div class="flex items-center gap-2 px-4 py-2 bg-orange-50 border border-orange-200 rounded-xl text-sm text-orange-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Subjects are assigned by the Administrator
    </div>
</div>

<!-- Search -->
<form method="GET" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5 flex gap-3">
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search courses…"
           class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
    <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Search</button>
</form>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($courses as $c): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
    <!-- Course Header -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 px-5 py-4">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-white font-semibold text-sm leading-tight"><?= e($c['title']) ?></p>
                <p class="text-orange-200 text-xs mt-0.5"><?= e($c['code']) ?></p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $c['is_published']?'bg-green-400/30 text-white':'bg-white/20 text-white' ?>">
                <?= $c['is_published']?'Published':'Draft' ?>
            </span>
        </div>
    </div>

    <div class="p-5 flex-1 flex flex-col">
        <!-- Block + Year badges -->
        <?php if ($c['year_level'] || $c['block']): ?>
        <div class="flex flex-wrap gap-1.5 mb-2">
            <?php if ($c['year_level']): ?>
            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-100">
                <?= match((int)$c['year_level']) {1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year',default=>$c['year_level'].'th Year'} ?>
            </span>
            <?php endif; ?>
            <?php if ($c['block']): ?>
            <span class="px-2 py-0.5 bg-orange-50 text-orange-700 text-xs font-semibold rounded-full border border-orange-100">
                Block <?= e($c['block']) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <p class="text-xs text-gray-500 mb-3 line-clamp-2"><?= e($c['description'] ?? 'No description') ?></p>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-2 mb-4">
            <?php foreach ([[$c['enrolled'],'Students'],[$c['lessons'],'Lessons'],[$c['quizzes'],'Quizzes']] as [$v,$l]): ?>
            <div class="text-center bg-gray-50 rounded-xl py-2">
                <p class="text-lg font-bold text-gray-800"><?= $v ?></p>
                <p class="text-xs text-gray-500"><?= $l ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-auto space-y-2">
            <!-- Primary actions -->
            <div class="grid grid-cols-3 gap-2">
                <a href="<?= BASE_URL ?>/modules/teacher/sections.php?course_id=<?= $c['id'] ?>"
                   class="text-center py-1.5 text-xs bg-orange-50 text-orange-700 rounded-xl hover:bg-orange-100 transition font-medium">
                    🏫 Blocks
                </a>
                <a href="<?= BASE_URL ?>/modules/teacher/lessons.php?course_id=<?= $c['id'] ?>"
                   class="text-center py-1.5 text-xs bg-blue-50 text-blue-700 rounded-xl hover:bg-blue-100 transition">
                    Lessons
                </a>
                <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php?course_id=<?= $c['id'] ?>"
                   class="text-center py-1.5 text-xs bg-orange-50 text-orange-700 rounded-xl hover:bg-orange-100 transition">
                    Quizzes
                </a>
            </div>
            <!-- Edit subject -->
            <a href="<?= BASE_URL ?>/modules/teacher/create_course.php?id=<?= $c['id'] ?>"
               class="block text-center py-1.5 text-xs bg-gray-50 text-gray-600 rounded-xl hover:bg-gray-100 transition border border-gray-200">
                Edit Subject
            </a>
            <!-- Toggle publish -->
            <form method="POST" class="inline w-full">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                <button type="submit"
                        class="w-full py-1.5 text-xs rounded-xl transition
                               <?= $c['is_published']?'bg-yellow-50 text-yellow-700 hover:bg-yellow-100':'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                    <?= $c['is_published']?'Unpublish':'Publish' ?>
                </button>
            </form>
            <!-- Delete subject -->
            <form method="POST" id="delCourse-<?= $c['id'] ?>">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                <button type="button"
                        onclick="confirmDelete('Delete subject &quot;<?= e($c['title']) ?>&quot;? This cannot be undone.','delCourse-<?= $c['id'] ?>')"
                        class="w-full py-1.5 text-xs rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition">
                    🗑 Delete Subject
                </button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($courses)): ?>
<div class="col-span-full bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400 mb-4">No subjects yet. Create your first subject!</p>
    <a href="<?= BASE_URL ?>/modules/teacher/create_course.php"
       class="px-6 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">
        Add Subject
    </a>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
