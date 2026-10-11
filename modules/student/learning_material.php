<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];
$mid     = getInt('id');
if (!$mid) redirect(BASE_URL.'/modules/student/courses.php');

$material = Database::fetchOne(
    "SELECT m.*, l.title lesson_title, l.id lesson_id, l.course_id, c.title course_title
     FROM learning_materials m JOIN lessons l ON l.id=m.lesson_id JOIN courses c ON c.id=l.course_id
     WHERE m.id=?",[$mid]
);
if (!$material) { flashSet('error','Material not found.'); redirect(BASE_URL.'/modules/student/courses.php'); }

$enrolled = Database::fetchOne("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$sid,$material['course_id']]);
if (!$enrolled) { flashSet('error','Not enrolled.'); redirect(BASE_URL.'/modules/student/courses.php'); }

$pageTitle = $material['title'];
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

<div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="<?= BASE_URL ?>/modules/student/lessons.php?id=<?= $material['lesson_id'] ?>" class="hover:text-blue-600">← <?= e($material['lesson_title']) ?></a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl">
    <div class="flex items-start gap-4 mb-6">
        <div class="text-4xl"><?= $typeIcons[$material['type']] ?? '📁' ?></div>
        <div>
            <h2 class="text-xl font-bold text-gray-800"><?= e($material['title']) ?></h2>
            <p class="text-sm text-gray-500"><?= e($material['course_title']) ?> › <?= e($material['lesson_title']) ?></p>
        </div>
    </div>

    <?php if ($material['type'] === 'text' && $material['content']): ?>
    <div class="prose max-w-none text-sm text-gray-700 leading-relaxed">
        <?= nl2br(e($material['content'])) ?>
    </div>

    <?php elseif ($material['type'] === 'text' && $material['file_path']): ?>
    <?php
        $txtPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $material['file_path']);
        $txtContent = file_exists($txtPath) ? file_get_contents($txtPath) : null;
    ?>
    <?php if ($txtContent !== null): ?>
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
        <pre class="text-sm text-gray-800 whitespace-pre-wrap font-mono leading-relaxed overflow-auto max-h-[600px]"><?= e($txtContent) ?></pre>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-sm">Could not load file content.</p>
    <?php endif; ?>

    <?php elseif ($material['file_path'] && $material['type'] !== 'text'): ?>
    <?php $fileUrl = fileUrl($material['file_path']); ?>
    <?php if ($material['type'] === 'pdf'): ?>
    <div class="rounded-xl overflow-hidden border border-gray-200">
        <iframe src="<?= e($fileUrl) ?>" class="w-full h-[600px]" title="<?= e($material['title']) ?>"></iframe>
    </div>
    <?php elseif ($material['type'] === 'video'): ?>
    <video controls class="w-full rounded-xl">
        <source src="<?= e($fileUrl) ?>" type="video/mp4">
        Your browser does not support video.
    </video>
    <?php elseif (in_array($material['type'], ['pptx','ppt'])): ?>
    <!-- Embed PPTX inline — browser/OS will render it -->
    <div class="rounded-xl overflow-hidden border border-gray-200 bg-white">
        <iframe src="<?= e($fileUrl) ?>" class="w-full h-[600px]" title="<?= e($material['title']) ?>">
            <p class="text-gray-500 text-sm p-4">Your browser cannot display this file inline.</p>
        </iframe>
    </div>
    <div class="mt-3 text-center">
        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"
           class="inline-block px-5 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 transition">
            Open in New Tab
        </a>
    </div>
    <?php elseif (in_array(strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])): ?>
    <div class="text-center">
        <img src="<?= e($fileUrl) ?>" alt="<?= e($material['title']) ?>" class="max-w-full rounded-xl mx-auto border border-gray-200">
    </div>
    <?php else: ?>
    <!-- Generic: show in iframe, fallback to open in new tab -->
    <div class="rounded-xl overflow-hidden border border-gray-200 bg-white">
        <iframe src="<?= e($fileUrl) ?>" class="w-full h-[600px]" title="<?= e($material['title']) ?>">
        </iframe>
    </div>
    <div class="mt-3 text-center">
        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"
           class="inline-block px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">
            Open in New Tab
        </a>
    </div>
    <?php endif; ?>

    <?php elseif ($material['external_url']): ?>
    <div class="text-center py-10">
        <p class="text-gray-500 mb-4">This material is hosted externally.</p>
        <a href="<?= e($material['external_url']) ?>" target="_blank" rel="noopener"
           class="inline-block px-6 py-3 bg-blue-600 text-white font-medium text-sm rounded-xl hover:bg-blue-700 transition">
            🔗 Open Resource
        </a>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
