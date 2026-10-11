<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Teacher Management';

// ── POST: Add Teacher ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'add_teacher') {
        $firstName   = postStr('first_name');
        $lastName    = postStr('last_name');
        $email       = postStr('email');
        $username    = postStr('username');
        $employeeId  = postStr('employee_id');
        $spec        = postStr('specialization');
        $deptId      = postInt('department_id') ?: null;
        $password    = $_POST['password'] ?? '';
        $errors      = [];

        if (empty($firstName))  $errors[] = 'First name is required.';
        if (empty($lastName))   $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (Database::fetchOne("SELECT id FROM users WHERE email=?", [$email])) $errors[] = 'Email already exists.';
        if (Database::fetchOne("SELECT id FROM users WHERE username=?", [$username])) $errors[] = 'Username already taken.';

        if (empty($errors)) {
            Database::beginTransaction();
            try {
                $uid = Database::insert('users', [
                    'username'   => $username,
                    'email'      => $email,
                    'password'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
                    'role'       => 'teacher',
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'is_active'  => 1,
                ]);
                Database::insert('teachers', [
                    'user_id'        => $uid,
                    'department_id'  => $deptId,
                    'employee_id'    => $employeeId ?: null,
                    'specialization' => $spec ?: null,
                ]);
                Database::commit();
                AuditLog::write(Auth::id(), 'add_teacher', 'users', "Added teacher: $email");
                flashSet('success', "Teacher {$firstName} {$lastName} added successfully.");
            } catch (Throwable $e) {
                Database::rollback();
                flashSet('error', 'Failed to add teacher. ' . ($e->getMessage()));
            }
        } else {
            foreach ($errors as $err) flashSet('error', $err);
        }
        redirect($_SERVER['PHP_SELF']);
    }

    if ($action === 'toggle_active') {
        $uid = postInt('user_id');
        $row = Database::fetchOne("SELECT is_active FROM users WHERE id=? AND role='teacher'", [$uid]);
        if ($row) {
            Database::update('users', ['is_active' => $row['is_active'] ? 0 : 1], 'id=?', [$uid]);
            flashSet('success', 'Teacher status updated.');
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$search = getStr('search');
$deptF  = getInt('department_id');
$page   = getInt('page', 1);

$where  = "WHERE u.role='teacher'";
$params = [];
if ($search) {
    $where  .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.employee_id LIKE ?)";
    $params  = ["%$search%","%$search%","%$search%","%$search%"];
}
if ($deptF) { $where .= " AND t.department_id=?"; $params[] = $deptF; }

$total   = Database::fetchOne("SELECT COUNT(*) c FROM users u JOIN teachers t ON t.user_id=u.id {$where}", $params)['c'];
$pag     = paginate($total, $page);
$depts   = Database::fetchAll("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name");

$teachers = Database::fetchAll(
    "SELECT u.*, t.id teacher_id, t.employee_id, t.specialization,
            d.name AS dept_name,
            COUNT(DISTINCT c.id) AS course_count,
            COUNT(DISTINCT e.student_id) AS student_count
     FROM users u
     JOIN teachers t ON t.user_id=u.id
     LEFT JOIN departments d ON d.id=t.department_id
     LEFT JOIN courses c ON c.teacher_id=t.id AND c.is_active=1
     LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
     {$where}
     GROUP BY u.id
     ORDER BY u.last_name, u.first_name
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Teachers</h2>
        <p class="text-sm text-gray-500"><?= $total ?> faculty members</p>
    </div>
    <button onclick="document.getElementById('addTeacherModal').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Teacher
    </button>
</div>

<!-- Filter -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, employee ID…"
               class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
        <select name="department_id" class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
            <option value="">All Departments</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptF==$d['id']?'selected':''?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Filter</button>
    </form>
</div>

<!-- Teacher Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($teachers as $t):
    $avatar = $t['avatar']
        ? fileUrl($t['avatar'])
        : 'https://ui-avatars.com/api/?name='.urlencode($t['first_name'].' '.$t['last_name']).'&background=e67e22&color=fff&size=80';
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center gap-4 mb-4">
        <img src="<?= e($avatar) ?>" class="w-14 h-14 rounded-2xl object-cover" alt="">
        <div class="min-w-0">
            <p class="font-semibold text-gray-800"><?= e($t['first_name'].' '.$t['last_name']) ?></p>
            <p class="text-xs text-gray-500 truncate"><?= e($t['email']) ?></p>
            <p class="text-xs text-orange-600 mt-0.5 font-mono"><?= e($t['employee_id'] ?? '—') ?></p>
        </div>
    </div>

    <?php if ($t['specialization']): ?>
    <p class="text-xs text-gray-500 mb-3 bg-gray-50 rounded-lg px-3 py-2"><?= e($t['specialization']) ?></p>
    <?php endif; ?>

    <div class="flex items-center gap-2 mb-3">
        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
        </svg>
        <p class="text-xs text-gray-600"><?= e($t['dept_name'] ?? '— No Department') ?></p>
    </div>

    <div class="grid grid-cols-2 gap-2 text-center mb-3">
        <div class="bg-orange-50 rounded-xl py-2">
            <p class="text-xl font-bold text-orange-600"><?= $t['course_count'] ?></p>
            <p class="text-xs text-gray-500">Subjects</p>
        </div>
        <div class="bg-blue-50 rounded-xl py-2">
            <p class="text-xl font-bold text-blue-600"><?= $t['student_count'] ?></p>
            <p class="text-xs text-gray-500">Students</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $t['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
            <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
        </span>
        <form method="POST">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="user_id" value="<?= $t['id'] ?>">
            <button type="submit" class="text-xs px-3 py-1 rounded-lg <?= $t['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' ?> transition">
                <?= $t['is_active'] ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($teachers)): ?>
<div class="col-span-full bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400 mb-3">No teachers found.</p>
    <button onclick="document.getElementById('addTeacherModal').classList.remove('hidden')"
            class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">
        Add First Teacher
    </button>
</div>
<?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pag['total_pages'] > 1): ?>
<div class="flex justify-center gap-2 mt-5">
    <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
    <a href="?page=<?=$p?>&search=<?= urlencode($search) ?>&department_id=<?= $deptF ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-orange-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- ── Add Teacher Modal ───────────────────────────────────── -->
<div id="addTeacherModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#e67e22,#d35400)">
            <h3 class="font-bold text-white text-lg">Add New Teacher</h3>
            <button onclick="document.getElementById('addTeacherModal').classList.add('hidden')"
                    class="text-white/70 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Form -->
        <form method="POST" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="add_teacher">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">First Name *</label>
                    <input type="text" name="first_name" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="Juan">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Last Name *</label>
                    <input type="text" name="last_name" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="Dela Cruz">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Email Address *</label>
                <input type="email" name="email" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                       placeholder="teacher@seait.edu.ph">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Username *</label>
                    <input type="text" name="username" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="jdelacruz">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Employee ID</label>
                    <input type="text" name="employee_id"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="EMP-001">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Department</label>
                <select name="department_id"
                        class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <option value="">— No Department —</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Specialization</label>
                <input type="text" name="specialization"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                       placeholder="e.g. Networking, Database Systems">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Password *</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                       placeholder="Min. 8 characters">
                <p class="text-xs text-gray-400 mt-1">Teacher will use this to log in.</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl text-sm transition">
                    Create Teacher Account
                </button>
                <button type="button"
                        onclick="document.getElementById('addTeacherModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
// Close modal on backdrop click
document.getElementById('addTeacherModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
</body>
</html>


$search = getStr('search');
$deptF  = getInt('department_id');
$page   = getInt('page', 1);

$where  = "WHERE u.role='teacher'";
$params = [];
if ($search) {
    $where   .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.employee_id LIKE ?)";
    $params   = ["%$search%","%$search%","%$search%","%$search%"];
}
if ($deptF) { $where .= " AND t.department_id=?"; $params[] = $deptF; }

$total    = Database::fetchOne("SELECT COUNT(*) c FROM users u JOIN teachers t ON t.user_id=u.id {$where}", $params)['c'];
$pag      = paginate($total, $page);
$depts    = Database::fetchAll("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name");

$teachers = Database::fetchAll(
    "SELECT u.*, t.employee_id, t.specialization,
            d.name AS dept_name,
            COUNT(DISTINCT c.id) AS course_count,
            COUNT(DISTINCT e.student_id) AS student_count
     FROM users u
     JOIN teachers t ON t.user_id=u.id
     LEFT JOIN departments d ON d.id=t.department_id
     LEFT JOIN courses c ON c.teacher_id=t.id AND c.is_active=1
     LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active'
     {$where}
     GROUP BY u.id
     ORDER BY u.last_name, u.first_name
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Teachers</h2>
        <p class="text-sm text-gray-500"><?= $total ?> faculty members</p>
    </div>
</div>

<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, employee ID…"
               class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
        <select name="department_id" class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
            <option value="">All Departments</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptF==$d['id']?'selected':''?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-700">Filter</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($teachers as $t):
    $avatar = $t['avatar']
        ? BASE_URL.'/'.$t['avatar']
        : 'https://ui-avatars.com/api/?name='.urlencode($t['first_name'].' '.$t['last_name']).'&background=8b5cf6&color=fff&size=80';
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center gap-4 mb-4">
        <img src="<?= e($avatar) ?>" class="w-14 h-14 rounded-2xl object-cover" alt="">
        <div>
            <p class="font-semibold text-gray-800"><?= e($t['first_name'].' '.$t['last_name']) ?></p>
            <p class="text-xs text-gray-500"><?= e($t['email']) ?></p>
            <p class="text-xs text-orange-600 mt-0.5"><?= e($t['employee_id'] ?? 'No ID') ?></p>
        </div>
    </div>

    <?php if ($t['specialization']): ?>
    <p class="text-xs text-gray-500 mb-3 bg-gray-50 rounded-lg px-3 py-2"><?= e($t['specialization']) ?></p>
    <?php endif; ?>

    <div class="flex items-center gap-2 mb-3">
        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
        </svg>
        <p class="text-xs text-gray-600"><?= e($t['dept_name'] ?? '—') ?></p>
    </div>

    <div class="grid grid-cols-2 gap-2 text-center">
        <div class="bg-orange-50 rounded-xl py-2">
            <p class="text-xl font-bold text-orange-700"><?= $t['course_count'] ?></p>
            <p class="text-xs text-gray-500">Courses</p>
        </div>
        <div class="bg-blue-50 rounded-xl py-2">
            <p class="text-xl font-bold text-blue-700"><?= $t['student_count'] ?></p>
            <p class="text-xs text-gray-500">Students</p>
        </div>
    </div>

    <div class="mt-3">
        <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $t['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
            <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
        </span>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($teachers)): ?>
<div class="col-span-full bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400">No teachers found.</p>
</div>
<?php endif; ?>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="flex justify-center gap-2 mt-5">
    <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
    <a href="?page=<?=$p?>&search=<?= urlencode($search) ?>&department_id=<?= $deptF ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-orange-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
