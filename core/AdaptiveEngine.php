<?php
/**
 * SEAIT E-Learning — Adaptive Quiz Engine
 *
 * Selects the next question based on a student's running performance,
 * adjusting difficulty after every answer.
 */

class AdaptiveEngine
{
    // Minimum consecutive correct answers to level up difficulty
    private const STREAK_UP   = 2;
    // Minimum consecutive incorrect answers to level down
    private const STREAK_DOWN = 2;

    /**
     * Select the next question for an in-progress attempt.
     *
     * @param int    $quizId    Quiz being taken
     * @param int    $attemptId Running attempt
     * @param array  $answered  Array of question IDs already answered this session
     * @return array|null       Next question row, or null if quiz is complete
     */
    public static function nextQuestion(int $quizId, int $attemptId, array $answered = []): ?array
    {
        $currentDifficulty = self::currentDifficulty($attemptId, $answered);

        // Try to fetch a question at the target difficulty not yet answered
        $placeholders = count($answered) > 0
            ? implode(',', array_fill(0, count($answered), '?'))
            : '0';

        $params = array_merge([$quizId, $currentDifficulty], $answered);

        $question = Database::fetchOne(
            "SELECT * FROM quiz_questions
             WHERE quiz_id = ?
               AND difficulty = ?
               AND id NOT IN ({$placeholders})
               AND is_active = 1
             ORDER BY RAND()
             LIMIT 1",
            $params
        );

        // Fallback: any difficulty not yet answered
        if (!$question && count($answered) > 0) {
            $params2 = array_merge([$quizId], $answered);
            $question = Database::fetchOne(
                "SELECT * FROM quiz_questions
                 WHERE quiz_id = ?
                   AND id NOT IN ({$placeholders})
                   AND is_active = 1
                 ORDER BY RAND()
                 LIMIT 1",
                $params2
            );
        }

        // Final fallback: no answered yet
        if (!$question && count($answered) === 0) {
            $question = Database::fetchOne(
                "SELECT * FROM quiz_questions
                 WHERE quiz_id = ? AND is_active = 1
                 ORDER BY sort_order, RAND()
                 LIMIT 1",
                [$quizId]
            );
        }

        return $question ?: null;
    }

    /**
     * Determine the appropriate difficulty for the next question.
     */
    public static function currentDifficulty(int $attemptId, array $answered): string
    {
        if (empty($answered)) {
            return 'easy';  // always start easy
        }

        // Get the last N answers
        $recent = Database::fetchAll(
            "SELECT sa.is_correct, sa.difficulty_at_answer
             FROM student_answers sa
             WHERE sa.attempt_id = ?
             ORDER BY sa.answered_at DESC
             LIMIT 4",
            [$attemptId]
        );

        if (empty($recent)) {
            return 'easy';
        }

        $lastDifficulty = $recent[0]['difficulty_at_answer'] ?? 'easy';

        // Count streak in the recent window
        $correctStreak   = 0;
        $incorrectStreak = 0;

        foreach ($recent as $r) {
            if ($r['is_correct']) {
                $correctStreak++;
                $incorrectStreak = 0;
            } else {
                $incorrectStreak++;
                $correctStreak = 0;
            }
        }

        // Level up
        if ($correctStreak >= self::STREAK_UP) {
            return match ($lastDifficulty) {
                'easy'   => 'medium',
                'medium' => 'hard',
                default  => 'hard',
            };
        }

        // Level down
        if ($incorrectStreak >= self::STREAK_DOWN) {
            return match ($lastDifficulty) {
                'hard'   => 'medium',
                'medium' => 'easy',
                default  => 'easy',
            };
        }

        return $lastDifficulty;
    }

