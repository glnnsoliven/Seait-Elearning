# SEAIT AI-Driven E-Learning and Adaptive Quiz Platform

**South East Asian Institute of Technology (SEAIT)**
**Version:** 1.0.0 | **Environment:** PHP + MySQL on XAMPP

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Features](#2-system-features)
3. [User Roles](#3-user-roles)
4. [Installation & Setup](#4-installation--setup)
5. [Default Credentials](#5-default-credentials)
6. [Folder Structure](#6-folder-structure)
7. [Programming Languages, Frameworks & Database](#7-programming-languages-frameworks--database)
8. [AI Integration](#8-ai-integration)
9. [Database Schema](#9-database-schema)
10. [Security](#10-security)

---

## 1. Project Overview

An AI-powered Learning Management System (LMS) built specifically for SEAIT. It provides adaptive assessments, personalized learning recommendations, AI-generated quizzes from lesson materials, competency skill trees, at-risk student detection, and spaced repetition study planning.

---

## 2. System Features

| Feature | Description |
|---------|-------------|
| AI Personalized Learning | Recommends lessons/quizzes based on quiz performance and weak topics |
| Adaptive Quiz System | Adjusts difficulty in real-time (Easy → Medium → Hard) based on answer streaks |
| AI Quiz Generation | Generates quizzes from uploaded PDF/PPTX/TXT via Groq LLaMA 3 |
| Misconception Mapper | Identifies specific conceptual errors per question/topic |
| Visual Skill Tree | SVG competency tree with prerequisite locking and mastery tracking |
| At-Risk Detection | Multi-factor early warning (score, inactivity, missed quizzes, lesson progress) |
| Spaced Repetition | SM-2 algorithm generates 14-day personalized study calendars |
| Notifications | In-app alerts + email digests for deadlines and quiz results |

---

## 3. User Roles

| Role | Access |
|------|--------|
| **Admin** | Institution-wide analytics, user/department management, system settings |
| **Teacher** | Course/lesson/quiz creation, AI generator, student monitoring, misconception analytics |
| **Student** | Take quizzes, view skill tree, study plan, recommendations, weak topics |

---

## 4. Installation & Setup

### Requirements
- XAMPP (PHP 8.1+, MySQL 8.0+, Apache)
- PHP extensions: `pdo_mysql`, `zip`, `curl`, `openssl`

### Steps

```bash
# 1. Place project in XAMPP htdocs
C:\xampp\htdocs\seait-elearning\

# 2. Start Apache and MySQL in XAMPP Control Panel

# 3. Create the database
# Open phpMyAdmin → New → seait_elearning → Create

# 4. Import the schema
# phpMyAdmin → seait_elearning → Import → database/seait_elearning.sql

# 5. Run migrations
# phpMyAdmin → SQL → paste each file in database/migrations/ in order

# 6. Configure AI key in config/config.php
define('OPENAI_API_KEY', 'your-groq-api-key-here');

# 7. Open in browser
http://localhost/seait-elearning
```

---

## 5. Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@seait.edu.ph | Admin@1234 |
| Teacher | jdelacruz@seait.edu.ph | Teacher@1234 |
| Student | jmendoza@seait.edu.ph | Student@1234 |

> **Change all default passwords immediately in production.**

---

## 6. Folder Structure

```
seait-elearning/
│
├── app/                          # MVC Framework Layer
│   ├── Controllers/              # Role-based controller classes
│   │   ├── BaseController.php    # Abstract base with shared methods
│   │   ├── AdminController.php   # Admin-specific logic
│   │   ├── TeacherController.php # Teacher-specific logic
│   │   └── StudentController.php # Student-specific logic
│   │
│   ├── Models/                   # Data model classes (Active Record)
│   │   ├── BaseModel.php         # CRUD base class
│   │   ├── User.php              # User model
│   │   ├── Course.php            # Course model
│   │   └── Quiz.php              # Quiz model
│   │
│   ├── Middleware/               # Request lifecycle middleware
│   │   ├── AuthMiddleware.php    # Session auth + role enforcement
│   │   ├── CsrfMiddleware.php    # CSRF token validation
│   │   └── RateLimitMiddleware.php # Brute-force protection
│   │
│   └── Services/                 # Business logic service layer
│       ├── AIService.php         # AI quiz generation pipeline
│       ├── QuizService.php       # Quiz lifecycle operations
│       └── NotificationService.php # Notification dispatch
│
├── config/
│   └── config.php                # All app constants (DB, AI, paths, session)
│
├── core/                         # Engine classes (framework-agnostic)
│   ├── bootstrap.php             # Loads config, core, app layer, starts session
│   ├── Database.php              # PDO singleton with query helpers
│   ├── Auth.php                  # Login, session, CSRF, password reset
│   ├── Helpers.php               # Global utility functions
│   ├── AIGenerator.php           # Groq/OpenAI API + fallback generator
│   ├── AdaptiveEngine.php        # Adaptive quiz difficulty engine
│   ├── AtRisk.php                # At-risk student detection
│   ├── Recommendation.php        # Personalized recommendation engine
│   ├── StudyPlan.php             # SM-2 spaced repetition planner
│   ├── Notification.php          # In-app + email notification service
│   └── AuditLog.php              # System activity audit trail
│
├── database/
│   ├── seait_elearning.sql       # Full database schema + seed data
│   └── migrations/               # Versioned incremental migrations
│       ├── 001_initial_schema.sql
│       ├── 002_quiz_sections.sql
│       └── 003_created_by_ai.sql
│
├── includes/
│   ├── 403.php                   # Forbidden error page
│   ├── 404.php                   # Not found error page
│   └── components/               # Shared UI components (PHP partials)
│       ├── head.php              # HTML <head>, CSS/JS imports
│       ├── topbar.php            # Top navigation bar
│       ├── sidebar_admin.php     # Admin sidebar navigation
│       ├── sidebar_teacher.php   # Teacher sidebar navigation
│       ├── sidebar_student.php   # Student sidebar navigation
│       ├── sidebar_js.php        # Sidebar JavaScript
│       └── flash.php             # Flash message display
│
├── modules/                      # Role-based view/controller pages
│   ├── admin/                    # Admin portal pages
│   │   ├── dashboard.php
│   │   ├── analytics.php
│   │   ├── users.php
│   │   ├── teachers.php
│   │   ├── students.php
│   │   ├── courses.php
│   │   ├── departments.php
│   │   ├── reports.php
│   │   ├── settings.php
│   │   └── notifications.php
│   │
│   ├── teacher/                  # Teacher portal pages
│   │   ├── dashboard.php
│   │   ├── courses.php
│   │   ├── create_course.php
│   │   ├── lessons.php
│   │   ├── materials.php
│   │   ├── upload_material.php
│   │   ├── quizzes.php
│   │   ├── create_quiz.php
│   │   ├── question_bank.php
│   │   ├── ai_quiz_generator.php # AI quiz generation UI
│   │   ├── sections.php
│   │   ├── students.php
│   │   ├── student_progress.php
│   │   ├── analytics.php
│   │   ├── misconceptions.php
│   │   ├── announcements.php
│   │   ├── reports.php
│   │   ├── profile.php
│   │   └── notifications.php
│   │
│   ├── student/                  # Student portal pages
│   │   ├── dashboard.php
│   │   ├── courses.php
│   │   ├── course_view.php
│   │   ├── lessons.php
│   │   ├── learning_material.php
│   │   ├── quiz_list.php
│   │   ├── take_quiz.php
│   │   ├── quiz_result.php
│   │   ├── review_answers.php
│   │   ├── skill_tree.php
│   │   ├── study_plan.php
│   │   ├── calendar.php
│   │   ├── weak_topics.php
│   │   ├── recommendations.php
│   │   ├── profile.php
│   │   └── notifications.php
│   │
│   ├── auth/                     # Authentication pages
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   ├── forgot_password.php
│   │   └── reset_password.php
│   │
│   └── api/                      # AJAX / JSON API endpoints
│       ├── submit_answer.php
│       ├── get_lessons.php
│       ├── mark_lesson_complete.php
│       ├── notifications_count.php
│       ├── join_quiz.php
│       └── join_section.php
│
├── public/                       # Web-accessible static files
│   ├── .htaccess                 # Prevents directory listing
│   ├── assets/
│   │   ├── css/
│   │   │   └── custom.css        # Custom styles (Tailwind overrides)
│   │   ├── js/
│   │   │   └── app.js            # Shared JavaScript utilities
│   │   └── images/               # Static images, logos
│   └── uploads/                  # User-uploaded files (PDFs, videos, avatars)
│       ├── materials/            # Lesson materials
│       ├── avatars/              # User profile photos
│       └── quizzes/              # Temp quiz generation files
│
├── resources/                    # Frontend source / template files
│   ├── views/                    # Shared view templates
│   │   ├── layouts/              # Base layout templates
│   │   └── partials/             # Reusable view fragments
│   └── lang/                     # i18n language files (future use)
│
├── routes/
│   └── web.php                   # Centralized route map (reference)
│
├── storage/                      # Server-side writable files
│   ├── logs/                     # Application error logs
│   ├── cache/                    # Rate limiting, session cache
│   └── temp/                     # Temporary AI file processing
│
├── tests/                        # Automated tests
│   ├── Unit/                     # Unit tests (individual classes)
│   └── Integration/              # Integration tests (full workflows)
│
├── .htaccess                     # Apache: security headers, rewrite rules
├── index.php                     # Application entry point
├── install.php                   # First-run database installer
└── README.md                     # This file
```

---

## 7. Programming Languages, Frameworks & Database

### 7.0 Technology Stack Overview

The SEAIT E-Learning Platform is built on an open-source LAMP-compatible stack using industry-standard technologies aligned with Philippine higher education IT curricula.

---

### 7.1 Front-End Technologies

| Technology | Version | Purpose |
|------------|---------|---------|
| **HTML5** | HTML Living Standard | Semantic page structure, forms, media elements |
| **CSS3** | CSS3 | Custom animations, transitions, media queries |
| **Tailwind CSS** | v3.x (CDN) | Utility-first CSS framework for responsive UI design |
| **JavaScript (ES2021+)** | ES2021 | Client-side interactivity, AJAX, DOM manipulation |
| **Chart.js** | v4.x | Data visualization — bar, line, doughnut charts |
| **SVG** | W3C SVG 1.1 | Skill tree competency rings, progress indicators |

**Front-End Architecture Pattern:** Component-based partials via PHP includes (`includes/components/`). No front-end build step required — Tailwind is loaded via CDN.

**JavaScript Approach:** Vanilla JS (no React/Vue/Angular dependency). AJAX calls use the native `fetch()` API for real-time features (notification count, lesson loading, quiz submission).

---

### 7.2 Back-End Technologies

| Technology | Version | Purpose |
|------------|---------|---------|
| **PHP** | 8.1+ | Server-side language — routing, business logic, DB access |
| **Apache** | 2.4+ | Web server with mod_rewrite for URL handling |
| **Custom MVC Framework** | 1.0 (in-house) | Controllers, Models, Services, Middleware layers |

**Back-End Architecture:** Custom lightweight MVC framework built in PHP without external dependencies. No Composer/vendor directory required.

#### Framework Layers

```
Request → .htaccess → module page
                         │
                         ├── core/bootstrap.php     (loads everything)
                         ├── app/Middleware/         (Auth, CSRF, RateLimit)
                         ├── app/Controllers/        (role logic)
                         ├── app/Services/           (business operations)
                         ├── app/Models/             (data access)
                         └── core/                   (engine: DB, Auth, AI...)
```

#### PHP Extensions Required

```
pdo_mysql    — Database connectivity
zip          — PPTX/DOCX text extraction
curl         — Groq AI API calls
openssl      — Password hashing, secure tokens
gd           — Image processing (avatars)
json         — Request/response serialization
mbstring     — Multi-byte string support
```

---

### 7.3 Database

| Technology | Version | Purpose |
|------------|---------|---------|
| **MySQL** | 8.0+ | Primary relational database |
| **InnoDB Engine** | MySQL 8.0 | Transactions, foreign keys, ACID compliance |
| **utf8mb4 charset** | — | Full Unicode support including emoji |

**Database Access Pattern:** PDO (PHP Data Objects) with prepared statements. All queries are parameterized — zero raw string interpolation in SQL.

#### Database Statistics

| Metric | Count |
|--------|-------|
| Total tables | 26 |
| Core tables | 8 (users, departments, teachers, students, courses, enrollments, lessons, topics) |
| Assessment tables | 6 (quizzes, quiz_questions, quiz_sections, quiz_attempts, student_answers, student_progress) |
| AI & Analytics tables | 7 (competencies, student_competencies, diagnostic_feedback, recommendations, study_plans, at_risk_students, learning_materials) |
| System tables | 5 (notifications, announcements, audit_logs, password_resets, system_settings) |

---

### 7.4 AI & External Services

| Service | Provider | Purpose |
|---------|----------|---------|
| **LLaMA 3 (8B)** | Groq Cloud API | AI quiz question generation from lesson content |
| **Fallback Generator** | In-house (PHP) | Rule-based question generation when API unavailable |

**API Endpoint:** `https://api.groq.com/openai/v1/chat/completions`
**Model:** `llama3-8b-8192`
**Compatibility:** OpenAI SDK-compatible (drop-in replacement for GPT-4)

---

### 7.5 Development Environment

| Tool | Purpose |
|------|---------|
| **XAMPP** | Local development server (Apache + MySQL + PHP) |
| **phpMyAdmin** | Database management GUI |
| **VS Code / Kiro IDE** | Code editor with PHP/SQL support |

---

### 7.6 Security Implementation

| Mechanism | Implementation |
|-----------|---------------|
| Password Hashing | bcrypt (cost 12) via `password_hash()` |
| SQL Injection Prevention | PDO prepared statements throughout |
| XSS Prevention | `htmlspecialchars()` on all output via `e()` helper |
| CSRF Protection | Synchronized token pattern (`CsrfMiddleware`) |
| Session Security | `session_regenerate_id()` on login, HttpOnly cookies |
| Rate Limiting | `RateLimitMiddleware` — file-based, blocks after 10 hits/60s |
| Directory Protection | `.htaccess` blocks access to `config/`, `core/`, `database/` |
| Input Sanitization | `sanitize()` → `strip_tags()` → `htmlspecialchars()` |

---

## 8. AI Integration

### Quiz Generation Pipeline

```
Teacher uploads file (PDF/PPTX/TXT)
         │
         ▼
AIService::extractFromUpload()
  └── copy(tmp, named.ext)
  └── AIGenerator::extractText(named.ext)
       ├── PDF  → stream parser + gzuncompress()
       ├── PPTX → ZipArchive + <a:t> XML tags
       └── TXT  → file_get_contents()
         │
         ▼
  Extracted text (min 30 chars required)
         │
         ▼
AIGenerator::generate(text, count, type, difficulty, bloom)
  └── AI_ENABLED=true  → generateViaAPI() → Groq LLaMA 3
  └── AI_ENABLED=false → generateFallback() → Rule-based
         │
         ▼
  Normalized question array
  [{question_text, choices, correct_answer, explanation, ...}]
         │
         ▼
  Teacher reviews/edits questions in Step 2 UI
         │
         ▼
  AIService::saveQuestions() → quiz_questions table
         │
         ▼
  Redirect to Question Bank
```

### Adaptive Quiz Algorithm

```
Start quiz → difficulty = 'easy'
    │
    ▼
Answer question → store in student_answers with difficulty_at_answer
    │
    ▼
Check last 4 answers:
  2+ consecutive correct → level UP   (easy→medium, medium→hard)
  2+ consecutive wrong   → level DOWN (hard→medium, medium→easy)
    │
    ▼
AdaptiveEngine::nextQuestion() selects from matching difficulty pool
    │
    ▼
On completion → finalizeAttempt() → updateCompetencyMastery() → AtRisk::analyse()
```

---

## 9. Database Schema

See `database/seait_elearning.sql` for the full schema.

Key relationships:
```
users ──< teachers ──< courses ──< lessons ──< topics
                                            ──< learning_materials
                   ──< quizzes ──< quiz_sections
                              ──< quiz_questions
     ──< students ──< enrollments
                  ──< quiz_attempts ──< student_answers
                  ──< student_progress
                  ──< student_competencies
                  ──< recommendations
                  ──< study_plans
departments ──< teachers
            ──< students
```

---

## 10. Security

- All passwords hashed with bcrypt (cost factor 12)
- All DB queries use PDO prepared statements
- CSRF tokens on every form POST
- `config/`, `core/`, `database/` blocked from web access via `.htaccess`
- Session regenerated on every login
- Rate limiting on auth endpoints
- Input sanitized with `strip_tags()` + `htmlspecialchars()`
- File uploads validated by MIME type (not just extension)
- Remember-me tokens stored as SHA-256 hash in database

---

*SEAIT E-Learning Platform — South East Asian Institute of Technology*
*Built with PHP 8.1 · MySQL 8.0 · Tailwind CSS · Groq LLaMA 3*
#   S e a i t - E l e a r n i n g  
 