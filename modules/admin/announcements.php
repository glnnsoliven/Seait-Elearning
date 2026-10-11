<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'create') {
        $title    = postStr('title');
        $content  = postStr('content');
        $courseId = postInt('course_id') ?: null;

        if (empty($title) || empty($content)) {
            flashSet('error', 'Title and content are required.');
        } else {
            Database::insert('announcements', [
                'author_id' => Auth::id(),
                'course_id' => $courseId,
                'title'     => $title,
                'content'   => $content,
                'is_active' => 1,
            ]);
            // Send in-app notification to relevant users
            if ($courseId) {
                Notification::broadcastToCourse($courseId, 'announcement', $title,
                    substr($content, 0, 150), BASE_URL . '/modules/student/dashboard.php');
            } else {
                // Broadcast to all students and teachers
                $users = Database::fetchAll("SELECT id FROM users WHERE is_active=1 AND role IN ('student','teacher')");
                foreach ($users as $u) {
                    Notification::send((int)$u['id'], 'announcement', $title,
                        substr($content, 0, 150), BASE_URL . '/modules/student/dashboard.php');
                }
            }
            AuditLog::write(Auth::id(), 'create_announcement', 'announcements', "Created: $title");
            flashSet('success', 'Announcement posted and notification sent.');
        }
    } elseif ($action === 'delete') {
        $id = postInt('announcement_id');
        Database::update('announcements', ['is_active' => 0], 'id=?', [$id]);
        flashSet('success', 'Announcement removed.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$page  = getInt('page', 1);
$pag   = paginate(
    Database::fetchOne("SELECT COUNT(*) c FROM announcements")['c'],
    $page, 15
);
$announcements = Database::fetchAll(
    "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) author_name,
            c.title course_title, c.code course_code
     FROM announcements a
     JOIN users u ON u.id = a.author_id
     LEFT JOIN courses c ON c.id = a.course_id
     ORDER BY a.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}"
);

$courses = Database::fetchAll("SELECT id,code,title FROM courses WHERE is_active=1 ORDER BY code");
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
        <h2 class="text-xl font-bold text-gray-800">Announcements</h2>
        <p class="text-sm text-gray-500">Broadcast messages to students and teachers</p>
    </div>
    <button onclick="openModal('announceModal')"
            class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
        </svg>
        New Announcement
    </button>
</div>

<!-- Announcements list -->
<?php if (empty($announcements)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <div class="text-5xl mb-3">📢</div>
    <p class="text-gray-500 font-medium">No announcements yet.</p>
    <p class="text-gray-400 text-sm mt-1">Click "New Announcement" to post your first message.</p>
</div>
<?php else: ?>
<div class="space-y-3">
<?php foreach ($announcements as $a): ?>
<div class="bg-white rounded-2xl shadow-sm border <?= $a['is_active'] ? 'border-gray-100' : 'border-gray-200 opacity-60' ?> p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-4 flex-1 min-w-0">
            <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0 text-xl">
                📢
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h3 class="font-semibold text-gray-800"><?= e($a['title']) ?></h3>
                    <?php if ($a['course_id']): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">
                        📚 <?= e($a['course_code'] . ' — ' . $a['course_title']) ?>
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700">🌐 Institution-wide</span>
                    <?php endif; ?>
                    <?php if (!$a['is_active']): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Archived</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600 line-clamp-2"><?= e($a['content']) ?></p>
                <p class="text-xs text-gray-400 mt-1.5">
                    By <span class="font-medium text-gray-600"><?= e($a['author_name']) ?></span>
                    · <?= timeAgo($a['created_at']) ?>
                </p>
            </div>
        </div>
        <?php if ($a['is_active']): ?>
        <form method="POST" id="delAnn-<?= $a['id'] ?>" class="flex-shrink-0">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
            <button type="button"
                    onclick="confirmDelete('Remove this announcement?','delAnn-<?= $a['id'] ?>')"
                    class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="flex justify-center gap-2 mt-5">
    <?php for ($p = 1; $p <= $pag['total_pages']; $p++): ?>
    <a href="?page=<?= $p ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
              <?= $p === $pag['current'] ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</main>
</div>

<!-- New Announcement Modal -->
<div id="announceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#e67e22,#d35400)">
            <h3 class="font-bold text-white text-lg">New Announcement</h3>
            <button onclick="closeModal('announceModal')" class="text-white/70 hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Send To</label>
                <select name="course_id"
                        class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">🌐 All Students &amp; Teachers (Institution-wide)</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>">📚 <?= e($c['code'] . ' — ' . $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Title *</label>
                <input type="text" name="title" required placeholder="Announcement title…"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Message *</label>
                <textarea name="content" rows="5" required placeholder="Write your announcement here…"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-bold rounded-xl hover:bg-orange-600 transition">
                    📢 Post Announcement
                </button>
                <button type="button" onclick="closeModal('announceModal')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
