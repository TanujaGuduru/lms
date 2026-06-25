<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Gamification;
use App\Core\Request;

/**
 * Assignments — docs/student-module/04d-apis-assignments-sandbox-ai.md.
 * The Coding Sandbox section of that same doc is **not implemented** — this
 * platform never executes student-submitted code at all, by deliberate
 * choice (GoDaddy shared hosting can't run untrusted code safely, and the
 * decision was to drop live execution rather than attempt a workaround).
 * That changes `type='code'` here specifically: the original design had it
 * submit a `code_workspaces` reference that the server zips server-side;
 * with no workspace/sandbox at all, a code submission is just pasted code
 * in `submission_text`, exactly like a text assignment — still reviewable,
 * still gradeable, just never run.
 */
class AssignmentController extends Controller
{
    public function courseAssignments(Request $request, string $courseId): void
    {
        $studentId = (int) $this->currentUser()['id'];

        if (! $this->isEnrolled($studentId, (int) $courseId)) {
            $this->fail('No such course.', ['reason' => ['not_found']], 404);
        }

        $rows = $this->db->select(
            "SELECT a.id, a.title, a.type, a.due_date, a.late_submission_allowed, a.late_penalty_percent,
                    s.status AS own_submission_status, s.extended_due_date
             FROM assignments a
             LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
             WHERE a.course_id = ? AND a.status IN ('published', 'closed')
             ORDER BY a.due_date",
            [$studentId, $courseId]
        );

        $this->success(array_map(fn (array $a) => [
            'id' => (int) $a['id'],
            'title' => $a['title'],
            'type' => $a['type'],
            'due_date' => $a['due_date'],
            'own_submission_status' => $a['own_submission_status'],
            'is_overdue' => $this->isOverdue($a['due_date'], $a['extended_due_date'], $a['own_submission_status']),
        ], $rows));
    }

    public function show(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $assignment = $this->visibleAssignment($id, $studentId);
        $submission = $this->ownSubmission((int) $id, $studentId);

        $this->success([
            'id' => (int) $assignment['id'],
            'title' => $assignment['title'],
            'description' => $assignment['description'],
            'type' => $assignment['type'],
            'due_date' => $assignment['due_date'],
            'late_submission_allowed' => (bool) $assignment['late_submission_allowed'],
            'late_penalty_percent' => (int) $assignment['late_penalty_percent'],
            'total_marks' => (int) $assignment['total_marks'],
            'own_submission_status' => $submission['status'] ?? null,
            'is_overdue' => $this->isOverdue(
                $assignment['due_date'],
                $submission['extended_due_date'] ?? null,
                $submission['status'] ?? null
            ),
        ]);
    }

    public function showSubmission(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $this->visibleAssignment($id, $studentId);
        $submission = $this->ownSubmission((int) $id, $studentId);

        if (! $submission) {
            $this->success(null);
            return;
        }

        $this->success($this->formatSubmission($submission));
    }

    /** GET /submissions/{id} — own submission detail by submission id directly (04e), incl. originality_score once available. */
    public function showSubmissionById(Request $request, string $submissionId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $submission = $this->db->fetchOne(
            'SELECT * FROM assignment_submissions WHERE id = ? AND student_id = ?',
            [$submissionId, $studentId]
        );

        if (! $submission) {
            $this->fail('No such submission.', ['reason' => ['not_found']], 404);
        }

        $this->success($this->formatSubmission($submission));
    }

    private function formatSubmission(array $submission): array
    {
        $submission['screenshots'] = $submission['screenshots'] ? json_decode($submission['screenshots'], true) : null;
        return $submission;
    }

