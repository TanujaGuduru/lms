<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use DateTime;
use DateTimeZone;

/**
 * Class Scheduling — docs/student-module/04b-apis-assessment-scheduling.md.
 *
 * Auto-approval heuristics for reschedule requests ("auto_approved" vs
 * "pending" in the doc's two example responses) aren't specified anywhere in
 * the 35-document series beyond "computed live, never a stale counter" for
 * the monthly limit — every request lands as `pending` for human review in
 * this pass rather than inventing an unstated auto-approval policy.
 */
class ScheduleController extends Controller
{
    private const MAX_RESCHEDULES_PER_MONTH = 2;
    private const MIN_NOTICE_HOURS = 24;

    public function upcoming(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $limit = max(1, min(50, (int) $request->input('limit', 10)));
        $timezone = $this->viewerTimezone($studentId);

        $classes = $this->db->select(
            "SELECT lc.id, lc.title, lc.start_datetime, lc.duration_minutes, lc.status,
                    u.first_name AS teacher_first_name, u.last_name AS teacher_last_name
             FROM live_classes lc
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ? AND bs.status = 'active'
             JOIN users u ON u.id = lc.teacher_id
             WHERE lc.status IN ('scheduled', 'live') AND lc.start_datetime >= UTC_TIMESTAMP()
             ORDER BY lc.start_datetime ASC LIMIT ?",
            [$studentId, $limit]
        );

        $this->success(array_map(fn (array $c) => [
            'live_class_id' => (int) $c['id'],
            'title' => $c['title'],
            'start_local' => $this->toLocal($c['start_datetime'], $timezone),
            'duration_minutes' => (int) $c['duration_minutes'],
            'teacher_name' => trim($c['teacher_first_name'] . ' ' . $c['teacher_last_name']),
            'join_window_opens_at' => $this->toLocal($c['start_datetime'], $timezone, -15),
            'status' => $c['status'],
        ], $classes));
    }

    public function calendar(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $month = (string) $request->input('month', date('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->fail('month must be in YYYY-MM format.', ['month' => ['format:YYYY-MM']]);
        }

        $start = $month . '-01 00:00:00';
        $end = date('Y-m-t 23:59:59', strtotime($start));

        $classes = $this->db->select(
            "SELECT lc.id, lc.title, lc.start_datetime, 'class' AS event_type
             FROM live_classes lc
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ? AND bs.status = 'active'
             WHERE lc.start_datetime BETWEEN ? AND ?",
            [$studentId, $start, $end]
        );

        $assignments = $this->db->select(
            "SELECT a.id, a.title, a.due_date AS start_datetime, 'assignment_due' AS event_type
             FROM assignments a
             JOIN batch_students bs ON bs.batch_id = a.batch_id AND bs.student_id = ? AND bs.status = 'active'
             WHERE a.due_date BETWEEN ? AND ?",
            [$studentId, $start, $end]
        );

        $ptms = $this->db->select(
            "SELECT pb.id, 'Parent-Teacher Meeting' AS title,
                    CONCAT(ps.slot_date, ' ', ps.start_time) AS start_datetime, 'ptm' AS event_type
             FROM ptm_bookings pb JOIN ptm_slots ps ON ps.id = pb.slot_id
             WHERE pb.student_id = ? AND ps.slot_date BETWEEN ? AND ?",
            [$studentId, substr($start, 0, 10), substr($end, 0, 10)]
        );

        $events = array_merge($classes, $assignments, $ptms);
        usort($events, fn ($a, $b) => strcmp((string) $a['start_datetime'], (string) $b['start_datetime']));

        $this->success(array_map(fn (array $e) => [
            'id' => (int) $e['id'],
            'title' => $e['title'],
            'event_type' => $e['event_type'],
            'datetime' => $e['start_datetime'],
        ], $events));
    }

    public function classDetail(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];

        // 404, not 403 — never confirms a class exists for a batch the
        // student isn't in (same "don't leak existence" pattern as the rest
        // of this catalog).
        $class = $this->db->fetchOne(
            "SELECT lc.*, u.first_name AS teacher_first_name, u.last_name AS teacher_last_name
             FROM live_classes lc
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ? AND bs.status = 'active'
             JOIN users u ON u.id = lc.teacher_id
             WHERE lc.id = ?",
            [$studentId, $id]
        );

        if (! $class) {
            $this->fail('No such class.', ['reason' => ['not_found']], 404);
        }

        $timezone = $this->viewerTimezone($studentId);

        $this->success([
            'live_class_id' => (int) $class['id'],
            'title' => $class['title'],
            'description' => $class['description'],
            'start_local' => $this->toLocal($class['start_datetime'], $timezone),
            'duration_minutes' => (int) $class['duration_minutes'],
            'teacher_name' => trim($class['teacher_first_name'] . ' ' . $class['teacher_last_name']),
            'status' => $class['status'],
        ]);
    }

