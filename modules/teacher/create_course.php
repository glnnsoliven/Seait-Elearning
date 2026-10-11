<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

// Edit mode
$editId  = getInt('id');
$editing = $editId > 0;
$course  = $editing ? Database::fetchOne("SELECT * FROM courses WHERE id=? AND teacher_id=?",[$editId,$tid]) : null;
if ($editing && !$course) { flashSet('error','Subject not found.'); redirect(BASE_URL.'/modules/teacher/courses.php'); }

$pageTitle = $editing ? 'Edit Subject' : 'Add New Subject';

// BSIT is the only program — always use department id=1
$bsitDeptId = 1;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $title   = postStr('title');
    $code    = postStr('code');
    $desc    = postStr('description');
    $credits = postInt('credits') ?: 3;

    if (empty($title)) $errors[] = 'Subject title is required.';
    if (empty($code))  $errors[] = 'Subject code is required.';

    // Unique code check (ignore self when editing)
    $existing = Database::fetchOne("SELECT id FROM courses WHERE code = ?", [$code]);
    if ($existing && (!$editing || (int)$existing['id'] !== $editId)) {
        $errors[] = 'Subject code already exists. Choose a different code.';
    }

    if (empty($errors)) {
        $data = [
            'title'         => $title,
            'code'          => strtoupper(trim($code)),
            'description'   => $desc,
            'department_id' => $bsitDeptId,
            'credits'       => $credits,
        ];
        if ($editing) {
            Database::update('courses', $data, 'id = ? AND teacher_id = ?', [$editId, $tid]);
            AuditLog::write(Auth::id(), 'edit_course', 'courses', "Edited: $title");
            flashSet('success', 'Subject updated successfully.');
        } else {
            $data['teacher_id']   = $tid;
            $data['is_published'] = 0;
            $data['is_active']    = 1;
            $cid = Database::insert('courses', $data);
            AuditLog::write(Auth::id(), 'create_course', 'courses', "Created: $title");
            flashSet('success', 'Subject created. Add lessons now.');
            redirect(BASE_URL . '/modules/teacher/lessons.php?course_id=' . $cid);
        }
        redirect(BASE_URL . '/modules/teacher/courses.php');
    }
}

$title   = $course['title']       ?? ($_POST['title']       ?? '');
$code    = $course['code']        ?? ($_POST['code']        ?? '');
$desc    = $course['description'] ?? ($_POST['description'] ?? '');
$credits = $course['credits']     ?? ($_POST['credits']     ?? 3);
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

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800"><?= $editing ? 'Edit Subject' : 'Add New Subject' ?></h2>
    <p class="text-sm text-gray-500">
        Program: <span class="font-medium text-orange-700">BSIT — Bachelor of Science in Information Technology</span>
    </p>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
    <?php foreach ($errors as $err): ?><p>• <?= e($err) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-2xl">

    <!-- Program badge (read-only) -->
    <div class="flex items-center gap-3 bg-orange-50 border border-orange-100 rounded-xl px-4 py-3 mb-5">
        <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
            </svg>
        </div>
        <div>
            <p class="text-xs text-orange-500 font-medium">Program</p>
            <p class="text-sm font-semibold text-violet-800">Bachelor of Science in Information Technology (BSIT)</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject Title *</label>
                <input type="text" name="title" value="<?= e($title) ?>" required
                       placeholder="e.g. Introduction to Programming"
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject Code *</label>
                    <input type="text" name="code" value="<?= e($code) ?>" required
                           placeholder="e.g. IT101"
                           oninput="this.value=this.value.toUpperCase()"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <p class="text-xs text-gray-400 mt-1">Auto-uppercased. Must be unique.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Units / Credits</label>
                    <input type="number" name="credits" value="<?= e($credits) ?>" min="1" max="10"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="4"
                          placeholder="Describe what students will learn in this subject…"
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= e($desc) ?></textarea>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit"
                    class="px-6 py-2.5 bg-orange-500 text-white font-medium text-sm rounded-xl hover:bg-orange-600 transition">
                <?= $editing ? 'Save Changes' : 'Create Subject' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/teacher/courses.php"
               class="px-6 py-2.5 bg-gray-100 text-gray-700 font-medium text-sm rounded-xl hover:bg-gray-200 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
