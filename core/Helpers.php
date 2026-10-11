<?php
/**
 * SEAIT E-Learning — Global Helper Functions
 */

// ── Output Sanitization ──────────────────────────────────────

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function nl2br_e(string $value): string
{
    return nl2br(e($value));
}

// ── Redirects ────────────────────────────────────────────────

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function redirectBack(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
    redirect($ref);
}

// ── Flash Messages ───────────────────────────────────────────

function flashSet(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function flashGet(string $type): array
{
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

function flashHas(string $type): bool
{
    return !empty($_SESSION['flash'][$type]);
}

// ── Input Sanitization ───────────────────────────────────────

function sanitize(mixed $input): string
{
    return trim(htmlspecialchars(strip_tags((string) $input), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function postStr(string $key, string $default = ''): string
{
    return sanitize($_POST[$key] ?? $default);
}

function getStr(string $key, string $default = ''): string
{
    return sanitize($_GET[$key] ?? $default);
}

function postInt(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function getInt(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $default);
}

// ── File Upload ──────────────────────────────────────────────

/**
 * Handle a file upload, returning the relative path on success.
 * Throws RuntimeException on failure.
 */
function handleUpload(array $file, string $subDir, array $allowedMimes): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error code: ' . $file['error']);
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File exceeds maximum allowed size.');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Normalize MIME for Office Open XML files (.docx, .pptx, .xlsx)
    // — finfo often returns application/zip or application/octet-stream for these
    $extMimeMap = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // If finfo returns a generic type, override with extension-based MIME
    if (in_array($mimeType, ['application/zip', 'application/octet-stream', 'application/x-zip-compressed'])
        && isset($extMimeMap[$ext])) {
        $mimeType = $extMimeMap[$ext];
    }

    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new RuntimeException('File type not allowed: ' . $mimeType . ' (.' . $ext . ')');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir      = UPLOAD_PATH . DIRECTORY_SEPARATOR . $subDir;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return 'uploads/' . $subDir . '/' . $filename;
}

// ── Pagination ───────────────────────────────────────────────

function paginate(int $totalRows, int $currentPage, int $perPage = ROWS_PER_PAGE): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'       => $totalRows,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

// ── Date / Time Helpers ──────────────────────────────────────

function timeAgo(string $datetime): string
{
    $time  = time() - strtotime($datetime);
    $units = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
        1        => 'second',
    ];

    foreach ($units as $seconds => $label) {
        $count = floor($time / $seconds);
        if ($count >= 1) {
            return "{$count} {$label}" . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function formatDate(string $datetime, string $format = 'M d, Y'): string
{
    return date($format, strtotime($datetime));
}

function formatDateTime(string $datetime): string
{
    return date('M d, Y g:i A', strtotime($datetime));
}

// ── Number / Score Helpers ───────────────────────────────────

function percentage(float $part, float $total): float
{
    if ($total <= 0) return 0;
    return round(($part / $total) * 100, 2);
}

function gradeLabel(float $pct): string
{
    return match (true) {
        $pct >= 97 => '1.00',
        $pct >= 93 => '1.25',
        $pct >= 89 => '1.50',
        $pct >= 85 => '1.75',
        $pct >= 80 => '2.00',
        $pct >= 76 => '2.25',
        $pct >= 72 => '2.50',
        $pct >= 68 => '2.75',
        $pct >= 64 => '3.00',
        $pct >= 60 => '5.00 (Conditional)',
        default    => '5.00 (Failed)',
    };
}

// ── Risk Badge ───────────────────────────────────────────────

function riskBadgeClass(string $level): string
{
    return match ($level) {
        'high'   => 'bg-red-100 text-red-700',
        'medium' => 'bg-yellow-100 text-yellow-700',
        'low'    => 'bg-green-100 text-green-700',
        default  => 'bg-gray-100 text-gray-600',
    };
}

// ── Mastery Badge / Color ─────────────────────────────────────

function masteryColor(int $pct): string
{
    return match (true) {
        $pct >= 80 => 'text-green-600',
        $pct >= 60 => 'text-blue-600',
        $pct >= 40 => 'text-yellow-600',
        default    => 'text-red-500',
    };
}

function masteryBarColor(int $pct): string
{
    return match (true) {
        $pct >= 80 => 'bg-green-500',
        $pct >= 60 => 'bg-blue-500',
        $pct >= 40 => 'bg-yellow-400',
        default    => 'bg-red-400',
    };
}

// ── Bloom Level Badge ────────────────────────────────────────

function bloomBadgeColor(string $level): string
{
    return match ($level) {
        'remember'  => 'bg-gray-100 text-gray-600',
        'understand'=> 'bg-blue-100 text-blue-600',
        'apply'     => 'bg-indigo-100 text-indigo-600',
        'analyze'   => 'bg-purple-100 text-purple-600',
        'evaluate'  => 'bg-pink-100 text-pink-600',
        'create'    => 'bg-rose-100 text-rose-700',
        default     => 'bg-gray-100 text-gray-500',
    };
}

// ── JSON Helpers ─────────────────────────────────────────────

function jsonResponse(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── File URL helper ──────────────────────────────────────────

/**
 * Build the correct public URL for an uploaded file.
 * Stored paths look like "uploads/materials/abc.pdf"
 * Actual URL must be BASE_URL . "/public/uploads/materials/abc.pdf"
 */
function fileUrl(string $path): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/public/' . $path;
}

// ── Notification Unread Count ─────────────────────────────────

function unreadNotifCount(int $userId): int
{
    $row = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    );
    return (int) ($row['cnt'] ?? 0);
}

// ── Student profile from user id ─────────────────────────────

function getStudentProfile(int $userId): ?array
{
    return Database::fetchOne(
        "SELECT s.*, u.first_name, u.last_name, u.email, u.avatar
         FROM students s JOIN users u ON u.id = s.user_id
         WHERE u.id = ? LIMIT 1",
        [$userId]
    );
}

function getTeacherProfile(int $userId): ?array
{
    return Database::fetchOne(
        "SELECT t.*, u.first_name, u.last_name, u.email, u.avatar
         FROM teachers t JOIN users u ON u.id = t.user_id
         WHERE u.id = ? LIMIT 1",
        [$userId]
    );
}

// ── Active Nav Link helper ────────────────────────────────────

function isActive(string $path): string
{
    $current = $_SERVER['PHP_SELF'] ?? '';
    return str_contains($current, $path) ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-700 hover:text-white';
}
