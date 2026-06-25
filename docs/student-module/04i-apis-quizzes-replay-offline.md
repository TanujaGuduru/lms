# Delivery Phase 4i — APIs: Live Quizzes, Code Replay, Offline Access, Calendar Sync

Covers lifecycle phases 34–37. Conventions per `04a`. Schema: `live_quizzes`/`live_quiz_responses`/`offline_downloads` (`03j`, new tables added alongside that workflow doc), `code_replay_sessions`/`code_replay_markers` (`02d`), `calendar_connections`/`calendar_sync_log` (`02e`).

---

## Live Quizzes

The launch, live leaderboard, and close are all **pushed over the live class's existing Pusher/Ably channel** (`04c`), never polled via REST — this section is only the response submission and the post-close read.

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/live-quizzes/{id}` | Current quiz state, for a client joining mid-push or reconnecting | Bearer |
| POST | `/live-quizzes/{id}/respond` | Submit an answer | Bearer |
| GET | `/live-quizzes/{id}/results` | Post-close results | Bearer |

### `GET /live-quizzes/{id}`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "quiz_type": "rapid_quiz", "question_text": "...", "options": ["A","B","C","D"],
    "launched_at": "2026-03-04T10:15:00Z", "duration_seconds": 30, "server_now": "2026-03-04T10:15:08Z"
  }
}
```
`server_now` rides alongside `launched_at`/`duration_seconds` specifically so the client computes its countdown from the server's clock, not from whenever the push notification happened to arrive locally — the same anchor-to-server-time principle `03f` already uses for exam timers, restated here because a quiz countdown is even more latency-sensitive.

### `POST /live-quizzes/{id}/respond`
**Request**: `{ "response_value": "B" }`
**Response (200)**: `{ "success": true, "data": { "is_correct": true, "points_awarded": 15 } }`
`response_time_ms` is computed **server-side** as time-of-receipt minus `launched_at` — never client-reported — which is what makes `rapid_quiz` speed scoring trustworthy. **Rejected past the deadline**: a request arriving after `launched_at + duration_seconds` gets `409` `"reason":"quiz_closed"`, regardless of what the client's local countdown showed (same server-anchored enforcement as the timing check itself) — this is a normal "you were too late," not treated as an error condition. **One response per student per quiz** (`uk_quiz_response`) — a second call returns `409` `"reason":"already_responded"` rather than overwriting; there's no changing an answer after submitting. A `quiz_won` result feeds `xp_transactions` automatically (`04h`'s gamification — no separate client call needed). A student who joined the class after `launched_at` simply has no quiz to respond to and isn't counted in participation — not penalized, just genuinely not there for it (`03j`'s explicit reasoning).

### `GET /live-quizzes/{id}/results`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "participation_rate": 0.87, "accuracy": 0.64, "avg_response_time_ms": 4200,
    "own_response": { "response_value": "B", "is_correct": true },
    "correct_answer": "B", "explain_mode_conversation_id": null
  }
}
```
On reveal, the client can start an AI Doubt Solver "explain mode" conversation (`04d`'s `POST /ai/conversations`) for a brief on-the-spot explanation — reusing that existing capability rather than this doc inventing a parallel explanation feature (`03j`'s explicit reuse point); `explain_mode_conversation_id` populates once the client does so.

---

## Code Replay

Schema is complete from `02d` — keystroke/execution event streams flow **client → S3 directly via presigned upload**, never through this API or MySQL. What's exposed here is session lifecycle and the lightweight marker/metadata writes.

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/code-replay-sessions` | Start (optionally `record: false` for free-form practice) | Bearer |
| POST | `/code-replay-sessions/{id}/markers` | Log a marker as it happens | Bearer |
| POST | `/code-replay-sessions/{id}/end` | Close, attach the final S3 event-log URL | Bearer |
| GET | `/code-replay-sessions?workspace_id=` | List own | Bearer |
| GET | `/code-replay-sessions/{id}` | Detail + markers (for the replay timeline) | Bearer |

