<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Create Quiz';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$courses   = Database::fetchAll("SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title",[$tid]);

$editId  = getInt('id');
$editing = $editId > 0;
$quiz    = $editing ? Database::fetchOne("SELECT * FROM quizzes WHERE id=? AND teacher_id=?",[$editId,$tid]) : null;
if ($editing && !$quiz) { flashSet('error','Quiz not found.'); redirect(BASE_URL.'/modules/teacher/quizzes.php'); }

$errors        = [];

// Helper: auto-publish logic based on scheduled_at
// If scheduled_at is set and is now or in the past → publish immediately
// If scheduled_at is in the future → save as unpublished, will auto-publish when time comes
function shouldAutoPublish(?string $scheduledAt): int {
    if (!$scheduledAt) return 0;
    return strtotime($scheduledAt) <= time() ? 1 : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $courseId   = postInt('course_id');
    $lessonId   = postInt('lesson_id') ?: null;
    $title      = postStr('title');
    $desc       = postStr('description');
    $instr      = postStr('instructions');
    $timeLimit  = postInt('time_limit') ?: null;
    $passing    = postInt('passing_score');
    $maxAtt     = postInt('max_attempts') ?: 3;
    $shuffle_q  = postInt('shuffle_questions') ? 1 : 0;
    $shuffle_c  = postInt('shuffle_choices')   ? 1 : 0;
    $adaptive   = postInt('is_adaptive')       ? 1 : 0;
    $scheduled  = postStr('scheduled_at') ?: null;
    $deadline   = postStr('deadline')     ?: null;

    // Sections
    $sectionNames  = $_POST['section_name']  ?? [];
    $sectionTypes  = $_POST['section_type']  ?? [];
    $sectionCounts = $_POST['section_count'] ?? [];

    if (!$courseId) $errors[] = 'Course is required.';
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($sectionNames) || empty(array_filter($sectionNames))) {
        $errors[] = 'At least one quiz section is required.';
    }

    if (empty($errors)) {
        // Auto-publish if scheduled time has already arrived
        $isPublished = shouldAutoPublish($scheduled);

        $data = [
            'course_id'=>$courseId,'lesson_id'=>$lessonId,'teacher_id'=>$tid,
            'title'=>$title,'description'=>$desc,'instructions'=>$instr,
            'time_limit'=>$timeLimit,'passing_score'=>$passing,'max_attempts'=>$maxAtt,
            'shuffle_questions'=>$shuffle_q,'shuffle_choices'=>$shuffle_c,
            'is_adaptive'=>$adaptive,'scheduled_at'=>$scheduled,'deadline'=>$deadline,
            'is_published'=>$isPublished,
        ];
        if ($editing) {
            unset($data['teacher_id']);
            Database::update('quizzes',$data,'id=?',[$editId]);
            AuditLog::write(Auth::id(),'update_quiz','quizzes',"Updated: $title");
            
            // Delete old sections then insert new ones
            Database::delete('quiz_sections','quiz_id=?',[$editId]);
            foreach ($sectionNames as $i => $name) {
                if (empty(trim($name))) continue;
                Database::insert('quiz_sections',[
                    'quiz_id'=>$editId,
                    'name'=>trim($name),
                    'question_type'=>$sectionTypes[$i] ?? 'multiple_choice',
                    'question_count'=>(int)($sectionCounts[$i] ?? 10),
                    'sort_order'=>$i,
                ]);
            }
            
            $msg = $isPublished ? 'Quiz updated and published.' : ($scheduled ? 'Quiz updated. Will auto-publish at scheduled time.' : 'Quiz updated.');
            flashSet('success', $msg);
            redirect(BASE_URL.'/modules/teacher/question_bank.php?quiz_id='.$editId);
        } else {
            $qid = Database::insert('quizzes',$data);
            
            // Insert sections and let question_bank auto-create placeholders
            foreach ($sectionNames as $i => $name) {
                if (empty(trim($name))) continue;
                Database::insert('quiz_sections',[
                    'quiz_id'=>$qid,
                    'name'=>trim($name),
                    'question_type'=>$sectionTypes[$i] ?? 'multiple_choice',
                    'question_count'=>(int)($sectionCounts[$i] ?? 10),
                    'sort_order'=>$i,
                ]);
            }
            
            AuditLog::write(Auth::id(),'create_quiz','quizzes',"Created: $title");
            $msg = $isPublished ? 'Quiz created and published!' : ($scheduled ? 'Quiz created. Will auto-publish at the scheduled time.' : 'Quiz saved as draft. Add questions now.');
            flashSet('success', $msg);
            redirect(BASE_URL.'/modules/teacher/question_bank.php?quiz_id='.$qid);
        }
    }
}

