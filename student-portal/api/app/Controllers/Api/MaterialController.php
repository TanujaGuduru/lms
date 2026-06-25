<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Request;

/**
 * Materials — docs/student-module/04c-apis-classroom-content.md.
 * Files live on local disk (storage/app/materials/...), not S3 — this
 * platform runs entirely on GoDaddy shared hosting, by deliberate choice.
 */
class MaterialController extends Controller
{
    public function courseMaterials(Request $request, string $courseId): void
    {
        $studentId = (int) $this->currentUser()['id'];

        if (! $this->isEnrolled($studentId, (int) $courseId)) {
            $this->fail('No such course.', ['reason' => ['not_found']], 404);
        }

        $rows = $this->db->select(
            'SELECT id, title, file_type, is_downloadable, current_version FROM course_materials WHERE course_id = ?',
            [$courseId]
        );

        $this->success($rows);
    }

    public function download(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $material = $this->accessibleMaterial($studentId, $id);

        if (! $material['is_downloadable']) {
            $this->fail('This material is not downloadable.', ['reason' => ['download_disabled']], 403);
        }

        // The request itself is the access event support needs visibility
        // into, logged regardless of whether the resulting URL is ever
        // actually used (04c's explicit reasoning).
        $this->db->insertInto('material_downloads', [
            'material_id' => $material['id'],
            'student_id' => $studentId,
        ]);

        $this->success([
            'url' => FileStorage::signedUrl($this->relativePathFor($material['file_url']), 300),
            'expires_in' => 300,
        ]);
    }

    public function versions(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $material = $this->accessibleMaterial($studentId, $id);

        $rows = $this->db->select(
            'SELECT version_number, changelog, created_at FROM material_versions WHERE material_id = ? ORDER BY version_number DESC',
            [$material['id']]
        );

        $this->success($rows);
    }

    private function isEnrolled(int $studentId, int $courseId): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?', [$studentId, $courseId]);
    }

    private function accessibleMaterial(int $studentId, string $materialId): array
    {
        $material = $this->db->fetchOne(
            'SELECT cm.* FROM course_materials cm
             JOIN enrollments e ON e.course_id = cm.course_id AND e.user_id = ?
             WHERE cm.id = ?',
            [$studentId, $materialId]
        );

        if (! $material) {
            $this->fail('No such material.', ['reason' => ['not_found']], 404);
        }

        return $material;
    }

    /**
     * `course_materials.file_url` is expected to hold a path relative to
     * storage/app/ (e.g. "materials/42/cheatsheet.pdf") going forward — the
     * Admin panel's upload flow is what's responsible for actually placing
     * the file there and writing that relative path into this column.
     */
    private function relativePathFor(string $fileUrl): string
    {
        return ltrim((string) parse_url($fileUrl, PHP_URL_PATH), '/') ?: $fileUrl;
    }
}
