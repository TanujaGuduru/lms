-- ============================================================
-- CodeGurukul LMS — Complete Database Schema
-- Version: 1.0.0 | Engine: InnoDB | Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS `codegurukul` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `codegurukul`;

-- ============================================================
-- SECTION 1: RBAC — Roles & Permissions
-- ============================================================

CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `is_system` TINYINT(1) DEFAULT 0 COMMENT '1=cannot be deleted',
  `hierarchy_level` TINYINT UNSIGNED DEFAULT 5 COMMENT '1=SuperAdmin (highest)',
  `max_students` INT DEFAULT NULL COMMENT 'NULL=unlimited',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `module` VARCHAR(60) NOT NULL COMMENT 'e.g. users, courses, finance',
  `action` VARCHAR(40) NOT NULL COMMENT 'e.g. view, create, edit, delete',
  `description` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `granted_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 2: USERS
-- ============================================================

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `role_id` INT UNSIGNED NOT NULL DEFAULT 4 COMMENT '1=SuperAdmin,2=Admin,3=Teacher,4=Student,5=Parent',
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `gender` ENUM('male','female','other','prefer_not_to_say') DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `state` VARCHAR(80) DEFAULT NULL,
  `country` VARCHAR(80) DEFAULT 'India',
  `pincode` VARCHAR(10) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive','suspended','pending') DEFAULT 'pending',
  `email_verified_at` DATETIME DEFAULT NULL,
  `phone_verified_at` DATETIME DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) DEFAULT 0,
  `two_factor_secret` VARCHAR(100) DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `login_count` INT UNSIGNED DEFAULT 0,
  `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `password_changed_at` DATETIME DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `email_verification_token` VARCHAR(100) DEFAULT NULL,
  `password_reset_token` VARCHAR(100) DEFAULT NULL,
  `password_reset_expires` DATETIME DEFAULT NULL,
  `notification_preferences` JSON DEFAULT NULL,
  `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata',
  `language` VARCHAR(10) DEFAULT 'en',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_uuid` (`uuid`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_phone` (`phone`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_roles` (
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` INT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_permissions` (
  `user_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `type` ENUM('grant','deny') DEFAULT 'grant',
  `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `granted_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`user_id`,`permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `device_type` VARCHAR(30) DEFAULT NULL,
  `device_name` VARCHAR(120) DEFAULT NULL,
  `browser` VARCHAR(80) DEFAULT NULL,
  `os` VARCHAR(80) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `location` VARCHAR(120) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_activity` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_token` (`session_token`(64)),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tokens_token` (`token`),
  KEY `idx_tokens_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `password_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pwhistory_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 3: ACADEMIC STRUCTURE
-- ============================================================

CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `description` TEXT,
  `head_id` INT UNSIGNED DEFAULT NULL COMMENT 'Teacher who heads the dept',
  `icon` VARCHAR(60) DEFAULT 'fas fa-building',
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `courses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `department_id` INT UNSIGNED DEFAULT NULL,
  `instructor_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `description` TEXT,
  `short_description` VARCHAR(500),
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `preview_video` VARCHAR(255) DEFAULT NULL,
  `level` ENUM('beginner','intermediate','advanced','expert') DEFAULT 'beginner',
  `language` VARCHAR(40) DEFAULT 'English',
  `duration_hours` DECIMAL(6,1) DEFAULT 0,
  `total_lessons` SMALLINT UNSIGNED DEFAULT 0,
  `max_students` INT DEFAULT NULL,
  `is_free` TINYINT(1) DEFAULT 0,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `currency` VARCHAR(5) DEFAULT 'INR',
  `certificate_template_id` INT UNSIGNED DEFAULT NULL,
  `passing_percentage` TINYINT UNSIGNED DEFAULT 60,
  `certificate_enabled` TINYINT(1) DEFAULT 1,
  `discussion_enabled` TINYINT(1) DEFAULT 1,
  `drip_content` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft','review','published','archived') DEFAULT 'draft',
  `is_featured` TINYINT(1) DEFAULT 0,
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_description` TEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `requirements` JSON DEFAULT NULL,
  `outcomes` JSON DEFAULT NULL,
  `enrolled_count` INT UNSIGNED DEFAULT 0,
  `completed_count` INT UNSIGNED DEFAULT 0,
  `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
  `rating_count` INT UNSIGNED DEFAULT 0,
  `published_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_courses_uuid` (`uuid`),
  UNIQUE KEY `uk_courses_slug` (`slug`),
  KEY `idx_courses_dept` (`department_id`),
  KEY `idx_courses_status` (`status`),
  KEY `idx_courses_creator` (`created_by`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `course_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `sort_order` SMALLINT UNSIGNED DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cm_course` (`course_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `lessons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `type` ENUM('video','text','pdf','quiz','assignment','live','scorm') DEFAULT 'video',
  `video_url` VARCHAR(500) DEFAULT NULL,
  `video_provider` ENUM('youtube','vimeo','upload','zoom') DEFAULT NULL,
  `video_duration` INT UNSIGNED DEFAULT 0 COMMENT 'in seconds',
  `resource_url` VARCHAR(500) DEFAULT NULL,
  `sort_order` SMALLINT UNSIGNED DEFAULT 0,
  `is_free_preview` TINYINT(1) DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 1,
  `completion_required` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_module` (`module_id`),
  KEY `idx_lessons_course` (`course_id`),
  FOREIGN KEY (`module_id`) REFERENCES `course_modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `subjects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `description` TEXT,
  `credits` TINYINT UNSIGNED DEFAULT 3,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subjects_code` (`code`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `learning_paths` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `level` ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
  `estimated_hours` DECIMAL(6,1) DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `learning_path_courses` (
  `path_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `sort_order` SMALLINT UNSIGNED DEFAULT 0,
  `is_required` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`path_id`,`course_id`),
  FOREIGN KEY (`path_id`) REFERENCES `learning_paths`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 4: BATCHES
-- ============================================================

CREATE TABLE `batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `course_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `description` TEXT,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `max_students` SMALLINT UNSIGNED DEFAULT 50,
  `enrolled_count` SMALLINT UNSIGNED DEFAULT 0,
  `mode` ENUM('online','offline','hybrid') DEFAULT 'online',
  `time_slot` VARCHAR(80) DEFAULT NULL,
  `days_of_week` JSON DEFAULT NULL COMMENT '[\"Mon\",\"Wed\",\"Fri\"]',
  `status` ENUM('upcoming','active','completed','cancelled') DEFAULT 'upcoming',
  `fee` DECIMAL(10,2) DEFAULT 0.00,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_batches_uuid` (`uuid`),
  UNIQUE KEY `uk_batches_code` (`code`),
  KEY `idx_batches_course` (`course_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `batch_students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `enrolled_by` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('active','dropped','completed','suspended') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_batch_student` (`batch_id`,`student_id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `batch_teachers` (
  `batch_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(60) DEFAULT 'primary' COMMENT 'primary, assistant, guest',
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`,`teacher_id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `timetable` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED DEFAULT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room` VARCHAR(60) DEFAULT NULL,
  `meeting_link` VARCHAR(500) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tt_batch` (`batch_id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `timetable_id` INT UNSIGNED DEFAULT NULL,
  `session_date` DATE NOT NULL,
  `status` ENUM('present','absent','late','excused') DEFAULT 'absent',
  `check_in_time` TIME DEFAULT NULL,
  `check_out_time` TIME DEFAULT NULL,
  `remarks` VARCHAR(255) DEFAULT NULL,
  `marked_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance` (`batch_id`,`student_id`,`session_date`),
  KEY `idx_att_student` (`student_id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 5: ENROLLMENTS & PROGRESS
-- ============================================================

CREATE TABLE `enrollments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `enrolled_by` INT UNSIGNED DEFAULT NULL,
  `source` ENUM('self','admin','import','scholarship','free') DEFAULT 'admin',
  `status` ENUM('active','completed','dropped','expired') DEFAULT 'active',
  `progress_percentage` TINYINT UNSIGNED DEFAULT 0,
  `completed_at` DATETIME DEFAULT NULL,
  `certificate_issued_at` DATETIME DEFAULT NULL,
  `certificate_id` INT UNSIGNED DEFAULT NULL,
  `last_accessed_at` DATETIME DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `payment_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_enrollments` (`user_id`,`course_id`),
  KEY `idx_enr_course` (`course_id`),
  KEY `idx_enr_batch` (`batch_id`),
  KEY `idx_enr_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `lesson_progress` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `lesson_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
  `progress_seconds` INT UNSIGNED DEFAULT 0,
  `completed_at` DATETIME DEFAULT NULL,
  `last_accessed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lesson_progress` (`enrollment_id`,`lesson_id`),
  KEY `idx_lp_user` (`user_id`),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 6: ASSESSMENTS
-- ============================================================

CREATE TABLE `question_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `question_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `category_id` INT UNSIGNED DEFAULT NULL,
  `course_id` INT UNSIGNED DEFAULT NULL,
  `subject_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `type` ENUM('mcq','msq','true_false','fill_blank','short_answer','long_answer','coding','match') NOT NULL,
  `options` JSON DEFAULT NULL,
  `correct_answer` JSON DEFAULT NULL,
  `explanation` TEXT DEFAULT NULL,
  `difficulty` ENUM('easy','medium','hard','expert') DEFAULT 'medium',
  `marks` DECIMAL(5,2) DEFAULT 1.00,
  `negative_marks` DECIMAL(5,2) DEFAULT 0.00,
  `time_limit_seconds` SMALLINT UNSIGNED DEFAULT 0,
  `tags` JSON DEFAULT NULL,
  `language` VARCHAR(20) DEFAULT 'English',
  `status` ENUM('draft','pending_review','approved','rejected') DEFAULT 'draft',
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `times_used` INT UNSIGNED DEFAULT 0,
  `correct_rate` DECIMAL(5,2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_questions_uuid` (`uuid`),
  KEY `idx_q_category` (`category_id`),
  KEY `idx_q_course` (`course_id`),
  KEY `idx_q_difficulty` (`difficulty`),
  KEY `idx_q_type` (`type`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `course_id` INT UNSIGNED DEFAULT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `type` ENUM('quiz','midterm','final','mock','practice','coding') DEFAULT 'quiz',
  `duration_minutes` SMALLINT UNSIGNED DEFAULT 60,
  `total_marks` DECIMAL(7,2) DEFAULT 100.00,
  `passing_marks` DECIMAL(7,2) DEFAULT 40.00,
  `max_attempts` TINYINT UNSIGNED DEFAULT 1,
  `shuffle_questions` TINYINT(1) DEFAULT 0,
  `shuffle_options` TINYINT(1) DEFAULT 0,
  `show_result_immediately` TINYINT(1) DEFAULT 1,
  `show_correct_answers` TINYINT(1) DEFAULT 0,
  `negative_marking` TINYINT(1) DEFAULT 0,
  `is_proctored` TINYINT(1) DEFAULT 0,
  `instructions` TEXT,
  `start_datetime` DATETIME DEFAULT NULL,
  `end_datetime` DATETIME DEFAULT NULL,
  `status` ENUM('draft','published','active','completed','archived') DEFAULT 'draft',
  `published_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_exams_uuid` (`uuid`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exam_questions` (
  `exam_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `sort_order` SMALLINT UNSIGNED DEFAULT 0,
  `marks_override` DECIMAL(5,2) DEFAULT NULL,
  PRIMARY KEY (`exam_id`,`question_id`),
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exam_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `attempt_number` TINYINT UNSIGNED DEFAULT 1,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` DATETIME DEFAULT NULL,
  `time_taken_seconds` INT UNSIGNED DEFAULT 0,
  `total_marks` DECIMAL(7,2) DEFAULT 0,
  `obtained_marks` DECIMAL(7,2) DEFAULT 0,
  `percentage` DECIMAL(5,2) DEFAULT 0.00,
  `is_passed` TINYINT(1) DEFAULT 0,
  `status` ENUM('in_progress','completed','abandoned','invalidated') DEFAULT 'in_progress',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `browser_info` VARCHAR(255) DEFAULT NULL,
  `cheating_flags` JSON DEFAULT NULL,
  `graded_by` INT UNSIGNED DEFAULT NULL,
  `graded_at` DATETIME DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ea_exam` (`exam_id`),
  KEY `idx_ea_user` (`user_id`),
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exam_responses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `response` JSON DEFAULT NULL,
  `is_correct` TINYINT(1) DEFAULT NULL,
  `marks_awarded` DECIMAL(5,2) DEFAULT 0,
  `time_spent_seconds` INT UNSIGNED DEFAULT 0,
  `is_flagged` TINYINT(1) DEFAULT 0,
  `grader_feedback` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_er_attempt` (`attempt_id`),
  FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `course_id` INT UNSIGNED DEFAULT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `lesson_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `type` ENUM('text','file','url','code','project') DEFAULT 'text',
  `allowed_file_types` VARCHAR(255) DEFAULT NULL COMMENT '.pdf,.docx,.zip',
  `max_file_size_mb` TINYINT UNSIGNED DEFAULT 10,
  `total_marks` DECIMAL(6,2) DEFAULT 100,
  `passing_marks` DECIMAL(6,2) DEFAULT 40,
  `due_date` DATETIME DEFAULT NULL,
  `late_submission_allowed` TINYINT(1) DEFAULT 0,
  `late_penalty_percent` TINYINT UNSIGNED DEFAULT 0,
  `status` ENUM('draft','published','closed') DEFAULT 'draft',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_assignments_uuid` (`uuid`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignment_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `submission_text` LONGTEXT DEFAULT NULL,
  `file_path` VARCHAR(500) DEFAULT NULL,
  `file_name` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_late` TINYINT(1) DEFAULT 0,
  `status` ENUM('submitted','graded','returned','resubmitted') DEFAULT 'submitted',
  `marks_awarded` DECIMAL(6,2) DEFAULT NULL,
  `grade` VARCHAR(5) DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  `graded_by` INT UNSIGNED DEFAULT NULL,
  `graded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_submission` (`assignment_id`,`student_id`),
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 7: CERTIFICATES
-- ============================================================

CREATE TABLE `certificate_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT,
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `html_template` LONGTEXT NOT NULL,
  `css_styles` TEXT,
  `variables` JSON DEFAULT NULL COMMENT '["student_name","course_name","date",...]',
  `orientation` ENUM('landscape','portrait') DEFAULT 'landscape',
  `paper_size` ENUM('A4','A3','letter') DEFAULT 'A4',
  `digital_signature_enabled` TINYINT(1) DEFAULT 0,
  `qr_enabled` TINYINT(1) DEFAULT 1,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `certificates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `template_id` INT UNSIGNED DEFAULT NULL,
  `enrollment_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED DEFAULT NULL,
  `certificate_number` VARCHAR(50) NOT NULL,
  `verification_code` VARCHAR(60) NOT NULL,
  `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `issued_by` INT UNSIGNED DEFAULT NULL,
  `pdf_path` VARCHAR(500) DEFAULT NULL,
  `is_revoked` TINYINT(1) DEFAULT 0,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoke_reason` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_certificates_uuid` (`uuid`),
  UNIQUE KEY `uk_cert_number` (`certificate_number`),
  UNIQUE KEY `uk_cert_verification` (`verification_code`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `certificate_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 8: COMMUNICATION
-- ============================================================

CREATE TABLE `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `type` ENUM('general','urgent','event','maintenance','feature') DEFAULT 'general',
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `audience` JSON DEFAULT NULL COMMENT '{"roles":["student"],"batch_ids":[1,2]}',
  `channels` JSON DEFAULT NULL COMMENT '["email","sms","whatsapp","push","inapp"]',
  `scheduled_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_pinned` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft','scheduled','sent','cancelled') DEFAULT 'draft',
  `sent_count` INT UNSIGNED DEFAULT 0,
  `read_count` INT UNSIGNED DEFAULT 0,
  `failed_count` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ann_uuid` (`uuid`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(80) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON DEFAULT NULL,
  `icon` VARCHAR(60) DEFAULT 'fas fa-bell',
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `action_url` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `channel` ENUM('inapp','email','sms','whatsapp','push') DEFAULT 'inapp',
  `status` ENUM('pending','sent','delivered','failed') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `type` ENUM('workshop','hackathon','webinar','competition','bootcamp','seminar','other') DEFAULT 'webinar',
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `venue` VARCHAR(255) DEFAULT NULL,
  `meeting_link` VARCHAR(500) DEFAULT NULL,
  `meeting_password` VARCHAR(60) DEFAULT NULL,
  `speakers` JSON DEFAULT NULL,
  `max_participants` INT DEFAULT NULL,
  `registration_deadline` DATETIME DEFAULT NULL,
  `is_paid` TINYINT(1) DEFAULT 0,
  `fee` DECIMAL(8,2) DEFAULT 0.00,
  `certificate_on_completion` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `event_registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `registered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `attended` TINYINT(1) DEFAULT 0,
  `certificate_issued` TINYINT(1) DEFAULT 0,
  `payment_status` ENUM('pending','paid','waived') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_reg` (`event_id`,`user_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 9: SUPPORT
-- ============================================================

CREATE TABLE `support_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(60) DEFAULT 'fas fa-question-circle',
  `sla_hours` TINYINT UNSIGNED DEFAULT 24,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `support_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `status` ENUM('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
  `channel` ENUM('web','email','phone','chat') DEFAULT 'web',
  `first_response_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `resolved_by` INT UNSIGNED DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `satisfaction_rating` TINYINT UNSIGNED DEFAULT NULL,
  `satisfaction_feedback` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_number` (`ticket_number`),
  KEY `idx_ticket_user` (`user_id`),
  KEY `idx_ticket_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `attachments` JSON DEFAULT NULL,
  `is_internal_note` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reply_ticket` (`ticket_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 10: FINANCE
-- ============================================================

CREATE TABLE `fee_structures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `course_id` INT UNSIGNED DEFAULT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(5) DEFAULT 'INR',
  `billing_cycle` ENUM('one_time','monthly','quarterly','semi_annual','annual') DEFAULT 'one_time',
  `installments_allowed` TINYINT(1) DEFAULT 0,
  `max_installments` TINYINT UNSIGNED DEFAULT 1,
  `gst_applicable` TINYINT(1) DEFAULT 1,
  `gst_percentage` DECIMAL(5,2) DEFAULT 18.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED DEFAULT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `fee_structure_id` INT UNSIGNED DEFAULT NULL,
  `invoice_number` VARCHAR(40) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `gst_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(5) DEFAULT 'INR',
  `gateway` ENUM('razorpay','stripe','payu','ccavenue','manual','scholarship') DEFAULT 'manual',
  `gateway_order_id` VARCHAR(120) DEFAULT NULL,
  `gateway_payment_id` VARCHAR(120) DEFAULT NULL,
  `gateway_signature` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','processing','success','failed','refunded','partially_refunded') DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'UPI, card, netbanking, cash',
  `paid_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `receipt_sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payments_uuid` (`uuid`),
  UNIQUE KEY `uk_invoice_number` (`invoice_number`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `discounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `description` TEXT,
  `type` ENUM('percentage','fixed','full_waiver') DEFAULT 'percentage',
  `value` DECIMAL(8,2) NOT NULL,
  `min_amount` DECIMAL(10,2) DEFAULT 0.00,
  `max_uses` INT DEFAULT NULL,
  `used_count` INT UNSIGNED DEFAULT 0,
  `per_user_limit` TINYINT UNSIGNED DEFAULT 1,
  `valid_from` DATE DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL,
  `applicable_to` JSON DEFAULT NULL COMMENT '{"course_ids":[1,2],"batch_ids":[]}',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_discount_code` (`code`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scholarships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT,
  `discount_id` INT UNSIGNED DEFAULT NULL,
  `eligibility_criteria` JSON DEFAULT NULL,
  `application_deadline` DATE DEFAULT NULL,
  `slots_available` SMALLINT UNSIGNED DEFAULT NULL,
  `slots_used` SMALLINT UNSIGNED DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 11: PLACEMENT
-- ============================================================

CREATE TABLE `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `industry` VARCHAR(80) DEFAULT NULL,
  `size` ENUM('startup','small','medium','large','enterprise') DEFAULT 'medium',
  `description` TEXT,
  `contact_name` VARCHAR(120) DEFAULT NULL,
  `contact_email` VARCHAR(180) DEFAULT NULL,
  `contact_phone` VARCHAR(20) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `job_openings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `requirements` TEXT,
  `type` ENUM('full_time','part_time','internship','contract','freelance') DEFAULT 'full_time',
  `mode` ENUM('remote','onsite','hybrid') DEFAULT 'onsite',
  `location` VARCHAR(120) DEFAULT NULL,
  `salary_min` DECIMAL(10,2) DEFAULT NULL,
  `salary_max` DECIMAL(10,2) DEFAULT NULL,
  `salary_currency` VARCHAR(5) DEFAULT 'INR',
  `experience_min` TINYINT UNSIGNED DEFAULT 0,
  `experience_max` TINYINT UNSIGNED DEFAULT NULL,
  `skills_required` JSON DEFAULT NULL,
  `vacancies` SMALLINT UNSIGNED DEFAULT 1,
  `application_deadline` DATE DEFAULT NULL,
  `status` ENUM('draft','active','closed','on_hold') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `placement_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `resume_path` VARCHAR(500) DEFAULT NULL,
  `cover_letter` TEXT DEFAULT NULL,
  `status` ENUM('applied','shortlisted','interview_scheduled','offer_made','accepted','rejected','withdrawn') DEFAULT 'applied',
  `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ctc_offered` DECIMAL(10,2) DEFAULT NULL,
  `joining_date` DATE DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application` (`job_id`,`student_id`),
  FOREIGN KEY (`job_id`) REFERENCES `job_openings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 12: SYSTEM — AUDIT, SETTINGS, BACKUP
-- ============================================================

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `record_id` INT UNSIGNED DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `route` VARCHAR(255) DEFAULT NULL,
  `method` VARCHAR(10) DEFAULT NULL,
  `status_code` SMALLINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group` VARCHAR(60) NOT NULL COMMENT 'general, email, sms, payment, security...',
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT,
  `type` ENUM('text','textarea','boolean','integer','json','color','file','select') DEFAULT 'text',
  `label` VARCHAR(120) NOT NULL,
  `description` TEXT,
  `is_public` TINYINT(1) DEFAULT 0 COMMENT '1=accessible without auth',
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `integrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `provider` VARCHAR(80) NOT NULL,
  `type` ENUM('email','sms','whatsapp','payment','storage','video','ai','analytics','crm') NOT NULL,
  `credentials` TEXT DEFAULT NULL COMMENT 'AES-encrypted JSON',
  `config` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `last_tested_at` DATETIME DEFAULT NULL,
  `last_test_status` ENUM('success','failed','untested') DEFAULT 'untested',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `backups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('database','media','full') DEFAULT 'database',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size_bytes` BIGINT UNSIGNED DEFAULT 0,
  `storage` ENUM('local','s3','gcs','dropbox') DEFAULT 'local',
  `status` ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
  `triggered_by` ENUM('manual','scheduled','auto') DEFAULT 'manual',
  `user_id` INT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ip_restrictions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('whitelist','blacklist') DEFAULT 'blacklist',
  `ip_address` VARCHAR(45) NOT NULL,
  `cidr_notation` VARCHAR(50) DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 13: RATINGS & COURSE REVIEWS
-- ============================================================

CREATE TABLE `course_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5',
  `review` TEXT DEFAULT NULL,
  `is_verified_purchase` TINYINT(1) DEFAULT 0,
  `is_approved` TINYINT(1) DEFAULT 0,
  `helpful_count` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_review` (`course_id`,`user_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 14: LIVE CLASSES
-- ============================================================

CREATE TABLE `live_classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `platform` ENUM('zoom','google_meet','jitsi','youtube_live','custom') DEFAULT 'zoom',
  `meeting_id` VARCHAR(120) DEFAULT NULL,
  `meeting_password` VARCHAR(60) DEFAULT NULL,
  `join_url` VARCHAR(500) NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED DEFAULT 60,
  `status` ENUM('scheduled','live','completed','cancelled') DEFAULT 'scheduled',
  `recording_url` VARCHAR(500) DEFAULT NULL,
  `attendee_count` SMALLINT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SECTION 15: STUDENT PROFILES & PARENT LINKS
-- ============================================================

CREATE TABLE `student_profiles` (
  `user_id` INT UNSIGNED NOT NULL,
  `enrollment_number` VARCHAR(30) NOT NULL,
  `admission_date` DATE DEFAULT NULL,
  `graduation_expected` DATE DEFAULT NULL,
  `blood_group` VARCHAR(5) DEFAULT NULL,
  `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
  `github_url` VARCHAR(255) DEFAULT NULL,
  `linkedin_url` VARCHAR(255) DEFAULT NULL,
  `portfolio_url` VARCHAR(255) DEFAULT NULL,
  `skills` JSON DEFAULT NULL,
  `achievements` JSON DEFAULT NULL,
  `placement_status` ENUM('not_placed','placed','opted_out') DEFAULT 'not_placed',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_enrollment_number` (`enrollment_number`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `parent_student_links` (
  `parent_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `relationship` VARCHAR(30) DEFAULT 'parent',
  `linked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`parent_id`,`student_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA: Roles & Permissions
-- ============================================================

INSERT INTO `roles` (`id`,`name`,`slug`,`description`,`color`,`is_system`,`hierarchy_level`) VALUES
(1,'Super Admin','super_admin','Full system access','#dc2626',1,1),
(2,'Admin','admin','Platform administration','#7c3aed',1,2),
(3,'Teacher','teacher','Create and manage courses','#0891b2',1,3),
(4,'Student','student','Access learning content','#059669',1,4),
(5,'Parent','parent','Monitor student progress','#d97706',1,5);

INSERT INTO `permissions` (`name`,`slug`,`module`,`action`) VALUES
('View Dashboard','dashboard.view','dashboard','view'),
('Manage Users','users.manage','users','manage'),
('View Users','users.view','users','view'),
('Create User','users.create','users','create'),
('Edit User','users.edit','users','edit'),
('Delete User','users.delete','users','delete'),
('Manage Roles','roles.manage','roles','manage'),
('Manage Courses','courses.manage','courses','manage'),
('Create Course','courses.create','courses','create'),
('Edit Course','courses.edit','courses','edit'),
('Delete Course','courses.delete','courses','delete'),
('View Course','courses.view','courses','view'),
('Manage Batches','batches.manage','batches','manage'),
('Manage Assessments','assessments.manage','assessments','manage'),
('Manage Questions','questions.manage','questions','manage'),
('Manage Certificates','certificates.manage','certificates','manage'),
('Manage Announcements','announcements.manage','announcements','manage'),
('Manage Finance','finance.manage','finance','manage'),
('View Finance Reports','finance.view_reports','finance','view'),
('Manage Placements','placements.manage','placements','manage'),
('Manage Settings','settings.manage','settings','manage'),
('Manage Security','security.manage','security','manage'),
('View Audit Logs','audit.view','audit','view'),
('Manage Backups','backup.manage','backup','manage'),
('Manage Support','support.manage','support','manage'),
('Manage Integrations','integrations.manage','integrations','manage');

INSERT INTO `settings` (`group`,`key`,`value`,`type`,`label`,`description`,`is_public`) VALUES
('general','site_name','CodeGurukul LMS','text','Site Name','Name of the LMS platform',1),
('general','site_url','https://lms.codegurukul.com','text','Site URL','Primary URL of the platform',1),
('general','site_logo','','file','Site Logo','Logo shown in header',1),
('general','site_favicon','','file','Favicon','Browser tab icon',1),
('general','timezone','Asia/Kolkata','select','Timezone','Default timezone',0),
('general','currency','INR','select','Currency','Default currency',1),
('general','date_format','d/m/Y','select','Date Format','Date display format',0),
('general','maintenance_mode','0','boolean','Maintenance Mode','Put site in maintenance mode',0),
('email','smtp_host','','text','SMTP Host','Email SMTP server',0),
('email','smtp_port','587','integer','SMTP Port','Email SMTP port',0),
('email','smtp_user','','text','SMTP Username','Email SMTP username',0),
('email','smtp_pass','','text','SMTP Password','Email SMTP password',0),
('email','from_email','noreply@codegurukul.com','text','From Email','Default sender email',0),
('email','from_name','CodeGurukul LMS','text','From Name','Default sender name',0),
('security','session_timeout','120','integer','Session Timeout (min)','Auto-logout after inactivity',0),
('security','max_login_attempts','5','integer','Max Login Attempts','Lock account after failed attempts',0),
('security','password_min_length','8','integer','Min Password Length','Minimum password characters',0),
('security','two_factor_required','0','boolean','Require 2FA','Force 2FA for all users',0),
('payment','razorpay_key_id','','text','Razorpay Key ID','Razorpay public key',0),
('payment','razorpay_key_secret','','text','Razorpay Key Secret','Razorpay secret key',0),
('payment','gst_number','','text','GST Number','Institution GST number',0);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- GAP-FILL: Live Class scheduling — recurrence support
-- live_classes already gained timetable_id/class_type/cancelled_by/etc.
-- from student-portal/api/database/schema_student_portal.sql §3; these
-- three columns are the Admin panel's "Schedule Live Class" feature's own
-- addition, layered the same additive way rather than forking the table.
-- ============================================================
ALTER TABLE `live_classes`
  ADD COLUMN `recurrence_rule` ENUM('none','daily','weekly') DEFAULT 'none' AFTER `duration_minutes`,
  ADD COLUMN `recurrence_end_date` DATE DEFAULT NULL AFTER `recurrence_rule`,
  ADD COLUMN `parent_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'links a generated occurrence back to the class that defined the recurring series' AFTER `recurrence_end_date`,
  ADD CONSTRAINT `fk_liveclass_parent` FOREIGN KEY (`parent_class_id`) REFERENCES `live_classes`(`id`) ON DELETE SET NULL;
