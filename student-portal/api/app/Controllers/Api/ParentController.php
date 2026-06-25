<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Request;

/**
 * Parent Visibility, Risk Visibility, Monthly Parent Reports —
 * docs/student-module/04f-apis-parent-billing.md. No cloud adaptation
 * needed for most of this section (it's all reads against existing
 * tables) — the one real change is `parent_reports.pdf_url`: see
 * cron/generate-parent-reports.php and App\Core\SimplePdf for why it now
 * stores a local relative path (signed fresh on every read, like
 * Materials/Recordings) instead of an S3 URL.
 */
class ParentController extends Controller
{
    public function children(Request $request): void
    {
        $parentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT psl.student_id, u.first_name, u.last_name, psl.relationship, psl.is_primary_guardian,
                    psl.can_view_recordings, psl.can_view_billing, psl.can_view_attendance, psl.can_book_ptm
             FROM parent_student_links psl JOIN users u ON u.id = psl.student_id
             WHERE psl.parent_id = ? AND psl.consent_status = 'granted'",
            [$parentId]
        );

        $this->success(array_map(fn (array $r) => [
            'student_id' => (int) $r['student_id'],
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'relationship' => $r['relationship'],
            'is_primary_guardian' => (bool) $r['is_primary_guardian'],
            'can_view_recordings' => (bool) $r['can_view_recordings'],
            'can_view_billing' => (bool) $r['can_view_billing'],
            'can_view_attendance' => (bool) $r['can_view_attendance'],
            'can_book_ptm' => (bool) $r['can_book_ptm'],
        ], $rows));
    }

    public function riskSummary(Request $request, string $studentId): void
    {
        // Deliberately not gated by any can_view_* boolean — none of them
        // map to a wellbeing signal (04f's explicit reasoning). Basic link
        // validity is the only check.
        $this->requireLink((int) $studentId, requireBoolean: null);

        $score = $this->db->fetchOne(
            "SELECT intervention_status FROM risk_scores
             WHERE student_id = ? AND intervention_status != 'none'
             ORDER BY computed_at DESC LIMIT 1",
            [$studentId]
        );

        if (! $score || ! in_array($score['intervention_status'], ['mentor_call_scheduled', 'parent_escalated'], true)) {
            $this->success(['has_active_flag' => false]);
        }

        $this->success([
            'has_active_flag' => true,
            'recommended_action' => 'mentor_call_recommended',
            'message' => "We've noticed a few missed classes recently — a quick check-in call might help.",
            'ptm_booking_available' => true,
        ]);
    }

    public function studentDashboard(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, requireBoolean: null);

        $enrollment = $this->db->fetchOne(
            "SELECT id FROM enrollments WHERE user_id = ? AND status = 'active' ORDER BY enrolled_at DESC LIMIT 1",
            [$studentId]
        );

        $snapshot = $enrollment
            ? $this->db->fetchOne(
                'SELECT * FROM student_progress_snapshots WHERE enrollment_id = ? ORDER BY snapshot_date DESC LIMIT 1',
                [$enrollment['id']]
            )
            : null;

        $this->success($snapshot ? [
            'enrollment_id' => (int) $enrollment['id'],
            'snapshot_date' => $snapshot['snapshot_date'],
            'attendance_percent' => $snapshot['attendance_percent'] !== null ? (int) $snapshot['attendance_percent'] : null,
            'course_completion_percent' => $snapshot['course_completion_percent'] !== null ? (int) $snapshot['course_completion_percent'] : null,
            'assignment_completion_percent' => $snapshot['assignment_completion_percent'] !== null ? (int) $snapshot['assignment_completion_percent'] : null,
            'avg_project_score' => $snapshot['avg_project_score'] !== null ? (float) $snapshot['avg_project_score'] : null,
            'avg_assessment_score' => $snapshot['avg_assessment_score'] !== null ? (float) $snapshot['avg_assessment_score'] : null,
        ] : null);
    }

    public function studentAttendance(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, 'can_view_attendance');
        $courseId = (int) $request->input('course_id', 0);

        if (! $courseId) {
            $this->fail('course_id is required.', ['course_id' => ['required']]);
        }

        $rows = $this->db->select(
            "SELECT a.live_class_id, a.session_date, a.status, a.attendance_percent, lc.title
             FROM attendance a JOIN live_classes lc ON lc.id = a.live_class_id JOIN batches b ON b.id = lc.batch_id
             WHERE a.student_id = ? AND b.course_id = ? ORDER BY a.session_date DESC",
            [$studentId, $courseId]
        );

        $this->success($rows);
    }

    public function studentRecordings(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, 'can_view_recordings');

        // Full enrollment history, not just current batches — same access
        // principle RecordingController already applies for the student's
        // own view (04c).
        $rows = $this->db->select(
            "SELECT DISTINCT cr.id, cr.processing_status, cr.thumbnail_url, cr.duration_seconds, cr.available_at, lc.title
             FROM class_recordings cr
             JOIN live_classes lc ON lc.id = cr.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             ORDER BY lc.start_datetime DESC",
            [$studentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'processing_status' => $r['processing_status'],
            'thumbnail_url' => $r['thumbnail_url'],
            'duration_seconds' => (int) $r['duration_seconds'],
            'available_at' => $r['available_at'],
        ], $rows));
    }

    /** Never served from a snapshot — read live every time (04f's explicit exception: stale financial data shown to a parent is a trust problem). */
    public function studentWallet(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, 'can_view_billing');

        $wallet = $this->db->fetchOne(
            "SELECT cw.* FROM credit_wallets cw JOIN enrollments e ON e.id = cw.enrollment_id
             WHERE cw.student_id = ? AND e.status = 'active' ORDER BY cw.created_at DESC LIMIT 1",
            [$studentId]
        );

        if (! $wallet) {
            $this->fail('No active wallet found.', ['reason' => ['no_active_wallet']], 404);
        }

        $this->success([
            'wallet_id' => (int) $wallet['id'],
            'status' => $wallet['status'],
            'credits_purchased' => (int) $wallet['credits_purchased'],
            'credits_consumed' => (int) $wallet['credits_consumed'],
            'credits_balance' => (int) $wallet['credits_balance'],
            'low_balance_threshold' => (int) $wallet['low_balance_threshold'],
            'expiry_date' => $wallet['expiry_date'],
        ]);
    }

    public function studentReports(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, requireBoolean: null);

        $rows = $this->db->select(
            'SELECT id, period_month, is_partial_period, viewed_by_parent_at FROM parent_reports WHERE student_id = ? ORDER BY period_month DESC',
            [$studentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'period_month' => $r['period_month'],
            'is_partial_period' => (bool) $r['is_partial_period'],
            'viewed_by_parent_at' => $r['viewed_by_parent_at'],
        ], $rows));
    }

    public function reportDetail(Request $request, string $id): void
    {
        $report = $this->ownReport($id);

        $this->success([
            'id' => (int) $report['id'],
            'period_month' => $report['period_month'],
            'is_partial_period' => (bool) $report['is_partial_period'],
            'pdf_url' => $report['pdf_url'] ? FileStorage::signedUrl($report['pdf_url'], 600) : null,
            'summary_text' => $report['summary_text'],
        ]);
    }

    /** One-way flag — an already-viewed report ignores a repeat call rather than overwriting the timestamp (04f's explicit rule). */
    public function markReportViewed(Request $request, string $id): void
    {
        $report = $this->ownReport($id);

        if (! $report['viewed_by_parent_at']) {
            $this->db->updateTable('parent_reports', ['viewed_by_parent_at' => date('Y-m-d H:i:s')], 'id = ?', [$report['id']]);
        }

        $this->success(true);
    }

    /**
     * The minor gate from 04j — not gated by any can_view_* boolean (same
     * call 04f made for risk-summary): this is a distinct consent action,
     * not an information-visibility setting. Basic link validity is the
     * only check.
     */
    public function portfolioPreview(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, requireBoolean: null);
        $this->success((new PortfolioController())->aggregatePortfolioForStudent((int) $studentId));
    }

    /** Flips is_public=1 immediately — reverting to private later and going public again re-triggers this same gate (04j's explicit rule). */
    public function portfolioApprove(Request $request, string $studentId): void
    {
        $this->requireLink((int) $studentId, requireBoolean: null);

        $portfolio = $this->db->fetchOne('SELECT * FROM student_portfolios WHERE student_id = ?', [$studentId]);
        if (! $portfolio || ! $portfolio['pending_public_request']) {
            $this->fail('No pending portfolio request for this student.', ['reason' => ['no_pending_request']], 422);
        }

        $this->db->updateTable('student_portfolios', ['is_public' => 1, 'pending_public_request' => 0], 'student_id = ?', [$studentId]);
        $this->success(['is_public' => true]);
    }

    private function ownReport(string $id): array
    {
        $parentId = (int) $this->currentUser()['id'];

        $report = $this->db->fetchOne(
            "SELECT pr.* FROM parent_reports pr
             JOIN parent_student_links psl ON psl.student_id = pr.student_id
             WHERE pr.id = ? AND psl.parent_id = ? AND psl.consent_status = 'granted'",
            [$id, $parentId]
        );

        if (! $report) {
            $this->fail('No such report.', ['reason' => ['not_found']], 404);
        }

        return $report;
    }

    /**
     * Every call re-checks the link's consent_status and (if given) the
     * relevant can_view_* boolean on every single request, never cached —
     * a custody change or revoked consent cuts off access on the very next
     * call (04f's explicit reasoning).
     */
    private function requireLink(int $studentId, ?string $requireBoolean): array
    {
        $parentId = (int) $this->currentUser()['id'];

        $link = $this->db->fetchOne(
            'SELECT psl.*, u.date_of_birth FROM parent_student_links psl
             JOIN users u ON u.id = psl.student_id
             WHERE psl.parent_id = ? AND psl.student_id = ?',
            [$parentId, $studentId]
        );

        if (! $link) {
            $this->fail('No such linked student.', ['reason' => ['not_found']], 404);
        }
        if ($link['consent_status'] !== 'granted') {
            $this->fail('Consent for this link is not active.', ['reason' => ['consent_not_granted']], 403);
        }
        if ($this->isNowAdult($link['date_of_birth'])) {
            $this->fail('This student is now an adult; continued parent access requires fresh consent.', ['reason' => ['student_now_adult_consent_required']], 403);
        }
        if ($requireBoolean !== null && ! $link[$requireBoolean]) {
            $this->fail('This is not visible to this guardian.', ['reason' => [$requireBoolean . '_not_granted']], 403);
        }

        return $link;
    }

    private function isNowAdult(?string $dateOfBirth): bool
    {
        if (! $dateOfBirth) {
            return false;
        }
        $threshold = (require BASE_PATH . '/config/app.php')['adult_age_threshold'];
        $age = (new \DateTime($dateOfBirth))->diff(new \DateTime())->y;
        return $age >= $threshold;
    }
}
