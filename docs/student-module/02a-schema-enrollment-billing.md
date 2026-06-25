# Delivery Phase 2a — Database Schema: Enrollment + Billing/Credits

Extends the existing `codegurukul` Aurora MySQL schema. Reuses `users`, `enrollments`, `courses`, `payments`, `student_profiles`, `parent_student_links` — does not fork them.

Design rule carried through every table below: **lead/demo data is never copied on conversion, only linked forward.** Copying creates two sources of truth; a foreign key back to `leads`/`demo_sessions` lets Admin and Student views join to the original record whenever needed.

---

## 1. Lead-to-Enrollment Tables

```sql
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

CREATE TABLE `lead_conversions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `payment_id` INT UNSIGNED DEFAULT NULL,
  `converted_by` INT UNSIGNED DEFAULT NULL COMMENT 'counselor/admin who closed it; NULL if self-checkout',
  `converted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lead_conversion` (`lead_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Why a separate `lead_conversions` table instead of an `enrollments.lead_id` column:** keeps the high-volume, low-value `leads` table fully decoupled from the core `enrollments` table — a lead funnel can be redesigned, archived, or even moved to a separate CRM later without touching the enrollment schema at all.

---

## 2. Student Profile Extensions

`student_profiles` already exists (enrollment_number, admission_date, github_url, skills JSON, etc.). Add the fields the lifecycle spec requires:

```sql
ALTER TABLE `student_profiles`
  ADD COLUMN `grade` VARCHAR(20) DEFAULT NULL AFTER `admission_date`,
  ADD COLUMN `school_name` VARCHAR(150) DEFAULT NULL AFTER `grade`,
  ADD COLUMN `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata' AFTER `school_name`,
  ADD COLUMN `interests` JSON DEFAULT NULL COMMENT '["robotics","game_dev"]' AFTER `timezone`,
  ADD COLUMN `goals` JSON DEFAULT NULL COMMENT '["crack_placement","build_portfolio"]' AFTER `interests`,
  ADD COLUMN `coding_experience` ENUM('none','beginner','intermediate','advanced') DEFAULT 'none' AFTER `goals`,
  ADD COLUMN `preferred_language` VARCHAR(30) DEFAULT NULL COMMENT 'preferred programming language' AFTER `coding_experience`;
```

`age` is deliberately **not** stored — it's derived from `users.date_of_birth` at read time (an age column would drift out of sync every birthday; a generated column or app-layer calculation avoids that class of bug entirely).

```sql
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
```

---

## 3. Parent Linking + Consent (compliance-critical — see Phase 1 §8)

`parent_student_links` already exists (parent_id, student_id, relationship, linked_at). Extend it:

```sql
ALTER TABLE `parent_student_links`
  ADD COLUMN `consent_status` ENUM('pending','granted','revoked') DEFAULT 'pending' AFTER `relationship`,
  ADD COLUMN `consent_method` ENUM('otp_verified','esignature','manual_admin') DEFAULT NULL AFTER `consent_status`,
  ADD COLUMN `consent_recorded_at` DATETIME DEFAULT NULL AFTER `consent_method`,
  ADD COLUMN `is_primary_guardian` TINYINT(1) DEFAULT 1 AFTER `consent_recorded_at`,
  ADD COLUMN `can_view_recordings` TINYINT(1) DEFAULT 1 AFTER `is_primary_guardian`,
  ADD COLUMN `can_view_billing` TINYINT(1) DEFAULT 1 AFTER `can_view_recordings`,
  ADD COLUMN `can_view_attendance` TINYINT(1) DEFAULT 1 AFTER `can_view_billing`,
  ADD COLUMN `can_book_ptm` TINYINT(1) DEFAULT 1 AFTER `can_view_attendance`;
```

**Why per-permission booleans instead of a single role flag:** a divorced/separated-parents scenario (real, common edge case) needs one guardian able to see billing while another only sees academic progress. A single "parent role" can't express that; explicit booleans can, and require no schema change to add more later (just more columns or a follow-up `parent_permissions` JSON column once the list stabilizes).

**Account activation rule enforced at the application layer, recorded here:** a student account under the configured minor-age threshold cannot transition to `active` status until at least one linked parent row has `consent_status = 'granted'`.

---

## 4. Credit Wallet / Billing Engine

```sql
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
  `attendance_threshold_percent` TINYINT UNSIGNED DEFAULT 60 COMMENT 'min attendance % in a class to count as consumed',
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
```

**Why `credit_transactions` is append-only and `credits_balance` is denormalized onto the wallet:** the ledger (transactions table) is the source of truth and is never updated/deleted — every deduction, refund, or bonus is a new row, satisfying the NFR that no credit record is ever destroyed and giving a full audit trail for free. `credits_balance` on the wallet is a cached, trigger-maintained running total purely so "what's my balance" is an O(1) read instead of summing the whole ledger on every dashboard load. The two are reconciled by a nightly job that re-sums the ledger and alerts on any drift — drift would indicate an application bug, and this is the safety net that catches it.

**Why deduction policy is its own table instead of hardcoded:** the business rules explicitly call out optional partial-deduction behavior and per-course variation (a 1-on-1 premium course might have stricter rules than a group batch). A policy row resolved per-course (falling back to the `course_id IS NULL` global default) lets ops change this without a deploy.

---

## Next

Phase 2b — Assessment + Scheduling schema (placement tests, batches, recurring schedules, reschedules, teacher-change requests). Say "continue" when ready.