### `POST /code-replay-sessions`
**Request**: `{ "workspace_id": 8821, "record": true }`
Recording **defaults on** for assignment/project work and live-class pairing sessions (highest teacher-review value) and is a **visible, explicit toggle** for free-form solo practice, where a student may prefer not to be recorded at all (`03j`'s stated default policy) — `record: false` for the latter simply skips creating markers/event-log attachment for the session, it isn't a partial or degraded recording.

### `POST /code-replay-sessions/{id}/end`
**Request**: `{ "s3_event_log_url": "https://s3.../session-8821.json.gz", "total_duration_seconds": 1840, "total_keystrokes": 3102, "total_executions": 14, "total_errors": 3 }`
The client has already flushed the compressed event stream straight to S3 in chunks during the session (presigned PUT, same direct-to-S3 pattern as `02d`) — this call just attaches the final pointer and totals. **If the client never calls this** (browser crash mid-recording), a server-side sweep closes the session using whatever `ended_at` can be inferred from the last successfully-flushed chunk, rather than leaving it in indefinite "still recording" limbo (`03j`'s explicit crash handling) — nothing for the client to call for that path; it's purely a backend job. **Post-session AI analysis** (repeated-mistake detection feeding `ai_insights`, surfaced later in `04e`'s progress analytics) runs asynchronously after `end` — never part of this call's response.

---

## Offline Access / Download Mode

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/offline-downloads` | Request a download | Bearer |
| GET | `/offline-downloads` | List own (active/expired/revoked) | Bearer |
| POST | `/offline-downloads/{id}/validate` | Periodic re-validation during playback | Bearer |
| POST | `/offline-downloads/{id}/revoke` | Student-initiated revoke (lost device) | Bearer |
| POST | `/lesson-progress/sync-offline` | Batch-sync progress accumulated while offline | Bearer |

### `POST /offline-downloads`
**Request**: `{ "content_type": "video_lesson", "content_id": 4471 }`
**Response (200)**: `{ "success": true, "data": { "download_token": "...", "package_url": "https://...", "expires_at": "2026-04-03T00:00:00Z" } }`
`403` `"reason":"download_not_enabled"` if the course/content doesn't have offline access enabled. The package is AES-128 HLS-encrypted (the deliberate non-Widevine tradeoff from the Phase 1 hosting addendum), and `expires_at` is a bounded window (7–30 days), never indefinite. **A request that would push this student's concurrent active downloads, or distinct `device_fingerprint`s in a short window, past a configured cap still succeeds but is flagged for account-sharing review** — surfaced for a human to check, not auto-blocked, since legitimate cases (a new phone) shouldn't be punished by an automatic hard rule (`03j`'s explicit reasoning).

### `POST /offline-downloads/{id}/validate`
**Response (200)**: `{ "success": true, "data": { "status": "active", "decryption_key": "..." } }` — called on each playback attempt while online, with a bounded grace period for genuinely offline stretches before re-validation is required again. Past `expires_at`: `403` `"reason":"download_expired"` — **the app refuses to decrypt the locally-stored file without this call succeeding**, since the encrypted bytes sitting on a device are useless without server-side validation (`03j`'s explicit point: the decryption capability is tied to the token, never permanently embedded client-side).

### `POST /lesson-progress/sync-offline`
**Request**: `{ "events": [ { "content_type": "video_lesson", "content_id": 4471, "progress_seconds": 612, "recorded_at": "2026-03-03T08:40:00Z" } ] }`
Each event applies as an ordinary progress update (same completion-threshold logic as `04c`'s `POST /lessons/{id}/progress`) using the client-recorded `recorded_at` as when it actually happened — a student who watched three lessons on a flight gets that progress, attributed to when they actually watched, but it's explicitly **not** treated as "live" for anything time-sensitive that depends on real-time presence (attendance, a live quiz response) — only progress/completion state is back-filled this way (`03j`'s explicit "isn't pretending to be live data" framing).

---

## Calendar Integration

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/calendar/providers` | Supported providers + this user's connection/reconnect status | Bearer |
| GET | `/calendar/connect/{provider}` | OAuth redirect URL | Bearer |
| DELETE | `/calendar/connections/{id}` | Disconnect | Bearer |
| POST | `/calendar/connections/{id}/reconnect` | Re-auth after a revoked/expired refresh token | Bearer |

Identical endpoints regardless of provider (`google`/`outlook`/`apple`) — Apple's CalDAV vs. Google/Outlook's REST APIs is purely a backend adapter difference, never something this client-facing surface branches on (`03j`'s explicit provider-agnostic design). **There is no "sync now" endpoint** — pushing a class/assignment-due-date/PTM/assessment event to the external calendar, and updating-or-deleting (never duplicating) the existing one via the stored `external_event_id` on reschedule, is entirely a background job reacting to the same domain events the Communication Engine (Phase 6) consumes, not something a client triggers.

### `GET /calendar/providers`
**Response (200)**:
```json
{ "success": true, "data": [
  { "provider": "google", "connected": true, "needs_reconnect": false },
  { "provider": "outlook", "connected": true, "needs_reconnect": true },
  { "provider": "apple", "connected": false, "needs_reconnect": false }
]}
```
`needs_reconnect: true` (from `calendar_connections.is_active=0`) is how a silently-failed refresh token (the user revoked access on the provider's side directly) surfaces to the user — prompting reconnection explicitly rather than syncing quietly failing forever with no visible signal (`03j`'s explicit reasoning).

---

## Next

Phase 4j — APIs for Achievement Wall, PTM Booking. This closes out Delivery Phase 4 — APIs. Say "continue."
