<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Learning Materials';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');
$courses  = Database::fetchAll(
    "SELECT id, title, code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY code",
    [$tid]
);

// ── POST: Upload or Delete ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    // Delete material
    if ($action === 'delete') {
        $mid = postInt('material_id');
        $mat = Database::fetchOne(
            "SELECT m.* FROM learning_materials m
             JOIN lessons l ON l.id = m.lesson_id
             JOIN courses c ON c.id = l.course_id
             WHERE m.id=? AND c.teacher_id=?", [$mid, $tid]
        );
        if ($mat) {
            if ($mat['file_path']) {
                $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $mat['file_path']);
                if (file_exists($full)) @unlink($full);
            }
            Database::delete('learning_materials', 'id=?', [$mid]);
            flashSet('success', 'Material deleted.');
        }
        $redir = $_SERVER['PHP_SELF'] . ($courseId ? "?course_id=$courseId" : '');
        redirect($redir);
    }

    // Create lesson inline from materials page
    if ($action === 'create_lesson') {
        $cid       = postInt('new_lesson_course_id');
        $lesTitle  = postStr('new_lesson_title');
        $errors    = [];

        // Verify course belongs to teacher
        $course = $cid ? Database::fetchOne(
            "SELECT id FROM courses WHERE id=? AND teacher_id=? AND is_active=1", [$cid, $tid]
        ) : null;

        if (!$course)              $errors[] = 'Invalid subject.';
        if (empty($lesTitle))      $errors[] = 'Lesson title is required.';

        if (empty($errors)) {
            $maxSort = Database::fetchOne(
                "SELECT COALESCE(MAX(sort_order),0) m FROM lessons WHERE course_id=?", [$cid]
            )['m'];
            $newLid = Database::insert('lessons', [
                'course_id'    => $cid,
                'title'        => $lesTitle,
                'description'  => '',
                'sort_order'   => (int)$maxSort + 1,
                'is_published' => 0,
            ]);
            AuditLog::write(Auth::id(), 'create_lesson_inline', 'lessons',
                "Created lesson [{$lesTitle}] in course {$cid} from materials page");
            // Return JSON for AJAX
            jsonResponse(['success' => true, 'lesson_id' => $newLid, 'title' => $lesTitle]);
        }
        jsonResponse(['success' => false, 'error' => implode(', ', $errors)], 422);
    }

    // Upload material
    if ($action === 'upload') {
        $lessonId  = postInt('lesson_id');
        $title     = postStr('title');
        $type      = postStr('type');
        $topicId   = postInt('topic_id') ?: null;
        $content   = $_POST['content']   ?? '';
        $extUrl    = postStr('external_url');
        $sortOrd   = postInt('sort_order');
        $download  = postInt('is_downloadable') ? 1 : 0;
        $errors    = [];

        if (!$lessonId) $errors[] = 'Please select a lesson.';
        if (empty($title)) $errors[] = 'Title is required.';

        // Verify lesson belongs to teacher
        $lesson = $lessonId ? Database::fetchOne(
            "SELECT l.id FROM lessons l JOIN courses c ON c.id=l.course_id WHERE l.id=? AND c.teacher_id=?",
            [$lessonId, $tid]
        ) : null;
        if ($lessonId && !$lesson) $errors[] = 'Invalid lesson.';

        $filePath = null;
        $fileSize = null;

        if (!empty($_FILES['material_file']['name']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $filePath = handleUpload($_FILES['material_file'], 'materials', ALLOWED_MATERIAL_TYPES);
                $fileSize = $_FILES['material_file']['size'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file(
                    PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath)
                );
                $type = match (true) {
                    str_contains($mime, 'pdf')          => 'pdf',
                    str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint') => 'pptx',
                    str_contains($mime, 'video')        => 'video',
                    str_contains($mime, 'image')        => 'other',
                    default                             => $type ?: 'other',
                };
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        if (empty($errors)) {
            Database::insert('learning_materials', [
                'lesson_id'       => $lessonId,
                'topic_id'        => $topicId,
                'title'           => $title,
                'type'            => $type ?: 'text',
                'content'         => $content ?: null,
                'file_path'       => $filePath,
                'file_size'       => $fileSize,
                'external_url'    => $extUrl ?: null,
                'sort_order'      => $sortOrd,
                'is_downloadable' => $download,
            ]);
            AuditLog::write(Auth::id(), 'upload_material', 'materials', "$title");
            flashSet('success', 'Material uploaded successfully.');
            $redir = $_SERVER['PHP_SELF'] . ($courseId ? "?course_id=$courseId" : '');
            redirect($redir);
        } else {
            foreach ($errors as $err) flashSet('error', $err);
        }
    }
}

// ── Fetch lessons for the upload form ────────────────────────
// Load ALL lessons (published + draft) so teacher can attach materials to any lesson
// Key: course_id → array of lessons
$allLessonsForForm = [];
foreach ($courses as $c) {
    $allLessonsForForm[$c['id']] = Database::fetchAll(
        "SELECT id, title, is_published FROM lessons WHERE course_id=? ORDER BY sort_order",
        [$c['id']]
    );
}
// Lessons for the currently filtered course (used as initial state in modal)
$lessonsForForm = $courseId && isset($allLessonsForForm[$courseId])
    ? $allLessonsForForm[$courseId]
    : (!empty($courses) ? ($allLessonsForForm[$courses[0]['id']] ?? []) : []);
$defaultCourseId = $courseId ?: (!empty($courses) ? $courses[0]['id'] : 0);

// ── Fetch all materials ───────────────────────────────────────
$where  = "WHERE c.teacher_id=?";
$params = [$tid];
if ($courseId) { $where .= " AND c.id=?"; $params[] = $courseId; }

$materials = Database::fetchAll(
    "SELECT m.*, l.id lesson_id_val, l.title lesson_title, c.id course_id, c.title course_title, c.code course_code
     FROM learning_materials m
     JOIN lessons l ON l.id = m.lesson_id
     JOIN courses c ON c.id = l.course_id
     {$where}
     ORDER BY c.code ASC, l.sort_order ASC, m.sort_order ASC, m.id ASC",
    $params
);

$grouped = [];
foreach ($materials as $m) {
    $cid = $m['course_id'];
    if (!isset($grouped[$cid])) {
        $grouped[$cid] = ['title' => $m['course_title'], 'code' => $m['course_code'], 'items' => []];
    }
    $grouped[$cid]['items'][] = $m;
}

$typeIcons  = ['pdf'=>'📄','pptx'=>'📊','ppt'=>'📊','video'=>'🎬','text'=>'📝','link'=>'🔗','other'=>'📁',
               'doc'=>'📝','docx'=>'📝'];
$typeColors = [
    'pdf'  =>'bg-red-50 text-red-700 border-red-100',
    'pptx' =>'bg-orange-50 text-orange-700 border-orange-100',
    'ppt'  =>'bg-orange-50 text-orange-700 border-orange-100',
    'video'=>'bg-blue-50 text-blue-700 border-blue-100',
    'text' =>'bg-green-50 text-green-700 border-green-100',
    'link' =>'bg-purple-50 text-purple-700 border-purple-100',
    'other'=>'bg-gray-50 text-gray-700 border-gray-100',
];
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

<!-- Header -->
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Learning Materials</h2>
        <p class="text-sm text-gray-500"><?= count($materials) ?> file<?= count($materials) != 1 ? 's' : '' ?> across <?= count($grouped) ?> subject<?= count($grouped) != 1 ? 's' : '' ?></p>
    </div>
    <button onclick="openModal('uploadModal')"
            class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Upload Material
    </button>
</div>

<!-- Subject filter tabs -->
<div class="flex gap-2 overflow-x-auto pb-1 mb-6">
    <a href="<?= BASE_URL ?>/modules/teacher/materials.php"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap flex-shrink-0 transition
              <?= !$courseId ? 'bg-orange-500 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        All Subjects
    </a>
    <?php foreach ($courses as $c): ?>
    <a href="?course_id=<?= $c['id'] ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap flex-shrink-0 transition
              <?= $courseId == $c['id'] ? 'bg-orange-500 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= e($c['code']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Materials List -->
<?php if (empty($grouped)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-3">&#128196;</div>
    <p class="text-gray-500 font-medium">No materials uploaded yet.</p>
    <p class="text-gray-400 text-sm mt-2">Click <strong>Upload Material</strong> to add your first file.</p>
    <button onclick="openModal('uploadModal')"
            class="mt-4 px-5 py-2.5 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition">
        + Upload Material
    </button>
</div>
<?php else: ?>
<?php foreach ($grouped as $cid => $group): ?>
<div class="mb-8">
    <div class="flex items-center gap-3 mb-3">
        <div class="flex items-center gap-2 bg-white border border-orange-200 rounded-xl px-4 py-2 shadow-sm">
            <span class="text-xs font-extrabold text-orange-600 tracking-wide"><?= e($group['code']) ?></span>
            <span class="text-gray-300">|</span>
            <span class="text-sm font-semibold text-gray-700"><?= e($group['title']) ?></span>
        </div>
        <span class="text-xs text-gray-400"><?= count($group['items']) ?> file<?= count($group['items']) != 1 ? 's' : '' ?></span>
        <a href="<?= BASE_URL ?>/modules/teacher/lessons.php?course_id=<?= $cid ?>"
           class="ml-auto flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-semibold rounded-xl hover:bg-orange-600 transition shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Manage Lessons
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
    <?php
    $byLesson = [];
    foreach ($group['items'] as $m) $byLesson[$m['lesson_title']][] = $m;
    foreach ($byLesson as $lessonTitle => $mats):
        foreach ($mats as $mat):
    ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex flex-col gap-2 hover:border-orange-200 hover:shadow-md transition">
            <div class="flex items-start gap-3">
                <span class="text-2xl flex-shrink-0 mt-0.5"><?= $typeIcons[$mat['type']] ?? '📁' ?></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-gray-800 leading-snug line-clamp-2"><?= e($mat['title']) ?></p>
                    <p class="text-xs text-orange-600 mt-0.5 truncate"><?= e($lessonTitle) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full border <?= $typeColors[$mat['type']] ?? $typeColors['other'] ?>">
                    <?= strtoupper($mat['type']) ?>
                </span>
                <?php if ($mat['file_size']): ?>
                <span class="text-xs text-gray-400">
                    <?= $mat['file_size'] >= 1048576 ? round($mat['file_size']/1048576,1).' MB' : round($mat['file_size']/1024).' KB' ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="flex gap-2 mt-auto pt-1 border-t border-gray-50">
                <?php if ($mat['file_path']): ?>
                <a href="<?= fileUrl($mat['file_path']) ?>" target="_blank"
                   class="flex-1 text-center py-1.5 text-xs font-medium bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition">View</a>
                <a href="<?= fileUrl($mat['file_path']) ?>" download
                   class="flex-1 text-center py-1.5 text-xs font-medium bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">Download</a>
                <?php elseif ($mat['external_url']): ?>
                <a href="<?= e($mat['external_url']) ?>" target="_blank"
                   class="flex-1 text-center py-1.5 text-xs font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">Open Link</a>
                <?php endif; ?>
                <form method="POST" id="delMat-<?= $mat['id'] ?>">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                    <?php if ($courseId): ?><input type="hidden" name="course_id" value="<?= $courseId ?>"><?php endif; ?>
                    <button type="button" onclick="confirmDelete('Delete this material?','delMat-<?= $mat['id'] ?>')"
                            class="p-1.5 text-gray-300 hover:text-red-500 rounded-lg hover:bg-red-50 transition" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</main>
</div>

<!-- ══════════════════════════════════════════════
     UPLOAD MATERIAL MODAL
══════════════════════════════════════════════ -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[92vh]">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0"
             style="background:linear-gradient(135deg,#e67e22,#d35400)">
            <h3 class="font-bold text-white">Upload Material</h3>
            <button onclick="closeModal('uploadModal')" class="text-white/70 hover:text-white">✕</button>
        </div>

        <!-- Scrollable body -->
        <div class="overflow-y-auto flex-1 p-6">
            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="upload">

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                    <select id="modalCourse" name="course_id_display" required
                            onchange="loadModalLessons(this.value)"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $defaultCourseId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['code'] . ' — ' . $c['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lesson -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Lesson *</label>
                        <button type="button" onclick="toggleNewLesson()"
                                class="text-xs text-orange-600 hover:text-orange-700 font-semibold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add New Lesson
                        </button>
                    </div>
                    <select id="modalLesson" name="lesson_id" required
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <?php if (!empty($lessonsForForm)): ?>
                        <option value="">-- Select Lesson --</option>
                        <?php foreach ($lessonsForForm as $l): ?>
                        <option value="<?= $l['id'] ?>">
                            <?= e($l['title']) ?><?= !$l['is_published'] ? ' (Draft)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <option value="">-- No lessons yet for this subject --</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($lessonsForForm)): ?>
                    <p class="text-xs text-amber-600 mt-1">⚠ Add lessons first before uploading materials.</p>
                    <?php endif; ?>

                    <!-- Inline Add Lesson Panel -->
                    <div id="newLessonPanel" class="hidden mt-3 bg-orange-50 border border-orange-200 rounded-xl p-4 space-y-3">
                        <p class="text-xs font-semibold text-orange-700">📖 Create New Lesson</p>
                        <input type="text" id="newLessonTitle" placeholder="Lesson title e.g. Introduction to Networking"
                               class="w-full px-3 py-2 rounded-lg border border-orange-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <div class="flex gap-2">
                            <button type="button" onclick="saveNewLesson()"
                                    class="flex-1 py-2 bg-orange-500 text-white text-xs font-bold rounded-lg hover:bg-orange-600 transition">
                                ✓ Create Lesson
                            </button>
                            <button type="button" onclick="toggleNewLesson()"
                                    class="flex-1 py-2 bg-gray-100 text-gray-600 text-xs rounded-lg hover:bg-gray-200 transition">
                                Cancel
                            </button>
                        </div>
                        <p id="newLessonMsg" class="text-xs hidden"></p>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Material Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Chapter 1 Slides"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="modalType" onchange="toggleModalFields()"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="text">Text Lesson</option>
                        <option value="pdf">PDF</option>
                        <option value="pptx">PowerPoint</option>
                        <option value="video">Video</option>
                        <option value="link">External Link</option>
                        <option value="other">Other File</option>
                    </select>
                </div>

                <!-- File upload -->
                <div id="modalFileField">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
                    <input type="file" name="material_file" accept=".pdf,.pptx,.ppt,.mp4,.webm,.txt,.png,.jpg,.jpeg"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    <p class="text-xs text-gray-400 mt-1">Max <?= MAX_UPLOAD_BYTES/1024/1024 ?>MB. PDF, PPTX, MP4, images.</p>
                </div>

                <!-- External URL -->
                <div id="modalLinkField" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">External URL</label>
                    <input type="url" name="external_url" placeholder="https://…"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>

                <!-- Text content -->
                <div id="modalTextField">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Text Content</label>
                    <textarea name="content" rows="4" placeholder="Write lesson content here…"
                              class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
                </div>

                <!-- Options row -->
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0"
                               class="w-24 px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div class="flex items-end pb-0.5">
                        <label class="flex items-center gap-2 cursor-pointer mt-5">
                            <input type="checkbox" name="is_downloadable" value="1" checked
                                   class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-400">
                            <span class="text-sm text-gray-700">Downloadable</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
            <button type="submit" form="uploadForm"
                    class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition">
                Upload Material
            </button>
            <button type="button" onclick="closeModal('uploadModal')"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition">
                Cancel
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
// Toggle file/link/text fields based on type
function toggleModalFields() {
    const type = document.getElementById('modalType').value;
    document.getElementById('modalFileField').classList.toggle('hidden', type === 'link' || type === 'text');
    document.getElementById('modalLinkField').classList.toggle('hidden', type !== 'link');
    document.getElementById('modalTextField').classList.toggle('hidden', type !== 'text');
}
toggleModalFields();

// Load lessons when subject changes in the modal
// All lessons pre-loaded per course (no AJAX needed)
const allLessons = <?= json_encode(array_map(function($cid, $lessons) {
    return ['course_id' => $cid, 'lessons' => $lessons];
}, array_keys($allLessonsForForm), array_values($allLessonsForForm))) ?>;
const lessonsMap = {};
allLessons.forEach(item => { lessonsMap[item.course_id] = item.lessons; });

function loadModalLessons(courseId) {
    const sel = document.getElementById('modalLesson');
    if (!courseId) {
        sel.innerHTML = '<option value="">-- Select Subject First --</option>';
        return;
    }

    const lessons = lessonsMap[courseId] || [];

    if (lessons.length === 0) {
        sel.innerHTML = '<option value="">-- No lessons yet — add lessons first --</option>';
        return;
    }

    sel.innerHTML = '<option value="">-- Select Lesson --</option>';
    lessons.forEach(l => {
        const o = document.createElement('option');
        o.value = l.id;
        o.textContent = l.title + (l.is_published == 0 ? ' (Draft)' : '');
        sel.appendChild(o);
    });
}

// ── Add New Lesson inline ──────────────────────────────────────
function toggleNewLesson() {
    const panel = document.getElementById('newLessonPanel');
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
        document.getElementById('newLessonTitle').focus();
        document.getElementById('newLessonMsg').classList.add('hidden');
    }
}

function saveNewLesson() {
    const title    = document.getElementById('newLessonTitle').value.trim();
    const courseId = document.getElementById('modalCourse').value;
    const msg      = document.getElementById('newLessonMsg');

    if (!title) { showMsg(msg, '⚠ Please enter a lesson title.', 'text-red-600'); return; }
    if (!courseId) { showMsg(msg, '⚠ Please select a subject first.', 'text-red-600'); return; }

    const btn = document.querySelector('#newLessonPanel button');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    const fd = new FormData();
    fd.append('<?= CSRF_TOKEN_NAME ?>', '<?= e(Auth::csrfToken()) ?>');
    fd.append('action', 'create_lesson');
    fd.append('new_lesson_course_id', courseId);
    fd.append('new_lesson_title', title);

    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Add the new lesson to the dropdown and select it
                const sel = document.getElementById('modalLesson');
                const opt = document.createElement('option');
                opt.value       = data.lesson_id;
                opt.textContent = data.title + ' (Draft)';
                sel.appendChild(opt);
                sel.value = data.lesson_id;

                // Also add to lessonsMap so switching subjects works
                if (!lessonsMap[courseId]) lessonsMap[courseId] = [];
                lessonsMap[courseId].push({ id: data.lesson_id, title: data.title, is_published: 0 });

                // Reset and close panel
                document.getElementById('newLessonTitle').value = '';
                showMsg(msg, '✅ Lesson created and selected!', 'text-green-600');
                setTimeout(() => {
                    document.getElementById('newLessonPanel').classList.add('hidden');
                    msg.classList.add('hidden');
                }, 1500);
            } else {
                showMsg(msg, '❌ ' + (data.error || 'Failed to create lesson.'), 'text-red-600');
            }
            btn.disabled = false;
            btn.textContent = '✓ Create Lesson';
        })
        .catch(() => {
            showMsg(msg, '❌ Network error. Try again.', 'text-red-600');
            btn.disabled = false;
            btn.textContent = '✓ Create Lesson';
        });
}

function showMsg(el, text, color) {
    el.textContent  = text;
    el.className    = 'text-xs ' + color;
    el.classList.remove('hidden');
}

// When modal opens, refresh the lesson dropdown for the currently selected course
document.addEventListener('DOMContentLoaded', () => {
    const courseSelect = document.getElementById('modalCourse');
    if (courseSelect && courseSelect.value) {
        loadModalLessons(courseSelect.value);
    }
});

// Auto-open modal if there's a flash error from a failed upload
<?php if (flashHas('error')): ?>
document.addEventListener('DOMContentLoaded', () => openModal('uploadModal'));
<?php endif; ?>
</script>
</body>
</html>
