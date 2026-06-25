<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class ExamController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('exams.view');

        $where  = ['e.deleted_at IS NULL'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "e.title LIKE ?"; $params[] = "%{$s}%";
        }
        if ($status = $request->input('status')) {
            $where[] = "e.status = ?"; $params[] = $status;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT e.*, e.duration_minutes duration, e.start_datetime starts_at, e.end_datetime ends_at,
             COUNT(DISTINCT ea.id) attempt_count, AVG(ea.obtained_marks) avg_score,
             CONCAT(u.first_name,' ',u.last_name) creator_name
             FROM exams e
             LEFT JOIN exam_attempts ea ON ea.exam_id = e.id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE " . implode(' AND ', $where) . "
             GROUP BY e.id ORDER BY e.created_at DESC",
            $params, $page, 20
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(status='published') published, SUM(status='draft') draft,
             SUM(status='archived') archived
             FROM exams WHERE deleted_at IS NULL"
        ) ?: [];

        $this->render('super-admin.exams.index', [
            'title'   => 'Exams',
            'exams'   => $result['data'],
            'meta'    => $result,
            'stats'   => $stats,
            'filters' => $request->only(['search','status']),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('exams.create');
        $courses    = $this->db->select("SELECT id, title FROM courses WHERE status='published' AND deleted_at IS NULL ORDER BY title");
        $categories = $this->db->select("SELECT * FROM question_categories ORDER BY name");
        $questions  = $this->db->select(
            "SELECT q.id, q.question_text text, q.type, q.difficulty, q.marks FROM questions q
             WHERE q.status='approved' AND q.deleted_at IS NULL ORDER BY q.type, q.difficulty"
        );
        $this->render('super-admin.exams.create', [
            'title'      => 'New Exam',
            'courses'    => $courses,
            'categories' => $categories,
            'questions'  => $questions,
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('exams.create');

        $data = $this->validate($request, [
            'title'            => 'required|min:3|max:200',
            'duration_minutes' => 'required|integer|min_val:5',
            'pass_mark'        => 'required|numeric|min_val:1|max_val:100',
            'max_attempts'     => 'integer|min_val:1',
            'start_date'       => 'date',
            'end_date'         => 'date',
        ]);

        $questionIds = array_filter(array_map('intval', explode(',', (string)$request->input('question_ids', ''))));

        $totalMarks = 0.0;
        if (!empty($questionIds)) {
            $in         = implode(',', array_fill(0, count($questionIds), '?'));
            $totalMarks = (float)($this->db->selectOne("SELECT SUM(marks) s FROM questions WHERE id IN ({$in})", $questionIds)['s'] ?? 0);
        }
        $passingMarks = round($totalMarks * (float)$data['pass_mark'] / 100, 2);

        $courseId  = $request->input('course_id') ? (int)$request->input('course_id') : null;
        $status    = $request->input('exam_status') === 'published' ? 'published' : 'draft';
        $createdBy = $this->currentUser()['id'];

        $id = $this->db->insert(
            "INSERT INTO exams (title,course_id,instructions,duration_minutes,total_marks,passing_marks,max_attempts,start_datetime,end_datetime,
             shuffle_questions,shuffle_options,show_result_immediately,status,created_by,published_at,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $data['title'], $courseId, $request->input('instructions', ''), $data['duration_minutes'],
                $totalMarks, $passingMarks, $data['max_attempts'] ?? 1,
                $request->input('start_date') ?: null, $request->input('end_date') ?: null,
                $request->has('shuffle_questions') ? 1 : 0,
                $request->has('shuffle_options') ? 1 : 0,
                $request->has('show_results') ? 1 : 0,
                $status, $createdBy, $status === 'published' ? date('Y-m-d H:i:s') : null,
            ]
        );

        foreach ($questionIds as $pos => $qId) {
            $this->db->insert(
                "INSERT INTO exam_questions (exam_id,question_id,sort_order) VALUES (?,?,?)",
                [$id, $qId, $pos + 1]
            );
        }

        AuditLogger::log('exam_created', 'exams', (string)$id, null, ['title' => $data['title']]);
        $this->withFlash('success', "Exam \"{$data['title']}\" created. Now add questions.")
             ->redirect("/super-admin/exams/{$id}/edit");
    }

    public function show(Request $request, int $id): void
    {
        $this->authorize('exams.view');

        $exam = $this->db->selectOne("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$exam) $this->withFlash('error', 'Exam not found.')->redirect('/super-admin/exams');

        $questions = $this->db->select(
            "SELECT eq.*, q.question_text text, q.type, q.marks, q.difficulty, qc.name category_name
             FROM exam_questions eq
             JOIN questions q ON q.id = eq.question_id
             LEFT JOIN question_categories qc ON qc.id = q.category_id
             WHERE eq.exam_id = ? ORDER BY eq.sort_order",
            [$id]
        );

        $attempts = $this->db->select(
            "SELECT ea.*, ea.percentage score, CONCAT(u.first_name,' ',u.last_name) student_name
             FROM exam_attempts ea JOIN users u ON u.id=ea.user_id
             WHERE ea.exam_id = ? ORDER BY ea.started_at DESC LIMIT 20",
            [$id]
        );

        $this->render('super-admin.exams.show', [
            'title'     => $exam['title'],
            'exam'      => $exam,
            'questions' => $questions,
            'attempts'  => $attempts,
        ]);
    }

    public function edit(Request $request, int $id): void
    {
        $this->authorize('exams.update');

        $exam = $this->db->selectOne(
            "SELECT *, start_datetime start_date, end_datetime end_date,
             ROUND(passing_marks / NULLIF(total_marks,0) * 100) pass_mark,
             show_result_immediately show_results
             FROM exams WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
        if (!$exam) $this->withFlash('error', 'Exam not found.')->redirect('/super-admin/exams');

        $examQuestions = $this->db->select(
            "SELECT eq.*, q.question_text text, q.type, q.marks, q.difficulty FROM exam_questions eq
             JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=? ORDER BY eq.sort_order",
            [$id]
        );

        $availableQuestions = $this->db->select(
            "SELECT q.*, q.question_text text, qc.name category_name FROM questions q
             LEFT JOIN question_categories qc ON qc.id=q.category_id
             WHERE q.status='approved' AND q.deleted_at IS NULL
             AND q.id NOT IN (SELECT question_id FROM exam_questions WHERE exam_id=?)
             ORDER BY q.difficulty, q.type",
            [$id]
        );

        $this->render('super-admin.exams.edit', [
            'title'         => 'Edit Exam: ' . $exam['title'],
            'exam'          => $exam,
            'examQuestions' => $examQuestions,
            'allQuestions'  => array_merge($examQuestions, $availableQuestions),
        ]);
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('exams.update');
        $exam = $this->db->selectOne("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$exam) $this->withFlash('error', 'Not found.')->back();

        $data = $this->validate($request, [
            'title'            => 'required|min:3|max:200',
            'duration_minutes' => 'required|integer|min_val:5',
            'pass_mark'        => 'required|numeric|min_val:1|max_val:100',
        ]);

        // Sync questions first (if provided) so total marks reflect the latest set
        $questionIds = array_filter(array_map('intval', explode(',', (string)$request->input('question_ids', ''))));
        if (!empty($questionIds)) {
            $this->db->query("DELETE FROM exam_questions WHERE exam_id=?", [$id]);
            foreach ($questionIds as $pos => $qId) {
                $this->db->insert(
                    "INSERT INTO exam_questions (exam_id,question_id,sort_order,marks_override) SELECT ?,?,?,marks FROM questions WHERE id=?",
                    [$id, $qId, $pos + 1, $qId]
                );
            }
        }

        $totalMarks = (float)($this->db->selectOne(
            "SELECT COALESCE(SUM(COALESCE(eq.marks_override, q.marks)),0) s FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=?",
            [$id]
        )['s'] ?? 0);
        $passingMarks = round($totalMarks * (float)$data['pass_mark'] / 100, 2);

        $this->db->query(
            "UPDATE exams SET title=?,instructions=?,duration_minutes=?,total_marks=?,passing_marks=?,max_attempts=?,
             start_datetime=?,end_datetime=?,shuffle_questions=?,shuffle_options=?,show_result_immediately=?,status=?,updated_at=NOW() WHERE id=?",
            [
                $data['title'], $request->input('instructions', ''), $data['duration_minutes'], $totalMarks, $passingMarks,
                $request->input('max_attempts', 1),
                $request->input('start_date') ?: null, $request->input('end_date') ?: null,
                $request->has('shuffle_questions') ? 1 : 0,
                $request->has('shuffle_options') ? 1 : 0,
                $request->has('show_results') ? 1 : 0,
                in_array($request->input('exam_status'), ['draft','published','archived'], true) ? $request->input('exam_status') : $exam['status'],
                $id,
            ]
        );

        AuditLogger::log('exam_updated', 'exams', (string)$id);
        $this->withFlash('success', 'Exam updated.')->redirect("/super-admin/exams/{$id}/edit");
    }

    public function publish(Request $request, int $id): never
    {
        $this->authorize('exams.update');

        $exam          = $this->db->selectOne("SELECT * FROM exams WHERE id=? AND deleted_at IS NULL", [$id]);
        $questionCount = $this->db->selectOne("SELECT COUNT(*) c FROM exam_questions WHERE exam_id=?", [$id])['c'] ?? 0;
        if ($questionCount < 1) {
            $this->error('Cannot publish: add at least one question first.');
        }

        $this->db->query("UPDATE exams SET status='published', published_at=NOW() WHERE id=?", [$id]);
        AuditLogger::log('exam_published', 'exams', (string)$id);

        // Notify students enrolled in the linked course, if set
        if (!empty($exam['course_id'])) {
            $students = $this->db->select(
                "SELECT DISTINCT e.user_id FROM enrollments e
                 WHERE e.course_id = ? AND e.status = 'active'",
                [$exam['course_id']]
            );
            NotificationService::broadcast(
                array_column($students, 'user_id'),
                'exam.published',
                'New Exam Available',
                "A new exam \"{$exam['title']}\" is now available for your course.",
                ['exam_id' => $id]
            );
        }

        $this->success(null, 'Exam published.');
    }

    public function results(Request $request, int $id): void
    {
        $this->authorize('exams.view');

        $exam = $this->db->selectOne(
            "SELECT *, ROUND(passing_marks / NULLIF(total_marks,0) * 100) pass_mark FROM exams WHERE id=?",
            [$id]
        );
        $results = $this->db->select(
            "SELECT ea.*, ea.percentage score, ea.obtained_marks marks_obtained, ea.time_taken_seconds time_taken,
             CONCAT(u.first_name,' ',u.last_name) student_name, u.email
             FROM exam_attempts ea JOIN users u ON u.id=ea.user_id
             WHERE ea.exam_id=? ORDER BY ea.percentage DESC",
            [$id]
        );

        $this->render('super-admin.exams.results', [
            'title'   => 'Exam Results: ' . ($exam['title'] ?? ''),
            'exam'    => $exam,
            'results' => $results,
        ]);
    }
}
