-- Add audit fields to marks table
-- Safe additive migration: only adds columns/indexes if they don't exist.
-- Uses MariaDB "IF NOT EXISTS" guards so the statements are idempotent
-- and can be executed through PDO::exec() without dynamic SQL.

-- Who entered the mark, and who last changed it
ALTER TABLE marks
    ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL;

ALTER TABLE marks
    ADD COLUMN IF NOT EXISTS updated_by INT UNSIGNED NULL;

-- Keep the audit trail, but never fail when the user account is deleted
ALTER TABLE marks
    ADD FOREIGN KEY IF NOT EXISTS fk_marks_created_by (created_by)
        REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE marks
    ADD FOREIGN KEY IF NOT EXISTS fk_marks_updated_by (updated_by)
        REFERENCES users(id) ON DELETE SET NULL;
