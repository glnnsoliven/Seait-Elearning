<?php
/**
 * SEAIT E-Learning — CSRF Middleware
 *
 * Provides CSRF token generation and validation.
 * Can be used as an alternative to calling Auth::verifyCsrf() directly.
 */
class CsrfMiddleware
{
    /**
     * Verify CSRF token on POST requests.
     * Terminates with 419 if token is invalid.
     */
    public static function verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $token = $_POST[CSRF_TOKEN_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            if (self::isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'CSRF token mismatch.', 'code' => 419]);
            } else {
                include ROOT_PATH . '/includes/403.php';
            }
            exit;
        }
    }

    /**
     * Generate and return a new CSRF token, storing in session.
     */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Render hidden CSRF input field.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::token() . '">';
    }

    private static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json';
    }
}
