<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $db   = Database::getInstance();
        $user = $db->selectOne(
            "SELECT u.*, r.slug as role_slug FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1",
            [strtolower(trim($email))]
        );

        if (!$user) {
            return false;
        }

        if ($user['status'] === 'suspended') {
            Session::flash('error', 'Your account has been suspended. Contact support.');
            return false;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $wait = ceil((strtotime($user['locked_until']) - time()) / 60);
            Session::flash('error', "Account locked. Try again in {$wait} minutes.");
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $lockUntil = null;
            $maxAttempts = (int)Setting::get('security.max_login_attempts', 5);

            if ($attempts >= $maxAttempts) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $attempts  = 0;
            }

            $db->updateTable('users', [
                'failed_login_attempts' => $attempts,
                'locked_until'          => $lockUntil,
            ], 'id = ?', [$user['id']]);

            AuditLogger::log('login_failed', 'auth', $user['id'], null, null, "Failed login for {$email}");
            return false;
        }

        $now    = date('Y-m-d H:i:s');
        $ip     = (new Request())->ip();
        $token  = bin2hex(random_bytes(32));

        $db->updateTable('users', [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => $now,
            'last_login_ip'         => $ip,
            'login_count'           => $user['login_count'] + 1,
        ], 'id = ?', [$user['id']]);

        $db->insertInto('user_sessions', [
            'user_id'       => $user['id'],
            'session_token' => $token,
            'ip_address'    => $ip,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at'    => $now,
            'expires_at'    => $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);

        Session::set('user_id', $user['id']);
        Session::set('session_token', $token);

        if ($remember) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('remember_token', $token, time() + 2592000, '/', '', $isHttps, true);
        }

        AuditLogger::log('login', 'auth', $user['id'], null, null, "Successful login");
        return true;
    }

    public static function check(): bool
    {
        return static::user() !== null;
    }

    public static function user(): ?array
    {
        if (static::$user !== null) {
            return static::$user;
        }

        $userId = Session::get('user_id');
        if (!$userId) {
            $userId = static::checkRememberToken();
        }

        if (!$userId) {
            return null;
        }

        $db   = Database::getInstance();
        $user = $db->selectOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name, r.hierarchy_level
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.status IN ('active','pending') AND u.deleted_at IS NULL",
            [$userId]
        );

        if (!$user) {
            static::logout();
            return null;
        }

        unset($user['password_hash'], $user['two_factor_secret'], $user['remember_token']);
        static::$user = $user;
        return static::$user;
    }

    private static function checkRememberToken(): ?int
    {
        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) return null;

        $db = Database::getInstance();
        $session = $db->selectOne(
            "SELECT user_id FROM user_sessions WHERE session_token = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())",
            [$token]
        );

        if ($session) {
            Session::set('user_id', $session['user_id']);
            Session::set('session_token', $token);
            return $session['user_id'];
        }

        return null;
    }

    public static function logout(): void
    {
        $token = Session::get('session_token');
        if ($token) {
            $db = Database::getInstance();
            $db->updateTable('user_sessions', ['is_active' => 0], 'session_token = ?', [$token]);
        }

        Session::destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        static::$user = null;
        static::$permissions = null;
    }

    public static function clearCache(): void
    {
        static::$user        = null;
        static::$permissions = null;
    }

    public static function id(): ?int
    {
        return static::user()['id'] ?? null;
    }

    public static function role(): ?string
    {
        return static::user()['role_slug'] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        return static::role() === 'super_admin';
    }

    public static function can(string $permission): bool
    {
        if (static::isSuperAdmin()) return true;

        $userId = static::id();
        if (!$userId) return false;

        if (static::$permissions === null) {
            static::loadPermissions($userId);
        }

        return in_array($permission, static::$permissions ?? []);
    }

    private static function loadPermissions(int $userId): void
    {
        $db   = Database::getInstance();
        $user = static::user();

        $rolePerms = $db->select(
            "SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?",
            [$user['role_id']]
        );

        $userGrants = $db->select(
            "SELECT p.slug, up.type FROM permissions p
             JOIN user_permissions up ON up.permission_id = p.id
             WHERE up.user_id = ?",
            [$userId]
        );

        $permissions = array_column($rolePerms, 'slug');

        foreach ($userGrants as $grant) {
            if ($grant['type'] === 'grant') {
                $permissions[] = $grant['slug'];
            } elseif ($grant['type'] === 'deny') {
                $permissions = array_diff($permissions, [$grant['slug']]);
            }
        }

        static::$permissions = array_unique($permissions);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
