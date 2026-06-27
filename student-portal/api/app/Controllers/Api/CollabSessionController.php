<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Collaborative Coding — docs/student-module/04h-apis-notebook-collab.md.
 * Originally designed around Pusher/Ably carrying the actual keystroke-
 * level Yjs CRDT sync directly between clients, with this REST API only
 * handling session membership/auth bootstrap. With no Pusher/Ably (or any
 * cloud service) anywhere in this build, peer connection setup reuses the
 * *exact* pure-P2P-WebRTC + polling-signal pattern ClassroomController
 * already established — `signal`/`pollSignals` here are line-for-line the
 * same mechanism, just addressed at `collab_signals`/`collab_session_id`
 * instead of `webrtc_signals`/`live_class_id`. Once connected, peers would
 * open a WebRTC *data channel* (not just audio/video) to carry Yjs update
 * messages directly, the same way ClassroomController's media tracks flow
 * peer-to-peer today.
 *
 * **Deliberate scope boundary**: this is the real, tested backend — no
 * client-side collaborative editor exists yet (no Yjs integration, no
 * code-editor UI component at all in this app, since there's no Coding
 * Sandbox to put one in). Building that frontend is a substantially larger,
 * separate undertaking than adapting this API doc, and is left for when
 * that UI is actually designed on purpose, not assembled as a side effect
 * of this pass.
 *
 * The "5-minutes-no-presence auto-end" rule (02d) was designed to fire from
 * a server-side listener on Pusher/Ably presence events — with no such
 * service, `cron/end-stale-collab-sessions.php` is the honest equivalent.
 */
class CollabSessionController extends Controller
{
    public function create(Request $request): void
    {
        $user = $this->currentUser();

        $id = $this->db->insertInto('collab_sessions', [
            'workspace_id' => $request->input('workspace_id') ?: null,
            'title' => $request->input('title') ?: null,
            'linked_live_class_id' => $request->input('linked_live_class_id') ?: null,
            'created_by' => $user['id'],
        ]);

        $this->db->insertInto('collab_participants', [
            'collab_session_id' => $id,
            'user_id' => $user['id'],
            'role' => 'owner',
        ]);

        $this->success(['id' => (int) $id], [], 201);
    }

    public function join(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $session = $this->requireActiveSession($id);

        $participant = $this->db->fetchOne(
            'SELECT * FROM collab_participants WHERE collab_session_id = ? AND user_id = ?',
            [$id, $user['id']]
        );

        if ($participant) {
            if ($participant['left_at']) {
                $this->db->updateTable('collab_participants', ['left_at' => null], 'id = ?', [$participant['id']]);
            }
            $role = $participant['role'];
        } else {
            $role = 'collaborator';
            $this->db->insertInto('collab_participants', [
                'collab_session_id' => $id,
                'user_id' => $user['id'],
                'role' => $role,
            ]);
        }

        $latestSnapshot = $this->db->fetchOne(
            'SELECT yjs_state FROM collab_snapshots WHERE collab_session_id = ? ORDER BY snapshot_at DESC LIMIT 1',
            [$id]
        );

        $this->success([
            // Same free, account-less Google STUN server as the Live
            // Classroom mesh — see ClassroomController's class docblock.
            'ice_servers' => [['urls' => 'stun:stun.l.google.com:19302']],
            'yjs_bootstrap' => $latestSnapshot ? base64_encode($latestSnapshot['yjs_state']) : null,
            // Informational only, not an enforced permission gate — a
            // session owner always has write access regardless of the
            // label shown (04h's explicit reasoning: a hard permission-
            // upgrade flow adds friction at exactly the moment, a student
            // stuck mid-problem, where friction costs the most).
            'role' => $role,
        ]);
    }

    public function leave(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $session = $this->requireActiveSession($id);

        $this->db->updateTable('collab_participants', ['left_at' => date('Y-m-d H:i:s')], 'collab_session_id = ? AND user_id = ? AND left_at IS NULL', [$id, $user['id']]);

        foreach ($this->currentParticipants((int) $id, (int) $user['id']) as $peer) {
            $this->db->insertInto('collab_signals', [
                'collab_session_id' => $id,
                'from_user_id' => $user['id'],
                'to_user_id' => $peer['user_id'],
                'type' => 'leave',
                'payload' => json_encode([]),
            ]);
        }

        $this->success(null);
    }

