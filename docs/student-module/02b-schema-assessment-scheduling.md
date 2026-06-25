# Delivery Phase 2b — Database Schema: Assessment + Scheduling

Covers lifecycle phases 4–6: Skill Assessment (placement), Batch Allocation, Class Scheduling Engine.

**Design rule carried through this section:** reuse the existing `exams`, `questions`, `exam_attempts`, `batches`, `timetable`, `live_classes` tables (already built and fixed in the Admin panel work) rather than building parallel student-side tables. A placement test is an exam; a class session is a `live_classes` row. Forking these would immediately reintroduce the exact schema-drift problem this whole project spent a session cleaning up.

---

## 1. Placement / Skill Assessment Engine

```sql
-- A placement test is just an exam with a new type value
ALTER TABLE `exams` MODIFY COLUMN `type`
  ENUM('quiz','midterm','final','mock','practice','coding','placement') DEFAULT 'quiz';

CREATE TABLE `placement_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_attempt_id` INT UNSIGNED NOT NULL,
  `coding_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100',
  `logical_reasoning_score` TINYINT UNSIGNED DEFAULT NULL,
  `communication_score` TINYINT UNSIGNED DEFAULT NULL,
  `overall_score` TINYINT UNSIGNED DEFAULT NULL,
  `recommended_level` ENUM('beginner','intermediate','advanced') DEFAULT NULL,
  `recommended_course_id` INT UNSIGNED DEFAULT NULL,
  `recommended_batch_type` ENUM('one_on_one','group') DEFAULT NULL,
  `ai_generated` TINYINT(1) DEFAULT 1 COMMENT '1 = AI-scored, 0 = mentor manually scored/overrode',
  `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'mentor who confirmed/overrode the AI recommendation',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_placement_attempt` (`exam_attempt_id`),
  FOREIGN KEY (`exam_attempt_id`) REFERENCES `exam_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recommended_course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Communication/logical/coding sub-scores are split out** rather than folded into the existing `exam_attempts.percentage` because course/batch recommendation logic needs to weigh them independently (e.g., strong coding + weak communication still routes to a technical track, but flags a soft-skills note for the mentor) — a single blended score would throw away the information the recommendation engine actually needs.

---

## 2. Batch Allocation

Existing `batches` table covers schedule/capacity but has no notion of 1-on-1 vs. group, or matching constraints. Extend it:

```sql
ALTER TABLE `batches`
  ADD COLUMN `batch_type` ENUM('one_on_one','group') DEFAULT 'group' AFTER `mode`,
  ADD COLUMN `min_age` TINYINT UNSIGNED DEFAULT NULL AFTER `batch_type`,
  ADD COLUMN `max_age` TINYINT UNSIGNED DEFAULT NULL AFTER `min_age`,
  ADD COLUMN `skill_level` ENUM('beginner','intermediate','advanced','mixed') DEFAULT 'mixed' AFTER `max_age`,
  ADD COLUMN `primary_timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata' AFTER `skill_level`;
```

