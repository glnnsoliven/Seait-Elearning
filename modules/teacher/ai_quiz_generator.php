<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'AI Quiz Generator';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$courses   = Database::fetchAll(
    "SELECT id,title,code FROM courses WHERE teacher_id=? AND is_active=1 ORDER BY title", [$tid]
);

$step              = 1;
$errors            = [];
$warnings          = [];
$generatedSections = [];
$extractedText     = '';
$debugInfo         = '';

// ══════════════════════════════════════════════════════════════
// SAVE: Insert questions into quiz
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postStr('action') === 'save_questions') {
    Auth::verifyCsrf();
    $quizId      = postInt('quiz_id');
    $sectionsRaw = json_decode($_POST['sections_json'] ?? '[]', true);

    if ($quizId && !empty($sectionsRaw)) {
        $sort = 0;
        foreach ($sectionsRaw as $sec) {
            $secId = (int)($sec['section_id'] ?? 0);
            foreach ($sec['questions'] ?? [] as $q) {
                if (empty(trim($q['question_text'] ?? ''))) continue;
                Database::insert('quiz_questions', [
                    'quiz_id'        => $quizId,
                    'section_id'     => $secId ?: null,
                    'question_text'  => $q['question_text'],
                    'question_type'  => $q['question_type']  ?? 'multiple_choice',
                    'choices'        => (!empty($q['choices'])) ? json_encode($q['choices']) : null,
                    'correct_answer' => $q['correct_answer'] ?? '',
                    'explanation'    => $q['explanation']    ?? '',
                    'difficulty'     => $q['difficulty']     ?? 'medium',
                    'bloom_level'    => $q['bloom_level']    ?? 'understand',
                    'points'         => 1,
                    'sort_order'     => $sort++,
                    'is_active'      => 1,
                ]);
            }
        }
        Database::update('quizzes', ['created_by_ai' => 1], 'id=?', [$quizId]);
        AuditLog::write(Auth::id(), 'ai_generate_quiz', 'quizzes', "Saved AI quiz $quizId");
        $total = array_sum(array_map(fn($s) => count($s['questions'] ?? []), $sectionsRaw));
        flashSet('success', "✅ $total questions saved to quiz!");
        redirect(BASE_URL . '/modules/teacher/question_bank.php?quiz_id=' . $quizId);
    }
    flashSet('error', 'Could not save questions.');
    redirect($_SERVER['PHP_SELF']);
}

