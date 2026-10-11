<?php
/**
 * SEAIT E-Learning — Authentication & Session Manager
 */

class Auth
{
    // ── Session bootstrap ────────────────────────────────────

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => !APP_DEBUG,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    // ── Login ────────────────────────────────────────────────

    /**
     * Attempt to log in with email/password.
     * Returns true on success, string error message on failure.
     */
    public static function attempt(string $email, string $password): bool|string
    {
        $user = Database::fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1",
            [$email]
        );

        if (!$user) {
            return 'Invalid email or password.';
        }

        if (!password_verify($password, $user['password'])) {
            AuditLog::write($user['id'], 'login_failed', 'auth', 'Failed login attempt for ' . $email);
            return 'Invalid email or password.';
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_avatar']= $user['avatar'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Update last_login
        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        AuditLog::write($user['id'], 'login', 'auth', 'User logged in successfully');

        return true;
    }

    // ── Remember Me ──────────────────────────────────────────

    public static function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        Database::update('users', ['remember_token' => hash('sha256', $token)], 'id = ?', [$userId]);

        setcookie('remember_token', $token, [
            'expires'  => time() + (REMEMBER_DAYS * 86400),
            'path'     => '/',
            'secure'   => !APP_DEBUG,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function loginViaRememberToken(): bool
    {
        if (empty($_COOKIE['remember_token'])) {
            return false;
        }

        $hashed = hash('sha256', $_COOKIE['remember_token']);
        $user   = Database::fetchOne(
            "SELECT * FROM users WHERE remember_token = ? AND is_active = 1 LIMIT 1",
            [$hashed]
        );

        if (!$user) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_avatar']= $user['avatar'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return true;
    }

    // ── Logout ───────────────────────────────────────────────

    public static function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            AuditLog::write($_SESSION['user_id'], 'logout', 'auth', 'User logged out');
            // Clear remember token
            Database::update('users', ['remember_token' => null], 'id = ?', [$_SESSION['user_id']]);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(SESSION_NAME, '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        // Clear remember cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // ── Checks ───────────────────────────────────────────────

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return Database::fetchOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$_SESSION['user_id']]);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function is(string $role): bool
    {
        return ($_SESSION['user_role'] ?? '') === $role;
    }

    public static function hasAny(array $roles): bool
    {
        return in_array($_SESSION['user_role'] ?? '', $roles, true);
    }

    // ── Require auth (redirect if not logged in) ─────────────

    public static function requireLogin(string $redirect = ''): void
    {
        if (!self::check()) {
            if (!self::loginViaRememberToken()) {
                $redirect = $redirect ?: BASE_URL . '/modules/auth/login.php';
                header("Location: {$redirect}");
                exit;
            }
        }
    }

    // ── Require specific role ─────────────────────────────────

    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();
        $roles = (array) $roles;
        if (!self::hasAny($roles)) {
            http_response_code(403);
            include ROOT_PATH . '/includes/403.php';
            exit;
        }
    }

    // ── CSRF ─────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die(json_encode(['error' => 'CSRF token mismatch.']));
        }
    }

    // ── Password reset helpers ───────────────────────────────

    public static function createPasswordResetToken(string $email): ?string
    {
        $user = Database::fetchOne("SELECT id FROM users WHERE email = ? AND is_active = 1", [$email]);
        if (!$user) {
            return null;
        }

        // Invalidate old tokens
        Database::query("DELETE FROM password_resets WHERE email = ?", [$email]);

        $token = bin2hex(random_bytes(32));
        Database::insert('password_resets', [
            'email'      => $email,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        return $token;
    }

    public static function validateResetToken(string $token): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM password_resets
             WHERE token = ? AND expires_at > NOW() AND used = 0
             LIMIT 1",
            [$token]
        );
    }

    public static function resetPassword(string $token, string $newPassword): bool
    {
        $record = self::validateResetToken($token);
        if (!$record) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        Database::update('users', ['password' => $hash, 'remember_token' => null], 'email = ?', [$record['email']]);
        Database::update('password_resets', ['used' => 1], 'token = ?', [$token]);

        return true;
    }
}
