<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class BatchController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('batches.view');

        $where  = ['b.deleted_at IS NULL'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[]  = "(b.name LIKE ? OR b.code LIKE ?)";
            $params   = array_merge($params, ["%{$s}%", "%{$s}%"]);
        }
        if ($status = $request->input('status')) {
            $where[] = "b.status = ?"; $params[] = $status;
        }
        if ($mode = $request->input('mode')) {
            $where[] = "b.mode = ?"; $params[] = $mode;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT b.*, c.title course_title, COUNT(DISTINCT bs.id) student_count
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             LEFT JOIN batch_students bs ON bs.batch_id = b.id AND bs.status='active'
             WHERE " . implode(' AND ', $where) . "
             GROUP BY b.id ORDER BY b.created_at DESC",
            $params, $page, 20
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(status='active') active,
             SUM(status='upcoming') upcoming,
             SUM(status='completed') completed,
             SUM(mode='online') online
             FROM batches WHERE deleted_at IS NULL"
        ) ?: [];

        $courses = $this->db->select("SELECT id, title FROM courses WHERE status='published' AND deleted_at IS NULL ORDER BY title");

        $this->render('super-admin.batches.index', [
            'title'   => 'Batches',
            'batches' => $result['data'],
            'meta'    => $result,
            'stats'   => $stats,
            'courses' => $courses,
            'filters' => $request->only(['search','status','mode']),
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('batches.create');

        $data = $this->validate($request, [
            'name'         => 'required|min:2|max:150',
            'code'         => 'required|max:20',
            'course_id'    => 'required|integer',
            'mode'         => 'required|in:online,offline,hybrid',
            'max_students' => 'required|integer|min_val:1',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
        ]);

        $data['created_by'] = $this->currentUser()['id'];
        $data['status']     = 'upcoming';

        $id = $this->db->insert(
            "INSERT INTO batches (name,code,course_id,mode,max_students,start_date,end_date,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())",
            [$data['name'],$data['code'],$data['course_id'],$data['mode'],$data['max_students'],$data['start_date'],$data['end_date'],$data['status'],$data['created_by']]
        );

        AuditLogger::log('batch_created', 'batches', (string)$id, null, $data);
        $this->withFlash('success', "Batch \"{$data['name']}\" created.")->redirect('/super-admin/batches');
    }

    public function delete(Request $request, int $id): void
    {
        $this->authorize('batches.delete');

        $batch = $this->db->selectOne("SELECT * FROM batches WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$batch) $this->withFlash('error', 'Batch not found.')->back();

        $this->db->query("UPDATE batches SET deleted_at=NOW() WHERE id=?", [$id]);
        AuditLogger::log('batch_deleted', 'batches', (string)$id, $batch);

        if ((new Request())->isAjax()) $this->success(null, 'Batch deleted.');
        $this->withFlash('success', 'Batch deleted.')->redirect('/super-admin/batches');
    }

    public function addStudent(Request $request, int $id): never
    {
        $this->authorize('batches.update');

        $data = $this->validate($request, ['user_id' => 'required|integer']);

        $batch = $this->db->selectOne("SELECT * FROM batches WHERE id = ?", [$id]);
        if (!$batch) $this->error('Batch not found.', 404);

        $count = $this->db->selectOne("SELECT COUNT(*) c FROM batch_students WHERE batch_id=? AND status='active'", [$id])['c'] ?? 0;
        if ($count >= $batch['max_students']) {
            $this->error('Batch is at maximum capacity.');
        }

        $exists = $this->db->selectOne("SELECT id FROM batch_students WHERE batch_id=? AND student_id=?", [$id, $data['user_id']]);
        if ($exists) $this->error('Student already in this batch.');

        $this->db->insert("INSERT INTO batch_students (batch_id,student_id,status,enrolled_at) VALUES (?,?,'active',NOW())", [$id, $data['user_id']]);
        AuditLogger::log('batch_student_added', 'batches', (string)$id, null, $data);

        NotificationService::send(
            (int)$data['user_id'],
            'batch.enrolled',
            'Enrolled in a Batch',
            "You have been added to the batch \"{$batch['name']}\".",
            ['batch_id' => $id]
        );

        $this->success(null, 'Student added to batch.');
    }
}
