<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'My Students';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');
$search   = getStr('search');
$page     = getInt('page', 1);

$courses  = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title",[$tid]);
if (!$courseId && !empty($courses)) $courseId = (int)$courses[0]['id'];

$where  = "WHERE c.teacher_id=? AND e.status='active'";
$params = [$tid];
if ($courseId) { $where .= " AND c.id=?"; $params[] = $courseId; }
if ($search)   { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)"; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }

$total = Database::fetchOne(
    "SELECT COUNT(DISTINCT s.id) c FROM students s JOIN enrollments e ON e.student_id=s.id JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=s.user_id {$where}",$params)['c'];
$pag   = paginate($total, $page);

$students = Database::fetchAll(
    "SELECT DISTINCT s.id, u.first_name, u.last_name, u.email, u.avatar, u.is_active,
            s.student_id AS sid, s.year_level, s.section,
            ROUND(AVG(qa.percentage),1) avg_score,
            COUNT(DISTINCT qa.id) attempts
     FROM students s
     JOIN users u ON u.id=s.user_id
     JOIN enrollments e ON e.student_id=s.id
     JOIN courses c ON c.id=e.course_id
     LEFT JOIN quiz_attempts qa ON qa.student_id=s.id AND qa.status='completed'
     {$where}
     GROUP BY s.id
     ORDER BY u.last_name, u.first_name
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",$params
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

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">My Students</h2>
        <p class="text-sm text-gray-500"><?= $total ?> students</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <select name="course_id" class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white" onchange="this.form.submit()">
            <option value="">All Subjects</option>
            <?php foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search students…"
               class="flex-1 px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Search</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Section</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Avg Score</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Attempts</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($students as $s):
                $avatar = $s['avatar'] ? BASE_URL.'/'.$s['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($s['first_name'].' '.$s['last_name']).'&background=8b5cf6&color=fff&size=40';
                $risk   = Database::fetchOne("SELECT risk_level FROM at_risk_students WHERE student_id=? AND course_id=? AND is_resolved=0 LIMIT 1",[$s['id'],$courseId]);
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <img src="<?= e($avatar) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                        <div>
                            <p class="font-medium text-gray-800"><?= e($s['first_name'].' '.$s['last_name']) ?></p>
                            <p class="text-xs text-gray-500"><?= e($s['email']) ?></p>
                        </div>
                        <?php if ($risk): ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= riskBadgeClass($risk['risk_level']) ?>"><?= ucfirst($risk['risk_level']) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-5 py-3 text-gray-600 hidden md:table-cell"><?= e($s['section'] ?? '—') ?></td>
                <td class="px-5 py-3 text-center font-semibold <?= masteryColor((int)($s['avg_score']??0)) ?>">
                    <?= $s['avg_score'] ?? 'N/A' ?>
                </td>
                <td class="px-5 py-3 text-center text-gray-600"><?= $s['attempts'] ?></td>
                <td class="px-5 py-3 text-center hidden lg:table-cell">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $s['is_active']?'bg-green-100 text-green-700':'bg-red-100 text-red-600' ?>">
                        <?= $s['is_active']?'Active':'Inactive' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['per_page'],$total) ?> of <?= $total ?></p>
        <div class="flex gap-1">
            <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
            <a href="?page=<?=$p?>&course_id=<?=$courseId?>&search=<?= urlencode($search) ?>"
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
