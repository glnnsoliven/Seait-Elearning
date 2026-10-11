<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];
$quizId  = getInt('quiz_id');
$resume  = (bool)getInt('resume');
if (!$quizId) redirect(BASE_URL.'/modules/student/quiz_list.php');

$quiz = Database::fetchOne(
    "SELECT q.*, c.title course_title FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=? AND q.is_published=1",[$quizId]
);
if (!$quiz) { flashSet('error','Quiz not found.'); redirect(BASE_URL.'/modules/student/quiz_list.php'); }

// Check enrollment
$enrolled = Database::fetchOne("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND status='active'",[$sid,$quiz['course_id']]);
if (!$enrolled) { flashSet('error','Not enrolled in this course.'); redirect(BASE_URL.'/modules/student/quiz_list.php'); }

// Check max attempts — block retake, only allow first attempt
$attCount = Database::fetchOne("SELECT COUNT(*) c FROM quiz_attempts WHERE student_id=? AND quiz_id=?",[$sid,$quizId])['c'];

// Block if already has a completed attempt
$completedAttempt = Database::fetchOne(
    "SELECT id FROM quiz_attempts WHERE student_id=? AND quiz_id=? AND status='completed' LIMIT 1",
    [$sid, $quizId]
);
if ($completedAttempt) {
    flashSet('error', 'You have already completed this quiz. Retaking is not allowed.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

if ($attCount >= $quiz['max_attempts']) {
    flashSet('error','You have reached the maximum number of attempts for this quiz.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

// Check deadline
if ($quiz['deadline'] && strtotime($quiz['deadline']) < time()) {
    flashSet('error','This quiz deadline has passed.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

// Create or continue in-progress attempt (never resume a previous session's attempt)
$attempt = Database::fetchOne("SELECT * FROM quiz_attempts WHERE student_id=? AND quiz_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1",[$sid,$quizId]);
if (!$attempt) {
    $attemptNumber = $attCount + 1;
    $attId = Database::insert('quiz_attempts',[
        'quiz_id'        => $quizId,
        'student_id'     => $sid,
        'attempt_number' => $attemptNumber,
        'status'         => 'in_progress',
        'started_at'     => date('Y-m-d H:i:s'),
    ]);
    $attempt = Database::fetchOne("SELECT * FROM quiz_attempts WHERE id=?",[$attId]);
    AuditLog::write(Auth::id(),'start_quiz','quiz',"Student started quiz $quizId");
}

$attemptId  = (int)$attempt['id'];
$pageTitle  = 'Quiz: '.$quiz['title'];

// Already-answered question IDs this attempt
$answered = array_column(
    Database::fetchAll("SELECT question_id FROM student_answers WHERE attempt_id=?",[$attemptId]),
    'question_id'
);

// Get total question count for progress
$totalQ = (int) Database::fetchOne("SELECT COUNT(*) c FROM quiz_questions WHERE quiz_id=? AND is_active=1",[$quizId])['c'];
$answeredCount = count($answered);

// Load sections for progress display
$sections = Database::fetchAll("SELECT * FROM quiz_sections WHERE quiz_id=? ORDER BY sort_order",[$quizId]);

// Get next question (adaptive or sequential)
if ($quiz['is_adaptive']) {
    $question = AdaptiveEngine::nextQuestion($quizId, $attemptId, $answered);
} else {
    // Sequential: fetch next unanswered in sort order (section sort_order first, then question sort_order)
    $pholder  = count($answered) > 0 ? implode(',',array_fill(0,count($answered),'?')) : '0';
    $qParams  = array_merge([$quizId], $answered);
    $question = Database::fetchOne(
        "SELECT qq.* FROM quiz_questions qq
         LEFT JOIN quiz_sections s ON s.id=qq.section_id
         WHERE qq.quiz_id=? AND qq.id NOT IN({$pholder}) AND qq.is_active=1 
         ORDER BY COALESCE(s.sort_order, 999), qq.sort_order, qq.id LIMIT 1",
        $qParams
    );
}

// All questions answered → auto-submit
$currentSection = null;
if ($question && !empty($question['section_id'])) {
    $currentSection = Database::fetchOne("SELECT * FROM quiz_sections WHERE id=?",[$question['section_id']]);
}

if (!$question) {
    $result = AdaptiveEngine::finalizeAttempt($attemptId);
    // Generate diagnostic feedback for wrong answers
    $wrongAnswers = Database::fetchAll(
        "SELECT sa.*, qq.explanation, qq.correct_answer, qq.question_text, qq.topic_id
         FROM student_answers sa JOIN quiz_questions qq ON qq.id=sa.question_id
         WHERE sa.attempt_id=? AND sa.is_correct=0",[$attemptId]
    );
    foreach ($wrongAnswers as $wa) {
        // Find recommended lesson linked to this topic
        $recLesson = null;
        if ($wa['topic_id']) {
            $recLesson = Database::fetchOne(
                "SELECT id FROM lessons WHERE course_id=? AND is_published=1 ORDER BY sort_order LIMIT 1",
                [$quiz['course_id']]
            );
            // Try the lesson that contains this topic first
            $topicLesson = Database::fetchOne(
                "SELECT lesson_id FROM topics WHERE id=? LIMIT 1", [$wa['topic_id']]
            );
            if ($topicLesson) $recLesson = ['id' => $topicLesson['lesson_id']];
        }

        // Generate meaningful misconception text based on question type
        $qType = Database::fetchOne("SELECT question_type, question_text FROM quiz_questions WHERE id=?", [$wa['question_id']]);
        $misconception = match($qType['question_type'] ?? 'multiple_choice') {
            'multiple_choice' => "Selected incorrect option. Correct answer is: {$wa['correct_answer']}. Review the concept behind this question.",
            'true_false'      => "Chose {$wa['student_answer']} but the correct answer is {$wa['correct_answer']}. Reconsider the underlying principle.",
            'identification'  => "Answered '{$wa['student_answer']}' — correct term is '{$wa['correct_answer']}'. Terminology gap detected.",
            'short_answer'    => "Response did not sufficiently match. Expected: '{$wa['correct_answer']}'. Concept understanding needs reinforcement.",
            default           => "Incorrect answer. Correct: {$wa['correct_answer']}.",
        };

        $suggestedActivity = $wa['explanation']
            ? "Review: {$wa['explanation']} Then re-attempt similar questions."
            : "Re-read the related lesson material and attempt practice questions on this topic.";

        Database::insert('diagnostic_feedback', [
            'student_answer_id'    => $wa['id'],
            'student_id'           => $sid,
            'question_id'          => $wa['question_id'],
            'student_answer'       => $wa['student_answer'],
            'correct_answer'       => $wa['correct_answer'],
            'explanation'          => $wa['explanation'],
            'misconception'        => $misconception,
            'related_topic'        => $wa['topic_id'] ? Database::fetchOne("SELECT title FROM topics WHERE id=?", [$wa['topic_id']])['title'] ?? null : null,
            'recommended_lesson_id'=> $recLesson['id'] ?? null,
            'suggested_activity'   => $suggestedActivity,
        ]);
    }
    // Run at-risk analysis
    AtRisk::analyse($sid, $quiz['course_id']);
    // Update competency mastery from quiz topic performance
    AdaptiveEngine::updateCompetencyMastery($sid, $quiz['course_id'], $result['topic_perf']);
    // Notify — no score revealed, results shown only after deadline
    Notification::send(Auth::id(),'quiz_result','Quiz Submitted','You have submitted "'.$quiz['title'].'". Results will be available after the deadline.',BASE_URL.'/modules/student/quiz_list.php');
    flashSet('success', 'Quiz submitted! Results will be shown after the deadline.');
    redirect(BASE_URL.'/modules/student/quiz_list.php');
}

// Parse choices
$choices = $question['choices'] ? json_decode($question['choices'], true) : [];
if ($quiz['shuffle_choices'] && !empty($choices)) {
    shuffle($choices);
}

$timeRemaining = null;
if ($quiz['time_limit']) {
    $elapsed = time() - strtotime($attempt['started_at']);
    $timeRemaining = max(0, $quiz['time_limit'] * 60 - $elapsed);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/../../includes/components/head.php'; ?>
<!-- Prevent back navigation during quiz -->
<style>body { user-select: none; }</style>
</head>
<body class="bg-gray-50 min-h-screen" oncontextmenu="return false">

<!-- Quiz Top Bar -->
<div class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <div>
            <p class="font-semibold text-gray-800 text-sm"><?= e($quiz['title']) ?></p>
            <p class="text-xs text-gray-500"><?= e($quiz['course_title']) ?></p>
            <?php if ($currentSection): ?>
            <p class="text-xs font-medium text-orange-600 mt-0.5">
                <?= e($currentSection['name']) ?> — <?= ucwords(str_replace('_', ' ', $currentSection['question_type'])) ?>
            </p>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-4">
            <!-- Progress -->
            <div class="text-center">
                <p class="text-xs text-gray-500">Progress</p>
                <p class="text-sm font-bold text-gray-800"><?= $answeredCount ?>/<?= $totalQ ?></p>
            </div>
            <!-- Timer -->
            <?php if ($timeRemaining !== null): ?>
            <div class="text-center">
                <p class="text-xs text-gray-500">Time Left</p>
                <p id="quiz-timer" class="text-sm font-bold text-blue-600"
                   data-seconds="<?= $timeRemaining ?>"></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Progress bar -->
    <div class="h-1 bg-gray-100">
        <div class="h-full bg-blue-500 transition-all" style="width: <?= $totalQ > 0 ? round(($answeredCount/$totalQ)*100) : 0 ?>%"></div>
    </div>
</div>

<main class="max-w-3xl mx-auto px-4 py-8 fade-in">

    <!-- Question card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <!-- Meta badges + per-question timer -->
    <div class="flex items-center justify-between gap-2 flex-wrap mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                Q<?= $answeredCount+1 ?> of <?= $totalQ ?>
            </span>
            <span class="px-2.5 py-1 rounded-full text-xs <?= ['easy'=>'bg-green-100 text-green-700','medium'=>'bg-yellow-100 text-yellow-700','hard'=>'bg-red-100 text-red-700'][$question['difficulty']] ?>">
                <?= ucfirst($question['difficulty']) ?>
            </span>
            <span class="px-2.5 py-1 rounded-full text-xs <?= bloomBadgeColor($question['bloom_level']) ?>">
                <?= ucfirst($question['bloom_level']) ?>
            </span>
            <span class="text-xs text-gray-400"><?= $question['points'] ?> pt<?= $question['points']>1?'s':'' ?></span>
        </div>

        <!-- Per-question countdown -->
        <div id="per-q-timer" class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-1.5">
            <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                <div id="per-q-bar" class="h-full rounded-full bg-green-500" style="width:100%"></div>
            </div>
            <span id="per-q-num" class="text-sm font-bold text-gray-700 w-10 text-right tabular-nums"></span>
        </div>
    </div>

        <h2 class="text-lg font-semibold text-gray-800 mb-6 leading-relaxed">
            <?= nl2br(e($question['question_text'])) ?>
        </h2>

        <form id="answerForm" method="POST" action="<?= BASE_URL ?>/modules/api/submit_answer.php">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="attempt_id"   value="<?= $attemptId ?>">
            <input type="hidden" name="question_id"  value="<?= $question['id'] ?>">
            <input type="hidden" name="quiz_id"      value="<?= $quizId ?>">
            <input type="hidden" name="start_time"   value="<?= time() ?>">

            <?php if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false'): ?>
            <!-- Multiple choice / True-False -->
            <div class="space-y-3" id="choicesContainer">
                <?php foreach ($choices as $choice): ?>
                <label class="flex items-center gap-4 p-4 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition answer-option group"
                       data-value="<?= e($choice['label']) ?>">
                    <input type="radio" name="answer" value="<?= e($choice['label']) ?>" class="hidden" required>
                    <span class="w-8 h-8 rounded-full border-2 border-gray-300 flex items-center justify-center text-sm font-bold text-gray-500 flex-shrink-0 group-hover:border-blue-400 group-hover:text-blue-600 transition choice-indicator">
                        <?= e($choice['label']) ?>
                    </span>
                    <span class="text-gray-700 text-sm"><?= e($choice['text']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <?php elseif ($question['question_type'] === 'identification'): ?>
            <!-- Identification -->
            <div>
                <label class="block text-sm text-gray-600 mb-2">Type your answer:</label>
                <input type="text" name="answer" required autofocus
                       placeholder="Enter the correct term or name…"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-sm focus:outline-none focus:border-blue-400 focus:ring-0 transition">
            </div>

            <?php elseif ($question['question_type'] === 'short_answer'): ?>
            <!-- Short Answer -->
            <div>
                <label class="block text-sm text-gray-600 mb-2">Write your answer:</label>
                <textarea name="answer" required rows="4" autofocus
                          placeholder="Write your answer in 1-3 sentences…"
                          class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-sm focus:outline-none focus:border-blue-400 focus:ring-0 resize-none transition"></textarea>
            </div>
            <?php endif; ?>

            <div class="flex gap-3 mt-6">
                <?php if ($question['question_type'] === 'identification' || $question['question_type'] === 'short_answer'): ?>
                <button type="submit" id="submitBtn"
                        class="flex-1 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition shadow-md shadow-blue-200">
                    <?= $answeredCount + 1 < $totalQ ? 'Next Question →' : 'Submit Quiz ✓' ?>
                </button>
                <?php else: ?>
                <!-- MC / T/F — auto-advances on selection; manual button as fallback -->
                <button type="submit" id="submitBtn"
                        class="flex-1 py-3 bg-gray-100 text-gray-500 font-semibold rounded-xl transition text-sm">
                    <?= $answeredCount + 1 < $totalQ ? 'Skip →' : 'Submit Quiz ✓' ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Quiz instructions reminder -->
    <?php if ($quiz['instructions']): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
        <p class="font-medium mb-1">📋 Instructions</p>
        <p><?= e($quiz['instructions']) ?></p>
    </div>
    <?php endif; ?>

</main>

<script>
// ═══════════════════════════════════════════════════════════
// PER-QUESTION TIMER
// ═══════════════════════════════════════════════════════════
const TOTAL_Q       = <?= $totalQ ?>;
const ANSWERED_COUNT= <?= $answeredCount ?>;
const Q_TYPE        = <?= json_encode($question['question_type']) ?>;

// Calculate seconds per question:
// If quiz has an overall time_limit, divide evenly; otherwise default 60s
<?php
$perQuestionSecs = 60; // default
if ($quiz['time_limit']) {
    $remaining = max(0, $quiz['time_limit'] * 60 - (time() - strtotime($attempt['started_at'])));
    $questionsLeft = max(1, $totalQ - $answeredCount);
    $perQuestionSecs = max(10, (int)floor($remaining / $questionsLeft));
}
?>
let perQSeconds = <?= $perQuestionSecs ?>;

// ── DOM refs ──────────────────────────────────────────────
const form       = document.getElementById('answerForm');
const submitBtn  = document.getElementById('submitBtn');
const timerEl    = document.getElementById('per-q-timer');
const timerBar   = document.getElementById('per-q-bar');
const timerNum   = document.getElementById('per-q-num');

let timerInterval = null;
let autoSubmitting = false;

// ── Format helper ─────────────────────────────────────────
function fmt(s) {
    return Math.floor(s/60).toString().padStart(2,'0') + ':' + (s%60).toString().padStart(2,'0');
}

// ── Start per-question countdown ─────────────────────────
function startTimer() {
    let seconds = perQSeconds;
    const total = perQSeconds;

    timerNum.textContent = fmt(seconds);
    timerBar.style.width = '100%';
    timerBar.className   = 'h-full rounded-full transition-all duration-1000 bg-green-500';

    timerInterval = setInterval(() => {
        seconds--;

        // Update display
        timerNum.textContent = fmt(seconds);
        const pct = (seconds / total) * 100;
        timerBar.style.width = pct + '%';

        // Colour changes: green → yellow → red
        if (pct <= 25) {
            timerBar.className = 'h-full rounded-full transition-all duration-1000 bg-red-500';
            timerNum.classList.add('text-red-600');
            timerNum.classList.remove('text-yellow-600','text-gray-700');
        } else if (pct <= 50) {
            timerBar.className = 'h-full rounded-full transition-all duration-1000 bg-yellow-400';
            timerNum.classList.add('text-yellow-600');
            timerNum.classList.remove('text-red-600','text-gray-700');
        }

        // Pulse when ≤ 10 seconds
        if (seconds <= 10) {
            timerEl.classList.add('animate-pulse');
        }

        if (seconds <= 0) {
            clearInterval(timerInterval);
            if (!autoSubmitting) {
                autoSubmitting = true;
                // If no answer selected for MC/TF, submit blank
                const answerInput = form.querySelector('[name="answer"]');
                if (!answerInput || !answerInput.value) {
                    // Create hidden answer with empty value so submit goes through
                    const hidden = document.createElement('input');
                    hidden.type  = 'hidden';
                    hidden.name  = 'answer';
                    hidden.value = '';
                    form.appendChild(hidden);
                }
                submitBtn.disabled    = true;
                submitBtn.textContent = '⏱ Time\'s up! Moving on…';
                form.submit();
            }
        }
    }, 1000);
}

// Start immediately on page load
startTimer();

// ═══════════════════════════════════════════════════════════
// AUTO-ADVANCE: MC / TRUE-FALSE — submit as soon as selected
// ═══════════════════════════════════════════════════════════
if (Q_TYPE === 'multiple_choice' || Q_TYPE === 'true_false') {
    document.querySelectorAll('.answer-option').forEach(label => {
        label.addEventListener('click', () => {
            // Visual selection
            document.querySelectorAll('.answer-option').forEach(l => {
                l.classList.remove('border-blue-500','bg-blue-50','ring-2','ring-blue-400');
                l.querySelector('.choice-indicator')?.classList.remove('border-blue-500','text-blue-700','bg-blue-100');
            });
            label.classList.add('border-blue-500','bg-blue-50','ring-2','ring-blue-400');
            label.querySelector('.choice-indicator')?.classList.add('border-blue-500','text-blue-700','bg-blue-100');

            const radio = label.querySelector('input[type=radio]');
            if (radio) radio.checked = true;

            // Stop the per-question timer
            clearInterval(timerInterval);

            // Brief highlight delay then auto-submit (300ms so student sees selection)
            setTimeout(() => {
                if (!autoSubmitting) {
                    autoSubmitting = true;
                    submitBtn.disabled    = true;
                    submitBtn.textContent = '→ Next Question…';
                    // Show checkmark on selected
                    label.classList.add('border-green-500','bg-green-50');
                    label.classList.remove('border-blue-500','bg-blue-50');
                    setTimeout(() => form.submit(), 200);
                }
            }, 300);
        });
    });
} else {
    // ── Identification / Short Answer — manual submit ─────
    // Still stop the timer when they start typing
    const textInput = form.querySelector('input[name=answer], textarea[name=answer]');
    if (textInput) {
        textInput.addEventListener('input', () => {
            // Reset timer when user starts typing (give them full time from first keystroke)
            // Only reset once
            textInput.removeEventListener('input', arguments.callee);
        });
    }
}

// ═══════════════════════════════════════════════════════════
// OVERALL QUIZ TIMER (if time_limit is set on the quiz)
// ═══════════════════════════════════════════════════════════
<?php if ($timeRemaining !== null): ?>
let overallSeconds = <?= $timeRemaining ?>;
const overallEl    = document.getElementById('quiz-timer');
if (overallEl) {
    overallEl.textContent = fmt(overallSeconds);
    setInterval(() => {
        overallSeconds--;
        overallEl.textContent = fmt(overallSeconds);
        if (overallSeconds <= 60) overallEl.classList.replace('text-blue-600','text-red-600');
        if (overallSeconds <= 0 && !autoSubmitting) {
            autoSubmitting = true;
            clearInterval(timerInterval);
            form.submit();
        }
    }, 1000);
}
<?php endif; ?>

// ── Prevent double-submit ─────────────────────────────────
form?.addEventListener('submit', () => {
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Submitting…';
});
</script>
</body>
</html>
