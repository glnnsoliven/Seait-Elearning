<?php
/**
 * SEAIT E-Learning — At-Risk Student Detector
 */

class AtRisk
{
    /**
     * Analyse a student's performance in a course and upsert their at-risk record.
     */
    public static function analyse(int $studentId, int $courseId): void
    {
        $score     = self::avgScore($studentId, $courseId);
        $inactive  = self::daysSinceLastActivity($studentId, $courseId);
        $missed    = self::missedQuizzes($studentId, $courseId);
        $lessonPct = self::lessonCompletionPct($studentId, $courseId);

        $indicators = [];
        $riskScore  = 0;

        if ($score !== null && $score < 60) {
            $indicators[] = 'Low average quiz score (' . $score . '%)';
            $riskScore   += 30;
        }
        if ($inactive > 7) {
            $indicators[] = "Inactive for {$inactive} days";
            $riskScore   += 25;
        }
        if ($missed > 0) {
            $indicators[] = "Missed {$missed} quiz(zes)";
            $riskScore   += 20;
        }
        if ($lessonPct < 30) {
            $indicators[] = 'Low lesson completion (' . $lessonPct . '%)';
            $riskScore   += 25;
        }

        if ($riskScore === 0) {
            // Resolve existing flag if any
            Database::query(
                "UPDATE at_risk_students SET is_resolved = 1, resolved_at = NOW()
                 WHERE student_id = ? AND course_id = ? AND is_resolved = 0",
                [$studentId, $courseId]
            );
            return;
        }

        $level = match (true) {
            $riskScore >= 70 => 'high',
            $riskScore >= 40 => 'medium',
            default          => 'low',
        };

        $weakTopics = array_column(
            AdaptiveEngine::identifyWeakTopics($studentId, $courseId),
            'title'
        );

        $intervention = self::suggestIntervention($level, $weakTopics);

        // Upsert
        $existing = Database::fetchOne(
            "SELECT id FROM at_risk_students WHERE student_id = ? AND course_id = ?",
            [$studentId, $courseId]
        );

        $data = [
            'risk_level'             => $level,
            'risk_score'             => $riskScore,
            'indicators'             => json_encode($indicators),
            'weak_topics'            => json_encode($weakTopics),
            'suggested_intervention' => $intervention,
            'is_resolved'            => 0,
            'flagged_at'             => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Database::update('at_risk_students', $data, 'id = ?', [$existing['id']]);
        } else {
            Database::insert('at_risk_students', array_merge($data, [
                'student_id' => $studentId,
                'course_id'  => $courseId,
            ]));
        }
    }

    private static function avgScore(int $studentId, int $courseId): ?float
    {
        $row = Database::fetchOne(
            "SELECT AVG(qa.percentage) AS avg_pct
             FROM quiz_attempts qa
             JOIN quizzes q ON q.id = qa.quiz_id
             WHERE qa.student_id = ? AND q.course_id = ? AND qa.status = 'completed'",
            [$studentId, $courseId]
        );
        return $row && $row['avg_pct'] !== null ? round((float) $row['avg_pct'], 2) : null;
    }

    private static function daysSinceLastActivity(int $studentId, int $courseId): int
    {
        $row = Database::fetchOne(
            "SELECT MAX(last_accessed) AS last
             FROM student_progress
             WHERE student_id = ? AND course_id = ?",
            [$studentId, $courseId]
        );
        if (!$row || !$row['last']) return 999;
        return (int) floor((time() - strtotime($row['last'])) / 86400);
    }

    private static function missedQuizzes(int $studentId, int $courseId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM quizzes q
             LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
             WHERE q.course_id = ? AND q.is_published = 1
               AND q.deadline < NOW()
               AND qa.id IS NULL",
            [$studentId, $courseId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    private static function lessonCompletionPct(int $studentId, int $courseId): float
    {
        $total = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM lessons WHERE course_id = ? AND is_published = 1",
            [$courseId]
        );
        $done = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM student_progress
             WHERE student_id = ? AND course_id = ? AND status = 'completed'",
            [$studentId, $courseId]
        );
        $t = (int) ($total['cnt'] ?? 0);
        $d = (int) ($done['cnt'] ?? 0);
        return $t > 0 ? round(($d / $t) * 100, 1) : 0;
    }

    private static function suggestIntervention(string $level, array $weakTopics): string
    {
        $topicsStr = !empty($weakTopics) ? implode(', ', array_slice($weakTopics, 0, 3)) : 'various topics';
        return match ($level) {
            'high'   => "Immediate intervention required. Schedule 1-on-1 tutoring on: {$topicsStr}. Review all missed quizzes.",
            'medium' => "Monitor closely. Recommend supplemental materials and practice quizzes for: {$topicsStr}.",
            default  => "Encourage continued engagement. Suggest reviewing: {$topicsStr}.",
        };
    }
}
