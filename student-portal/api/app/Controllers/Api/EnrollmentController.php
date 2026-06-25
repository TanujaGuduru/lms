<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\CourseCompletion;
use App\Core\Request;

/**
 * Course Completion — docs/student-module/04g-apis-completion-growth.md.
 * Read-only: completion itself is computed server-side by
 * cron/check-course-completion.php (an event-driven check in the doc's own
 * description; on shared hosting with no queue/worker process and no way
 * for this app to be notified the instant an Admin-portal teacher grades
 * something, a periodic cron sweep is the honest equivalent — see that
 * script's docblock).
 */
class EnrollmentController extends Controller
{
    public function show(Request $request, string $id): void
    {
        $enrollment = $this->ownEnrollment($id);

        $this->success([
            'id' => (int) $enrollment['id'],
            'course_id' => (int) $enrollment['course_id'],
            'status' => $enrollment['status'],
            'progress_percentage' => (int) $enrollment['progress_percentage'],
            'completed_at' => $enrollment['completed_at'],
            'certificate_issued_at' => $enrollment['certificate_issued_at'],
            'expiry_date' => $enrollment['expiry_date'],
        ]);
    }

    public function completionRequirements(Request $request, string $id): void
    {
        $enrollment = $this->ownEnrollment($id);
        $requirements = CourseCompletion::evaluate($this->db, $enrollment);
        $allMet = $requirements['all_met'];
        unset($requirements['all_met']); // internal-only, not part of the documented response shape

        $requirements['grace_period_ends_at'] = $this->graceperiodEndsAt($enrollment, $allMet);

        $this->success($requirements);
    }

    /**
     * Only present once the course's nominal end date has passed *without*
     * every requirement met (04g's explicit rule) — distinct from both
     * `completed` and a hard `incomplete`, neither of which this value
     * implies on its own. A student whose criteria are already all
     * satisfied never sees this, even before the hourly completion cron
     * has gotten around to flipping `status` to `completed` — the
     * requirements themselves, not the as-yet-unprocessed status, are
     * what this check is about.
     */
    private function graceperiodEndsAt(array $enrollment, bool $allRequirementsMet): ?string
    {
        if ($allRequirementsMet || $enrollment['status'] !== 'active' || ! $enrollment['batch_id']) {
            return null;
        }

        $batch = $this->db->fetchOne('SELECT end_date FROM batches WHERE id = ?', [$enrollment['batch_id']]);
        if (! $batch || ! $batch['end_date'] || strtotime($batch['end_date']) > time()) {
            return null;
        }

        $graceDays = (require BASE_PATH . '/config/app.php')['grace_period_days'];
        return date('Y-m-d\TH:i:s\Z', strtotime($batch['end_date'] . " +{$graceDays} days"));
    }

    private function ownEnrollment(string $id): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollment = $this->db->fetchOne('SELECT * FROM enrollments WHERE id = ? AND user_id = ?', [$id, $studentId]);

        if (! $enrollment) {
            $this->fail('No such enrollment.', ['reason' => ['not_found']], 404);
        }

        return $enrollment;
    }
}