// ══════════════════════════════════════════════════════════════
// GENERATE: Extract → API → Create quiz
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postStr('action') === 'generate') {
    Auth::verifyCsrf();

    $courseId  = postInt('course_id');
    $lessonId  = postInt('lesson_id') ?: null;
    $quizTitle = postStr('quiz_title');
    $passing   = postInt('passing_score') ?: 60;
    $timeLimit = postInt('time_limit')    ?: null;
    $textInput = trim($_POST['lesson_text'] ?? '');
    $difficulty = postStr('difficulty')   ?: 'medium';
    $bloom      = postStr('bloom_level')  ?: 'understand';
    $secNames   = $_POST['section_name']  ?? [];
    $secTypes   = $_POST['section_type']  ?? [];
    $secCounts  = $_POST['section_count'] ?? [];

    // ── Step 1: Extract text from file ───────────────────────
    if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['lesson_file']['name'];
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $tmpPath  = $_FILES['lesson_file']['tmp_name'];
        $rawBytes = file_get_contents($tmpPath);

        $extracted = '';

        if ($rawBytes !== false && strlen($rawBytes) > 0) {
            // Write to a properly named temp file (single extension only)
            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seait_ai_' . uniqid() . '.' . $ext;
            if (file_put_contents($dest, $rawBytes) !== false) {
                $extracted = AIGenerator::extractText($dest);
                @unlink($dest);
            }
        }

        $extracted = trim(preg_replace('/\s+/', ' ', $extracted));

        if (strlen($extracted) >= 20) {
            // Good extraction
            $textInput = $extracted;
            if (empty(trim($quizTitle))) {
                $quizTitle = pathinfo($fileName, PATHINFO_FILENAME) . ' Quiz';
            }
            $debugInfo = "✅ Extracted " . number_format(strlen($extracted)) . " characters from \"{$fileName}\"";
        } else {
            // Extraction failed or too little text — warn but DON'T block
            // Teacher can still paste text manually; if textInput was already set (pasted), use that
            $warnings[] = "⚠ Could not extract enough text from \"{$fileName}\" (only " . strlen($extracted) . " chars). "
                         . "If you pasted text below, that will be used instead. "
                         . "For best results: save your content as a .txt file, or paste the text directly.";
            if (!empty($extracted)) {
                $textInput = $extracted; // use whatever we got, even if sparse
            }
            // Keep any pasted text ($textInput already set above from $_POST)
        }
    }

    // ── Step 2: Auto-fill sections if empty ──────────────────
    $validSecs = array_filter($secNames, fn($n) => trim($n) !== '');
    if (empty($validSecs)) {
        $secNames  = ['Test I', 'Test II', 'Test III'];
        $secTypes  = ['multiple_choice', 'true_false', 'identification'];
        $secCounts = [10, 10, 5];
    }

    // ── Step 3: Validate ─────────────────────────────────────
    if (!$courseId)              $errors[] = 'Please select a subject.';
    if (empty(trim($quizTitle))) $errors[] = 'Please enter a quiz title.';
    if (empty($textInput))       $errors[] = 'Please provide lesson content — upload a file or paste text.';

    // ── Step 4: Create quiz + generate ALL sections in ONE API call ─────
    if (empty($errors)) {
        $newQuizId = Database::insert('quizzes', [
            'course_id'         => $courseId,
            'lesson_id'         => $lessonId,
            'teacher_id'        => $tid,
            'title'             => $quizTitle,
            'description'       => 'AI-generated quiz',
            'time_limit'        => $timeLimit ?: null,
            'passing_score'     => $passing,
            'max_attempts'      => 3,
            'shuffle_questions' => 1,
            'shuffle_choices'   => 1,
            'is_adaptive'       => 0,
            'is_published'      => 0,
            'created_by_ai'     => 1,
        ]);

        // Build section definitions for the batch call
        $sectionDefs = [];
        $sectionIds  = [];
        foreach ($secNames as $i => $secName) {
            $secName  = trim($secName);
            if (empty($secName)) continue;
            $secType  = $secTypes[$i]  ?? 'multiple_choice';
            $secCount = max(1, (int)($secCounts[$i] ?? 10));

            $secId = Database::insert('quiz_sections', [
                'quiz_id'        => $newQuizId,
                'name'           => $secName,
                'question_type'  => $secType,
                'question_count' => $secCount,
                'sort_order'     => $i,
            ]);

            $sectionDefs[]  = ['name' => $secName, 'type' => $secType, 'count' => $secCount];
            $sectionIds[]   = ['id' => $secId, 'name' => $secName, 'type' => $secType, 'count' => $secCount];
        }

        // ONE API call for all sections — much faster
        $allQuestions = AIGenerator::generateAll($textInput, $sectionDefs, $difficulty, $bloom);

        foreach ($sectionIds as $idx => $sec) {
            $questions = $allQuestions[$idx] ?? [];

            // Pad with fallback if API returned fewer than needed
            if (count($questions) < $sec['count']) {
                $extra     = AIGenerator::generate($textInput, $sec['count'] - count($questions), $sec['type'], $difficulty, $bloom);
                $questions = array_merge($questions, $extra);
            }

            $generatedSections[] = [
                'section'   => $sec,
                'questions' => array_slice($questions, 0, $sec['count']),
            ];
        }

        $totalGenerated = array_sum(array_map(fn($s) => count($s['questions']), $generatedSections));

        if ($totalGenerated === 0) {
            Database::delete('quizzes', 'id=?', [$newQuizId]);
            $errors[] = 'No questions were generated. Check the file content and try again.';
        } else {
            $step = 2;
            $_SESSION['ai_quiz_id']    = $newQuizId;
            $_SESSION['ai_quiz_title'] = $quizTitle;
            $_SESSION['ai_extracted']  = substr($textInput, 0, 300);
        }
    }
}
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