```sql
CREATE TABLE `batch_waitlist` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `preferred_batch_type` ENUM('one_on_one','group') DEFAULT 'group',
  `preferred_timezone` VARCHAR(50) DEFAULT NULL,
  `skill_level` ENUM('beginner','intermediate','advanced') DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('waiting','allocated','expired','cancelled') DEFAULT 'waiting',
  `allocated_batch_id` INT UNSIGNED DEFAULT NULL,
  `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `allocated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_waitlist_course_status` (`course_id`, `status`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`),
  FOREIGN KEY (`allocated_batch_id`) REFERENCES `batches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `batch_allocation_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `from_batch_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL on first-ever allocation',
  `to_batch_id` INT UNSIGNED NOT NULL,
  `reason` ENUM('initial_allocation','overflow_split','reassignment_request','teacher_change','schedule_conflict') NOT NULL,
  `allocated_by` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = automated allocation engine',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alloc_student` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_batch_id`) REFERENCES `batches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Why an allocation *log* separate from `batch_students`:** `batch_students` (existing) answers "who is in this batch right now." It doesn't answer "why," or preserve history once a student moves batches (Phase 9/10 reassignment). The log is append-only and answers "show me every batch this student has ever been in and why" — needed for support/parent-escalation conversations ("why was my child moved?") without reconstructing it from audit logs.

**Overflow logic** (business rule, not a table): when a waitlist for a given `course_id` + `preferred_batch_type` crosses a configurable threshold (e.g., 6 waiting students for a group batch), the allocation engine auto-creates a new `batches` row and bulk-allocates from the waitlist — handled in application logic against the tables above, no schema needed beyond what exists.

---

## 3. Class Scheduling Engine

Existing `timetable` (recurring weekly pattern per batch) + `live_classes` (concrete session instances) already cover the core. A scheduled job materializes upcoming `live_classes` rows from `timetable` on a rolling 4-week window. Extend `live_classes` for makeup/reschedule/cancellation tracking:

```sql
ALTER TABLE `live_classes`
  ADD COLUMN `timetable_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL if ad-hoc/makeup, not generated from the recurring pattern' AFTER `batch_id`,
  ADD COLUMN `class_type` ENUM('regular','makeup','demo','extra') DEFAULT 'regular' AFTER `timetable_id`,
  ADD COLUMN `rescheduled_from_id` INT UNSIGNED DEFAULT NULL COMMENT 'points at the original session this one replaces' AFTER `class_type`,
  ADD COLUMN `cancellation_reason` ENUM('teacher_emergency','technical_outage','holiday','student_emergency','platform_maintenance') DEFAULT NULL AFTER `status`,
  ADD COLUMN `cancelled_by` INT UNSIGNED DEFAULT NULL AFTER `cancellation_reason`,
  ADD COLUMN `credit_charged` TINYINT(1) DEFAULT NULL COMMENT 'NULL = not yet resolved; set by the attendance/credit engine after the class ends' AFTER `attendee_count`,
  MODIFY COLUMN `status` ENUM('scheduled','live','completed','cancelled','rescheduled') DEFAULT 'scheduled';
```

```sql
CREATE TABLE `academic_holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `applies_to` ENUM('all','course_specific','batch_specific') DEFAULT 'all',
  `course_id` INT UNSIGNED DEFAULT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_holiday_date` (`holiday_date`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reschedule_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `original_class_id` INT UNSIGNED NOT NULL,
  `requested_new_datetime` DATETIME NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','auto_approved') DEFAULT 'pending',
  `new_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'the resulting live_classes row once approved',
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resched_student` (`student_id`, `created_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`original_class_id`) REFERENCES `live_classes`(`id`),
  FOREIGN KEY (`new_class_id`) REFERENCES `live_classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `teacher_change_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `batch_id` INT UNSIGNED NOT NULL,
  `current_teacher_id` INT UNSIGNED NOT NULL,
  `reason` ENUM('teaching_mismatch','language_mismatch','schedule_mismatch','complaint','other') NOT NULL,
  `details` TEXT DEFAULT NULL,
  `status` ENUM('pending','under_review','approved','rejected') DEFAULT 'pending',
  `new_teacher_id` INT UNSIGNED DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`),
  FOREIGN KEY (`current_teacher_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`new_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**"Max reschedules per month" and conflict validation are deliberately not schema** — they're computed at request time (`COUNT(*) FROM reschedule_requests WHERE student_id=? AND status IN ('approved','auto_approved') AND created_at >= start_of_month`) rather than a stored running counter, because a stored counter is one more place for drift to creep in (exactly the class of bug this whole project just spent a session hunting down). Same logic for teacher/student double-booking checks — queried against `live_classes` at request time, not pre-computed.

All tables above use plain MySQL 8 syntax — no Aurora-specific features — so they run unmodified on GoDaddy's MySQL per the hosting addendum.

---

## Next

Phase 2c — Classroom (live session/attendance/recordings) + Content (video library/notes/notebook) schema. Say "continue."
