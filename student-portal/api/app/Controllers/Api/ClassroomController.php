<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Gamification;
use App\Core\Request;

/**
 * Live Classroom — pure peer-to-peer WebRTC, by deliberate choice: this
 * platform runs entirely on GoDaddy shared hosting for the long term, with
 * no external video/relay/realtime service of any kind (no Agora, no
 * Pusher/Ably) — not even as a "for now" placeholder. Every participant's
 * browser connects directly to every other participant's browser; this
 * server's only job is signaling (exchanging the connection-setup messages
 * WebRTC needs) over plain polled HTTP, since shared hosting can't hold a
 * persistent WebSocket connection open anyway.
 *
 * Real, load-bearing limitation, stated plainly rather than glossed over:
 * full-mesh P2P means every participant uploads their own video stream
 * directly to every other participant, so bandwidth cost per participant
 * scales with class size — this is workable for small classes (the actual
 * batch sizes this platform uses) and would not scale to a large lecture
 * hall without a relay server this hosting model can't provide. There is
 * also no TURN relay (a real server cost this plan deliberately avoids) —
 * the free public STUN server below resolves NAT for the large majority of
 * home/office networks, but a peer behind a symmetric/restrictive NAT can
 * still fail to connect directly. Both tradeoffs are the direct, accepted
 * cost of "no cloud service of any kind," not bugs.
 */
class ClassroomController extends Controller
{
    private const JOIN_WINDOW_MINUTES_BEFORE = 15;
    private const HEARTBEAT_INTERVAL_SECONDS = 30;
    private const PRESENCE_TIMEOUT_SECONDS = 45; // ~1.5x heartbeat — a peer who's missed this long is treated as gone

    public function join(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->authorizeJoin($classId, $user);

        $now = date('Y-m-d H:i:s');
        $this->db->insertInto('attendance', [
            'batch_id' => $class['batch_id'],
            'student_id' => $user['id'],
            'live_class_id' => $class['id'],
            'session_date' => date('Y-m-d'),
            'join_time' => $now,
            'leave_time' => $now,
            'marked_method' => 'auto_join',
            'status' => 'present',
        ]);

        $this->success([
            // Google's STUN server is free, public, and requires no account
            // or key — it only helps a peer discover its own reachable
            // address, it never carries any media or signaling traffic.
            'ice_servers' => [['urls' => 'stun:stun.l.google.com:19302']],
            'self' => ['user_id' => (int) $user['id'], 'name' => trim($user['first_name'] . ' ' . $user['last_name'])],
            'participants' => $this->currentParticipants((int) $class['id'], (int) $user['id']),
        ]);
    }

    public function heartbeat(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->fetchOne(
            'SELECT * FROM attendance WHERE live_class_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1',
            [$class['id'], $user['id']]
        );

        // Single-window join/leave tracking, not a full multi-interval union
        // — a reconnect after a long gap starts a fresh attendance row
        // rather than reopening the old one. A documented scope cut, not an
        // oversight (see the equivalent note this replaced).
        $staleGap = $existing && $existing['leave_time']
            && (time() - strtotime($existing['leave_time'])) > self::PRESENCE_TIMEOUT_SECONDS;

        if (! $existing || $staleGap) {
            $this->db->insertInto('attendance', [
                'batch_id' => $class['batch_id'],
                'student_id' => $user['id'],
                'live_class_id' => $class['id'],
                'session_date' => date('Y-m-d'),
                'join_time' => $now,
                'leave_time' => $now,
                'marked_method' => 'auto_join',
                'status' => 'present',
            ]);
        } else {
            $duration = strtotime($now) - strtotime($existing['join_time']);
            $this->db->updateTable('attendance', [
                'leave_time' => $now,
                'duration_seconds' => $duration,
                'attendance_percent' => min(100, (int) round($duration / ($class['duration_minutes'] * 60) * 100)),
            ], 'id = ?', [$existing['id']]);
        }

        // Returned on every heartbeat so the client discovers new joiners
        // without a separate poll loop just for presence.
        $this->success(['participants' => $this->currentParticipants((int) $class['id'], (int) $user['id'])]);
    }

    public function leave(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);

