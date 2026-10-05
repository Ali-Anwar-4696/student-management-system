-- =====================================================
-- 007: Weekly class timetable
-- =====================================================
-- One row = one weekly slot (class + section + day + period).
-- section_id IS NULL = whole-class slot (same convention as
-- assignments / attendance / teacher_classes).
--
-- Idempotent: CREATE TABLE IF NOT EXISTS, so re-runs are safe.
--
-- The unique key uses COALESCE(section_id, 0) because plain
-- unique indexes treat NULLs as distinct in MariaDB, which
-- would allow duplicate whole-class slots.
--
-- MariaDB (unlike MySQL 8) has no functional key parts, so the
-- COALESCE lives in a STORED generated column and the unique key
-- indexes that column. Generated columns cannot be inserted into
-- explicitly, so INSERTs and SELECTs keep using the real columns.

CREATE TABLE IF NOT EXISTS `timetables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int(10) unsigned NOT NULL,
  `section_id` int(10) unsigned DEFAULT NULL,
  `section_key` int(10) unsigned GENERATED ALWAYS AS (COALESCE(`section_id`, 0)) STORED,
  `day_of_week` tinyint(3) unsigned NOT NULL COMMENT '1 = Monday .. 7 = Sunday',
  `period` tinyint(3) unsigned NOT NULL COMMENT 'Period number, 1-based',
  `subject_id` int(10) unsigned DEFAULT NULL,
  `teacher_id` int(10) unsigned DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_timetable_slot` (`class_id`, `section_key`, `day_of_week`, `period`),
  KEY `idx_timetable_class` (`class_id`),
  KEY `idx_timetable_section` (`section_id`),
  KEY `idx_timetable_subject` (`subject_id`),
  KEY `idx_timetable_teacher` (`teacher_id`),
  CONSTRAINT `fk_timetable_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_timetable_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_timetable_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