    public function upsertSubmission(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $assignment = $this->visibleAssignment($id, $studentId);

        // type='code' is plain pasted code in submission_text — there is no
        // workspace/sandbox to reference (see class docblock). type='project'
        // combines several of these fields at once rather than forcing one
        // (04e's explicit requirement) — all plain columns on the same row.
        $allowed = ['submission_text', 'url', 'github_repo_url', 'demo_video_url', 'screenshots'];
        $data = array_intersect_key($request->all(), array_flip($allowed));

        if (empty($data)) {
            $this->fail('No submittable fields were provided.', ['reason' => ['empty_payload']]);
        }
        if (isset($data['screenshots'])) {
            $data['screenshots'] = json_encode($data['screenshots']);
        }

        $existing = $this->db->fetchOne(
            'SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?',
            [$id, $studentId]
        );

        // A draft autosave never advances status on its own — stays 'draft',
        // or stays 'returned' if a teacher had reopened it (04d's explicit rule).
        if ($existing) {
            $this->db->updateTable('assignment_submissions', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insertInto('assignment_submissions', array_merge($data, [
                'assignment_id' => $id,
                'student_id' => $studentId,
                'status' => 'draft',
            ]));
        }

        $this->showSubmission($request, $id);
    }

    public function submit(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $assignment = $this->visibleAssignment($id, $studentId);
        $submission = $this->ownSubmission((int) $id, $studentId);

        if (! $submission) {
            $this->fail('Nothing has been saved to submit yet.', ['reason' => ['no_draft']], 422);
        }

        // A retried request (flaky connection, double-tap) must not re-stamp
        // submitted_at/is_late on an already-finalized submission — the SLA
        // clock should reflect the first successful submit, not the last
        // retry. The doc calls for an Idempotency-Key here; this state-machine
        // guard achieves the same "never a second submission event" outcome
        // without needing a separate idempotency-key table.
        if (in_array($submission['status'], ['submitted', 'graded', 'resubmitted'], true)) {
            $this->success([
                'id' => (int) $submission['id'],
                'status' => $submission['status'],
                'submitted_at' => $submission['submitted_at'],
                'is_late' => (bool) $submission['is_late'],
                'late_penalty_percent' => $submission['is_late'] ? (int) $assignment['late_penalty_percent'] : 0,
            ]);
        }

        $effectiveDueDate = $submission['extended_due_date'] ?? $assignment['due_date'];
        $isLate = strtotime($effectiveDueDate) < time();

        if ($isLate && ! $assignment['late_submission_allowed']) {
            $this->fail('The deadline for this assignment has passed.', ['reason' => ['past_deadline_not_allowed']], 422);
        }

        // submitted -> graded is terminal until a teacher explicitly returns
        // it; resubmitting after 'returned' transitions to 'resubmitted'
        // rather than plain 'submitted' (04d's explicit lifecycle).
        $newStatus = $submission['status'] === 'returned' ? 'resubmitted' : 'submitted';

        $this->db->updateTable('assignment_submissions', [
            'status' => $newStatus,
            'submitted_at' => date('Y-m-d H:i:s'),
            'is_late' => $isLate ? 1 : 0,
        ], 'id = ?', [$submission['id']]);

        if ($assignment['type'] === 'project') {
            $this->queueOriginalityCheck((int) $submission['id']);
        }

        // 04h: "an assignment submitted via 04d" / "a project completed via
        // 04e" are the documented XP triggers. Grading itself happens in the
        // Admin panel (outside this codebase's reach), so submission — not
        // a later grading event this app can't observe — is the honest
        // trigger point actually available here.
        Gamification::awardXp(
            $this->db, $studentId, $assignment['type'] === 'project' ? 25 : 10,
            $assignment['type'] === 'project' ? 'project_completed' : 'assignment_submitted',
            'assignment_submission', (int) $submission['id']
        );

        $this->success([
            'id' => (int) $submission['id'],
            'status' => $newStatus,
            'submitted_at' => date('c'),
            'is_late' => $isLate,
            'late_penalty_percent' => $isLate ? (int) $assignment['late_penalty_percent'] : 0,
        ]);
    }

    /**
     * "Asynchronous" made concrete on shared hosting: insert-and-return,
     * never block the submit response on it. A cPanel cron job
     * (cron/process-originality-checks.php) drains this queue — see
     * schema_student_portal.sql's originality_check_queue comment for why
     * this exists instead of the originally-designed Pinecone/plagiarism-
     * service pipeline. Re-submitting re-queues (ON DUPLICATE KEY UPDATE),
     * since the content genuinely changed and the old score no longer applies.
     */
    private function queueOriginalityCheck(int $submissionId): void
    {
        $this->db->execute(
            "INSERT INTO originality_check_queue (submission_id, status) VALUES (?, 'pending')
             ON DUPLICATE KEY UPDATE status = 'pending', processed_at = NULL",
            [$submissionId]
        );
    }

    /**
     * Publishing — docs/student-module/04e. Creates the published_projects
     * row immediately but is_public=0; nothing reaches the public Achievement
     * Showcase wall without an explicit mentor/admin approval (an Admin/
     * Teacher-portal action, not callable from this student-facing API) —
     * published work is tied to a minor's name, so this stays consent-first.
     */
    public function publishRequest(Request $request, string $submissionId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $submission = $this->db->fetchOne(
            'SELECT * FROM assignment_submissions WHERE id = ? AND student_id = ?',
            [$submissionId, $studentId]
        );

        if (! $submission) {
            $this->fail('No such submission.', ['reason' => ['not_found']], 404);
        }
        if ($submission['status'] !== 'graded') {
            $this->fail('This submission has not been graded yet.', ['reason' => ['not_graded_yet']], 422);
        }

        $existing = $this->db->fetchOne('SELECT * FROM published_projects WHERE submission_id = ?', [$submissionId]);
        if ($existing) {
            $this->respondPublishRequest($existing);
        }

        $assignment = $this->db->fetchOne(
            'SELECT title FROM assignments a JOIN assignment_submissions s ON s.assignment_id = a.id WHERE s.id = ?',
            [$submissionId]
        );

        $id = $this->db->insertInto('published_projects', [
            'submission_id' => $submissionId,
            'student_id' => $studentId,
            'title' => $assignment['title'],
            'description' => $submission['submission_text'],
            'is_public' => 0,
        ]);

        $this->respondPublishRequest($this->db->fetchOne('SELECT * FROM published_projects WHERE id = ?', [$id]));
    }

    public function myPublishRequests(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT id, submission_id, title, is_public, approved_at, created_at FROM published_projects WHERE student_id = ? ORDER BY created_at DESC',
            [$studentId]
        );
        $this->success($rows);
    }

