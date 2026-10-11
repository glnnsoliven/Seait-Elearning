<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'Question Bank';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];

$quizId = getInt('quiz_id');
if (!$quizId) redirect(BASE_URL.'/modules/teacher/quizzes.php');

$quiz = Database::fetchOne("SELECT q.*, c.title course_title FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=? AND q.teacher_id=?",[$quizId,$tid]);
if (!$quiz) { flashSet('error','Quiz not found.'); redirect(BASE_URL.'/modules/teacher/quizzes.php'); }

// Load sections
$sections = Database::fetchAll("SELECT * FROM quiz_sections WHERE quiz_id=? ORDER BY sort_order",[$quizId]);

$topics = Database::fetchAll(
    "SELECT t.* FROM topics t JOIN lessons l ON l.id=t.lesson_id WHERE l.course_id=? ORDER BY t.title",
    [$quiz['course_id']]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'save_question') {
        $qid    = postInt('question_id');
        $text   = postStr('question_text');
        $type   = postStr('question_type');
        $answer = postStr('correct_answer');
        $diff   = postStr('difficulty');
        $bloom  = postStr('bloom_level');
        $expl   = postStr('explanation');
        $topId  = postInt('topic_id') ?: null;
        $secId  = postInt('section_id') ?: null;
        $pts    = postInt('points') ?: 1;
        $sort   = postInt('sort_order');

        // Build choices JSON for multiple choice
        $choices = null;
        if ($type === 'multiple_choice') {
            $choiceTexts  = $_POST['choice_text']  ?? [];
            $choiceLabels = ['A','B','C','D'];
            $built = [];
            foreach ($choiceTexts as $i => $ct) {
                if (!empty(trim($ct))) {
                    $built[] = ['label'=>$choiceLabels[$i]??($i+1), 'text'=>trim($ct)];
                }
            }
            if (!empty($built)) $choices = json_encode($built);
        } elseif ($type === 'true_false') {
            $choices = json_encode([['label'=>'A','text'=>'True'],['label'=>'B','text'=>'False']]);
        }

        $data = [
            'quiz_id'=>$quizId,'section_id'=>$secId,'question_text'=>$text,'question_type'=>$type,
            'choices'=>$choices,'correct_answer'=>$answer,'explanation'=>$expl,
            'difficulty'=>$diff,'bloom_level'=>$bloom,'points'=>$pts,
            'topic_id'=>$topId,'sort_order'=>$sort,'is_active'=>1,
        ];

        if ($qid) {
            unset($data['quiz_id']);
            Database::update('quiz_questions',$data,'id=? AND quiz_id=?',[$qid,$quizId]);
            flashSet('success','Question updated.');
        } else {
            Database::insert('quiz_questions',$data);
            // Don't redirect - just set a flag to keep modal open
            $_SESSION['keep_modal_open'] = true;
            $_SESSION['modal_message'] = 'Question added! Add another or close the modal.';
        }
        
        // Only redirect if editing (not adding new)
        if ($qid) {
            redirect($_SERVER['PHP_SELF'].'?quiz_id='.$quizId);
        } else {
            // Reload page to refresh question list but keep modal open
            redirect($_SERVER['PHP_SELF'].'?quiz_id='.$quizId.'&keep_modal=1');
        }
    } elseif ($action === 'delete') {
        $qid = postInt('question_id');
        Database::delete('quiz_questions','id=? AND quiz_id=?',[$qid,$quizId]);
        flashSet('success','Question deleted.');
        redirect($_SERVER['PHP_SELF'].'?quiz_id='.$quizId);
    }
}

// Delete ALL empty placeholder questions for this quiz (cleanup only, no auto-creation)
Database::query(
    "DELETE FROM quiz_questions 
     WHERE quiz_id = ? AND (question_text = '' OR question_text IS NULL)",
    [$quizId]
);

// Fetch question list
$questions = Database::fetchAll(
    "SELECT qq.*, t.title topic_title, s.name section_name FROM quiz_questions qq 
     LEFT JOIN topics t ON t.id=qq.topic_id
     LEFT JOIN quiz_sections s ON s.id=qq.section_id
     WHERE qq.quiz_id=? AND qq.is_active=1 ORDER BY s.sort_order, qq.sort_order, qq.id",
    [$quizId]
);

// Check if modal should stay open after adding question
$keepModalOpen = isset($_GET['keep_modal']) && $_GET['keep_modal'] == 1;
$modalMessage = $_SESSION['modal_message'] ?? null;
unset($_SESSION['modal_message']);
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

