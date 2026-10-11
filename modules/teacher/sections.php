<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Blocks';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');

if ($courseId) {
    $filterCourse = Database::fetchOne(
        "SELECT id, title, code FROM courses WHERE id=? AND teacher_id=? AND is_active=1",
        [$courseId, $tid]
    );
    if (!$filterCourse) {
        flashSet('error', 'Subject not found.');
        redirect(BASE_URL . '/modules/teacher/courses.php');
    }
}

// Generate a unique 6-char join code
function generateBlockCode(): string {
    do {
        $code   = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        $exists = Database::fetchOne("SELECT id FROM course_sections WHERE join_code=?", [$code]);
    } while ($exists);
    return $code;
}

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'create') {
        $cid        = postInt('course_id');
        $name       = postStr('name');
        $schoolYear = postStr('school_year');
        $semester   = postStr('semester');

        $course = Database::fetchOne(
            "SELECT id, title FROM courses WHERE id=? AND teacher_id=? AND is_active=1",
            [$cid, $tid]
        );

        if (!$course) {
            flashSet('error', 'Invalid subject. Make sure a subject is assigned to you.');
        } elseif (empty($name)) {
            flashSet('error', 'Block name is required.');
        } else {
            $code = generateBlockCode();
            Database::insert('course_sections', [
                'course_id'   => $cid,
                'name'        => $name,
                'join_code'   => $code,
                'school_year' => $schoolYear ?: null,
                'semester'    => in_array($semester, ['1st','2nd','Summer']) ? $semester : '1st',
                'is_active'   => 1,
            ]);
            AuditLog::write(Auth::id(), 'create_block', 'course_sections',
                "Created block [{$name}] for course {$cid}");
            $_SESSION['new_block_code'] = $code;
            $_SESSION['new_block_name'] = $name;
            redirect(BASE_URL . '/modules/teacher/sections.php?course_id=' . $cid);
        }
        redirect($_SERVER['PHP_SELF'] . ($courseId ? "?course_id=$courseId" : ''));
    }

    if ($action === 'toggle') {
        $secId = postInt('section_id');
        $sec   = Database::fetchOne(
            "SELECT cs.id, cs.is_active FROM course_sections cs
             JOIN courses c ON c.id=cs.course_id
             WHERE cs.id=? AND c.teacher_id=?",
            [$secId, $tid]
        );
        if ($sec) {
            Database::update('course_sections', ['is_active' => $sec['is_active'] ? 0 : 1], 'id=?', [$secId]);
            flashSet('success', 'Block status updated.');
        }
        redirect($_SERVER['PHP_SELF'] . ($courseId ? "?course_id=$courseId" : ''));
    }

    if ($action === 'delete') {
        $secId = postInt('section_id');
        $sec   = Database::fetchOne(
            "SELECT cs.id FROM course_sections cs
             JOIN courses c ON c.id=cs.course_id
             WHERE cs.id=? AND c.teacher_id=?",
            [$secId, $tid]
        );
        if ($sec) {
            Database::update('course_sections', ['is_active' => 0], 'id=?', [$secId]);
            flashSet('success', 'Block removed.');
        }
        redirect($_SERVER['PHP_SELF'] . ($courseId ? "?course_id=$courseId" : ''));
    }
}

// ── Session data after create ─────────────────────────────────
$newBlockCode = $_SESSION['new_block_code'] ?? null;
$newBlockName = $_SESSION['new_block_name'] ?? null;
unset($_SESSION['new_block_code'], $_SESSION['new_block_name']);

// ── Teacher's courses ─────────────────────────────────────────
$teacherCourses = Database::fetchAll(
    "SELECT id, code, title FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title",
    [$tid]
);

// ── Fetch blocks ──────────────────────────────────────────────
$secWhere  = "WHERE c.teacher_id=? AND cs.is_active=1";
$secParams = [$tid];
if ($courseId) {
    $secWhere  .= " AND cs.course_id=?";
    $secParams[] = $courseId;
}

$blocks = Database::fetchAll(
    "SELECT cs.*,
            c.title course_title, c.code course_code,
            COUNT(DISTINCT e.student_id) student_count
     FROM course_sections cs
     JOIN courses c ON c.id=cs.course_id
     LEFT JOIN enrollments e ON e.section_id=cs.id AND e.status='active'
     {$secWhere}
     GROUP BY cs.id
     ORDER BY c.title ASC, cs.name ASC",
    $secParams
);

// Group by course
$grouped = [];
foreach ($blocks as $b) {
    $grouped[$b['course_id']]['course_title'] = $b['course_title'];
    $grouped[$b['course_id']]['course_code']  = $b['course_code'];
    $grouped[$b['course_id']]['blocks'][]     = $b;
}
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

<?php if ($newBlockCode): ?>
<!-- Join-Code Modal -->
<div id="codeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-5 text-white text-center">
            <div class="text-3xl mb-1">🎉</div>
            <h2 class="text-xl font-bold">Block Created!</h2>
            <p class="text-orange-200 text-sm mt-1"><?= e($newBlockName) ?></p>
        </div>
        <div class="p-6 text-center">
            <p class="text-sm text-gray-500 mb-2">Block Join Code</p>
            <div class="flex items-center justify-center gap-3 mb-4">
                <span class="text-4xl font-extrabold tracking-[0.3em] text-orange-700 bg-orange-50 border-2 border-orange-200 rounded-xl px-6 py-3 select-all">
                    <?= e($newBlockCode) ?>
                </span>
                <button onclick="copyCode()" class="p-2 rounded-xl bg-gray-100 hover:bg-orange-100 text-gray-500 hover:text-orange-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>
            </div>
            <p id="copyMsg" class="text-xs text-green-600 font-medium mb-4 hidden">Copied!</p>
            <p class="text-xs text-gray-400 mb-6">Share this code with students to let them join this block.</p>
            <button onclick="document.getElementById('codeModal').remove()"
                    class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl text-sm transition">
                Done
            </button>
        </div>
    </div>
