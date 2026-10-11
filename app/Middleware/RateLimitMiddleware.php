<?php
/**
 * SEAIT E-Learning — Rate Limit Middleware
 *
 * Simple file-based rate limiter to prevent brute-force attacks
 * on login, registration, and password reset endpoints.
 */
class RateLimitMiddleware
{
    private static function getStorageDir(): string
    {
        return (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR;
    }

    /**
     * Check rate limit for the current IP on a named action.
     *
     * @param string $action     Identifier, e.g. 'login', 'register'
     * @param int    $maxHits    Max allowed requests in the window
     * @param int    $windowSec  Window size in seconds
     * @return bool  true = allowed, false = rate limited
     */
    public static function check(string $action, int $maxHits = 10, int $windowSec = 60): bool
    {
        $ip  = self::getClientIp();
        $key = $action . '_' . md5($ip);
        $dir = self::getStorageDir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . $key . '.json';
        $now  = time();
        $data = ['hits' => [], 'blocked_until' => 0];

        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            if ($raw) $data = json_decode($raw, true) ?? $data;
        }

        // If blocked, check if block has expired
        if ($data['blocked_until'] > $now) {
            return false;
        }

        // Remove hits outside the window
        $data['hits'] = array_filter($data['hits'], fn($t) => $t > $now - $windowSec);

        if (count($data['hits']) >= $maxHits) {
            // Block for double the window
            $data['blocked_until'] = $now + ($windowSec * 2);
            @file_put_contents($file, json_encode($data));
            return false;
        }

        // Record this hit
        $data['hits'][] = $now;
        @file_put_contents($file, json_encode($data));
        return true;
    }

    /**
     * Reset rate limit for an action + IP (e.g. on successful login).
     */
    public static function reset(string $action): void
    {
        $ip   = self::getClientIp();
        $key  = $action . '_' . md5($ip);
        $file = self::getStorageDir() . $key . '.json';
        if (file_exists($file)) @unlink($file);
    }

    private static function getClientIp(): string
    {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }
}