<div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php" class="hover:text-orange-600">Quizzes</a>
    <span>›</span><span class="text-gray-800 font-medium"><?= e($quiz['title']) ?></span>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Question Bank</h2>
        <p class="text-sm text-gray-500"><?= count($questions) ?> question<?= count($questions) != 1 ? 's' : '' ?> in "<?= e($quiz['title']) ?>"</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/modules/teacher/ai_quiz_generator.php" class="px-4 py-2 bg-orange-50 text-orange-700 text-sm rounded-xl hover:bg-orange-100">✨ AI Generate</a>
        <button onclick="openQuestionModal()" class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Question
        </button>
    </div>
</div>

<?php if (!empty($sections)): ?>
<!-- Section Tabs -->
<div class="mb-4 border-b border-gray-200">
    <nav class="flex gap-1 overflow-x-auto">
        <?php 
        $cumulativeCount = 0;
        foreach ($sections as $sec): 
            $secQuestions = array_filter($questions, fn($q) => $q['section_id'] == $sec['id']);
            $startNum = $cumulativeCount + 1;
            $endNum = $cumulativeCount + $sec['question_count'];
            $cumulativeCount += $sec['question_count'];
        ?>
        <button onclick="showSection(<?= $sec['id'] ?>)" 
                data-section="<?= $sec['id'] ?>"
                class="section-tab px-4 py-2 text-sm font-medium rounded-t-xl border-b-2 transition whitespace-nowrap <?= $sec === $sections[0] ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
            <?= e($sec['name']) ?> 
            <span class="text-xs opacity-60">(<?= count($secQuestions) ?>/<?= $sec['question_count'] ?>)</span>
            <span class="block text-xs opacity-50 mt-0.5">Q<?= $startNum ?>-<?= $endNum ?></span>
        </button>
        <?php endforeach; ?>
    </nav>
</div>
<?php endif; ?>

<div class="space-y-3">
<?php
// Group questions by section
$grouped = [];
foreach ($questions as $q) {
    $sid = $q['section_id'] ?? 0;
    if (!isset($grouped[$sid])) $grouped[$sid] = [];
    $grouped[$sid][] = $q;
}

$globalQuestionNumber = 0; // Track question number across all sections

foreach ($sections as $sec):
    $secQuestions = $grouped[$sec['id']] ?? [];
    $sectionStartNum = $globalQuestionNumber + 1;
    $sectionEndNum = $globalQuestionNumber + $sec['question_count'];
?>
<div class="section-content" data-section="<?= $sec['id'] ?>" style="display:<?= $sec === $sections[0] ? 'block' : 'none' ?>">
    <div class="mb-3 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">
                <?= e($sec['name']) ?> — <?= ucwords(str_replace('_', ' ', $sec['question_type'])) ?> 
                <span class="text-gray-500 font-normal">(<?= count($secQuestions) ?>/<?= $sec['question_count'] ?>)</span>
            </h3>
            <p class="text-xs text-gray-500 mt-0.5"><?= $sec['question_count'] ?> question<?= $sec['question_count'] != 1 ? 's' : '' ?> (Q<?= $sectionStartNum ?>-<?= $sectionEndNum ?>)</p>
        </div>
        <button onclick="openQuestionModal(null, <?= $sec['id'] ?>)" class="text-xs text-orange-600 hover:text-orange-700 font-medium">
            + Add to this section
        </button>
    </div>
    
    <?php if (empty($secQuestions)): ?>
    <div class="bg-gray-50 rounded-xl p-8 text-center border border-gray-100">
        <p class="text-sm text-gray-400 mb-3">No questions in this section yet.</p>
        <button onclick="openQuestionModal(null, <?= $sec['id'] ?>)" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">
            + Add Question
        </button>
    </div>
    <?php else: ?>
        <?php foreach ($secQuestions as $i => $q):
            $globalQuestionNumber++; // Increment global counter
            $num = $globalQuestionNumber;
            $isEmpty = empty(trim($q['question_text']));
    $typeColors = ['multiple_choice'=>'bg-blue-100 text-blue-700','true_false'=>'bg-green-100 text-green-700','identification'=>'bg-amber-100 text-amber-700','short_answer'=>'bg-purple-100 text-purple-700'];
    $diffColors = ['easy'=>'bg-green-100 text-green-700','medium'=>'bg-yellow-100 text-yellow-700','hard'=>'bg-red-100 text-red-700'];
