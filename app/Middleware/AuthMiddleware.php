<?php
/**
 * SEAIT E-Learning — Authentication Middleware
 *
 * Validates session state, enforces role-based access,
 * and handles remember-me token login before every request.
 */
class AuthMiddleware
{
    /**
     * Run the middleware.
     * Call this at the top of any page that requires authentication.
     */
    public static function handle(string|array $requiredRole = []): void
    {
        // Ensure session is started
        Auth::startSession();

        // Attempt remember-me auto-login if no active session
        if (!Auth::check()) {
            Auth::loginViaRememberToken();
        }

        // Enforce role if specified
        if (!empty($requiredRole)) {
            $roles = (array) $requiredRole;
            if (!Auth::hasAny($roles)) {
                if (!Auth::check()) {
                    // Not logged in — redirect to login
                    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
                    header('Location: ' . BASE_URL . '/modules/auth/login.php?redirect=' . $redirect);
                    exit;
                }
                // Logged in but wrong role
                http_response_code(403);
                include ROOT_PATH . '/includes/403.php';
                exit;
            }
        }
    }

    /**
     * Require authentication without role restriction.
     */
    public static function requireAuth(): void
    {
        self::handle();
        if (!Auth::check()) {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit;
        }
    }

    /**
     * Block authenticated users from accessing auth pages (login, register).
     */
    public static function guest(): void
    {
        if (Auth::check()) {
            $role = Auth::role();
            header('Location: ' . BASE_URL . '/modules/' . $role . '/dashboard.php');
            exit;
        }
    }
}
