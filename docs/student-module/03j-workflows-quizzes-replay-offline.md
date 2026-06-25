# Delivery Phase 3j — Workflows: Live Quizzes, Code Replay, Offline Access/DRM, Calendar Sync

Covers lifecycle phases 34–37. Live Quizzes and Offline Access need new schema (not covered in Phase 2) — added below alongside the workflow. Code Replay and Calendar Sync schema already exist (`02d`/`02e`); this is their workflow detail.

---

## 34. Live Quizzes / In-Class Interactivity

```sql
CREATE TABLE `live_quizzes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` BIGINT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED DEFAULT NULL COMMENT 'set when pulled from the question bank',
  `ad_hoc_question_text` TEXT DEFAULT NULL COMMENT 'set when the teacher types it on the fly instead',
  `ad_hoc_options` JSON DEFAULT NULL,
  `quiz_type` ENUM('mcq','poll','code_challenge','rapid_quiz') DEFAULT 'mcq',
  `duration_seconds` SMALLINT UNSIGNED DEFAULT 30,
  `launched_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_class` (`live_class_id`),
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `live_quiz_responses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_quiz_id` BIGINT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `response_value` TEXT DEFAULT NULL,
  `response_time_ms` INT UNSIGNED DEFAULT NULL COMMENT 'time from launch to submission, drives rapid-quiz speed scoring',
  `is_correct` TINYINT(1) DEFAULT NULL,
  `points_awarded` SMALLINT UNSIGNED DEFAULT 0,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quiz_response` (`live_quiz_id`, `student_id`),
  FOREIGN KEY (`live_quiz_id`) REFERENCES `live_quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Business workflow

1. Teacher launches a quiz mid-class — pushed instantly to all connected students via the Pusher/Ably classroom channel as a popup overlay. `launched_at` is the server timestamp; **the countdown every student sees is computed from `launched_at + duration_seconds`, not a client-side timer started independently** — this is what keeps everyone's countdown actually in sync and, more importantly, is what makes the deadline enforceable server-side (same anchor-to-server-time principle as exam timers in Phase 3f).
2. Responses write to `live_quiz_responses`, scored on correctness and (for `rapid_quiz`) response speed.
3. A live leaderboard updates as responses arrive, pushed the same way the quiz was launched. Quiz wins feed `xp_transactions` (`reason='quiz_won'`).
4. On reveal, the AI Doubt Solver's "explain mode" (Phase 3e) can generate a brief on-the-spot explanation of the correct answer — reusing that existing capability rather than building a separate explanation feature just for quizzes.
5. Quiz closes; participation rate, accuracy, and average response time are computed directly from `live_quiz_responses` for that quiz.

### Edge cases & failure handling
- **Late join, missed the quiz window**: simply excluded from that quiz's participation count — not penalized, just not counted, since they genuinely weren't there for it.
- **Response arrives after the server-side deadline** (network lag made it feel on-time to the student): rejected at the server using `launched_at + duration_seconds` as the actual cutoff, regardless of what the client's local countdown showed — same reasoning as exam submission timing.
- **Teacher launches the wrong quiz**: a single "close immediately" action stops it for everyone instantly via the same real-time channel, rather than waiting out the timer.

---

## 35. Code Replay — Workflow Detail

(Schema: `code_replay_sessions`, `code_replay_markers` — Phase 2d.)

### Business workflow

1. **Recording isn't blanket always-on** — full keystroke/execution capture for every casual practice session would be a lot of storage/processing for low value. The recommended default: always record for assignment/project work and for live-class pairing sessions (where teacher review value is highest), with a visible toggle for free-form practice sessions where a student may prefer not to be recorded at all.
2. The client batches keystroke/diff and execution events and flushes compressed chunks directly to S3 on an interval — never proxied through the GoDaddy-hosted app (Phase 2d's core GoDaddy principle, restated here because it's the same pattern).
3. Markers (`execution`/`error`/`fix`/`teacher_intervention`) are written as they happen, which is what makes the replay timeline's "jump to every error" feature instant rather than requiring the full stream to be parsed first.
4. **Post-session AI analysis** runs async, looking for repeated-mistake patterns across the session (and across a student's history of sessions), writing results into `ai_insights` — which then surfaces in progress analytics (Phase 3f) as "what specifically is still recurring," not just a vague "needs improvement."

### Edge cases & failure handling
- **Browser crash mid-recording**: the event log up to the last successfully-flushed chunk is still fully usable — the session is closed with whatever `ended_at` can be inferred from the last received chunk, rather than left in an indefinite "still recording" limbo.
- **Very long sessions**: already handled by chunked S3 storage; the replay UI supports seeking/skipping via markers rather than forcing a linear watch-through of a multi-hour session.

---

## 36. Offline Access / Download Mode

```sql
CREATE TABLE `offline_downloads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `content_type` ENUM('video_lesson','recording','material','note_export') NOT NULL,
  `content_id` BIGINT UNSIGNED NOT NULL,
  `download_token` VARCHAR(255) NOT NULL,
  `device_fingerprint` VARCHAR(255) DEFAULT NULL,
  `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `last_validated_at` DATETIME DEFAULT NULL,
  `status` ENUM('active','expired','revoked') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_offline_token` (`download_token`),
  KEY `idx_offline_student` (`student_id`, `status`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Business workflow

1. Student requests an offline download → eligibility check (is download enabled for this specific content/course) → if eligible, an encrypted package is prepared (AES-128 HLS via the chosen video API, per the Phase 1 hosting addendum — **not** full Widevine-style DRM, a deliberate cost/complexity tradeoff: this content's piracy risk profile doesn't justify studio-grade DRM expense) and an `offline_downloads` row is created with a bounded `expires_at` (e.g., 7–30 days).
2. The decryption capability is tied to the token, **validated against the server periodically** (on each playback attempt while online; a bounded grace period for genuinely offline use before re-validation is required) — the key is never permanently embedded client-side.
3. **Sync on reconnect**: any progress accumulated while offline (equivalent to `lesson_progress`) queues locally and syncs once back online — a student who watched three lessons on a flight doesn't lose that progress, but it also isn't pretending to be "live" data in the meantime.
4. **Expiry is actually enforced, not just a UI suggestion**: past `expires_at`, the app refuses to decrypt the locally-stored file without a renewed token — having the encrypted bytes sitting on a device is useless without server-side validation succeeding.

### Edge cases & failure handling
- **Tampering** (a modified client attempting to extract decrypted content): accepted as a residual risk at this DRM tier — full Widevine would close this further, at a cost the actual piracy risk for educational content doesn't justify, consistent with the Phase 1 hosting-addendum reasoning.
- **Account sharing via offline downloads**: `device_fingerprint` plus a cap on concurrent active `offline_downloads` per student surfaces sharing patterns (e.g., the same account requesting downloads from many distinct devices in a short window) for review, rather than silently allowing unlimited device spread.

---

## 37. Calendar Integration

(Schema: `calendar_connections`, `calendar_sync_log` — Phase 2e.)

### Business workflow

1. Student/parent connects Google/Outlook/Apple calendar via standard OAuth — tokens encrypted at rest.
2. On connection, and on every subsequent schedule-relevant change (a class materializes, an assignment due date is set, a PTM is booked, an assessment is scheduled), a sync job pushes the event to the external calendar via that provider's API, recording the mapping in `calendar_sync_log` (`external_event_id`).
3. **Reschedule/cancellation updates or deletes the existing external event** using the stored `external_event_id` — it never creates a duplicate, which is the failure mode a naive "just re-push on every change" implementation would have.
4. Token refresh is transparent via the stored refresh token; if refresh itself fails (the user revoked access on Google's side directly, not through the app), `calendar_connections.is_active=0` and the student is prompted to reconnect — the failure surfaces to the user instead of silently stopping all future syncs with no explanation.

### Edge cases & failure handling
- **Apple Calendar uses CalDAV**, a different protocol than Google/Outlook's REST APIs — this needs its own integration adapter, but the business workflow and schema (`calendar_connections`/`calendar_sync_log`) stay provider-agnostic; only the adapter implementation differs per provider.
- **Provider API rate limits**: handled with the same small-batch, backoff-and-retry pattern already used for every other cron-driven job in this design on GoDaddy — not a special case unique to calendar sync.

---

## Next

Phase 3k — Achievement Showcase Wall, Parent-Teacher Meeting (PTM) Booking. This closes out Delivery Phase 3 — Workflows. Say "continue."
