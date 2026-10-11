<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Quizzes';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$courseId = getInt('course_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'toggle_publish') {
        $qid = postInt('quiz_id');
        $row = Database::fetchOne("SELECT is_published, course_id, title FROM quizzes WHERE id=? AND teacher_id=?",[$qid,$tid]);
        if ($row) {
            $new = $row['is_published'] ? 0 : 1;
            Database::update('quizzes',['is_published'=>$new],'id=?',[$qid]);
            if ($new) {
                Notification::broadcastToCourse((int)$row['course_id'],'new_quiz','New Quiz: '.$row['title'],'A new quiz is available.',BASE_URL.'/modules/student/take_quiz.php?quiz_id='.$qid);
            }
            flashSet('success','Quiz status updated.');
        }
    } elseif ($action === 'delete') {
        $qid = postInt('quiz_id');
        Database::delete('quizzes','id=? AND teacher_id=?',[$qid,$tid]);
        flashSet('success','Quiz deleted.');
    }
    redirect($_SERVER['PHP_SELF'].($courseId?"?course_id=$courseId":''));
}

// Auto-publish any scheduled quizzes whose time has arrived
Database::query(
    "UPDATE quizzes SET is_published=1
     WHERE teacher_id=? AND is_published=0 AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()",
    [$tid]
);

$where  = "WHERE q.teacher_id=?";
$params = [$tid];
if ($courseId) { $where .= " AND q.course_id=?"; $params[] = $courseId; }

$quizzes = Database::fetchAll(
    "SELECT q.*, c.title course_title, c.code course_code, l.title lesson_title,
            COUNT(DISTINCT qq.id) question_count,
            COUNT(DISTINCT qa.id) attempt_count,
            ROUND(AVG(qa.percentage),1) avg_score
     FROM quizzes q
     JOIN courses c ON c.id=q.course_id
     LEFT JOIN lessons l ON l.id=q.lesson_id
     LEFT JOIN quiz_questions qq ON qq.quiz_id=q.id
     LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
     {$where}
     GROUP BY q.id ORDER BY q.created_at DESC",$params
);

$courses = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title",[$tid]);
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

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Quizzes</h2>
        <p class="text-sm text-gray-500"><?= count($quizzes) ?> quizzes</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/modules/teacher/ai_quiz_generator.php"
           class="px-4 py-2 bg-orange-50 text-orange-700 text-sm font-medium rounded-xl hover:bg-orange-100 flex items-center gap-2">
            ✨ AI Generate
        </a>
        <a href="<?= BASE_URL ?>/modules/teacher/create_quiz.php"
           class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Quiz
        </a>
    </div>
</div>

<!-- Course Filter -->
<?php if (!$courseId): ?>
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5">
    <div class="flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php"
           class="px-3 py-1.5 rounded-xl text-sm <?= !$courseId?'bg-orange-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">All</a>
        <?php foreach ($courses as $c): ?>
        <a href="?course_id=<?= $c['id'] ?>"
           class="px-3 py-1.5 rounded-xl text-sm <?= $courseId==$c['id']?'bg-orange-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= e($c['code']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="space-y-3">
