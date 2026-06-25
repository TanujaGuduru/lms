<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class CourseController
{
    public function index(Request $request): void
    {
        $db     = Database::getInstance();
        $page   = max(1, (int)$request->get('page', 1));
        $limit  = min(100, max(1, (int)$request->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $search = trim($request->get('search', ''));
        $status = $request->get('status', '');

        $where  = ['c.deleted_at IS NULL'];
        $params = [];

        if ($search) {
            $where[]  = 'c.title LIKE ?';
            $params[] = "%{$search}%";
        }
        if ($status) {
            $where[]  = 'c.status = ?';
            $params[] = $status;
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);
        $total    = (int)$db->fetchOne("SELECT COUNT(*) as cnt FROM courses c $whereStr", $params)['cnt'];

        $courses = $db->fetchAll(
            "SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.status,
                    c.level, c.duration_hours as duration, c.price, c.is_free,
                    CONCAT(u.first_name,' ',u.last_name) as instructor_name,
                    dept.name as category_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as enrollment_count,
                    c.created_at
             FROM courses c
             LEFT JOIN users u ON u.id = c.instructor_id
             LEFT JOIN departments dept ON dept.id = c.department_id
             $whereStr ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );

        $this->json([
            'success' => true,
            'data'    => $courses,
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
        $db     = Database::getInstance();
        $course = $db->fetchOne(
            "SELECT c.*, CONCAT(u.first_name,' ',u.last_name) as instructor_name,
                    dept.name as category_name
             FROM courses c
             LEFT JOIN users u ON u.id = c.instructor_id
             LEFT JOIN departments dept ON dept.id = c.department_id
             WHERE c.id = ? AND c.deleted_at IS NULL",
            [$id]
        );

        if (!$course) { $this->json(['success'=>false,'message'=>'Course not found'], 404); return; }

        $modules = $db->fetchAll(
            "SELECT m.*, (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id AND l.deleted_at IS NULL) as lessons_count
             FROM course_modules m WHERE m.course_id = ? ORDER BY m.sort_order",
            [$id]
        );

        $course['modules'] = $modules;
        $this->json(['success'=>true,'data'=>$course]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
