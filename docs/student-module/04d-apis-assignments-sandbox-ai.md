# Delivery Phase 4d — APIs: Assignments, Coding Sandbox, AI Doubt Solver, AI Coding Assistant

Covers lifecycle phases 14–17. Conventions per `04a`.

**Correction to how Assignments (14) fits here.** A first pass at this doc assumed `assignments`/`assignment_submissions` were an undefined gap and drafted new `CREATE TABLE` statements for them. That was wrong — both tables **already exist** in the original Admin panel schema (`database/schema.sql`, "SECTION" preceding Certificates), built before this student-module planning series started. `02d`'s FK (`code_workspaces.linked_assignment_id → assignments.id`) and `02e`'s `ALTER TABLE assignment_submissions ADD COLUMN ...` were correctly assuming those real, already-built tables, not a missing one — exactly as `03f` already states ("Reuses existing `assignments`/`assignment_submissions`... for projects (Phase 2e)"). Re-deriving them from scratch would have silently diverged from the live schema (different column names, a different `status` enum, no `module_id`, etc.). Corrected below: only the two amendments `03d`/`03f` actually called for, applied to the real table.

Grouped with the Sandbox/AI APIs because homework submission for `type='code'` assignments flows through `code_workspaces`/`code_executions`.

---

## Schema amendment: `assignment_submissions`

The real table (`database/schema.sql`) has `assignment_submissions.status ENUM('submitted','graded','returned','resubmitted')` — no `draft` state — and no per-student deadline override. `03d` §14 already called for both; neither was ever turned into SQL until now:

```sql
ALTER TABLE `assignment_submissions`
  MODIFY COLUMN `status` ENUM('draft','submitted','graded','returned','resubmitted') DEFAULT 'draft',
  ADD COLUMN `extended_due_date` DATETIME DEFAULT NULL COMMENT 'per-student override, checked in preference to assignments.due_date (3d)' AFTER `submitted_at`;
```

Everything else below uses the real, existing columns as-is: `assignments` (`type` ENUM `text`/`file`/`url`/`code`/`project`, `status` ENUM `draft`/`published`/`closed` — the assignment's own publish state, distinct from a submission's `status` — `due_date`, `late_submission_allowed`, `late_penalty_percent`, `total_marks`, `passing_marks`); `assignment_submissions` (`submission_text`, `file_path`, `file_name`, `url`, `is_late`, `marks_awarded`, `grade`, `feedback`, `graded_by`/`graded_at`). The `type='project'` case — and the `github_repo_url`/`demo_video_url`/`screenshots`/`originality_score` columns `02e` adds for it — is deliberately deferred to `04e`'s Project Lifecycle + Publishing section; everything here covers plain homework (`text`/`file`/`url`/`code`).

---

## Assignments

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/courses/{id}/assignments` | List assignments for an enrolled course (with own status/overdue per item) | Bearer |
| GET | `/assignments/{id}` | Detail incl. computed overdue flag | Bearer |
| GET | `/assignments/{id}/submission` | Own current submission (draft or final) | Bearer |
| PUT | `/assignments/{id}/submission` | Upsert the draft — autosave, no status change | Bearer |
| POST | `/assignments/{id}/submission/submit` | Finalize — sets `submitted_at`, `status='submitted'` (or `resubmitted`) | Bearer |

### `GET /assignments/{id}`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "id": 4410, "title": "Build a calculator CLI", "type": "code",
    "due_date": "2026-03-04T18:00:00Z", "late_submission_allowed": true, "late_penalty_percent": 10,
    "own_submission_status": "draft", "is_overdue": false
  }
}
```
Only assignments with `status` in (`published`,`closed`) are ever visible here — a `draft` assignment is a teacher-side authoring state the student API never exposes (`404`, not `403`, for the same "don't confirm it exists" reasoning used elsewhere in this catalog). `is_overdue` is computed at request time (`due_date`, or `assignment_submissions.extended_due_date` if set for this student, has passed **and** `status` is not in `submitted`/`graded`/`resubmitted`) — never a stored column, per `03d`'s reasoning that there's no real row to mark overdue for a student who submitted nothing at all.

