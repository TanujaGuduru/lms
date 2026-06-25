# Delivery Phase 3f — Workflows: Assessments, Project Lifecycle, Publishing, Progress Analytics

Covers lifecycle phases 18–21. Reuses existing `exams`/`questions`/`exam_attempts` infrastructure for assessments and `assignments`/`assignment_submissions`/`published_projects` for projects (Phase 2e). Adds one new table for analytics.

---

## 18. Assessments (MCQ, Coding Tests, Short Answer, Viva)

### Business workflow

1. Teacher creates an exam (existing flow) — type, availability window (`start_datetime`/`end_datetime`), duration, shuffle settings.
2. Student starts an attempt within the window → `exam_attempts` row, `started_at` set — **the server timestamp is the deadline anchor, not anything the client reports.**
3. Question/option order is shuffled per `exams.shuffle_questions`/`shuffle_options`, **seeded deterministically by `attempt_id`** — so a page reload mid-attempt shows the same order, rather than re-shuffling and confusing the student about which question they were on.
4. Coding-type questions embed the Coding Sandbox (Phase 3e) inline, graded by running the submission against stored test cases (test case inputs/expected outputs stored in the existing flexible `questions.options`/`correct_answer` JSON columns — no new table needed, reusing the same pattern already used for MCQ).
5. Every answer auto-saves to its `exam_responses` row as the student progresses, not just at final submit — this is what makes mid-exam recovery (below) possible at all.
6. Submission → auto-grading for objective types runs immediately; short-answer/viva route to a manual grading queue. Results show immediately only if `show_result_immediately` is set; otherwise held until the teacher releases them.

