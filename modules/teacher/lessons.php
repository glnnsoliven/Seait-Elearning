<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Lessons';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');
if (!$courseId) redirect(BASE_URL.'/modules/teacher/courses.php');

$course = Database::fetchOne("SELECT * FROM courses WHERE id=? AND teacher_id=?",[$courseId,$tid]);
if (!$course) { flashSet('error','Course not found.'); redirect(BASE_URL.'/modules/teacher/courses.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'save_lesson') {
        $lid   = postInt('lesson_id');
        $title = postStr('title');
        $desc  = postStr('description');
        $sort  = postInt('sort_order');
        $pub   = postInt('is_published');

        if (empty($title)) { flashSet('error','Title required.'); redirect($_SERVER['PHP_SELF'].'?course_id='.$courseId); }

        if ($lid) {
            Database::update('lessons',['title'=>$title,'description'=>$desc,'sort_order'=>$sort,'is_published'=>$pub],'id=? AND course_id=?',[$lid,$courseId]);
            $newId = $lid;
            flashSet('success','Lesson updated.');
        } else {
            $newId = Database::insert('lessons',['course_id'=>$courseId,'title'=>$title,'description'=>$desc,'sort_order'=>$sort,'is_published'=>$pub]);
            if ($pub) Notification::broadcastToCourse($courseId,'new_lesson','New Lesson: '.$title,'A new lesson has been added.', BASE_URL.'/modules/student/lessons.php?id='.$newId);
            flashSet('success','Lesson created.');
        }

        // Handle optional file attachment
        if (!empty($_FILES['lesson_file']['name'])) {
            try {
                $filePath = handleUpload($_FILES['lesson_file'], 'materials', ALLOWED_MATERIAL_TYPES);
                $ext      = strtolower(pathinfo($_FILES['lesson_file']['name'], PATHINFO_EXTENSION));
                $typeMap  = ['pdf'=>'pdf','pptx'=>'pptx','ppt'=>'pptx','mp4'=>'video','webm'=>'video',
                             'txt'=>'text','doc'=>'other','docx'=>'other'];
                $matType  = $typeMap[$ext] ?? 'other';
                Database::insert('learning_materials', [
                    'lesson_id'       => $newId,
                    'title'           => pathinfo($_FILES['lesson_file']['name'], PATHINFO_FILENAME),
                    'type'            => $matType,
                    'file_path'       => $filePath,
                    'file_size'       => $_FILES['lesson_file']['size'],
                    'sort_order'      => 0,
                    'is_downloadable' => 1,
                ]);
            } catch (RuntimeException $e) {
                flashSet('error', 'Lesson saved but file upload failed: ' . $e->getMessage());
            }
        }

        AuditLog::write(Auth::id(),'save_lesson','lessons',"$title in course $courseId");
    } elseif ($action === 'save_topic') {
        $lid   = postInt('lesson_id');
        $topId = postInt('topic_id');
        $title = postStr('title');
        $desc  = postStr('description');
        $sort  = postInt('sort_order');
        if ($topId) {
            Database::update('topics',['title'=>$title,'description'=>$desc,'sort_order'=>$sort],'id=? AND lesson_id=?',[$topId,$lid]);
        } else {
            Database::insert('topics',['lesson_id'=>$lid,'title'=>$title,'description'=>$desc,'sort_order'=>$sort]);
        }
        flashSet('success','Topic saved.');
    } elseif ($action === 'delete_material') {
        $matId = postInt('material_id');
        $mat = Database::fetchOne(
            "SELECT m.file_path FROM learning_materials m
             JOIN lessons l ON l.id=m.lesson_id
             WHERE m.id=? AND l.course_id=?", [$matId, $courseId]
        );
        if ($mat) {
            if ($mat['file_path']) {
                $fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $mat['file_path']);
                if (file_exists($fullPath)) @unlink($fullPath);
            }
            Database::delete('learning_materials', 'id=?', [$matId]);
            flashSet('success', 'File removed.');
        }
    } elseif ($action === 'delete_lesson') {
        $lid = postInt('lesson_id');
        Database::delete('lessons','id=? AND course_id=?',[$lid,$courseId]);
        flashSet('success','Lesson deleted.');
    } elseif ($action === 'delete_topic') {
        $topId = postInt('topic_id');
        Database::delete('topics','id=?',[$topId]);
        flashSet('success','Topic deleted.');
    }
    redirect($_SERVER['PHP_SELF'].'?course_id='.$courseId);
}

