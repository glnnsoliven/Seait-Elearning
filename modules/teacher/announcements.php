<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Announcements';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$courses   = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1",[$tid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'save') {
        $aid      = postInt('announcement_id');
        $title    = postStr('title');
        $content  = postStr('content');
        $courseId = postInt('course_id') ?: null;

        if (empty($title) || empty($content)) {
            flashSet('error','Title and content are required.');
        } else {
            if ($aid) {
                Database::update('announcements',['title'=>$title,'content'=>$content,'course_id'=>$courseId],'id=? AND author_id=?',[$aid,Auth::id()]);
                flashSet('success','Announcement updated.');
            } else {
                $newId = Database::insert('announcements',['author_id'=>Auth::id(),'course_id'=>$courseId,'title'=>$title,'content'=>$content]);
                if ($courseId) {
                    Notification::broadcastToCourse($courseId,'announcement',$title,$content,BASE_URL.'/modules/student/notifications.php');
                }
                AuditLog::write(Auth::id(),'create_announcement','announcements',$title);
                flashSet('success','Announcement posted.');
            }
        }
    } elseif ($action === 'delete') {
        $aid = postInt('announcement_id');
        Database::update('announcements',['is_active'=>0],'id=? AND author_id=?',[$aid,Auth::id()]);
        flashSet('success','Announcement removed.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$announcements = Database::fetchAll(
    "SELECT a.*, c.title course_title, c.code course_code
     FROM announcements a LEFT JOIN courses c ON c.id=a.course_id
     WHERE a.author_id=? AND a.is_active=1
     ORDER BY a.created_at DESC",[Auth::id()]
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
        <h2 class="text-xl font-bold text-gray-800">Announcements</h2>
        <p class="text-sm text-gray-500"><?= count($announcements) ?> announcements</p>
    </div>
    <button onclick="openModal('annModal')" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Announcement
    </button>
</div>

<div class="space-y-4">
<?php foreach ($announcements as $a): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-gray-800"><?= e($a['title']) ?></h3>
                <?php if ($a['course_title']): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700"><?= e($a['course_code']) ?></span>
                <?php else: ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">All Subjects</span>
                <?php endif; ?>
            </div>
            <p class="text-sm text-gray-600 mt-1"><?= nl2br_e($a['content']) ?></p>
            <p class="text-xs text-gray-400 mt-2"><?= timeAgo($a['created_at']) ?></p>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <button onclick='editAnn(<?= htmlspecialchars(json_encode($a)) ?>)'
                    class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <form method="POST" id="delAnn-<?= $a['id'] ?>" class="inline">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                <button type="button" onclick="confirmDelete('Delete this announcement?','delAnn-<?= $a['id'] ?>')"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($announcements)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400">No announcements yet.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>

<!-- Modal -->
<div id="annModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="annModalTitle" class="font-semibold text-gray-800">New Announcement</h3>
            <button onclick="closeModal('annModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="announcement_id" id="annId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" id="annTitle" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <select name="course_id" id="annCourse" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <option value="">All My Subjects</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['code'].' – '.$c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                <textarea name="content" id="annContent" rows="4" required
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Post</button>
                <button type="button" onclick="closeModal('annModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function editAnn(a) {
    document.getElementById('annModalTitle').textContent = 'Edit Announcement';
    document.getElementById('annId').value     = a.id;
    document.getElementById('annTitle').value  = a.title;
    document.getElementById('annContent').value= a.content;
    document.getElementById('annCourse').value = a.course_id ?? '';
    openModal('annModal');
}
</script>
</body>
</html>
