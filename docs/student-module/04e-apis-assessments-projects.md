# Delivery Phase 4e — APIs: Assessments (Exams), Project Lifecycle + Publishing, Progress Analytics

Covers lifecycle phases 18–21. Conventions per `04a`. Reuses the existing Admin-panel `exams`/`questions`/`exam_attempts`/`exam_responses` tables (`database/schema.sql`) as-is — no amendment needed here, unlike `assignments` in `04d`. Project submission continues to use `assignments`(`type='project'`)/`assignment_submissions` from `04d`, plus `published_projects` from `02e`.

---

## Assessments (Exams)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/exams?course_id=` | List exams available to the student (within their batch's window) | Bearer |
| GET | `/exams/{id}` | Detail — duration, marks, attempts used/allowed, window | Bearer |
| POST | `/exams/{id}/attempts` | Start (or resume) an attempt | Bearer |
| GET | `/attempts/{id}` | Current state — questions + own responses so far | Bearer |
| PUT | `/attempts/{id}/responses/{questionId}` | Autosave one answer | Bearer |
| POST | `/attempts/{id}/cheating-flag` | Log a client-side blur/visibility event | Bearer |
| POST | `/attempts/{id}/submit` | Finalize | Bearer |
| GET | `/attempts/{id}/result` | Result, once released | Bearer |
| POST | `/attempts/{id}/proctor-token` | Agora token for proctored exams only | Bearer |

### `POST /exams/{id}/attempts`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "attempt_id": 55102, "started_at": "2026-03-04T10:00:00Z",
    "ends_at": "2026-03-04T11:00:00Z",
    "questions": [
      { "question_id": 881, "type": "mcq", "question_text": "...", "options": ["A","B","C","D"], "marks": 2 }
    ]
  }
}
```
Question and option order is **shuffled server-side, seeded deterministically by `attempt_id`** when `exams.shuffle_questions`/`shuffle_options` are set (per `03f` §18) — calling `GET /attempts/{id}` again mid-exam returns the identical order, so a page reload never re-shuffles and confuses the student about which question they were on. `correct_answer` is never included in this or any in-progress response. **Validation/business rules**: `409` `"reason":"max_attempts_reached"` if `attempt_number` would exceed `exams.max_attempts`; `403` `"reason":"outside_window"` if `now` isn't within `start_datetime`/`end_datetime`; a second call while an `in_progress` attempt already exists returns that same attempt rather than starting a new one (resumability, not a new row).

### `PUT /attempts/{id}/responses/{questionId}`
**Request**:
```json
{ "response": ["B"], "time_spent_seconds": 42 }
```
**Response (200)**: `{ "success": true }` — every answer persists to `exam_responses` as it's picked, **not just at final submit**, which is what makes mid-exam recovery possible at all (`03f`'s stated reason). For `type='coding'` questions, `response` instead carries the submitted code; grading runs it against the test cases stored in that question's `options`/`correct_answer` JSON via the same execution path as `04d`'s `POST /workspaces/{id}/run` (no separate sandbox integration — one execution path, reused). No deadline check happens here — the deadline is enforced once, at `submit`, server-side.

### `POST /attempts/{id}/submit`
**Response (200)**:
```json
{ "success": true, "data": { "status": "completed", "obtained_marks": 78, "percentage": 78.0, "is_passed": true, "result_visible": true } }
```
**Server-side timer enforcement, not client-trusted**: a request arriving after `started_at + duration_minutes` is rejected with the attempt auto-finalized server-side using whatever was already autosaved — a manipulated client clock or a closed browser tab changes nothing, since the **next** request of any kind against an expired attempt triggers the same auto-finalize before doing anything else (`03f`'s stated mechanism, not a background job racing the client). Auto-grading runs immediately for objective types (`mcq`/`msq`/`true_false`/`fill_blank`/`coding`); `short_answer`/`long_answer`/`viva`-equivalent types route to a manual grading queue and leave `obtained_marks` partially populated until a teacher grades them. `result_visible` reflects `exams.show_result_immediately` — `false` means the response still confirms successful submission but omits marks entirely (not a `0`, which would be misread as a failing score) until the teacher releases results.

### `POST /attempts/{id}/proctor-token`
Only callable when `exams.is_proctored=1` — same Agora-token shape and authorization-check pattern as `04c`'s `POST /classes/{id}/join-token`, reusing the recording infrastructure rather than a separate proctoring system, per `03f`'s explicit "kept opt-in per exam type" framing. Recording consent is checked the same way live-class recording consent is, since this is the same underlying mechanism with a different trigger.

---

## Project Lifecycle + Publishing

Project work uses the **same** Assignments endpoints from `04d` (`PUT/POST .../submission`) — `assignments.type='project'` rather than a parallel API, per `03f` §19–20. What's new here is the publishing step:

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/submissions/{id}` | Own submission detail incl. `originality_score`/`plagiarism_report_url` once available | Bearer |
| POST | `/submissions/{id}/publish-request` | Request publishing a graded project to the portfolio | Bearer |
| GET | `/submissions/mine/publish-requests` | Own publish requests + status | Bearer |

