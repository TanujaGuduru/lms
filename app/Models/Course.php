<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Course extends Model
{
    protected string $table      = 'courses';
    protected bool   $softDeletes = true;

    protected array $fillable = [
        'uuid', 'department_id', 'instructor_id', 'created_by', 'title', 'slug', 'description',
        'short_description', 'thumbnail', 'preview_video', 'level', 'language',
        'duration_hours', 'total_lessons', 'max_students', 'is_free', 'price',
        'discount_price', 'currency', 'certificate_template_id', 'passing_percentage',
        'status', 'is_featured', 'meta_title', 'meta_description', 'tags',
        'requirements', 'outcomes', 'certificate_enabled', 'discussion_enabled', 'drip_content',
    ];

    public function createCourse(array $data): int|string
    {
        $data['uuid'] = $this->generateUuid();
        $data['slug'] = $this->generateSlug($data['title']);
        return $this->create($data);
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        $base = $slug;
        $i    = 1;

        while ($this->exists('slug', $slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where  = ['c.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[]  = '(c.title LIKE ? OR c.description LIKE ?)';
            $params   = array_merge($params, [$s, $s]);
        }
        if (!empty($filters['status'])) {
            $where[]  = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['level'])) {
            $where[]  = 'c.level = ?';
            $params[] = $filters['level'];
        }
        if (!empty($filters['department_id'])) {
            $where[]  = 'c.department_id = ?';
            $params[] = $filters['department_id'];
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT c.id, c.uuid, c.title, c.slug, c.thumbnail, c.level, c.status,
                       c.price, c.is_free, c.enrolled_count, c.rating_avg, c.rating_count,
                       c.created_at, c.published_at,
                       d.name as department_name,
                       u.first_name, u.last_name
                FROM courses c
                LEFT JOIN departments d ON d.id = c.department_id
                LEFT JOIN users u ON u.id = c.created_by
                WHERE {$whereStr}
                ORDER BY c.created_at DESC";

        return $this->db->paginate($sql, $params, $page, $perPage);
    }

    public function getStats(): array
    {
        return $this->db->selectOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_courses,
                SUM(enrolled_count) as total_enrollments
             FROM courses WHERE deleted_at IS NULL"
        ) ?: [];
    }

    public function getWithDetails(int $id): array|false
    {
        return $this->db->selectOne(
            "SELECT c.*, d.name as department_name,
                    u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM course_modules WHERE course_id = c.id) as module_count,
                    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'active') as active_enrollments
             FROM courses c
             LEFT JOIN departments d ON d.id = c.department_id
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = ? AND c.deleted_at IS NULL",
            [$id]
        );
    }
}