?>
<div class="bg-white rounded-2xl shadow-sm border <?= $isEmpty ? 'border-orange-200 border-dashed' : 'border-gray-100' ?> overflow-hidden">
    <!-- Question number header -->
    <div class="flex items-center justify-between px-5 py-2.5 border-b border-gray-50"
         style="background:<?= $isEmpty ? '#fffbeb' : 'linear-gradient(135deg,#e67e2210,#fff)' ?>">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-xl text-xs font-extrabold text-white flex-shrink-0"
                  style="background:<?= $isEmpty ? '#f59e0b' : '#e67e22' ?>">
                <?= $num ?>
            </span>
            <span class="text-sm font-bold <?= $isEmpty ? 'text-amber-600' : 'text-gray-700' ?>">
                <?= $isEmpty ? 'Question '.$num.' (Empty)' : 'Question '.$num ?>
            </span>
            <?php if (!$isEmpty): ?>
            <span class="px-2 py-0.5 rounded-full text-xs <?= $typeColors[$q['question_type']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucwords(str_replace('_',' ',$q['question_type'])) ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs <?= $diffColors[$q['difficulty']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($q['difficulty']) ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs <?= bloomBadgeColor($q['bloom_level']) ?>"><?= ucfirst($q['bloom_level']) ?></span>
            <?php if ($q['topic_title']): ?>
            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 hidden sm:inline"><?= e($q['topic_title']) ?></span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            <?php if (!$isEmpty): ?>
            <span class="text-xs text-gray-400 mr-2"><?= $q['points'] ?> pt<?= $q['points'] != 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <button onclick='openQuestionModal(<?= htmlspecialchars(json_encode($q)) ?>)'
                    class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <form method="POST" id="delQ-<?= $q['id'] ?>" class="inline">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
                <button type="button" onclick="confirmDelete('Delete Question <?= $num ?>?','delQ-<?= $q['id'] ?>')"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Question body -->
    <?php if ($isEmpty): ?>
    <div class="px-5 py-3 bg-amber-50">
        <p class="text-xs text-amber-700 italic font-medium">Empty question — click the ✏️ edit button to fill in this question</p>
    </div>
    <?php else: ?>
    <div class="px-5 py-4">
        <p class="text-sm text-gray-800 font-medium mb-3"><?= e($q['question_text']) ?></p>
        <?php if ($q['choices']): ?>
        <?php $choices = json_decode($q['choices'], true); ?>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <?php foreach ($choices as $c): ?>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg <?= $c['label']===$q['correct_answer'] ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-100' ?>">
                <span class="text-xs font-bold <?= $c['label']===$q['correct_answer'] ? 'text-green-600' : 'text-gray-500' ?>"><?= $c['label'] ?>.</span>
                <span class="text-xs text-gray-700"><?= e($c['text']) ?></span>
                <?php if ($c['label']===$q['correct_answer']): ?>
                <svg class="w-3.5 h-3.5 text-green-500 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="flex items-center gap-2 mb-3">
            <span class="text-xs text-gray-500">Correct Answer:</span>
            <span class="px-2 py-0.5 bg-green-50 text-green-700 text-xs font-semibold rounded-lg border border-green-200"><?= e($q['correct_answer']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($q['explanation']): ?>
        <p class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2">&#128161; <?= e($q['explanation']) ?></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($sections)): ?>
<div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
    <p class="text-gray-400 mb-4">No sections defined. Please edit the quiz to add sections first.</p>
    <a href="<?= BASE_URL ?>/modules/teacher/create_quiz.php?id=<?= $quizId ?>" class="px-4 py-2 bg-orange-500 text-white text-sm rounded-xl hover:bg-orange-600">Edit Quiz</a>
</div>
<?php endif; ?>
</div>

</main>
</div>

<!-- Question Modal -->
<div id="questionModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[90vh]">
        <!-- Fixed Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
            <h3 id="qModalTitle" class="font-semibold text-gray-800">Add Question</h3>
            <button onclick="closeModal('questionModal')" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        
        <!-- Scrollable Body -->
        <div class="overflow-y-auto flex-1 p-6">
            <form method="POST" id="questionForm" class="space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save_question">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
            <input type="hidden" name="question_id" id="qId" value="">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                <textarea name="question_text" id="qText" rows="3" required
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="question_type" id="qType" onchange="toggleChoices()"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="identification">Identification</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                    <select name="section_id" id="qSection" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="">-- No Section --</option>
                        <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['id'] ?>"><?= e($sec['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Topic (optional)</label>
                <select name="topic_id" id="qTopic" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                    <option value="">-- No Topic --</option>
                    <?php foreach ($topics as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Choices (MC only) -->
            <div id="choicesSection">
                <label class="block text-sm font-medium text-gray-700 mb-2">Answer Choices</label>
                <?php foreach (['A','B','C','D'] as $label): ?>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-6 text-xs font-bold text-gray-500"><?= $label ?>.</span>
                    <input type="text" name="choice_text[]" id="choice<?= $label ?>"
                           class="flex-1 px-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-violet-300"
                           placeholder="Choice <?= $label ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer *</label>
                <input type="text" name="correct_answer" id="qAnswer" required
                       placeholder="For MC: enter A, B, C, or D"
                       class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                    <select name="difficulty" id="qDiff" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bloom Level</label>
                    <select name="bloom_level" id="qBloom" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                        <?php foreach (['remember','understand','apply','analyze','evaluate','create'] as $b): ?>
                        <option value="<?= $b ?>"><?= ucfirst($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Points</label>
                    <input type="number" name="points" id="qPts" value="1" min="1" max="10"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="qSort" value="0" min="0"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Explanation</label>
                <textarea name="explanation" id="qExpl" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"
                          placeholder="Explain why the answer is correct…"></textarea>
            </div>
            </form>
        </div>
        
        <!-- Fixed Footer -->
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
            <button type="submit" form="questionForm" class="flex-1 py-2.5 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600">Save Question</button>
            <button type="button" onclick="closeModal('questionModal')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
function toggleChoices() {
    const type = document.getElementById('qType').value;
    const sec  = document.getElementById('choicesSection');
    sec.classList.toggle('hidden', type !== 'multiple_choice');
}

function showSection(secId) {
    document.querySelectorAll('.section-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.section-tab').forEach(el => {
        el.classList.remove('border-orange-500', 'text-orange-600');
        el.classList.add('border-transparent', 'text-gray-500');
    });
    const content = document.querySelector(`.section-content[data-section="${secId}"]`);
    const tab = document.querySelector(`.section-tab[data-section="${secId}"]`);
    if (content) content.style.display = 'block';
    if (tab) {
        tab.classList.add('border-orange-500', 'text-orange-600');
        tab.classList.remove('border-transparent', 'text-gray-500');
    }
}

function openQuestionModal(q = null, sectionId = null) {
    const nextNum = document.querySelectorAll('[id^="delQ-"]').length + 1;
    document.getElementById('qModalTitle').textContent = q
        ? 'Edit Question ' + (q.sort_order !== undefined ? '' : '')
        : 'Add Question ' + nextNum;
    document.getElementById('qId').value     = q?.id          ?? '';
    document.getElementById('qText').value   = q?.question_text ?? '';
    document.getElementById('qType').value   = q?.question_type ?? 'multiple_choice';
    document.getElementById('qAnswer').value = q?.correct_answer ?? '';
    document.getElementById('qDiff').value   = q?.difficulty   ?? 'medium';
    document.getElementById('qBloom').value  = q?.bloom_level  ?? 'understand';
    document.getElementById('qExpl').value   = q?.explanation  ?? '';
    document.getElementById('qPts').value    = q?.points       ?? 1;
    document.getElementById('qSort').value   = q?.sort_order   ?? 0;
    document.getElementById('qTopic').value  = q?.topic_id     ?? '';
    document.getElementById('qSection').value = q?.section_id  ?? (sectionId ?? '');

    // Fill choices
    const choices = q?.choices ? JSON.parse(q.choices) : [];
    ['A','B','C','D'].forEach((l,i) => {
        const inp = document.getElementById('choice'+l);
        inp.value = choices[i]?.text ?? '';
    });

    toggleChoices();
    openModal('questionModal');
}

// Auto-open modal if returning after adding question
<?php if ($keepModalOpen && $modalMessage): ?>
document.addEventListener('DOMContentLoaded', function() {
    openQuestionModal();
    // Show success message in modal
    const modalBody = document.querySelector('#questionModal .overflow-y-auto');
    const msg = document.createElement('div');
    msg.className = 'mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3';
    msg.textContent = '<?= addslashes($modalMessage) ?>';
    modalBody.insertBefore(msg, modalBody.firstChild);
    setTimeout(() => msg.remove(), 3000);
});
<?php endif; ?>
</script>
</body>
</html>