<?php foreach ($quizzes as $q): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-gray-800"><?= e($q['title']) ?></h3>
                <span class="px-2 py-0.5 rounded-full text-xs <?= $q['is_published']?'bg-green-100 text-green-700':($q['scheduled_at'] && strtotime($q['scheduled_at']) > time() ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') ?>">
                    <?= $q['is_published'] ? 'Published' : ($q['scheduled_at'] && strtotime($q['scheduled_at']) > time() ? 'Scheduled' : 'Draft') ?>
                </span>
                <?php if ($q['is_adaptive']): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700">Adaptive</span>
                <?php endif; ?>
                <?php if ($q['created_by_ai']): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">AI Generated</span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-gray-500">
                <?= e($q['course_title']) ?> — <?= e($q['lesson_title'] ?? 'No lesson') ?>
            </p>
            <p class="text-xs text-gray-400 mt-1">
                <?= $q['question_count'] ?> questions · <?= $q['attempt_count'] ?> attempts
                <?php if ($q['avg_score']): ?> · Avg: <span class="font-semibold <?= masteryColor((int)$q['avg_score']) ?>"><?= $q['avg_score'] ?>%</span><?php endif; ?>
                <?php if ($q['time_limit']): ?> · <?= $q['time_limit'] ?> min<?php endif; ?>
                <?php if ($q['deadline']): ?> · Due: <?= formatDate($q['deadline']) ?><?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="<?= BASE_URL ?>/modules/teacher/create_quiz.php?id=<?= $q['id'] ?>"
               class="px-3 py-1.5 text-xs bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200">Edit</a>
            <a href="<?= BASE_URL ?>/modules/teacher/question_bank.php?quiz_id=<?= $q['id'] ?>"
               class="px-3 py-1.5 text-xs bg-orange-50 text-orange-600 rounded-xl hover:bg-orange-100">Questions</a>
            <?php if ($q['attempt_count'] > 0): ?>
            <button onclick="toggleRanking(<?= $q['id'] ?>)"
                    class="px-3 py-1.5 text-xs bg-amber-50 text-amber-700 rounded-xl hover:bg-amber-100 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Rankings
            </button>
            <?php endif; ?>
            <form method="POST" class="inline">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                <button class="px-3 py-1.5 text-xs rounded-xl <?= $q['is_published']?'bg-red-50 text-red-600 hover:bg-red-100':'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                    <?= $q['is_published']?'Unpublish':'Publish' ?>
                </button>
            </form>
            <form method="POST" id="delQ-<?= $q['id'] ?>" class="inline">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                <button type="button" onclick="confirmDelete('Delete this quiz and all its questions?','delQ-<?= $q['id'] ?>')"
                        class="px-3 py-1.5 text-xs bg-red-50 text-red-600 rounded-xl hover:bg-red-100">Delete</button>
            </form>
        </div>
    </div>

    <!-- Rankings Panel (hidden by default) -->
    <div id="ranking-<?= $q['id'] ?>" class="hidden mt-4 border-t border-gray-100 pt-4">
        <?php
        $rankings = Database::fetchAll(
            "SELECT u.first_name, u.last_name, s.student_id,
                    MAX(qa.percentage)   best_score,
                    MAX(qa.score)        best_points,
                    COUNT(qa.id)         attempts,
                    MIN(qa.submitted_at) first_attempt,
                    MAX(qa.submitted_at) last_attempt
             FROM quiz_attempts qa
             JOIN students s   ON s.id  = qa.student_id
             JOIN users u      ON u.id  = s.user_id
             WHERE qa.quiz_id = ? AND qa.status = 'completed'
             GROUP BY qa.student_id
             ORDER BY best_score DESC, best_points DESC, first_attempt ASC",
            [$q['id']]
        );
        ?>
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">
                Student Rankings — <?= e($q['title']) ?>
            </p>
            <span class="text-xs text-gray-400"><?= count($rankings) ?> student<?= count($rankings) != 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($rankings)): ?>
        <p class="text-xs text-gray-400 py-2">No completed attempts yet.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-2 text-left rounded-l-xl w-10">Rank</th>
                        <th class="px-3 py-2 text-left">Student</th>
                        <th class="px-3 py-2 text-left">ID</th>
                        <th class="px-3 py-2 text-center">Best Score</th>
                        <th class="px-3 py-2 text-center">Points</th>
                        <th class="px-3 py-2 text-center">Attempts</th>
                        <th class="px-3 py-2 text-left rounded-r-xl">Last Taken</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php foreach ($rankings as $rank => $r):
                    $medal = match($rank) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '#'.($rank+1) };
                    $passed = (float)$r['best_score'] >= (float)$q['passing_score'];
                ?>
                <tr class="hover:bg-gray-50 transition <?= $rank < 3 ? 'font-medium' : '' ?>">
                    <td class="px-3 py-2.5 text-center text-base"><?= $medal ?></td>
                    <td class="px-3 py-2.5 text-gray-800">
                        <?= e($r['first_name'] . ' ' . $r['last_name']) ?>
                    </td>
                    <td class="px-3 py-2.5 text-gray-500 font-mono text-xs">
                        <?= e($r['student_id'] ?? '—') ?>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                            <?= $passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= number_format((float)$r['best_score'], 1) ?>%
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-700 font-semibold">
                        <?= $r['best_points'] ?>
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-500"><?= $r['attempts'] ?></td>
                    <td class="px-3 py-2.5 text-gray-400 text-xs"><?= formatDate($r['last_attempt'], 'M d, Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($quizzes)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400 mb-4">No quizzes yet. Create one or use the AI generator.</p>
    <div class="flex gap-3 justify-center">
        <a href="<?= BASE_URL ?>/modules/teacher/create_quiz.php" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Create Manually</a>
        <a href="<?= BASE_URL ?>/modules/teacher/ai_quiz_generator.php" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-700">AI Generator</a>
    </div>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function toggleRanking(quizId) {
    const el = document.getElementById('ranking-' + quizId);
    if (el) el.classList.toggle('hidden');
}
</script>
</body>
</html>
