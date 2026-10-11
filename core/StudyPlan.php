<?php
/**
 * SEAIT E-Learning — Study Plan Generator
 *
 * Uses a simplified SM-2 spaced repetition algorithm:
 * - Items answered correctly with high confidence are scheduled further out.
 * - Items answered incorrectly or from weak topics are scheduled sooner.
 * - New/unseen items are introduced at day 0 (today).
 */

class StudyPlan
{
    /**
     * Generate a spaced-repetition study plan for a student.
     * Clears incomplete future plans first, then reschedules using SM-2 logic.
     */
    public static function generate(int $studentId): void
    {
        // Remove future incomplete plans
        Database::query(
            "DELETE FROM study_plans WHERE student_id = ? AND is_completed = 0 AND plan_date >= CURDATE()",
            [$studentId]
        );

        $enrollments = Database::fetchAll(
            "SELECT e.course_id FROM enrollments e
             WHERE e.student_id = ? AND e.status = 'active'",
            [$studentId]
        );

        $items = [];

        foreach ($enrollments as $enr) {
            $courseId = (int) $enr['course_id'];

            // ── Weak topics (highest priority — schedule soon) ──
            $weak = AdaptiveEngine::identifyWeakTopics($studentId, $courseId);
            foreach (array_slice($weak, 0, 3) as $idx => $t) {
                // How weak? accuracy determines next interval
                $accuracy = (float) $t['accuracy'];
                $interval = self::sm2Interval($accuracy, $t['total_answers'] ?? 1);
                $items[] = [
                    'type'     => 'topic',
                    'id'       => $t['id'],
                    'title'    => 'Review: ' . $t['title'],
                    'priority' => 'high',
                    'minutes'  => 20,
                    'day'      => min($interval, 2), // weak topics never go beyond day 2
                ];
            }

            // ── Incomplete lessons (medium priority) ──
            $lessons = Database::fetchAll(
                "SELECT l.id, l.title, sp.completion_pct, sp.status sp_status
                 FROM lessons l
                 LEFT JOIN student_progress sp ON sp.lesson_id = l.id AND sp.student_id = ?
                 WHERE l.course_id = ? AND l.is_published = 1
                   AND (sp.status IS NULL OR sp.status != 'completed')
                 ORDER BY l.sort_order
                 LIMIT 6",
                [$studentId, $courseId]
            );

            foreach ($lessons as $idx => $l) {
                $pct      = (int)($l['completion_pct'] ?? 0);
                $inProg   = ($l['sp_status'] ?? '') === 'in_progress';
                // In-progress lessons go to day 0; unseen lessons spread from day 0
                $day      = $inProg ? 0 : min($idx, 5);
                $items[]  = [
                    'type'     => 'lesson',
                    'id'       => $l['id'],
                    'title'    => $l['title'],
                    'priority' => $inProg ? 'high' : 'medium',
                    'minutes'  => 30,
                    'day'      => $day,
                ];
            }

            // ── Pending quizzes ──
            $quizzes = Database::fetchAll(
                "SELECT q.id, q.title, q.deadline,
                        COALESCE(MAX(qa.percentage), 0) last_score,
                        COUNT(qa.id) attempt_count
                 FROM quizzes q
                 LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ? AND qa.status='completed'
                 WHERE q.course_id = ? AND q.is_published = 1
                   AND (qa.id IS NULL OR qa.id IS NOT NULL)
                 GROUP BY q.id
                 HAVING attempt_count = 0 OR last_score < 75
                 ORDER BY q.deadline ASC
                 LIMIT 4",
                [$studentId, $courseId]
            );

            foreach ($quizzes as $idx => $q) {
                $lastScore = (float)$q['last_score'];
                // Quizzes never attempted go to day 0-1; retake quizzes go based on SM-2 interval
                if ($q['attempt_count'] == 0) {
                    $day = min($idx, 1);
                } else {
                    $day = self::sm2Interval($lastScore, $q['attempt_count']);
                }
                // Respect deadline — if deadline is sooner than computed day, use deadline day
                if ($q['deadline']) {
                    $deadlineDays = (int) ceil((strtotime($q['deadline']) - time()) / 86400);
                    if ($deadlineDays >= 0 && $deadlineDays < $day) {
                        $day = max(0, $deadlineDays - 1);
                    }
                }
                $items[] = [
                    'type'     => 'quiz',
                    'id'       => $q['id'],
                    'title'    => 'Quiz: ' . $q['title'],
                    'priority' => 'high',
                    'minutes'  => 25,
                    'day'      => min($day, 13), // cap at 2 weeks
                ];
            }
        }

        if (empty($items)) return;

        // Sort: day ASC, then priority DESC (high first), then type
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, function($a, $b) use ($priorityOrder) {
            if ($a['day'] !== $b['day']) return $a['day'] <=> $b['day'];
            return ($priorityOrder[$a['priority']] ?? 2) <=> ($priorityOrder[$b['priority']] ?? 2);
        });

