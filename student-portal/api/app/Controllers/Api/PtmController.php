<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use PDOException;

/**
 * Parent-Teacher Meeting (PTM) Booking — docs/student-module/04j-apis-showcase-ptm.md.
 * No cloud adaptation needed for booking/slots/cancel — pure SQL + a real
 * DB-level race guard (`ptm_bookings.uk_ptm_slot`), exactly as specified.
 *
 * **`meeting_link`** is the one real addition beyond the doc's own table:
 * the doc only describes the *link* existing, not how the call itself
 * works — this build answers that the same way Live Classroom/
 * Collaborative Coding do, with `join`/`signal`/`pollSignals` reusing the
 * exact pure-P2P-WebRTC + polling pattern (`ptm_signals`, a third parallel
 * table). Only two participants, so no presence/mesh-of-many complexity —
 * see `web/ptm-room.html`.
 *
 * **No academic-head role exists** in this schema (`roles` has exactly
 * Super Admin/Admin/Teacher/Student/Parent) — `concern_discussion`/
 * `performance_intervention`/`renewal_counseling` slots are filtered to
 * Admin/Super Admin hosts as the closest real equivalent, documented here
 * rather than guessed silently.
 *
 * **No calendar auto-sync on booking** — 04i's Calendar `connect`/
 * `reconnect` (the real OAuth2 flow) was deferred, so no
 * `calendar_connections` row will ever hold a real, working token to sync
 * against; wiring a sync call here would have nothing real to call.
 */