### Anti-cheating measures
- **Server-side timer enforcement**: submission past `started_at + duration_minutes` is rejected/auto-finalized server-side regardless of client-reported time remaining — a manipulated client clock changes nothing.
- **Randomization** (already covered above).
- **Tab-switch/focus-loss detection**: client-side blur/visibility events logged to `exam_attempts.cheating_flags` (JSON) — informational for teacher review, not an automatic fail, since legitimate reasons for switching tabs exist (a notification, a sibling's interruption) and a heavy-handed auto-penalty would punish kids unfairly for ordinary distraction.
- **Optional webcam proctoring** for high-stakes assessments only (finals, not routine practice quizzes) — reuses the same Agora recording infrastructure as live classes rather than a separate system, kept opt-in per exam type given the cost and the added parental-consent surface area it implies.

### Edge cases & failure handling
- **Browser crash/refresh mid-exam**: resumable by design, since every answer is already persisted as it's selected (§5 above) — reopening the attempt restores exactly where they left off, with the timer continuing from the original `started_at` (not paused, not restarted).
- **Time runs out mid-question**: auto-submit is triggered by the **server** noticing the deadline has passed on the next request/poll, not by trusting the client to fire a submit call on time — a closed browser tab still results in a correctly auto-submitted (and graded, for objective parts) attempt.
- **Network drop during the exam**: timer keeps counting (intentionally not pausable — a pause-on-disconnect policy is gameable by deliberately disconnecting for extra time). A genuine **platform-side** outage affecting many students simultaneously is handled the same way as the classroom outage case in Phase 3c: detected as a pattern, remediated with a manual extension/retry issued by a teacher/admin, not left to each student to individually contest.

---

## 19–20. Project Lifecycle + Publishing

### Business workflow

1. Teacher releases a project — an `assignments` row with `type='project'`, typically with a multi-week `due_date` rather than the 78-hour homework default (Phase 3d's 78h default only applies to plain homework; project timelines are set explicitly).
2. Student works on it, often inside a linked `code_workspaces` (`linked_assignment_id`).
3. **Submission supports multiple types together, not a forced single choice** — a real project submission is often a GitHub repo *and* screenshots *and* a demo video at once. The submission UI lets a student attach any combination; `assignment_submissions` already has discrete columns for each (`github_repo_url`, `demo_video_url`, `screenshots` JSON, plus the original `file_path`/`url`).
4. **Originality check** runs automatically on submission — comparing against a corpus of prior submissions (embedding-similarity) and, where applicable, a public-code plagiarism service — populating `originality_score` and `plagiarism_report_url`. **This is advisory, not an auto-reject gate**: a low score flags the submission for teacher review rather than blocking it outright, because false positives are common (shared boilerplate/starter code, a student legitimately building on their own earlier work) and an automated hard-fail on something this consequential would be the wrong place to fully trust an algorithm.
5. Teacher reviews and scores against `total_marks`/`passing_marks`, with written `feedback`. (Granular multi-criteria rubric scoring beyond a single total is a documented future enhancement, not built into v1 — a single score + substantive written feedback is the pragmatic starting point.)
6. **Publishing is opt-in, then gated by approval** — a student/parent can request publishing a graded project to the portfolio; this creates a `published_projects` row with `is_public=0`. A mentor/admin reviews and only then sets `is_public=1` with `approved_by`/`approved_at` recorded. Nothing reaches the public portfolio (Phase 3k) without that explicit human step — consistent with the consent-first posture established in Phase 1, especially relevant since published work is tied to a minor's name.

### Edge cases & failure handling
- **Plagiarism flagged but submission is legitimate** (shared starter code, building on prior personal work): resolved by teacher judgment using the report as input, not by the system auto-deciding — the score is a signal, not a verdict.
- **Multiple resubmissions while work is still in progress**: same draft-vs-submit distinction recommended in Phase 3d applies here too — a project, even more than homework, benefits from being able to save progress without each save being treated as the "final" hand-in for grading purposes.

---

## 21. Progress Analytics

### Design principle: precomputed, not live-aggregated
Attendance %, course/assignment completion %, average project/assessment scores, AI usage, and coding-execution trends are each cheap to read individually but expensive to compute together on every dashboard load (multiple joins/aggregations across `attendance`, `lesson_progress`, `assignment_submissions`, `exam_attempts`, `ai_messages`, `code_executions`). On a GoDaddy shared-hosting box specifically, running that aggregation live on every page view is a real performance risk, not just a theoretical one. Instead:

```sql
CREATE TABLE `student_progress_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `snapshot_date` DATE NOT NULL,
  `attendance_percent` TINYINT UNSIGNED DEFAULT NULL,
  `course_completion_percent` TINYINT UNSIGNED DEFAULT NULL,
  `assignment_completion_percent` TINYINT UNSIGNED DEFAULT NULL,
  `avg_project_score` DECIMAL(5,2) DEFAULT NULL,
  `avg_assessment_score` DECIMAL(5,2) DEFAULT NULL,
  `ai_messages_count` INT UNSIGNED DEFAULT 0,
  `coding_executions_count` INT UNSIGNED DEFAULT 0,
  `coding_success_rate` DECIMAL(5,2) DEFAULT NULL COMMENT 'share of executions with exit_code=0, trend = "coding improvement"',
  `computed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_snapshot_day` (`student_id`, `enrollment_id`, `snapshot_date`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A nightly cron job (GoDaddy-compatible — no persistent worker needed) computes one row per active enrollment per day. The dashboard reads the **latest snapshot** instead of recomputing live — accepting up to 24h staleness on trend data, which is the right tradeoff since "my progress over the last month" doesn't need second-by-second accuracy. Anything that *does* need to be live (credit balance, today's class status) is read straight from its own source table, since those are cheap single-row lookups, not multi-table aggregations — the snapshot table is specifically for the expensive, slow-changing analytics view, not a blanket cache of everything.

**"Coding improvement" tracking** is the `coding_success_rate` trend across snapshots over time, cross-referenced with `ai_insights` (`repeated_mistake` entries from Phase 2d) to surface not just "you're improving" but *what specifically* improved or is still recurring.

---

## Next

Phase 3g — AI Risk Detection, Parent Visibility, Monthly Parent Reports, Payments/Refunds/Freeze. Say "continue."
