# Delivery Phase 2d — Database Schema: Sandbox + Collaborative Coding + Code Replay + AI

Covers lifecycle phases 15–17 (Coding Sandbox, AI Doubt Solver, AI Coding Assistant), 22 (AI Risk Detection), 33 (Collaborative Coding), 35 (Code Replay).

**GoDaddy-specific pattern that shapes every table in this section:** the *expensive/high-volume* parts — code execution, real-time collaboration sync, keystroke-level replay events — never touch the GoDaddy-hosted database or server at all. MySQL only ever stores **metadata and results**, never the raw heavy data. This isn't just good practice generically — on a GoDaddy shared plan your MySQL database has a real, often-capped size quota, and your PHP process has no business running untrusted code or holding a WebSocket open. Every design choice below routes the heavy part to the separate sandbox VPS, Pusher/Ably, or S3 (per the `01b` hosting addendum) and keeps GoDaddy's MySQL holding only small, bounded rows.

---

## 1. Coding Sandbox / Cloud IDE

```sql
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
  `sandbox_node` VARCHAR(100) DEFAULT NULL COMMENT 'which sandbox worker handled it, once there is more than one',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exec_workspace` (`workspace_id`, `created_at`),
  FOREIGN KEY (`workspace_id`) REFERENCES `code_workspaces`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Flow on GoDaddy:** the Laravel app receives a "run" request, calls the sandbox VPS's HTTP API (`POST /execute {language, files, stdin}`), gets back stdout/stderr/exit_code/timing, and writes one `code_executions` row. The actual untrusted code never executes on the GoDaddy box — GoDaddy only ever sees the result.

**Versioning policy (same principle as notes in 2c):** `workspace_files.content` is the live autosaved state — no row explosion on every keystroke. `file_versions` snapshots only on manual save, right before a run (so a broken edit can always be rolled back to "last known working"), or on a periodic interval — never on every autosave tick.

---

## 2. Collaborative Coding (Pair Programming)

Real-time character-level sync is Yjs (CRDT) over Pusher/Ably — that traffic never touches GoDaddy or MySQL. MySQL stores only session membership and periodic durability snapshots:

```sql
CREATE TABLE `collab_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `workspace_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(150) DEFAULT NULL,
  `session_type` ENUM('live_class','hackathon','project','practice') DEFAULT 'practice',
  `linked_live_class_id` BIGINT UNSIGNED DEFAULT NULL,
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
```

**"Teacher can edit student code" is a permission, not a schema feature:** a teacher joins as a participant with `role='owner'`-equivalent write access rather than `teacher_observer` (read-only) — enforced in the Yjs awareness/permission layer, no extra table needed. **Stale-session handling:** a session with no active Pusher/Ably presence for >5 minutes auto-transitions `status='ended'` via a scheduled check, taking a final snapshot first — prevents zombie sessions accumulating.

---

## 3. Code Replay

Keystroke-level event volume is the textbook case for *not* using MySQL rows — even at modest usage this would be millions of rows per student per month. Compressed event stream in S3; MySQL holds a thin index:

```sql
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
```

The browser-side editor batches keystroke deltas client-side and flushes a compressed chunk to S3 every few seconds (direct-to-S3 presigned upload — never proxied through the GoDaddy PHP process, which would otherwise be holding open requests for no reason). `code_replay_markers` gives the timeline slider instant seek to "show me every error" or "show me where the teacher intervened" without ever downloading/parsing the full stream.

---

## 4. AI Domain (Doubt Solver, Coding Assistant, Risk Detection)

```sql
CREATE TABLE `ai_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` INT UNSIGNED NOT NULL,
  `conversation_type` ENUM('doubt_solver','coding_assistant') NOT NULL,
  `mode` ENUM('hint','explain','practice','debug','review') DEFAULT 'hint',
  `linked_course_id` INT UNSIGNED DEFAULT NULL,
  `linked_lesson_id` INT UNSIGNED DEFAULT NULL,
  `linked_workspace_id` BIGINT UNSIGNED DEFAULT NULL,
  `language` VARCHAR(10) DEFAULT 'en' COMMENT 'conversation language, for multilingual support',
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
  `rag_sources` JSON DEFAULT NULL COMMENT 'retrieved chunk references, for transparency/debugging',
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
```

**GoDaddy-specific operational note that doesn't apply on AWS:** `ai_messages.content` is `LONGTEXT` and this table will be the fastest-growing one in the entire schema — every doubt-solver exchange writes a row. On a GoDaddy shared MySQL plan with a database size cap, this is the table to watch. Recommended mitigation baked into the design from day one: a monthly archival job exports `ai_messages` older than N months to a compressed JSON file in S3 and deletes the MySQL rows (keeping `ai_conversations` as the summary record with a pointer to the archive) — rather than discovering the size cap the hard way after launch. This is purely a GoDaddy/shared-hosting concern; on AWS Aurora it would just be a cost-optimization nice-to-have, not a hard constraint.

`ai_usage_quotas` and the deduction logic it drives are what make Phase 16/17's "quota system, cost control" requirement concrete — checked before every AI call, not just logged after the fact.

All tables above are plain MySQL 8 syntax (no Aurora-specific features), confirmed portable to GoDaddy.

---

## Next

Phase 2e — Projects, Gamification, Achievement Wall, Support, PTM booking, Calendar sync, Communication logs. Say "continue."
