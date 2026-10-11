<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Upload Material';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$lessonId = getInt('lesson_id');
if (!$lessonId) redirect(BASE_URL.'/modules/teacher/courses.php');

$lesson = Database::fetchOne(
    "SELECT l.*, c.title course_title, c.id course_id FROM lessons l JOIN courses c ON c.id=l.course_id WHERE l.id=? AND c.teacher_id=?",
    [$lessonId,$tid]
);
if (!$lesson) { flashSet('error','Lesson not found.'); redirect(BASE_URL.'/modules/teacher/courses.php'); }

$topics = Database::fetchAll("SELECT id,title FROM topics WHERE lesson_id=? ORDER BY sort_order",[$lessonId]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'delete') {
        $mid = postInt('material_id');
        $mat = Database::fetchOne("SELECT * FROM learning_materials WHERE id=?",[$mid]);
        if ($mat && $mat['file_path'] && file_exists(PUBLIC_PATH.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$mat['file_path']))) {
            @unlink(PUBLIC_PATH.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$mat['file_path']));
        }
        Database::delete('learning_materials','id=?',[$mid]);
        flashSet('success','Material deleted.');
        redirect($_SERVER['PHP_SELF'].'?lesson_id='.$lessonId);
    }

    $title    = postStr('title');
    $type     = postStr('type');
    $topicId  = postInt('topic_id') ?: null;
    $content  = $_POST['content']      ?? '';
    $extUrl   = postStr('external_url');
    $sortOrd  = postInt('sort_order');
    $download = postInt('is_downloadable') ? 1 : 0;

    if (empty($title)) $errors[] = 'Title is required.';

    $filePath = null;
    $fileSize = null;

    if (!empty($_FILES['material_file']['name'])) {
        try {
            $filePath = handleUpload($_FILES['material_file'],'materials',ALLOWED_MATERIAL_TYPES);
            $fileSize = $_FILES['material_file']['size'];
            // Auto-detect type from mime
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file(PUBLIC_PATH.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$filePath));
            $type = match (true) {
                str_contains($mime,'pdf')        => 'pdf',
                str_contains($mime,'presentation') || str_contains($mime,'powerpoint') => 'pptx',
                str_contains($mime,'video')      => 'video',
                default                          => $type ?: 'other',
            };
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (empty($errors)) {
        $data = [
            'lesson_id'        => $lessonId,
            'topic_id'         => $topicId,
            'title'            => $title,
            'type'             => $type ?: 'text',
            'content'          => $content ?: null,
            'file_path'        => $filePath,
            'file_size'        => $fileSize,
            'external_url'     => $extUrl ?: null,
            'sort_order'       => $sortOrd,
            'is_downloadable'  => $download,
        ];
        Database::insert('learning_materials',$data);
        AuditLog::write(Auth::id(),'upload_material','materials',"$title in lesson $lessonId");
        flashSet('success','Material uploaded successfully.');
        redirect($_SERVER['PHP_SELF'].'?lesson_id='.$lessonId);
    }
}

$materials = Database::fetchAll(
    "SELECT m.*, t.title topic_title
     FROM learning_materials m LEFT JOIN topics t ON t.id=m.topic_id
     WHERE m.lesson_id=? ORDER BY m.sort_order, m.id",[$lessonId]
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

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="<?= BASE_URL ?>/modules/teacher/lessons.php?course_id=<?= $lesson['course_id'] ?>" class="hover:text-orange-600">Lessons</a>
    <span>›</span><span class="text-gray-800 font-medium"><?= e($lesson['title']) ?></span>
    <span>›</span><span class="text-gray-800">Materials</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Upload Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Upload / Add Material</h3>

        <?php if (!empty($errors)): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
            <?php foreach ($errors as $e): ?><p>• <?= e($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="materialType" onchange="toggleFields()"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="text">Text Lesson</option>
                        <option value="pdf">PDF</option>
                        <option value="pptx">PowerPoint</option>
                        <option value="video">Video</option>
                        <option value="link">External Link</option>
                        <option value="other">Other File</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                    <select name="topic_id" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="">-- No Topic --</option>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- File upload -->
            <div id="fileField">
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
                <input type="file" name="material_file" accept=".pdf,.pptx,.ppt,.mp4,.webm,.txt,.png,.jpg"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                <p class="text-xs text-gray-400 mt-1">Max <?= MAX_UPLOAD_BYTES/1024/1024 ?>MB. Supported: PDF, PPTX, MP4, images.</p>
            </div>

            <!-- External URL -->
            <div id="linkField" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">External URL</label>
                <input type="url" name="external_url" placeholder="https://…"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <!-- Text content -->
            <div id="textField">
                <label class="block text-sm font-medium text-gray-700 mb-1">Text Content</label>
                <textarea name="content" rows="5" placeholder="Write lesson content here…"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="0" min="0"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_downloadable" value="1" checked
                               class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-400">
                        <span class="text-sm text-gray-700">Downloadable</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 transition">
                Upload Material
            </button>
        </form>
    </div>

    <!-- Existing Materials -->
    <div>
        <h3 class="font-semibold text-gray-800 mb-4">Existing Materials (<?= count($materials) ?>)</h3>
        <div class="space-y-3">
            <?php foreach ($materials as $m):
                $typeIcons = ['pdf'=>'📄','pptx'=>'📊','video'=>'🎬','text'=>'📝','link'=>'🔗','other'=>'📁'];
                $icon = $typeIcons[$m['type']] ?? '📁';
            ?>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-start gap-3">
                <div class="text-2xl flex-shrink-0"><?= $icon ?></div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm text-gray-800 truncate"><?= e($m['title']) ?></p>
                    <p class="text-xs text-gray-500">
                        <?= strtoupper($m['type']) ?>
                        <?php if ($m['topic_title']): ?> · <?= e($m['topic_title']) ?><?php endif; ?>
                        <?php if ($m['file_size']): ?> · <?= round($m['file_size']/1024) ?> KB<?php endif; ?>
                    </p>
                    <?php if ($m['file_path']): ?>
                <a href="<?= fileUrl($m['file_path']) ?>" target="_blank"
                       class="text-xs text-orange-600 hover:underline mt-1 inline-block">View / Download</a>
                    <?php endif; ?>
                </div>
                <form method="POST" id="delMat-<?= $m['id'] ?>">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                    <button type="button" onclick="confirmDelete('Delete this material?','delMat-<?= $m['id'] ?>')"
                            class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (empty($materials)): ?>
            <div class="bg-white rounded-2xl p-8 text-center border border-gray-100">
                <p class="text-gray-400 text-sm">No materials uploaded yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function toggleFields() {
    const type = document.getElementById('materialType').value;
    document.getElementById('fileField').classList.toggle('hidden', type === 'link' || type === 'text');
    document.getElementById('linkField').classList.toggle('hidden', type !== 'link');
    document.getElementById('textField').classList.toggle('hidden', type !== 'text');
}
toggleFields();
</script>
</body>
</html>
