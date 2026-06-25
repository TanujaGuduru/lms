# Delivery Phase 2c — Database Schema: Classroom + Content

Covers lifecycle phases 7–8 (Live Classroom session metadata, Attendance), 11–13 (Recordings, Video Library, Notes/Materials), and the Digital Notebook (phase 32 in your spec). Reuses existing `attendance`, `lessons`, `lesson_progress`, `live_classes` tables.

---

## 1. Attendance Engine

Existing `attendance` table tracks per-day status but isn't wired to a specific session or join/leave timing. Extend it:

```sql
ALTER TABLE `attendance`
  ADD COLUMN `live_class_id` BIGINT UNSIGNED DEFAULT NULL AFTER `timetable_id`,
  ADD COLUMN `join_time` DATETIME DEFAULT NULL AFTER `check_in_time`,
  ADD COLUMN `leave_time` DATETIME DEFAULT NULL AFTER `check_out_time`,
  ADD COLUMN `duration_seconds` INT UNSIGNED DEFAULT 0 AFTER `leave_time`,
  ADD COLUMN `attendance_percent` TINYINT UNSIGNED DEFAULT 0 COMMENT 'duration_seconds / class duration * 100' AFTER `duration_seconds`,
  ADD COLUMN `marked_method` ENUM('auto_join','manual_override','auto_absent') DEFAULT 'auto_join' AFTER `marked_by`,
  MODIFY COLUMN `status` ENUM('present','absent','late','partial','excused') DEFAULT 'absent',
  ADD KEY `idx_attendance_liveclass` (`live_class_id`),
  ADD CONSTRAINT `fk_attendance_liveclass` FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE;

ALTER TABLE `attendance` DROP INDEX `uk_attendance`;
ALTER TABLE `attendance` ADD UNIQUE KEY `uk_attendance_session` (`live_class_id`, `student_id`);
```

**Why `attendance_percent` is stored, not computed on every read:** it's written once by the attendance engine right after a class ends (join/leave events from the Agora SDK feed it) and is then read constantly — by the credit deduction job (Phase 2a's `credit_deduction_policies.attendance_threshold_percent`), by parent dashboards, by progress analytics. Computing it from raw join/leave events on every read would mean re-deriving the same number thousands of times for one class.

**Auto attendance rule (app logic, not schema):** join within 10 min of `live_classes.start_datetime` → `present`; later → `late`; `attendance_percent` < 60 → `partial` regardless of join time; never joined → `absent` (auto-marked by a sweep job shortly after the class's scheduled end, so a student doesn't sit in limbo "unmarked" if they simply never showed up).

---

## 2. Recording Pipeline

`live_classes.recording_url` (existing) is a single raw field — too thin for a multi-stage pipeline with real failure modes. Dedicated table:

```sql
CREATE TABLE `class_recordings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` BIGINT UNSIGNED NOT NULL,
  `raw_recording_url` VARCHAR(500) DEFAULT NULL COMMENT 'unprocessed capture straight from Agora cloud recording',
  `processed_video_url` VARCHAR(500) DEFAULT NULL COMMENT 'final transcoded/adaptive-bitrate version',
  `thumbnail_url` VARCHAR(500) DEFAULT NULL,
  `duration_seconds` INT UNSIGNED DEFAULT 0,
  `file_size_bytes` BIGINT UNSIGNED DEFAULT 0,
  `processing_status` ENUM('pending','processing','completed','failed','corrupted') DEFAULT 'pending',
  `processing_error` TEXT DEFAULT NULL,
  `retry_count` TINYINT UNSIGNED DEFAULT 0,
  `transcript_status` ENUM('pending','processing','completed','failed') DEFAULT 'pending',
  `transcript_text` LONGTEXT DEFAULT NULL,
  `available_at` DATETIME DEFAULT NULL COMMENT 'when it became visible to students — may be hours after class end',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recording_class` (`live_class_id`),
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `recording_views` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recording_id` BIGINT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `last_position_seconds` INT UNSIGNED DEFAULT 0,
  `watched_seconds` INT UNSIGNED DEFAULT 0 COMMENT 'cumulative, for completion % — distinct from last_position which is for resume',
  `completed` TINYINT(1) DEFAULT 0,
  `device_type` VARCHAR(30) DEFAULT NULL,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_watched_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_view_student_recording` (`recording_id`, `student_id`),
  FOREIGN KEY (`recording_id`) REFERENCES `class_recordings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Access control is a JOIN, not a table:** a student can view a recording iff they're (or were) in `batch_students` for the batch that `live_classes.batch_id` belongs to. No separate ACL table — granting/revoking access is just the existing enrollment/batch membership, so it can't drift out of sync with who's actually enrolled.

**Failure handling built into the state machine:** `processing_status='failed'` with `retry_count` lets a queue-driven retry job (capped at, say, 3 attempts before alerting ops) reprocess without manual intervention; `corrupted` is a terminal state surfaced to ops for manual remediation (re-pull from Agora's raw capture if still available within their retention window).

---

## 3. Video Lecture Library

Existing `lessons` + `lesson_progress` already cover video content + resume/progress tracking well. Add subtitles/transcript and bookmarks:

```sql
ALTER TABLE `lessons`
  ADD COLUMN `subtitle_url` VARCHAR(500) DEFAULT NULL AFTER `resource_url`,
  ADD COLUMN `transcript_text` LONGTEXT DEFAULT NULL AFTER `subtitle_url`;

