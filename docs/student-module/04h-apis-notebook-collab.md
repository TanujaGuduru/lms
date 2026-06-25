# Delivery Phase 4h — APIs: Gamification, Digital Notebook, Collaborative Coding

Covers lifecycle phases 31–33. Conventions per `04a`. Schema is already complete from `02c` (Notebook), `02d` (Collaborative Coding), `02e` (Gamification) — no amendments needed in this phase.

---

## Gamification

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/gamification/profile` | Own `total_xp`, `current_level`, streak status | Bearer |
| GET | `/gamification/xp-history` | Paginated `xp_transactions` | Bearer |
| GET | `/gamification/badges` | All badges, with `earned_at` for the ones held | Bearer |
| GET | `/leaderboard?scope=course\|global&course_id=` | Top 100 by `total_xp` | Bearer |

**There is no `POST` endpoint that awards XP, badges, or extends a streak anywhere in this catalog — deliberately.** Every award is a server-side side effect of an event that already has its own endpoint elsewhere (a class attended via `04c`'s attendance, an assignment submitted via `04d`, a project completed via `04e`, a referral converted via `04g`) — checked against `xp_transactions`/`badges.criteria_type` immediately after that event, not via any call this client makes directly (`03i`'s explicit "check, not a separate trigger" design). A client claiming "I should get XP for X" with no corresponding real event has nothing to call.

### `GET /gamification/badges`
**Response (200)**:
```json
{ "success": true, "data": [
  { "id": 12, "name": "7-Day Streak", "icon_url": "...", "earned_at": "2026-02-20T08:00:00Z" },
  { "id": 14, "name": "Project Master", "icon_url": "...", "earned_at": null, "locked": true }
]}
```
A badge already earned stays earned even if `criteria_value` is raised later (`03i`'s explicit no-retroactive-revocation rule) — this endpoint only ever reflects what's actually in `student_badges`, never re-evaluates past awards against current criteria.

### `GET /leaderboard`
Cached server-side for a short window (e.g. 5 minutes) — a plain indexed `ORDER BY total_xp DESC` query, not a correctness concern, purely avoiding identical re-queries across many dashboard loads in the same few minutes (`03i`'s explicit reasoning, and the GoDaddy-driven fallback from Redis sorted sets to a MySQL query established in `02e`).

---

## Digital Notebook

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/notes?course_id=&tag=&favorite=` | List / filter own notes | Bearer |
| POST | `/notes` | Create | Bearer |
| GET | `/notes/{id}` | Detail | Bearer |
| PATCH | `/notes/{id}` | Autosave content/title/tags/favorite | Bearer |
| DELETE | `/notes/{id}` | Soft delete | Bearer |
| GET | `/notes/{id}/versions` | Version history | Bearer |
| POST | `/notes/voice-transcribe` | Upload audio, get back transcribed text | Bearer |
| POST | `/recordings/{id}/generate-notes` | One-click AI notes from a ready transcript | Bearer |
| POST | `/notes/{id}/summarize` | AI summary — returned as a suggestion | Bearer |
| POST | `/notes/{id}/flashcards` | AI-extract Q&A pairs into a deck | Bearer |
| GET | `/flashcards/due` | Cards due for spaced-repetition review | Bearer |
| POST | `/flashcards/{id}/review` | Record a review outcome | Bearer |
| POST | `/notes/{id}/generate-quiz` | AI-generate a practice quiz from this note | Bearer |

`POST /recordings/{id}/bookmarks/{bookmarkId}/save-to-note` was already listed in `04c`'s table without a detailed write-up — the detail belongs here: it creates-or-appends a note with the bookmark's timestamp + label and sets `recording_bookmarks.linked_note_id`, which is the mechanism that turns "Recording 32:15 — explains recursion" into something permanent and searchable rather than just an entry in the player's bookmark list (`03i`'s explicit framing).

### `PATCH /notes/{id}`
Debounced autosave straight to `notes.content` — a full `note_versions` snapshot is only written on a coarser threshold (every N autosaves, or once per editing session), never on every keystroke, since the live autosaved content already protects against data loss (`02c`'s explicit reasoning for why `note_versions` doesn't grow unboundedly).