$lessons = Database::fetchAll(
    "SELECT l.*, COUNT(DISTINCT t.id) topic_count, COUNT(DISTINCT m.id) material_count
     FROM lessons l
     LEFT JOIN topics t ON t.lesson_id=l.id
     LEFT JOIN learning_materials m ON m.lesson_id=l.id
     WHERE l.course_id=?
     GROUP BY l.id ORDER BY l.sort_order, l.id",[$courseId]
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
    <a href="<?= BASE_URL ?>/modules/teacher/courses.php" class="hover:text-orange-600">Courses</a>
    <span>›</span>
    <span class="text-gray-800 font-medium"><?= e($course['title']) ?></span>
    <span>›</span>
    <span class="text-gray-800">Lessons</span>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Lessons</h2>
        <p class="text-sm text-gray-500"><?= count($lessons) ?> lessons in <?= e($course['code']) ?></p>
    </div>
    <button onclick="openLessonModal()" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Lesson
    </button>
</div>

<!-- Lessons Accordion -->
<div class="space-y-3">
<?php foreach ($lessons as $i => $lesson):
    $topics = Database::fetchAll("SELECT * FROM topics WHERE lesson_id=? ORDER BY sort_order",[$lesson['id']]);
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Lesson Header -->
    <div class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition"
         onclick="toggleLesson('lesson-<?= $lesson['id'] ?>')">
        <div class="flex items-center gap-3">
            <span class="w-7 h-7 bg-orange-100 rounded-full flex items-center justify-center text-xs font-bold text-orange-700">
                <?= $lesson['sort_order'] ?: $i+1 ?>
            </span>
            <div>
                <p class="font-semibold text-gray-800"><?= e($lesson['title']) ?></p>
                <p class="text-xs text-gray-500">
                    <?= $lesson['topic_count'] ?> topics · <?= $lesson['material_count'] ?> materials
                    <span class="ml-2 px-1.5 py-0.5 rounded text-xs <?= $lesson['is_published']?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
                        <?= $lesson['is_published']?'Published':'Draft' ?>
                    </span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="event.stopPropagation(); openLessonModal(<?= htmlspecialchars(json_encode($lesson)) ?>)"
                    class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <a href="<?= BASE_URL ?>/modules/teacher/upload_material.php?lesson_id=<?= $lesson['id'] ?>"
               onclick="event.stopPropagation()"
               class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition" title="Add material">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
            </a>
            <form method="POST" id="delLesson-<?= $lesson['id'] ?>" onclick="event.stopPropagation()">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_lesson">
                <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                <button type="button" onclick="event.stopPropagation(); confirmDelete('Delete this lesson and all its content?','delLesson-<?= $lesson['id'] ?>')"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
            <svg id="chevron-lesson-<?= $lesson['id'] ?>" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>

    <!-- Expandable Topics + Materials -->
    <div id="lesson-<?= $lesson['id'] ?>" class="hidden border-t border-gray-100 bg-gray-50 px-5 py-4">

        <!-- Attached Files -->
        <?php
        $lessonMaterials = Database::fetchAll(
            "SELECT * FROM learning_materials WHERE lesson_id=? ORDER BY sort_order, id",
            [$lesson['id']]
        );
        $matIcons = ['pdf'=>'📄','pptx'=>'📊','ppt'=>'📊','video'=>'🎬','text'=>'📝','link'=>'🔗','other'=>'📁'];
        if (!empty($lessonMaterials)): ?>
        <div class="mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Attached Files</p>
            <div class="space-y-1.5">
            <?php foreach ($lessonMaterials as $mat): ?>
            <div class="flex items-center justify-between bg-white rounded-xl px-4 py-2.5 border border-gray-100">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="text-lg flex-shrink-0"><?= $matIcons[$mat['type']] ?? '📁' ?></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-700 truncate"><?= e($mat['title']) ?></p>
                        <p class="text-xs text-gray-400">
                            <?= strtoupper($mat['type']) ?>
                            <?php if ($mat['file_size']): ?> · <?= $mat['file_size'] >= 1048576 ? round($mat['file_size']/1048576,1).' MB' : round($mat['file_size']/1024).' KB' ?><?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                    <?php if ($mat['file_path']): ?>
                    <a href="<?= fileUrl($mat['file_path']) ?>" target="_blank"
                       class="text-xs px-2.5 py-1 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition font-medium">
                        View
                    </a>
                    <a href="<?= fileUrl($mat['file_path']) ?>" download
                       class="text-xs px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
                        Download
                    </a>
                    <?php endif; ?>
                    <?php if ($mat['external_url']): ?>
                    <a href="<?= e($mat['external_url']) ?>" target="_blank"
                       class="text-xs px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition font-medium">
                        Open
                    </a>
                    <?php endif; ?>
                    <!-- Delete material -->
                    <form method="POST" class="inline" onsubmit="return confirm('Remove this file?')">
                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete_material">
                        <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <button type="submit" class="p-1 text-gray-300 hover:text-red-500 transition" title="Remove file">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Topics -->
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Topics</p>
            <button onclick="openTopicModal(<?= $lesson['id'] ?>)"
                    class="text-xs px-3 py-1 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition">
                + Add Topic
            </button>
        </div>
        <?php if (empty($topics)): ?>
        <p class="text-xs text-gray-400 py-2">No topics yet.</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($topics as $t): ?>
            <div class="flex items-center justify-between bg-white rounded-xl px-4 py-2.5 border border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700"><?= e($t['title']) ?></p>
                    <?php if ($t['description']): ?>
                    <p class="text-xs text-gray-400"><?= e($t['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-1">
                    <button onclick="openTopicModal(<?= $lesson['id'] ?>, <?= htmlspecialchars(json_encode($t)) ?>)"
                            class="p-1 text-gray-400 hover:text-orange-600 rounded">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <form method="POST" id="delTopic-<?= $t['id'] ?>" class="inline">
                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete_topic">
                        <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <button type="button" onclick="confirmDelete('Delete this topic?','delTopic-<?= $t['id'] ?>')"
                                class="p-1 text-gray-400 hover:text-red-600 rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($lessons)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400 mb-4">No lessons yet. Add your first lesson!</p>
    <button onclick="openLessonModal()" class="px-6 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Add Lesson</button>
</div>
<?php endif; ?>
</div>

</main>
</div>

<!-- Lesson Modal -->
<div id="lessonModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="lessonModalTitle" class="font-semibold text-gray-800">Add Lesson</h3>
            <button onclick="closeModal('lessonModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save_lesson">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="lesson_id" id="lessonId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" id="lessonTitle" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="lessonDesc" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>
            <!-- File attachment -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Attach File <span class="text-gray-400 font-normal">(PDF, PPTX, MP4, etc.)</span></label>
                <div class="flex items-center gap-2">
                    <label class="flex-1 flex items-center gap-2 px-3 py-2 rounded-xl border border-dashed border-gray-300 cursor-pointer hover:border-violet-400 hover:bg-orange-50 transition">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span id="fileLabel" class="text-xs text-gray-500">Click to choose a file</span>
                        <input type="file" name="lesson_file" id="lessonFile" class="hidden"
                               accept=".pdf,.ppt,.pptx,.mp4,.webm,.txt,.png,.jpg,.jpeg,.doc,.docx"
                               onchange="document.getElementById('fileLabel').textContent = this.files[0]?.name || 'Click to choose a file'">
                    </label>
                    <button type="button" onclick="document.getElementById('lessonFile').value=''; document.getElementById('fileLabel').textContent='Click to choose a file'"
                            class="p-2 text-gray-400 hover:text-red-500 transition" title="Clear file">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Max 50 MB. Supported: PDF, PPTX, MP4, WEBM, TXT, DOCX, images.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="lessonSort" value="0" min="0"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="is_published" id="lessonPub"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="0">Draft</option>
                        <option value="1">Published</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Save Lesson</button>
                <button type="button" onclick="closeModal('lessonModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Topic Modal -->
<div id="topicModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="topicModalTitle" class="font-semibold text-gray-800">Add Topic</h3>
            <button onclick="closeModal('topicModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save_topic">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="lesson_id" id="topicLessonId" value="">
            <input type="hidden" name="topic_id"  id="topicId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" id="topicTitle" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="topicDesc" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>
            <input type="number" name="sort_order" id="topicSort" value="0" class="hidden">
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Save Topic</button>
                <button type="button" onclick="closeModal('topicModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function toggleLesson(id) {
    const el  = document.getElementById(id);
    const chv = document.getElementById('chevron-' + id);
    el.classList.toggle('hidden');
    chv?.classList.toggle('rotate-180');
}
function openLessonModal(lesson = null) {
    document.getElementById('lessonModalTitle').textContent = lesson ? 'Edit Lesson' : 'Add Lesson';
    document.getElementById('lessonId').value    = lesson?.id    ?? '';
    document.getElementById('lessonTitle').value = lesson?.title ?? '';
    document.getElementById('lessonDesc').value  = lesson?.description ?? '';
    document.getElementById('lessonSort').value  = lesson?.sort_order  ?? 0;
    document.getElementById('lessonPub').value   = lesson?.is_published ?? 0;
    openModal('lessonModal');
}
function openTopicModal(lessonId, topic = null) {
    document.getElementById('topicModalTitle').textContent = topic ? 'Edit Topic' : 'Add Topic';
    document.getElementById('topicLessonId').value = lessonId;
    document.getElementById('topicId').value    = topic?.id    ?? '';
    document.getElementById('topicTitle').value = topic?.title ?? '';
    document.getElementById('topicDesc').value  = topic?.description ?? '';
    document.getElementById('topicSort').value  = topic?.sort_order  ?? 0;
    openModal('topicModal');
}
</script>
</body>
</html>
