-- =====================================================================
-- 004_teacher_assignment_sync.sql
-- =====================================================================
--
-- Single authoritative teacher assignment model:
--
--   teacher_classes  = AUTHORITATIVE authorization record
--                      (teacher + class + section + subject)
--   teacher_subjects = DERIVED projection of the distinct subjects a
--                      teacher is assigned to, used only for display.
--
-- Every write now happens inside classes/TeacherClass.php so the two
-- tables can never disagree again. This migration synchronizes the
-- existing data once:
--
--   1. backfill missing derived rows from the authoritative rows
--   2. remove derived rows that have no authoritative assignment
--
-- The DELETE below only removes teacher_subjects rows that grant no
-- authorization anywhere (no matching teacher_classes row). It never
-- touches teacher_classes, marks, attendance or assignments.
--
-- The statements are idempotent (safe to run more than once).
-- =====================================================================

-- 1) Backfill: make sure every subject used in teacher_classes exists
--    in teacher_subjects.
INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id)
SELECT DISTINCT tc.teacher_id, tc.subject_id
FROM teacher_classes tc
LEFT JOIN subjects s
    ON s.id = tc.subject_id
WHERE s.id IS NOT NULL;

-- 2) Remove derived rows with no authoritative assignment.
DELETE ts
FROM teacher_subjects ts
LEFT JOIN teacher_classes tc
    ON tc.teacher_id = ts.teacher_id
   AND tc.subject_id = ts.subject_id
WHERE tc.id IS NULL;
