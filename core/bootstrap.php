<?php
/**
 * SEAIT E-Learning — Application Bootstrap
 *
 * Include this file at the top of every PHP page.
 * Load order: config → core engine → app framework → session
 */

define('ROOT_BOOTSTRAP', true);

// ── Core configuration ────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';

// ── Core engine classes ───────────────────────────────────────
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/AdaptiveEngine.php';
require_once __DIR__ . '/Recommendation.php';
require_once __DIR__ . '/AtRisk.php';
require_once __DIR__ . '/StudyPlan.php';
require_once __DIR__ . '/AIGenerator.php';

// ── App framework layer (MVC) ─────────────────────────────────
require_once ROOT_PATH . '/app/Models/BaseModel.php';
require_once ROOT_PATH . '/app/Models/User.php';
require_once ROOT_PATH . '/app/Models/Course.php';
require_once ROOT_PATH . '/app/Models/Quiz.php';

require_once ROOT_PATH . '/app/Controllers/BaseController.php';
require_once ROOT_PATH . '/app/Controllers/AdminController.php';
require_once ROOT_PATH . '/app/Controllers/TeacherController.php';
require_once ROOT_PATH . '/app/Controllers/StudentController.php';

require_once ROOT_PATH . '/app/Middleware/AuthMiddleware.php';
require_once ROOT_PATH . '/app/Middleware/CsrfMiddleware.php';
require_once ROOT_PATH . '/app/Middleware/RateLimitMiddleware.php';

require_once ROOT_PATH . '/app/Services/AIService.php';
require_once ROOT_PATH . '/app/Services/NotificationService.php';
require_once ROOT_PATH . '/app/Services/QuizService.php';

// ── Start session ─────────────────────────────────────────────
Auth::startSession();
