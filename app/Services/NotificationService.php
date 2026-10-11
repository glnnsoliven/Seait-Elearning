<?php
/**
 * SEAIT E-Learning — Notification Service
 *
 * Wraps Notification class with higher-level convenience methods
 * and scheduled-task helpers.
 */
class NotificationService
{
    /**
     * Send a quiz result notification to a student.
     */
    public static function quizSubmitted(int $userId, string $quizTitle, int $quizId): void
    {
        Notification::send(
            $userId,
            'quiz_result',
            'Quiz Submitted',
            "You have submitted \"{$quizTitle}\". Results will be available after the deadline.",
            BASE_URL . '/modules/student/quiz_result.php?quiz_id=' . $quizId
        );
    }

    /**
     * Notify enrolled students about a new lesson.
     */
    public static function newLesson(int $courseId, string $lessonTitle, int $lessonId): void
    {
        Notification::broadcastToCourse(
            $courseId,
            'new_lesson',
            'New Lesson: ' . $lessonTitle,
            'A new lesson has been published.',
            BASE_URL . '/modules/student/lessons.php?id=' . $lessonId
        );
    }

    /**
     * Send 24-hour deadline reminders (call via cron or admin trigger).
     */
    public static function sendDeadlineReminders(): int
    {
        Notification::sendDeadlineReminders();
        return 1;
    }

    /**
     * Send weekly digest to all students (call via cron).
     */
    public static function sendWeeklyDigests(): void
    {
        Notification::sendWeeklyDigests();
    }

    /**
     * Get unread count for a user.
     */
    public static function unreadCount(int $userId): int
    {
        return unreadNotifCount($userId);
    }
}