$f = $quiz ?? $_POST ?? [];
$lessons = [];
if (!empty($f['course_id'])) {
    $lessons = Database::fetchAll("SELECT id,title FROM lessons WHERE course_id=? ORDER BY sort_order",[(int)($f['course_id'])]);
}

// Load existing sections when editing
$existingSections = [];
if ($editing && $editId) {
    $existingSections = Database::fetchAll("SELECT * FROM quiz_sections WHERE quiz_id=? ORDER BY sort_order",[$editId]);
}

// When editing, fetch current join code to display
$editJoinCode = ($editing && $quiz) ? ($quiz['join_code'] ?? '') : '';
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

<?php if ($editing): ?>
<!-- Edit mode: show schedule status -->
<?php if ($quiz && $quiz['scheduled_at']): ?>
<div class="mb-4 max-w-2xl flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <p class="text-sm text-blue-700">
        <?php if (strtotime($quiz['scheduled_at']) <= time()): ?>
        This quiz is <strong>published</strong> (started <?= formatDate($quiz['scheduled_at'], 'M d, Y g:i A') ?>)
        <?php else: ?>
        This quiz will <strong>auto-publish</strong> on <?= formatDate($quiz['scheduled_at'], 'M d, Y g:i A') ?>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800"><?= $editing?'Edit Quiz':'Create Quiz' ?></h2>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
    <?php foreach ($errors as $e): ?><p>• <?= e($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-2xl">
    <form method="POST" id="quizForm">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">

        <div class="space-y-4">
            <!-- Course & Lesson -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject *</label>
                    <select name="course_id" id="courseSelect" onchange="loadLessons(this.value)"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($f['course_id']??'')==$c['id']?'selected':''?>><?= e($c['code'].' – '.$c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Lesson (optional)</label>
                    <select name="lesson_id" id="lessonSelect"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="">-- Select Lesson --</option>
                        <?php foreach ($lessons as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($f['lesson_id']??'')==$l['id']?'selected':''?>><?= e($l['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Quiz Title *</label>
                <input type="text" name="title" value="<?= e($f['title']??'') ?>" required
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= e($f['description']??'') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Instructions</label>
                <textarea name="instructions" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= e($f['instructions']??'') ?></textarea>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Time Limit (min)</label>
                    <input type="number" name="time_limit" value="<?= e($f['time_limit']??'') ?>" min="0" placeholder="No limit"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Passing Score (%)</label>
                    <input type="number" name="passing_score" value="<?= e($f['passing_score']??60) ?>" min="0" max="100"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Attempts</label>
                    <input type="number" name="max_attempts" value="<?= e($f['max_attempts']??3) ?>" min="1" max="10"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Schedule</label>
                    <input type="datetime-local" name="scheduled_at" value="<?= e($f['scheduled_at']??'') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Deadline</label>
                <input type="datetime-local" name="deadline" value="<?= e($f['deadline']??'') ?>"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 max-w-xs">
            </div>

            <!-- Toggles -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?php foreach ([
                    ['shuffle_questions','Shuffle Questions', $f['shuffle_questions']??0],
                    ['shuffle_choices',  'Shuffle Choices',  $f['shuffle_choices']??0],
                    ['is_adaptive',      'Adaptive Mode',    $f['is_adaptive']??0],
                ] as [$name,$label,$checked]): ?>
                <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                    <input type="hidden" name="<?= $name ?>" value="0">
                    <input type="checkbox" name="<?= $name ?>" value="1" <?= $checked?'checked':'' ?>
                           class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-400">
                    <span class="text-sm text-gray-700"><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Quiz Sections Builder -->
            <div class="border-t border-gray-200 pt-4 mt-2">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-gray-700">Quiz Sections *</label>
                    <button type="button" onclick="addSection()" class="text-xs text-orange-600 hover:text-orange-700 font-medium">
                        + Add Section
                    </button>
                </div>
                <div id="sectionsContainer" class="space-y-2">
                    <?php if (!empty($existingSections)): ?>
                        <?php foreach ($existingSections as $i => $sec): ?>
                        <div class="section-row flex items-center gap-2">
                            <input type="text" name="section_name[]" value="<?= e($sec['name']) ?>" placeholder="Test <?= $i+1 ?>" required
                                   class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                            <select name="section_type[]" class="px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 bg-white">
                                <option value="multiple_choice" <?= $sec['question_type']==='multiple_choice'?'selected':'' ?>>Multiple Choice</option>
                                <option value="true_false" <?= $sec['question_type']==='true_false'?'selected':'' ?>>True/False</option>
                                <option value="identification" <?= $sec['question_type']==='identification'?'selected':'' ?>>Identification</option>
                                <option value="short_answer" <?= $sec['question_type']==='short_answer'?'selected':'' ?>>Short Answer</option>
                            </select>
                            <input type="number" name="section_count[]" value="<?= $sec['question_count'] ?>" min="1" max="100" placeholder="10" required
                                   class="w-20 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                            <button type="button" onclick="removeSection(this)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default section -->
                        <div class="section-row flex items-center gap-2">
                            <input type="text" name="section_name[]" value="Test 1" placeholder="Test 1" required
                                   class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                            <select name="section_type[]" class="px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 bg-white">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="identification">Identification</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                            <input type="number" name="section_count[]" value="10" min="1" max="100" placeholder="10" required
                                   class="w-20 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                            <button type="button" onclick="removeSection(this)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 mt-2">Define test sections. Example: Test 1 (Multiple Choice, 20 questions), Test 2 (True/False, 10 questions).</p>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="px-6 py-2.5 bg-orange-500 text-white font-medium text-sm rounded-xl hover:bg-orange-600">
                <?= $editing?'Update & Manage Questions':'Save & Add Questions' ?>
            </button>
            <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 font-medium text-sm rounded-xl hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function loadLessons(courseId) {
    if (!courseId) return;
    fetch('<?= BASE_URL ?>/modules/api/get_lessons.php?course_id=' + courseId + '&csrf='+csrfToken())
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('lessonSelect');
            sel.innerHTML = '<option value="">-- Select Lesson --</option>';
            data.forEach(l => sel.innerHTML += `<option value="${l.id}">${l.title}</option>`);
        });
}

function addSection() {
    const container = document.getElementById('sectionsContainer');
    const count = container.querySelectorAll('.section-row').length + 1;
    const row = document.createElement('div');
    row.className = 'section-row flex items-center gap-2';
    row.innerHTML = `
        <input type="text" name="section_name[]" value="Test ${count}" placeholder="Test ${count}" required
               class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
        <select name="section_type[]" class="px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 bg-white">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="true_false">True/False</option>
            <option value="identification">Identification</option>
            <option value="short_answer">Short Answer</option>
        </select>
        <input type="number" name="section_count[]" value="10" min="1" max="100" placeholder="10" required
               class="w-20 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
        <button type="button" onclick="removeSection(this)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    `;
    container.appendChild(row);
}

function removeSection(btn) {
    const container = document.getElementById('sectionsContainer');
    if (container.querySelectorAll('.section-row').length > 1) {
        btn.closest('.section-row').remove();
    } else {
        alert('At least one section is required.');
    }
}
</script>
</body>
</html>