    /**
     * Evaluate a student's answer and store the result.
     *
     * @return array ['is_correct' => bool, 'points' => float, 'explanation' => string]
     */
    public static function evaluateAnswer(
        int $attemptId,
        int $questionId,
        string $studentAnswer,
        int $responseTime = 0
    ): array {
        $question = Database::fetchOne(
            "SELECT * FROM quiz_questions WHERE id = ? LIMIT 1",
            [$questionId]
        );

        if (!$question) {
            return ['is_correct' => false, 'points' => 0, 'explanation' => ''];
        }

        $isCorrect = self::checkAnswer($question, $studentAnswer);
        $points    = $isCorrect ? (float) $question['points'] : 0.0;

        // Store the answer
        Database::insert('student_answers', [
            'attempt_id'           => $attemptId,
            'question_id'          => $questionId,
            'student_answer'       => $studentAnswer,
            'is_correct'           => $isCorrect ? 1 : 0,
            'points_earned'        => $points,
            'response_time'        => $responseTime,
            'difficulty_at_answer' => $question['difficulty'],
            'answered_at'          => date('Y-m-d H:i:s'),
        ]);

        return [
            'is_correct'  => $isCorrect,
            'points'      => $points,
            'explanation' => $question['explanation'] ?? '',
        ];
    }

    /**
     * Fuzzy answer checking for different question types.
     */
    private static function checkAnswer(array $question, string $studentAnswer): bool
    {
        $correct = strtolower(trim($question['correct_answer']));
        $given   = strtolower(trim($studentAnswer));

        return match ($question['question_type']) {
            'multiple_choice', 'true_false' => $correct === $given,
            'identification'  => $correct === $given || similar_text($correct, $given) / max(strlen($correct), 1) >= 0.85,
            'short_answer'    => $correct === $given || similar_text($correct, $given) / max(strlen($correct), 1) >= 0.75,
            default           => $correct === $given,
        };
    }

    /**
     * Finalize a quiz attempt: compute score and mark weak topics.
     */
    public static function finalizeAttempt(int $attemptId): array
    {
        $answers = Database::fetchAll(
            "SELECT sa.*, qq.points, qq.topic_id, qq.difficulty
             FROM student_answers sa
             JOIN quiz_questions qq ON qq.id = sa.question_id
             WHERE sa.attempt_id = ?",
            [$attemptId]
        );

        $total        = count($answers);
        $correct      = array_filter($answers, fn($a) => $a['is_correct']);
        $totalPoints  = array_sum(array_column($answers, 'points'));
        $earnedPoints = array_sum(array_column($answers, 'points_earned'));
        $pct          = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;

        // Topic performance
        $topicPerf = [];
        foreach ($answers as $ans) {
            $tid = $ans['topic_id'] ?? 'general';
            if (!isset($topicPerf[$tid])) {
                $topicPerf[$tid] = ['correct' => 0, 'total' => 0];
            }
            $topicPerf[$tid]['total']++;
            if ($ans['is_correct']) {
                $topicPerf[$tid]['correct']++;
            }
        }

        // Update attempt record
        Database::query(
            "UPDATE quiz_attempts
             SET score = ?, percentage = ?, total_questions = ?,
                 correct_count = ?, incorrect_count = ?,
                 status = 'completed', submitted_at = NOW()
             WHERE id = ?",
            [
                $earnedPoints,
                $pct,
                $total,
                count($correct),
                $total - count($correct),
                $attemptId,
            ]
        );

        return [
            'score'          => $earnedPoints,
            'total_points'   => $totalPoints,
            'percentage'     => $pct,
            'total'          => $total,
            'correct'        => count($correct),
            'incorrect'      => $total - count($correct),
            'topic_perf'     => $topicPerf,
        ];
    }

