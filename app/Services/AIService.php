<?php
/**
 * SEAIT E-Learning — AI Service
 *
 * Orchestrates AI quiz generation: extract text from file,
 * generate questions via API, create quiz and sections in DB.
 */
class AIService
{
    /**
     * Full pipeline: file → extracted text → quiz record → questions.
     *
     * @param  array  $file        $_FILES['lesson_file'] entry
     * @param  array  $quizData    Quiz metadata (title, course_id, etc.)
     * @param  array  $sections    Section definitions [['name','type','count'], ...]
     * @param  string $difficulty  easy|medium|hard
     * @param  string $bloom       Bloom's level
     * @return array  ['quiz_id' => int, 'sections' => [...], 'errors' => [...]]
     */
    public static function generateFromFile(
        array  $file,
        array  $quizData,
        array  $sections,
        string $difficulty = 'medium',
        string $bloom      = 'understand'
    ): array {
        $result = ['quiz_id' => 0, 'sections' => [], 'errors' => []];

        // 1. Extract text from uploaded file
        $text = self::extractFromUpload($file);
        if (strlen(trim($text)) < 30 || str_starts_with(trim($text), 'Could not')) {
            $result['errors'][] = 'Could not extract text from "' . basename($file['name']) . '". '
                                . 'Please paste the content manually.';
            return $result;
        }

        // 2. Create quiz + sections
        $quizId = Database::insert('quizzes', array_merge($quizData, ['created_by_ai' => 1]));
        $result['quiz_id'] = $quizId;

        // 3. Generate questions per section
        foreach ($sections as $i => $sec) {
            $secName  = trim($sec['name'] ?? '');
            if (empty($secName)) continue;

            $secType  = $sec['type']  ?? 'multiple_choice';
            $secCount = max(1, (int)($sec['count'] ?? 10));

            $secId = Database::insert('quiz_sections', [
                'quiz_id'        => $quizId,
                'name'           => $secName,
                'question_type'  => $secType,
                'question_count' => $secCount,
                'sort_order'     => $i,
            ]);

            $questions = AIGenerator::generate($text, $secCount, $secType, $difficulty, $bloom);

            $result['sections'][] = [
                'section'   => ['id' => $secId, 'name' => $secName, 'type' => $secType, 'count' => $secCount],
                'questions' => $questions,
                'text_used' => substr($text, 0, 200) . '…', // for debug
            ];
        }

        if (empty($result['sections'])) {
            $result['errors'][] = 'No questions could be generated.';
        }

        return $result;
    }

    /**
     * Save generated questions to the database.
     */
    public static function saveQuestions(int $quizId, array $sections): int
    {
        $saved = 0;
        $sort  = 0;
        foreach ($sections as $sec) {
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
                $saved++;
            }
        }
        return $saved;
    }

    /**
     * Extract text from an uploaded file ($_FILES entry).
     */
    public static function extractFromUpload(array $file): string
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return '';

        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tmpPath = $file['tmp_name'];

        // Copy to named temp file so extractText() can detect by extension
        $named = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('seait_', true) . '.' . $ext;
        if (!copy($tmpPath, $named)) return '';

        $text = AIGenerator::extractText($named);
        @unlink($named);

        return $text;
    }
}
