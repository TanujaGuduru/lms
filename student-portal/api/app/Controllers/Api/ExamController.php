<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Assessments (Exams) — docs/student-module/04e-apis-assessments-projects.md.
 * Two adaptations for the no-cloud / no-live-execution decisions:
 *  - **No `POST /attempts/{id}/proctor-token` at all.** The original design
 *    reused Agora's token shape for proctored exams; with no Agora (or any
 *    video service) anywhere in this build, there's no video proctoring.
 *    `POST /attempts/{id}/cheating-flag` (blur/visibility events, already
 *    below) is the only proctoring signal that exists. A live-monitored
 *    option could later reuse ClassroomController's P2P mesh the same way
 *    a tiny classroom would, but that's a real feature to design on
 *    purpose later, not something to fake here.
 *  - **`type='coding'` questions are never auto-graded by execution** —
 *    there is no sandbox in this build (confirmed: "no coding live should
 *    be there"). They're routed into the same manual-grading queue as
 *    `short_answer`/`long_answer` (`marks_awarded` left NULL, distinct from
 *    a real, graded zero, until a teacher grades them).
 */
class ExamController extends Controller
{
    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $courseId = $request->input('course_id');

        $exams = $this->db->select(
            "SELECT e.id, e.title, e.type, e.duration_minutes, e.total_marks, e.max_attempts,
                    e.start_datetime, e.end_datetime,
                    (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id AND ea.user_id = ?) AS attempts_used
             FROM exams e
             JOIN enrollments en ON en.course_id = e.course_id AND en.user_id = ?
             WHERE e.type != 'placement' AND e.status IN ('published', 'active')
               AND (? IS NULL OR e.course_id = ?)
             ORDER BY e.start_datetime",
            [$studentId, $studentId, $courseId, $courseId]
        );

        $this->success(array_map(fn (array $e) => [
            'id' => (int) $e['id'],
            'title' => $e['title'],
            'type' => $e['type'],
            'duration_minutes' => (int) $e['duration_minutes'],
            'total_marks' => (float) $e['total_marks'],
            'attempts_used' => (int) $e['attempts_used'],
            'max_attempts' => (int) $e['max_attempts'],
            'start_datetime' => $e['start_datetime'],
            'end_datetime' => $e['end_datetime'],
        ], $exams));
    }

    public function show(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $exam = $this->visibleExam($id, $studentId);
        $attemptsUsed = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM exam_attempts WHERE exam_id = ? AND user_id = ?',
            [$id, $studentId]
        )['c'];

        $this->success([
            'id' => (int) $exam['id'],
            'title' => $exam['title'],
            'description' => $exam['description'],
            'type' => $exam['type'],
            'duration_minutes' => (int) $exam['duration_minutes'],
            'total_marks' => (float) $exam['total_marks'],
            'passing_marks' => (float) $exam['passing_marks'],
            'attempts_used' => $attemptsUsed,
            'max_attempts' => (int) $exam['max_attempts'],
            'start_datetime' => $exam['start_datetime'],
            'end_datetime' => $exam['end_datetime'],
            'instructions' => $exam['instructions'],
        ]);
    }

    public function startAttempt(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $exam = $this->visibleExam($id, $studentId);

        $existing = $this->db->fetchOne(
            "SELECT * FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1",
            [$id, $studentId]
        );
        if ($existing) {
            $this->respondAttempt($existing, $exam);
        }

        $now = time();
        if (($exam['start_datetime'] && strtotime($exam['start_datetime']) > $now)
            || ($exam['end_datetime'] && strtotime($exam['end_datetime']) < $now)) {
            $this->fail('This exam is not open right now.', ['reason' => ['outside_window']], 403);
        }

        $attemptsUsed = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM exam_attempts WHERE exam_id = ? AND user_id = ?',
            [$id, $studentId]
        )['c'];
        if ($attemptsUsed >= (int) $exam['max_attempts']) {
            $this->fail('No attempts remaining for this exam.', ['reason' => ['max_attempts_reached']], 409);
        }

        $attemptId = $this->db->insertInto('exam_attempts', [
            'exam_id' => $id,
            'user_id' => $studentId,
            'attempt_number' => $attemptsUsed + 1,
            'total_marks' => $exam['total_marks'],
        ]);

        $attempt = $this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ?', [$attemptId]);
        $this->respondAttempt($attempt, $exam);
    }

    public function attemptDetail(Request $request, string $attemptId): void
    {
        $attempt = $this->autoFinalizeIfExpired($this->ownAttempt($attemptId));
        $exam = $this->db->fetchOne('SELECT * FROM exams WHERE id = ?', [$attempt['exam_id']]);
        $this->respondAttempt($attempt, $exam, includeResponses: true);
    }

    public function autosaveResponse(Request $request, string $attemptId, string $questionId): void
    {
        $attempt = $this->autoFinalizeIfExpired($this->ownAttempt($attemptId));
        if ($attempt['status'] !== 'in_progress') {
            $this->fail('This attempt is no longer in progress.', ['reason' => ['attempt_closed']], 422);
        }

        $response = $request->input('response');
        $timeSpent = (int) $request->input('time_spent_seconds', 0);

        $existing = $this->db->fetchOne(
            'SELECT id FROM exam_responses WHERE attempt_id = ? AND question_id = ?',
            [$attemptId, $questionId]
        );

        if ($existing) {
            $this->db->updateTable('exam_responses', [
                'response' => json_encode($response),
                'time_spent_seconds' => $timeSpent,
            ], 'id = ?', [$existing['id']]);
        } else {
            // marks_awarded/is_correct must start NULL, not the column's
            // DEFAULT 0 — gradeAndFinalize() leaves both untouched for
            // short_answer/long_answer/coding (manual grading), and a
            // silent 0 would be indistinguishable from a real, graded zero.
            $this->db->insertInto('exam_responses', [
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
                'response' => json_encode($response),
                'time_spent_seconds' => $timeSpent,
                'marks_awarded' => null,
                'is_correct' => null,
            ]);
        }

        $this->success(true);
    }

    public function cheatingFlag(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);
        $flags = json_decode($attempt['cheating_flags'] ?? '[]', true) ?: [];
        $flags[] = [
            'type' => (string) $request->input('type', 'unknown'),
            'at' => date('c'),
        ];

        $this->db->updateTable('exam_attempts', ['cheating_flags' => json_encode($flags)], 'id = ?', [$attemptId]);
        $this->success(true);
    }

    public function submit(Request $request, string $attemptId): void
    {
        $attempt = $this->autoFinalizeIfExpired($this->ownAttempt($attemptId));

        if ($attempt['status'] !== 'in_progress') {
            // Already finalized (by this call, a retry, or expiry) — idempotent.
            $this->respondResult($this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ?', [$attemptId]));
        }

        $this->gradeAndFinalize((int) $attemptId, 'completed');
        $this->respondResult($this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ?', [$attemptId]));
    }

    public function result(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);
        if ($attempt['status'] === 'in_progress') {
            $this->fail('This attempt has not been submitted yet.', ['reason' => ['not_submitted']], 422);
        }
        $this->respondResult($attempt);
    }

    private function respondResult(array $attempt): void
    {
        $exam = $this->db->fetchOne('SELECT show_result_immediately FROM exams WHERE id = ?', [$attempt['exam_id']]);
        $pendingGrading = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM exam_responses WHERE attempt_id = ? AND marks_awarded IS NULL',
            [$attempt['id']]
        )['c'];

        $resultVisible = (bool) $exam['show_result_immediately'];

        $data = [
            'status' => $attempt['status'],
            'result_visible' => $resultVisible,
            'fully_graded' => $pendingGrading === 0,
        ];
        if ($resultVisible) {
            $data['obtained_marks'] = (float) $attempt['obtained_marks'];
            $data['percentage'] = (float) $attempt['percentage'];
            $data['is_passed'] = (bool) $attempt['is_passed'];
        }

        $this->success($data);
    }

    /**
     * 03f's stated mechanism: the *next* request of any kind against an
     * expired attempt finalizes it server-side first, using whatever was
     * already autosaved — never trusting the client's clock or a
     * background job racing the request.
     */
    private function autoFinalizeIfExpired(array $attempt): array
    {
        if ($attempt['status'] !== 'in_progress') {
            return $attempt;
        }

        $exam = $this->db->fetchOne('SELECT duration_minutes FROM exams WHERE id = ?', [$attempt['exam_id']]);
        $deadline = strtotime($attempt['started_at']) + ((int) $exam['duration_minutes'] * 60);

        if (time() > $deadline) {
            $this->gradeAndFinalize((int) $attempt['id'], 'completed');
            return $this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ?', [$attempt['id']]);
        }

        return $attempt;
    }

    /** Objective types auto-grade; short_answer/long_answer/coding route to manual grading (marks_awarded left NULL). */
    private function gradeAndFinalize(int $attemptId, string $status): void
    {
        $examId = $this->db->fetchOne('SELECT exam_id FROM exam_attempts WHERE id = ?', [$attemptId])['exam_id'];

        $responses = $this->db->select(
            "SELECT er.id, er.response, q.type, q.correct_answer,
                    COALESCE(eq.marks_override, q.marks) AS marks
             FROM exam_responses er
             JOIN questions q ON q.id = er.question_id
             JOIN exam_questions eq ON eq.exam_id = ? AND eq.question_id = er.question_id
             WHERE er.attempt_id = ?",
            [$examId, $attemptId]
        );

        $objectiveTypes = ['mcq', 'msq', 'true_false', 'fill_blank'];
        $obtainedMarks = 0.0;
        $totalMarks = 0.0;

        foreach ($responses as $r) {
            $totalMarks += (float) $r['marks'];
            if (! in_array($r['type'], $objectiveTypes, true)) {
                continue; // short_answer/long_answer/coding — manual grading, marks_awarded stays NULL.
            }

            $given = json_decode($r['response'] ?? 'null', true);
            $correct = json_decode($r['correct_answer'] ?? 'null', true);
            $isCorrect = $this->responsesMatch($given, $correct);
            $marksAwarded = $isCorrect ? (float) $r['marks'] : 0.0;
            $obtainedMarks += $marksAwarded;

            $this->db->updateTable('exam_responses', [
                'is_correct' => $isCorrect ? 1 : 0,
                'marks_awarded' => $marksAwarded,
            ], 'id = ?', [$r['id']]);
        }

        $attempt = $this->db->fetchOne('SELECT started_at FROM exam_attempts WHERE id = ?', [$attemptId]);
        $exam = $this->db->fetchOne(
            'SELECT e.total_marks, e.passing_marks FROM exams e JOIN exam_attempts ea ON ea.exam_id = e.id WHERE ea.id = ?',
            [$attemptId]
        );
        $denominator = $totalMarks > 0 ? $totalMarks : (float) $exam['total_marks'];
        $percentage = $denominator > 0 ? round(($obtainedMarks / $denominator) * 100, 2) : 0.0;

        $this->db->updateTable('exam_attempts', [
            'status' => $status,
            'submitted_at' => date('Y-m-d H:i:s'),
            'time_taken_seconds' => time() - strtotime($attempt['started_at']),
            'obtained_marks' => $obtainedMarks,
            'percentage' => $percentage,
            'is_passed' => $obtainedMarks >= (float) $exam['passing_marks'] ? 1 : 0,
        ], 'id = ?', [$attemptId]);
    }

    private function responsesMatch(mixed $given, mixed $correct): bool
    {
        if (is_array($given) && is_array($correct)) {
            sort($given);
            sort($correct);
            return $given == $correct;
        }
        return $given === $correct;
    }

    private function respondAttempt(array $attempt, array $exam, bool $includeResponses = false): void
    {
        // The exam's actual question set is exam_questions (a curated pivot,
        // not "every question in the course") — same join PlacementController
        // uses for placement exams.
        $questions = $this->db->select(
            'SELECT q.id AS question_id, q.type, q.question_text, q.options,
                    COALESCE(eq.marks_override, q.marks) AS marks
             FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
             WHERE eq.exam_id = ? ORDER BY eq.sort_order',
            [$exam['id']]
        );

        if ($exam['shuffle_questions']) {
            mt_srand((int) $attempt['id']);
            shuffle($questions);
            mt_srand(); // reseed from system entropy — nothing else in this request should inherit a predictable sequence
        }

        $responsesByQuestion = [];
        if ($includeResponses) {
            $rows = $this->db->select('SELECT question_id, response, time_spent_seconds FROM exam_responses WHERE attempt_id = ?', [$attempt['id']]);
            foreach ($rows as $r) {
                $responsesByQuestion[(int) $r['question_id']] = [
                    'response' => json_decode($r['response'] ?? 'null', true),
                    'time_spent_seconds' => (int) $r['time_spent_seconds'],
                ];
            }
        }

        $this->success([
            'attempt_id' => (int) $attempt['id'],
            'status' => $attempt['status'],
            'started_at' => $attempt['started_at'],
            'ends_at' => date('c', strtotime($attempt['started_at']) + ((int) $exam['duration_minutes'] * 60)),
            'questions' => array_map(function (array $q) use ($responsesByQuestion) {
                $options = json_decode($q['options'] ?? 'null', true);
                if (is_array($options) && (bool) ($exam['shuffle_options'] ?? false)) {
                    shuffle($options);
                }
                $out = [
                    'question_id' => (int) $q['question_id'],
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'options' => $options,
                    'marks' => (float) $q['marks'],
                ];
                if (isset($responsesByQuestion[(int) $q['question_id']])) {
                    $out['own_response'] = $responsesByQuestion[(int) $q['question_id']];
                }
                return $out;
            }, $questions),
        ]);
    }

    /**
     * A self-generated practice quiz (NoteController::generateQuiz(),
     * `source_note_id` set, `created_by` = the student themselves) is a
     * personal study tool, not a course-assigned exam — it's visible to
     * its own creator regardless of course/enrollment, since it may not
     * even have a `course_id` at all (a note with no linked course).
     * Every other exam still requires the real enrollment join.
     */
    private function visibleExam(string $id, int $studentId): array
    {
        $exam = $this->db->fetchOne(
            "SELECT e.* FROM exams e
             LEFT JOIN enrollments en ON en.course_id = e.course_id AND en.user_id = ?
             WHERE e.id = ? AND e.type != 'placement' AND e.status IN ('published', 'active')
               AND (en.id IS NOT NULL OR (e.source_note_id IS NOT NULL AND e.created_by = ?))",
            [$studentId, $id, $studentId]
        );

        if (! $exam) {
            $this->fail('No such exam.', ['reason' => ['not_found']], 404);
        }

        return $exam;
    }

    private function ownAttempt(string $attemptId): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $attempt = $this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?', [$attemptId, $studentId]);

        if (! $attempt) {
            $this->fail('No such attempt.', ['reason' => ['not_found']], 404);
        }

        return $attempt;
    }
}