    private function respondPublishRequest(array $row): void
    {
        $this->success([
            'submission_id' => (int) $row['submission_id'],
            'published_project_id' => (int) $row['id'],
            'is_public' => (bool) $row['is_public'],
            'status' => $row['is_public'] ? 'approved' : 'pending_approval',
        ]);
    }

    private function isEnrolled(int $studentId, int $courseId): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?', [$studentId, $courseId]);
    }

    private function visibleAssignment(string $id, int $studentId): array
    {
        $assignment = $this->db->fetchOne(
            "SELECT a.* FROM assignments a
             JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = ?
             WHERE a.id = ? AND a.status IN ('published', 'closed')",
            [$studentId, $id]
        );

        if (! $assignment) {
            $this->fail('No such assignment.', ['reason' => ['not_found']], 404);
        }

        return $assignment;
    }

    private function ownSubmission(int $assignmentId, int $studentId): array|null
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?',
            [$assignmentId, $studentId]
        );

        return $row ?: null;
    }

    private function isOverdue(?string $dueDate, ?string $extendedDueDate, ?string $submissionStatus): bool
    {
        if (in_array($submissionStatus, ['submitted', 'graded', 'resubmitted'], true)) {
            return false;
        }
        $effective = $extendedDueDate ?: $dueDate;
        return $effective !== null && strtotime($effective) < time();
    }
}
