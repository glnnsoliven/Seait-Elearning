-- ============================================================
-- SEAIT AI-Driven E-Learning and Adaptive Quiz Platform
-- Complete MySQL Database Schema
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS seait_elearning
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE seait_elearning;

-- ============================================================
-- TABLE: users
-- Central authentication table for all roles
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    first_name  VARCHAR(80)  NOT NULL,
    last_name   VARCHAR(80)  NOT NULL,
    avatar      VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    remember_token VARCHAR(100) DEFAULT NULL,
    last_login  DATETIME     DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role       (role),
    INDEX idx_is_active  (is_active),
    INDEX idx_email      (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: departments
-- ============================================================
CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    description TEXT         DEFAULT NULL,
    head_id     INT UNSIGNED DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code      (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: teachers
-- Extended profile for teacher role
-- ============================================================
CREATE TABLE teachers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    department_id   INT UNSIGNED DEFAULT NULL,
    employee_id     VARCHAR(30)  DEFAULT NULL UNIQUE,
    specialization  VARCHAR(150) DEFAULT NULL,
    bio             TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: students
-- Extended profile for student role
-- ============================================================
CREATE TABLE students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    department_id   INT UNSIGNED DEFAULT NULL,
    student_id      VARCHAR(30)  DEFAULT NULL UNIQUE,
    year_level      TINYINT UNSIGNED DEFAULT 1,
    section         VARCHAR(20)  DEFAULT NULL,
    birth_date      DATE         DEFAULT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_department (department_id),
    INDEX idx_year_level (year_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: courses
-- ============================================================
CREATE TABLE courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(200) NOT NULL,
    code            VARCHAR(30)  NOT NULL UNIQUE,
    description     TEXT         DEFAULT NULL,
    thumbnail       VARCHAR(255) DEFAULT NULL,
    credits         TINYINT UNSIGNED DEFAULT 3,
    is_published    TINYINT(1)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id)    REFERENCES teachers(id)    ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_teacher    (teacher_id),
    INDEX idx_published  (is_published),
    INDEX idx_active     (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: enrollments
-- ============================================================
CREATE TABLE enrollments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED NOT NULL,
    course_id   INT UNSIGNED NOT NULL,
    enrolled_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status      ENUM('active','dropped','completed') NOT NULL DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    UNIQUE KEY uk_enrollment (student_id, course_id),
    INDEX idx_course   (course_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: lessons
-- ============================================================
CREATE TABLE lessons (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1)  NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course    (course_id),
    INDEX idx_sort      (sort_order),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: topics
-- Sub-units within a lesson
-- ============================================================
CREATE TABLE topics (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id   INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_lesson (lesson_id),
    INDEX idx_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: learning_materials
-- Files and content attached to lessons/topics
-- ============================================================
CREATE TABLE learning_materials (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id   INT UNSIGNED NOT NULL,
    topic_id    INT UNSIGNED DEFAULT NULL,
    title       VARCHAR(200) NOT NULL,
    type        ENUM('pdf','pptx','video','text','link','other') NOT NULL DEFAULT 'text',
    content     LONGTEXT     DEFAULT NULL,
    file_path   VARCHAR(255) DEFAULT NULL,
    file_size   INT UNSIGNED DEFAULT NULL COMMENT 'bytes',
    external_url VARCHAR(500) DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_downloadable TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id)  REFERENCES topics(id)  ON DELETE SET NULL,
    INDEX idx_lesson (lesson_id),
    INDEX idx_topic  (topic_id),
    INDEX idx_type   (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: quizzes
-- ============================================================
CREATE TABLE quizzes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    lesson_id       INT UNSIGNED DEFAULT NULL,
    teacher_id      INT UNSIGNED NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT         DEFAULT NULL,
    instructions    TEXT         DEFAULT NULL,
    time_limit      SMALLINT UNSIGNED DEFAULT NULL COMMENT 'minutes; NULL = no limit',
    passing_score   TINYINT UNSIGNED NOT NULL DEFAULT 60,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
    shuffle_choices   TINYINT(1) NOT NULL DEFAULT 0,
    show_results    TINYINT(1)   NOT NULL DEFAULT 1,
    is_adaptive     TINYINT(1)   NOT NULL DEFAULT 0,
    is_published    TINYINT(1)   NOT NULL DEFAULT 0,
    scheduled_at    DATETIME     DEFAULT NULL,
    deadline        DATETIME     DEFAULT NULL,
    join_code       VARCHAR(10)  DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)  REFERENCES lessons(id)  ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_course     (course_id),
    INDEX idx_lesson     (lesson_id),
    INDEX idx_published  (is_published),
    INDEX idx_deadline   (deadline),
    UNIQUE KEY uk_join_code (join_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: quiz_questions
-- ============================================================
CREATE TABLE quiz_questions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED NOT NULL,
    topic_id        INT UNSIGNED DEFAULT NULL,
    question_text   TEXT         NOT NULL,
    question_type   ENUM('multiple_choice','true_false','identification','short_answer') NOT NULL DEFAULT 'multiple_choice',
    choices         JSON         DEFAULT NULL COMMENT 'Array of choice objects {label, text}',
    correct_answer  TEXT         NOT NULL,
    explanation     TEXT         DEFAULT NULL,
    difficulty      ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    bloom_level     ENUM('remember','understand','apply','analyze','evaluate','create') NOT NULL DEFAULT 'understand',
    points          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id)  REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id)  ON DELETE SET NULL,
    INDEX idx_quiz       (quiz_id),
    INDEX idx_difficulty (difficulty),
    INDEX idx_bloom      (bloom_level),
    INDEX idx_type       (question_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: quiz_attempts
-- Each time a student starts a quiz
-- ============================================================
CREATE TABLE quiz_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    attempt_number  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status          ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    score           DECIMAL(5,2) DEFAULT NULL,
    percentage      DECIMAL(5,2) DEFAULT NULL,
    total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    correct_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    incorrect_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    time_spent      SMALLINT UNSIGNED DEFAULT NULL COMMENT 'seconds',
    started_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at    DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id)    REFERENCES quizzes(id)  ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_quiz    (quiz_id),
    INDEX idx_student (student_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: student_answers
-- Individual answers per attempt
-- ============================================================
CREATE TABLE student_answers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id      INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    student_answer  TEXT         DEFAULT NULL,
    is_correct      TINYINT(1)   DEFAULT NULL,
    points_earned   DECIMAL(4,2) NOT NULL DEFAULT 0,
    response_time   SMALLINT UNSIGNED DEFAULT NULL COMMENT 'seconds',
    difficulty_at_answer ENUM('easy','medium','hard') DEFAULT NULL,
    answered_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id)  REFERENCES quiz_attempts(id)  ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    INDEX idx_attempt  (attempt_id),
    INDEX idx_question (question_id),
    INDEX idx_correct  (is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: student_progress
-- Tracks lesson/topic completion
-- ============================================================
CREATE TABLE student_progress (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    course_id       INT UNSIGNED NOT NULL,
    lesson_id       INT UNSIGNED DEFAULT NULL,
    topic_id        INT UNSIGNED DEFAULT NULL,
    material_id     INT UNSIGNED DEFAULT NULL,
    status          ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
    completion_pct  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_accessed   DATETIME     DEFAULT NULL,
    completed_at    DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)  REFERENCES lessons(id)  ON DELETE CASCADE,
    FOREIGN KEY (topic_id)   REFERENCES topics(id)   ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES learning_materials(id) ON DELETE SET NULL,
    UNIQUE KEY uk_progress (student_id, course_id, lesson_id, topic_id),
    INDEX idx_student (student_id),
    INDEX idx_course  (course_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: competencies
-- Skills / competency framework per course
-- ============================================================
CREATE TABLE competencies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    topic_id        INT UNSIGNED DEFAULT NULL,
    name            VARCHAR(200) NOT NULL,
    description     TEXT         DEFAULT NULL,
    prerequisite_id INT UNSIGNED DEFAULT NULL COMMENT 'Self-referencing prerequisite',
    required_mastery TINYINT UNSIGNED NOT NULL DEFAULT 70 COMMENT 'Percentage',
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)       REFERENCES courses(id)      ON DELETE CASCADE,
    FOREIGN KEY (topic_id)        REFERENCES topics(id)       ON DELETE SET NULL,
    FOREIGN KEY (prerequisite_id) REFERENCES competencies(id) ON DELETE SET NULL,
    INDEX idx_course (course_id),
    INDEX idx_prereq (prerequisite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: student_competencies
-- Mastery tracking per student per competency
-- ============================================================
CREATE TABLE student_competencies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    competency_id   INT UNSIGNED NOT NULL,
    mastery_pct     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('locked','learning','completed','mastered') NOT NULL DEFAULT 'locked',
    unlocked_at     DATETIME     DEFAULT NULL,
    mastered_at     DATETIME     DEFAULT NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)    REFERENCES students(id)     ON DELETE CASCADE,
    FOREIGN KEY (competency_id) REFERENCES competencies(id) ON DELETE CASCADE,
    UNIQUE KEY uk_student_comp (student_id, competency_id),
    INDEX idx_student    (student_id),
    INDEX idx_competency (competency_id),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: diagnostic_feedback
-- AI-generated feedback per incorrect answer
-- ============================================================
CREATE TABLE diagnostic_feedback (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_answer_id INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    student_answer  TEXT         DEFAULT NULL,
    correct_answer  TEXT         NOT NULL,
    explanation     TEXT         DEFAULT NULL,
    misconception   TEXT         DEFAULT NULL COMMENT 'Identified misconception',
    related_topic   VARCHAR(200) DEFAULT NULL,
    recommended_lesson_id INT UNSIGNED DEFAULT NULL,
    suggested_activity TEXT      DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_answer_id)      REFERENCES student_answers(id)  ON DELETE CASCADE,
    FOREIGN KEY (student_id)             REFERENCES students(id)          ON DELETE CASCADE,
    FOREIGN KEY (question_id)            REFERENCES quiz_questions(id)    ON DELETE CASCADE,
    FOREIGN KEY (recommended_lesson_id)  REFERENCES lessons(id)           ON DELETE SET NULL,
    INDEX idx_student  (student_id),
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: recommendations
-- Personalized learning recommendations
-- ============================================================
CREATE TABLE recommendations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    type            ENUM('lesson','topic','quiz','activity') NOT NULL DEFAULT 'lesson',
    reference_id    INT UNSIGNED NOT NULL COMMENT 'ID of lesson/topic/quiz',
    title           VARCHAR(200) NOT NULL,
    reason          TEXT         DEFAULT NULL,
    priority        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_dismissed    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student   (student_id),
    INDEX idx_type      (type),
    INDEX idx_dismissed (is_dismissed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: study_plans
-- AI-generated daily/weekly study plan entries
-- ============================================================
CREATE TABLE study_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    lesson_id       INT UNSIGNED DEFAULT NULL,
    topic_id        INT UNSIGNED DEFAULT NULL,
    quiz_id         INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT         DEFAULT NULL,
    plan_date       DATE         NOT NULL,
    estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    priority        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    is_completed    TINYINT(1)   NOT NULL DEFAULT 0,
    completed_at    DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id)  REFERENCES lessons(id)  ON DELETE SET NULL,
    FOREIGN KEY (topic_id)   REFERENCES topics(id)   ON DELETE SET NULL,
    FOREIGN KEY (quiz_id)    REFERENCES quizzes(id)  ON DELETE SET NULL,
    INDEX idx_student  (student_id),
    INDEX idx_date     (plan_date),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: at_risk_students
-- Predictive analytics flags
-- ============================================================
CREATE TABLE at_risk_students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    course_id       INT UNSIGNED NOT NULL,
    risk_level      ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    risk_score      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
    indicators      JSON         DEFAULT NULL COMMENT 'Array of risk factor strings',
    weak_topics     JSON         DEFAULT NULL COMMENT 'Array of topic names',
    suggested_intervention TEXT  DEFAULT NULL,
    is_resolved     TINYINT(1)   NOT NULL DEFAULT 0,
    flagged_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     DATETIME     DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    UNIQUE KEY uk_risk (student_id, course_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_resolved   (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('quiz_deadline','assignment','new_lesson','new_quiz','quiz_result','achievement','study_reminder','announcement','system') NOT NULL DEFAULT 'system',
    title       VARCHAR(200) NOT NULL,
    message     TEXT         NOT NULL,
    link        VARCHAR(500) DEFAULT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     DATETIME     DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type    (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: announcements
-- Teacher/Admin broadcasts
-- ============================================================
CREATE TABLE announcements (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id   INT UNSIGNED NOT NULL COMMENT 'users.id',
    course_id   INT UNSIGNED DEFAULT NULL COMMENT 'NULL = institution-wide',
    title       VARCHAR(200) NOT NULL,
    content     TEXT         NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id)  ON DELETE CASCADE,
    INDEX idx_author    (author_id),
    INDEX idx_course    (course_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: audit_logs
-- System activity trail
-- ============================================================
CREATE TABLE audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    module      VARCHAR(80)  NOT NULL,
    description TEXT         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    user_agent  VARCHAR(300) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: password_resets
-- Token-based password reset
-- ============================================================
CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(150) NOT NULL,
    token       VARCHAR(100) NOT NULL UNIQUE,
    expires_at  DATETIME     NOT NULL,
    used        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email   (email),
    INDEX idx_token   (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: system_settings
-- Key-value config store
-- ============================================================
CREATE TABLE system_settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT        DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FOREIGN KEY: departments.head_id -> teachers.id
-- Added after teachers table exists
-- ============================================================
ALTER TABLE departments
    ADD CONSTRAINT fk_dept_head
    FOREIGN KEY (head_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- System settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name',        'SEAIT E-Learning Platform', 'Application display name'),
('site_email',       'admin@seait.edu.ph',        'System sender email'),
('site_logo',        '',                           'Logo file path'),
('allow_register',   '1',                          '1 = open registration'),
('default_role',     'student',                    'Role assigned on register'),
('ai_quiz_enabled',  '1',                          'Enable AI quiz generation'),
('smtp_host',        'smtp.mailtrap.io',           'SMTP server'),
('smtp_port',        '2525',                       'SMTP port'),
('smtp_user',        '',                           'SMTP username'),
('smtp_pass',        '',                           'SMTP password'),
('maintenance_mode', '0',                          '1 = site under maintenance'),
('max_upload_mb',    '50',                         'Max file upload in MB');

-- Admin user  (password: Admin@1234)
INSERT INTO users (username, email, password, role, first_name, last_name, is_active) VALUES
('admin', 'admin@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'System', 'Administrator', 1);

-- Department
INSERT INTO departments (name, code, description) VALUES
('College of Computer Studies',    'CCS',  'Handles all computing and IT programs'),
('College of Engineering',         'COE',  'Handles engineering disciplines'),
('College of Business Education',  'CBE',  'Business and accountancy programs'),
('College of Education',           'COEd', 'Teacher education programs');

-- Teacher users  (password: Teacher@1234)
INSERT INTO users (username, email, password, role, first_name, last_name, is_active) VALUES
('jdelacruz',  'jdelacruz@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'teacher', 'Jose', 'Dela Cruz', 1),
('msantos',    'msantos@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'teacher', 'Maria', 'Santos', 1),
('rlimjoco',   'rlimjoco@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'teacher', 'Roberto', 'Limjoco', 1);

-- Teacher profiles
INSERT INTO teachers (user_id, department_id, employee_id, specialization) VALUES
(2, 1, 'EMP-001', 'Artificial Intelligence & Machine Learning'),
(3, 1, 'EMP-002', 'Web Development & Database Systems'),
(4, 2, 'EMP-003', 'Electronics & Embedded Systems');

-- Student users  (password: Student@1234)
INSERT INTO users (username, email, password, role, first_name, last_name, is_active) VALUES
('jmendoza',  'jmendoza@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Juan', 'Mendoza', 1),
('agarcia',   'agarcia@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Ana', 'Garcia', 1),
('preyes',    'preyes@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Pedro', 'Reyes', 1),
('lbautista', 'lbautista@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Lisa', 'Bautista', 1),
('mvillareal','mvillareal@seait.edu.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Marco', 'Villareal', 1);

-- Student profiles
INSERT INTO students (user_id, department_id, student_id, year_level, section) VALUES
(5, 1, 'STU-2024-001', 2, 'BSCS-2A'),
(6, 1, 'STU-2024-002', 2, 'BSCS-2A'),
(7, 1, 'STU-2024-003', 3, 'BSIT-3B'),
(8, 1, 'STU-2024-004', 1, 'BSCS-1C'),
(9, 2, 'STU-2024-005', 2, 'BSECE-2A');

-- Courses
INSERT INTO courses (teacher_id, department_id, title, code, description, is_published, is_active) VALUES
(1, 1, 'Introduction to Artificial Intelligence', 'CS101-AI',
 'Fundamental concepts of AI including search algorithms, machine learning basics, and expert systems.', 1, 1),
(1, 1, 'Machine Learning Fundamentals',           'CS201-ML',
 'Supervised, unsupervised, and reinforcement learning with practical Python implementations.', 1, 1),
(2, 1, 'Web Development with PHP & MySQL',        'IT201-WD',
 'Full-stack web development covering HTML, CSS, PHP 8, and MySQL database design.', 1, 1),
(2, 1, 'Database Management Systems',             'IT301-DB',
 'Relational database design, SQL queries, normalization, and transactions.', 1, 1),
(3, 2, 'Electronics Engineering Review',          'ECE101-EE',
 'Review of fundamental electronics concepts for engineering students.', 1, 1);

-- Lessons for Course 1
INSERT INTO lessons (course_id, title, description, sort_order, is_published) VALUES
(1, 'What is Artificial Intelligence?',    'Introduction and history of AI.',                          1, 1),
(1, 'Search Algorithms',                   'Uninformed and informed search strategies.',               2, 1),
(1, 'Knowledge Representation',            'Propositional and predicate logic for AI.',                3, 1),
(1, 'Introduction to Machine Learning',    'Overview of ML paradigms within AI.',                     4, 1),
(1, 'Neural Networks Basics',              'Perceptrons, layers, and activation functions.',           5, 1),
-- Lessons for Course 3
(3, 'HTML5 & Modern Web Standards',        'Semantic HTML, forms, and accessibility.',                 1, 1),
(3, 'CSS with Tailwind CSS',               'Utility-first CSS design and responsive layout.',          2, 1),
(3, 'PHP 8 Fundamentals',                  'Variables, control flow, functions, and OOP in PHP.',     3, 1),
(3, 'MySQL & PDO',                         'Database design, queries, and prepared statements.',       4, 1),
(3, 'Building a Complete Web Application', 'Capstone: apply all skills in an integrated project.',    5, 1);

-- Topics for Lesson 1 of Course 1
INSERT INTO topics (lesson_id, title, sort_order) VALUES
(1, 'Definition and Scope of AI',      1),
(1, 'History of AI: 1950s to Present', 2),
(1, 'Types of AI Systems',             3),
(1, 'Real-World AI Applications',      4);

-- Topics for Lesson 8 (PHP 8)
INSERT INTO topics (lesson_id, title, sort_order) VALUES
(8, 'PHP Syntax and Variables',      1),
(8, 'Control Structures',            2),
(8, 'Functions and Arrays',          3),
(8, 'Object-Oriented PHP',           4),
(8, 'Error Handling and Exceptions', 5);

-- Enrollments
INSERT INTO enrollments (student_id, course_id, status) VALUES
(1, 1, 'active'), (1, 3, 'active'),
(2, 1, 'active'), (2, 3, 'active'),
(3, 3, 'active'), (3, 4, 'active'),
(4, 1, 'active'),
(5, 5, 'active');

-- Competencies for AI course
INSERT INTO competencies (course_id, name, description, required_mastery, sort_order) VALUES
(1, 'AI Foundations',           'Understand what AI is and its history.',                          70, 1),
(1, 'Search & Problem Solving', 'Apply search algorithms to solve AI problems.',                   75, 2),
(1, 'Knowledge Representation', 'Represent knowledge using logic and ontologies.',                 75, 3),
(1, 'Machine Learning Basics',  'Distinguish ML paradigms and apply basic algorithms.',            80, 4),
(1, 'Neural Networks',          'Design and train simple neural networks.',                        80, 5);

-- Set prerequisites (search requires foundations, etc.)
UPDATE competencies SET prerequisite_id = 1 WHERE id = 2;
UPDATE competencies SET prerequisite_id = 2 WHERE id = 3;
UPDATE competencies SET prerequisite_id = 3 WHERE id = 4;
UPDATE competencies SET prerequisite_id = 4 WHERE id = 5;

-- Sample quizzes
INSERT INTO quizzes (course_id, lesson_id, teacher_id, title, description, time_limit, passing_score, max_attempts, is_adaptive, is_published) VALUES
(1, 1, 1, 'AI Fundamentals Quiz',         'Test your knowledge of AI basics.',          30, 60, 3, 1, 1),
(1, 2, 1, 'Search Algorithms Quiz',        'Evaluate understanding of search methods.',  20, 65, 2, 1, 1),
(3, 8, 2, 'PHP 8 Basics Assessment',       'Test PHP syntax and fundamentals.',           25, 70, 3, 0, 1),
(3, 9, 2, 'MySQL Query Skills',            'SQL queries and database design test.',       20, 70, 3, 0, 1);

-- Sample quiz questions (AI Fundamentals Quiz)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, choices, correct_answer, explanation, difficulty, bloom_level, points) VALUES
(1, 'Who is credited with coining the term "Artificial Intelligence"?',
 'multiple_choice',
 '[{"label":"A","text":"Alan Turing"},{"label":"B","text":"John McCarthy"},{"label":"C","text":"Marvin Minsky"},{"label":"D","text":"Claude Shannon"}]',
 'B', 'John McCarthy coined the term "Artificial Intelligence" at the 1956 Dartmouth Conference.',
 'easy', 'remember', 1),

(1, 'The Turing Test evaluates whether a machine can exhibit intelligent behavior equivalent to that of a human.',
 'true_false',
 '[{"label":"A","text":"True"},{"label":"B","text":"False"}]',
 'True', 'Alan Turing proposed the Turing Test in 1950 as a measure of machine intelligence.',
 'easy', 'understand', 1),

(1, 'Which type of AI system can perform any intellectual task that a human can?',
 'multiple_choice',
 '[{"label":"A","text":"Narrow AI"},{"label":"B","text":"General AI"},{"label":"C","text":"Reactive AI"},{"label":"D","text":"Super AI"}]',
 'B', 'Artificial General Intelligence (AGI) refers to a machine that can perform any intellectual task a human can.',
 'medium', 'understand', 1),

(1, 'What does "machine learning" primarily focus on?',
 'multiple_choice',
 '[{"label":"A","text":"Explicit rule programming"},{"label":"B","text":"Learning from data without being explicitly programmed"},{"label":"C","text":"Mechanical automation"},{"label":"D","text":"Database management"}]',
 'B', 'Machine learning enables computers to learn from data and improve their performance over time.',
 'medium', 'understand', 1),

(1, 'Name the AI that defeated world chess champion Garry Kasparov in 1997.',
 'identification',
 NULL,
 'Deep Blue', 'IBM''s Deep Blue defeated Garry Kasparov in 1997, marking a milestone in AI.',
 'easy', 'remember', 1),

(1, 'AI that operates on pre-defined rules and cannot learn from experience is called __________.',
 'identification',
 NULL,
 'Reactive AI', 'Reactive AI systems respond to inputs with outputs based on fixed rules without memory or learning.',
 'medium', 'remember', 1),

(1, 'A self-driving car is an example of which branch of AI?',
 'multiple_choice',
 '[{"label":"A","text":"Natural Language Processing"},{"label":"B","text":"Computer Vision"},{"label":"C","text":"Robotics"},{"label":"D","text":"Expert Systems"}]',
 'C', 'Self-driving cars use robotics combined with computer vision and sensor fusion.',
 'medium', 'apply', 1),

(1, 'Explain the difference between Narrow AI and General AI in your own words.',
 'short_answer',
 NULL,
 'Narrow AI is designed for a specific task while General AI can perform any intellectual task like a human.',
 'Narrow AI (Weak AI) excels at a single task. General AI (Strong AI) can handle any cognitive task humans can.',
 'hard', 'analyze', 2);

-- Sample student progress
INSERT INTO student_progress (student_id, course_id, lesson_id, status, completion_pct, last_accessed) VALUES
(1, 1, 1, 'completed', 100, NOW()),
(1, 1, 2, 'in_progress', 60, NOW()),
(2, 1, 1, 'completed', 100, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 3, 6, 'completed', 100, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 7, 'in_progress', 40, NOW());

-- Student competencies
INSERT INTO student_competencies (student_id, competency_id, mastery_pct, status) VALUES
(1, 1, 85, 'mastered'),
(1, 2, 70, 'completed'),
(1, 3, 45, 'learning'),
(1, 4, 10, 'locked'),
(1, 5,  0, 'locked'),
(2, 1, 72, 'completed'),
(2, 2, 55, 'learning'),
(2, 3,  0, 'locked'),
(3, 1,  0, 'locked');

-- Sample notifications
INSERT INTO notifications (user_id, type, title, message, is_read) VALUES
(5, 'new_quiz',      'New Quiz Available', 'AI Fundamentals Quiz has been published. Good luck!', 0),
(5, 'study_reminder','Study Reminder',     'You have a study session scheduled for Search Algorithms today.', 0),
(6, 'new_lesson',    'New Lesson Added',   'Neural Networks Basics lesson is now available in CS101-AI.', 0),
(7, 'quiz_result',   'Quiz Graded',        'Your PHP 8 Basics Assessment has been graded. Score: 88%', 1),
(2, 'announcement',  'Faculty Meeting',    'Reminder: Faculty meeting on Friday at 3PM in AVR.', 0),
(3, 'announcement',  'Faculty Meeting',    'Reminder: Faculty meeting on Friday at 3PM in AVR.', 0);

-- Sample audit logs
INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES
(1, 'login',          'auth',     'Admin logged in successfully',        '127.0.0.1'),
(2, 'create_course',  'courses',  'Teacher created course CS101-AI',      '127.0.0.1'),
(2, 'publish_quiz',   'quizzes',  'Teacher published AI Fundamentals Quiz','127.0.0.1'),
(5, 'start_quiz',     'quiz',     'Student started AI Fundamentals Quiz', '127.0.0.1');

-- At-risk flags (sample)
INSERT INTO at_risk_students (student_id, course_id, risk_level, risk_score, indicators, weak_topics, suggested_intervention) VALUES
(4, 1, 'high', 78,
 '["Low quiz scores","Inactive for 7 days","Only 20% lessons completed"]',
 '["Search Algorithms","Knowledge Representation"]',
 'Schedule 1-on-1 tutoring session and assign supplemental reading materials.'),
(5, 5, 'medium', 52,
 '["Below-average quiz performance","Missed 1 quiz deadline"]',
 '["Circuit Analysis"]',
 'Recommend practice quizzes and connect with study group.');

-- Sample announcements
INSERT INTO announcements (author_id, course_id, title, content) VALUES
(2, 1, 'Midterm Examination Schedule', 'The midterm exam for CS101-AI will be held on the 15th of next month. Please review Lessons 1-3.'),
(3, 3, 'Assignment 2 Deadline Extended', 'The deadline for Assignment 2 (PHP CRUD) has been extended by 3 days due to the upcoming holiday.');
