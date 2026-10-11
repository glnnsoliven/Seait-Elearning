<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Student Management';

$search = getStr('search');
$deptF  = getInt('department_id');
$page   = getInt('page', 1);

$where  = "WHERE u.role='student'";
$params = [];
if ($search) {
    $where   .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ?)";
    $params   = ["%$search%","%$search%","%$search%","%$search%"];
}
if ($deptF) { $where .= " AND s.department_id=?"; $params[] = $deptF; }

$total    = Database::fetchOne("SELECT COUNT(*) c FROM users u JOIN students s ON s.user_id=u.id {$where}", $params)['c'];
$pag      = paginate($total, $page);
$depts    = Database::fetchAll("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name");

$students = Database::fetchAll(
    "SELECT u.*, s.student_id AS sid, s.year_level, s.section,
            d.name AS dept_name,
            COUNT(DISTINCT e.course_id) AS enrolled_courses,
            ROUND(AVG(qa.percentage),1) AS avg_score
     FROM users u
     JOIN students s ON s.user_id=u.id
     LEFT JOIN departments d ON d.id=s.department_id
     LEFT JOIN enrollments e ON e.student_id=s.id AND e.status='active'
     LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
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
        <h2 class="text-xl font-bold text-gray-800">Students</h2>
        <p class="text-sm text-gray-500"><?= $total ?> students enrolled</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, student ID…"
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

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Student ID</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Department</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Courses</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Avg Score</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($students as $s):
                $avatar = $s['avatar']
                    ? BASE_URL.'/'.$s['avatar']
                    : 'https://ui-avatars.com/api/?name='.urlencode($s['first_name'].' '.$s['last_name']).'&background=3b82f6&color=fff&size=40';
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <img src="<?= e($avatar) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                        <div>
                            <p class="font-medium text-gray-800"><?= e($s['first_name'].' '.$s['last_name']) ?></p>
                            <p class="text-xs text-gray-500"><?= e($s['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3 text-gray-600 hidden md:table-cell"><?= e($s['sid'] ?? '—') ?></td>
                <td class="px-5 py-3 text-gray-600 hidden md:table-cell"><?= e($s['dept_name'] ?? '—') ?></td>
                <td class="px-5 py-3 text-center font-semibold text-gray-700"><?= $s['enrolled_courses'] ?></td>
                <td class="px-5 py-3 text-center hidden lg:table-cell">
                    <span class="font-semibold <?= masteryColor((int)($s['avg_score']??0)) ?>"><?= $s['avg_score'] ?? 'N/A' ?>%</span>
                </td>
                <td class="px-5 py-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $s['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
            <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pag['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['per_page'],$total) ?> of <?= $total ?></p>
        <div class="flex gap-1">
            <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
            <a href="?page=<?=$p?>&search=<?= urlencode($search) ?>&department_id=<?= $deptF ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-orange-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
