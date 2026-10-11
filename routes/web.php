<?php
/**
 * SEAIT E-Learning — Web Route Map
 *
 * Documents all application routes organized by role.
 * This file serves as the authoritative route reference.
 * Routes are resolved by Apache via .htaccess — this file
 * is documentation + can be used by a future Router class.
 *
 * Format: 'METHOD /path' => 'module/role/file.php'
 */

return [

    // ── Public / Auth ─────────────────────────────────────────
    'GET  /'                            => 'index.php',
    'GET  /auth/login'                  => 'modules/auth/login.php',
    'POST /auth/login'                  => 'modules/auth/login.php',
    'GET  /auth/register'               => 'modules/auth/register.php',
    'POST /auth/register'               => 'modules/auth/register.php',
    'GET  /auth/logout'                 => 'modules/auth/logout.php',
    'GET  /auth/forgot-password'        => 'modules/auth/forgot_password.php',
    'POST /auth/forgot-password'        => 'modules/auth/forgot_password.php',
    'GET  /auth/reset-password'         => 'modules/auth/reset_password.php',
    'POST /auth/reset-password'         => 'modules/auth/reset_password.php',

    // ── Admin ─────────────────────────────────────────────────
    'GET  /admin/dashboard'             => 'modules/admin/dashboard.php',
    'GET  /admin/analytics'             => 'modules/admin/analytics.php',
    'GET  /admin/users'                 => 'modules/admin/users.php',
    'GET  /admin/teachers'              => 'modules/admin/teachers.php',
    'GET  /admin/students'              => 'modules/admin/students.php',
    'GET  /admin/courses'               => 'modules/admin/courses.php',
    'GET  /admin/departments'           => 'modules/admin/departments.php',
    'GET  /admin/reports'               => 'modules/admin/reports.php',
    'GET  /admin/settings'              => 'modules/admin/settings.php',
    'GET  /admin/notifications'         => 'modules/admin/notifications.php',

    // ── Teacher ───────────────────────────────────────────────
    'GET  /teacher/dashboard'           => 'modules/teacher/dashboard.php',
    'GET  /teacher/courses'             => 'modules/teacher/courses.php',
    'GET  /teacher/courses/create'      => 'modules/teacher/create_course.php',
    'POST /teacher/courses/create'      => 'modules/teacher/create_course.php',
    'GET  /teacher/lessons'             => 'modules/teacher/lessons.php',
    'GET  /teacher/materials'           => 'modules/teacher/materials.php',
    'GET  /teacher/quizzes'             => 'modules/teacher/quizzes.php',
    'GET  /teacher/quizzes/create'      => 'modules/teacher/create_quiz.php',
    'POST /teacher/quizzes/create'      => 'modules/teacher/create_quiz.php',
    'GET  /teacher/question-bank'       => 'modules/teacher/question_bank.php',
    'GET  /teacher/ai-generator'        => 'modules/teacher/ai_quiz_generator.php',
    'POST /teacher/ai-generator'        => 'modules/teacher/ai_quiz_generator.php',
    'GET  /teacher/sections'            => 'modules/teacher/sections.php',
    'GET  /teacher/students'            => 'modules/teacher/students.php',
    'GET  /teacher/student-progress'    => 'modules/teacher/student_progress.php',
    'GET  /teacher/analytics'           => 'modules/teacher/analytics.php',
    'GET  /teacher/misconceptions'      => 'modules/teacher/misconceptions.php',
    'GET  /teacher/announcements'       => 'modules/teacher/announcements.php',
    'GET  /teacher/reports'             => 'modules/teacher/reports.php',
    'GET  /teacher/profile'             => 'modules/teacher/profile.php',
    'GET  /teacher/notifications'       => 'modules/teacher/notifications.php',

    // ── Student ───────────────────────────────────────────────
    'GET  /student/dashboard'           => 'modules/student/dashboard.php',
    'GET  /student/courses'             => 'modules/student/courses.php',
    'GET  /student/course/:id'          => 'modules/student/course_view.php',
    'GET  /student/lessons/:id'         => 'modules/student/lessons.php',
    'GET  /student/material/:id'        => 'modules/student/learning_material.php',
    'GET  /student/quizzes'             => 'modules/student/quiz_list.php',
    'GET  /student/take-quiz/:id'       => 'modules/student/take_quiz.php',
    'GET  /student/quiz-result/:id'     => 'modules/student/quiz_result.php',
    'GET  /student/review/:id'          => 'modules/student/review_answers.php',
    'GET  /student/skill-tree'          => 'modules/student/skill_tree.php',
    'GET  /student/study-plan'          => 'modules/student/study_plan.php',
    'GET  /student/calendar'            => 'modules/student/calendar.php',
    'GET  /student/weak-topics'         => 'modules/student/weak_topics.php',
    'GET  /student/recommendations'     => 'modules/student/recommendations.php',
    'GET  /student/profile'             => 'modules/student/profile.php',
    'GET  /student/notifications'       => 'modules/student/notifications.php',

    // ── API Endpoints ─────────────────────────────────────────
    'POST /api/submit-answer'           => 'modules/api/submit_answer.php',
    'GET  /api/lessons'                 => 'modules/api/get_lessons.php',
    'POST /api/mark-lesson-complete'    => 'modules/api/mark_lesson_complete.php',
    'GET  /api/notifications/count'     => 'modules/api/notifications_count.php',
    'POST /api/join-quiz'               => 'modules/api/join_quiz.php',
    'POST /api/join-section'            => 'modules/api/join_section.php',
];
