<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'User Management';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'toggle_active') {
        $uid = postInt('user_id');
        $row = Database::fetchOne("SELECT is_active FROM users WHERE id=?", [$uid]);
        if ($row) {
            $newState = $row['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newState], 'id=?', [$uid]);
            AuditLog::write(Auth::id(), $newState ? 'activate_user' : 'deactivate_user', 'users', "User ID {$uid}");
            flashSet('success', 'User status updated.');
        }
    } elseif ($action === 'delete') {
        $uid = postInt('user_id');
        Database::delete('users', 'id=? AND role != "admin"', [$uid]);
        AuditLog::write(Auth::id(), 'delete_user', 'users', "Deleted user ID {$uid}");
        flashSet('success', 'User deleted.');
    } elseif ($action === 'create') {
        $errors = [];
        $fn  = postStr('first_name');
        $ln  = postStr('last_name');
        $em  = postStr('email');
        $un  = postStr('username');
        $pw  = $_POST['password'] ?? '';
        $role= postStr('role');

        if (!in_array($role, ['admin','teacher','student'])) $errors[] = 'Invalid role.';
        if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (Database::fetchOne("SELECT id FROM users WHERE email=?", [$em])) $errors[] = 'Email already in use.';
        if (Database::fetchOne("SELECT id FROM users WHERE username=?", [$un])) $errors[] = 'Username already in use.';

        if (empty($errors)) {
            Database::beginTransaction();
            try {
                $hash   = password_hash($pw, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $userId = Database::insert('users', ['username'=>$un,'email'=>$em,'password'=>$hash,'role'=>$role,'first_name'=>$fn,'last_name'=>$ln,'is_active'=>1]);
                if ($role === 'student')  Database::insert('students', ['user_id'=>$userId]);
                if ($role === 'teacher')  Database::insert('teachers', ['user_id'=>$userId]);
                Database::commit();
                AuditLog::write(Auth::id(), 'create_user', 'users', "Created {$role}: {$em}");
                flashSet('success', 'User created successfully.');
            } catch (Throwable $e) {
                Database::rollback();
                flashSet('error', 'Failed to create user. ' . ($e->getMessage()));
            }
        } else {
            foreach ($errors as $e) flashSet('error', $e);
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

// ── Filters & Pagination ──────────────────────────────────────
$search = getStr('search');
$roleF  = isset($_GET['role']) ? trim($_GET['role']) : '';
$page   = getInt('page', 1);

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where   .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if (in_array($roleF, ['admin','teacher','student'], true)) {
    $where   .= " AND u.role = ?";
    $params[] = $roleF;
} else {
    $roleF = '';
}

$total = Database::fetchOne("SELECT COUNT(*) c FROM users u {$where}", $params)['c'];
$pag   = paginate($total, $page);
$users = Database::fetchAll(
    "SELECT u.*, 
            COALESCE(s.student_id, t.employee_id) AS id_number,
            d.name AS department
     FROM users u
     LEFT JOIN students s ON s.user_id=u.id
     LEFT JOIN teachers t ON t.user_id=u.id
     LEFT JOIN departments d ON d.id=COALESCE(s.department_id,t.department_id)
     {$where}
     ORDER BY u.created_at DESC
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

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-800">User Management</h2>
        <p class="text-sm text-gray-500"><?= $total ?> total users</p>
    </div>
    <button onclick="openModal('createUserModal')"
            class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add User
    </button>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, username…"
               class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Search</button>
        <?php if ($search || $roleF): ?>
        <a href="<?= BASE_URL ?>/modules/admin/users.php" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200 flex items-center">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Role filter tabs -->
<div class="flex gap-2 mb-5 overflow-x-auto pb-1">
    <?php foreach ([
        ['' ,       'All Users',  count(Database::fetchAll("SELECT id FROM users WHERE 1=1"))],
        ['admin',   'Admins',     count(Database::fetchAll("SELECT id FROM users WHERE role='admin'"))],
        ['teacher', 'Teachers',   count(Database::fetchAll("SELECT id FROM users WHERE role='teacher'"))],
        ['student', 'Students',   count(Database::fetchAll("SELECT id FROM users WHERE role='student'"))],
    ] as [$rv, $rl, $rc]): ?>
    <a href="?role=<?= $rv ?>&search=<?= urlencode($search) ?>"
       class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?= $roleF === $rv ? 'bg-orange-500 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= $rl ?>
        <span class="px-1.5 py-0.5 rounded-full text-xs <?= $roleF === $rv ? 'bg-white/30 text-white' : 'bg-gray-100 text-gray-500' ?>">
            <?= $rc ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Department</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Joined</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php if (empty($users)): ?>
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($users as $u):
                $avatar = $u['avatar']
                    ? BASE_URL . '/' . $u['avatar']
                    : 'https://ui-avatars.com/api/?name=' . urlencode($u['first_name'].' '.$u['last_name']) . '&background=6366f1&color=fff&size=40';
            ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="<?= e($avatar) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                            <div>
                                <p class="font-medium text-gray-800"><?= e($u['first_name'].' '.$u['last_name']) ?></p>
                                <p class="text-xs text-gray-500"><?= e($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium
                            <?= $u['role']==='admin' ? 'bg-orange-100 text-orange-700' :
                                ($u['role']==='teacher' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 hidden md:table-cell"><?= e($u['department'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell"><?= formatDate($u['created_at']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium
                            <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <!-- Toggle -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="px-2.5 py-1 text-xs rounded-lg <?= $u['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' ?> transition">
                                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <!-- Delete (non-admin only) -->
                            <?php if ($u['role'] !== 'admin' || $u['id'] !== Auth::id()): ?>
                            <form method="POST" id="del-<?= $u['id'] ?>" class="inline">
                                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="button"
                                        onclick="confirmDelete('Delete this user permanently?','del-<?= $u['id'] ?>')"
                                        class="px-2.5 py-1 text-xs rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                                    Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">
            Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['per_page'], $total) ?> of <?= $total ?>
        </p>
        <div class="flex gap-1">
            <?php for ($p = 1; $p <= $pag['total_pages']; $p++): ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleF) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                      <?= $p === $pag['current'] ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</main>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Create New User</h3>
            <button onclick="closeModal('createUserModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" name="first_name" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 transition">
                    Create User
                </button>
                <button type="button" onclick="closeModal('createUserModal')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