    public function createRescheduleRequest(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $originalClassId = (int) $request->input('original_class_id', 0);
        $requestedNew = (string) $request->input('requested_new_datetime', '');
        $reason = (string) $request->input('reason', '');

        if (! $originalClassId || ! $requestedNew || strtotime($requestedNew) <= time()) {
            $this->fail('original_class_id and a future requested_new_datetime are required.', [
                'requested_new_datetime' => ['required|date|after:now'],
            ]);
        }

        $original = $this->db->fetchOne(
            "SELECT lc.* FROM live_classes lc
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ? AND bs.status = 'active'
             WHERE lc.id = ?",
            [$studentId, $originalClassId]
        );

        if (! $original) {
            $this->fail('No such class.', ['reason' => ['not_found']], 404);
        }

        if (strtotime($original['start_datetime']) - time() < self::MIN_NOTICE_HOURS * 3600) {
            $this->fail('Too close to the class start time to reschedule.', ['reason' => ['insufficient_notice']], 422);
        }

        // Computed live against reschedule_requests every time — never a
        // stale stored counter that could drift (03b's explicit reasoning).
        $countThisMonth = $this->db->count(
            'reschedule_requests',
            "student_id = ? AND status IN ('approved','auto_approved') AND created_at >= ?",
            [$studentId, date('Y-m-01 00:00:00')]
        );

        if ($countThisMonth >= self::MAX_RESCHEDULES_PER_MONTH) {
            $this->fail('Monthly reschedule limit reached.', ['reason' => ['monthly_limit_exceeded']], 422);
        }

        $id = $this->db->insertInto('reschedule_requests', [
            'student_id' => $studentId,
            'original_class_id' => $originalClassId,
            'requested_new_datetime' => date('Y-m-d H:i:s', strtotime($requestedNew)),
            'reason' => $reason ?: null,
        ]);

        $this->success([
            'id' => (int) $id,
            'status' => 'pending',
            'message' => "We'll confirm within 24 hours.",
        ], [], 201);
    }

    public function rescheduleRequests(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT * FROM reschedule_requests WHERE student_id = ? ORDER BY created_at DESC',
            [$studentId]
        );
        $this->success($rows);
    }

    public function createTeacherChangeRequest(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $batchId = (int) $request->input('batch_id', 0);
        $reason = (string) $request->input('reason', '');
        $details = (string) $request->input('details', '');

        $validReasons = ['teaching_mismatch', 'language_mismatch', 'schedule_mismatch', 'complaint', 'other'];
        if (! $batchId || ! in_array($reason, $validReasons, true)) {
            $this->fail('batch_id and a valid reason are required.', [
                'reason' => ['required|in:' . implode(',', $validReasons)],
            ]);
        }

        $membership = $this->db->fetchOne(
            "SELECT bs.id FROM batch_students bs WHERE bs.batch_id = ? AND bs.student_id = ? AND bs.status = 'active'",
            [$batchId, $studentId]
        );
        if (! $membership) {
            $this->fail('Not currently in that batch.', ['reason' => ['not_in_batch']], 404);
        }

        $teacher = $this->db->fetchOne(
            "SELECT teacher_id FROM batch_teachers WHERE batch_id = ? ORDER BY (role = 'primary') DESC LIMIT 1",
            [$batchId]
        );

        $id = $this->db->insertInto('teacher_change_requests', [
            'student_id' => $studentId,
            'batch_id' => $batchId,
            'current_teacher_id' => $teacher['teacher_id'] ?? null,
            'reason' => $reason,
            'details' => $details ?: null,
        ]);

        $this->success(['id' => (int) $id, 'status' => 'pending'], [], 201);
    }

    public function teacherChangeRequests(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $rows = $this->db->select(
            'SELECT * FROM teacher_change_requests WHERE student_id = ? ORDER BY requested_at DESC',
            [$studentId]
        );
        $this->success($rows);
    }

    private function viewerTimezone(int $studentId): string
    {
        $row = $this->db->fetchOne('SELECT timezone FROM student_profiles WHERE user_id = ?', [$studentId]);
        return $row['timezone'] ?? 'Asia/Kolkata';
    }

    /**
     * Computed server-side from the canonical UTC start_datetime — the
     * client never does its own UTC math (04b's explicit reasoning: this is
     * exactly the kind of thing that drifts wrong around DST transitions if
     * duplicated in two places).
     */
    private function toLocal(string $utcDatetime, string $timezone, int $offsetMinutes = 0): string
    {
        try {
            $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return $utcDatetime;
        }

        if ($offsetMinutes !== 0) {
            $dt->modify($offsetMinutes . ' minutes');
        }

        try {
            $dt->setTimezone(new DateTimeZone($timezone));
        } catch (\Exception) {
            // Unknown/invalid timezone string — fall back to UTC rather than 500ing.
        }

        return $dt->format('c');
    }
}
