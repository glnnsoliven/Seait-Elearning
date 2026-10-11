<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Subject Management';

$teachers = Database::fetchAll(
    "SELECT t.id, CONCAT(u.first_name,' ',u.last_name) name, u.email
     FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE u.is_active=1 ORDER BY u.last_name"
);
$depts = Database::fetchAll("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name");

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    // Create & assign subject to teacher
    if ($action === 'create') {
        $title      = postStr('title');
        $code       = strtoupper(trim(postStr('code')));
        $desc       = postStr('description');
        $credits    = postInt('credits') ?: 3;
        $deptId     = postInt('department_id') ?: null;
        $teacherId  = postInt('teacher_id');
        $block      = postStr('block') ?: null;
        $yearLevel  = postInt('year_level') ?: null;
        $errors     = [];

        if (empty($title))    $errors[] = 'Subject title is required.';
        if (empty($code))     $errors[] = 'Subject code is required.';
        if (!$teacherId)      $errors[] = 'Please assign a teacher.';
        if (Database::fetchOne("SELECT id FROM courses WHERE code=?", [$code])) {
            $errors[] = "Subject code \"{$code}\" already exists.";
        }

        if (empty($errors)) {
            $cid = Database::insert('courses', [
                'teacher_id'    => $teacherId,
                'department_id' => $deptId,
                'title'         => $title,
                'code'          => $code,
                'description'   => $desc ?: null,
                'block'         => $block,
                'year_level'    => $yearLevel,
                'credits'       => $credits,
                'is_published'  => 1,
                'is_active'     => 1,
            ]);

            // ── Auto-create a Block with join code so teacher sees it immediately ──
            $blockName = $block ? "Block {$block}" : "Block 1";
            $joinCode  = '';
            do {
                $joinCode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
                $exists   = Database::fetchOne("SELECT id FROM course_sections WHERE join_code=?", [$joinCode]);
            } while ($exists);

            Database::insert('course_sections', [
                'course_id'   => $cid,
                'name'        => $blockName,
                'join_code'   => $joinCode,
                'school_year' => date('Y') . '-' . (date('Y') + 1),
                'semester'    => '1st',
                'is_active'   => 1,
            ]);

            AuditLog::write(Auth::id(), 'admin_create_course', 'courses',
                "Admin created+published subject [{$code}] block [{$block}] year [{$yearLevel}] to teacher #{$teacherId}");
            flashSet('success', "✅ Subject \"{$title}\" created, assigned to teacher and published. Block <strong>{$blockName}</strong> with join code <strong>{$joinCode}</strong> was created automatically.");
        } else {
            foreach ($errors as $e) flashSet('error', $e);
        }
        redirect($_SERVER['PHP_SELF']);
    }

    // Reassign teacher
    if ($action === 'reassign') {
        $cid       = postInt('course_id');
        $teacherId = postInt('teacher_id');
        if ($cid && $teacherId) {
            Database::update('courses', ['teacher_id' => $teacherId], 'id=?', [$cid]);
            AuditLog::write(Auth::id(), 'admin_reassign_course', 'courses',
                "Reassigned course #{$cid} to teacher #{$teacherId}");
            flashSet('success', 'Subject reassigned successfully.');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    // Toggle active
    if ($action === 'toggle') {
        $id  = postInt('course_id');
        $row = Database::fetchOne("SELECT is_active FROM courses WHERE id=?", [$id]);
        if ($row) {
            Database::update('courses', ['is_active' => $row['is_active'] ? 0 : 1], 'id=?', [$id]);
            flashSet('success', 'Subject status updated.');
        }
        redirect($_SERVER['PHP_SELF']);
    }

    // Delete (soft)
    if ($action === 'delete') {
        $id = postInt('course_id');
        Database::update('courses', ['is_active'=>0,'is_published'=>0], 'id=?', [$id]);
        AuditLog::write(Auth::id(), 'admin_delete_course', 'courses', "Deleted course #{$id}");
        flashSet('success', 'Subject removed.');
        redirect($_SERVER['PHP_SELF']);
    }
}

// ── Fetch courses ─────────────────────────────────────────────
$search     = getStr('search');
$deptF      = getInt('department_id');
$yearLevelF = isset($_GET['year_level_f']) && $_GET['year_level_f'] !== '' ? (int)$_GET['year_level_f'] : 0;
$page       = getInt('page', 1);

$where  = "WHERE c.is_active=1";
$params = [];
if ($search) {
    $where   .= " AND (c.title LIKE ? OR c.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($deptF)      { $where .= " AND c.department_id=?"; $params[] = $deptF; }
if ($yearLevelF) { $where .= " AND c.year_level=?";    $params[] = $yearLevelF; }

$total = Database::fetchOne("SELECT COUNT(*) cnt FROM courses c {$where}", $params)['cnt'];
$pag   = paginate($total, $page);

$courses = Database::fetchAll(
    "SELECT c.*, d.name dept_name,
            CONCAT(u.first_name,' ',u.last_name) teacher_name, t.id tid,
            COUNT(DISTINCT e.id) enrolled,
            COUNT(DISTINCT l.id) lesson_count,
            COUNT(DISTINCT q.id) quiz_count,
            c.block, c.year_level
     FROM courses c
     LEFT JOIN departments d ON d.id=c.department_id
     LEFT JOIN teachers t ON t.id=c.teacher_id
     LEFT JOIN users u ON u.id=t.user_id
     LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
     LEFT JOIN lessons l ON l.course_id=c.id
     LEFT JOIN quizzes q ON q.course_id=c.id
     {$where}
     GROUP BY c.id
     ORDER BY c.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<!-- Header -->
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Subject Management</h2>
        <p class="text-sm text-gray-500"><?= $total ?> active subjects</p>
    </div>
    <button onclick="openModal('createModal')"
            class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Create & Assign Subject
    </button>
</div>

<!-- Year Level Tabs -->
<?php
$yearCounts = [];
foreach ([0,1,2,3,4] as $yr) {
    $q = $yr === 0
        ? "SELECT COUNT(*) c FROM courses WHERE is_active=1"
        : "SELECT COUNT(*) c FROM courses WHERE is_active=1 AND year_level=$yr";
    $yearCounts[$yr] = (int)Database::fetchOne($q)['c'];
}
$yearLabels = [0=>'All Years', 1=>'1st Year', 2=>'2nd Year', 3=>'3rd Year', 4=>'4th Year'];
$yearColors = [
    1 => ['tab'=>'bg-blue-500 text-white','badge'=>'bg-white/30 text-white'],
    2 => ['tab'=>'bg-emerald-500 text-white','badge'=>'bg-white/30 text-white'],
    3 => ['tab'=>'bg-purple-500 text-white','badge'=>'bg-white/30 text-white'],
    4 => ['tab'=>'bg-rose-500 text-white','badge'=>'bg-white/30 text-white'],
];
?>
<div class="flex gap-2 overflow-x-auto pb-1 mb-5">
    <?php foreach ($yearLabels as $yr => $label):
        $isActive = $yearLevelF === $yr;
        $activeColor = $yr === 0 ? 'bg-orange-500 text-white shadow-sm' : ($yearColors[$yr]['tab'] ?? 'bg-gray-500 text-white');
        $href = '?' . http_build_query(array_filter([
            'year_level_f'  => $yr ?: '',
            'search'        => $search,
            'department_id' => $deptF,
        ]));
    ?>
    <a href="<?= $href ?>"
       class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap flex-shrink-0 transition
              <?= $isActive
                    ? ($yr === 0 ? 'bg-orange-500 text-white shadow-md' : ($yearColors[$yr]['tab'] ?? 'bg-gray-500 text-white') . ' shadow-md')
                    : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= $label ?>
        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold
                     <?= $isActive ? 'bg-white/30 text-white' : 'bg-gray-100 text-gray-500' ?>">
            <?= $yearCounts[$yr] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search + Department filter row -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="year_level_f" value="<?= $yearLevelF ?>">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by title, code…"
               class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
        <select name="department_id" class="px-4 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
            <option value="">All Departments</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptF==$d['id']?'selected':''?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Search</button>
        <?php if ($search || $deptF): ?>
        <a href="?year_level_f=<?= $yearLevelF ?>" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-xl hover:bg-gray-200 flex items-center">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Subjects as blocks grouped by teacher -->
<div class="space-y-6">
<?php
// Group courses by teacher
$grouped = [];
foreach ($courses as $c) {
    $key = $c['tid'] ?? 0;
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['teacher' => $c['teacher_name'] ?? 'Unassigned', 'subjects' => []];
    }
    $grouped[$key]['subjects'][] = $c;
}
foreach ($grouped as $tid => $group):
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Teacher header -->
    <div class="flex items-center gap-3 px-5 py-3 bg-orange-50 border-b border-orange-100">
        <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
            <?= strtoupper(substr($group['teacher'], 0, 1)) ?>
        </div>
        <div>
            <p class="font-semibold text-gray-800 text-sm"><?= e($group['teacher']) ?></p>
            <p class="text-xs text-gray-500"><?= count($group['subjects']) ?> assigned subject<?= count($group['subjects']) != 1 ? 's' : '' ?></p>
        </div>
    </div>

    <!-- Subject blocks grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
    <?php foreach ($group['subjects'] as $c): ?>
    <div class="border border-gray-200 rounded-xl p-4 hover:border-orange-300 hover:shadow-sm transition">
        <div class="flex items-start justify-between mb-2">
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-800 text-sm leading-snug"><?= e($c['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= e($c['code']) ?></p>
            </div>
            <span class="ml-2 flex-shrink-0 px-1.5 py-0.5 rounded-full text-xs font-medium
                <?= $c['is_published'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                <?= $c['is_published'] ? 'Live' : 'Draft' ?>
            </span>
        </div>

        <!-- Block + Year Level badges -->
        <div class="flex flex-wrap gap-1.5 mb-2">
            <?php if ($c['year_level']): ?>
            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-100">
                <?= match((int)$c['year_level']) {
                    1 => '1st Year', 2 => '2nd Year',
                    3 => '3rd Year', 4 => '4th Year',
                    default => $c['year_level'].'th Year'
                } ?>
            </span>
            <?php endif; ?>
            <?php if ($c['block']): ?>
            <span class="px-2 py-0.5 bg-orange-50 text-orange-700 text-xs font-semibold rounded-full border border-orange-100">
                Block <?= e($c['block']) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-3 text-xs text-gray-500 mb-3">
            <span>👥 <?= $c['enrolled'] ?></span>
            <span>📖 <?= $c['lesson_count'] ?></span>
            <span>✏️ <?= $c['quiz_count'] ?></span>
        </div>

        <div class="flex gap-1.5">
            <button onclick='openReassign(<?= $c["id"] ?>, <?= (int)$c["tid"] ?>)'
                    class="flex-1 py-1 text-xs bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition text-center">
                Reassign
            </button>
            <form method="POST" class="flex-1">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                <button class="w-full py-1 text-xs rounded-lg transition
                    <?= $c['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' ?>">
                    <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
            <form method="POST" id="del-<?= $c['id'] ?>">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                <button type="button"
                        onclick="confirmDelete('Delete subject &quot;<?= e($c['title']) ?>&quot;?','del-<?= $c['id'] ?>')"
                        class="py-1 px-2 text-xs bg-gray-100 text-gray-500 rounded-lg hover:bg-red-50 hover:text-red-600 transition">
                    🗑
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($courses)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-3">📚</div>
    <p class="text-gray-500 font-medium">No subjects assigned yet.</p>
    <p class="text-gray-400 text-sm mt-1">Click "Create & Assign Subject" to assign subjects to teachers.</p>
</div>
<?php endif; ?>
</div>                            
</main>
</div>

<!-- ════════════════════════════════════════
     CREATE & ASSIGN SUBJECT MODAL
════════════════════════════════════════ -->
<div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[92vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#e67e22,#d35400)">
            <h3 class="font-bold text-white text-lg">Create & Assign Subject</h3>
            <button onclick="closeModal('createModal')" class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="overflow-y-auto flex-1 p-6">
            <form method="POST" id="createForm" class="space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Assign to Teacher *</label>
                    <select name="teacher_id" required
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> <span class="text-gray-400">(<?= e($t['email']) ?>)</span></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Subject Title *</label>
                        <input type="text" name="title" required placeholder="e.g. Networking 1"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Subject Code *</label>
                        <input type="text" name="code" required placeholder="e.g. IT321"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                               oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Department</label>
                        <select name="department_id"
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                            <option value="">-- Select Dept --</option>
                            <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Credits</label>
                        <input type="number" name="credits" value="3" min="1" max="10"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                </div>

                <!-- Block & Year Level -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Block</label>
                        <input type="text" name="block" placeholder="e.g. A, B, 1, 2"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <p class="text-xs text-gray-400 mt-1">Section/Block identifier</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Year Level</label>
                        <select name="year_level"
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                            <option value="">-- Select Year --</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Description</label>
                    <textarea name="description" rows="2" placeholder="Subject description (optional)…"
                              class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
                </div>
            </form>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100">
            <button type="submit" form="createForm"
                    class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-bold rounded-xl hover:bg-orange-600 transition">
                Create & Assign Subject
            </button>
            <button type="button" onclick="closeModal('createModal')"
                    class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200 transition">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     REASSIGN TEACHER MODAL
════════════════════════════════════════ -->
<div id="reassignModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800">Reassign Subject</h3>
            <button onclick="closeModal('reassignModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="reassign">
            <input type="hidden" name="course_id" id="reassignCourseId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Assign to Teacher</label>
                <select name="teacher_id" id="reassignTeacherId" required
                        class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-bold rounded-xl hover:bg-orange-600">
                    Reassign
                </button>
                <button type="button" onclick="closeModal('reassignModal')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function openReassign(courseId, currentTeacherId) {
    document.getElementById('reassignCourseId').value    = courseId;
    document.getElementById('reassignTeacherId').value   = currentTeacherId;
    openModal('reassignModal');
}
</script>
</body>
</html>
