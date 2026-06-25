<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Attendance — docs/student-module/04c-apis-classroom-content.md.
 * Read-only from the student/parent side, deliberately — there is no
 * POST/PATCH here at all; manual overrides are an Admin/Teacher-portal
 * action, never callable from this API.
 */
class AttendanceController extends Controller
{
    public function history(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $courseId = (int) $request->input('course_id', 0);

        if (! $courseId) {
            $this->fail('course_id is required.', ['course_id' => ['required']]);
        }

        $rows = $this->db->select(
            "SELECT a.live_class_id, a.session_date, a.status, a.attendance_percent, a.marked_method, lc.title
             FROM attendance a
             JOIN live_classes lc ON lc.id = a.live_class_id
             JOIN batches b ON b.id = lc.batch_id
             WHERE a.student_id = ? AND b.course_id = ?
             ORDER BY a.session_date DESC",
            [$studentId, $courseId]
        );

        $this->success($rows);
    }

    public function summary(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollmentId = (int) $request->input('enrollment_id', 0);

        if (! $enrollmentId) {
            $this->fail('enrollment_id is required.', ['enrollment_id' => ['required']]);
        }

        $enrollment = $this->db->fetchOne(
            'SELECT * FROM enrollments WHERE id = ? AND user_id = ?',
            [$enrollmentId, $studentId]
        );
        if (! $enrollment) {
            $this->fail('No such enrollment.', ['reason' => ['not_found']], 404);
        }

        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_sessions,
                SUM(a.status IN ('present', 'partial')) AS attended_sessions,
                AVG(a.attendance_percent) AS avg_attendance_percent
             FROM attendance a
             JOIN live_classes lc ON lc.id = a.live_class_id
             JOIN batches b ON b.id = lc.batch_id
             WHERE a.student_id = ? AND b.course_id = ?",
            [$studentId, $enrollment['course_id']]
        );

        $this->success([
            'total_sessions' => (int) ($row['total_sessions'] ?? 0),
            'attended_sessions' => (int) ($row['attended_sessions'] ?? 0),
            'avg_attendance_percent' => $row['avg_attendance_percent'] !== null ? round((float) $row['avg_attendance_percent'], 1) : 0.0,
        ]);
    }
}