<!-- Header -->
<div class="flex items-center gap-3 mb-6">
    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center text-white text-xl shadow-md">✨</div>
    <div>
        <h2 class="text-xl font-bold text-gray-800">AI Quiz Generator</h2>
        <p class="text-sm text-gray-500">Upload a file or paste text — AI generates questions based on your content</p>
    </div>
    <span class="ml-auto px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5
        <?= AI_ENABLED ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>">
        <span class="w-2 h-2 rounded-full <?= AI_ENABLED ? 'bg-green-500' : 'bg-amber-500' ?>"></span>
        <?= AI_ENABLED ? 'AI Active (Groq LLaMA 3)' : 'Fallback Mode (no API key)' ?>
    </span>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-2xl px-5 py-4 space-y-1">
    <?php foreach ($errors as $err): ?><p>⚠ <?= e($err) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
<div class="mb-5 bg-amber-50 border border-amber-300 text-amber-800 text-sm rounded-2xl px-5 py-4 space-y-1">
    <?php foreach ($warnings as $w): ?><p>⚠ <?= e($w) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($step === 1): ?>
<!-- ═══════════════════════════════════════════════════════
     STEP 1 — Upload + Settings
═══════════════════════════════════════════════════════ -->

<?php if ($debugInfo): ?>
<div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-2xl px-5 py-3">
    <?= e($debugInfo) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

    <!-- LEFT: File + Text -->
    <div class="lg:col-span-3 space-y-4">

        <!-- Upload area -->
        <div id="dropZone"
             class="border-2 border-dashed border-orange-300 rounded-2xl p-8 text-center bg-orange-50/50 hover:bg-orange-50 hover:border-orange-500 transition cursor-pointer"
             onclick="document.getElementById('fileInput').click()"
             ondragover="event.preventDefault();this.classList.add('border-orange-500','bg-orange-100')"
             ondragleave="this.classList.remove('border-orange-500','bg-orange-100')"
             ondrop="handleDrop(event)">

            <div id="idleState">
                <div class="text-5xl mb-3">📁</div>
                <p class="font-semibold text-orange-700 mb-1">Drop your lesson file here</p>
                <p class="text-sm text-gray-500 mb-4">PDF · PPTX · DOCX · TXT</p>
                <span class="px-5 py-2 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition">
                    Browse File
                </span>
            </div>

            <div id="readyState" class="hidden">
                <div class="text-5xl mb-3">✅</div>
                <p id="fileName" class="font-semibold text-orange-700 mb-1 text-base"></p>
                <p class="text-sm text-gray-500">File selected — click <strong>Generate Quiz Now</strong> below</p>
            </div>

            <form method="POST" enctype="multipart/form-data" id="genForm" style="display:none">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="generate">
                <input type="hidden" name="course_id" id="hCourseId">
                <input type="hidden" name="lesson_id" id="hLessonId">
                <input type="hidden" name="quiz_title" id="hQuizTitle">
                <input type="hidden" name="passing_score" id="hPassing" value="60">
                <input type="hidden" name="time_limit" id="hTimeLimit">
                <input type="hidden" name="difficulty" id="hDifficulty" value="medium">
                <input type="hidden" name="bloom_level" id="hBloom" value="understand">
                <!-- Section hidden fields injected by JS -->
                <div id="hSections"></div>
                <input type="file" id="fileInput" name="lesson_file"
                       accept=".pdf,.pptx,.ppt,.docx,.doc,.txt" onchange="onFileChosen(this)">
                <textarea name="lesson_text" id="hLessonText" style="display:none"></textarea>
            </form>
        </div>

        <!-- OR divider -->
        <div class="flex items-center gap-3">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs text-gray-400 font-medium">OR PASTE TEXT</span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <!-- Paste text -->
        <div>
            <textarea id="pasteText" rows="10"
                      placeholder="Paste your lesson content here…"
                      class="w-full px-4 py-3 rounded-2xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none bg-white shadow-sm"><?= e($extractedText) ?></textarea>
            <div class="flex justify-between text-xs text-gray-400 mt-1 px-1">
                <span id="charCount">0 characters</span>
                <span>More text = better questions</span>
            </div>
        </div>

        <!-- Paste submit button -->
        <button type="button" onclick="submitFromText()"
                class="w-full py-3 bg-gray-800 text-white font-semibold text-sm rounded-xl hover:bg-gray-900 transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Generate from Pasted Text
        </button>
    </div>

    <!-- RIGHT: Settings -->
    <div class="lg:col-span-2 space-y-4">

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Quiz Settings</h3>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Subject *</label>
                <select id="courseSelect" required
                        onchange="loadLessons(this.value)"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['code'] . ' — ' . $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="subjectHint" class="hidden text-xs text-orange-600 font-semibold mt-1 animate-pulse">
                    ⬆ Select a subject to generate from your file
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Lesson <span class="text-gray-400 font-normal">(optional)</span></label>
                <select id="lessonSelect"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">-- Select subject first --</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Quiz Title *</label>
                <input type="text" id="quizTitleInput" placeholder="e.g. Chapter 3 Quiz"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Difficulty</label>
                    <select id="diffSelect" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Bloom Level</label>
                    <select id="bloomSelect" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <?php foreach (['remember','understand','apply','analyze','evaluate','create'] as $b): ?>
                        <option value="<?= $b ?>" <?= $b==='understand'?'selected':'' ?>><?= ucfirst($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Passing Score (%)</label>
                    <input type="number" id="passingInput" value="60" min="1" max="100"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Time Limit (min)</label>
                    <input type="number" id="timeLimitInput" placeholder="None" min="1"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>
        </div>

        <!-- Sections -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Sections</h3>
                <button type="button" onclick="addSection()" class="text-xs text-orange-600 font-semibold hover:text-orange-700">+ Add</button>
            </div>
            <div id="sectionsUI" class="space-y-2">
                <div class="section-row grid grid-cols-[1fr_auto_auto_auto] gap-1.5 items-center">
                    <input type="text" class="sec-name px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-1 focus:ring-orange-400" value="Test I" placeholder="Name">
                    <select class="sec-type px-2 py-1.5 rounded-lg border border-gray-200 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-400">
                        <option value="multiple_choice" selected>MC</option>
                        <option value="true_false">T/F</option>
                        <option value="identification">ID</option>
                        <option value="short_answer">SA</option>
                    </select>
                    <input type="number" class="sec-count w-14 px-2 py-1.5 rounded-lg border border-gray-200 text-xs text-center focus:outline-none focus:ring-1 focus:ring-orange-400" value="10" min="1" max="50">
                    <button type="button" onclick="removeSection(this)" class="text-gray-300 hover:text-red-500 text-sm">✕</button>
                </div>
                <div class="section-row grid grid-cols-[1fr_auto_auto_auto] gap-1.5 items-center">
                    <input type="text" class="sec-name px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-1 focus:ring-orange-400" value="Test II" placeholder="Name">
                    <select class="sec-type px-2 py-1.5 rounded-lg border border-gray-200 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-400">
                        <option value="multiple_choice">MC</option>
                        <option value="true_false" selected>T/F</option>
                        <option value="identification">ID</option>
                        <option value="short_answer">SA</option>
                    </select>
                    <input type="number" class="sec-count w-14 px-2 py-1.5 rounded-lg border border-gray-200 text-xs text-center focus:outline-none focus:ring-1 focus:ring-orange-400" value="10" min="1" max="50">
                    <button type="button" onclick="removeSection(this)" class="text-gray-300 hover:text-red-500 text-sm">✕</button>
                </div>
                <div class="section-row grid grid-cols-[1fr_auto_auto_auto] gap-1.5 items-center">
                    <input type="text" class="sec-name px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-1 focus:ring-orange-400" value="Test III" placeholder="Name">
                    <select class="sec-type px-2 py-1.5 rounded-lg border border-gray-200 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-400">
                        <option value="multiple_choice">MC</option>
                        <option value="true_false">T/F</option>
                        <option value="identification" selected>ID</option>
                        <option value="short_answer">SA</option>
                    </select>
                    <input type="number" class="sec-count w-14 px-2 py-1.5 rounded-lg border border-gray-200 text-xs text-center focus:outline-none focus:ring-1 focus:ring-orange-400" value="5" min="1" max="50">
                    <button type="button" onclick="removeSection(this)" class="text-gray-300 hover:text-red-500 text-sm">✕</button>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MC=Multiple Choice · T/F=True/False · ID=Fill-in-blank · SA=Short Answer</p>
        </div>

    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════
     STEP 2 — Review & Save
═══════════════════════════════════════════════════════ -->
<?php
    $newQuizId    = $_SESSION['ai_quiz_id']    ?? 0;
    $newQuizTitle = $_SESSION['ai_quiz_title'] ?? 'New Quiz';
    $aiExtracted  = $_SESSION['ai_extracted']  ?? '';
    $totalQ = array_sum(array_map(fn($s) => count($s['questions']), $generatedSections));
?>

<!-- Status -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 bg-green-50 border border-green-200 rounded-2xl px-5 py-4">
    <div>
        <p class="font-semibold text-green-800">✅ <?= $totalQ ?> questions generated for <strong>"<?= e($newQuizTitle) ?>"</strong></p>
        <p class="text-sm text-green-600 mt-0.5">Questions are based on your lesson content. Edit if needed, then save.</p>
        <?php if ($aiExtracted): ?>
        <p class="text-xs text-gray-400 mt-1">Content used: "<?= e(substr($aiExtracted, 0, 120)) ?>…"</p>
        <?php endif; ?>
    </div>
    <div class="flex gap-2 flex-shrink-0">
        <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php"
           class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
            📋 Go to Quizzes
        </a>
        <a href="<?= BASE_URL ?>/modules/teacher/ai_quiz_generator.php"
           onclick="return confirm('Start over? The draft quiz will remain in your list.')"
           class="px-4 py-2 bg-white border border-gray-200 text-gray-600 text-sm rounded-xl hover:bg-gray-50">
            ← Start Over
        </a>
    </div>
</div>

<form method="POST" id="saveForm">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="action" value="save_questions">
    <input type="hidden" name="quiz_id" value="<?= $newQuizId ?>">
    <input type="hidden" name="sections_json" id="sectionsJson">

    <?php foreach ($generatedSections as $si => $secData):
        $sec = $secData['section'];
        $qs  = $secData['questions'];
        $typeLabels = ['multiple_choice'=>'Multiple Choice','true_false'=>'True/False',
                       'identification'=>'Identification','short_answer'=>'Short Answer'];
    ?>
    <div class="flex items-center gap-3 mb-4 <?= $si > 0 ? 'mt-8' : '' ?>">
        <span class="px-4 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-full"><?= e($sec['name']) ?></span>
        <span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs rounded-full"><?= $typeLabels[$sec['type']] ?? $sec['type'] ?></span>
        <span class="text-xs text-gray-400"><?= count($qs) ?> questions</span>
        <div class="flex-1 h-px bg-gray-200"></div>
    </div>

    <div class="space-y-3 mb-2"
         data-section-idx="<?= $si ?>"
         data-section-id="<?= $sec['id'] ?>"
         data-section-type="<?= e($sec['type']) ?>">
        <?php foreach ($qs as $qi => $q): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 question-card"
             data-sec="<?= $si ?>">

            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-7 h-7 bg-orange-500 text-white rounded-lg flex items-center justify-center text-xs font-bold"><?= $qi+1 ?></span>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500"><?= ucfirst($q['difficulty']??'medium') ?></span>
                    <span class="px-2 py-0.5 rounded-full text-xs <?= bloomBadgeColor($q['bloom_level']??'understand') ?>"><?= ucfirst($q['bloom_level']??'understand') ?></span>
                    <?php if (!empty($q['topic'])): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600"><?= e($q['topic']) ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="removeQ(this)" class="text-gray-300 hover:text-red-500 text-sm" title="Remove">✕</button>
            </div>

            <div class="mb-3">
                <label class="block text-xs text-gray-400 mb-1">Question</label>
                <textarea class="q-text w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none" rows="2"><?= e($q['question_text']) ?></textarea>
            </div>

            <?php if (!empty($q['choices']) && is_array($q['choices'])): ?>
            <div class="mb-3">
                <label class="block text-xs text-gray-400 mb-1">Choices</label>
                <div class="space-y-1.5">
                <?php foreach ($q['choices'] as $c): ?>
                <div class="flex items-center gap-2">
                    <span class="w-6 text-xs font-bold text-gray-500 flex-shrink-0"><?= e($c['label']??'') ?>.</span>
                    <input type="text" class="choice-text flex-1 px-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-300" value="<?= e($c['text']??'') ?>">
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Correct Answer</label>
                    <input type="text" class="q-answer w-full px-3 py-1.5 rounded-xl border border-gray-200 text-sm bg-green-50 focus:outline-none focus:ring-1 focus:ring-orange-300" value="<?= e($q['correct_answer']) ?>">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Difficulty</label>
                    <select class="q-diff w-full px-3 py-1.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-orange-300">
                        <?php foreach (['easy','medium','hard'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($q['difficulty']??'medium')===$d?'selected':''?>><?= ucfirst($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Explanation</label>
                <textarea class="q-explanation w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-orange-300 resize-none" rows="2"><?= e($q['explanation']??'') ?></textarea>
            </div>

            <input type="hidden" class="q-type"  value="<?= e($q['question_type']??$sec['type']) ?>">
            <input type="hidden" class="q-bloom" value="<?= e($q['bloom_level']??'understand') ?>">
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- Save bar -->
    <div class="sticky bottom-4 mt-6">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-lg px-5 py-4 flex flex-wrap items-center gap-3">
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800">Save <span id="qCount"><?= $totalQ ?></span> questions to quiz</p>
                <p class="text-xs text-gray-400">Quiz saved as draft — publish from the Quizzes page</p>
            </div>
            <button type="button" onclick="doSave()"
                    class="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm rounded-xl transition shadow-md">
                💾 Save All to Quiz
            </button>
            <a href="<?= BASE_URL ?>/modules/teacher/question_bank.php?quiz_id=<?= $_SESSION['ai_quiz_id'] ?? 0 ?>"
               class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition">
                📋 Go to Question Bank
            </a>
            <a href="<?= BASE_URL ?>/modules/teacher/quizzes.php"
               class="px-5 py-3 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200 transition">
                View Quizzes
            </a>
        </div>
    </div>
</form>
<?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
<script>
// ── Character counter ────────────────────────────────────────
const pt = document.getElementById('pasteText');
const cc = document.getElementById('charCount');
if (pt && cc) {
    const upd = () => cc.textContent = pt.value.length.toLocaleString() + ' characters';
    pt.addEventListener('input', upd); upd();
}

// ── Load lessons ─────────────────────────────────────────────
function loadLessons(cid) {
    const sel = document.getElementById('lessonSelect');
    if (!cid) { sel.innerHTML = '<option value="">-- Select subject first --</option>'; return; }
    sel.innerHTML = '<option value="">Loading…</option>';
    fetch('<?= BASE_URL ?>/modules/api/get_lessons.php?course_id=' + cid)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">-- No lesson --</option>';
            data.forEach(l => {
                const o = document.createElement('option');
                o.value = l.id; o.textContent = l.title;
                sel.appendChild(o);
            });
        })
        .catch(() => sel.innerHTML = '<option value="">Could not load</option>');
}

document.getElementById('lessonSelect')?.addEventListener('change', function() {
    const t = document.getElementById('quizTitleInput');
    if (this.value && !t.value.trim()) {
        t.value = this.options[this.selectedIndex].text + ' Quiz';
    }
});

// ── Section builder ──────────────────────────────────────────
function addSection() {
    const c = document.getElementById('sectionsUI');
    const n = c.querySelectorAll('.section-row').length + 1;
    const div = document.createElement('div');
    div.className = 'section-row grid grid-cols-[1fr_auto_auto_auto] gap-1.5 items-center';
    div.innerHTML = `
        <input type="text" class="sec-name px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-1 focus:ring-orange-400" value="Test ${['I','II','III','IV','V'][n-1]||n}" placeholder="Name">
        <select class="sec-type px-2 py-1.5 rounded-lg border border-gray-200 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-orange-400">
            <option value="multiple_choice" selected>MC</option>
            <option value="true_false">T/F</option>
            <option value="identification">ID</option>
            <option value="short_answer">SA</option>
        </select>
        <input type="number" class="sec-count w-14 px-2 py-1.5 rounded-lg border border-gray-200 text-xs text-center focus:outline-none focus:ring-1 focus:ring-orange-400" value="10" min="1" max="50">
        <button type="button" onclick="removeSection(this)" class="text-gray-300 hover:text-red-500 text-sm">✕</button>`;
    c.appendChild(div);
}
function removeSection(btn) {
    const c = document.getElementById('sectionsUI');
    if (c.querySelectorAll('.section-row').length <= 1) { alert('Need at least one section.'); return; }
    btn.closest('.section-row').remove();
}

// ════════════════════════════════════════════════════════════
// FILE UPLOAD — PDF.js client-side extraction (bulletproof)
// ════════════════════════════════════════════════════════════

// Step 1: File chosen — enforce subject first, then extract text in browser
async function onFileChosen(input) {
    const cid = document.getElementById('courseSelect').value;

    // Enforce subject selection BEFORE reading file
    if (!cid) {
        alert('⚠️ Please select a Subject first before uploading a file.');
        document.getElementById('courseSelect').classList.add('ring-2','ring-orange-500');
        document.getElementById('courseSelect').focus();
        input.value = ''; // clear so they can re-select file after picking subject
        return;
    }
    document.getElementById('courseSelect').classList.remove('ring-2','ring-orange-500');

    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const ext  = file.name.split('.').pop().toLowerCase();

    if (!['pdf','txt','docx','doc','pptx','ppt'].includes(ext)) {
        alert('Supported formats: PDF, TXT, DOCX, PPTX');
        input.value = '';
        return;
    }

    // Show reading state
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('readyState').classList.remove('hidden');
    document.getElementById('fileName').textContent = '⏳ Reading ' + file.name + '…';

    try {
        let extractedText = '';

        if (ext === 'pdf') {
            // ── PDF: use PDF.js to extract text page by page ──────────
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js not loaded. Please refresh the page and try again, or paste the text manually.');
            }
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const maxPages = Math.min(pdf.numPages, 20);
            for (let i = 1; i <= maxPages; i++) {
                const page    = await pdf.getPage(i);
                const content = await page.getTextContent();
                extractedText += content.items.map(item => item.str).join(' ') + '\n';
            }
        } else {
            // ── TXT / DOCX / PPTX: read raw text ──────────────────────
            // For DOCX/PPTX, file.text() won't parse binary — we get XML chars
            // but it's better than nothing; PHP fallback handles the rest
            extractedText = await file.text();
        }

        extractedText = extractedText.replace(/\s+/g, ' ').trim();

        if (extractedText.length < 30) {
            throw new Error('The file appears empty or unreadable. Try a .txt file or paste the content.');
        }

        // ✅ Pass extracted text to the hidden textarea — PHP receives it as lesson_text
        document.getElementById('hLessonText').value = extractedText;
        document.getElementById('fileName').textContent = '✅ ' + file.name + ' (' + extractedText.length.toLocaleString() + ' chars read)';

        // Auto-fill title
        const t = document.getElementById('quizTitleInput');
        if (!t.value.trim()) {
            t.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g,' ')
                               .replace(/\b\w/g, c => c.toUpperCase()) + ' Quiz';
        }

        // Auto-submit generation
        submitGeneratedQuiz();

    } catch (err) {
        // Show a clear error — don't try to submit
        alert('❌ Could not read file: ' + err.message + '\n\nTip: Try a .txt file or paste the text manually in the box below.');
        document.getElementById('readyState')?.classList.add('hidden');
        document.getElementById('idleState')?.classList.remove('hidden');
        input.value = '';
    }
}

