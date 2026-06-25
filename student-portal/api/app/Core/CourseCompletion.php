<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Shared completion-criteria evaluation — docs/student-module/04g/03h.
 * Used by both EnrollmentController (the read-only `completion-requirements`
 * checklist) and cron/check-course-completion.php (the actual status flip),
 * so the two can never silently disagree about what "done" means.
 *
 * Two criteria have no precise schema signal to check against, since
 * 03h/04g describe them conceptually without a dedicated column or table —
 * resolved with the simplest defensible interpretation rather than
 * inventing new database state for either:
 *  - **`capstone_complete`**: there's no `is_capstone` flag on
 *    `assignments`. Treated as the course's `type='project'` assignment
 *    with the latest `due_date` — the "final project" by construction,
 *    same "discover the gap, fill it, say so" pattern used elsewhere in
 *    this project. A course with no project assignment at all has nothing
 *    to require here, so it's trivially true.
 *  - **`wallet_disputes_clear`**: there's no dispute-tracking table at all.
 *    Tied to a real, already-built signal instead of an invented one: true
 *    unless the student has an *open* support ticket whose subject matches
 *    `PaymentController::refundRequest()`'s own `"Refund request — ..."`
 *    pattern — the one place this codebase actually creates a
 *    billing-dispute-shaped ticket.
 */
class CourseCompletion
{
    public static function evaluate(Database $db, array $enrollment): array
    {
        $studentId = (int) $enrollment['user_id'];
        $courseId = (int) $enrollment['course_id'];
        $enrollmentId = (int) $enrollment['id'];

        $attendanceMet = self::attendanceMet($db, $studentId, $enrollment['batch_id']);
        $assignmentsComplete = self::requiredAssignmentsComplete($db, $studentId, $courseId);
        $capstoneComplete = self::capstoneComplete($db, $studentId, $courseId);
        $finalAssessmentPassed = self::finalAssessmentPassed($db, $studentId, $courseId);
        $walletDisputesClear = self::walletDisputesClear($db, $studentId);

        return [
            'attendance_met' => $attendanceMet,
            'required_assignments_complete' => $assignmentsComplete,
            'capstone_complete' => $capstoneComplete,
            'final_assessment_passed' => $finalAssessmentPassed,
            'wallet_disputes_clear' => $walletDisputesClear,
            'all_met' => $attendanceMet && $assignmentsComplete && $capstoneComplete && $finalAssessmentPassed && $walletDisputesClear,
        ];
    }

    private static function attendanceMet(Database $db, int $studentId, ?int $batchId): bool
    {
        if (! $batchId) {
            return true; // nothing to attend (e.g. a self-paced course) — never a false negative for something that isn't applicable.
        }
        $threshold = (require BASE_PATH . '/config/app.php')['attendance_required_percent'];
        $avg = $db->fetchOne(
            "SELECT AVG(a.attendance_percent) AS v FROM attendance a
             JOIN live_classes lc ON lc.id = a.live_class_id WHERE a.student_id = ? AND lc.batch_id = ?",
            [$studentId, $batchId]
        )['v'];

        return $avg !== null && (float) $avg >= $threshold;
    }

    private static function requiredAssignmentsComplete(Database $db, int $studentId, int $courseId): bool
    {
        $total = (int) $db->fetchOne(
            "SELECT COUNT(*) AS c FROM assignments WHERE course_id = ? AND status IN ('published', 'closed')",
            [$courseId]
        )['c'];
        if ($total === 0) {
            return true;
        }

        $done = (int) $db->fetchOne(
            "SELECT COUNT(*) AS c FROM assignment_submissions asub JOIN assignments a ON a.id = asub.assignment_id
             WHERE asub.student_id = ? AND a.course_id = ? AND asub.status IN ('graded', 'submitted', 'resubmitted')",
            [$studentId, $courseId]
        )['c'];

        return $done >= $total;
    }

    private static function capstoneComplete(Database $db, int $studentId, int $courseId): bool
    {
        $capstone = $db->fetchOne(
            "SELECT id FROM assignments WHERE course_id = ? AND type = 'project' ORDER BY due_date DESC LIMIT 1",
            [$courseId]
        );
        if (! $capstone) {
            return true; // no capstone defined for this course — nothing to require.
        }

        $submission = $db->fetchOne(
            "SELECT status FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?",
            [$capstone['id'], $studentId]
        );

        return $submission && $submission['status'] === 'graded';
    }

    private static function finalAssessmentPassed(Database $db, int $studentId, int $courseId): bool
    {
        $finalExam = $db->fetchOne(
            "SELECT id FROM exams WHERE course_id = ? AND type = 'final' ORDER BY start_datetime DESC LIMIT 1",
            [$courseId]
        );
        if (! $finalExam) {
            return true; // no final assessment defined for this course.
        }

        return (bool) $db->fetchOne(
            "SELECT 1 FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND is_passed = 1",
            [$finalExam['id'], $studentId]
        );
    }

    private static function walletDisputesClear(Database $db, int $studentId): bool
    {
        $openDispute = $db->fetchOne(
            "SELECT 1 FROM support_tickets WHERE user_id = ? AND subject LIKE 'Refund request%' AND status IN ('open', 'in_progress', 'waiting')",
            [$studentId]
        );

        return ! $openDispute;
    }
}