        $existing = $this->db->fetchOne(
            'SELECT * FROM attendance WHERE live_class_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1',
            [$class['id'], $user['id']]
        );

        if ($existing) {
            $now = date('Y-m-d H:i:s');
            $duration = strtotime($now) - strtotime($existing['join_time']);
            $this->db->updateTable('attendance', [
                'leave_time' => $now,
                'duration_seconds' => $duration,
                'attendance_percent' => min(100, (int) round($duration / ($class['duration_minutes'] * 60) * 100)),
            ], 'id = ?', [$existing['id']]);

            // 04h: "a class attended via 04c's attendance" is the documented
            // XP trigger — checked here, not via any separate call a client makes.
            Gamification::awardXp($this->db, (int) $user['id'], 10, 'class_attended', 'attendance', (int) $existing['id']);
        }

        // Tell every other current participant explicitly, so their browsers
        // tear down the now-dead RTCPeerConnection immediately rather than
        // waiting for it to time out on its own.
        foreach ($this->currentParticipants((int) $class['id'], (int) $user['id']) as $peer) {
            $this->db->insertInto('webrtc_signals', [
                'live_class_id' => $class['id'],
                'from_user_id' => $user['id'],
                'to_user_id' => $peer['user_id'],
                'type' => 'leave',
                'payload' => json_encode([]),
            ]);
        }