        // Insert — max 3 items per day, spread over 14 days
        $daySlots = array_fill(0, 14, 0); // track items per day
        foreach ($items as $item) {
            $day = (int) $item['day'];

            // If the computed day is full, shift to next available day
            while ($day < 14 && $daySlots[$day] >= 3) {
                $day++;
            }
            if ($day >= 14) continue; // No room in 14-day window

            $planDate = date('Y-m-d', strtotime("+{$day} days"));

            $data = [
                'student_id'        => $studentId,
                'title'             => $item['title'],
                'plan_date'         => $planDate,
                'estimated_minutes' => $item['minutes'],
                'priority'          => $item['priority'],
                'is_completed'      => 0,
            ];

            if ($item['type'] === 'lesson') {
                $data['lesson_id'] = $item['id'];
            } elseif ($item['type'] === 'topic') {
                $data['topic_id'] = $item['id'];
            } elseif ($item['type'] === 'quiz') {
                $data['quiz_id'] = $item['id'];
            }

            try {
                Database::insert('study_plans', $data);
                $daySlots[$day]++;
            } catch (Throwable) {}
        }
    }

    /**
     * SM-2 inspired interval calculator.
     *
     * Maps a performance score (0-100) and repetition count to a review interval in days.
     * - Score >= 85: good recall → long interval (4-13 days)
     * - Score 60-84: okay → medium interval (2-4 days)
     * - Score < 60:  poor  → short interval (0-1 days)
     *
     * @param  float $score       Percentage score (0–100)
     * @param  int   $repetitions How many times this has been reviewed
     * @return int                Days until next review
     */
    private static function sm2Interval(float $score, int $repetitions = 1): int
    {
        // Easiness factor (EF) — SM-2 formula simplified
        $ef = max(1.3, 2.5 - 0.8 * (1 - ($score / 100)));

        if ($score < 60) {
            // Failed — reset to interval 1
            return 1;
        }

        // Base intervals: rep 1 = 1 day, rep 2 = 3 days, rep N = prev * EF
        $interval = 1;
        for ($i = 1; $i < max(1, $repetitions); $i++) {
            $interval = (int) round($interval * $ef);
        }

        return min($interval, 13); // cap at 13 days (within 2-week window)
    }

    /**
     * Get study plan for a date range.
     */
    public static function getByRange(int $studentId, string $from, string $to): array
    {
        return Database::fetchAll(
            "SELECT sp.*, l.title AS lesson_title, t.title AS topic_title, q.title AS quiz_title
             FROM study_plans sp
             LEFT JOIN lessons l ON l.id = sp.lesson_id
             LEFT JOIN topics  t ON t.id  = sp.topic_id
             LEFT JOIN quizzes q ON q.id  = sp.quiz_id
             WHERE sp.student_id = ?
               AND sp.plan_date BETWEEN ? AND ?
             ORDER BY sp.plan_date, sp.priority DESC",
            [$studentId, $from, $to]
        );
    }

    /**
     * Mark a plan item as completed.
     */
    public static function complete(int $planId, int $studentId): void
    {
        Database::update(
            'study_plans',
            ['is_completed' => 1, 'completed_at' => date('Y-m-d H:i:s')],
            'id = ? AND student_id = ?',
            [$planId, $studentId]
        );
    }
}
