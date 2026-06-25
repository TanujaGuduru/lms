# Delivery Phase 4b — APIs: Skill Assessment, Batch Allocation, Scheduling

Covers lifecycle phases 4–6. Conventions per `04a`.

---

## Placement / Skill Assessment

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/placement/status` | Whether a placement test is required/pending/done | Bearer |
| POST | `/placement/start` | Begin the attempt | Bearer |
| POST | `/placement/{attemptId}/answer` | Auto-save one answer | Bearer |
| POST | `/placement/{attemptId}/submit` | Finalize the attempt | Bearer |
| GET | `/placement/{attemptId}/result` | Recommended level/course (once reviewed) | Bearer |
| POST | `/placement/{attemptId}/request-recheck` | Dispute a result, triggers a fresh attempt cycle | Bearer |

### `POST /placement/start`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "attempt_id": 88231, "duration_minutes": 40, "started_at": "2026-03-02T09:00:00Z",
    "questions": [
      { "id": 5521, "type": "mcq", "text": "Which loop runs at least once?", "options": ["for","while","do-while","none"] }
    ]
  }
}
```
**Business-rule failure (422)**: returns `"reason":"already_completed"` if an attempt already exists and is finalized — placement is one-shot per enrollment unless a recheck is explicitly approved (§ recheck endpoint), preventing a student from just retrying until they get the level they want.

### `GET /placement/{attemptId}/result`
**Response (200) — pending mentor review**:
```json
{ "success": true, "data": { "status": "pending_review", "ai_recommended_level": "intermediate" } }
```
**Response (200) — confirmed**:
```json
{
  "success": true,
  "data": {
    "status": "confirmed", "recommended_level": "intermediate", "recommended_course_id": 14,
    "scores": { "coding": 78, "logical_reasoning": 65, "communication": 70 }
  }
}
```
The client deliberately gets two different shapes depending on review state, rather than showing an unreviewed AI guess with the same confidence as a mentor-confirmed result — this mirrors the Phase 3b rule that nothing is "final" until a human has looked at it.

---

## Batch Allocation (read-mostly from the student side — allocation itself is system/ops-driven)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/batch/current` | Active batch details (teacher, schedule, batchmates count — not other students' identities for group batches, just the count) | Bearer |
| GET | `/batch/waitlist-status` | Position context if still waitlisted | Bearer |

### `GET /batch/waitlist-status`
**Response (200)**:
```json
{
  "success": true,
  "data": { "status": "waiting", "course_id": 14, "added_at": "2026-02-20T12:00:00Z", "estimated_wait": "1-2 weeks" }
}
```
`estimated_wait` is a coarse bucket (not a false-precision "you are #7 in queue," which would imply an ordering guarantee the allocation engine doesn't actually make — it allocates by best-fit match, not strict FIFO).

---

## Class Scheduling

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/schedule/upcoming` | Next N classes, localized to the viewer's timezone | Bearer |
| GET | `/schedule/calendar?month=2026-04` | Month view (classes + assignment due dates + PTMs) | Bearer |
| GET | `/schedule/classes/{id}` | Single class detail | Bearer |
| POST | `/reschedule-requests` | Request a reschedule/makeup | Bearer |
| GET | `/reschedule-requests` | List own requests | Bearer |
| POST | `/teacher-change-requests` | Submit a teacher change request | Bearer |
| GET | `/teacher-change-requests` | List own requests + status | Bearer |

### `GET /schedule/upcoming`
**Response (200)**:
```json
{
  "success": true,
  "data": [
    {
      "live_class_id": 99102, "title": "Loops & Conditionals",
      "start_local": "2026-03-05T17:00:00+05:30", "duration_minutes": 60,
      "teacher_name": "Rahul Sharma", "join_window_opens_at": "2026-03-05T16:45:00+05:30",
      "status": "scheduled"
    }
  ]
}
```
`start_local` is computed server-side from the canonical UTC `start_datetime` and the requesting user's timezone (`student_profiles.timezone`, or the parent's own if a parent is viewing) — the client never does its own UTC math, which is exactly the kind of thing that drifts wrong around Daylight Saving transitions if duplicated in two places (Phase 3b's stated reasoning, enforced here).

### `POST /reschedule-requests`
**Request**:
```json
{ "original_class_id": 99105, "requested_new_datetime": "2026-03-08T18:00:00+05:30", "reason": "Family function" }
```
**Response (201)**:
```json
{ "success": true, "data": { "id": 5512, "status": "auto_approved", "new_class_id": 99220 } }
```
or, when it needs human coordination:
```json
{ "success": true, "data": { "id": 5512, "status": "pending", "message": "We'll confirm within 24 hours." } }
```
**Validation**: `requested_new_datetime` required|date|after:now. **Business-rule failures (422)**: `"reason":"monthly_limit_exceeded"` (computed live against `reschedule_requests`, per Phase 3b — never a stale stored counter that could drift), `"reason":"insufficient_notice"` (inside the advance-notice window), `"reason":"slot_unavailable"`.

---

## Next

Phase 4c — APIs for Live Classroom, Attendance, Recordings, Video Library, Materials. Say "continue."
