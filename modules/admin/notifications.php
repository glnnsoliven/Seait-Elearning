<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'broadcast') {
        $title   = postStr('title');
        $message = postStr('message');
        $type    = postStr('type') ?: 'announcement';
        $target  = postStr('target');

        if (!empty($title) && !empty($message)) {
            $users = match ($target) {
                'students' => Database::fetchAll("SELECT id FROM users WHERE role='student' AND is_active=1"),
                'teachers' => Database::fetchAll("SELECT id FROM users WHERE role='teacher' AND is_active=1"),
                default    => Database::fetchAll("SELECT id FROM users WHERE is_active=1"),
            };
            foreach ($users as $u) {
                Notification::send((int)$u['id'], $type, $title, $message);
            }
            // Save to announcements table so admin can see sent broadcasts
            Database::insert('announcements', [
                'author_id' => Auth::id(),
                'course_id' => null,
                'title'     => $title,
                'content'   => $message,
                'is_active' => 1,
            ]);
            AuditLog::write(Auth::id(), 'broadcast_notification', 'notifications', "Sent to {$target}: {$title}");
            flashSet('success', '✅ Notification broadcast to ' . count($users) . ' users.');
        } else {
            flashSet('error', 'Title and message are required.');
        }
    } elseif ($action === 'mark_all_read') {
        Notification::markAllRead(Auth::id());
        flashSet('success', 'All notifications marked as read.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$page   = getInt('page', 1);
$typeF  = getStr('type');
$where  = $typeF ? "AND type=?" : "";
$params = $typeF ? [$typeF] : [];

$total   = Database::fetchOne("SELECT COUNT(*) c FROM notifications WHERE user_id=? {$where}", array_merge([Auth::id()], $params))['c'];
$pag     = paginate($total, $page, 20);
$notifs  = Database::fetchAll(
    "SELECT * FROM notifications WHERE user_id=? {$where} ORDER BY created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    array_merge([Auth::id()], $params)
);

$notifTypes = ['quiz_deadline','assignment','new_lesson','new_quiz','quiz_result','achievement','study_reminder','announcement','system'];
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
        <h2 class="text-xl font-bold text-gray-800">Notifications</h2>
        <p class="text-sm text-gray-500"><?= $total ?> total notifications</p>
    </div>
    <div class="flex gap-2">
        <form method="POST" class="inline">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200">Mark All Read</button>
        </form>
        <button onclick="openModal('broadcastModal')" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            Broadcast
        </button>
    </div>
</div>

<!-- Filter -->
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <form method="GET" class="flex gap-3">
        <select name="type" class="px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
            <option value="">All Types</option>
            <?php foreach ($notifTypes as $t): ?>
            <option value="<?= $t ?>" <?= $typeF===$t?'selected':''?>><?= ucwords(str_replace('_',' ',$t)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-700">Filter</button>
    </form>
</div>

<!-- Notifications List -->
<div class="space-y-2">
<?php foreach ($notifs as $n): ?>
<div class="bg-white rounded-2xl p-4 shadow-sm border <?= $n['is_read'] ? 'border-gray-100' : 'border-orange-200' ?> flex items-start gap-4">
    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0 text-lg">
        <?= Notification::icon($n['type']) ?>
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-gray-800 <?= !$n['is_read'] ? 'text-orange-700' : '' ?>"><?= e($n['title']) ?></p>
                <p class="text-sm text-gray-600 mt-0.5"><?= e($n['message']) ?></p>
            </div>
            <?php if (!$n['is_read']): ?>
            <span class="w-2 h-2 rounded-full bg-orange-500 mt-1 flex-shrink-0"></span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-gray-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($notifs)): ?>
<div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400">No notifications found.</p>
</div>
<?php endif; ?>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="flex justify-center gap-2 mt-5">
    <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
    <a href="?page=<?=$p?>&type=<?= urlencode($typeF) ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-orange-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?=$p?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</main>
</div>

<!-- Broadcast Modal -->
<div id="broadcastModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Broadcast Notification</h3>
            <button onclick="closeModal('broadcastModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="broadcast">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Send To</label>
                <select name="target" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <option value="all">All Users</option>
                    <option value="students">Students Only</option>
                    <option value="teachers">Teachers Only</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <?php foreach ($notifTypes as $t): ?>
                    <option value="<?= $t ?>"><?= ucwords(str_replace('_',' ',$t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea name="message" rows="3" required
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-700">Broadcast</button>
                <button type="button" onclick="closeModal('broadcastModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
