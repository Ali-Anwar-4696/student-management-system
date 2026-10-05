-- =====================================================
-- 006: Parent portal support
-- =====================================================
-- Adds the 'parent' login role and links parents to their
-- children through students.parent_user_id.
--
-- Idempotent: every statement is either re-runnable
-- (MODIFY), tolerated on re-run by migrate.php (duplicate
-- column / duplicate key name), or guarded by an explicit
-- existence check (foreign key).

-- New login role.
ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('admin','teacher','student','parent') NOT NULL;

-- Parent login = a regular user row, so notification targeting must
-- accept the new role (idempotent MODIFY; 'all'/'user' kept as-is).
ALTER TABLE `notifications`
    MODIFY COLUMN `target_role` ENUM('admin','teacher','student','user','parent','all')
    NOT NULL DEFAULT 'all';

-- Parent <-> student linkage.
ALTER TABLE `students`
    ADD COLUMN `parent_user_id` INT(10) UNSIGNED DEFAULT NULL;

ALTER TABLE `students`
    ADD INDEX `idx_students_parent` (`parent_user_id`);

-- Foreign key, created only when it does not exist yet.
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND CONSTRAINT_NAME = 'fk_students_parent_user'
);

-- The no-op branch must NOT return a result set: migrate.php runs
-- these through PDO::exec(), and a row-returning statement would
-- leave the connection with an unbuffered result, making the
-- following DEALLOCATE PREPARE fail with SQLSTATE 2014.
SET @fk_ddl := IF(
    @fk_exists = 0,
    'ALTER TABLE `students` ADD CONSTRAINT `fk_students_parent_user` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);

PREPARE fk_stmt FROM @fk_ddl;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;