// Step 2: Fill hidden form fields and submit to PHP
function submitGeneratedQuiz() {
    const cid = document.getElementById('courseSelect').value;
    if (!cid) { alert('Please select a subject.'); return; }

    const form = document.getElementById('genForm');
    if (!form) {
        // genForm not found — likely PDF.js loaded on step 2 page; just reload step 1
        alert('Please go back and try again — the form was not found.');
        return;
    }

    document.getElementById('hCourseId').value   = cid;
    document.getElementById('hLessonId').value   = document.getElementById('lessonSelect')?.value || '';
    document.getElementById('hQuizTitle').value  = document.getElementById('quizTitleInput')?.value || '';
    document.getElementById('hPassing').value    = document.getElementById('passingInput')?.value || 60;
    document.getElementById('hTimeLimit').value  = document.getElementById('timeLimitInput')?.value || '';
    document.getElementById('hDifficulty').value = document.getElementById('diffSelect')?.value || 'medium';
    document.getElementById('hBloom').value      = document.getElementById('bloomSelect')?.value || 'understand';

    // Inject section hidden inputs
    const hSec = document.getElementById('hSections');
    if (hSec) {
        hSec.innerHTML = '';
        document.querySelectorAll('#sectionsUI .section-row').forEach(row => {
            const name  = row.querySelector('.sec-name')?.value || '';
            const type  = row.querySelector('.sec-type')?.value || 'multiple_choice';
            const count = row.querySelector('.sec-count')?.value || '10';
            hSec.innerHTML +=
                `<input type="hidden" name="section_name[]" value="${name}">` +
                `<input type="hidden" name="section_type[]" value="${type}">` +
                `<input type="hidden" name="section_count[]" value="${count}">`;
        });
    }

    // Show spinner
    const dz = document.getElementById('dropZone');
    if (dz) dz.innerHTML = `
        <div class="text-5xl mb-3">⏳</div>
        <p class="font-semibold text-orange-700 mb-1 animate-pulse">Generating quiz with AI…</p>
        <p class="text-sm text-gray-500">Analyzing your document — please wait 15–30 seconds.</p>`;

    form.submit();
}

