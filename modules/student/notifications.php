<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');
    if ($action === 'read') {
        Notification::markRead(postInt('notif_id'), Auth::id());
    } elseif ($action === 'read_all') {
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
$unread = unreadNotifCount(Auth::id());
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Notifications</h2>
        <?php if ($unread > 0): ?>
        <p class="text-sm text-blue-600 font-medium"><?= $unread ?> unread</p>
        <?php endif; ?>
    </div>
    <?php if ($unread > 0): ?>
    <form method="POST">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="read_all">
        <button type="submit" class="px-4 py-2 bg-blue-50 text-blue-600 text-sm font-medium rounded-xl hover:bg-blue-100">
            Mark All Read
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="space-y-2">
<?php foreach ($notifs as $n): ?>
<div class="bg-white rounded-2xl p-4 shadow-sm border <?= $n['is_read']?'border-gray-100':'border-blue-200' ?> flex items-start gap-3">
    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0 text-lg">
        <?= Notification::icon($n['type']) ?>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-800 <?= !$n['is_read']?'text-blue-700':'' ?>"><?= e($n['title']) ?></p>
        <p class="text-sm text-gray-600 mt-0.5"><?= e($n['message']) ?></p>
        <div class="flex items-center gap-3 mt-1">
            <p class="text-xs text-gray-400"><?= timeAgo($n['created_at']) ?></p>
            <?php if ($n['link']): ?>
            <a href="<?= e($n['link']) ?>" class="text-xs text-blue-600 hover:underline">View</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$n['is_read']): ?>
    <div class="flex-shrink-0">
        <form method="POST">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="read">
            <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
            <button type="submit" class="w-2 h-2 bg-blue-500 rounded-full hover:bg-blue-600 mt-1" title="Mark read"></button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (empty($notifs)): ?>
<div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
    <div class="text-4xl mb-3">🔔</div>
    <p class="text-gray-400">No notifications yet.</p>
</div>
<?php endif; ?>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="flex justify-center gap-2 mt-5">
    <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
    <a href="?page=<?=$p?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-blue-600 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