CREATE TABLE `lesson_bookmarks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lesson_progress_id` BIGINT UNSIGNED NOT NULL,
  `timestamp_seconds` INT UNSIGNED NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookmark_progress` (`lesson_progress_id`),
  FOREIGN KEY (`lesson_progress_id`) REFERENCES `lesson_progress`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Playback speed is a client-side/local preference (or a single `users.preferred_playback_speed` column at most) — not worth a table.

---

## 4. Notes / Materials (course-provided, not student-authored)

```sql
CREATE TABLE `course_materials` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED DEFAULT NULL,
  `lesson_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `file_type` ENUM('pdf','ppt','doc','xls','code','other') DEFAULT 'pdf',
  `file_url` VARCHAR(500) NOT NULL,
  `file_size_bytes` BIGINT UNSIGNED DEFAULT 0,
  `current_version` INT UNSIGNED DEFAULT 1,
  `is_downloadable` TINYINT(1) DEFAULT 1,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`module_id`) REFERENCES `course_modules`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `material_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `file_url` VARCHAR(500) NOT NULL,
  `changelog` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_version_material` (`material_id`),
  FOREIGN KEY (`material_id`) REFERENCES `course_materials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `material_downloads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_id` BIGINT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `downloaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_download_material` (`material_id`),
  FOREIGN KEY (`material_id`) REFERENCES `course_materials`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`material_downloads` doubles as both an analytics log and the basis for "download permission" checks (e.g., rate-limiting how many times a file can be re-downloaded, or revoking access retroactively by checking enrollment status at download time, not just at original grant time).

---

## 5. Digital Notebook / Smart Notes

```sql
CREATE TABLE `notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `content` LONGTEXT NOT NULL,
  `content_format` ENUM('html','markdown') DEFAULT 'html',
  `linked_course_id` INT UNSIGNED DEFAULT NULL,
  `linked_lesson_id` INT UNSIGNED DEFAULT NULL,
  `linked_live_class_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_ai_generated` TINYINT(1) DEFAULT 0,
  `is_favorite` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notes_student` (`student_id`, `updated_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`linked_lesson_id`) REFERENCES `lessons`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`linked_live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `note_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `note_id` BIGINT UNSIGNED NOT NULL,
  `content` LONGTEXT NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_noteversion_note` (`note_id`),
  FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `note_tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `note_id` BIGINT UNSIGNED NOT NULL,
  `tag` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_note_tag` (`note_id`, `tag`),
  FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `note_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `note_id` BIGINT UNSIGNED NOT NULL,
  `file_url` VARCHAR(500) NOT NULL,
  `file_type` ENUM('image','audio','code_snippet') DEFAULT 'image',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `recording_bookmarks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `recording_id` BIGINT UNSIGNED NOT NULL,
  `timestamp_seconds` INT UNSIGNED NOT NULL,
  `label` VARCHAR(255) DEFAULT NULL,
  `linked_note_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'set when the bookmark is saved into a note',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookmark_recording` (`recording_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recording_id`) REFERENCES `class_recordings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`linked_note_id`) REFERENCES `notes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `flashcards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_note_id` BIGINT UNSIGNED DEFAULT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `deck_name` VARCHAR(100) DEFAULT 'Default',
  `front_text` TEXT NOT NULL,
  `back_text` TEXT NOT NULL,
  `review_count` INT UNSIGNED DEFAULT 0,
  `last_reviewed_at` DATETIME DEFAULT NULL,
  `next_review_at` DATETIME DEFAULT NULL COMMENT 'spaced-repetition schedule',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flashcard_student_due` (`student_id`, `next_review_at`),
  FOREIGN KEY (`source_note_id`) REFERENCES `notes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI-generated "quiz from notes" reuses the exam infrastructure rather than a new table
ALTER TABLE `exams` ADD COLUMN `source_note_id` BIGINT UNSIGNED DEFAULT NULL
  COMMENT 'set when this practice quiz was AI-generated from a student note' AFTER `course_id`;
```

**Autosave strategy (app logic):** the editor autosaves to `notes.content` on a debounced interval (e.g., every 5s of inactivity) via a lightweight PATCH; a full `note_versions` snapshot is only written on bigger thresholds (every N autosaves, or once per session, or on explicit "save version") — versioning every keystroke would make `note_versions` grow unboundedly for no real benefit, since the autosaved `notes.content` itself already protects against data loss.

**Voice-to-text notes** aren't a separate schema concept — the recorded audio is transcribed via a speech-to-text API (detailed in the Phase 5 AI workflows doc) and the result lands in `notes.content` like any other note; no dedicated table needed.

All tables above are plain MySQL 8 — confirmed portable to GoDaddy per the hosting addendum.

---

## Next

Phase 2d — Sandbox (coding workspaces, execution, code replay) + Collaborative Coding + AI conversation/risk-scoring schema. Say "continue."
