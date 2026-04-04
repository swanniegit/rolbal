<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file into $_ENV and getenv()
 */

class Env {
    private static bool $loaded = false;

    public static function load(string $path = null): void {
        if (self::$loaded) {
            return;
        }

        $path = $path ?? dirname(__DIR__) . '/.env';

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse KEY=value
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Set in environment
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function required(string $key): string {
        $value = self::get($key);
        if ($value === null || $value === false) {
            throw new RuntimeException("Missing required environment variable: $key");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool {
        $value = self::get($key);
        if ($value === null || $value === false) {
            return $default;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
}