    /** The deliberate "we're done" case — the 5-minutes-no-presence auto-end is cron/end-stale-collab-sessions.php instead. */
    public function end(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $session = $this->requireActiveSession($id);

        $owner = $this->db->fetchOne("SELECT 1 FROM collab_participants WHERE collab_session_id = ? AND user_id = ? AND role = 'owner'", [$id, $user['id']]);
        if (! $owner) {
            $this->fail('Only the session owner can end this session.', ['reason' => ['not_owner']], 403);
        }

        $this->db->updateTable('collab_sessions', ['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->success(null);
    }

    public function show(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $session = $this->requireParticipant($id, (int) $user['id']);

        $participants = $this->db->select(
            "SELECT cp.user_id, cp.role, cp.joined_at, cp.left_at, u.first_name, u.last_name
             FROM collab_participants cp JOIN users u ON u.id = cp.user_id
             WHERE cp.collab_session_id = ? ORDER BY cp.joined_at",
            [$id]
        );

        $this->success([
            'id' => (int) $session['id'],
            'title' => $session['title'],
            'status' => $session['status'],
            'session_type' => $session['session_type'],
            'created_at' => $session['created_at'],
            'ended_at' => $session['ended_at'],
            'participants' => array_map(fn (array $p) => [
                'user_id' => (int) $p['user_id'],
                'name' => trim($p['first_name'] . ' ' . $p['last_name']),
                'role' => $p['role'],
                'is_present' => $p['left_at'] === null,
            ], $participants),
        ]);
    }

    public function signal(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $this->requireActiveSession($id);
        $this->ensureParticipant($id, (int) $user['id']);

        $toUserId = (int) $request->input('to_user_id', 0);
        $type = (string) $request->input('type', '');
        $payload = $request->input('payload', []);

        if (! $toUserId || ! in_array($type, ['offer', 'answer', 'ice_candidate'], true)) {
            $this->fail('to_user_id and a valid type are required.', ['type' => ['required|in:offer,answer,ice_candidate']]);
        }

        $this->db->insertInto('collab_signals', [
            'collab_session_id' => $id,
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
        $this->ensureParticipant($id, (int) $user['id']);

        $rows = $this->db->select(
            'SELECT * FROM collab_signals WHERE collab_session_id = ? AND to_user_id = ? AND consumed_at IS NULL ORDER BY id',
            [$id, $user['id']]
        );

        if ($rows) {
            $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $rows));
            $this->db->execute("UPDATE collab_signals SET consumed_at = NOW() WHERE id IN ({$ids})");
        }

        $this->success(array_map(fn (array $r) => [
            'from_user_id' => (int) $r['from_user_id'],
            'type' => $r['type'],
            'payload' => json_decode($r['payload'], true),
        ], $rows));
    }

    private function requireActiveSession(string $id): array
    {
        $session = $this->db->fetchOne("SELECT * FROM collab_sessions WHERE id = ? AND status = 'active'", [$id]);
        if (! $session) {
            $this->fail('No such session.', ['reason' => ['not_found']], 404);
        }
        return $session;
    }

    /**
     * show()/signal()/pollSignals() used to skip checking participation
     * entirely - collab_sessions.id is a sequential auto-increment column,
     * so any authenticated user could enumerate IDs and read another
     * pair's session title, status, and participant names, or poll/send
     * WebRTC signals into a session they never joined. join() is left
     * exactly as-is (anyone holding a session id legitimately joins it,
     * by design - 04h's documented sharing model); these calls now require
     * having already done that via join() first.
     */
    private function requireParticipant(string $id, int $userId): array
    {
        $session = $this->db->fetchOne('SELECT * FROM collab_sessions WHERE id = ?', [$id]);
        if (! $session) {
            $this->fail('No such session.', ['reason' => ['not_found']], 404);
        }
        $this->ensureParticipant($id, $userId);
        return $session;
    }

    private function ensureParticipant(string $id, int $userId): void
    {
        $participant = $this->db->fetchOne(
            'SELECT id FROM collab_participants WHERE collab_session_id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (! $participant) {
            $this->fail('Not a participant in this session.', ['reason' => ['not_participant']], 403);
        }
    }

    private function currentParticipants(int $sessionId, int $excludeUserId): array
    {
        $rows = $this->db->select(
            'SELECT user_id FROM collab_participants WHERE collab_session_id = ? AND user_id != ? AND left_at IS NULL',
            [$sessionId, $excludeUserId]
        );
        return array_map(fn (array $r) => ['user_id' => (int) $r['user_id']], $rows);
    }
}
