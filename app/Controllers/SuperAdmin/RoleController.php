<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class RoleController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('roles.view');
        $this->render('super-admin.roles.index', ['title' => 'Roles & Permissions']);
    }

    public function create(Request $request): void
    {
        $this->authorize('roles.create');

        $data = $this->validate($request, [
            'name'        => 'required|min:2|max:80',
            'slug'        => 'required|regex:/^[a-z_]+$/|unique:roles,slug',
            'description' => 'max:255',
        ]);

        $this->db->insert(
            "INSERT INTO roles (name, slug, description, is_system, created_at) VALUES (?,?,?,0,NOW())",
            [$data['name'], $data['slug'], $data['description'] ?? '']
        );

        AuditLogger::log('role_created', 'roles', null, null, $data);

        $this->withFlash('success', "Role \"{$data['name']}\" created.")
             ->redirect('/super-admin/roles');
    }

    public function savePermissions(Request $request): never
    {
        $this->authorize('roles.update');

        $perms = $request->input('permissions', []);

        if (!is_array($perms)) {
            $this->error('Invalid permissions payload.');
        }

        $this->db->transaction(function () use ($perms) {
            foreach ($perms as $item) {
                $roleId = (int)($item['role_id'] ?? 0);
                $permId = (int)($item['perm_id'] ?? 0);
                $allowed = !empty($item['allowed']);

                if (!$roleId || !$permId) continue;

                // Check if role is system-protected super_admin
                $role = $this->db->selectOne("SELECT slug FROM roles WHERE id = ?", [$roleId]);
                if ($role && $role['slug'] === 'super_admin') continue;

                // Upsert (row presence = granted; no is_allowed column exists)
                $existing = $this->db->selectOne(
                    "SELECT role_id FROM role_permissions WHERE role_id=? AND permission_id=?",
                    [$roleId, $permId]
                );

                if ($existing && !$allowed) {
                    $this->db->query(
                        "DELETE FROM role_permissions WHERE role_id=? AND permission_id=?",
                        [$roleId, $permId]
                    );
                } elseif (!$existing && $allowed) {
                    $this->db->insert(
                        "INSERT INTO role_permissions (role_id,permission_id,granted_at) VALUES (?,?,NOW())",
                        [$roleId, $permId]
                    );
                }
            }
        });

        AuditLogger::log('permissions_updated', 'roles');
        $this->success(null, 'Permissions saved successfully.');
    }

    public function clone(Request $request, int $id): never
    {
        $this->authorize('roles.create');

        $source = $this->db->selectOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$source) $this->error('Role not found.', 404);

        $name = trim($request->input('name', $source['name'] . ' Copy'));
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($name));

        // Ensure unique slug
        $base = $slug;
        $n    = 2;
        while ($this->db->selectOne("SELECT id FROM roles WHERE slug = ?", [$slug])) {
            $slug = $base . '_' . $n++;
        }

        $newId = $this->db->insert(
            "INSERT INTO roles (name, slug, description, is_system, created_at) VALUES (?,?,?,0,NOW())",
            [$name, $slug, "Cloned from: {$source['name']}"]
        );

        // Copy permissions
        $this->db->query(
            "INSERT INTO role_permissions (role_id, permission_id, granted_at)
             SELECT ?, permission_id, NOW() FROM role_permissions WHERE role_id = ?",
            [$newId, $id]
        );

        AuditLogger::log('role_cloned', 'roles', (string)$newId, ['source_id' => $id]);
        $this->success(['id' => $newId, 'slug' => $slug], "Role cloned as \"{$name}\".");
    }

    public function delete(Request $request, int $id): void
    {
        $this->authorize('roles.delete');

        $role = $this->db->selectOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            $this->withFlash('error', 'Role not found.')->back();
        }

        if ($role['is_system']) {
            $this->withFlash('error', 'System roles cannot be deleted.')->back();
        }

        $userCount = $this->db->selectOne(
            "SELECT COUNT(*) c FROM users WHERE role_id = ?", [$id]
        )['c'] ?? 0;

        if ($userCount > 0) {
            $this->withFlash('error', "Cannot delete: {$userCount} user(s) have this role.")->back();
        }

        $this->db->query("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
        $this->db->query("DELETE FROM roles WHERE id = ?", [$id]);

        AuditLogger::log('role_deleted', 'roles', (string)$id, $role);
        $this->withFlash('success', "Role \"{$role['name']}\" deleted.")->redirect('/super-admin/roles');
    }
}
