-- =====================================================
-- 008: Schema drift alignment
-- =====================================================
-- Aligns the four columns whose definitions the application
-- depends on but which could differ on a database restored
-- from an older dump.
--
-- These statements were previously executed inline from
-- ensure_optional_schema() on the normal web request path.
-- They now live here so DDL only ever runs from the CLI
-- migration runner:
--
--     php includes/migrate.php
--
-- ensure_optional_schema() still DETECTS drift and writes a
-- clear message to the error log; it no longer alters tables.
--
-- Idempotent: every statement is a re-runnable MODIFY.
--
-- NOTE: notifications.target_role MUST include 'parent'
-- (added by 006_parent_role.sql). The previous inline
-- statement omitted it, so had it ever fired it would have
-- silently rewritten every 'parent' target to the empty
-- value.

-- Fee payment methods the UI offers.
ALTER TABLE `fee_payments`
    MODIFY COLUMN `payment_method` ENUM('cash','bank','online','card','other')
    NOT NULL DEFAULT 'cash';

-- Student lifecycle, including the 'pending' state that gates
-- public self-registration until an administrator approves it.
ALTER TABLE `students`
    MODIFY COLUMN `status` ENUM('pending','active','inactive','graduated','left')
    NOT NULL DEFAULT 'pending';

-- Notification audiences, per roles in 006_parent_role.sql.
ALTER TABLE `notifications`
    MODIFY COLUMN `target_role` ENUM('admin','teacher','student','user','parent','all')
    NOT NULL DEFAULT 'all';

-- Whole-class timetables, assignments, attendance and teacher
-- assignments are stored with section_id = NULL, so the column
-- must be nullable.
ALTER TABLE `assignments`
    MODIFY COLUMN `section_id` INT(10) UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `attendance`
    MODIFY COLUMN `section_id` INT(10) UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `teacher_classes`
    MODIFY COLUMN `section_id` INT(10) UNSIGNED NULL DEFAULT NULL;
