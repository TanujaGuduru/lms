-- ============================================================
-- CodeGurukul Student Portal — additive schema
-- ============================================================
-- Extends the EXISTING `codegurukul` database (database/schema.sql) used by
-- the Admin panel — never forked, never replaces a table. Import this file
-- via phpMyAdmin (or `mysql codegurukul < schema_student_portal.sql`) AFTER
-- the Admin panel's own schema.sql is already in place.
--
-- Source: docs/student-module/01 through 08 (35-document architecture series).
-- FK column types are deliberately corrected against the REAL existing tables
-- (most use INT UNSIGNED ids, not BIGINT) where the source docs were written
-- before being checked against the live schema — see inline notes below.

-- ============================================================
-- PHASE 2a: ENROLLMENT + BILLING/CREDITS
-- docs/student-module/02a-schema-enrollment-billing.md
-- ============================================================

-- §1 Lead-to-Enrollment Tables
CREATE TABLE `leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(180) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `age` TINYINT UNSIGNED DEFAULT NULL,
  `source` ENUM('website','referral','ads','social','walkin','partner') DEFAULT 'website',
  `interested_course_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('new','contacted','demo_scheduled','demo_completed','converted','lost') DEFAULT 'new',
  `lost_reason` VARCHAR(255) DEFAULT NULL,
  `assigned_counselor_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leads_uuid` (`uuid`),
  KEY `idx_leads_status` (`status`),
  KEY `idx_leads_counselor` (`assigned_counselor_id`),
  FOREIGN KEY (`interested_course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_counselor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `demo_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED DEFAULT NULL,
  `scheduled_at` DATETIME NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED DEFAULT 30,
  `status` ENUM('scheduled','completed','no_show','cancelled','rescheduled') DEFAULT 'scheduled',
  `recording_url` VARCHAR(500) DEFAULT NULL,
  `join_url` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_demo_lead` (`lead_id`),
  KEY `idx_demo_teacher` (`teacher_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `demo_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demo_session_id` BIGINT UNSIGNED NOT NULL,
  `author_id` INT UNSIGNED NOT NULL,
  `note_type` ENUM('teacher_observation','counselor_remark','objection','general') DEFAULT 'general',
  `content` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notes_demo` (`demo_session_id`),
  FOREIGN KEY (`demo_session_id`) REFERENCES `demo_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `demo_skill_assessments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demo_session_id` BIGINT UNSIGNED NOT NULL,
  `coding_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100',
  `logical_reasoning_score` TINYINT UNSIGNED DEFAULT NULL,
  `communication_score` TINYINT UNSIGNED DEFAULT NULL,
  `recommended_level` ENUM('beginner','intermediate','advanced') DEFAULT NULL,
  `recommended_course_id` INT UNSIGNED DEFAULT NULL,
  `assessed_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`demo_session_id`) REFERENCES `demo_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recommended_course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assessed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lead/demo data is never copied on conversion, only linked forward.
CREATE TABLE `lead_conversions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `payment_id` INT UNSIGNED DEFAULT NULL,
  `converted_by` INT UNSIGNED DEFAULT NULL COMMENT 'counselor/admin who closed it; NULL if self-checkout — deliberately no FK so it survives the staff account being hard-deleted later',
  `converted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lead_conversion` (`lead_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §2 Student Profile Extensions (student_profiles already exists)
ALTER TABLE `student_profiles`
  ADD COLUMN `grade` VARCHAR(20) DEFAULT NULL AFTER `admission_date`,
  ADD COLUMN `school_name` VARCHAR(150) DEFAULT NULL AFTER `grade`,
  ADD COLUMN `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata' AFTER `school_name`,
  ADD COLUMN `interests` JSON DEFAULT NULL COMMENT '["robotics","game_dev"]' AFTER `timezone`,
  ADD COLUMN `goals` JSON DEFAULT NULL COMMENT '["crack_placement","build_portfolio"]' AFTER `interests`,
  ADD COLUMN `coding_experience` ENUM('none','beginner','intermediate','advanced') DEFAULT 'none' AFTER `goals`,
  ADD COLUMN `preferred_language` VARCHAR(30) DEFAULT NULL COMMENT 'preferred programming language' AFTER `coding_experience`;

-- age is deliberately not stored — derived from users.date_of_birth at read
-- time, so it never drifts out of sync after a birthday.
CREATE TABLE `ai_profiles` (
  `user_id` INT UNSIGNED NOT NULL,
  `learning_pace` ENUM('slow','medium','fast') DEFAULT 'medium',
  `explanation_style` ENUM('visual','text','code_heavy','mixed') DEFAULT 'mixed',
  `weak_topics` JSON DEFAULT NULL,
  `strong_topics` JSON DEFAULT NULL,
  `persona_settings` JSON DEFAULT NULL COMMENT 'tone/age-band/language prefs fed to AI Gateway',
  `last_interaction_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §3 Parent Linking + Consent (parent_student_links already exists) —
-- compliance-critical, see docs/student-module/01 §8. Per-permission booleans
-- instead of a single role flag so split-custody parents can have different
-- visibility (e.g. one sees billing, the other doesn't).
ALTER TABLE `parent_student_links`
  ADD COLUMN `consent_status` ENUM('pending','granted','revoked') DEFAULT 'pending' AFTER `relationship`,
  ADD COLUMN `consent_method` ENUM('otp_verified','esignature','manual_admin') DEFAULT NULL AFTER `consent_status`,
  ADD COLUMN `consent_recorded_at` DATETIME DEFAULT NULL AFTER `consent_method`,
  ADD COLUMN `is_primary_guardian` TINYINT(1) DEFAULT 1 AFTER `consent_recorded_at`,
  ADD COLUMN `can_view_recordings` TINYINT(1) DEFAULT 1 AFTER `is_primary_guardian`,
  ADD COLUMN `can_view_billing` TINYINT(1) DEFAULT 1 AFTER `can_view_recordings`,
  ADD COLUMN `can_view_attendance` TINYINT(1) DEFAULT 1 AFTER `can_view_billing`,
  ADD COLUMN `can_book_ptm` TINYINT(1) DEFAULT 1 AFTER `can_view_attendance`;

-- §4 Credit Wallet / Billing Engine
CREATE TABLE `credit_wallets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `credits_purchased` INT UNSIGNED DEFAULT 0,
  `credits_bonus` INT UNSIGNED DEFAULT 0,
  `credits_promo` INT UNSIGNED DEFAULT 0,
  `credits_consumed` INT UNSIGNED DEFAULT 0,
  `credits_refunded` INT UNSIGNED DEFAULT 0,
  `credits_balance` INT NOT NULL DEFAULT 0 COMMENT 'maintained by trigger/app — never computed ad hoc in queries',
  `low_balance_threshold` TINYINT UNSIGNED DEFAULT 3,
  `expiry_date` DATE DEFAULT NULL,
  `status` ENUM('active','frozen','expired','exhausted') DEFAULT 'active',
  `frozen_at` DATETIME DEFAULT NULL,
  `frozen_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_enrollment` (`enrollment_id`),
  KEY `idx_wallet_student` (`student_id`),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only ledger — source of truth, never updated/deleted.
CREATE TABLE `credit_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('purchase','consumption','refund','bonus','promo','adjustment','expiry') NOT NULL,
  `amount` SMALLINT NOT NULL COMMENT 'signed: negative for consumption/expiry, positive otherwise',
  `balance_after` INT NOT NULL,
  `related_class_id` BIGINT UNSIGNED DEFAULT NULL,
  `related_payment_id` INT UNSIGNED DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = system-generated',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_credittxn_wallet` (`wallet_id`, `created_at`),
  FOREIGN KEY (`wallet_id`) REFERENCES `credit_wallets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `credit_deduction_policies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global default',
  `attendance_threshold_percent` TINYINT UNSIGNED DEFAULT 60,
  `allow_partial_deduction` TINYINT(1) DEFAULT 0,
  `early_exit_partial_percent` TINYINT UNSIGNED DEFAULT 50,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `credit_alerts_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `alert_type` ENUM('low_balance','exhausted','expiring_soon') NOT NULL,
  `channels_used` JSON DEFAULT NULL COMMENT '["whatsapp","email"]',
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_wallet_type` (`wallet_id`, `alert_type`),
  FOREIGN KEY (`wallet_id`) REFERENCES `credit_wallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PHASE 2b: ASSESSMENT + SCHEDULING
-- docs/student-module/02b-schema-assessment-scheduling.md
-- Design rule: reuse existing exams/questions/exam_attempts/batches/timetable/
-- live_classes — never fork them.
-- ============================================================

-- §1 Placement / Skill Assessment Engine — a placement test is just an exam
-- with a new type value, not a parallel table.
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

-- §2 Batch Allocation
ALTER TABLE `batches`
  ADD COLUMN `batch_type` ENUM('one_on_one','group') DEFAULT 'group' AFTER `mode`,
  ADD COLUMN `min_age` TINYINT UNSIGNED DEFAULT NULL AFTER `batch_type`,
  ADD COLUMN `max_age` TINYINT UNSIGNED DEFAULT NULL AFTER `min_age`,
  ADD COLUMN `skill_level` ENUM('beginner','intermediate','advanced','mixed') DEFAULT 'mixed' AFTER `max_age`,
  ADD COLUMN `primary_timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata' AFTER `skill_level`;

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

-- Append-only — answers "show me every batch this student has ever been in
-- and why," which batch_students (current membership only) can't.
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

-- §3 Class Scheduling Engine
ALTER TABLE `live_classes`
  ADD COLUMN `timetable_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL if ad-hoc/makeup, not generated from the recurring pattern' AFTER `batch_id`,
  ADD COLUMN `class_type` ENUM('regular','makeup','demo','extra') DEFAULT 'regular' AFTER `timetable_id`,
  ADD COLUMN `rescheduled_from_id` INT UNSIGNED DEFAULT NULL COMMENT 'points at the original session this one replaces' AFTER `class_type`,
  ADD COLUMN `cancellation_reason` ENUM('teacher_emergency','technical_outage','holiday','student_emergency','platform_maintenance') DEFAULT NULL AFTER `status`,
  ADD COLUMN `cancelled_by` INT UNSIGNED DEFAULT NULL AFTER `cancellation_reason`,
  ADD COLUMN `credit_charged` TINYINT(1) DEFAULT NULL COMMENT 'NULL = not yet resolved; set by the attendance/credit engine after the class ends' AFTER `attendee_count`,
  ADD CONSTRAINT `fk_liveclass_timetable` FOREIGN KEY (`timetable_id`) REFERENCES `timetable`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_liveclass_cancelledby` FOREIGN KEY (`cancelled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  MODIFY COLUMN `status` ENUM('scheduled','live','completed','cancelled','rescheduled') DEFAULT 'scheduled';

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
  FOREIGN KEY (`new_class_id`) REFERENCES `live_classes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
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
  FOREIGN KEY (`new_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PHASE 2c: CLASSROOM + CONTENT + DIGITAL NOTEBOOK
-- docs/student-module/02c-schema-classroom-content.md
-- ============================================================

-- §1 Attendance Engine (attendance already exists)
ALTER TABLE `attendance`
  ADD COLUMN `live_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'INT not BIGINT — corrected to match live_classes.id, which is INT UNSIGNED; the source doc declared BIGINT' AFTER `timetable_id`,
  ADD COLUMN `join_time` DATETIME DEFAULT NULL AFTER `check_in_time`,
  ADD COLUMN `leave_time` DATETIME DEFAULT NULL AFTER `check_out_time`,
  ADD COLUMN `duration_seconds` INT UNSIGNED DEFAULT 0 AFTER `leave_time`,
  ADD COLUMN `attendance_percent` TINYINT UNSIGNED DEFAULT 0 COMMENT 'duration_seconds / class duration * 100' AFTER `duration_seconds`,
  ADD COLUMN `marked_method` ENUM('auto_join','manual_override','auto_absent') DEFAULT 'auto_join' AFTER `marked_by`,
  MODIFY COLUMN `status` ENUM('present','absent','late','partial','excused') DEFAULT 'absent',
  ADD KEY `idx_attendance_liveclass` (`live_class_id`),
  ADD CONSTRAINT `fk_attendance_liveclass` FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE;

-- `uk_attendance` (batch_id, student_id, session_date) is the only index
-- covering batch_id's own FK to `batches` — MySQL refuses to drop it until a
-- replacement index for batch_id exists, so that has to be added first.
ALTER TABLE `attendance` ADD KEY `idx_attendance_batch` (`batch_id`);
ALTER TABLE `attendance` DROP INDEX `uk_attendance`;
ALTER TABLE `attendance` ADD UNIQUE KEY `uk_attendance_session` (`live_class_id`, `student_id`);

-- §2 Recording Pipeline
CREATE TABLE `class_recordings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` INT UNSIGNED NOT NULL,
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
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
  -- docs/student-module/01b's stated v1 search strategy on GoDaddy: MySQL
  -- FULLTEXT (free, built-in) rather than a separate OpenSearch/Elastic
  -- cluster — backs `GET /recordings/search?q=` (04c). Not present in any
  -- earlier schema pass; added here where it's actually first used.
  FULLTEXT KEY `ftx_recording_transcript` (`transcript_text`)
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

-- §3 Video Lecture Library (lessons/lesson_progress already exist)
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

-- §4 Notes / Materials (course-provided, not student-authored)
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
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
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

-- §5 Digital Notebook / Smart Notes
CREATE TABLE `notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `content` LONGTEXT NOT NULL,
  `content_format` ENUM('html','markdown') DEFAULT 'html',
  `linked_course_id` INT UNSIGNED DEFAULT NULL,
  `linked_lesson_id` INT UNSIGNED DEFAULT NULL,
  `linked_live_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'INT not BIGINT — see attendance.live_class_id note above',
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
ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_sourcenote` FOREIGN KEY (`source_note_id`) REFERENCES `notes`(`id`) ON DELETE SET NULL;

-- ============================================================
-- PHASE 2d: SANDBOX + COLLABORATIVE CODING + CODE REPLAY + AI
-- docs/student-module/02d-schema-sandbox-collab-ai.md
-- ============================================================

-- §1 Coding Sandbox / Cloud IDE
CREATE TABLE `code_workspaces` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `language` ENUM('python','java','c','cpp','javascript','html_css','sql','php') NOT NULL,
  `linked_course_id` INT UNSIGNED DEFAULT NULL,
  `linked_lesson_id` INT UNSIGNED DEFAULT NULL,
  `linked_assignment_id` INT UNSIGNED DEFAULT NULL,
  `is_multi_file` TINYINT(1) DEFAULT 0,
  `last_opened_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_workspace_student` (`student_id`, `last_opened_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`linked_assignment_id`) REFERENCES `assignments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `workspace_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'folder structure for multi-file projects',
  `content` LONGTEXT NOT NULL COMMENT 'live, continuously autosaved current state',
  `is_entry_point` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_file_workspace` (`workspace_id`),
  FOREIGN KEY (`workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `file_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `content` LONGTEXT NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `created_by` ENUM('manual_save','pre_execution','periodic') DEFAULT 'periodic',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_version_file` (`file_id`),
  FOREIGN KEY (`file_id`) REFERENCES `workspace_files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `code_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `trigger_source` ENUM('manual_run','assignment_submission','assessment','ai_assistant_check') DEFAULT 'manual_run',
  `stdin` TEXT DEFAULT NULL,
  `stdout` LONGTEXT DEFAULT NULL,
  `stderr` LONGTEXT DEFAULT NULL,
  `exit_code` INT DEFAULT NULL,
  `execution_time_ms` INT UNSIGNED DEFAULT NULL,
  `memory_used_kb` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('queued','running','completed','timeout','memory_exceeded','error') DEFAULT 'queued',
  `sandbox_node` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exec_workspace` (`workspace_id`, `created_at`),
  FOREIGN KEY (`workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §2 Collaborative Coding (Pair Programming) — real-time sync is Yjs over
-- Pusher/Ably, never MySQL; this stores only membership + durability snapshots.
CREATE TABLE `collab_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `workspace_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `session_type` ENUM('live_class','hackathon','project','practice') DEFAULT 'practice',
  `linked_live_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'INT not BIGINT — see attendance.live_class_id note in Phase 2c',
  `status` ENUM('active','ended') DEFAULT 'active',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`linked_live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `collab_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `collab_session_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','collaborator','teacher_observer') DEFAULT 'collaborator',
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `left_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_participant_session` (`collab_session_id`),
  FOREIGN KEY (`collab_session_id`) REFERENCES `collab_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `collab_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `collab_session_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `yjs_state` LONGBLOB NOT NULL COMMENT 'binary CRDT document state, for reload/durability — not a live sync channel',
  `snapshot_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot_session` (`collab_session_id`, `snapshot_at`),
  FOREIGN KEY (`collab_session_id`) REFERENCES `collab_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §3 Code Replay — keystroke-level event volume never lands in MySQL;
-- compressed stream lives in S3, this is a thin, fast-seekable index.
CREATE TABLE `code_replay_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` BIGINT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `s3_event_log_url` VARCHAR(500) DEFAULT NULL COMMENT 'compressed keystroke/diff/execution event stream',
  `total_duration_seconds` INT UNSIGNED DEFAULT 0,
  `total_keystrokes` INT UNSIGNED DEFAULT 0,
  `total_executions` INT UNSIGNED DEFAULT 0,
  `total_errors` INT UNSIGNED DEFAULT 0,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_replay_workspace` (`workspace_id`),
  FOREIGN KEY (`workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `code_replay_markers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `replay_session_id` BIGINT UNSIGNED NOT NULL,
  `marker_type` ENUM('execution','error','fix','teacher_intervention') NOT NULL,
  `timestamp_offset_ms` INT UNSIGNED NOT NULL COMMENT 'offset from session start — drives the replay timeline slider',
  `summary` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marker_session` (`replay_session_id`, `timestamp_offset_ms`),
  FOREIGN KEY (`replay_session_id`) REFERENCES `code_replay_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §4 AI Domain (Doubt Solver, Coding Assistant, Risk Detection) — prompt/Gateway
-- design is docs/student-module/05a-05d, not this schema.
CREATE TABLE `ai_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` INT UNSIGNED NOT NULL,
  `conversation_type` ENUM('doubt_solver','coding_assistant') NOT NULL,
  `mode` ENUM('hint','explain','practice','debug','review') DEFAULT 'hint',
  `linked_course_id` INT UNSIGNED DEFAULT NULL,
  `linked_lesson_id` INT UNSIGNED DEFAULT NULL,
  `linked_workspace_id` BIGINT UNSIGNED DEFAULT NULL,
  `language` VARCHAR(10) DEFAULT 'en',
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_student` (`student_id`, `last_message_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`linked_workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ai_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('user','assistant','system') NOT NULL,
  `content` LONGTEXT NOT NULL,
  `model_used` VARCHAR(50) DEFAULT NULL,
  `tokens_input` INT UNSIGNED DEFAULT NULL,
  `tokens_output` INT UNSIGNED DEFAULT NULL,
  `cost_usd` DECIMAL(10,6) DEFAULT NULL,
  `latency_ms` INT UNSIGNED DEFAULT NULL,
  `rag_sources` JSON DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_conversation` (`conversation_id`, `created_at`),
  FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ai_usage_quotas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `period` ENUM('daily','monthly') NOT NULL,
  `period_start` DATE NOT NULL,
  `messages_used` INT UNSIGNED DEFAULT 0,
  `tokens_used` BIGINT UNSIGNED DEFAULT 0,
  `cost_usd_used` DECIMAL(10,4) DEFAULT 0,
  `quota_limit_messages` INT UNSIGNED DEFAULT NULL,
  `quota_limit_cost_usd` DECIMAL(10,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quota_period` (`student_id`, `period`, `period_start`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `risk_scores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `score_type` ENUM('dropout_risk','low_engagement','weak_topic','absenteeism') NOT NULL,
  `score_value` TINYINT UNSIGNED NOT NULL COMMENT '0-100',
  `contributing_factors` JSON DEFAULT NULL,
  `intervention_status` ENUM('none','ai_nudge_sent','whatsapp_sent','email_sent','mentor_call_scheduled','parent_escalated') DEFAULT 'none',
  `computed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_risk_student_type` (`student_id`, `score_type`, `computed_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ai_insights` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `insight_type` ENUM('repeated_mistake','weak_topic','improvement_area','strength') NOT NULL,
  `source_type` ENUM('code_replay','assignment','assessment','ai_conversation') NOT NULL,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `detail` JSON DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_insight_student` (`student_id`, `insight_type`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PHASE 2e: PROJECTS + GAMIFICATION + ACHIEVEMENT WALL + SUPPORT + PTM +
-- CALENDAR + COMMUNICATION
-- docs/student-module/02e-schema-projects-gamification-support.md
-- ============================================================

-- §1 Projects + Publishing — assignments.type already includes 'project'
-- (database/schema.sql); projects are assignments with richer submission
-- types and a publishing layer, not a parallel system.
ALTER TABLE `assignment_submissions`
  ADD COLUMN `github_repo_url` VARCHAR(500) DEFAULT NULL AFTER `url`,
  ADD COLUMN `demo_video_url` VARCHAR(500) DEFAULT NULL AFTER `github_repo_url`,
  ADD COLUMN `screenshots` JSON DEFAULT NULL COMMENT 'array of S3 URLs' AFTER `demo_video_url`,
  ADD COLUMN `originality_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100, higher = more original — see docs/student-module/05d' AFTER `feedback`,
  ADD COLUMN `plagiarism_report_url` VARCHAR(500) DEFAULT NULL AFTER `originality_score`;

CREATE TABLE `published_projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `cover_image_url` VARCHAR(500) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_public` TINYINT(1) DEFAULT 0,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `view_count` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_published_submission` (`submission_id`),
  KEY `idx_published_student` (`student_id`, `is_public`),
  FOREIGN KEY (`submission_id`) REFERENCES `assignment_submissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §2 Gamification — append-only ledger, same principle as credits.
CREATE TABLE `student_xp` (
  `student_id` INT UNSIGNED NOT NULL,
  `total_xp` INT UNSIGNED DEFAULT 0,
  `current_level` SMALLINT UNSIGNED DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `xp_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `amount` INT NOT NULL COMMENT 'signed',
  `reason` ENUM('class_attended','assignment_submitted','project_completed','quiz_won','streak_bonus','referral','manual_adjustment') NOT NULL,
  `source_type` VARCHAR(50) DEFAULT NULL,
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_xptxn_student` (`student_id`, `created_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `badges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `icon_url` VARCHAR(500) DEFAULT NULL,
  `criteria_type` ENUM('xp_threshold','streak','course_completion','project_count','custom') NOT NULL,
  `criteria_value` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `student_badges` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `badge_id` INT UNSIGNED NOT NULL,
  `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_badge` (`student_id`, `badge_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `student_streaks` (
  `student_id` INT UNSIGNED NOT NULL,
  `current_streak_days` INT UNSIGNED DEFAULT 0,
  `longest_streak_days` INT UNSIGNED DEFAULT 0,
  `last_activity_date` DATE DEFAULT NULL,
  `streak_freezes_remaining` TINYINT UNSIGNED DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Leaderboard is a query, not a table — see docs/student-module/02e/07. Add
-- `KEY idx_xp_leaderboard (total_xp DESC)` on student_xp if/when read volume
-- justifies it.

-- §3 Achievement Showcase Wall / Public Portfolio — is_public defaults to 0;
-- a minor's achievements aren't shareable without explicit opt-in.
CREATE TABLE `student_portfolios` (
  `student_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(100) NOT NULL COMMENT 'public URL: /student/portfolio/{slug}',
  `is_public` TINYINT(1) DEFAULT 0,
  `headline` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `show_certificates` TINYINT(1) DEFAULT 1,
  `show_badges` TINYINT(1) DEFAULT 1,
  `show_projects` TINYINT(1) DEFAULT 1,
  `theme` VARCHAR(30) DEFAULT 'default',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uk_portfolio_slug` (`slug`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `portfolio_views` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_student_id` INT UNSIGNED NOT NULL,
  `viewer_ip_hash` CHAR(64) DEFAULT NULL COMMENT 'SHA-256 of viewer IP — analytics without storing raw IPs',
  `viewed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_view_portfolio` (`portfolio_student_id`, `viewed_at`),
  FOREIGN KEY (`portfolio_student_id`) REFERENCES `student_portfolios`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §4 Support — existing support_tickets/ticket_replies/support_categories
-- reused as-is. No new tables.

-- §5 Parent-Teacher Meeting (PTM) Booking — uk_ptm_slot is what actually
-- prevents double-booking under concurrent requests, not app-layer checking.
CREATE TABLE `ptm_slots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `host_id` INT UNSIGNED NOT NULL COMMENT 'teacher, mentor, or academic head',
  `slot_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_booked` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slot_host_date` (`host_id`, `slot_date`, `is_booked`),
  FOREIGN KEY (`host_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ptm_bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slot_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `meeting_type` ENUM('progress_review','concern_discussion','performance_intervention','renewal_counseling') DEFAULT 'progress_review',
  `status` ENUM('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `meeting_link` VARCHAR(500) DEFAULT NULL,
  `pre_meeting_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ptm_slot` (`slot_id`),
  FOREIGN KEY (`slot_id`) REFERENCES `ptm_slots`(`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ptm_summaries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `summary` TEXT NOT NULL,
  `action_items` JSON DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ptm_booking` (`booking_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `ptm_bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §6 Calendar Integration — tokens encrypted at rest at the application layer.
CREATE TABLE `calendar_connections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` ENUM('google','outlook','apple') NOT NULL,
  `access_token_encrypted` TEXT NOT NULL,
  `refresh_token_encrypted` TEXT DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `connected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendar_user_provider` (`user_id`, `provider`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `calendar_sync_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `event_type` ENUM('live_class','assignment_due','ptm','assessment') NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `external_event_id` VARCHAR(255) DEFAULT NULL,
  `synced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sync_event` (`connection_id`, `event_type`, `source_id`),
  FOREIGN KEY (`connection_id`) REFERENCES `calendar_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- §7 Cross-Cutting: Communication Log + Admin<->Student Event Sync
-- communication_logs is what every trigger across all 39 lifecycle phases
-- writes to — docs/student-module/06-communication-engine.md.
CREATE TABLE `communication_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'recipient',
  `channel` ENUM('whatsapp','email','sms','push','in_app','ivr','manual_call') NOT NULL,
  `trigger_event` VARCHAR(100) NOT NULL COMMENT 'e.g. low_credit_alert, assignment_reminder_24h',
  `template_used` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('queued','sent','delivered','failed','read') DEFAULT 'queued',
  `provider_message_id` VARCHAR(150) DEFAULT NULL,
  `retry_count` TINYINT UNSIGNED DEFAULT 0,
  `failed_reason` VARCHAR(255) DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comm_user_event` (`user_id`, `trigger_event`),
  KEY `idx_comm_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The outbox table (docs/student-module/01 §3) — either side (Admin PHP or
-- Student Portal PHP) polls WHERE processed_by_x = 0 and marks itself done.
CREATE TABLE `domain_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL COMMENT 'e.g. credit.adjusted, teacher.reassigned',
  `aggregate_type` VARCHAR(50) NOT NULL COMMENT 'e.g. enrollment, batch',
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `payload` JSON NOT NULL,
  `occurred_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `processed_by_admin` TINYINT(1) DEFAULT 0,
  `processed_by_student_app` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event_unprocessed` (`processed_by_admin`, `processed_by_student_app`, `occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: escalation/family batching queue
-- docs/student-module/06-communication-engine.md §4 — a batchable
-- trigger doesn't send immediately; App\Core\Notifier writes it here
-- instead, and cron/process-notification-batches.php drains it, grouping
-- not-yet-sent rows for the same recipient (or the same `batch_key`, for
-- the family-level case §4 explicitly separates from per-student grouping
-- — e.g. one row per child's monthly_parent_report_ready, all sharing the
-- parent's family batch_key) within a short window into one combined send.
-- Non-batchable triggers never touch this table — Notifier::send() sends
-- those immediately and standalone, per-trigger config (06 §4).
-- ============================================================
CREATE TABLE `notification_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'recipient this row will be delivered to',
  `trigger_event` VARCHAR(100) NOT NULL,
  `context` JSON DEFAULT NULL,
  `batch_key` VARCHAR(100) DEFAULT NULL COMMENT 'family-level grouping key; NULL means "group by user_id alone" (06 §4)',
  `status` ENUM('pending','sent','failed') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue_pending` (`status`, `created_at`),
  KEY `idx_queue_batch` (`batch_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- WORKFLOW-DOC SCHEMA ADDITIONS (03f, 03g, 03h, 03j)
-- Introduced inline in the workflow docs rather than pre-planned in Phase 2 —
-- same "discovered while writing the workflow" pattern each time.
-- ============================================================

-- docs/student-module/03f-workflows-assessment-projects.md §21 — precomputed,
-- not live-aggregated: a nightly job writes one row per active enrollment per
-- day; dashboards read the latest snapshot instead of recomputing expensive
-- joins on every page view.
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
  `coding_success_rate` DECIMAL(5,2) DEFAULT NULL COMMENT 'share of executions with exit_code=0',
  `computed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_snapshot_day` (`student_id`, `enrollment_id`, `snapshot_date`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- docs/student-module/03g-workflows-risk-parent-billing.md §24
CREATE TABLE `parent_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `period_month` DATE NOT NULL COMMENT 'first day of the reporting month',
  `is_partial_period` TINYINT(1) DEFAULT 0,
  `pdf_url` VARCHAR(500) DEFAULT NULL,
  `summary_text` TEXT DEFAULT NULL COMMENT 'AI-generated strengths/weaknesses/recommendations — docs/student-module/05c §5',
  `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `viewed_by_parent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_period` (`student_id`, `enrollment_id`, `period_month`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- docs/student-module/03g-workflows-risk-parent-billing.md §25 — kept separate
-- from credit_transactions since freezing/resuming moves no credits; folding
-- it into the financial ledger would pollute it with non-financial events.
CREATE TABLE `wallet_freeze_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `action` ENUM('frozen','resumed') NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `effective_date` DATE NOT NULL,
  `requested_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_freeze_wallet` (`wallet_id`, `created_at`),
  FOREIGN KEY (`wallet_id`) REFERENCES `credit_wallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- docs/student-module/03h-workflows-completion-growth.md §28
CREATE TABLE `course_recommendations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `source_enrollment_id` INT UNSIGNED DEFAULT NULL,
  `recommended_course_id` INT UNSIGNED NOT NULL,
  `confidence_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100',
  `reason_summary` VARCHAR(255) DEFAULT NULL,
  `shown_at` DATETIME DEFAULT NULL,
  `converted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reco_student` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recommended_course_id`) REFERENCES `courses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- docs/student-module/03h-workflows-completion-growth.md §29
CREATE TABLE `referral_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_referral_user` (`user_id`),
  UNIQUE KEY `uk_referral_code` (`code`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `referrals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_user_id` INT UNSIGNED NOT NULL,
  `referral_code` VARCHAR(20) NOT NULL,
  `referred_lead_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','converted','expired') DEFAULT 'pending',
  `reward_type` ENUM('credits','cash','discount') DEFAULT 'credits',
  `reward_value` DECIMAL(10,2) DEFAULT NULL,
  `reward_status` ENUM('pending','approved','paid') DEFAULT 'pending',
  `converted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_referrer` (`referrer_user_id`),
  FOREIGN KEY (`referrer_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`referred_lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- docs/student-module/03j-workflows-quizzes-replay-offline.md — Live Quizzes
-- and Offline Access needed schema not covered in Phase 2, added alongside
-- that workflow doc (see 00-master-index.md note against row 3j).
CREATE TABLE `live_quizzes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` INT UNSIGNED NOT NULL COMMENT 'INT not BIGINT — see attendance.live_class_id note in Phase 2c',
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
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
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

-- ============================================================
-- CORRECTION (docs/student-module/04d): assignment_submissions amendment
-- The real `assignment_submissions.status` enum (database/schema.sql) had no
-- `draft` state, and no per-student deadline-extension column. 03d called for
-- both but neither was ever turned into SQL until 04d. Two minimal amendments
-- to the EXISTING table — an earlier pass at this mistakenly assumed the
-- table itself was missing and tried to recreate it; it wasn't (see
-- 00-master-index.md's note against row 4d).
-- ============================================================
ALTER TABLE `assignment_submissions`
  MODIFY COLUMN `status` ENUM('draft','submitted','graded','returned','resubmitted') DEFAULT 'draft',
  ADD COLUMN `extended_due_date` DATETIME DEFAULT NULL COMMENT 'per-student override, checked in preference to assignments.due_date (03d)' AFTER `submitted_at`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: Parent Consent OTP storage
-- docs/student-module/04a-apis-conventions-enrollment-billing.md's
-- `POST /parent/consent/{linkId}/grant` validates an OTP "issued for this
-- link within its validity window" — no doc in the 35-document series ever
-- specified where that issued OTP is actually stored. Same pattern as every
-- other gap caught and filled inline during this project (03j, 04d): add the
-- minimal table the already-written behavior requires.
-- ============================================================
CREATE TABLE `parent_consent_otps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL COMMENT 'default validity window: 10 minutes from issue',
  `consumed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_link` (`parent_id`, `student_id`, `consumed_at`),
  FOREIGN KEY (`parent_id`, `student_id`) REFERENCES `parent_student_links`(`parent_id`, `student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ARCHITECTURE CHANGE: pure peer-to-peer WebRTC live classroom
-- This platform runs entirely on GoDaddy shared hosting long-term — no
-- Agora, no Pusher/Ably, no relay/SFU server of any kind, by deliberate
-- choice (every participant's browser connects directly to every other
-- participant's). This table is the entire signaling channel: standing in
-- for what a WebSocket push would normally do, polled over plain HTTP
-- since shared hosting can't hold a persistent connection open. Replaces
-- the originally-designed Agora-token approach (docs/student-module/02d/04c
-- assumed Agora; this is a deliberate, later architecture change, not a
-- gap in the original docs).
-- ============================================================
CREATE TABLE `webrtc_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` INT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `to_user_id` INT UNSIGNED NOT NULL COMMENT 'mesh topology — one row per peer, not a broadcast',
  `type` ENUM('offer','answer','ice_candidate','leave') NOT NULL,
  `payload` JSON NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `consumed_at` DATETIME DEFAULT NULL COMMENT 'set the instant the addressed peer polls it, so a slow client never double-processes the same message',
  PRIMARY KEY (`id`),
  KEY `idx_signal_inbox` (`live_class_id`, `to_user_id`, `consumed_at`),
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lightweight in-call text chat — same polling pattern as signaling,
-- deliberately not folded into webrtc_signals since a chat message is
-- broadcast to everyone in the room, not addressed pairwise to one peer.
CREATE TABLE `live_class_chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_class_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_class` (`live_class_id`, `created_at`),
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: AI near-duplicate flagging
-- docs/student-module/03e/05a describe flagging a conversation for mentor
-- review after 3+ near-duplicate asks, but `ai_conversations` never had a
-- column to actually record that flag on. Original design detected
-- duplicates via embedding similarity (Pinecone) — with no vector DB of any
-- kind in this no-cloud build, AiController instead uses a local text-
-- similarity heuristic (PHP's similar_text()), but the flag it sets is the
-- same one this column was always going to need regardless of detection
-- method.
-- ============================================================
ALTER TABLE `ai_conversations`
  ADD COLUMN `flagged_for_review` TINYINT(1) DEFAULT 0 AFTER `language`,
  ADD COLUMN `flagged_reason` VARCHAR(255) DEFAULT NULL AFTER `flagged_for_review`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: project originality check queue
-- docs/student-module/04e describes submitting a project as queuing an
-- "asynchronous" originality check that populates assignment_submissions.
-- originality_score sometime after the response returns. The original
-- design ran this via embedding-similarity (Pinecone) plus a third-party
-- plagiarism service — both cloud dependencies this build doesn't have.
-- Reusing the one accepted exception (a direct Anthropic API call, see
-- App\Core\AiGateway), this queue table is what makes "asynchronous"
-- concretely real on shared hosting: AssignmentController::submit() inserts
-- a 'pending' row instead of blocking, and a GoDaddy cPanel cron job
-- (api/cron/process-originality-checks.php) drains it periodically — cron
-- jobs are a real, ordinary cPanel feature, not a cloud service.
-- ============================================================
CREATE TABLE `originality_check_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','done','failed') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_originality_submission` (`submission_id`),
  KEY `idx_originality_status` (`status`),
  FOREIGN KEY (`submission_id`) REFERENCES `assignment_submissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: confirmed certificate name
-- docs/student-module/04g's POST /enrollments/{id}/confirm-certificate-name
-- exists specifically so a nickname used at enrollment never silently ends
-- up on a printed credential — but no column anywhere stores that
-- confirmation. "Skipping it just means the certificate generates from
-- users.first_name/last_name as-is" only works if there's somewhere to
-- record the override when a student *doesn't* skip it.
-- ============================================================
ALTER TABLE `enrollments`
  ADD COLUMN `confirmed_certificate_name` VARCHAR(200) DEFAULT NULL AFTER `certificate_id`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: real SM-2 spaced repetition needs ease tracking
-- docs/student-module/04h calls POST /flashcards/{id}/review "a deterministic
-- spaced-repetition algorithm (SM-2-style interval growth)" — but `flashcards`
-- only ever had review_count/last_reviewed_at/next_review_at. A real SM-2
-- (not a fake one that just multiplies a constant) needs to remember the
-- per-card ease factor and the interval actually used last time, both of
-- which would otherwise be silently lost the moment next_review_at gets
-- overwritten by the following review.
-- ============================================================
ALTER TABLE `flashcards`
  ADD COLUMN `ease_factor` DECIMAL(3,2) DEFAULT 2.50 AFTER `next_review_at`,
  ADD COLUMN `interval_days` INT UNSIGNED DEFAULT 0 AFTER `ease_factor`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: collaborative coding signaling
-- docs/student-module/04h's Collaborative Coding design assumed Pusher/Ably
-- for the actual keystroke-level Yjs CRDT sync, with this REST API only
-- handling session membership/auth bootstrap. With no Pusher/Ably (or any
-- cloud service) anywhere in this build, peer connection setup reuses the
-- exact same pure-P2P-WebRTC + polling-signal pattern ClassroomController
-- already established for live classes — but `webrtc_signals` is tightly
-- FK'd to `live_class_id` specifically, so a parallel table (same shape,
-- different FK target) is the lower-risk choice over generalizing an
-- already-tested table.
-- ============================================================
CREATE TABLE `collab_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `collab_session_id` BIGINT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `to_user_id` INT UNSIGNED NOT NULL COMMENT 'mesh topology — one row per peer, not a broadcast',
  `type` ENUM('offer','answer','ice_candidate','leave') NOT NULL,
  `payload` JSON NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `consumed_at` DATETIME DEFAULT NULL COMMENT 'set the instant the addressed peer polls it, so a slow client never double-processes the same message',
  PRIMARY KEY (`id`),
  KEY `idx_collabsignal_inbox` (`collab_session_id`, `to_user_id`, `consumed_at`),
  FOREIGN KEY (`collab_session_id`) REFERENCES `collab_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: note tags
-- docs/student-module/04h's GET /notes?course_id=&tag=&favorite= implies
-- tag filtering, but `notes` never had a tags column at all.
-- ============================================================
ALTER TABLE `notes` ADD COLUMN `tags` JSON DEFAULT NULL AFTER `is_favorite`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: live quiz ad-hoc grading + explain-mode link
-- docs/student-module/04i §34. `live_quizzes` already stores
-- `ad_hoc_question_text`/`ad_hoc_options` for a teacher-typed-on-the-fly
-- question, but nowhere to record *which* option is correct — only a
-- question pulled from the bank (`question_id` -> questions.correct_answer)
-- could ever be auto-graded. And `live_quiz_responses` had nowhere to
-- remember the AI Doubt Solver conversation a student starts from the
-- results screen ("explain_mode_conversation_id populates once the client
-- does so" — but populates *where*, with no column to hold it).
-- ============================================================
ALTER TABLE `live_quizzes`
  ADD COLUMN `ad_hoc_correct_answer` TEXT DEFAULT NULL AFTER `ad_hoc_options`;

ALTER TABLE `live_quiz_responses`
  ADD COLUMN `explain_mode_conversation_id` BIGINT UNSIGNED DEFAULT NULL AFTER `submitted_at`,
  ADD CONSTRAINT `fk_quizresponse_aiconv` FOREIGN KEY (`explain_mode_conversation_id`) REFERENCES `ai_conversations`(`id`) ON DELETE SET NULL;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: PTM meeting room signaling
-- docs/student-module/04j §39 — "meeting_link points at a lighter-weight
-- video/audio room than the internal classroom... deliberately without the
-- sandbox/whiteboard/AI-panel machinery" (none of which exists anywhere in
-- this build regardless). The same pure-P2P-WebRTC + polling-signal
-- pattern as ClassroomController/CollabSessionController, a third parallel
-- table since `webrtc_signals`/`collab_signals` are each tightly FK'd to a
-- different parent entity. Just two participants (parent + host), so no
-- presence/mesh-of-many complexity is needed — see PtmController.
-- ============================================================
CREATE TABLE `ptm_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `to_user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('offer','answer','ice_candidate','leave') NOT NULL,
  `payload` JSON NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `consumed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ptmsignal_inbox` (`booking_id`, `to_user_id`, `consumed_at`),
  FOREIGN KEY (`booking_id`) REFERENCES `ptm_bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: pending portfolio public request
-- docs/student-module/04j's PUT /portfolio response shows
-- `{ "is_public": false, "status": "pending_parent_approval" }` for a
-- minor's off->on transition — `is_public` stays false until approved, but
-- nothing distinguishes "false because nobody asked" from "false because
-- a request is awaiting parent approval" without a column to hold that.
-- ============================================================
ALTER TABLE `student_portfolios`
  ADD COLUMN `pending_public_request` TINYINT(1) DEFAULT 0 AFTER `is_public`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: AI usage log for non-conversational calls
-- docs/student-module/05a says every AI Gateway call's cost/tokens get
-- written "in the same operation that persists the response" — but
-- `ai_messages.conversation_id` is NOT NULL, FK'd to `ai_conversations`,
-- and Notebook AI features (summarize/flashcards/quiz-generation,
-- recording-to-notes) are one-shot utility calls with no conversation to
-- attach to. Without somewhere to log these, their cost would be invisible
-- to both per-student reporting and the platform-wide daily spend circuit
-- breaker (App\Core\AiGateway) — silently undercounting real spend.
-- ============================================================
CREATE TABLE `ai_usage_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `feature` VARCHAR(50) NOT NULL COMMENT 'e.g. notebook.summarize, notebook.generate_notes',
  `model_used` VARCHAR(80) DEFAULT NULL,
  `tokens_input` INT UNSIGNED DEFAULT NULL,
  `tokens_output` INT UNSIGNED DEFAULT NULL,
  `cost_usd` DECIMAL(10,6) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usagelog_student` (`student_id`, `created_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: communication-score transparency
-- docs/student-module/05d §1 — "returns a communication_score (0-100)
-- plus a short rationale, the same transparency principle
-- risk_scores.contributing_factors already established: a score with no
-- visible 'why' is useless to the mentor who has to act on it." No column
-- existed anywhere to hold that rationale.
-- ============================================================
ALTER TABLE `placement_results`
  ADD COLUMN `communication_score_rationale` TEXT DEFAULT NULL AFTER `communication_score`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: course recommendation mapping
-- docs/student-module/05d §3's Stage 1 (deterministic, no model call) reads
-- from "a curriculum-maintained course_next_steps mapping" — no such table
-- existed anywhere in the schema. Admin-curated (e.g. via phpMyAdmin until
-- an Admin-panel UI exists for it, out of this student-portal codebase's
-- scope) — empty by default; cron/generate-course-recommendations.php
-- simply has nothing to recommend until rows exist, which is the correct,
-- honest behavior (04g's "explore_other_tracks" graceful no-fit state),
-- not a bug.
-- ============================================================
CREATE TABLE `course_next_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_course_id` INT UNSIGNED NOT NULL,
  `recommended_course_id` INT UNSIGNED NOT NULL,
  `sort_order` SMALLINT UNSIGNED DEFAULT 0,
  `min_completion_percent` TINYINT UNSIGNED DEFAULT 70 COMMENT 'minimum course_completion_percent on the source course for this mapping to apply',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_next_step` (`source_course_id`, `recommended_course_id`),
  FOREIGN KEY (`source_course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recommended_course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: exam_responses row timestamp
-- docs/student-module/07-scaling-strategy.md §5 generalizes 02d's
-- ai_messages archival pattern to every high-volume ledger, including this
-- one — but `exam_responses` had no `created_at` of its own to archive by
-- (its timing was only ever implicit via the parent exam_attempts row).
-- Added for new rows going forward; cron/archive-ledger-content.php
-- deliberately uses the PARENT exam_attempts.submitted_at as the real age
-- signal for this table anyway (a response's true age is when the exam was
-- submitted, not when this column happens to default on a future schema
-- migration) — this column exists mainly so a future row has its own
-- timestamp without depending on a join, not as the archival cutoff itself.
-- ============================================================
ALTER TABLE `exam_responses`
  ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `grader_feedback`;

-- ============================================================
-- IMPLEMENTATION GAP-FILL: queryable health-check results
-- docs/student-module/08-infrastructure-devops.md §3's business-specific
-- alerts (credit-ledger reconciliation drift, support SLA breaches) need
-- somewhere to record "what did the last check find," so an external
-- uptime service (UptimeRobot/Pingdom — 08 §3's stated GoDaddy-specific
-- substitute for CloudWatch, since no infrastructure console exists
-- underneath shared hosting) polling HealthController has something real
-- to read rather than re-running an expensive check on every poll.
-- cron/reconcile-credit-ledger.php and cron/check-support-sla-breaches.php
-- both upsert their own row here; the actual paging itself still happens
-- via App\Core\Notifier in the same cron run, not by anyone polling this table.
-- ============================================================
CREATE TABLE `system_health_checks` (
  `check_name` VARCHAR(80) NOT NULL,
  `last_run_at` DATETIME DEFAULT NULL,
  `status` ENUM('ok', 'warning', 'critical') DEFAULT 'ok',
  `details` JSON DEFAULT NULL,
  PRIMARY KEY (`check_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
