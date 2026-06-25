<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (static::$started || session_status() === PHP_SESSION_ACTIVE) {
            static::$started = true;
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }

        $timeout = (int)(Setting::get('security.session_timeout', 120) ?? 120);
        ini_set('session.gc_maxlifetime', (string)($timeout * 60));
        session_set_cookie_params(['lifetime' => $timeout * 60, 'httponly' => true, 'samesite' => 'Lax']);
        session_name('CG_LMS_SESSION');
        session_start();
        static::$started = true;

        static::regenerateIfNeeded();
    }

    private static function regenerateIfNeeded(): void
    {
        if (!isset($_SESSION['_last_regen'])) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        } elseif (time() - $_SESSION['_last_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        static::$started = false;
    }

    public static function csrfToken(): string
    {
        if (!static::has('_csrf_token')) {
            static::set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return static::get('_csrf_token');
    }

    public static function verifyCsrf(string $token): bool
    {
        return hash_equals(static::csrfToken(), $token);
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . static::csrfToken() . '">';
    }
}
