# Delivery Phase 4c — APIs: Live Classroom, Attendance, Recordings, Video Library, Materials

Covers lifecycle phases 7–8, 11–13. Conventions per `04a`.

---

## Live Classroom

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/classes/{id}/join-token` | Issue a short-lived Agora RTC token | Bearer |
| POST | `/classes/{id}/heartbeat` | Periodic in-call ping, feeds attendance duration | Bearer |
| POST | `/classes/{id}/leave` | Explicit leave signal | Bearer |
| GET | `/classes/{id}` | Class detail (status, teacher, linked resources) | Bearer |

### `POST /classes/{id}/join-token` — the security-critical endpoint in this entire catalog
**Request**: empty body — the path parameter and the bearer token are the only inputs; nothing client-supplied is trusted for the authorization decision itself.
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "agora_token": "006abc...", "channel_name": "lc_99102_x7f3a9",
    "uid": 9931, "role": "audience_or_host", "expires_at": "2026-03-05T17:30:00Z"
  }
}
```
**Authorization checks performed, in order, every single time** (per Phase 3c — this is the actual enforcement point, not the UI):
1. Requesting user is in `batch_students` for this class's batch.
2. Requesting user's account `status='active'` (not `pending_consent`).
3. Class `status='live'` or current time is within the configured join window before `start_datetime`.

Any failure returns `403` with a specific `reason` (`not_enrolled` / `account_pending_consent` / `class_not_open`) — never a bare 403, since the client needs to render the right explanation (a "consent pending" message is very different from "this class hasn't started yet").
**Token TTL is short** (matches the class's expected duration + a small buffer) — a token doesn't outlive its purpose, so a leaked token from one session is useless shortly after.

### `POST /classes/{id}/heartbeat`
**Request**: `{}`  (just an authenticated ping on an interval, e.g. every 30s, while connected)
**Response (200)**: `{ "success": true }`
**Server-side effect**: extends `attendance.leave_time` for the current join interval — a gap longer than the heartbeat interval × a small multiplier (e.g., 2x) is treated as a disconnect, closing that interval (Phase 3c's multi-interval union logic for duration calculation).

---

## Attendance (read-only from the student/parent side — it's written by the classroom system itself, not by direct API calls)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/attendance/history?course_id=` | Per-class attendance history | Bearer |
| GET | `/attendance/summary?enrollment_id=` | Aggregate % for the enrollment | Bearer |

No `POST`/`PATCH` attendance endpoints exist in the student-facing API at all — manual overrides are an Admin/Teacher-portal action, never something the student app can call, which is a deliberate boundary, not an oversight.

---

## Recordings

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/recordings?batch_id=` | List available recordings | Bearer |
| GET | `/recordings/{id}` | Full detail incl. `processed_video_url`, `transcript_text` | Bearer |
| POST | `/recordings/{id}/progress` | Update watch position | Bearer |
| GET | `/recordings/{id}/bookmarks` | List own bookmarks | Bearer |
| POST | `/recordings/{id}/bookmarks` | Create a bookmark | Bearer |
| POST | `/recordings/{id}/bookmarks/{bookmarkId}/save-to-note` | Promote a bookmark into the Notebook | Bearer |
| GET | `/recordings/search?q=` | Full-text transcript search across accessible recordings | Bearer |

### `GET /recordings/{id}`
**Response (200) — processing not yet finished**:
```json
{ "success": true, "data": { "id": 771, "processing_status": "processing", "available_at": null } }
```
**Response (200) — ready**:
```json
{
  "success": true,
  "data": {
    "id": 771, "processing_status": "completed",
    "processed_video_url": "https://stream.example.com/771/master.m3u8",
    "thumbnail_url": "https://stream.example.com/771/thumb.jpg",
    "duration_seconds": 2640, "transcript_available": true,
    "last_position_seconds": 540
  }
}
```
Access control here is **not** a stored ACL check — it's a live join against `batch_students` for the batch the class belongs to (Phase 3d's stated design), so the response is `404` (deliberately not `403`, to avoid confirming a recording exists at all to someone not entitled to know) for any recording outside the requester's enrollment history.

---

## Video Lecture Library

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/courses/{id}/modules` | Modules + lessons with per-lesson progress | Bearer |
| GET | `/lessons/{id}` | Lesson detail (video URL, subtitle, transcript) | Bearer |
| POST | `/lessons/{id}/progress` | Update playback position | Bearer |
| GET | `/lessons/{id}/bookmarks` | List bookmarks | Bearer |
| POST | `/lessons/{id}/bookmarks` | Create a bookmark |  Bearer |

### `POST /lessons/{id}/progress`
**Request**:
```json
{ "progress_seconds": 412, "playback_speed": 1.25 }
```
**Response (200)**:
```json
{ "success": true, "data": { "status": "in_progress", "completed": false } }
```
Once `progress_seconds` crosses the configurable completion threshold (default ~90% of `video_duration`), the same call's response flips to `"status":"completed","completed":true` — completion is a side effect of an ordinary progress update, not a separate explicit "mark complete" action the client has to remember to call.

---

## Materials

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/courses/{id}/materials` | List materials for an enrolled course | Bearer |
| GET | `/materials/{id}/download` | Logs the download, returns a signed S3 URL | Bearer |
| GET | `/materials/{id}/versions` | Version history (older versions stay accessible) | Bearer |

### `GET /materials/{id}/download`
**Response (200)**:
```json
{ "success": true, "data": { "url": "https://s3.../signed?...", "expires_in": 300 } }
```
The signed URL is short-lived (5 minutes) — long enough to start a download, short enough that the link isn't useful if copied and shared outside the platform. Every call logs a `material_downloads` row regardless of whether the resulting URL is ever actually used, since the **request** itself (not the download completing) is the access event support needs visibility into.

---

## Next

Phase 4d — APIs for Coding Sandbox, AI Doubt Solver, AI Coding Assistant. Say "continue."
