<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Progress Analytics — docs/student-module/04e-apis-assessments-projects.md.
 * Read-only, exactly as specified: a nightly cron
 * (cron/compute-progress-snapshots.php, a real GoDaddy cPanel Cron Job, not
 * a cloud service) is the only thing that ever writes
 * `student_progress_snapshots` — computing this live on every dashboard
 * load would mean multi-table joins/aggregations on shared hosting on every
 * request, a real performance risk the doc explicitly calls out.
 *
 * `coding_success_rate`'s meaning changed from the original design: it was
 * "share of sandbox executions with exit_code=0" — with no Coding Sandbox
 * at all (no live code execution, by deliberate choice), there are no
 * executions to measure. The cron instead computes it as the share of
 * graded code-type work (assignments + exam questions) that scored at or
 * above the passing threshold — a "did it work" signal based on grading
 * instead of running, which is the closest honest equivalent left.
 */
class ProgressController extends Controller
{
    public function snapshot(Request $request): void
    {
        $enrollment = $this->ownEnrollment($request);

        $row = $this->db->fetchOne(
            'SELECT * FROM student_progress_snapshots WHERE enrollment_id = ? ORDER BY snapshot_date DESC LIMIT 1',
            [$enrollment['id']]
        );

        if (! $row) {
            $this->success(null);
        }

        $this->success($this->formatSnapshot($row));
    }

    public function history(Request $request): void
    {
        $enrollment = $this->ownEnrollment($request);
        $days = max(1, min(365, (int) $request->input('days', 30)));

        $rows = $this->db->select(
            "SELECT * FROM student_progress_snapshots
             WHERE enrollment_id = ? AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY snapshot_date",
            [$enrollment['id'], $days]
        );

        $this->success(array_map(fn (array $r) => $this->formatSnapshot($r), $rows));
    }

    public function insights(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT insight_type, source_type, source_id, summary, detail, created_at
             FROM ai_insights WHERE student_id = ? ORDER BY created_at DESC',
            [$studentId]
        );

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['insight_type']][] = [
                'source_type' => $r['source_type'],
                'source_id' => $r['source_id'] !== null ? (int) $r['source_id'] : null,
                'summary' => $r['summary'],
                'detail' => $r['detail'] ? json_decode($r['detail'], true) : null,
                'created_at' => $r['created_at'],
            ];
        }

        $this->success($grouped);
    }

    private function formatSnapshot(array $row): array
    {
        return [
            'snapshot_date' => $row['snapshot_date'],
            'attendance_percent' => $row['attendance_percent'] !== null ? (int) $row['attendance_percent'] : null,
            'course_completion_percent' => $row['course_completion_percent'] !== null ? (int) $row['course_completion_percent'] : null,
            'assignment_completion_percent' => $row['assignment_completion_percent'] !== null ? (int) $row['assignment_completion_percent'] : null,
            'avg_project_score' => $row['avg_project_score'] !== null ? (float) $row['avg_project_score'] : null,
            'avg_assessment_score' => $row['avg_assessment_score'] !== null ? (float) $row['avg_assessment_score'] : null,
            'coding_success_rate' => $row['coding_success_rate'] !== null ? (float) $row['coding_success_rate'] : null,
        ];
    }

    private function ownEnrollment(Request $request): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollmentId = $request->input('enrollment_id');

        $enrollment = $this->db->fetchOne(
            'SELECT * FROM enrollments WHERE id = ? AND user_id = ?',
            [$enrollmentId, $studentId]
        );

        if (! $enrollment) {
            $this->fail('No such enrollment.', ['reason' => ['not_found']], 404);
        }

        return $enrollment;
    }
}
