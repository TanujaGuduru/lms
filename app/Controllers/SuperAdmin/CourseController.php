<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Models\Course;

class CourseController extends Controller
{
    private Course $course;

    public function __construct()
    {
        parent::__construct();
        $this->course = new Course();
    }

    public function index(Request $request): void
    {
        $this->authorize('courses.view');

        $filters = $request->only(['search','status','level','department_id']);
        $page    = max(1, (int)$request->input('page', 1));
        $result  = $this->course->search($filters, $page, 18);
        $stats   = $this->course->getStats();
        $depts   = $this->db->select("SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name");

        $this->render('super-admin.courses.index', [
            'title'   => 'Courses',
            'courses' => $result['data'],
            'meta'    => $result,
            'stats'   => $stats,
            'depts'   => $depts,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('courses.create');
        $depts    = $this->db->select("SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name");
        $teachers = $this->db->select("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='teacher' AND u.status='active' ORDER BY u.first_name");
        $this->render('super-admin.courses.create', [
            'title'    => 'New Course',
            'depts'    => $depts,
            'teachers' => $teachers,
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('courses.create');

        $data = $this->validate($request, [
            'title'         => 'required|min:3|max:200',
            'description'   => 'required|min:20',
            'level'         => 'required|in:beginner,intermediate,advanced',
            'language'      => 'max:50',
            'price'         => 'numeric|min_val:0',
            'department_id' => 'integer',
            'instructor_id' => 'integer',
            'max_students'  => 'integer|min_val:1',
        ]);

        // Handle thumbnail upload
        if (!empty($_FILES['thumbnail']['tmp_name']) && is_uploaded_file($_FILES['thumbnail']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                $filename = 'course_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/courses/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
                    $data['thumbnail'] = '/uploads/courses/' . $filename;
                }
            }
        }

        $data['created_by']  = $this->currentUser()['id'];
        $data['status']      = $request->input('status', 'draft');
        $data['is_featured'] = $request->has('is_featured') ? 1 : 0;

        $id = $this->course->createCourse($data);

        AuditLogger::log('course_created', 'courses', (string)$id, null, ['title' => $data['title']]);

        $this->withFlash('success', "Course \"{$data['title']}\" created successfully.")
             ->redirect('/super-admin/courses/' . $id . '/edit');
    }

    public function edit(Request $request, int $id): void
    {
        $this->authorize('courses.update');

        $course   = $this->course->getWithDetails($id);
        if (!$course) {
            $this->withFlash('error', 'Course not found.')->redirect('/super-admin/courses');
        }

        $depts    = $this->db->select("SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name");
        $teachers = $this->db->select("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='teacher' AND u.status='active' ORDER BY u.first_name");
        $modules  = $this->db->select("SELECT * FROM course_modules WHERE course_id = ? AND deleted_at IS NULL ORDER BY sort_order", [$id]);

        $this->render('super-admin.courses.edit', [
            'title'    => 'Edit Course',
            'course'   => $course,
            'depts'    => $depts,
            'teachers' => $teachers,
            'modules'  => $modules,
        ]);
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('courses.update');

        $course = $this->course->find($id);
        if (!$course) $this->withFlash('error', 'Course not found.')->back();

        $data = $this->validate($request, [
            'title'         => 'required|min:3|max:200',
            'description'   => 'required|min:20',
            'level'         => 'required|in:beginner,intermediate,advanced',
            'price'         => 'numeric|min_val:0',
            'department_id' => 'integer',
            'instructor_id' => 'integer',
        ]);

        if (!empty($_FILES['thumbnail']['tmp_name']) && is_uploaded_file($_FILES['thumbnail']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                $filename = 'course_' . time() . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/courses/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
                    $data['thumbnail'] = '/uploads/courses/' . $filename;
                }
            }
        }

        $data['is_featured'] = $request->has('is_featured') ? 1 : 0;
        $data['status']      = $request->input('status', $course['status']);
        $this->course->update($id, $data);

        AuditLogger::log('course_updated', 'courses', (string)$id, $course, $data);

        $this->withFlash('success', 'Course updated successfully.')->redirect('/super-admin/courses/' . $id . '/edit');
    }

    public function publish(Request $request, int $id): never
    {
        $this->authorize('courses.update');

        $course = $this->course->find($id);
        if (!$course) $this->error('Course not found.', 404);

        $this->course->update($id, ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('course_published', 'courses', (string)$id);

        $this->success(null, "Course published successfully.");
    }

    public function delete(Request $request, int $id): void
    {
        $this->authorize('courses.delete');

        $course = $this->course->find($id);
        if (!$course) $this->withFlash('error', 'Course not found.')->back();

        $enrollCount = $this->db->selectOne("SELECT COUNT(*) c FROM enrollments WHERE course_id = ?", [$id])['c'] ?? 0;
        if ($enrollCount > 0) {
            if ((new Request())->isAjax()) {
                $this->error("Cannot delete: {$enrollCount} student(s) enrolled.");
            }
            $this->withFlash('error', "Cannot delete: {$enrollCount} student(s) enrolled.")->back();
        }

        $this->course->delete($id);
        AuditLogger::log('course_deleted', 'courses', (string)$id, $course);

        if ((new Request())->isAjax()) {
            $this->success(null, 'Course deleted.');
        }
        $this->withFlash('success', 'Course deleted.')->redirect('/super-admin/courses');
    }
}
