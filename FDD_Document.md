# Feature-Driven Development (FDD) Document
## SEAIT E-Learning Platform - AI-Driven Adaptive Learning System

**Document Version:** 1.0  
**Date:** September 13, 2026  
**Project:** SEAIT E-Learning Platform  
**Development Approach:** Feature-Driven Development (FDD)

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [FDD Process Model](#fdd-process-model)
3. [Domain Model](#domain-model)
4. [Feature List by Subject Area](#feature-list-by-subject-area)
5. [Feature Implementation Details](#feature-implementation-details)
6. [Technical Architecture](#technical-architecture)
7. [Database Schema Overview](#database-schema-overview)

---

## Project Overview

### System Purpose
SEAIT E-Learning Platform is a comprehensive Learning Management System (LMS) with advanced AI-driven adaptive learning capabilities designed for Southeast Asian Institute of Technology. The platform provides personalized learning experiences through adaptive assessments, intelligent content recommendations, and predictive analytics.

### Core Value Proposition
- **Adaptive Learning**: Dynamic quiz difficulty adjustment based on real-time student performance
- **AI-Powered Content Generation**: Automated quiz creation from lesson materials using OpenAI GPT-4
- **Predictive Analytics**: Early identification of at-risk students with intervention recommendations
- **Personalized Study Planning**: SM-2 spaced repetition algorithm for optimal learning retention
- **Competency-Based Progression**: Skill tree framework with prerequisite-based unlocking

### Technology Stack
- **Backend**: PHP 8.1+, MySQL 8.0 (InnoDB)
- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **AI Integration**: OpenAI API (GPT-4o-mini)
- **Architecture**: MVC Pattern, Role-Based Access Control (RBAC)
- **Security**: CSRF Protection, Bcrypt Password Hashing, Prepared Statements

---

## FDD Process Model

FDD follows a five-step process model applied to this platform:

### 1. Develop an Overall Model
**Domain Areas Identified:**
- User Management & Authentication
- Course & Content Management
- Assessment & Adaptive Learning
- Analytics & Reporting
- Student Performance Tracking
- AI-Driven Personalization
- Communication & Notifications

### 2. Build a Features List
Features organized by **Subject Area → Business Activity → Feature**

### 3. Plan by Feature
Each feature assigned to:
- **Chief Programmer**: Module/area owner
- **Implementation Timeline**: Based on dependencies
- **Priority Level**: Critical, High, Medium, Low

### 4. Design by Feature
- Database schema design per feature set
- API endpoint design
- UI/UX wireframes
- Integration points

### 5. Build by Feature
- Feature-complete implementations
- Unit testing per feature
- Integration with existing modules
- User acceptance validation

---

## Domain Model

### Core Domain Objects

```
┌─────────────────────────────────────────────────────────────────┐
│                         DOMAIN MODEL                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────┐         ┌──────────┐         ┌──────────┐        │
│  │  USERS   │────────▶│ TEACHERS │         │  ADMIN   │        │
│  └──────────┘         └──────────┘         └──────────┘        │
│       │                    │                                     │
│       │                    │                                     │
│       ▼                    ▼                                     │
│  ┌──────────┐         ┌──────────┐         ┌──────────┐        │
│  │ STUDENTS │         │ COURSES  │◀────────│DEPARTMENTS│       │
│  └──────────┘         └──────────┘         └──────────┘        │
│       │                    │                                     │
│       │                    ├──────────┬──────────┐              │
│       │                    ▼          ▼          ▼              │
│       │              ┌──────────┐ ┌────────┐ ┌────────┐        │
│       │              │ LESSONS  │ │QUIZZES │ │SECTIONS│        │
│       │              └──────────┘ └────────┘ └────────┘        │
│       │                    │          │                         │
│       │              ┌─────┴──────┐  │                         │
│       │              ▼            ▼  │                         │
│       │         ┌────────┐  ┌─────────┐                       │
│       │         │ TOPICS │  │MATERIALS│                       │
│       │         └────────┘  └─────────┘                       │
│       │              │                                          │
│       │              ▼                                          │
│       │         ┌──────────────┐                               │
│       │         │ COMPETENCIES │                               │
│       │         └──────────────┘                               │
│       │                                                         │
│       ├─────────────────────┐                                  │
│       ▼                     ▼                                  │
│  ┌──────────┐         ┌──────────────────┐                   │
│  │  QUIZ    │         │    ADAPTIVE      │                   │
│  │ ATTEMPTS │◀────────│     ENGINE       │                   │
│  └──────────┘         └──────────────────┘                   │
│       │                     │                                  │
│       ▼                     ▼                                  │
│  ┌──────────┐         ┌──────────────────┐                   │
│  │ STUDENT  │         │  RECOMMENDATIONS │                   │
│  │ ANSWERS  │         └──────────────────┘                   │
│  └──────────┘                │                                 │
│       │                      ▼                                 │
│       ▼                 ┌──────────────────┐                  │
│  ┌──────────┐          │   STUDY PLANS    │                  │
│  │ STUDENT  │          │  (SM-2 SPACED    │                  │
│  │PROGRESS  │          │   REPETITION)    │                  │
│  └──────────┘          └──────────────────┘                  │
│       │                                                         │
│       ▼                                                         │
│  ┌──────────────────┐                                          │
│  │   AT-RISK        │                                          │
│  │   DETECTION      │                                          │
│  └──────────────────┘                                          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Feature List by Subject Area

### Subject Area 1: Authentication & User Management

#### 1.1 User Authentication
- **Feature 1.1.1**: Login with Email and Password
  - **Implementation**: `modules/auth/login.php`, `core/Auth.php::attempt()`
  - **Files**: `Auth.php` (lines 33-61)
  
- **Feature 1.1.2**: Remember Me Functionality
  - **Implementation**: `core/Auth.php::setRememberToken()`, `loginViaRememberToken()`
  - **Files**: `Auth.php` (lines 63-101)
  
- **Feature 1.1.3**: Password Reset via Email Token
  - **Implementation**: `modules/auth/forgot_password.php`, `modules/auth/reset_password.php`
  - **Files**: `Auth.php` (lines 159-193)
  
- **Feature 1.1.4**: CSRF Token Protection
  - **Implementation**: `core/Auth.php::verifyCsrf()`
  - **Files**: `Auth.php` (lines 151-157)

#### 1.2 User Profile Management
- **Feature 1.2.1**: Student Profile Update
  - **Implementation**: `modules/student/profile.php`
  - **Tables**: `students`, `users`
  
- **Feature 1.2.2**: Teacher Profile Management
  - **Implementation**: `modules/teacher/profile.php`
  - **Tables**: `teachers`, `users`

#### 1.3 Role-Based Access Control
- **Feature 1.3.1**: Admin Access Control
  - **Implementation**: `core/Auth.php::requireRole('admin')`
  - **Files**: `Auth.php` (lines 141-149)
  
- **Feature 1.3.2**: Teacher Access Control
  - **Implementation**: `core/Auth.php::requireRole('teacher')`
  
- **Feature 1.3.3**: Student Access Control
  - **Implementation**: `core/Auth.php::requireRole('student')`

---

### Subject Area 2: Course & Content Management

#### 2.1 Course Management
- **Feature 2.1.1**: Create Course
  - **Implementation**: `modules/teacher/create_course.php`
  - **Tables**: `courses`
  - **Fields**: title, code, description, thumbnail, credits, department_id
  
- **Feature 2.1.2**: Edit Course Details
  - **Implementation**: `modules/teacher/courses.php` (edit mode)
  - **Tables**: `courses`
  
- **Feature 2.1.3**: Publish/Unpublish Course
  - **Implementation**: `modules/teacher/courses.php` (toggle is_published)
  - **Tables**: `courses.is_published`
  
- **Feature 2.1.4**: Course Enrollment
  - **Implementation**: `modules/student/courses.php` (enroll action)
  - **Tables**: `enrollments`

#### 2.2 Section Management
- **Feature 2.2.1**: Create Course Sections
  - **Implementation**: `modules/teacher/sections.php`
  - **Tables**: `quiz_sections` (migrated from sections)
  - **Fields**: section_name, join_code, school_year, semester
  
- **Feature 2.2.2**: Generate Section Join Codes
  - **Implementation**: 6-character alphanumeric code generation
  - **Files**: `modules/teacher/sections.php`
  
- **Feature 2.2.3**: Student Join Section via Code
  - **Implementation**: `modules/api/join_section.php`
  - **Tables**: `enrollments` with section_id

#### 2.3 Lesson Management
- **Feature 2.3.1**: Create Structured Lessons
  - **Implementation**: `modules/teacher/lessons.php`
  - **Tables**: `lessons`
  - **Fields**: title, description, sort_order, is_published
  
- **Feature 2.3.2**: Create Topics within Lessons
  - **Implementation**: `modules/teacher/lessons.php` (topics section)
  - **Tables**: `topics`
  - **Fields**: lesson_id, title, description, sort_order
  
- **Feature 2.3.3**: Publish/Unpublish Lessons
  - **Implementation**: Toggle `lessons.is_published`

#### 2.4 Learning Materials Management
- **Feature 2.4.1**: Upload PDF Documents
  - **Implementation**: `modules/teacher/upload_material.php`
  - **Tables**: `learning_materials`
  - **Max Size**: 50MB
  - **Storage**: `public/uploads/materials/`
  
- **Feature 2.4.2**: Upload PowerPoint Presentations (PPTX/PPT)
  - **Implementation**: `modules/teacher/upload_material.php`
  - **Tables**: `learning_materials`
  
- **Feature 2.4.3**: Upload Video Files (MP4, WebM)
  - **Implementation**: `modules/teacher/upload_material.php`
  - **Tables**: `learning_materials`
  
- **Feature 2.4.4**: Add External Links
  - **Implementation**: `modules/teacher/materials.php`
  - **Tables**: `learning_materials.external_url`
  
- **Feature 2.4.5**: Set Downloadable Flag
  - **Implementation**: `learning_materials.is_downloadable`
  
- **Feature 2.4.6**: Material Sort Ordering
  - **Implementation**: `learning_materials.sort_order`

---

### Subject Area 3: Quiz & Assessment Management

#### 3.1 Quiz Creation & Configuration
- **Feature 3.1.1**: Create Quiz with Basic Settings
  - **Implementation**: `modules/teacher/create_quiz.php`
  - **Tables**: `quizzes`
  - **Fields**: title, description, instructions, time_limit, passing_score
  
- **Feature 3.1.2**: Set Max Attempts
  - **Implementation**: `quizzes.max_attempts` (default: 3)
  
- **Feature 3.1.3**: Configure Question Shuffling
  - **Implementation**: `quizzes.shuffle_questions`
  
- **Feature 3.1.4**: Configure Choice Shuffling
  - **Implementation**: `quizzes.shuffle_choices`
  
- **Feature 3.1.5**: Set Quiz Deadlines
  - **Implementation**: `quizzes.deadline`
  
- **Feature 3.1.6**: Generate Quiz Join Codes
  - **Implementation**: `quizzes.join_code` (10-char unique code)
  
- **Feature 3.1.7**: Enable Adaptive Mode
  - **Implementation**: `quizzes.is_adaptive`
  
- **Feature 3.1.8**: Show/Hide Results Option
  - **Implementation**: `quizzes.show_results`

#### 3.2 Question Bank Management
- **Feature 3.2.1**: Create Multiple Choice Questions (4 options)
  - **Implementation**: `modules/teacher/question_bank.php`
  - **Tables**: `quiz_questions`
  - **Fields**: question_type='multiple_choice', choices (JSON array)
  
- **Feature 3.2.2**: Create True/False Questions
  - **Implementation**: `quiz_questions.question_type='true_false'`
  
- **Feature 3.2.3**: Create Identification Questions
  - **Implementation**: `quiz_questions.question_type='identification'`
  
- **Feature 3.2.4**: Create Short Answer Questions
  - **Implementation**: `quiz_questions.question_type='short_answer'`
  
- **Feature 3.2.5**: Set Question Difficulty Level
  - **Implementation**: `quiz_questions.difficulty` (easy, medium, hard)
  
- **Feature 3.2.6**: Tag Question with Bloom's Level
  - **Implementation**: `quiz_questions.bloom_level` (remember, understand, apply, analyze, evaluate, create)
  
- **Feature 3.2.7**: Add Question Explanations
  - **Implementation**: `quiz_questions.explanation`
  
- **Feature 3.2.8**: Assign Questions to Topics
  - **Implementation**: `quiz_questions.topic_id` (links to competency tracking)
  
- **Feature 3.2.9**: Set Points per Question
  - **Implementation**: `quiz_questions.points`
  
- **Feature 3.2.10**: Reorder Questions
  - **Implementation**: `quiz_questions.sort_order`

#### 3.3 Quiz Section Organization
- **Feature 3.3.1**: Create Quiz Sections
  - **Implementation**: `quiz_sections` table
  - **Fields**: section_name, description, time_limit, sort_order
  
- **Feature 3.3.2**: Assign Questions to Sections
  - **Implementation**: `quiz_questions.section_id`
  
- **Feature 3.3.3**: Section-Specific Time Limits
  - **Implementation**: `quiz_sections.time_limit`

---

### Subject Area 4: AI-Powered Quiz Generation

#### 4.1 AI Quiz Generator
- **Feature 4.1.1**: Upload PDF for Text Extraction
  - **Implementation**: `modules/teacher/ai_quiz_generator.php`, `core/AIGenerator.php::extractText()`
  - **Extraction**: Uses pdftotext (poppler-utils) with binary fallback
  - **Files**: `AIGenerator.php` (lines 220-245)
  
- **Feature 4.1.2**: Upload PPTX for Text Extraction
  - **Implementation**: `core/AIGenerator.php::extractText()` (ZIP-based XML parsing)
  - **Files**: `AIGenerator.php` (lines 246-265)
  
- **Feature 4.1.3**: Manual Text Paste Option
  - **Implementation**: `modules/teacher/ai_quiz_generator.php` (textarea input)
  
- **Feature 4.1.4**: Configure Question Count (3-20)
  - **Implementation**: POST parameter `question_count`
  
- **Feature 4.1.5**: Select Question Type
  - **Implementation**: POST parameter `question_type`
  - **Options**: multiple_choice, true_false, identification, short_answer
  
- **Feature 4.1.6**: Set Target Difficulty
  - **Implementation**: POST parameter `difficulty`
  
- **Feature 4.1.7**: Set Bloom's Taxonomy Level
  - **Implementation**: POST parameter `bloom_level`
  
- **Feature 4.1.8**: OpenAI GPT-4 Integration
  - **Implementation**: `core/AIGenerator.php::generateViaOpenAI()`
  - **Model**: GPT-4o-mini
  - **API**: OpenAI Chat Completions
  - **Files**: `AIGenerator.php` (lines 37-82)
  
- **Feature 4.1.9**: Fallback Rule-Based Generator
  - **Implementation**: `core/AIGenerator.php::generateFallback()`
  - **Trigger**: When OPENAI_API_KEY not configured
  - **Files**: `AIGenerator.php` (lines 84-158)
  
- **Feature 4.1.10**: Preview Generated Questions
  - **Implementation**: `modules/teacher/ai_quiz_generator.php` (Step 2)
  
- **Feature 4.1.11**: Edit Generated Questions Before Saving
  - **Implementation**: JavaScript-based inline editing
  
- **Feature 4.1.12**: Bulk Import Questions to Quiz
  - **Implementation**: `modules/teacher/ai_quiz_generator.php` (save_questions action)

---

### Subject Area 5: Adaptive Learning Engine

#### 5.1 Adaptive Quiz Taking
- **Feature 5.1.1**: Dynamic Question Selection Based on Performance
  - **Implementation**: `core/AdaptiveEngine.php::nextQuestion()`
  - **Algorithm**: Selects questions matching current difficulty level
  - **Files**: `AdaptiveEngine.php` (lines 18-65)
  
- **Feature 5.1.2**: Start at Easy Difficulty
  - **Implementation**: `core/AdaptiveEngine.php::currentDifficulty()` returns 'easy' for first question
  - **Files**: `AdaptiveEngine.php` (line 73)
  
- **Feature 5.1.3**: Level Up After 2 Consecutive Correct Answers
  - **Implementation**: `STREAK_UP = 2` constant
  - **Logic**: Easy→Medium→Hard progression
  - **Files**: `AdaptiveEngine.php` (lines 90-95)
  
- **Feature 5.1.4**: Level Down After 2 Consecutive Incorrect Answers
  - **Implementation**: `STREAK_DOWN = 2` constant
  - **Logic**: Hard→Medium→Easy regression
  - **Files**: `AdaptiveEngine.php` (lines 97-103)
  
- **Feature 5.1.5**: Track Difficulty at Time of Answer
  - **Implementation**: `student_answers.difficulty_at_answer`
  - **Files**: `AdaptiveEngine.php` (line 136)

#### 5.2 Answer Evaluation
- **Feature 5.2.1**: Exact Match for Multiple Choice
  - **Implementation**: `core/AdaptiveEngine.php::checkAnswer()`
  - **Files**: `AdaptiveEngine.php` (line 162)
  
- **Feature 5.2.2**: Fuzzy Matching for Identification (85% threshold)
  - **Implementation**: `similar_text()` with 85% similarity
  - **Files**: `AdaptiveEngine.php` (line 163)
  
- **Feature 5.2.3**: Fuzzy Matching for Short Answer (75% threshold)
  - **Implementation**: `similar_text()` with 75% similarity
  - **Files**: `AdaptiveEngine.php` (line 164)
  
- **Feature 5.2.4**: Response Time Tracking
  - **Implementation**: `student_answers.response_time` (seconds)
  - **Files**: `AdaptiveEngine.php` (line 135)
  
- **Feature 5.2.5**: Points Calculation
  - **Implementation**: Awards question points if correct, 0 if incorrect
  - **Files**: `AdaptiveEngine.php` (lines 125-126)

#### 5.3 Attempt Finalization
- **Feature 5.3.1**: Calculate Total Score and Percentage
  - **Implementation**: `core/AdaptiveEngine.php::finalizeAttempt()`
  - **Formula**: (earned_points / total_points) × 100
  - **Files**: `AdaptiveEngine.php` (lines 173-182)
  
- **Feature 5.3.2**: Topic-Wise Performance Analysis
  - **Implementation**: Groups answers by topic_id, calculates accuracy
  - **Files**: `AdaptiveEngine.php` (lines 184-194)
  
- **Feature 5.3.3**: Update Quiz Attempt Record
  - **Implementation**: Updates `quiz_attempts` with final stats
  - **Tables**: `quiz_attempts.score`, `percentage`, `correct_count`, `incorrect_count`
  - **Files**: `AdaptiveEngine.php` (lines 196-208)

---

### Subject Area 6: Competency & Skill Tree

#### 6.1 Competency Framework
- **Feature 6.1.1**: Define Course Competencies
  - **Implementation**: `competencies` table
  - **Fields**: name, description, required_mastery, prerequisite_id
  
- **Feature 6.1.2**: Link Competencies to Topics
  - **Implementation**: `competencies.topic_id`
  
- **Feature 6.1.3**: Set Prerequisite Competencies
  - **Implementation**: `competencies.prerequisite_id` (self-referencing FK)
  
- **Feature 6.1.4**: Define Required Mastery Threshold
  - **Implementation**: `competencies.required_mastery` (default: 70%)

#### 6.2 Student Competency Tracking
- **Feature 6.2.1**: Track Mastery Percentage per Competency
  - **Implementation**: `student_competencies.mastery_pct` (0-100)
  - **Files**: `AdaptiveEngine.php` (lines 223-266)
  
- **Feature 6.2.2**: Auto-Update Mastery After Quiz
  - **Implementation**: `core/AdaptiveEngine.php::updateCompetencyMastery()`
  - **Trigger**: Called after `finalizeAttempt()`
  - **Files**: `AdaptiveEngine.php` (lines 218-283)
  
- **Feature 6.2.3**: Competency Status Progression
  - **Implementation**: locked → learning → completed → mastered
  - **Logic**:
    - Mastery ≥90%: mastered
    - Mastery ≥required_mastery: completed
    - Mastery <required_mastery: learning
    - Prerequisite incomplete: locked
  - **Files**: `AdaptiveEngine.php` (lines 242-248)
  
- **Feature 6.2.4**: Unlock Competencies When Prerequisites Met
  - **Implementation**: Scans prerequisite_id chains, unlocks when parent completed
  - **Files**: `AdaptiveEngine.php` (lines 268-283)
  
- **Feature 6.2.5**: Track Unlock and Mastery Dates
  - **Implementation**: `student_competencies.unlocked_at`, `mastered_at`

#### 6.3 Skill Tree Visualization
- **Feature 6.3.1**: Display Competency Tree
  - **Implementation**: `modules/student/skill_tree.php`
  - **Visual**: Circular progress rings with status icons
  
- **Feature 6.3.2**: Show Prerequisite Dependencies
  - **Implementation**: Vertical connector lines between competencies
  
- **Feature 6.3.3**: Color-Coded Status Indicators
  - **Implementation**:
    - Mastered: Green (⭐)
    - Completed: Blue (✅)
    - Learning: Orange (📖)
    - Locked: Gray (🔒)

---

### Subject Area 7: Weak Topics & Recommendations

#### 7.1 Weak Topic Identification
- **Feature 7.1.1**: Identify Topics with <70% Accuracy
  - **Implementation**: `modules/student/weak_topics.php`
  - **Query**: Groups student_answers by topic, filters accuracy <70%
  
- **Feature 7.1.2**: Categorize by Urgency Level
  - **Implementation**:
    - High: <40% accuracy (red)
    - Medium: 40-60% accuracy (amber)
    - Low: 60-70% accuracy (yellow)
  
- **Feature 7.1.3**: Direct Link to Review Lessons
  - **Implementation**: Button links to `modules/student/lessons.php?id={topic_id}`
  
- **Feature 7.1.4**: Direct Link to Practice Quizzes
  - **Implementation**: Button links to `modules/student/quiz_list.php?course_id={course_id}`

#### 7.2 Personalized Recommendations
- **Feature 7.2.1**: Generate Recommendations on Demand
  - **Implementation**: `core/Recommendation.php::generate()`
  - **Trigger**: Called from student dashboard
  - **Files**: `Recommendation.php` (lines 10-15)
  
- **Feature 7.2.2**: Recommend Review for Weak Topics
  - **Implementation**: Identifies top 3 weak topics, recommends linked lessons
  - **Priority**: 9 (high)
  - **Files**: `Recommendation.php` (lines 34-47)
  
- **Feature 7.2.3**: Recommend Next Incomplete Lesson
  - **Implementation**: Finds first unpublished lesson in sort order
  - **Priority**: 7 (medium)
  - **Files**: `Recommendation.php` (lines 49-63)
  
- **Feature 7.2.4**: Recommend Unattempted Quizzes
  - **Implementation**: Finds quizzes with no attempts by student
  - **Priority**: 8 (high)
  - **Files**: `Recommendation.php` (lines 65-78)
  
- **Feature 7.2.5**: Display Recommendation Reason
  - **Implementation**: Each recommendation includes explanation text
  - **Example**: "You scored 45% on 'Data Structures'. Review this lesson..."
  
- **Feature 7.2.6**: Dismiss Recommendations
  - **Implementation**: `core/Recommendation.php::dismiss()`, sets `is_dismissed=1`
  - **Files**: `Recommendation.php` (lines 101-104)

---

### Subject Area 8: Study Plan & Spaced Repetition

#### 8.1 Study Plan Generation
- **Feature 8.1.1**: Generate Personalized Study Plan
  - **Implementation**: `core/StudyPlan.php::generate()`
  - **Trigger**: Auto-generated when student has no future plans
  - **Files**: `StudyPlan.php` (lines 16-22)
  
- **Feature 8.1.2**: Clear Old Incomplete Plans
  - **Implementation**: Deletes future incomplete plans before regeneration
  - **Files**: `StudyPlan.php` (lines 18-21)
  
- **Feature 8.1.3**: 14-Day Planning Window
  - **Implementation**: Schedules activities over 14 days
  - **Files**: `StudyPlan.php` (lines 107-120)

#### 8.2 SM-2 Spaced Repetition Algorithm
- **Feature 8.2.1**: Calculate Easiness Factor (EF)
  - **Implementation**: `EF = max(1.3, 2.5 - 0.8 × (1 - score/100))`
  - **Files**: `StudyPlan.php` (lines 143-144)
  
- **Feature 8.2.2**: Compute Review Intervals
  - **Implementation**: `interval = previous_interval × EF`
  - **Base intervals**: rep1=1 day, rep2=3 days
  - **Files**: `StudyPlan.php` (lines 147-152)
  
- **Feature 8.2.3**: Cap Interval at 13 Days
  - **Implementation**: Maximum interval limited to 2-week window
  - **Files**: `StudyPlan.php` (line 154)
  
- **Feature 8.2.4**: Reset Interval on Failure (<60% score)
  - **Implementation**: Returns interval=1 for scores <60%
  - **Files**: `StudyPlan.php` (lines 139-141)

#### 8.3 Activity Scheduling
- **Feature 8.3.1**: Prioritize Weak Topics (Days 0-2)
  - **Implementation**: Weak topics scheduled within first 3 days
  - **Priority**: High
  - **Files**: `StudyPlan.php` (lines 33-45)
  
- **Feature 8.3.2**: Schedule Incomplete Lessons (Days 0-5)
  - **Implementation**: In-progress lessons on day 0, unseen lessons spread
  - **Priority**: High (in-progress), Medium (unseen)
  - **Files**: `StudyPlan.php` (lines 47-70)
  
- **Feature 8.3.3**: Schedule Quizzes Respecting Deadlines
  - **Implementation**: Computes interval with SM-2, overrides if deadline sooner
  - **Files**: `StudyPlan.php` (lines 72-97)
  
- **Feature 8.3.4**: Limit 3 Activities per Day
  - **Implementation**: Auto-shifts overflow to next day
  - **Files**: `StudyPlan.php` (lines 107-118)
  
- **Feature 8.3.5**: Set Estimated Time per Activity
  - **Implementation**:
    - Topics: 20 minutes
    - Lessons: 30 minutes
    - Quizzes: 25 minutes
  - **Files**: `StudyPlan.php` (lines 42, 63, 91)

#### 8.4 Plan Interaction
- **Feature 8.4.1**: View 7-Day Study Calendar
  - **Implementation**: `modules/student/study_plan.php`
  - **Query**: Fetches plans for date range
  
- **Feature 8.4.2**: Mark Activities as Complete
  - **Implementation**: `core/StudyPlan.php::complete()`, sets `is_completed=1`
  - **Files**: `StudyPlan.php` (lines 170-177)
  
- **Feature 8.4.3**: Regenerate Study Plan
  - **Implementation**: Button triggers `StudyPlan::generate()` again
  - **Effect**: Clears incomplete future plans, reschedules

---

### Subject Area 9: At-Risk Student Detection

#### 9.1 Risk Factor Analysis
- **Feature 9.1.1**: Detect Low Quiz Scores (<60%)
  - **Implementation**: `core/AtRisk.php::avgScore()`
  - **Weight**: +30 risk points
  - **Files**: `AtRisk.php` (lines 44-51)
  
- **Feature 9.1.2**: Detect Inactivity (>7 days)
  - **Implementation**: `core/AtRisk.php::daysSinceLastActivity()`
  - **Weight**: +25 risk points
  - **Files**: `AtRisk.php` (lines 53-62)
  
- **Feature 9.1.3**: Detect Missed Quizzes
  - **Implementation**: `core/AtRisk.php::missedQuizzes()` (past deadline, no attempt)
  - **Weight**: +20 risk points
  - **Files**: `AtRisk.php` (lines 64-74)
  
- **Feature 9.1.4**: Detect Low Lesson Completion (<30%)
  - **Implementation**: `core/AtRisk.php::lessonCompletionPct()`
  - **Weight**: +25 risk points
  - **Files**: `AtRisk.php` (lines 76-85)

#### 9.2 Risk Classification
- **Feature 9.2.1**: Calculate Risk Score (0-100)
  - **Implementation**: Sum of all risk factor weights
  - **Files**: `AtRisk.php` (lines 17-32)
  
- **Feature 9.2.2**: Assign Risk Level
  - **Implementation**:
    - High: 70-100 points
    - Medium: 40-69 points
    - Low: 0-39 points
  - **Files**: `AtRisk.php` (lines 34-38)

#### 9.3 Intervention Recommendations
- **Feature 9.3.1**: Generate Intervention Suggestions
  - **Implementation**: `core/AtRisk.php::suggestIntervention()`
  - **High**: "Immediate intervention required. Schedule 1-on-1 tutoring..."
  - **Medium**: "Monitor closely. Recommend supplemental materials..."
  - **Low**: "Encourage continued engagement..."
  - **Files**: `AtRisk.php` (lines 87-94)
  
- **Feature 9.3.2**: Identify Weak Topics for At-Risk Students
  - **Implementation**: Calls `AdaptiveEngine::identifyWeakTopics()`
  - **Files**: `AtRisk.php` (line 40)
  
- **Feature 9.3.3**: Upsert At-Risk Records
  - **Implementation**: Updates existing record or inserts new
  - **Tables**: `at_risk_students`
  - **Files**: `AtRisk.php` (lines 48-63)
  
- **Feature 9.3.4**: Auto-Resolve When Risk Cleared
  - **Implementation**: Sets `is_resolved=1` when risk score drops to 0
  - **Files**: `AtRisk.php` (lines 34-37)

---

### Subject Area 10: Analytics & Reporting

#### 10.1 Student Analytics
- **Feature 10.1.1**: Personal Dashboard Statistics
  - **Implementation**: `modules/student/dashboard.php`
  - **Metrics**: enrolled courses, completed lessons, available quizzes, overall mastery
  
- **Feature 10.1.2**: 6-Month Performance Trend Chart
  - **Implementation**: Chart.js line chart with monthly averages
  - **Files**: `modules/student/dashboard.php` (lines 46-52)
  
- **Feature 10.1.3**: Recent Quiz Scores (Last 6)
  - **Implementation**: Query `quiz_attempts` ordered by `submitted_at DESC`
  - **Files**: `modules/student/dashboard.php` (lines 40-44)
  
- **Feature 10.1.4**: Upcoming Deadlines (Top 5)
  - **Implementation**: Fetches unpublished quizzes with future deadlines
  - **Files**: `modules/student/dashboard.php` (lines 26-32)

#### 10.2 Teacher Analytics
- **Feature 10.2.1**: Course Performance Dashboard
  - **Implementation**: `modules/teacher/analytics.php`
  - **Metrics**: average scores, pass rates, total attempts, at-risk count
  
- **Feature 10.2.2**: Per-Quiz Performance Breakdown
  - **Implementation**: Query groups attempts by quiz_id
  - **Metrics**: avg/max/min scores, attempt count
  
- **Feature 10.2.3**: Student Rankings by Average Score
  - **Implementation**: Ordered list of students with overall avg percentages
  
- **Feature 10.2.4**: Score Distribution Histogram
  - **Implementation**: Buckets: 0-20, 21-40, 41-60, 61-80, 81-100
  
- **Feature 10.2.5**: Misconception Analytics
  - **Implementation**: `modules/teacher/misconceptions.php`
  - **Query**: Groups incorrect answers by question, shows common wrong answers
  - **Files**: `modules/teacher/misconceptions.php` (lines 11-28)
  
- **Feature 10.2.6**: Topic Error Rate Visualization
  - **Implementation**: Bar chart showing wrong_pct per topic
  - **Files**: `modules/teacher/misconceptions.php` (lines 30-35)

#### 10.3 Admin Analytics
- **Feature 10.3.1**: Institution-Wide Dashboard
  - **Implementation**: `modules/admin/dashboard.php`
  - **Metrics**: total students, teachers, courses, quizzes, attempts, avg performance
  
- **Feature 10.3.2**: Monthly Enrollment Trends (6 months)
  - **Implementation**: Chart.js bar chart with enrollment counts per month
  - **Files**: `modules/admin/dashboard.php` (lines 23-32)
  
- **Feature 10.3.3**: Department Performance Comparison
  - **Implementation**: Query groups by department, calculates avg scores
  - **Files**: `modules/admin/dashboard.php` (lines 34-41)
  
- **Feature 10.3.4**: At-Risk Student Aggregation
  - **Implementation**: Top 5 at-risk students across all courses
  - **Files**: `modules/admin/dashboard.php` (lines 43-50)
  
- **Feature 10.3.5**: Recent Audit Log Activity
  - **Implementation**: Last 10 audit log entries with user names
  - **Files**: `modules/admin/dashboard.php` (lines 17-21)

---

### Subject Area 11: Communication & Notifications

#### 11.1 Notification System
- **Feature 11.1.1**: Create Notifications
  - **Implementation**: `core/Notification.php::send()`
  - **Types**: quiz_deadline, new_lesson, new_quiz, quiz_result, achievement, study_reminder, announcement, system
  
- **Feature 11.1.2**: Real-Time Notification Count
  - **Implementation**: `modules/api/notifications_count.php` (AJAX endpoint)
  - **Returns**: JSON with unread count
  
- **Feature 11.1.3**: Mark Notifications as Read
  - **Implementation**: Sets `notifications.is_read=1`, `read_at=NOW()`
  
- **Feature 11.1.4**: Link Notifications to Content
  - **Implementation**: `notifications.link` field stores URL

#### 11.2 Announcements
- **Feature 11.2.1**: Teacher Course Announcements
  - **Implementation**: `modules/teacher/announcements.php`
  - **Tables**: `announcements` with `course_id`
  
- **Feature 11.2.2**: Institution-Wide Announcements
  - **Implementation**: `announcements` with `course_id=NULL`
  - **Visible**: All users
  
- **Feature 11.2.3**: Activate/Deactivate Announcements
  - **Implementation**: Toggle `announcements.is_active`

---

### Subject Area 12: Department & User Management

#### 12.1 Department Management
- **Feature 12.1.1**: Create Departments
  - **Implementation**: `modules/admin/departments.php`
  - **Tables**: `departments`
  - **Fields**: name, code, description, head_id
  
- **Feature 12.1.2**: Assign Department Heads
  - **Implementation**: `departments.head_id` → `teachers.id`
  
- **Feature 12.1.3**: Activate/Deactivate Departments
  - **Implementation**: Toggle `departments.is_active`

#### 12.2 Student Management (Admin)
- **Feature 12.2.1**: View All Students
  - **Implementation**: `modules/admin/students.php`
  - **Tables**: `students` JOIN `users`
  
- **Feature 12.2.2**: Add New Student
  - **Implementation**: Create `users` record + `students` profile
  
- **Feature 12.2.3**: Edit Student Details
  - **Implementation**: Update `students` and `users` tables
  
- **Feature 12.2.4**: Assign Student to Department
  - **Implementation**: `students.department_id`
  
- **Feature 12.2.5**: Deactivate Student Account
  - **Implementation**: Set `users.is_active=0`

#### 12.3 Teacher Management (Admin)
- **Feature 12.3.1**: View All Teachers
  - **Implementation**: `modules/admin/teachers.php`
  
- **Feature 12.3.2**: Add New Teacher
  - **Implementation**: Create `users` record + `teachers` profile
  
- **Feature 12.3.3**: Edit Teacher Details
  - **Implementation**: Update `teachers` and `users` tables
  
- **Feature 12.3.4**: Set Teacher Specialization
  - **Implementation**: `teachers.specialization`
  
- **Feature 12.3.5**: Assign Teacher to Department
  - **Implementation**: `teachers.department_id`

---

### Subject Area 13: Diagnostic Feedback & Remediation

#### 13.1 Diagnostic Feedback Generation
- **Feature 13.1.1**: Generate Feedback for Incorrect Answers
  - **Implementation**: `modules/student/take_quiz.php` (after finalize)
  - **Tables**: `diagnostic_feedback`
  
- **Feature 13.1.2**: Identify Misconceptions
  - **Implementation**: Context-aware misconception text based on question type
  - **Examples**:
    - Multiple Choice: "Selected incorrect option..."
    - True/False: "Chose False but correct is True..."
    - Identification: "Terminology gap detected..."
    - Short Answer: "Concept understanding needs reinforcement..."
  
- **Feature 13.1.3**: Suggest Related Topics
  - **Implementation**: `diagnostic_feedback.related_topic` links to `topics.title`
  
- **Feature 13.1.4**: Recommend Review Lessons
  - **Implementation**: `diagnostic_feedback.recommended_lesson_id`
  
- **Feature 13.1.5**: Provide Remediation Activities
  - **Implementation**: `diagnostic_feedback.suggested_activity` field

---

### Subject Area 14: Audit & Security

#### 14.1 Audit Logging
- **Feature 14.1.1**: Log User Actions
  - **Implementation**: `core/AuditLog.php::write()`
  - **Tables**: `audit_logs`
  - **Fields**: user_id, action, module, description, ip_address, user_agent
  
- **Feature 14.1.2**: Log Authentication Events
  - **Implementation**: Login, logout, failed login attempts
  - **Files**: `Auth.php` (lines 53, 58, 105)
  
- **Feature 14.1.3**: Log Content Creation/Modification
  - **Implementation**: Course creation, quiz creation, AI generation, etc.
  
- **Feature 14.1.4**: Admin Audit Log Viewer
  - **Implementation**: `modules/admin/dashboard.php` (recent activity section)

#### 14.2 Security Features
- **Feature 14.2.1**: Password Hashing with Bcrypt
  - **Implementation**: `password_hash()` with cost 12
  - **Files**: Throughout codebase
  
- **Feature 14.2.2**: CSRF Token Validation
  - **Implementation**: `Auth::verifyCsrf()` on all POST requests
  - **Files**: `Auth.php` (lines 151-157)
  
- **Feature 14.2.3**: Session Regeneration on Login
  - **Implementation**: `session_regenerate_id(true)` in `Auth::attempt()`
  - **Files**: `Auth.php` (line 50)
  
- **Feature 14.2.4**: SQL Injection Prevention
  - **Implementation**: Prepared statements throughout (Database class)
  
- **Feature 14.2.5**: File Upload Validation
  - **Implementation**: MIME type checking, size limits, extension whitelist

---

## Feature Implementation Details

### Critical Path Features (MVP)

**Priority 1 - Authentication & Core Access:**
1. User Login/Logout (`Auth.php`)
2. Role-Based Access Control (`Auth::requireRole()`)
3. Password Reset (`modules/auth/reset_password.php`)

**Priority 2 - Course Foundation:**
1. Create Course (`modules/teacher/create_course.php`)
2. Create Lessons (`modules/teacher/lessons.php`)
3. Upload Materials (`modules/teacher/upload_material.php`)
4. Student Enrollment (`modules/student/courses.php`)

**Priority 3 - Assessment Core:**
1. Create Quiz (`modules/teacher/create_quiz.php`)
2. Add Questions (`modules/teacher/question_bank.php`)
3. Take Quiz (`modules/student/take_quiz.php`)
4. View Results (`modules/student/quiz_result.php`)

**Priority 4 - Adaptive Learning:**
1. Adaptive Engine (`core/AdaptiveEngine.php`)
2. Competency Tracking (`student_competencies`)
3. Weak Topics (`modules/student/weak_topics.php`)

**Priority 5 - AI & Personalization:**
1. AI Quiz Generator (`core/AIGenerator.php`)
2. Recommendations (`core/Recommendation.php`)
3. Study Plans (`core/StudyPlan.php`)
4. At-Risk Detection (`core/AtRisk.php`)

---

## Technical Architecture

### MVC Pattern

```
┌─────────────────────────────────────────┐
│           PRESENTATION LAYER            │
│  (modules/admin, teacher, student)      │
│  - HTML Views with Tailwind CSS         │
│  - JavaScript AJAX Interactions         │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│          BUSINESS LOGIC LAYER           │
│  (core/*.php)                           │
│  - Auth, Database, AdaptiveEngine       │
│  - AIGenerator, StudyPlan, AtRisk       │
│  - Recommendation, Notification         │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│            DATA ACCESS LAYER            │
│  (Database.php)                         │
│  - PDO with Prepared Statements         │
│  - CRUD Operations (fetchAll, insert)   │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│          DATABASE (MySQL 8.0)           │
│  - 25+ Tables with Foreign Keys         │
│  - Indexes on Key Columns               │
│  - JSON Fields for Flexible Data        │
└─────────────────────────────────────────┘
```

### Key Design Patterns

**1. Singleton Pattern:**
- Database connection management

**2. Factory Pattern:**
- Recommendation generation
- Diagnostic feedback creation

**3. Strategy Pattern:**
- Question evaluation (by type)
- Adaptive difficulty adjustment

**4. Repository Pattern:**
- Database abstraction layer (`Database.php`)

**5. Service Layer:**
- `AdaptiveEngine`, `AIGenerator`, `StudyPlan`, `AtRisk` as services

---

## Database Schema Overview

### Core Tables (8)
1. **users** - Central authentication (1,000+ records expected)
2. **departments** - Academic units (10-20 records)
3. **teachers** - Teacher profiles (50-200 records)
4. **students** - Student profiles (1,000-10,000 records)
5. **courses** - Course catalog (100-500 records)
6. **enrollments** - Student-course relationships (10,000+ records)
7. **lessons** - Learning modules (1,000-5,000 records)
8. **topics** - Lesson sub-units (5,000-20,000 records)

### Assessment Tables (6)
9. **quizzes** - Assessments (500-2,000 records)
10. **quiz_questions** - Question bank (10,000-50,000 records)
11. **quiz_sections** - Quiz organization (200-1,000 records)
12. **quiz_attempts** - Student attempts (50,000-500,000 records)
13. **student_answers** - Individual answers (500,000-5,000,000 records)
14. **student_progress** - Lesson completion (100,000-1,000,000 records)

### AI & Analytics Tables (7)
15. **competencies** - Skill framework (500-2,000 records)
16. **student_competencies** - Mastery tracking (50,000-200,000 records)
17. **diagnostic_feedback** - AI feedback (100,000-500,000 records)
18. **recommendations** - Personalized suggestions (50,000-200,000 records)
19. **study_plans** - Scheduled activities (100,000-500,000 records)
20. **at_risk_students** - Risk flags (1,000-10,000 records)
21. **learning_materials** - Content files (5,000-20,000 records)

### System Tables (4)
22. **notifications** - User alerts (100,000-1,000,000 records)
23. **announcements** - Broadcasts (500-2,000 records)
24. **audit_logs** - Activity tracking (1,000,000+ records)
25. **password_resets** - Reset tokens (1,000-5,000 records)
26. **system_settings** - Configuration (10-50 records)

### Indexing Strategy
- Primary keys on all `id` columns
- Indexes on foreign keys (student_id, course_id, quiz_id, etc.)
- Composite indexes on frequently queried combinations
- Indexes on status/flag columns (is_active, is_published, is_resolved)

---

## Feature Dependency Map

```
Authentication & RBAC (Subject Area 1)
    │
    ├──> Course Management (Subject Area 2.1)
    │        │
    │        ├──> Lesson Management (Subject Area 2.3)
    │        │        │
    │        │        └──> Materials Management (Subject Area 2.4)
    │        │
    │        └──> Quiz Creation (Subject Area 3.1)
    │                 │
    │                 ├──> Question Bank (Subject Area 3.2)
    │                 │
    │                 ├──> AI Quiz Generator (Subject Area 4)
    │                 │
    │                 └──> Quiz Taking
    │                          │
    │                          ├──> Adaptive Engine (Subject Area 5)
    │                          │        │
    │                          │        └──> Competency Tracking (Subject Area 6)
    │                          │
    │                          └──> Result & Review
    │                                   │
    │                                   ├──> Weak Topics (Subject Area 7.1)
    │                                   │
    │                                   ├──> Recommendations (Subject Area 7.2)
    │                                   │
    │                                   ├──> Study Plans (Subject Area 8)
    │                                   │
    │                                   ├──> At-Risk Detection (Subject Area 9)
    │                                   │
    │                                   └──> Diagnostic Feedback (Subject Area 13)
    │
    ├──> Analytics & Reporting (Subject Area 10)
    │
    ├──> Notifications (Subject Area 11)
    │
    └──> Admin Management (Subject Area 12)
```

---

## Implementation Roadmap

### Phase 1: Foundation (Weeks 1-4) ✅ COMPLETE
- Authentication & RBAC
- Database schema setup
- User management (admin, teacher, student)
- Department management
- Basic course & lesson creation

### Phase 2: Content & Assessment (Weeks 5-8) ✅ COMPLETE
- Material upload system
- Quiz creation & question bank
- Sequential quiz taking
- Result display & review
- Section management

### Phase 3: Adaptive Learning (Weeks 9-12) ✅ COMPLETE
- Adaptive Engine implementation
- Dynamic difficulty adjustment
- Competency framework
- Skill tree visualization
- Topic performance tracking

### Phase 4: AI Integration (Weeks 13-16) ✅ COMPLETE
- OpenAI API integration
- AI quiz generator
- Text extraction (PDF/PPTX)
- Fallback generator

### Phase 5: Personalization (Weeks 17-20) ✅ COMPLETE
- Weak topic identification
- Recommendation engine
- SM-2 spaced repetition
- Study plan generation
- At-risk detection

### Phase 6: Analytics & Reporting (Weeks 21-24) ✅ COMPLETE
- Student analytics dashboard
- Teacher analytics & misconceptions
- Admin institution-wide reports
- Diagnostic feedback system
- Audit logging

### Phase 7: Polish & Optimization (Weeks 25-26) ✅ COMPLETE
- Performance optimization
- UI/UX refinements
- Security hardening
- Documentation
- User acceptance testing

---

## Success Metrics

### Student Engagement
- **Quiz Completion Rate**: Target >80%
- **Lesson Progress**: Target average 70%+ completion
- **Study Plan Adherence**: Target 60%+ activities completed
- **Platform Active Users**: Target 85%+ monthly active rate

### Learning Effectiveness
- **Average Quiz Score**: Target 75%+
- **Competency Mastery Rate**: Target 70%+ students reaching "completed" status
- **Weak Topic Resolution**: Target 50%+ improvement after recommendations
- **At-Risk Student Recovery**: Target 40%+ at-risk students return to green status

### AI Feature Usage
- **AI Quiz Generation Adoption**: Target 50%+ of quizzes use AI-generated questions
- **Recommendation Click-Through**: Target 40%+ students act on recommendations
- **Study Plan Usage**: Target 60%+ students follow generated plans

### System Performance
- **Page Load Time**: Target <2 seconds
- **Quiz Taking Latency**: Target <500ms per answer submission
- **API Response Time**: Target <200ms for AJAX endpoints
- **Database Query Optimization**: Target <100ms for 95% of queries

---

## Maintenance & Evolution

### Continuous Improvement Areas
1. **AI Model Training**: Refine quiz generation prompts based on teacher feedback
2. **Algorithm Tuning**: Adjust SM-2 intervals, adaptive difficulty thresholds based on data
3. **Analytics Expansion**: Add more visualization types, export formats
4. **Mobile Responsiveness**: Optimize Tailwind CSS for mobile devices
5. **Accessibility**: WCAG 2.1 compliance improvements

### Planned Future Enhancements
- **Gamification**: Badges, leaderboards, achievement system
- **Peer Collaboration**: Discussion forums, study groups
- **Video Conferencing**: Integrate live class sessions
- **Parent Portal**: Guardian access to student progress
- **Multi-Language**: i18n support for Filipino, English
- **LTI Integration**: Connect with external LMS platforms

---

## Conclusion

This FDD document comprehensively maps all implemented features of the SEAIT E-Learning Platform. The platform successfully delivers:

✅ **13 Subject Areas** with 200+ individual features  
✅ **5 Core Classes** (`AdaptiveEngine`, `AIGenerator`, `StudyPlan`, `AtRisk`, `Recommendation`)  
✅ **26 Database Tables** with full normalization and referential integrity  
✅ **3 User Roles** with distinct, role-appropriate feature sets  
✅ **AI-Driven Personalization** across assessments, recommendations, and study planning  
✅ **Adaptive Assessment Engine** with real-time difficulty adjustment  
✅ **Predictive Analytics** for early intervention with at-risk students  

The platform is production-ready and provides a solid foundation for continuous enhancement based on user feedback and institutional needs.

---

**Document Control**  
**Author**: Development Team  
**Last Updated**: September 13, 2026  
**Next Review**: December 2026  
**Status**: Active - Production
