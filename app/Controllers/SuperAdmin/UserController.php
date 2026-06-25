<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Models\User;

class UserController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function index(Request $request): void
    {
        $this->authorize('users.view');

        $filters = $request->only(['search', 'role_id', 'status', 'date_from', 'date_to']);
        $page    = max(1, (int)$request->input('page', 1));
        $result  = $this->userModel->search($filters, $page);

        $roles = $this->db->select("SELECT id, name, slug, color FROM roles ORDER BY hierarchy_level");
        $stats = $this->userModel->getStats();

        $this->render('super-admin.users.index', [
            'title'   => 'User Management',
            'users'   => $result,
            'roles'   => $roles,
            'stats'   => $stats,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('users.create');

        $roles = $this->db->select("SELECT id, name, slug FROM roles WHERE is_active = 1 ORDER BY hierarchy_level");

        $this->render('super-admin.users.create', [
            'title' => 'Add New User',
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('users.create');

        $data = $this->validate($request, [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|phone',
            'role_id'    => 'required|integer',
            'password'   => 'required|password_strength|confirmed',
            'status'     => 'required|in:active,inactive,pending',
        ]);

        $userId = $this->userModel->createUser($data);
        AuditLogger::log('user_created', 'users', (int)$userId, null, ['email' => $data['email']]);

        if ($request->isAjax()) {
            $this->success(['id' => $userId], 'User created successfully.');
        }

        $this->withFlash('success', 'User created successfully!')->redirect('/super-admin/users/' . $userId);
    }

    public function show(Request $request, string $id): void
    {
        $this->authorize('users.view');

        $user = $this->userModel->getWithRole((int)$id);
        if (!$user) {
            $this->withFlash('error', 'User not found.')->redirect('/super-admin/users');
        }

        $enrollments = $this->db->select(
            "SELECT e.*, c.title as course_title, c.thumbnail FROM enrollments e
             JOIN courses c ON c.id = e.course_id WHERE e.user_id = ? ORDER BY e.enrolled_at DESC LIMIT 10",
            [$id]
        );

        $recentActivity = $this->db->select(
            "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$id]
        );

        $sessions = $this->db->select(
            "SELECT * FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
            [$id]
        );

        $this->render('super-admin.users.show', [
            'title'          => 'User Profile — ' . $user['first_name'],
            'user'           => $user,
            'enrollments'    => $enrollments,
            'recentActivity' => $recentActivity,
            'sessions'       => $sessions,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $this->authorize('users.edit');

        $user  = $this->userModel->getWithRole((int)$id);
        if (!$user) {
            $this->withFlash('error', 'User not found.')->redirect('/super-admin/users');
        }

        $roles = $this->db->select("SELECT id, name, slug FROM roles WHERE is_active = 1 ORDER BY hierarchy_level");

        $this->render('super-admin.users.edit', [
            'title' => 'Edit User — ' . $user['first_name'],
            'user'  => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $this->authorize('users.edit');

        $user = $this->userModel->find((int)$id);
        if (!$user) $this->error('User not found.', 404);

        $data = $this->validate($request, [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'email'      => 'required|email',
            'phone'      => 'nullable|phone',
            'role_id'    => 'required|integer',
            'status'     => 'required|in:active,inactive,pending,suspended',
            'bio'        => 'nullable|max:1000',
            'city'       => 'nullable|max:80',
            'state'      => 'nullable|max:80',
            'country'    => 'nullable|max:80',
        ]);

        $old = $user;
        $this->userModel->update((int)$id, $data);
        AuditLogger::log('user_updated', 'users', (int)$id, $old, $data);

        if ($request->isAjax()) {
            $this->success(null, 'User updated successfully.');
        }
        $this->withFlash('success', 'User updated successfully!')->redirect('/super-admin/users/' . $id);
    }

    public function destroy(Request $request, string $id): void
    {
        $this->authorize('users.delete');

        $user = $this->userModel->find((int)$id);
        if (!$user) $this->error('User not found.', 404);

        if ((int)$id === Auth::id()) $this->error('You cannot delete your own account.', 400);

        $this->userModel->delete((int)$id);
        AuditLogger::log('user_deleted', 'users', (int)$id, $user);

        if ($request->isAjax()) {
            $this->success(null, 'User deleted successfully.');
        }
        $this->withFlash('success', 'User deleted.')->redirect('/super-admin/users');
    }

    public function toggleStatus(Request $request, string $id): void
    {
        $this->authorize('users.edit');

        $user = $this->userModel->find((int)$id);
        if (!$user) $this->error('User not found.', 404);

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->userModel->update((int)$id, ['status' => $newStatus]);
        AuditLogger::log('user_status_changed', 'users', (int)$id, ['status' => $user['status']], ['status' => $newStatus]);

        $this->success(['status' => $newStatus], 'Status updated successfully.');
    }

    public function bulkAction(Request $request): void
    {
        $this->authorize('users.manage');

        $action  = $request->input('action');
        $userIds = (array)$request->input('user_ids', []);

        if (empty($userIds)) $this->error('No users selected.');

        $count = 0;
        foreach ($userIds as $userId) {
            if ((int)$userId === Auth::id()) continue;
            match($action) {
                'activate'   => $this->userModel->update((int)$userId, ['status' => 'active']) && $count++,
                'deactivate' => $this->userModel->update((int)$userId, ['status' => 'inactive']) && $count++,
                'delete'     => $this->userModel->delete((int)$userId) && $count++,
                default      => null,
            };
        }

        AuditLogger::log('bulk_user_action', 'users', null, null, ['action' => $action, 'count' => $count]);
        $this->success(['count' => $count], "{$count} users updated successfully.");
    }

    public function activityHistory(Request $request, string $id): void
    {
        $this->authorize('users.view');
        $logs = $this->db->select(
            "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
            [$id]
        );
        $this->success($logs);
    }

    public function sessions(Request $request, string $id): void
    {
        $this->authorize('users.view');
        $sessions = $this->db->select(
            "SELECT * FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$id]
        );
        $this->success($sessions);
    }

    public function export(Request $request): void
    {
        $this->authorize('users.view');

        $filters = $request->only(['role_id', 'status']);
        $where   = ['deleted_at IS NULL'];
        $params  = [];

        if (!empty($filters['role_id'])) { $where[] = 'role_id = ?'; $params[] = $filters['role_id']; }
        if (!empty($filters['status']))  { $where[] = 'status = ?';  $params[] = $filters['status'];  }

        $users = $this->db->select(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
                    u.created_at, u.last_login_at, r.name as role
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             WHERE " . implode(' AND ', $where) . " ORDER BY u.created_at DESC",
            $params
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_' . date('Ymd_His') . '.csv"');

        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Created', 'Last Login']);
        foreach ($users as $u) {
            fputcsv($fp, [$u['id'], $u['first_name'], $u['last_name'], $u['email'], $u['phone'] ?? '',
                $u['role'], $u['status'], $u['created_at'], $u['last_login_at'] ?? '']);
        }
        fclose($fp);
        exit;
    }

    public function import(Request $request): void
    {
        $this->authorize('users.create');
        $file = $request->file('file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->error('Please upload a valid CSV file.');
        }

        $handle  = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        $created = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $rowData = array_combine($headers, $row);
                $this->userModel->createUser([
                    'first_name'  => $rowData['first_name'] ?? '',
                    'last_name'   => $rowData['last_name']  ?? '',
                    'email'       => $rowData['email']      ?? '',
                    'phone'       => $rowData['phone']      ?? null,
                    'role_id'     => 4,
                    'password'    => 'Change@123',
                    'status'      => 'active',
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$created}: " . $e->getMessage();
            }
        }
        fclose($handle);

        $this->success(['created' => $created, 'errors' => $errors], "{$created} users imported successfully.");
    }
}