</div>
<script>
function copyCode() {
    navigator.clipboard.writeText('<?= e($newBlockCode) ?>').then(() => {
        const m = document.getElementById('copyMsg');
        m.classList.remove('hidden');
        setTimeout(() => m.classList.add('hidden'), 2000);
    });
}
</script>
<?php endif; ?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">
            <?= $courseId ? 'Blocks — ' . e($filterCourse['code'] . ': ' . $filterCourse['title']) : 'All Blocks' ?>
        </h2>
        <p class="text-sm text-gray-500"><?= count($blocks) ?> block<?= count($blocks) !== 1 ? 's' : '' ?></p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($courseId): ?>
        <a href="<?= BASE_URL ?>/modules/teacher/courses.php"
           class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-xl hover:bg-gray-200 transition">
            ← My Subjects
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Blocks list -->
<?php if (empty($grouped)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-3">🏫</div>
    <p class="text-gray-500 font-medium">No blocks yet.</p>
    <p class="text-gray-400 text-sm mt-1">Blocks are created automatically when admin assigns a subject to you.</p>
</div>
<?php else: ?>
<?php foreach ($grouped as $cid => $group): ?>
<div class="mb-8">
    <!-- Course header -->
    <div class="flex items-center gap-3 mb-4">
        <div class="flex items-center gap-2 bg-white border border-orange-200 rounded-xl px-4 py-2 shadow-sm">
            <span class="text-xs font-extrabold text-orange-600 tracking-wide"><?= e($group['course_code']) ?></span>
            <span class="text-gray-300">|</span>
            <span class="text-sm font-semibold text-gray-700"><?= e($group['course_title']) ?></span>
        </div>
        <span class="text-xs text-gray-400"><?= count($group['blocks']) ?> block<?= count($group['blocks']) != 1 ? 's' : '' ?></span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($group['blocks'] as $b): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
        <!-- Card header -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 px-5 py-4">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <span class="inline-block text-xs font-bold bg-white/20 text-white px-2 py-0.5 rounded-full mb-1">
                        <?= e($b['course_code']) ?>
                    </span>
                    <p class="text-white font-bold text-sm leading-tight"><?= e($b['name']) ?></p>
                    <?php if ($b['school_year']): ?>
                    <p class="text-orange-200 text-xs mt-0.5"><?= e($b['school_year']) ?> · <?= e($b['semester']) ?> Sem</p>
                    <?php endif; ?>
                </div>
                <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center ml-2 flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="p-4 flex-1 flex flex-col">
            <!-- Student count -->
            <div class="flex items-center gap-2 mb-3">
                <span class="text-2xl font-extrabold text-gray-800"><?= $b['student_count'] ?></span>
                <span class="text-sm text-gray-500">student<?= $b['student_count'] != 1 ? 's' : '' ?></span>
            </div>

            <!-- Join code -->
            <div class="bg-orange-50 border border-orange-100 rounded-xl px-3 py-2 mb-3 flex items-center justify-between">
                <div>
                    <p class="text-xs text-orange-400 font-medium">Block Join Code</p>
                    <p class="text-lg font-extrabold tracking-widest text-orange-700"><?= e($b['join_code']) ?></p>
                </div>
                <button onclick="navigator.clipboard.writeText('<?= e($b['join_code']) ?>').then(()=>{this.textContent='✓';setTimeout(()=>this.textContent='Copy',1500)})"
                        class="px-2 py-1 text-xs bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium">
                    Copy
                </button>
            </div>
            <!-- Subject code badge -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 mb-3">
                <p class="text-xs text-gray-400 font-medium">Subject Code</p>
                <p class="text-sm font-extrabold text-gray-700 tracking-wide"><?= e($b['course_code']) ?></p>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 mt-auto">
                <a href="<?= BASE_URL ?>/modules/teacher/students.php?section_id=<?= $b['id'] ?>"
                   class="flex-1 text-center py-1.5 text-xs bg-orange-50 text-orange-700 rounded-xl hover:bg-orange-100 transition font-medium">
                    View Students
                </a>
                <form method="POST" class="flex-1">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="section_id" value="<?= $b['id'] ?>">
                    <button type="submit"
                            onclick="return confirm('Remove this block? Students will stay enrolled in the subject.')"
                            class="w-full py-1.5 text-xs bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition">
                        Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</main>
</div>

<!-- New Block Modal -->
<div id="blockModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#e67e22,#d35400)">
            <h3 class="font-bold text-white text-lg">Create New Block</h3>
            <button onclick="closeModal('blockModal')" class="text-white/70 hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Subject *</label>
                <select name="course_id" required
                        class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($teacherCourses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['code'] . ' — ' . $c['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Block Name *</label>
                <input type="text" name="name" required placeholder="e.g. BSIT-2A or Block 1"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">School Year</label>
                    <input type="text" name="school_year" placeholder="e.g. 2025-2026"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Semester</label>
                    <select name="semester"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-xl text-sm transition">
                    Create Block & Get Code
                </button>
                <button type="button" onclick="closeModal('blockModal')"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