        $this->success(null);
    }

    public function show(Request $request, string $classId): void
    {
        $user = $this->currentUser();

        $class = $this->db->fetchOne(
            "SELECT lc.*, u.first_name AS teacher_first_name, u.last_name AS teacher_last_name
             FROM live_classes lc
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ? AND bs.status = 'active'
             JOIN users u ON u.id = lc.teacher_id
             WHERE lc.id = ?",
            [$user['id'], $classId]
        );

        if (! $class) {
            $this->fail('No such class.', ['reason' => ['not_found']], 404);
        }

        $this->success([
            'id' => (int) $class['id'],
            'title' => $class['title'],
            'status' => $class['status'],
            'start_datetime' => $class['start_datetime'],
            'duration_minutes' => (int) $class['duration_minutes'],
            'teacher_name' => trim($class['teacher_first_name'] . ' ' . $class['teacher_last_name']),
        ]);
    }

    /**
     * One signaling message addressed to one specific peer (mesh topology —
     * a student with N other participants sends N copies of an SDP
     * offer/answer or ICE candidate, one per peer, exactly mirroring how a
     * real mesh actually negotiates pairwise connections).
     */
    public function signal(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);

        $toUserId = (int) $request->input('to_user_id', 0);
        $type = (string) $request->input('type', '');
        $payload = $request->input('payload', []);

        if (! $toUserId || ! in_array($type, ['offer', 'answer', 'ice_candidate'], true)) {
            $this->fail('to_user_id and a valid type are required.', [
                'type' => ['required|in:offer,answer,ice_candidate'],
            ]);
        }

        $this->db->insertInto('webrtc_signals', [
            'live_class_id' => $class['id'],
            'from_user_id' => $user['id'],
            'to_user_id' => $toUserId,
            'type' => $type,
            'payload' => json_encode($payload),
        ]);

        $this->success(null);
    }

    /**
     * Polled every ~1-2s by the client — this *is* the signaling channel,
     * standing in for what a WebSocket/Pusher push would normally do. Each
     * row is marked consumed immediately so a slow client never double-
     * processes the same offer/candidate on the next poll.
     */
    public function pollSignals(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);

        $rows = $this->db->select(
            "SELECT * FROM webrtc_signals WHERE live_class_id = ? AND to_user_id = ? AND consumed_at IS NULL ORDER BY id",
            [$class['id'], $user['id']]
        );

        if ($rows) {
            $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $rows));
            $this->db->execute("UPDATE webrtc_signals SET consumed_at = NOW() WHERE id IN ({$ids})");
        }

        $this->success(array_map(fn (array $r) => [
            'from_user_id' => (int) $r['from_user_id'],
            'type' => $r['type'],
            'payload' => json_decode($r['payload'], true),
        ], $rows));
    }

    /**
     * Broadcast-style chat, deliberately separate from the pairwise
     * webrtc_signals channel — every current participant polls the same
     * room-wide message list rather than this needing N addressed copies.
     */
    public function sendChatMessage(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);
        $message = trim((string) $request->input('message', ''));

        if (! $message) {
            $this->fail('message is required.', ['message' => ['required']]);
        }
        if (mb_strlen($message) > 1000) {
            $this->fail('Message is too long.', ['message' => ['max:1000']]);
        }

        $id = $this->db->insertInto('live_class_chat_messages', [
            'live_class_id' => $class['id'],
            'user_id' => $user['id'],
            'message' => $message,
        ]);

        $this->success(['id' => (int) $id]);
    }

    public function pollChatMessages(Request $request, string $classId): void
    {
        $user = $this->currentUser();
        $class = $this->requireEnrolledClass($classId, $user);
        $afterId = (int) $request->input('after_id', 0);

        $rows = $this->db->select(
            "SELECT m.id, m.user_id, u.first_name, u.last_name, m.message, m.created_at
             FROM live_class_chat_messages m JOIN users u ON u.id = m.user_id
             WHERE m.live_class_id = ? AND m.id > ? ORDER BY m.id",
            [$class['id'], $afterId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'user_id' => (int) $r['user_id'],
            'name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'message' => $r['message'],
            'created_at' => $r['created_at'],
        ], $rows));
    }

    private function authorizeJoin(string $classId, array $user): array
    {
        $class = $this->requireEnrolledClass($classId, $user);

        if ($user['status'] !== 'active') {
            $this->fail('Account is not active.', ['reason' => ['account_pending_consent']], 403);
        }

        $joinWindowStart = strtotime($class['start_datetime']) - self::JOIN_WINDOW_MINUTES_BEFORE * 60;
        if ($class['status'] !== 'live' && time() < $joinWindowStart) {
            $this->fail('This class is not open yet.', ['reason' => ['class_not_open']], 403);
        }
        if (in_array($class['status'], ['cancelled', 'completed'], true)) {
            $this->fail('This class is not open.', ['reason' => ['class_not_open']], 403);
        }

        return $class;
    }

    /**
     * Every endpoint here except join() (which calls authorizeJoin() instead,
     * layering its own time-window/status checks on top of this same check)
     * used to call requireClass() alone - which only verified the class row
     * exists, not that the caller's batch is actually enrolled in it. That
     * let any authenticated student heartbeat/leave (fabricating attendance),
     * signal, or read/post chat for a live class belonging to a batch they
     * were never in. Fixed by routing every one of them through this.
     */
    private function requireEnrolledClass(string $classId, array $user): array
    {
        $class = $this->requireClass($classId);

        $membership = $this->db->fetchOne(
            "SELECT id FROM batch_students WHERE batch_id = ? AND student_id = ? AND status = 'active'",
            [$class['batch_id'], $user['id']]
        );
        if (! $membership) {
            $this->fail('Not enrolled in this class.', ['reason' => ['not_enrolled']], 403);
        }

        return $class;
    }

    private function requireClass(string $classId): array
    {
        $class = $this->db->fetchOne('SELECT * FROM live_classes WHERE id = ?', [$classId]);
        if (! $class) {
            $this->fail('No such class.', ['reason' => ['not_found']], 404);
        }
        return $class;
    }

    /**
     * "Currently present" is derived from recent heartbeats, not a separate
     * presence table — the same attendance row already being written is the
     * single source of truth for both attendance *and* who's live right now.
     */
    private function currentParticipants(int $liveClassId, int $excludeUserId): array
    {
        $rows = $this->db->select(
            "SELECT a.student_id, u.first_name, u.last_name
             FROM attendance a JOIN users u ON u.id = a.student_id
             WHERE a.live_class_id = ? AND a.student_id != ?
               AND a.leave_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$liveClassId, $excludeUserId, self::PRESENCE_TIMEOUT_SECONDS]
        );

        return array_map(fn (array $r) => [
            'user_id' => (int) $r['student_id'],
            'name' => trim($r['first_name'] . ' ' . $r['last_name']),
        ], $rows);
    }
}
