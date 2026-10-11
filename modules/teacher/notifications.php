<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    if (postStr('action') === 'mark_all_read') {
        Notification::markAllRead(Auth::id());
        flashSet('success','All marked as read.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$page   = getInt('page', 1);
$total  = Database::fetchOne("SELECT COUNT(*) c FROM notifications WHERE user_id=?",[Auth::id()])['c'];
$pag    = paginate($total, $page, 20);
$notifs = Database::fetchAll(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    [Auth::id()]
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
    <h2 class="text-xl font-bold text-gray-800">Notifications</h2>
    <form method="POST">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200">Mark All Read</button>
    </form>
</div>

<div class="space-y-2">
<?php foreach ($notifs as $n): ?>
<div class="bg-white rounded-2xl p-4 shadow-sm border <?= $n['is_read']?'border-gray-100':'border-orange-200' ?> flex items-start gap-3">
    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0 text-lg"><?= Notification::icon($n['type']) ?></div>
    <div class="flex-1">
        <p class="text-sm font-semibold text-gray-800 <?= !$n['is_read']?'text-orange-700':'' ?>"><?= e($n['title']) ?></p>
        <p class="text-sm text-gray-600 mt-0.5"><?= e($n['message']) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
    </div>
    <?php if (!$n['is_read']): ?><span class="w-2 h-2 bg-orange-500 rounded-full mt-2 flex-shrink-0"></span><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (empty($notifs)): ?>
<div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400">No notifications.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
