-- =====================================================================
-- 005_result_settings_notifications_alignment.sql
-- =====================================================================
--
-- Align a fresh installation (database/student_management.sql + migrations
-- 002-004) with the schema the application code actually expects:
--
--   1. result_settings          -> missing entirely (Result engine + admin
--                                  and teacher result configuration would
--                                  crash on a fresh install)
--   2. notifications            -> missing sender_id/sender_type/type/
--                                  priority/action_url columns
--   3. notifications.target_role-> ENUM must accept 'user' (the UI and
--                                  notification queries use it)
--   4. fee_payments.payment_method -> ENUM must accept 'card'
--   5. students.status          -> ENUM must accept 'pending'
--
-- Every statement is idempotent (MariaDB "IF NOT EXISTS" guards or
-- safe MODIFYs that are no-ops once applied). No data is deleted.
-- =====================================================================

-- 1) RESULT CONFIGURATION ------------------------------------------------
CREATE TABLE IF NOT EXISTS result_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NULL,
    subject_id INT UNSIGNED NOT NULL,
    assignment_total DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    attendance_total DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    midterm_total DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    final_total DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    passing_percentage DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY fk_result_settings_teacher (teacher_id),
    KEY fk_result_settings_section (section_id),
    KEY fk_result_settings_subject (subject_id),
    KEY idx_result_settings_lookup (class_id, section_id, subject_id),
    CONSTRAINT fk_result_settings_class
        FOREIGN KEY (class_id) REFERENCES classes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_result_settings_section
        FOREIGN KEY (section_id) REFERENCES sections(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_result_settings_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_result_settings_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- 2) NOTIFICATIONS COLUMNS ------------------------------------------------
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS sender_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS sender_type ENUM('admin','teacher','student','system')
        NOT NULL DEFAULT 'system',
    ADD COLUMN IF NOT EXISTS type ENUM('system','announcement','assignment',
        'submission','attendance','exam','marks','fee','general')
        NOT NULL DEFAULT 'general',
    ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','urgent')
        NOT NULL DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS action_url VARCHAR(500) NULL;

ALTER TABLE notifications
    ADD INDEX IF NOT EXISTS idx_notifications_type (type),
    ADD INDEX IF NOT EXISTS idx_notifications_priority (priority),
    ADD INDEX IF NOT EXISTS idx_notifications_created (created_at);

-- 3) NOTIFICATION TARGETING: 'user' = notification for one specific account
ALTER TABLE notifications
    MODIFY target_role ENUM('admin','teacher','student','user','all')
        NOT NULL DEFAULT 'all';

-- 4) FEE PAYMENT METHODS: the admin UI offers "card"
ALTER TABLE fee_payments
    MODIFY payment_method ENUM('cash','bank','online','card','other')
        NOT NULL DEFAULT 'cash';

-- 5) STUDENT LIFECYCLE: registration starts as 'pending'
ALTER TABLE students
    MODIFY status ENUM('pending','active','inactive','graduated','left')
        NOT NULL DEFAULT 'pending';

-- 6) WHOLE-CLASS ROWS: section_id must accept NULL
--    (assignment with section_id = NULL  -> whole class)
--    (attendance with section_id = NULL  -> whole class)
--    (teacher_classes with section_id = NULL -> whole class authorization)
ALTER TABLE assignments
    MODIFY section_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE attendance
    MODIFY section_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE teacher_classes
    MODIFY section_id INT UNSIGNED NULL DEFAULT NULL;
