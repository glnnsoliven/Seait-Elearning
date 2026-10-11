<?php
/**
 * SEAIT E-Learning — Audit Log Helper
 */

class AuditLog
{
    /**
     * Write an entry to the audit_logs table.
     *
     * @param int|null $userId   The acting user's ID (null for anonymous)
     * @param string   $action   Short action identifier, e.g. 'login', 'create_course'
     * @param string   $module   Module name, e.g. 'auth', 'courses'
     * @param string   $desc     Human-readable description
     */
    public static function write(?int $userId, string $action, string $module, string $desc = ''): void
    {
        try {
            Database::insert('audit_logs', [
                'user_id'    => $userId,
                'action'     => $action,
                'module'     => $module,
                'description'=> $desc,
                'ip_address' => self::clientIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            ]);
        } catch (Throwable) {
            // Never let audit logging crash the app
        }
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }
}
