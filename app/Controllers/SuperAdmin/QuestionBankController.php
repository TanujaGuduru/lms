<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class QuestionBankController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('questions.view');

        $where  = ['q.deleted_at IS NULL'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "q.question_text LIKE ?";
            $params[] = "%{$s}%";
        }
        if ($type = $request->input('type')) {
            $where[] = "q.type = ?"; $params[] = $type;
        }
        if ($diff = $request->input('difficulty')) {
            $where[] = "q.difficulty = ?"; $params[] = $diff;
        }
        if ($cat = $request->input('category_id')) {
            $where[] = "q.category_id = ?"; $params[] = (int)$cat;
        }
        if ($status = $request->input('status')) {
            $where[] = "q.status = ?"; $params[] = $status;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT q.*, qc.name category_name, CONCAT(u.first_name,' ',u.last_name) author_name
             FROM questions q
             LEFT JOIN question_categories qc ON qc.id = q.category_id
             LEFT JOIN users u ON u.id = q.created_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY q.created_at DESC",
            $params, $page, 25
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(type='mcq') mcq, SUM(type='msq') msq, SUM(type='true_false') tf,
             SUM(type='short_answer') sa, SUM(type='coding') coding,
             SUM(status='approved') approved, SUM(status='pending_review') pending
             FROM questions WHERE deleted_at IS NULL"
        ) ?: [];

        $categories = $this->db->select("SELECT * FROM question_categories ORDER BY name");

        $this->render('super-admin.question-bank.index', [
            'title'      => 'Question Bank',
            'questions'  => $result['data'],
            'meta'       => $result,
            'stats'      => $stats,
            'categories' => $categories,
            'filters'    => $request->only(['search','type','difficulty','category_id','status']),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('questions.create');
        $categories = $this->db->select("SELECT * FROM question_categories ORDER BY name");
        $this->render('super-admin.question-bank.create', [
            'title'      => 'New Question',
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize('questions.create');

        $data = $this->validate($request, [
            'text'        => 'required|min:5',
            'type'        => 'required|in:mcq,msq,true_false,short_answer,coding',
            'difficulty'  => 'required|in:easy,medium,hard',
            'category_id' => 'required|integer',
            'marks'       => 'required|numeric|min_val:0.5',
        ]);

        $options      = $request->input('options', []);
        $correctAnswer = $request->input('correct_answer', '');
        $explanation  = $request->input('explanation', '');
        $tags         = $request->input('tags', '');

        $id = $this->db->insert(
            "INSERT INTO questions (question_text,type,difficulty,category_id,marks,options,correct_answer,explanation,tags,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $data['text'], $data['type'], $data['difficulty'], $data['category_id'],
                $data['marks'],
                is_array($options) ? json_encode($options) : $options,
                is_array($correctAnswer) ? json_encode($correctAnswer) : $correctAnswer,
                $explanation, $tags,
                'pending_review',
                $this->currentUser()['id'],
            ]
        );

        AuditLogger::log('question_created', 'questions', (string)$id);
        $this->withFlash('success', 'Question added to bank.')
             ->redirect('/super-admin/question-bank');
    }

    public function edit(Request $request, int $id): void
    {
        $this->authorize('questions.update');
        $q = $this->db->selectOne("SELECT *, question_text AS text FROM questions WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$q) $this->withFlash('error', 'Question not found.')->redirect('/super-admin/question-bank');
        $categories = $this->db->select("SELECT * FROM question_categories ORDER BY name");
        $this->render('super-admin.question-bank.edit', [
            'title'      => 'Edit Question',
            'question'   => $q,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, int $id): void
    {
        $this->authorize('questions.update');
        $q = $this->db->selectOne("SELECT * FROM questions WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$q) $this->withFlash('error', 'Question not found.')->back();

        $data = $this->validate($request, [
            'text'       => 'required|min:5',
            'difficulty' => 'required|in:easy,medium,hard',
            'marks'      => 'required|numeric|min_val:0.5',
        ]);

        $options = $request->input('options', []);
        $correct = $request->input('correct_answer', '');

        $this->db->query(
            "UPDATE questions SET question_text=?,difficulty=?,marks=?,options=?,correct_answer=?,explanation=?,updated_at=NOW() WHERE id=?",
            [$data['text'],$data['difficulty'],$data['marks'],
             is_array($options)?json_encode($options):$options,
             is_array($correct)?json_encode($correct):$correct,
             $request->input('explanation',''), $id]
        );

        AuditLogger::log('question_updated', 'questions', (string)$id);
        $this->withFlash('success', 'Question updated.')->redirect('/super-admin/question-bank');
    }

    public function approve(Request $request, int $id): never
    {
        $this->authorize('questions.update');
        $this->db->query("UPDATE questions SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
            [$this->currentUser()['id'], $id]);
        AuditLogger::log('question_approved', 'questions', (string)$id);
        $this->success(null, 'Question approved.');
    }

    public function destroy(Request $request, int $id): never
    {
        $this->authorize('questions.delete');
        $this->db->query("UPDATE questions SET deleted_at=NOW() WHERE id=?", [$id]);
        AuditLogger::log('question_deleted', 'questions', (string)$id);
        $this->success(null, 'Question deleted.');
    }

    public function import(Request $request): void
    {
        $this->authorize('questions.create');

        if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $this->withFlash('error', 'No file uploaded.')->back();
        }

        $handle  = fopen($_FILES['csv_file']['tmp_name'], 'r');
        fgetcsv($handle); // skip header
        $ok = $err = 0;
        $userId = $this->currentUser()['id'];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) { $err++; continue; }
            [$text, $type, $diff, $marks, $catName] = $row;

            $cat = $this->db->selectOne("SELECT id FROM question_categories WHERE name = ?", [trim($catName)]);
            $catId = $cat['id'] ?? 1;

            $this->db->insert(
                "INSERT INTO questions (question_text,type,difficulty,marks,category_id,status,created_by,created_at) VALUES (?,?,?,?,?,'pending_review',?,NOW())",
                [trim($text), trim($type), trim($diff), (float)$marks, $catId, $userId]
            );
            $ok++;
        }
        fclose($handle);

        AuditLogger::log('questions_imported', 'questions', null, null, ['ok' => $ok, 'err' => $err]);
        $this->withFlash('success', "{$ok} questions imported, {$err} skipped.")->redirect('/super-admin/question-bank');
    }

    public function export(Request $request): void
    {
        $this->authorize('questions.view');

        $questions = $this->db->select(
            "SELECT q.question_text, q.type, q.difficulty, q.marks, qc.name category, q.correct_answer, q.status
             FROM questions q LEFT JOIN question_categories qc ON qc.id=q.category_id
             WHERE q.deleted_at IS NULL ORDER BY q.created_at DESC"
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="question-bank-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Text','Type','Difficulty','Marks','Category','Correct Answer','Status']);
        foreach ($questions as $q) fputcsv($out, array_values($q));
        fclose($out);
        exit;
    }

    public function storeBulk(Request $request): never
    {
        $this->authorize('questions.create');

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $questions = $body['questions'] ?? [];

        if (empty($questions) || !is_array($questions)) {
            $this->error('No questions provided.');
        }

        $userId = $this->currentUser()['id'];
        $ok     = 0;

        foreach ($questions as $q) {
            if (empty($q['text'])) continue;

            $options = $q['options'] ?? [];
            $correct = $q['correct_answer'] ?? ($options[0] ?? '');

            $this->db->insert(
                "INSERT INTO questions (question_text,type,difficulty,marks,options,correct_answer,explanation,status,created_by,created_at)
                 VALUES (?,?,?,?,?,?,?,'pending_review',?,NOW())",
                [
                    $q['text'],
                    $q['type'] ?? 'mcq',
                    $q['difficulty'] ?? 'medium',
                    $q['marks'] ?? 1,
                    is_array($options) ? json_encode($options) : $options,
                    is_array($correct) ? json_encode($correct) : $correct,
                    $q['explanation'] ?? '',
                    $userId,
                ]
            );
            $ok++;
        }

        AuditLogger::log('questions_bulk_imported', 'questions', null, null, ['count' => $ok]);
        $this->success(null, "{$ok} questions saved to bank.");
    }
}
