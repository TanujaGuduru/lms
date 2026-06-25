<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class UserController
{
    public function index(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db     = Database::getInstance();
        $page   = max(1, (int)$request->get('page', 1));
        $limit  = min(100, max(1, (int)$request->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $search = trim($request->get('search', ''));
        $role   = $request->get('role', '');

        $where  = ['u.deleted_at IS NULL'];
        $params = [];

        if ($search) {
            $where[]  = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $like     = "%{$search}%";
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($role) {
            $where[]  = 'r.slug = ?';
            $params[] = $role;
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);
        $total    = (int)$db->fetchOne("SELECT COUNT(*) as cnt FROM users u LEFT JOIN roles r ON r.id = u.role_id $whereStr", $params)['cnt'];

        $users = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.avatar,
                    r.name as role_name, r.slug as role_slug, u.created_at, u.last_login_at
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             $whereStr ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );

        $this->json([
            'success' => true,
            'data'    => $users,
            'meta'    => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => (int)ceil($total / $limit),
            ],
        ]);
    }

    public function show(Request $request, int $id): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db   = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT u.*, r.name as role_name, r.slug as role_slug
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL",
            [$id]
        );

        if (!$user) { $this->json(['success'=>false,'message'=>'User not found'], 404); return; }

        unset($user['password_hash']);
        $this->json(['success'=>true,'data'=>$user]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