### `PUT /assignments/{id}/submission`
**Request** (whichever fields fit the assignment's `type`):
```json
{ "submission_text": "...", "url": "https://github.com/..." }
```
For `type='code'`, the request instead carries `{ "workspace_id": 8821 }` — the server doesn't store a live pointer to the workspace (no such column exists, and a workspace keeps changing after a draft save); it reads `workspace_files` for that workspace at call time, zips the file set, uploads it, and writes the resulting location into the existing `file_path`/`file_name` columns — re-zipping and overwriting on every autosave, the same "live state, no row explosion" principle `02d` already applies to `workspace_files.content` itself.
**Response (200)**: the updated submission, `status` unchanged (stays `draft`, or stays `returned` if a teacher had reopened it — a draft autosave never advances status on its own). Called frequently (debounced client-side) — purely an upsert, not the SLA-relevant event.

### `POST /assignments/{id}/submission/submit`
**Response (200)**:
```json
{ "success": true, "data": { "id": 9012, "status": "submitted", "submitted_at": "2026-03-04T17:50:00Z", "is_late": false } }
```
**This is the actual 78-hour-SLA event** — the one action that sets `submitted_at`/`is_late` and is what the reminder/overdue logic evaluates (per `03d` §14, a draft save never counts). For `type='code'`, this is also the point where the workspace is zipped one final time into `file_path`/`file_name` (the draft-time zip above is a preview of what submitting would capture, not the official copy). **Validation/business rules**: `422` `"reason":"past_deadline_not_allowed"` if `late_submission_allowed=false` and past `due_date`/`extended_due_date`; if late but allowed, succeeds with `"is_late": true` (stored on the row, not recomputed later) and the response includes the `late_penalty_percent` that grading will apply — the penalty itself is applied to `marks_awarded` at grading time, not subtracted from anything at submission time, since there's no mark yet to penalize. A `submitted → graded` submission resubmitted after a teacher sets `status='returned'` lands here too, transitioning to `resubmitted`. Accepts `Idempotency-Key` (per `04a`) — a flaky GoDaddy connection retrying this call must never create a second submission event.

---

## Coding Sandbox / Cloud IDE

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/workspaces` | Create (language, optional `linked_course_id`/`linked_lesson_id`/`linked_assignment_id`) | Bearer |
| GET | `/workspaces` | List own, sorted by `last_opened_at` | Bearer |
| GET | `/workspaces/{id}` | Detail incl. files | Bearer |
| PATCH | `/workspaces/{id}` | Rename / touch `last_opened_at` | Bearer |
| DELETE | `/workspaces/{id}` | Soft delete | Bearer |
| POST | `/workspaces/{id}/files` | Add a file (multi-file projects) | Bearer |
| PUT | `/workspaces/{id}/files/{fileId}` | Autosave content | Bearer |
| DELETE | `/workspaces/{id}/files/{fileId}` | Remove a file | Bearer |
| GET | `/workspaces/{id}/files/{fileId}/versions` | Version history | Bearer |
| POST | `/workspaces/{id}/files/{fileId}/versions/{versionId}/restore` | Roll back to a snapshot | Bearer |
| POST | `/workspaces/{id}/run` | Execute — proxies to the sandbox VPS | Bearer |

### `POST /workspaces/{id}/run` — the one endpoint that talks to infrastructure outside Laravel/MySQL entirely
**Request**:
```json
{ "stdin": "", "trigger_source": "manual_run" }
```
`trigger_source` (`manual_run` / `assignment_submission` / `assessment` / `ai_assistant_check`) is client-supplied but only ever used for labeling the resulting `code_executions` row — it never changes authorization, since a sandbox run by itself isn't the SLA event (the Assignments `submit` endpoint above is what actually finalizes a homework hand-in; running code is just running code, however many times, before or after that).

**Response (200) — synchronous**, since the default wall-clock timeout is 10s (per `03e`) a blocking HTTP response is simpler than polling for this v1:
```json
{
  "success": true,
  "data": {
    "execution_id": 88231, "status": "completed",
    "stdout": "Enter two numbers:\n7\n", "stderr": "", "exit_code": 0,
    "execution_time_ms": 412, "memory_used_kb": 9120
  }
}
```
**Language branching mirrors `03e` exactly**: Python/JS/PHP run directly; Java/C/C++ return a distinct `compile_error` field (separate from `stderr`) when the build itself fails, so the client can render "didn't build" differently from "built and crashed"; HTML/CSS returns `{"preview_url": "..."}` instead of stdout/stderr — there's no real "execution" for that branch; SQL spins up a throwaway SQLite database seeded from the lesson/assignment's schema and returns a result-set table rather than text output. **Failure statuses** (`timeout`, `memory_exceeded`, `error`) come back as `success:true` with that `status` value, not an HTTP error — a timeout is an expected, valid outcome of running code, not a server fault. The request bundles every file in the workspace, not just the entry point, so cross-file includes/imports resolve.

### `POST /workspaces/{id}/files/{fileId}/versions/{versionId}/restore`
**Response (200)**: the file's `content` reverts to that version's snapshot; a new `file_versions` row is **not** created for the restored state itself until the next manual-save/pre-execution/periodic trigger fires naturally — restoring is a read-then-overwrite of the live `workspace_files.content`, not a special version-creating action of its own.

---

## AI Doubt Solver & AI Coding Assistant

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/ai/conversations?type=` | List own conversations (`doubt_solver` / `coding_assistant`) | Bearer |
| POST | `/ai/conversations` | Start one (`mode`, optional `linked_course_id`/`linked_lesson_id`/`linked_workspace_id`) | Bearer |
| GET | `/ai/conversations/{id}/messages` | Paginated history | Bearer |
| POST | `/ai/conversations/{id}/messages` | Send a message — streamed response | Bearer |
| POST | `/ai/conversations/{id}/escalate` | "Show me the fix" / "just give me the answer" escalation | Bearer |
| GET | `/ai/quota` | Current quota status for the logged-in student | Bearer |

### `POST /ai/conversations/{id}/messages` — the only streaming (SSE) endpoint in this catalog
**Request**:
```json
{ "content": "Why does my loop never end?", "code_context": { "workspace_id": 8821 } }
```
**Quota is checked before the LLM call, not after** (per `03e` §16) — if exhausted, the response is `429`:
```json
{ "success": false, "message": "Daily AI help limit reached", "errors": { "reason": "quota_exhausted" }, "data": { "resets_at": "2026-03-05T00:00:00Z" } }
```
Otherwise the response is `Content-Type: text/event-stream`, one `data:` frame per token/chunk, terminated by a final frame carrying the persisted message metadata:
```
data: {"delta": "It looks like the "}
data: {"delta": "condition on line 4 never changes."}
data: {"done": true, "message_id": 991233, "tokens_output": 47, "cost_usd": 0.0021}
```
The `ai_usage_quotas` increment happens atomically with the `ai_messages` insert server-side once the stream completes — not a client-driven follow-up call that could be skipped. **Near-duplicate detection** (3+ rephrased asks of the same question in one conversation) runs silently server-side and, when triggered, flags the conversation for mentor review — the streamed response to the student is unaffected and shows no error, since the point is a quiet escalation, not a visible block. **Content moderation** runs before any token is streamed; a flagged message returns `422` with a generic, age-appropriate message rather than the underlying classifier reasoning.

### `POST /ai/conversations/{id}/escalate`
**Request**: `{ "escalation_type": "show_fix" }`
**Response (200)**: the direct fix/answer, plus `{"sandbox_verified": true}` for `coding_assistant` conversations specifically — per `03e` §17, any suggested fix is run through `POST /workspaces/{id}/run` server-side before being shown as confirmed; if it doesn't actually resolve the original error, the response carries `"sandbox_verified": false` and softer wording ("here's an idea, but I'm not certain — try it and see") instead of a flat answer. Every call here is logged on the conversation (visible to the teacher) — this is the mechanism that makes a pattern of "always skips straight to the answer" visible rather than invisible, per the brief's teach-don't-spoon-feed requirement.

### `GET /ai/quota`
**Response (200)**:
```json
{ "success": true, "data": { "period": "daily", "messages_used": 8, "quota_limit_messages": 15, "resets_at": "2026-03-05T00:00:00Z" } }
```
Whether `doubt_solver` and `coding_assistant` draw from one shared pool or two separate ones is a per-deployment config flag, not hardcoded (per `03e`'s note that `ai_usage_quotas` is keyed by student+period precisely so either policy works without a schema change) — this endpoint returns whichever shape that config implies.

---

## Next

Phase 4e — APIs for Assessments (exams), Project Lifecycle + Publishing, Progress Analytics. Say "continue."