### `PUT /assignments/{id}/submission` — project-specific fields, same endpoint as `04d`
**Request**:
```json
{ "url": "https://myproject.example.com", "github_repo_url": "https://github.com/...", "demo_video_url": "https://...", "screenshots": ["https://s3.../1.png"] }
```
A project submission **combines several of these at once rather than forcing one type** (`03f`'s explicit requirement) — all are plain columns on the same `assignment_submissions` row (`02e`'s `ALTER TABLE`), so any subset can be present.

### `POST /assignments/{id}/submission/submit` — what's different for `type='project'`
Same finalize semantics as `04d`, plus: submitting a project queues an **asynchronous** originality check (embedding-similarity against prior submissions, plus a public-code plagiarism service where applicable) that populates `originality_score`/`plagiarism_report_url` sometime after the response returns — the submit call itself never blocks on it. **This is advisory, never an auto-reject gate** (`03f`'s explicit reasoning: shared starter code and a student building on their own prior work both produce false positives) — a low score surfaces on the teacher's grading queue as a flag, and is never surfaced to the student as a rejection or pre-emptively withheld from grading.

### `POST /submissions/{id}/publish-request`
**Response (200)**:
```json
{ "success": true, "data": { "submission_id": 9012, "published_project_id": 441, "is_public": false, "status": "pending_approval" } }
```
Creates the `published_projects` row immediately but `is_public=0` — nothing reaches the public wall (`04j`, Achievement Showcase) without an explicit mentor/admin approval step setting `approved_by`/`approved_at` (an Admin/Teacher-portal action, not callable from this API), consistent with the consent-first posture `02e` already establishes given published work is tied to a minor's name. **Validation**: `422` `"reason":"not_graded_yet"` if the submission's `status` isn't `graded` — nothing unfinished gets queued for publishing. A second call while a `published_projects` row already exists for this submission returns the existing one (`uk_published_submission` is unique per submission) rather than erroring or duplicating.

---

## Progress Analytics

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/progress/snapshot?enrollment_id=` | Latest precomputed snapshot | Bearer |
| GET | `/progress/history?enrollment_id=&days=30` | Snapshot series, for trend charts | Bearer |
| GET | `/progress/insights` | `ai_insights` entries (repeated mistakes, strengths) | Bearer |

### `GET /progress/snapshot?enrollment_id=`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "snapshot_date": "2026-03-04", "attendance_percent": 92, "course_completion_percent": 64,
    "assignment_completion_percent": 80, "avg_project_score": 88.5, "avg_assessment_score": 76.0,
    "coding_success_rate": 71.4
  }
}
```
This always reads the **latest precomputed `student_progress_snapshots` row** — there is no live-aggregation path and no write endpoint; a nightly cron (`02e`'s Phase 21 design) is the only thing that ever populates this table, accepting up to 24h staleness on trend data deliberately, since a GoDaddy shared-hosting box doing multi-table joins/aggregations on every dashboard load is a real performance risk, not a theoretical one. **Anything that needs to be live bypasses this entirely**: credit balance is `GET /wallet` (`04a`), today's class status is `GET /classes/{id}` (`04c`) — this endpoint is specifically for the expensive, slow-changing view, not a blanket proxy for every stat.

### `GET /progress/insights`
Surfaces `ai_insights` rows (`repeated_mistake`/`weak_topic`/`improvement_area`/`strength`), cross-referenced against the `coding_success_rate` trend from the snapshot series — the "coding improvement" feature is this pairing specifically: not just *that* a student is improving, but *what* (per `03f`'s closing point), so the response groups insights by `insight_type` rather than returning a flat reverse-chronological feed.

---

## Next

Phase 4f — APIs for AI Risk Detection (internal), Parent Visibility, Monthly Parent Reports, Payments/Refunds/Freeze. Say "continue."
