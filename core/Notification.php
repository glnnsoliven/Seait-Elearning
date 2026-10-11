<?php
/**
 * SEAIT E-Learning — Notification Service
 * Supports in-app notifications and optional email via PHP mail().
 */

class Notification
{
    /**
     * Create a single in-app notification and optionally send an email.
     */
    public static function send(int $userId, string $type, string $title, string $message, string $link = ''): void
    {
        try {
            Database::insert('notifications', [
                'user_id'   => $userId,
                'type'      => $type,
                'title'     => $title,
                'message'   => $message,
                'link'      => $link,
                'is_read'   => 0,
            ]);
        } catch (Throwable) {
            // silent
        }

        // Send email for high-priority notification types
        $emailTypes = ['quiz_result', 'quiz_deadline', 'achievement', 'new_quiz'];
        if (in_array($type, $emailTypes)) {
            self::sendEmail($userId, $title, $message, $link);
        }
    }

    /**
     * Send an email to a user using PHP mail().
     */
    private static function sendEmail(int $userId, string $title, string $message, string $link = ''): void
    {
        try {
            $user = Database::fetchOne(
                "SELECT email, first_name, last_name FROM users WHERE id = ? AND is_active = 1",
                [$userId]
            );
            if (!$user || empty($user['email'])) return;

            $to       = $user['email'];
            $name     = $user['first_name'] . ' ' . $user['last_name'];
            $siteName = APP_NAME;
            $year     = date('Y');
            $linkHtml = $link
                ? '<p style="text-align:center;margin-top:20px"><a href="' . htmlspecialchars($link) . '"
                   style="background:#4f46e5;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block">
                   View Details</a></p>'
                : '';

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif">
<div style="max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">
  <div style="background:linear-gradient(135deg,#4f46e5,#2563eb);padding:28px 32px;text-align:center">
    <h1 style="color:#fff;margin:0;font-size:22px">{$siteName}</h1>
  </div>
  <div style="padding:28px 32px">
    <p style="color:#374151;font-size:15px">Hi <strong>{$name}</strong>,</p>
    <h2 style="color:#111827;font-size:18px;margin:16px 0 8px">{$title}</h2>
    <p style="color:#4b5563;font-size:14px;line-height:1.6">{$message}</p>
    {$linkHtml}
  </div>
  <div style="background:#f9fafb;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb">
    <p style="color:#9ca3af;font-size:12px;margin:0">&copy; {$year} {$siteName}. This is an automated message.</p>
  </div>
</div>
</body>
</html>
HTML;

            $subject = "=?UTF-8?B?" . base64_encode("[{$siteName}] {$title}") . "?=";
            $headers = implode("\r\n", [
                "From: {$siteName} <no-reply@seait.edu.ph>",
                "Reply-To: no-reply@seait.edu.ph",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "X-Mailer: PHP/" . PHP_VERSION,
            ]);

            @mail($to, $subject, $html, $headers);
        } catch (Throwable) {
            // Silent — email is optional, never break the app
        }
    }

    /**
     * Send weekly performance digest emails to all active students.
     * Call this from a scheduled task (e.g., cron or admin trigger).
     */
    public static function sendWeeklyDigests(): void
    {
        $students = Database::fetchAll(
            "SELECT u.id, u.email, u.first_name, u.last_name, s.id student_id
             FROM users u JOIN students s ON s.user_id = u.id
             WHERE u.is_active = 1 AND u.role = 'student'"
        );

        foreach ($students as $student) {
            $sid = $student['student_id'];

            // Last 7 days quiz attempts
            $attempts = Database::fetchAll(
                "SELECT qa.percentage, q.title FROM quiz_attempts qa
                 JOIN quizzes q ON q.id = qa.quiz_id
                 WHERE qa.student_id = ? AND qa.status = 'completed'
                   AND qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY qa.submitted_at DESC LIMIT 5",
                [$sid]
            );

            if (empty($attempts)) continue; // No activity this week — skip

            $avgScore = round(array_sum(array_column($attempts, 'percentage')) / count($attempts), 1);

            $rows = '';
            foreach ($attempts as $a) {
                $score = round($a['percentage'], 1);
                $color = $score >= 75 ? '#16a34a' : ($score >= 60 ? '#2563eb' : '#dc2626');
                $rows .= "<tr><td style='padding:6px 0;color:#374151'>" . htmlspecialchars($a['title']) . "</td>"
                       . "<td style='padding:6px 0;text-align:right;font-weight:700;color:{$color}'>{$score}%</td></tr>";
            }

            $message = "Your average score this week: <strong>{$avgScore}%</strong>. "
                     . "You completed " . count($attempts) . " quiz attempt(s).";

            $html = "Weekly quiz summary — avg: {$avgScore}%";
            self::sendEmail($student['id'], "Your Weekly Performance Digest", $message,
                BASE_URL . '/modules/student/dashboard.php');
        }
    }

    /**
     * Send 24-hour deadline reminders for upcoming quizzes.
     * Call from a scheduled task.
     */
    public static function sendDeadlineReminders(): void
    {
        $upcoming = Database::fetchAll(
            "SELECT q.id quiz_id, q.title quiz_title, q.deadline, q.course_id,
                    e.student_id, u.id user_id
             FROM quizzes q
             JOIN enrollments e ON e.course_id = q.course_id AND e.status = 'active'
             JOIN students s    ON s.id = e.student_id
             JOIN users u       ON u.id = s.user_id
             LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = e.student_id AND qa.status = 'completed'
             WHERE q.is_published = 1
               AND q.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
               AND qa.id IS NULL"
        );

        foreach ($upcoming as $row) {
            $due = date('M d, Y g:i A', strtotime($row['deadline']));
            self::send(
                $row['user_id'],
                'quiz_deadline',
                'Quiz Deadline in 24 Hours',
                "Your quiz \"{$row['quiz_title']}\" is due on {$due}. Complete it before the deadline!",
                BASE_URL . '/modules/student/take_quiz.php?quiz_id=' . $row['quiz_id']
            );
        }
    }

    /**
     * Broadcast to all enrolled students in a course.
     */
    public static function broadcastToCourse(int $courseId, string $type, string $title, string $message, string $link = ''): void
    {
        $students = Database::fetchAll(
            "SELECT s.user_id FROM students s
             INNER JOIN enrollments e ON e.student_id = s.id
             WHERE e.course_id = ? AND e.status = 'active'",
            [$courseId]
        );

        foreach ($students as $s) {
            self::send((int) $s['user_id'], $type, $title, $message, $link);
        }
    }

    /**
     * Mark a notification as read.
     */
    public static function markRead(int $notifId, int $userId): void
    {
        Database::update('notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$notifId, $userId]
        );
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllRead(int $userId): void
    {
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get recent notifications for a user.
     */
    public static function getRecent(int $userId, int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Notification type icon mapping.
     */
    public static function icon(string $type): string
    {
        return match ($type) {
            'quiz_deadline'   => "\u{23F0}",
            'assignment'      => "\u{1F4DD}",
            'new_lesson'      => "\u{1F4D6}",
            'new_quiz'        => "\u{270F}",
            'quiz_result'     => "\u{1F4CA}",
            'achievement'     => "\u{1F3C6}",
            'study_reminder'  => "\u{1F4C5}",
            'announcement'    => "\u{1F4E2}",
            default           => "\u{1F514}",
        };
    }
}
