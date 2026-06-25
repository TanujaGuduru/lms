<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Batch Allocation (read-mostly) — docs/student-module/04b-apis-assessment-scheduling.md.
 */
class BatchController extends Controller
{
    public function current(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $batch = $this->db->fetchOne(
            "SELECT b.*, bs.id AS membership_id
             FROM batch_students bs JOIN batches b ON b.id = bs.batch_id
             WHERE bs.student_id = ? AND bs.status = 'active'
             ORDER BY bs.enrolled_at DESC LIMIT 1",
            [$studentId]
        );

        if (! $batch) {
            $this->fail('Not currently allocated to a batch.', ['reason' => ['no_active_batch']], 404);
        }

        $teacher = $this->db->fetchOne(
            "SELECT u.id, u.first_name, u.last_name FROM batch_teachers bt JOIN users u ON u.id = bt.teacher_id
             WHERE bt.batch_id = ? ORDER BY (bt.role = 'primary') DESC LIMIT 1",
            [$batch['id']]
        );

        $batchmateCount = $this->db->count('batch_students', "batch_id = ? AND status = 'active'", [$batch['id']]);

        $schedule = $this->db->select(
            'SELECT day_of_week, start_time, end_time FROM timetable WHERE batch_id = ? AND is_active = 1',
            [$batch['id']]
        );

        $this->success([
            'batch_id' => (int) $batch['id'],
            'name' => $batch['name'],
            'mode' => $batch['mode'],
            'teacher_name' => $teacher ? trim($teacher['first_name'] . ' ' . $teacher['last_name']) : null,
            // Other students' identities are never exposed for a group batch
            // — only the count (04b's explicit privacy note).
            'batchmate_count' => max(0, $batchmateCount - 1),
            'schedule' => $schedule,
        ]);
    }

    public function waitlistStatus(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $entry = $this->db->fetchOne(
            "SELECT * FROM batch_waitlist WHERE student_id = ? AND status = 'waiting' ORDER BY added_at DESC LIMIT 1",
            [$studentId]
        );

        if (! $entry) {
            $this->fail('Not currently waitlisted.', ['reason' => ['not_waitlisted']], 404);
        }

        $this->success([
            'status' => $entry['status'],
            'course_id' => (int) $entry['course_id'],
            'added_at' => $entry['added_at'],
            // A coarse bucket, not a false-precision queue position — the
            // allocation engine matches by best-fit, not strict FIFO (04b's
            // explicit reasoning).
            'estimated_wait' => $this->estimatedWaitBucket($entry['added_at']),
        ]);
    }

    private function estimatedWaitBucket(string $addedAt): string
    {
        $days = (int) floor((time() - strtotime($addedAt)) / 86400);

        return match (true) {
            $days < 3 => '1-2 weeks',
            $days < 10 => '1 week',
            $days < 21 => 'a few more days',
            default => 'longer than usual — flagged for ops review',
        };
    }
}
