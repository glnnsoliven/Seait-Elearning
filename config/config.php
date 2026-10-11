<?php
/**
 * SEAIT E-Learning Platform — Central Configuration
 */

// ── Environment ──────────────────────────────────────────────
define('APP_ENV',     'development');   // 'development' | 'production'
define('APP_DEBUG',   APP_ENV === 'development');
define('APP_NAME',    'SEAIT E-Learning Platform');
define('APP_VERSION', '1.0.0');

// ── Base URL & Paths ─────────────────────────────────────────
// Detect base URL automatically
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir  = dirname($_SERVER['SCRIPT_NAME'] ?? '');

// Walk up to project root (remove /modules/xxx, /includes, etc.)
$baseDir = rtrim($scriptDir, '/');
// If we're inside a subdirectory module, strip back to project root
foreach (['modules/admin','modules/teacher','modules/student','modules/auth','modules/api','includes'] as $sub) {
    $sub = str_replace('/', DIRECTORY_SEPARATOR, $sub);
    if (str_ends_with($baseDir, str_replace(DIRECTORY_SEPARATOR, '/', $sub))) {
        $baseDir = substr($baseDir, 0, strlen($baseDir) - strlen('/' . str_replace(DIRECTORY_SEPARATOR, '/', $sub)));
        break;
    }
}

define('BASE_URL',    $protocol . '://' . $host . $baseDir);
define('ROOT_PATH',   dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('UPLOAD_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
define('LOG_PATH',    STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');
define('CACHE_PATH',  STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache');
define('TEMP_PATH',   STORAGE_PATH . DIRECTORY_SEPARATOR . 'temp');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    '3306');
define('DB_NAME',    'seait_elearning');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',     'seait_session');
define('SESSION_LIFETIME', 7200);   // seconds (2 hours)
define('REMEMBER_DAYS',    30);

// ── Security ─────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');
define('BCRYPT_COST',     12);

// ── File Upload ──────────────────────────────────────────────
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);  // 50 MB
define('ALLOWED_MATERIAL_TYPES', [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // Word documents
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'video/mp4',
    'video/webm',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/gif',
]);

// Allowed types for the AI Quiz Generator (broader — no video/images needed)
define('ALLOWED_AI_UPLOAD_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    // Some browsers send these for docx/pptx
    'application/zip',
    'application/octet-stream',
]);
define('ALLOWED_AVATAR_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);

// ── AI / External API ────────────────────────────────────────
// Groq API key (compatible with OpenAI SDK format)
define('OPENAI_API_KEY',  getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL',    'llama-3.1-8b-instant'); // Groq fastest model
define('OPENAI_API_URL',  'https://api.groq.com/openai/v1/chat/completions');
define('AI_ENABLED',      !empty(OPENAI_API_KEY) && OPENAI_API_KEY !== 'your-openai-api-key-here');

// ── Pagination ───────────────────────────────────────────────
define('ROWS_PER_PAGE', 15);

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Error reporting ──────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
