# Delivery Phase 3d — Workflows: Recordings, Video Library, Materials, Assignments

Covers lifecycle phases 11–14. Tables from `02c`. The 78-hour homework SLA is the most precisely specified rule in your brief, so it gets the most detail below.

---

## 11. Session Recording Pipeline

### Business workflow

1. `live_classes.status='completed'` → Agora's recording-stopped webhook fires → `class_recordings` row created, `raw_recording_url` set, `processing_status='pending'`.
2. A queued job hands the raw capture to the video API (Mux/Cloudinary, per the GoDaddy hosting addendum — transcoding never happens on the GoDaddy box itself) → `processing_status='processing'`.
3. Video API returns adaptive-bitrate renditions + thumbnail + (where supported) auto-captions → `processed_video_url`, `thumbnail_url`, `duration_seconds`, `file_size_bytes` populate, `processing_status='completed'`.
4. Transcript generation tracked independently (`transcript_status`) since it can finish before or after video processing — once done, `transcript_text` is indexed into MySQL FULLTEXT (or the managed search service, per the hosting addendum's search tier) for in-recording search.
5. `available_at` is set once both processing and a basic automated quality check (duration sanity, file-size sanity) pass — not necessarily the instant transcoding finishes, so a corrupted or truncated output never silently reaches students.

### Edge cases & failure handling

- **Capture failed entirely** (no raw file produced — rare, but Agora cloud recording isn't infallible): `processing_status='failed'` immediately, ops alerted. If unrecoverable, the affected students are offered a compensating makeup session — recorded as the resolution, not left as a silent gap in their content access.
- **Corrupted output**: caught by the automated sanity checks in step 5 (a 45-minute class producing a 12-second video is clearly wrong) → `processing_status='corrupted'`, retried from the raw capture if Agora's retention window still has it, otherwise the same makeup-session fallback as a full capture failure.
- **Delayed availability** (long sessions sometimes take longer to finalize on the provider side): handled with exponential-backoff polling and a bounded `retry_count`, not a single check-and-give-up — ops is only alerted if the delay exceeds a threshold (e.g., 2 hours), not on every individual retry.

---

## 12. Video Lecture Library

Builds on existing `lessons` / `lesson_progress` / `lesson_bookmarks` (Phase 2c) — no new tables.

### Business workflow

1. Student opens a lesson → `lesson_progress` row created on first play (`status='in_progress'`).
2. Playback position is beaconed periodically (lightweight, not per-frame) to update `progress_seconds` — reopening the lesson seeks the player to that position automatically.
3. **Completion threshold is configurable, not 100%** (default ~90% watched) — credit lands once someone's clearly finished the substantive content, since requiring the literal last second (often just an outro) would understate completion for people who genuinely finished.
4. Bookmarks (`lesson_bookmarks`) let a student timestamp a moment with a note, independent of the Notebook (Phase 2c) — though a bookmark can also be promoted into a full note from the same UI affordance.
5. Subtitles render from `lessons.subtitle_url` if a teacher supplied one; otherwise generated through the same transcription step used for class recordings, so there's one transcription pipeline, not two.

### Edge cases
- **Resume across devices**: since `lesson_progress` is server-side per student+lesson (not a browser-local position), switching from laptop to phone resumes correctly — this only works because progress was never stored client-side in the first place.

---

## 13. Notes / Materials (teacher-provided)

### Business workflow

1. Teacher/admin uploads a material → `course_materials` row + `material_versions` v1.
2. Student access is gated by enrollment (and, if the course has `drip_content` enabled, by module-unlock progress) — checked at request time, not cached, so revoking access (e.g., enrollment lapses) takes effect immediately rather than on some delayed sync.
3. Re-uploads create a new `material_versions` row and bump `course_materials.current_version`; **the old version stays accessible**, not deleted — a student who started from v1 may need to see exactly what changed, and silently swapping the file out from under them mid-course is a worse experience than keeping history.
4. Every download is logged (`material_downloads`) — this doubles as analytics and as the access record support pulls up if a student claims "I never got the worksheet."

---

## 14. Homework / Assignments — the 78-hour SLA Engine

### Business workflow

1. **Deadline computation**: on creation, `assignments.due_date` defaults to `created_at + 78 hours` — not an arbitrary teacher-picked date. A teacher *can* override it for a specific reason (e.g., a project-style assignment that genuinely needs more time), but the system-generated default is what makes the reminder cadence below meaningful and consistent across the platform.
2. **Submission states**: the existing `assignment_submissions.status` enum (`submitted`/`graded`/`returned`/`resubmitted`) is missing a **draft** state — adding one is a one-line schema amendment (`ADD 'draft' to the ENUM`) needed for correctness here. A student can save work-in-progress (draft) without it counting as their official submission; only an explicit "Submit" action sets `submitted_at` and is what the 78-hour check evaluates. Without a draft state, the SLA clock would effectively be checking the wrong thing — partial autosaves, not the actual hand-in.
3. **Reminder cadence**, computed relative to `due_date` (so a teacher-extended deadline naturally reschedules every reminder, not just the final one):
   - **T+24h** (54h remaining)
   - **T+48h** (30h remaining)
   - **T+72h** (6h remaining)
   - **T+77h** (1h remaining — final, urgent tone)
   
   Each reminder is a cron-driven job scanning for assignments whose release time crosses that exact tier within the job's polling window, sent only to students who haven't yet submitted (status not in `submitted`/`graded`/`resubmitted`). **Idempotency**: before sending, check `communication_logs` for an existing row with `trigger_event='assignment_reminder_24h'` (etc.) for that student+assignment — a job re-run (cron retry, overlapping window) must never double-send the same reminder.
4. **At T+78h**: the assignment becomes "overdue" for any student without a qualifying submission. This is **computed at read/report time** (`due_date` passed AND no `submitted`/`graded`/`resubmitted` row exists, or the row is still `draft`), not a stored status — there's no submission row to mark overdue for a student who never submitted anything at all, so forcing a stored "overdue" state would mean fabricating rows that don't represent real student action.
5. **Late submission policy**: governed by `assignments.late_submission_allowed` + `late_penalty_percent` (existing columns) — a late submission past T+78h is still accepted if allowed, scored, then penalized by the configured percentage; if not allowed, the submission UI simply closes at T+78h.

### Per-student deadline exceptions
A global `due_date` extension (whole class struggling) is just editing `assignments.due_date` — every reminder and the overdue check recompute against the new value automatically. An **individual** extension (one student was sick) needs its own field rather than moving the whole class's deadline: `assignment_submissions.extended_due_date DATETIME NULL`, checked in preference to `assignments.due_date` for that one student's reminder/overdue evaluation when set.

### Resubmission & grading state machine
`submitted → graded` is terminal by default — a graded assignment isn't silently reopened by a late resubmission attempt. To allow revision, a teacher explicitly sets `status='returned'`, which re-opens submission (`resubmitted` once the student acts on it), and it can be graded again. This prevents a student from quietly overwriting a graded submission after the fact, while still supporting "redo this and resubmit" as a deliberate teacher action.

### Edge cases & failure handling
- **Resubmission timing**: the *final* submission's timestamp is what's evaluated against the deadline, not an earlier draft's — a student who saved a draft at T+10h but didn't hit Submit until T+80h is late, full stop; drafting early doesn't bank an on-time status.
- **Reminder job overlap on shared hosting** (cron ticks can occasionally overlap on a slow box): the idempotency check against `communication_logs` is what actually prevents duplicate sends — the job being "at-least-once" rather than "exactly-once" is fine precisely because the idempotency check exists.

---

## Next

Phase 3e — Coding Sandbox, AI Doubt Solver, AI Coding Assistant workflows. Say "continue."
