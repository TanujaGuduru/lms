# Delivery Phase 2e — Database Schema: Projects, Gamification, Achievement Wall, Support, PTM, Calendar, Communication

Covers lifecycle phases 19–20 (Projects, Publishing), 30–31 (Support, Gamification), 38–39 (Achievement Wall, PTM), 37 (Calendar), and the cross-cutting Communication log + Admin↔Student event sync. **This closes out Delivery Phase 2 — Database Schema.**

---

## 1. Projects + Publishing

`assignments.type` already includes a `'project'` value — projects are assignments with richer submission types and a publishing layer, not a parallel system.

```sql
ALTER TABLE `assignment_submissions`
  ADD COLUMN `github_repo_url` VARCHAR(500) DEFAULT NULL AFTER `url`,
  ADD COLUMN `demo_video_url` VARCHAR(500) DEFAULT NULL AFTER `github_repo_url`,
  ADD COLUMN `screenshots` JSON DEFAULT NULL COMMENT 'array of S3 URLs' AFTER `demo_video_url`,
  ADD COLUMN `originality_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100 from plagiarism/originality check' AFTER `feedback`,
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
```

**Publishing requires explicit mentor/admin approval (`approved_by`/`approved_at`), not a self-service toggle** — a student can request publication, but nothing goes onto the public portfolio wall unfiltered. This matters more than usual here given the user base includes minors.

---

## 2. Gamification

```sql
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
  `streak_freezes_remaining` TINYINT UNSIGNED DEFAULT 1 COMMENT 'lets a student miss one day without breaking the streak',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`xp_transactions` is append-only** — same ledger principle as credits and audit logs throughout this design. `student_xp.total_xp` is the denormalized running total for fast reads, reconciled nightly against the ledger sum, exactly like `credit_wallets.credits_balance`.

**Leaderboard is a query, not a table:** `SELECT student_id, total_xp FROM student_xp ORDER BY total_xp DESC LIMIT 100`. Phase 1 originally assumed Redis sorted sets for this; per the GoDaddy addendum, Redis isn't guaranteed on shared hosting, so this falls back to an indexed MySQL query — add `KEY idx_xp_leaderboard (total_xp DESC)` if/when leaderboard read volume justifies it. Fine at the scale a single GoDaddy box serves; revisit if/when moving to infrastructure with Redis available.

---

## 3. Achievement Showcase Wall / Public Portfolio

```sql
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
```

The portfolio page itself aggregates from `published_projects`, `certificates` (existing), `student_badges`, `student_xp` at read time — no duplication. **`is_public` defaults to 0**: a minor's achievements aren't shareable by default; the student/parent must explicitly opt in, consistent with the consent-first stance from Phase 1.

---

## 4. Support

Existing `support_tickets`/`ticket_replies`/`support_categories` (already built, SLA via `support_categories.sla_hours`) are reused as-is for student-raised tickets — `support_tickets.user_id` already accepts any user including students. **No new tables.** Category seed data just needs student-relevant categories added (technical, academic, payment, scheduling) via the existing Admin UI — a data change, not a schema change.

---

## 5. Parent-Teacher Meeting (PTM) Booking

```sql
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
```

`uk_ptm_slot` (unique on `slot_id`) is what actually prevents double-booking — not application-layer checking alone, which would race under concurrent requests.

---

## 6. Calendar Integration

```sql
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
  `external_event_id` VARCHAR(255) DEFAULT NULL COMMENT 'the ID in Google/Outlook''s system, needed to update or delete it later',
  `synced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sync_event` (`connection_id`, `event_type`, `source_id`),
  FOREIGN KEY (`connection_id`) REFERENCES `calendar_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Tokens are encrypted at rest (Laravel's encrypted cast) — these are live credentials to a user's personal Google/Outlook account, treated with the same seriousness as payment data.

---

## 7. Cross-Cutting: Communication Log + Admin↔Student Event Sync

```sql
CREATE TABLE `communication_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'recipient',
  `channel` ENUM('whatsapp','email','sms','push','in_app','ivr','manual_call') NOT NULL,
  `trigger_event` VARCHAR(100) NOT NULL COMMENT 'e.g. low_credit_alert, assignment_reminder_24h',
  `template_used` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('queued','sent','delivered','failed','read') DEFAULT 'queued',
  `provider_message_id` VARCHAR(150) DEFAULT NULL COMMENT 'external ID from Twilio/WhatsApp Cloud API/SES, for delivery tracking',
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
```

`communication_logs` is what every trigger across all 39 lifecycle phases writes to — detailed in the Phase 6 Communication Engine document. `domain_events` is the outbox table referenced in Phase 1 §3: either side (Admin PHP or Student Laravel) can poll `WHERE processed_by_x = 0` and mark itself done, giving near-real-time cross-system propagation without a synchronous HTTP dependency between two different runtimes.

All tables in this document use plain MySQL 8 syntax — confirmed portable to GoDaddy.

---

## Delivery Phase 2 — Database Schema: complete

All five sub-parts (2a–2e) are done. Full table inventory lives across `02a`–`02e` in this folder, extending the existing `codegurukul` schema without forking it.

## Next

**Delivery Phase 3 — Phase-by-phase Workflows**, starting with **3a: Lead-to-Enrollment, Account Creation, Credit Engine**. Say "continue."
