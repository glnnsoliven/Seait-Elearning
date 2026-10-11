<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Departments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'create' || $action === 'edit') {
        $name = postStr('name');
        $id   = postInt('dept_id');

        if (empty($name)) {
            flashSet('error', 'Department name is required.');
        } else {
            // Auto-generate code from name initials
            $words = preg_split('/\s+/', strtoupper($name));
            $code  = implode('', array_map(fn($w) => $w[0] ?? '', $words));
            if (strlen($code) < 2) $code = strtoupper(substr($name, 0, 4));

            if ($action === 'create') {
                // Ensure unique code
                $base = $code; $suffix = 1;
                while (Database::fetchOne("SELECT id FROM departments WHERE code=?", [$code])) {
                    $code = $base . $suffix++;
                }
                Database::insert('departments', ['name'=>$name,'code'=>$code,'description'=>'']);
                AuditLog::write(Auth::id(), 'create_department', 'departments', "Created: $name");
                flashSet('success', 'Department created.');
            } else {
                Database::update('departments', ['name'=>$name], 'id=?', [$id]);
                AuditLog::write(Auth::id(), 'edit_department', 'departments', "Edited ID: $id");
                flashSet('success', 'Department updated.');
            }
        }
    } elseif ($action === 'delete') {
        $id = postInt('dept_id');
        Database::update('departments', ['is_active'=>0], 'id=?', [$id]);
        flashSet('success', 'Department deactivated.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$departments = Database::fetchAll(
    "SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS head_name,
            COUNT(DISTINCT s.id) AS student_count,
            COUNT(DISTINCT t.id) AS teacher_count,
            COUNT(DISTINCT c.id) AS course_count
     FROM departments d
     LEFT JOIN teachers th ON th.id=d.head_id
     LEFT JOIN users u ON u.id=th.user_id
     LEFT JOIN students s ON s.department_id=d.id
     LEFT JOIN teachers t ON t.department_id=d.id
     LEFT JOIN courses c ON c.department_id=d.id
     GROUP BY d.id ORDER BY d.name"
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
        <h2 class="text-xl font-bold text-gray-800">Departments</h2>
        <p class="text-sm text-gray-500"><?= count($departments) ?> departments</p>
    </div>
    <button onclick="openModal('deptModal')" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Department
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($departments as $d): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
            <span class="text-orange-700 font-bold text-sm"><?= e(substr($d['code'],0,2)) ?></span>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $d['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
            <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
        </span>
    </div>
    <h3 class="font-semibold text-gray-800 mb-0.5"><?= e($d['name']) ?></h3>
    <p class="text-xs text-gray-500 mb-3"><?= e($d['code']) ?> · <?= e($d['description'] ?? 'No description') ?></p>

    <div class="grid grid-cols-3 gap-2 mb-4 text-center">
        <div class="bg-gray-50 rounded-xl py-2">
            <p class="text-lg font-bold text-gray-800"><?= $d['student_count'] ?></p>
            <p class="text-xs text-gray-500">Students</p>
        </div>
        <div class="bg-gray-50 rounded-xl py-2">
            <p class="text-lg font-bold text-gray-800"><?= $d['teacher_count'] ?></p>
            <p class="text-xs text-gray-500">Teachers</p>
        </div>
        <div class="bg-gray-50 rounded-xl py-2">
            <p class="text-lg font-bold text-gray-800"><?= $d['course_count'] ?></p>
            <p class="text-xs text-gray-500">Courses</p>
        </div>
    </div>

    <div class="flex gap-2">
        <button onclick='editDept(<?= json_encode($d) ?>)'
                class="flex-1 py-1.5 text-xs bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition">Edit</button>
        <form method="POST" id="delDept-<?= $d['id'] ?>" class="flex-1">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
            <button type="button" onclick="confirmDelete('Deactivate this department?','delDept-<?= $d['id'] ?>')"
                    class="w-full py-1.5 text-xs bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                Deactivate
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>

</main>
</div>

<!-- Dept Modal -->
<div id="deptModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="deptModalTitle" class="font-semibold text-gray-800">Add Department</h3>
            <button onclick="closeModal('deptModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" id="deptForm" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" id="deptAction" value="create">
            <input type="hidden" name="dept_id" id="deptId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department Name *</label>
                <input type="text" name="name" id="deptName" required
                       placeholder="e.g. College of Computer Studies"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                <p class="text-xs text-gray-400 mt-1">The code will be auto-generated from the name initials.</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Save</button>
                <button type="button" onclick="closeModal('deptModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function editDept(d) {
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    document.getElementById('deptAction').value = 'edit';
    document.getElementById('deptId').value   = d.id;
    document.getElementById('deptName').value = d.name;
    openModal('deptModal');
}
</script>
</body>
</html>