### `POST /notes/voice-transcribe`
**Request**: multipart audio blob. **Response (200)**: `{ "success": true, "data": { "transcribed_text": "..." } }` — inserted at the cursor client-side, and **always editable afterward, never locked as read-only AI output** (`03i`'s explicit reasoning: background noise, accents, and coding jargon all mean transcription sometimes comes back wrong, so editability is the actual correctness mechanism, not optional polish). **Failure mode**: `422` `"reason":"recording_too_long"` past a configured cap — a clear reason, not a silent truncation or a generic timeout, so the student knows *why* a 20-minute recording didn't transcribe.

### `POST /recordings/{id}/generate-notes`
Only callable once `class_recordings`'s transcript is ready (`04c`). **Deliberately one-click, never automatic per class** — every class auto-spawning a summary would clutter the notebook with notes nobody asked for (`03i`'s explicit reasoning); the student decides what's worth keeping. **Response (200)**: the created note, with `is_ai_generated=1` and `linked_live_class_id` set — the client badges this visibly in the UI, which is the actual safeguard against a hallucinated detail being mistaken for the student's own understanding, not anything server-enforced beyond the flag itself.

### `POST /notes/{id}/summarize`
**Response (200)**: `{ "success": true, "data": { "suggested_summary": "..." } }` — a suggestion only. It **never overwrites `notes.content` directly**; the client offers replace-or-append, and either choice is then just an ordinary `PATCH /notes/{id}` call, same as any other edit.

### `POST /flashcards/{id}/review`
**Request**: `{ "outcome": "again" | "hard" | "good" | "easy" }`
**Response (200)**: updated `next_review_at`/`review_count`. The scheduling itself is a deterministic spaced-repetition algorithm (SM-2-style interval growth) running in the Laravel app, not an AI Gateway call — worth being explicit about, since everything else in this section is AI-assisted and this one endpoint isn't.

### `POST /notes/{id}/generate-quiz`
**Response (200)**: `{ "success": true, "data": { "exam_id": 9981 } }` — creates an `exams` row with `source_note_id` set (`02c`'s `ALTER TABLE exams`) and AI-generated `questions`, reusing the exact same exam infrastructure as every other assessment in this catalog rather than a parallel quiz system (`03i`'s explicit reuse principle). The returned `exam_id` is then started the normal way, via `04e`'s `POST /exams/{id}/attempts`.

---

## Collaborative Coding

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/collab-sessions` | Create (optional `workspace_id`, `linked_live_class_id`) | Bearer |
| POST | `/collab-sessions/{id}/join` | Join — returns channel auth + doc bootstrap | Bearer |
| POST | `/collab-sessions/{id}/leave` | Explicit leave | Bearer |
| POST | `/collab-sessions/{id}/end` | Explicit end (final snapshot taken) | Bearer |
| GET | `/collab-sessions/{id}` | Detail + participants | Bearer |

### `POST /collab-sessions/{id}/join`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "pusher_channel": "private-collab-9910", "pusher_auth": "...",
    "yjs_bootstrap": "<latest collab_snapshots.yjs_state, base64>",
    "role": "collaborator"
  }
}
```
Real-time keystroke-level sync flows over Pusher/Ably **directly between clients** afterward — it never touches this REST API or MySQL (`02d`'s explicit design). This endpoint is purely the session-membership/auth bootstrap, the same join-token pattern already used for live classes (`04c`) and proctored exams (`04e`); it hands back the latest durability snapshot so a newly-joining or reconnecting client has something to render before the first real-time delta arrives. **`role` is informational, not an enforced permission gate**: a teacher joining a session always has write access regardless of the label shown — the distinction between "observing" and "actively editing" is a client-side cursor/highlight treatment, not something this API restricts, because a hard permission-upgrade flow would add friction at exactly the moment (a student stuck mid-problem) where friction costs the most (`03i`'s explicit reasoning).

### `POST /collab-sessions/{id}/end`
Exists for the deliberate "we're done" case. The 5-minutes-no-presence auto-end rule from `02d` fires from a server-side listener on Pusher/Ably presence events, not from any client call — there's nothing to expose here for that path; it already takes its own final snapshot before flipping `status='ended'`.

**Nothing in this section handles simultaneous edits or reconnect-after-disconnect** — both are solved entirely by Yjs's CRDT merge semantics on the client (`03i`'s explicit point: this is what choosing a CRDT buys you at the architecture level, not something a workflow or an API needs to compensate for).

---

## Next

Phase 4i — APIs for Live Quizzes, Code Replay, Offline Access, Calendar Sync. Say "continue."
