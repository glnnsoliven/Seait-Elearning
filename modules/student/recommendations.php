<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student   = getStudentProfile(Auth::id());
$sid       = (int)$student['id'];
$pageTitle = 'Recommendations';

// Handle dismiss
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    if (postStr('action') === 'dismiss') {
        Recommendation::dismiss(postInt('rec_id'), $sid);
    } elseif (postStr('action') === 'refresh') {
        Recommendation::generate($sid);
        flashSet('success','Recommendations refreshed!');
    }
    redirect($_SERVER['PHP_SELF']);
}

// Regenerate if empty
$recs = Recommendation::get($sid, 20);
if (empty($recs)) {
    Recommendation::generate($sid);
    $recs = Recommendation::get($sid, 20);
}

$typeLabels = ['lesson'=>'Lesson','topic'=>'Topic Review','quiz'=>'Practice Quiz','activity'=>'Activity'];
$typeIcons  = ['lesson'=>'📖','topic'=>'🎯','quiz'=>'✏️','activity'=>'🏃'];
$typeBg     = ['lesson'=>'bg-blue-50 border-blue-200','topic'=>'bg-amber-50 border-amber-200','quiz'=>'bg-orange-50 border-orange-200','activity'=>'bg-green-50 border-green-200'];
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Personalized Recommendations</h2>
        <p class="text-sm text-gray-500">Based on your quiz performance and learning progress</p>
    </div>
    <form method="POST">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="refresh">
        <button type="submit" class="px-4 py-2 bg-blue-50 text-blue-600 text-sm font-medium rounded-xl hover:bg-blue-100 transition flex items-center gap-2">
            🔄 Refresh
        </button>
    </form>
</div>

<!-- Priority filter -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
<?php foreach ($recs as $r):
    $bg   = $typeBg[$r['type']]    ?? 'bg-gray-50 border-gray-200';
    $icon = $typeIcons[$r['type']] ?? '💡';
    $label= $typeLabels[$r['type']]?? ucfirst($r['type']);
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col">
    <div class="flex items-start justify-between gap-2 mb-3">
        <div class="flex items-center gap-3">
            <div class="text-3xl"><?= $icon ?></div>
            <div>
                <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700"><?= $label ?></span>
                <?php if ($r['priority'] >= 9): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700 ml-1">High Priority</span>
                <?php endif; ?>
            </div>
        </div>
        <form method="POST" class="flex-shrink-0">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="dismiss">
            <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
            <button type="submit" class="p-1 text-gray-300 hover:text-gray-500 text-xs" title="Dismiss">✕</button>
        </form>
    </div>

    <h3 class="font-semibold text-gray-800 mb-1"><?= e($r['title']) ?></h3>
    <p class="text-xs text-gray-500 flex-1 line-clamp-3"><?= e($r['reason']) ?></p>

    <div class="mt-4">
        <?php if ($r['type'] === 'quiz'): ?>
        <a href="<?= BASE_URL ?>/modules/student/quiz_list.php"
           class="block text-center py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 transition">
            Take Practice Quiz
        </a>
        <?php elseif ($r['type'] === 'lesson'): ?>
        <a href="<?= BASE_URL ?>/modules/student/lessons.php?id=<?= $r['reference_id'] ?>"
           class="block text-center py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">
            Start Lesson
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/modules/student/courses.php"
           class="block text-center py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition">
            View Courses
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($recs)): ?>
<div class="col-span-full bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-4">✅</div>
    <p class="text-gray-500 font-medium">Great job! No recommendations right now.</p>
    <p class="text-gray-400 text-sm mt-1">Complete quizzes to generate personalized suggestions.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
