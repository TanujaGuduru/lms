-- ============================================================
-- STAGING FIXTURES — entirely synthetic data, no real student/parent PII.
--
-- docs/student-module/08-infrastructure-devops.md §2: "staging never holds
-- real student PII... staging runs against synthetic/seeded fixtures
-- instead, full stop, not a name-swapped prod export." Given the user base
-- is minors, QA running against a "sanitized" copy of production data is a
-- real, recurring source of accidental PII leaks in practice — this file
-- is the alternative: run this against a staging database that has NEVER
-- been seeded from a production export, on a database that is its own
-- separate schema/subdomain target, never a shared DB with prod (08 §2's
-- explicit "never a 'staging_' table-prefix convention" rule).
--
-- All passwords below are the literal string "Staging@1234" — fine for a
-- non-production environment with no real user data in it.
-- ============================================================

INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, language, date_of_birth) VALUES
  (4, 'Aria', 'Fixture', 'aria.fixture@staging.test', '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHQxMjM0NTY$RklYVFVSRWhhc2hwbGFjZWhvbGRlcg', 'active', 'en', '2012-03-15'),
  (4, 'Beni', 'Fixture', 'beni.fixture@staging.test', '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHQxMjM0NTY$RklYVFVSRWhhc2hwbGFjZWhvbGRlcg', 'active', 'en', '2010-07-22'),
  (4, 'Cleo', 'Fixture', 'cleo.fixture@staging.test', '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHQxMjM0NTY$RklYVFVSRWhhc2hwbGFjZWhvbGRlcg', 'active', 'en', '2008-11-02'),
  (5, 'Dax', 'Fixture', 'dax.fixture@staging.test', '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHQxMjM0NTY$RklYVFVSRWhhc2hwbGFjZWhvbGRlcg', 'active', 'en', NULL),
  (3, 'Eva', 'Fixture', 'eva.fixture@staging.test', '$argon2id$v=19$m=65536,t=4,p=2$ZmFrZXNhbHQxMjM0NTY$RklYVFVSRWhhc2hwbGFjZWhvbGRlcg', 'active', 'en', NULL);

-- NOTE: the password_hash placeholders above are NOT a real working hash —
-- this file documents the *shape* of staging fixtures (synthetic
-- identities, realistic relationships, no real PII). Generate real,
-- working Argon2id hashes for "Staging@1234" at fixture-load time instead,
-- e.g.: php -r "echo password_hash('Staging@1234', PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>4,'threads'=>2]);"
-- and substitute them before running this file, the same way every test
-- round this session generated a real hash rather than hardcoding one.

INSERT INTO parent_student_links (parent_id, student_id, relationship, consent_status, is_primary_guardian) VALUES
  (4, 1, 'parent', 'granted', 1),
  (4, 2, 'parent', 'granted', 1);

INSERT INTO departments (name, code, created_by) VALUES ('Computer Science (Staging)', 'CSF', 1);
INSERT INTO courses (department_id, created_by, title, slug) VALUES
  (1, 1, 'Python Beginner (Staging)', 'python-beginner-staging'),
  (1, 1, 'Python Intermediate (Staging)', 'python-intermediate-staging');
INSERT INTO batches (course_id, name, code, start_date, created_by) VALUES
  (1, 'Staging Batch A', 'STG-A', CURDATE(), 1);
INSERT INTO batch_students (batch_id, student_id) VALUES (1, 1), (1, 2), (1, 3);
INSERT INTO enrollments (user_id, course_id, batch_id, status, enrolled_at) VALUES
  (1, 1, 1, 'active', NOW()),
  (2, 1, 1, 'active', NOW()),
  (3, 1, 1, 'active', NOW());

-- Enough realistic relationship depth (parent-child links, an active
-- batch, enrollments) for QA to exercise dashboards, notifications, and
-- placement/assessment flows end to end without ever touching a real name.