// Drag-and-drop support
function handleDrop(event) {
    event.preventDefault();
    document.getElementById('dropZone')?.classList.remove('border-orange-500','bg-orange-100');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    const fi = document.getElementById('fileInput');
    fi.files = dt.files;
    onFileChosen(fi);
}

// Manual paste text submit
function submitFromText() {
    const text = document.getElementById('pasteText').value.trim();
    if (text.length < 50) { alert('Please paste at least 50 characters of content.'); return; }
    document.getElementById('hLessonText').value = text;
    document.getElementById('fileInput').value   = '';
    submitGeneratedQuiz();
}

// ── Step 2: Remove question ──────────────────────────────────
function removeQ(btn) {
    const container = btn.closest('[data-section-idx]');
    if (container && container.querySelectorAll('.question-card').length <= 1) {
        alert('Each section needs at least one question.'); return;
    }
    btn.closest('.question-card').remove();
    const qc = document.getElementById('qCount');
    if (qc) qc.textContent = document.querySelectorAll('.question-card').length;
}

// ── Step 2: Save all ─────────────────────────────────────────
function doSave() {
    const sections = [];
    document.querySelectorAll('[data-section-idx]').forEach(secEl => {
        const sid    = secEl.dataset.sectionId;
        const stype  = secEl.dataset.sectionType;
        const labels = ['A','B','C','D'];
        const qs     = [];
        secEl.querySelectorAll('.question-card').forEach(card => {
            const qText = card.querySelector('.q-text')?.value.trim() ?? '';
            if (!qText) return;
            const choiceInputs = card.querySelectorAll('.choice-text');
            const choices = choiceInputs.length
                ? Array.from(choiceInputs).map((inp, i) => ({ label: labels[i] ?? String(i+1), text: inp.value.trim() }))
                : null;
            qs.push({
                question_text:  qText,
                question_type:  card.querySelector('.q-type')?.value  ?? stype,
                choices,
                correct_answer: card.querySelector('.q-answer')?.value.trim()      ?? '',
                explanation:    card.querySelector('.q-explanation')?.value.trim() ?? '',
                difficulty:     card.querySelector('.q-diff')?.value               ?? 'medium',
                bloom_level:    card.querySelector('.q-bloom')?.value              ?? 'understand',
            });
        });
        if (qs.length) sections.push({ section_id: sid, questions: qs });
    });
    if (!sections.length) { alert('No questions to save.'); return; }
    document.getElementById('sectionsJson').value = JSON.stringify(sections);
    document.getElementById('saveForm').submit();
}
</script>
</body>
</html>
