<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Auth;

class User extends Model
{
    protected string $table      = 'users';
    protected bool   $softDeletes = true;

    protected array $fillable = [
        'uuid', 'role_id', 'first_name', 'last_name', 'email', 'phone',
        'password_hash', 'avatar', 'gender', 'date_of_birth', 'address',
        'city', 'state', 'country', 'pincode', 'bio', 'status',
        'timezone', 'language', 'notification_preferences',
        'two_factor_enabled', 'email_verified_at', 'phone_verified_at',
    ];

    protected array $hidden = ['password_hash', 'two_factor_secret', 'remember_token', 'password_reset_token', 'email_verification_token'];

    public function createUser(array $data): int|string
    {
        $data['uuid']          = $this->generateUuid();
        $data['password_hash'] = Auth::hash($data['password']);
        unset($data['password'], $data['password_confirmation']);

        $userId = $this->create($data);

        if (isset($data['role_id'])) {
            $this->db->insertInto('user_roles', [
                'user_id'  => $userId,
                'role_id'  => $data['role_id'],
                'assigned_by' => Auth::id(),
            ]);
        }

        return $userId;
    }

    public function getWithRole(int $id): array|false
    {
        return $this->db->selectOne(
            "SELECT u.*, r.name as role_name, r.slug as role_slug, r.color as role_color
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL",
            [$id]
        );
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where  = ['u.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $params   = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($filters['role_id'])) {
            $where[]  = 'u.role_id = ?';
            $params[] = $filters['role_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'u.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'DATE(u.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'DATE(u.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereStr = implode(' AND ', $where);
        $sql      = "SELECT u.id, u.uuid, u.first_name, u.last_name, u.email, u.phone,
                            u.avatar, u.status, u.created_at, u.last_login_at,
                            r.name as role_name, r.slug as role_slug, r.color as role_color
                     FROM users u LEFT JOIN roles r ON r.id = u.role_id
                     WHERE {$whereStr}
                     ORDER BY u.created_at DESC";

        return $this->db->paginate($sql, $params, $page, $perPage);
    }

    public function getStats(): array
    {
        $stats = $this->db->selectOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as students,
                SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as teachers,
                SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as this_month
             FROM users WHERE deleted_at IS NULL"
        );
        return $stats ?: [];
    }

    public function getRecentUsers(int $limit = 10): array
    {
        return $this->db->select(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar, u.status, u.created_at, u.last_login_at, r.name as role_name, r.color as role_color
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function getMonthlyGrowth(int $months = 12): array
    {
        return $this->db->select(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
             FROM users
             WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month ORDER BY month ASC",
            [$months]
        );
    }

    public function fullName(array $user): string
    {
        return trim($user['first_name'] . ' ' . $user['last_name']);
    }

    public function avatarUrl(array $user): string
    {
        if (!empty($user['avatar'])) {
            return BASE_URL . $user['avatar'];
        }
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        return "https://ui-avatars.com/api/?name={$initials}&background=6366f1&color=fff&size=80&bold=true&format=svg";
    }
}
