# Delivery Phase 3b — Workflows: Skill Assessment, Batch Allocation, Class Scheduling

Covers lifecycle phases 4–6. Tables from `02b-schema-assessment-scheduling.md`. APIs, comms templates, and scaling are covered in Phases 4/6/7.

---

## 4. Skill Assessment (Placement Engine)

### Business workflow

1. Once the account is `active` (consent cleared), if the purchased course requires placement (not all do — a strictly sequential beginner-only course doesn't), the system assigns a placement test: an `exams` row with `type='placement'`.
2. Student takes it within a deadline (default 48–72h post-enrollment) — objective sections (coding MCQs, logic puzzles) auto-graded; a short open-ended/communication section is either AI-scored (Phase 5) or deferred to a brief live mini-check with a mentor for borderline cases.
3. `exam_attempts` records the raw attempt; `placement_results` records the multi-dimensional scores (coding / logical reasoning / communication) and the AI-computed `recommended_level` + `recommended_course_id` + `recommended_batch_type`.
4. **A mentor reviews before it's final** — `placement_results.reviewed_by` is set even when the mentor simply confirms the AI's call. This isn't bureaucratic box-ticking: it's the check against an AI recommendation conflicting with what was actually sold ("parent paid for Advanced track, AI says Beginner" needs a human in the loop, not a silent override either way).
5. Confirmed result feeds Batch Allocation (§5).

### Why the demo's assessment isn't reused as the final placement
`demo_skill_assessments` (captured during the sales-context demo, Phase 3a) is treated as a *signal*, not the academic placement itself. A demo is short, sales-oriented, and the teacher running it may not be the same one teaching the course — relying on it for real placement risks both rushed/optimistic scoring and a mismatch with the actual curriculum track. A formal placement test runs post-enrollment specifically to avoid that bias, unless the course design makes placement moot.

### UI screens
- Placement test landing/instructions screen, the test-taking UI itself (reuses the Assessment UI from Phase 3f), a "your recommended track" result screen with a clear explanation (age-appropriate language for younger students) and a "request a re-check" option

### Edge cases & failure handling
- **Test abandoned / deadline passed**: defaults to a conservative (Beginner) placement, flagged `ai_generated=1, reviewed_by=NULL` so a mentor knows to re-assess once the student actually engages — never leaves a student stuck with no placement at all.
- **Borderline score**: explicitly routed to mentor review rather than auto-decided by a score threshold — the recommendation engine flags "borderline" as its own outcome, not just picking the nearer level.
- **Student/parent disputes placement**: self-service "request re-assessment" creates a new `exams`/`exam_attempts` cycle rather than mutating the original result (preserves history of why the placement changed).

---

## 5. Batch Allocation

### Business workflow

1. Allocation engine searches `batches` for a match on: `course_id`, `batch_type`, `skill_level` (exact match or the batch is `mixed`), age range overlap (`min_age`/`max_age`), timezone compatibility (`primary_timezone` within a configurable offset window), and remaining capacity (`max_students` vs. current `batch_students` count).
2. **Match found with capacity** → insert `batch_students`, log `batch_allocation_log` (`reason='initial_allocation'`).
3. **No match / batch full** → insert `batch_waitlist` (`status='waiting'`).
4. **Waitlist threshold crossed** for a given `course_id` + `batch_type` (configurable, e.g. 6 for group batches) → triggers batch-creation workflow: ops is notified with a shortlist of teachers who have matching expertise/availability (cross-referencing existing `timetable` for conflicts); once a teacher is confirmed, a new `batches` row is created and the waiting students are bulk-allocated, each logged in `batch_allocation_log` (`reason='overflow_split'`).

### 1-on-1 is a different matching problem than group
For `batch_type='one_on_one'` (`max_students=1`), "finding a batch" is really "finding an available teacher slot." The engine matches against teacher availability (checked against the teacher's existing `timetable`/`live_classes`) and expertise tags rather than searching for an under-filled batch — there's no such thing as a partially-filled 1-on-1 batch to find.

### UI screens
- Student/parent: "finding your batch" status screen (with expected-wait messaging if waitlisted), batch confirmation screen (teacher intro, schedule, timezone-localized times)
- Ops/Academic: waitlist dashboard, teacher-availability matcher, manual override (force-allocate)

### Edge cases & failure handling
- **No matching batch exists at all** (rare timezone, niche skill combination): stays on the waitlist indefinitely with a visible status, rather than silently failing — ops gets an aging-waitlist alert (e.g., >7 days unallocated) to manually accommodate.
- **Batch outgrows capacity mid-term** (an exception admission): requires either an admin capacity override on that specific batch or a split — a split is a planned `batch_allocation_log` (`reason='overflow_split'`) event with the same communication workflow as any reassignment (Phase 6), since a student waking up to "you're now in a different batch" without warning is exactly the kind of trust-eroding surprise a premium product can't afford.
- **Reassignment from a teacher-change request (Phase 10) or schedule conflict**: always logged with the correct `reason` value — the log is what support pulls up when a parent asks "why did my child's batch change," so the reason has to be accurate, not just "reassignment" generically.

---

## 6. Class Scheduling Engine

### Business workflow

1. A batch's `timetable` (recurring weekly pattern: day/time/teacher) is the template. A cron-driven job (GoDaddy-compatible — no persistent daemon needed) materializes concrete `live_classes` rows on a rolling 4-week forward window.
2. Before materializing a given occurrence, the job checks `academic_holidays` for that date/course/batch — if it's a holiday, that occurrence is skipped (and, if it was already materialized before the holiday was added, retroactively cancelled — see edge cases).
3. Each `live_classes` row carries `start_datetime` in **UTC**, always — display conversion to the student's and teacher's local timezones happens at render time, never by storing a localized time. This is the detail that prevents Daylight Saving drift: a recurring "Monday 5pm IST" doesn't need a stored offset that goes stale twice a year, because the conversion is recomputed fresh every time from the canonical UTC value and the viewer's current timezone.
4. Class state machine: `scheduled → live → completed` (happy path) or `scheduled → cancelled` / `scheduled → rescheduled` (exception paths, detailed in Phase 3c).

### Validations
- **Teacher double-booking**: checked against the teacher's existing `live_classes` at materialization time and at manual-schedule-creation time — a conflict blocks creation and alerts ops rather than silently creating two overlapping sessions for one teacher.
- **Student double-booking** (relevant mainly for 1-on-1 reschedules where a student might end up with two sessions overlapping): same conflict check pattern.
- **Maintenance windows**: a configured recurring window (e.g., a weekly deploy slot) is excluded from materialization, same mechanism as a holiday.

### UI screens
- Student/parent: upcoming-classes calendar view (localized times), class detail card (teacher, topic, join button appearing only within the join window)
- Teacher: weekly schedule view, conflict warnings surfaced before they confirm a manual schedule change

### Edge cases & failure handling
- **Holiday added after classes for that date already exist**: a follow-up job scans already-materialized `live_classes` rows that now fall on a newly added holiday and cancels them (`cancellation_reason='holiday'`), which feeds directly into the makeup-class workflow (Phase 3c) rather than just disappearing from the schedule.
- **Timetable changed mid-term** (batch's slot moved): only *future, not-yet-occurred* `live_classes` rows are regenerated to match; already-`completed`/`cancelled` rows are left untouched as historical record — rewriting history here would corrupt attendance/credit records that have already been finalized.
- **Materialization job fails partway** (cron timeout on a shared-hosting box): each occurrence is created in its own small transaction, so a partial run just means next cron tick picks up the remaining occurrences — no batch-level rollback needed, consistent with the same "small transactions, not one giant one" principle used for credit deductions in Phase 3a.

---

## Next

Phase 3c — Live Classroom, Attendance, Reschedule/Cancellation, Teacher Change workflows. Say "continue."
