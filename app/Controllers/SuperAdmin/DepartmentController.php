<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class DepartmentController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('departments.view');

        $departments = $this->db->select(
            "SELECT d.*, COUNT(DISTINCT c.id) course_count,
             COUNT(DISTINCT c.instructor_id) teacher_count
             FROM departments d
             LEFT JOIN courses c ON c.department_id=d.id AND c.deleted_at IS NULL
             WHERE d.deleted_at IS NULL
             GROUP BY d.id ORDER BY d.name"
        );

        $this->render('super-admin.departments.index', [
            'title'       => 'Departments',
            'departments' => $departments,
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('departments.create');

        $data = $this->validate($request, [
            'name' => 'required|min:2|max:150',
            'code' => 'max:20',
        ]);

        $data['created_by'] = $this->currentUser()['id'];

        $id = $this->db->insert(
            "INSERT INTO departments (name,code,description,created_by,created_at) VALUES (?,?,?,?,NOW())",
            [$data['name'], $data['code']??'', $request->input('description',''), $data['created_by']]
        );

        AuditLogger::log('department_created', 'departments', (string)$id, null, $data);

        if ((new Request())->isAjax()) {
            $this->success(['id' => $id, 'name' => $data['name']], 'Department created.');
        }
        $this->withFlash('success', "Department \"{$data['name']}\" created.")->redirect('/super-admin/departments');
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('departments.update');

        $dept = $this->db->selectOne("SELECT * FROM departments WHERE id=? AND deleted_at IS NULL", [$id]);
        if (!$dept) $this->withFlash('error', 'Not found.')->back();

        $data = $this->validate($request, [
            'name' => 'required|min:2|max:150',
        ]);

        $this->db->query(
            "UPDATE departments SET name=?,description=?,updated_at=NOW() WHERE id=?",
            [$data['name'], $request->input('description',''), $id]
        );

        AuditLogger::log('department_updated', 'departments', (string)$id, $dept, $data);

        if ((new Request())->isAjax()) $this->success(null, 'Department updated.');
        $this->withFlash('success', 'Department updated.')->redirect('/super-admin/departments');
    }

    public function destroy(Request $request, int $id): void
    {
        $this->authorize('departments.delete');

        $dept = $this->db->selectOne("SELECT * FROM departments WHERE id=? AND deleted_at IS NULL", [$id]);
        if (!$dept) $this->withFlash('error', 'Not found.')->back();

        $courseCount = $this->db->selectOne("SELECT COUNT(*) c FROM courses WHERE department_id=? AND deleted_at IS NULL", [$id])['c'] ?? 0;
        if ($courseCount > 0) {
            if ((new Request())->isAjax()) $this->error("Cannot delete: {$courseCount} course(s) assigned.");
            $this->withFlash('error', "Cannot delete: {$courseCount} course(s) in this department.")->back();
        }

        $this->db->query("UPDATE departments SET deleted_at=NOW() WHERE id=?", [$id]);
        AuditLogger::log('department_deleted', 'departments', (string)$id, $dept);

        if ((new Request())->isAjax()) $this->success(null, 'Department deleted.');
        $this->withFlash('success', 'Department deleted.')->redirect('/super-admin/departments');
    }
}
