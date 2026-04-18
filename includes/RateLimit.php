<?php
/**
 * File-based rate limiter for brute-force protection.
 * State stored in temp files keyed by action + identifier (e.g. IP).
 */
class RateLimit {

    /**
     * Record an attempt and return true if within limit, false if exceeded.
     *
     * @param string $action     Logical action name, e.g. 'login'
     * @param string $identifier Per-client identifier, e.g. client IP
     * @param int    $max        Max allowed attempts in the window
     * @param int    $window     Window size in seconds
     */
    public static function attempt(string $action, string $identifier, int $max, int $window): bool {
        $file = self::filePath($action, $identifier);
        $now  = time();

        $attempts = self::load($file);
        $attempts = array_values(array_filter($attempts, fn(int $t) => $t > $now - $window));

        if (count($attempts) >= $max) {
            return false;
        }

        $attempts[] = $now;
        self::save($file, $attempts);
        return true;
    }

    /**
     * Clear the rate-limit record on a successful action (e.g. successful login).
     */
    public static function clear(string $action, string $identifier): void {
        $file = self::filePath($action, $identifier);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Return seconds until the oldest attempt expires, or 0 if not limited.
     */
    public static function retryAfter(string $action, string $identifier, int $window): int {
        $file     = self::filePath($action, $identifier);
        $now      = time();
        $attempts = self::load($file);
        $oldest   = $attempts[0] ?? null;
        if ($oldest === null) return 0;
        return max(0, ($oldest + $window) - $now);
    }

    private static function filePath(string $action, string $identifier): string {
        return sys_get_temp_dir() . '/rl_' . md5($action . ':' . $identifier) . '.json';
    }

    private static function load(string $file): array {
        if (!file_exists($file)) return [];
        $data = @file_get_contents($file);
        return is_string($data) ? (json_decode($data, true) ?? []) : [];
    }

    private static function save(string $file, array $attempts): void {
        file_put_contents($file, json_encode($attempts), LOCK_EX);
    }
}
