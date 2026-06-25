<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class BatchController
{
    public function index(Request $request): void
    {
        if (!Auth::check()) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db      = Database::getInstance();
        $page    = max(1, (int)$request->get('page', 1));
        $limit   = min(100, max(1, (int)$request->get('limit', 20)));
        $offset  = ($page - 1) * $limit;

        $total = (int)$db->fetchOne("SELECT COUNT(*) as cnt FROM batches WHERE deleted_at IS NULL")['cnt'];

        $batches = $db->fetchAll(
            "SELECT b.id, b.name, b.code, b.status, b.start_date, b.end_date, b.max_students,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) as student_count,
                    c.title as course_title, b.created_at
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE b.deleted_at IS NULL
             ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset"
        );

        $this->json([
            'success' => true,
            'data'    => $batches,
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

        $db    = Database::getInstance();
        $batch = $db->fetchOne("SELECT * FROM batches WHERE id = ? AND deleted_at IS NULL", [$id]);

        if (!$batch) { $this->json(['success'=>false,'message'=>'Batch not found'], 404); return; }

        $students = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email, bs.joined_at
             FROM batch_students bs
             JOIN users u ON u.id = bs.student_id
             WHERE bs.batch_id = ?",
            [$id]
        );

        $batch['students'] = $students;
        $this->json(['success'=>true,'data'=>$batch]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