class PtmController extends Controller
{
    public function slots(Request $request): void
    {
        $parentId = (int) $this->currentUser()['id'];
        $studentId = (int) $request->input('student_id', 0);
        $meetingType = (string) $request->input('meeting_type', 'progress_review');

        $this->requireCanBookPtm($parentId, $studentId);

        $hostIds = $this->relevantHostIds($studentId, $meetingType);
        if (empty($hostIds)) {
            $this->success([]);
        }

        $placeholders = implode(',', array_fill(0, count($hostIds), '?'));
        $rows = $this->db->select(
            "SELECT ps.id, ps.host_id, ps.slot_date, ps.start_time, ps.end_time, u.first_name, u.last_name
             FROM ptm_slots ps JOIN users u ON u.id = ps.host_id
             WHERE ps.is_booked = 0 AND ps.host_id IN ({$placeholders}) AND ps.slot_date >= CURDATE()
             ORDER BY ps.slot_date, ps.start_time",
            $hostIds
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'host_name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'slot_date' => $r['slot_date'],
            'start_time' => $r['start_time'],
            'end_time' => $r['end_time'],
        ], $rows));
    }

    public function book(Request $request): void
    {
        $parentId = (int) $this->currentUser()['id'];
        $slotId = (int) $request->input('slot_id', 0);
        $studentId = (int) $request->input('student_id', 0);
        $meetingType = (string) $request->input('meeting_type', 'progress_review');

        $this->requireCanBookPtm($parentId, $studentId);

        $slot = $this->db->fetchOne('SELECT * FROM ptm_slots WHERE id = ? AND is_booked = 0', [$slotId]);
        if (! $slot) {
            $this->fail('This slot is no longer available.', ['reason' => ['slot_already_booked']], 409);
        }

        try {
            $bookingId = $this->db->transaction(function () use ($slotId, $parentId, $studentId, $meetingType, $request) {
                // The actual race guard is uk_ptm_slot (a DB unique constraint),
                // not this is_booked flag — the flag is a fast-path check, the
                // INSERT below is what a concurrent second request really fails on.
                $id = $this->db->insertInto('ptm_bookings', [
                    'slot_id' => $slotId,
                    'parent_id' => $parentId,
                    'student_id' => $studentId,
                    'meeting_type' => $meetingType,
                    'pre_meeting_notes' => $request->input('pre_meeting_notes') ?: null,
                ]);
                $this->db->updateTable('ptm_slots', ['is_booked' => 1], 'id = ?', [$slotId]);
                return $id;
            });
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'uk_ptm_slot')) {
                $this->fail('This slot was just booked by someone else.', ['reason' => ['slot_already_booked']], 409);
            }
            throw $e;
        }

        $appConfig = require BASE_PATH . '/config/app.php';
        $meetingLink = rtrim($appConfig['frontend_url'], '/') . "/ptm-room.html?booking_id={$bookingId}";
        $this->db->updateTable('ptm_bookings', ['meeting_link' => $meetingLink], 'id = ?', [$bookingId]);

        $this->success(['booking_id' => (int) $bookingId, 'meeting_link' => $meetingLink, 'status' => 'scheduled']);
    }

    public function myBookings(Request $request): void
    {
        $parentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT pb.id, pb.meeting_type, pb.status, ps.slot_date, ps.start_time, u.first_name, u.last_name
             FROM ptm_bookings pb JOIN ptm_slots ps ON ps.id = pb.slot_id JOIN users u ON u.id = ps.host_id
             WHERE pb.parent_id = ? ORDER BY ps.slot_date DESC, ps.start_time DESC",
            [$parentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'meeting_type' => $r['meeting_type'],
            'status' => $r['status'],
            'host_name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'slot_date' => $r['slot_date'],
            'start_time' => $r['start_time'],
        ], $rows));
    }

    public function show(Request $request, string $id): void
    {
        $booking = $this->ownBooking($id);

        $summary = $booking['status'] === 'completed'
            ? $this->db->fetchOne('SELECT summary, action_items, follow_up_date FROM ptm_summaries WHERE booking_id = ?', [$id])
            : null;

        $this->success([
            'id' => (int) $booking['id'],
            'meeting_type' => $booking['meeting_type'],
            'status' => $booking['status'],
            'meeting_link' => $booking['meeting_link'],
            'pre_meeting_notes' => $booking['pre_meeting_notes'],
            'summary' => $summary ? [
                'summary' => $summary['summary'],
                'action_items' => $summary['action_items'] ? json_decode($summary['action_items'], true) : null,
                'follow_up_date' => $summary['follow_up_date'],
            ] : null,
        ]);
    }

    /** No minimum-notice cutoff for a parent-side cancellation — only a host-side one is specified, and none is invented here (04j's explicit rule). */
    public function cancel(Request $request, string $id): void
    {
        $booking = $this->ownBooking($id);

        if ($booking['status'] !== 'scheduled') {
            $this->fail('Only a scheduled booking can be cancelled.', ['reason' => ['not_scheduled']], 422);
        }

        $this->db->transaction(function () use ($booking) {
            $this->db->updateTable('ptm_bookings', ['status' => 'cancelled'], 'id = ?', [$booking['id']]);
            $this->db->updateTable('ptm_slots', ['is_booked' => 0], 'id = ?', [$booking['slot_id']]);
        });

        $this->success(true);
    }

    /** A PTM booking always has exactly 2 known participants (parent + host) — no heartbeat/discovery needed, unlike a classroom where many students join over time. */
    public function join(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $booking = $this->requireParticipant($id, (int) $user['id']);

        $otherUserId = (int) $user['id'] === (int) $booking['parent_id'] ? (int) $booking['host_id'] : (int) $booking['parent_id'];
        $other = $this->db->fetchOne('SELECT first_name, last_name FROM users WHERE id = ?', [$otherUserId]);

        $this->success([
            'ice_servers' => [['urls' => 'stun:stun.l.google.com:19302']],
            'self' => ['user_id' => (int) $user['id']],
            'other' => ['user_id' => $otherUserId, 'name' => trim($other['first_name'] . ' ' . $other['last_name'])],
        ]);
    }

    public function signal(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $this->requireParticipant($id, (int) $user['id']);

        $toUserId = (int) $request->input('to_user_id', 0);
        $type = (string) $request->input('type', '');
        $payload = $request->input('payload', []);

        if (! $toUserId || ! in_array($type, ['offer', 'answer', 'ice_candidate'], true)) {
            $this->fail('to_user_id and a valid type are required.', ['type' => ['required|in:offer,answer,ice_candidate']]);
        }

        $this->db->insertInto('ptm_signals', [
            'booking_id' => $id,
            'from_user_id' => $user['id'],
            'to_user_id' => $toUserId,
            'type' => $type,
            'payload' => json_encode($payload),
        ]);

        $this->success(null);
    }

    public function pollSignals(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $this->requireParticipant($id, (int) $user['id']);

        $rows = $this->db->select(
            'SELECT * FROM ptm_signals WHERE booking_id = ? AND to_user_id = ? AND consumed_at IS NULL ORDER BY id',
            [$id, $user['id']]
        );

        if ($rows) {
            $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $rows));
            $this->db->execute("UPDATE ptm_signals SET consumed_at = NOW() WHERE id IN ({$ids})");
        }

        $this->success(array_map(fn (array $r) => [
            'from_user_id' => (int) $r['from_user_id'],
            'type' => $r['type'],
            'payload' => json_decode($r['payload'], true),
        ], $rows));
    }

    /** The child's own teacher (from real live_classes.teacher_id assignment) for progress_review; Admin/Super Admin for everything else (the closest real equivalent to "academic head" — see class docblock). */
    private function relevantHostIds(int $studentId, string $meetingType): array
    {
        if ($meetingType === 'progress_review') {
            $rows = $this->db->select(
                "SELECT DISTINCT lc.teacher_id FROM live_classes lc
                 JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?",
                [$studentId]
            );
            return array_map(fn (array $r) => (int) $r['teacher_id'], $rows);
        }

        $rows = $this->db->select(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin', 'super_admin')"
        );
        return array_map(fn (array $r) => (int) $r['id'], $rows);
    }

    private function requireCanBookPtm(int $parentId, int $studentId): void
    {
        if (! $studentId) {
            $this->fail('student_id is required.', ['student_id' => ['required']]);
        }

        $link = $this->db->fetchOne(
            "SELECT can_book_ptm FROM parent_student_links WHERE parent_id = ? AND student_id = ? AND consent_status = 'granted'",
            [$parentId, $studentId]
        );
        if (! $link) {
            $this->fail('No such linked student.', ['reason' => ['not_found']], 404);
        }
        if (! $link['can_book_ptm']) {
            $this->fail('PTM booking is not enabled for this guardian.', ['reason' => ['can_book_ptm_not_granted']], 403);
        }
    }

    private function ownBooking(string $id): array
    {
        $parentId = (int) $this->currentUser()['id'];
        $booking = $this->db->fetchOne('SELECT * FROM ptm_bookings WHERE id = ? AND parent_id = ?', [$id, $parentId]);

        if (! $booking) {
            $this->fail('No such booking.', ['reason' => ['not_found']], 404);
        }

        return $booking;
    }

    /** Either the booking's parent or its host (the teacher/admin on the other end of the call) may join. */
    private function requireParticipant(string $id, int $userId): array
    {
        $booking = $this->db->fetchOne(
            "SELECT pb.*, ps.host_id FROM ptm_bookings pb JOIN ptm_slots ps ON ps.id = pb.slot_id
             WHERE pb.id = ? AND pb.status = 'scheduled' AND (pb.parent_id = ? OR ps.host_id = ?)",
            [$id, $userId, $userId]
        );

        if (! $booking) {
            $this->fail('No such booking.', ['reason' => ['not_found']], 404);
        }

        return $booking;
    }
}