    /**
     * Update student_competencies mastery_pct and status after a quiz attempt.
     * Called from take_quiz.php after finalizeAttempt().
     *
     * @param int   $studentId
     * @param int   $courseId
     * @param array $topicPerf  ['topic_id' => ['correct'=>N,'total'=>N], ...]
     */
    public static function updateCompetencyMastery(int $studentId, int $courseId, array $topicPerf): void
    {
        if (empty($topicPerf)) return;

        // Get all competencies for this course that are linked to topics
        $competencies = Database::fetchAll(
            "SELECT id, topic_id, prerequisite_id, required_mastery
             FROM competencies
             WHERE course_id = ? AND topic_id IS NOT NULL",
            [$courseId]
        );

        foreach ($competencies as $comp) {
            $tid = $comp['topic_id'];
            if (!isset($topicPerf[$tid])) continue;

            $perf    = $topicPerf[$tid];
            $mastery = $perf['total'] > 0
                ? (int) round($perf['correct'] / $perf['total'] * 100)
                : 0;

            // Determine new status
            $required = (int) $comp['required_mastery'];
            if ($mastery >= 90) {
                $newStatus = 'mastered';
            } elseif ($mastery >= $required) {
                $newStatus = 'completed';
            } else {
                $newStatus = 'learning';
            }

            // Check if row exists
            $existing = Database::fetchOne(
                "SELECT id, mastery_pct, status FROM student_competencies
                 WHERE student_id = ? AND competency_id = ?",
                [$studentId, $comp['id']]
            );

            if ($existing) {
                // Only update if mastery improved
                if ($mastery > (int)$existing['mastery_pct'] || $existing['status'] === 'locked') {
                    $updateData = [
                        'mastery_pct' => $mastery,
                        'status'      => $newStatus,
                    ];
                    if (in_array($existing['status'], ['locked','learning']) && $mastery >= $required) {
                        $updateData['mastered_at'] = date('Y-m-d H:i:s');
                    }
                    Database::update('student_competencies', $updateData, 'id = ?', [$existing['id']]);
                }
            } else {
                Database::insert('student_competencies', [
                    'student_id'    => $studentId,
                    'competency_id' => $comp['id'],
                    'mastery_pct'   => $mastery,
                    'status'        => $newStatus,
                    'unlocked_at'   => date('Y-m-d H:i:s'),
                    'mastered_at'   => $mastery >= 90 ? date('Y-m-d H:i:s') : null,
                ]);
            }
        }

        // Unlock competencies whose prerequisites are now completed
        $allComps = Database::fetchAll(
            "SELECT c.id, c.prerequisite_id, sc.status
             FROM competencies c
             LEFT JOIN student_competencies sc ON sc.competency_id = c.id AND sc.student_id = ?
             WHERE c.course_id = ? AND c.prerequisite_id IS NOT NULL",
            [$studentId, $courseId]
        );

        foreach ($allComps as $comp) {
            if (($comp['status'] ?? 'locked') !== 'locked') continue;

            // Check if prerequisite is completed or mastered
            $prereqStatus = Database::fetchOne(
                "SELECT status FROM student_competencies
                 WHERE student_id = ? AND competency_id = ?",
                [$studentId, $comp['prerequisite_id']]
            );

            if (in_array($prereqStatus['status'] ?? '', ['completed', 'mastered'])) {
                // Unlock this competency
                $scRow = Database::fetchOne(
                    "SELECT id FROM student_competencies WHERE student_id=? AND competency_id=?",
                    [$studentId, $comp['id']]
                );
                if ($scRow) {
                    Database::update('student_competencies',
                        ['status' => 'learning', 'unlocked_at' => date('Y-m-d H:i:s')],
                        'id = ?', [$scRow['id']]
                    );
                } else {
                    Database::insert('student_competencies', [
                        'student_id'    => $studentId,
                        'competency_id' => $comp['id'],
                        'mastery_pct'   => 0,
                        'status'        => 'learning',
                        'unlocked_at'   => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    /**
     * Identify weak topics for a student in a course.
     * A topic is "weak" if average correctness < 60%.
     */    public static function identifyWeakTopics(int $studentId, int $courseId): array
    {
        return Database::fetchAll(
            "SELECT t.id, t.title,
                    COUNT(sa.id) AS total_answers,
                    SUM(sa.is_correct) AS correct_answers,
                    ROUND(SUM(sa.is_correct) / COUNT(sa.id) * 100, 1) AS accuracy
             FROM student_answers sa
             JOIN quiz_questions qq ON qq.id = sa.question_id
             JOIN topics t          ON t.id  = qq.topic_id
             JOIN quizzes q         ON q.id  = qq.quiz_id
             JOIN quiz_attempts qa  ON qa.id = sa.attempt_id
             WHERE qa.student_id = ?
               AND q.course_id   = ?
               AND qa.status     = 'completed'
             GROUP BY t.id, t.title
             HAVING accuracy < 60
             ORDER BY accuracy ASC",
            [$studentId, $courseId]
        );
    }
}
