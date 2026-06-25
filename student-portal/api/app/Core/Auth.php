<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reuses the EXACT same `api_tokens` table and SHA-256-hashed Bearer token
 * pattern as the Admin panel's existing App\Controllers\Api\AuthController —
 * not a new auth mechanism, the same one already proven in this codebase.
 * Password hashing (Argon2ID) also matches the Admin panel's App\Core\Auth so
 * both apps verify against the identical `users.password_hash` column.
 */
class Auth
{
    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password): array|false
    {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT u.*, r.slug as role_slug FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1",
            [strtolower(trim($email))]
        );

        if (! $user || ! static::verify($password, $user['password_hash'])) {
            return false;
        }

        return $user;
    }

    public static function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        Database::getInstance()->execute(
            'INSERT INTO api_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())',
            [$userId, hash('sha256', $token)]
        );
        return $token;
    }

    public static function revokeToken(string $token): void
    {
        Database::getInstance()->execute('DELETE FROM api_tokens WHERE token = ?', [hash('sha256', $token)]);
    }

    public static function userFromToken(?string $token = null): ?array
    {
        if (static::$cachedUser !== null) {
            return static::$cachedUser;
        }

        $token ??= (new Request())->bearerToken();
        if (! $token) {
            return null;
        }

        $row = Database::getInstance()->fetchOne(
            "SELECT u.*, r.slug as role_slug FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE t.token = ? AND t.expires_at > NOW() AND u.deleted_at IS NULL",
            [hash('sha256', $token)]
        );

        if (! $row) {
            return null;
        }

        // Strip every sensitive/secret field, not just the password hash —
        // a leaked password_reset_token or email_verification_token from this
        // endpoint would be just as much an account-takeover vector as the hash.
        unset(
            $row['password_hash'],
            $row['two_factor_secret'],
            $row['remember_token'],
            $row['password_reset_token'],
            $row['email_verification_token'],
        );
        static::$cachedUser = $row;
        return $row;
    }

    /**
     * docs/student-module/04a §"Auth & Account" — both `/auth/login` and
     * `/auth/me` document `linked_students` in the response when the caller
     * is a parent, each carrying that specific link's visibility booleans
     * (split-custody parents can have different permissions per child).
     */
    public static function linkedStudents(int $parentId): array
    {
        return Database::getInstance()->select(
            'SELECT u.id AS student_id, u.first_name, u.last_name,
                    l.can_view_billing, l.can_view_recordings, l.can_view_attendance, l.can_book_ptm
             FROM parent_student_links l
             JOIN users u ON u.id = l.student_id
             WHERE l.parent_id = ? AND u.deleted_at IS NULL',
            [$parentId]
        );
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
