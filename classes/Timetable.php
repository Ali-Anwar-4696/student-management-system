<?php

declare(strict_types=1);

/**
 * Weekly timetable slots.
 *
 * One row = one recurring weekly slot (class + section + day + period).
 * section_id IS NULL means a whole-class slot — the same convention
 * used by assignments, attendance and teacher_classes.
 *
 * All write methods validate first and throw RuntimeException with a
 * human-readable message on conflict/validation failure; controllers
 * catch it and show a flash error. The DB unique key
 * (class, COALESCE(section,0), day, period) is the last line of defence.
 */
class Timetable
{
    private const DAY_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function dayNames(): array
    {
        return self::DAY_NAMES;
    }

    // =====================================================
    // READ
    // =====================================================

    /**
     * Rows for one class (optionally narrowed to one section or to
     * whole-class slots). Used by the admin grid.
     *
     * @param int      $classId
     * @param int|null $sectionId null = no section filter
     * @param bool     $wholeClassOnly
     */
    public function listForClass(
        int $classId,
        ?int $sectionId = null,
        bool $wholeClassOnly = false
    ): array {
        if ($classId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                t.id, t.class_id, t.section_id, t.day_of_week, t.period,
                t.subject_id, t.teacher_id, t.start_time, t.end_time, t.room,
                c.name AS class_name,
                sec.name AS section_name,
                sub.name AS subject_name,
                sub.code AS subject_code,
                te.name AS teacher_name
            FROM timetables t
            INNER JOIN classes c ON c.id = t.class_id
            LEFT JOIN sections sec ON sec.id = t.section_id
            LEFT JOIN subjects sub ON sub.id = t.subject_id
            LEFT JOIN teachers te ON te.id = t.teacher_id
            WHERE t.class_id = :class_id
        ";

        $params = [':class_id' => $classId];

        if ($wholeClassOnly) {
            $sql .= " AND t.section_id IS NULL";
        } elseif ($sectionId !== null) {
            $sql .= "
                AND (
                    t.section_id IS NULL
                    OR t.section_id = :section_id
                )
            ";
            $params[':section_id'] = $sectionId;
        }

        $sql .= " ORDER BY t.day_of_week ASC, t.period ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A teacher's timetable: their own teaching slots plus the full
     * grid of every class they are assigned to (teacher_classes is the
     * single authorization source for that).
     */
    public function listForTeacher(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                t.id, t.class_id, t.section_id, t.day_of_week, t.period,
                t.subject_id, t.teacher_id, t.start_time, t.end_time, t.room,
                c.name AS class_name,
                sec.name AS section_name,
                sub.name AS subject_name,
                sub.code AS subject_code,
                te.name AS teacher_name
            FROM timetables t
            INNER JOIN classes c ON c.id = t.class_id
            LEFT JOIN sections sec ON sec.id = t.section_id
            LEFT JOIN subjects sub ON sub.id = t.subject_id
            LEFT JOIN teachers te ON te.id = t.teacher_id
            WHERE t.teacher_id = :teacher_id
               OR t.class_id IN (
                    SELECT DISTINCT tc.class_id
                    FROM teacher_classes tc
                    WHERE tc.teacher_id = :teacher_id2
               )
            ORDER BY t.day_of_week ASC, t.period ASC, c.name, sec.name
        ");

        $stmt->execute([
            ':teacher_id'  => $teacherId,
            ':teacher_id2' => $teacherId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A student's timetable: whole-class slots + slots for the
     * student's own section.
     */
    public function listForStudent(int $classId, ?int $sectionId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                t.id, t.class_id, t.section_id, t.day_of_week, t.period,
                t.subject_id, t.teacher_id, t.start_time, t.end_time, t.room,
                c.name AS class_name,
                sec.name AS section_name,
                sub.name AS subject_name,
                sub.code AS subject_code,
                te.name AS teacher_name
            FROM timetables t
            INNER JOIN classes c ON c.id = t.class_id
            LEFT JOIN sections sec ON sec.id = t.section_id
            LEFT JOIN subjects sub ON sub.id = t.subject_id
            LEFT JOIN teachers te ON te.id = t.teacher_id
            WHERE t.class_id = :class_id
              AND (
                    t.section_id IS NULL
                    OR t.section_id = :section_id2
              )
            ORDER BY t.day_of_week ASC, t.period ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':class_id'    => $classId,
            ':section_id2' => $sectionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                t.id, t.class_id, t.section_id, t.day_of_week, t.period,
                t.subject_id, t.teacher_id, t.start_time, t.end_time, t.room,
                c.name AS class_name,
                sec.name AS section_name,
                sub.name AS subject_name,
                te.name AS teacher_name
            FROM timetables t
            INNER JOIN classes c ON c.id = t.class_id
            LEFT JOIN sections sec ON sec.id = t.section_id
            LEFT JOIN subjects sub ON sub.id = t.subject_id
            LEFT JOIN teachers te ON te.id = t.teacher_id
            WHERE t.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // =====================================================
    // VALIDATION
    // =====================================================

    /**
     * Validates slot data and checks for conflicts.
     * Throws RuntimeException on the first problem found.
     *
     * @param array    $data      class_id, section_id, day_of_week,
     *                            period, subject_id, teacher_id,
     *                            start_time, end_time, room
     * @param int|null $excludeId slot being replaced (edit mode)
     */
    private function validate(array $data, ?int $excludeId = null): void
    {
        $classId = (int) ($data['class_id'] ?? 0);

        if ($classId <= 0) {
            throw new RuntimeException('Please select a class.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM classes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $classId]);

        if (!$stmt->fetch()) {
            throw new RuntimeException('The selected class does not exist.');
        }

        $sectionId = $data['section_id'] ?? null;
        $sectionId = $sectionId !== null && $sectionId !== ''
            ? (int) $sectionId
            : null;

        if ($sectionId !== null) {
            $secStmt = $this->pdo->prepare(
                'SELECT id FROM sections WHERE id = :id AND class_id = :class_id LIMIT 1'
            );
            $secStmt->execute([
                ':id'       => $sectionId,
                ':class_id' => $classId,
            ]);

            if (!$secStmt->fetch()) {
                throw new RuntimeException(
                    'The selected section does not belong to that class.'
                );
            }
        }

        $day = (int) ($data['day_of_week'] ?? 0);

        if ($day < 1 || $day > 7) {
            throw new RuntimeException('Please select a valid day of the week.');
        }

        $period = (int) ($data['period'] ?? 0);

        if ($period < 1 || $period > 50) {
            throw new RuntimeException(
                'Period must be a number between 1 and 50.'
            );
        }

        $start = $this->normalizeTime((string) ($data['start_time'] ?? ''));
        $end = $this->normalizeTime((string) ($data['end_time'] ?? ''));

        if ($start === null || $end === null) {
            throw new RuntimeException(
                'Please provide valid start and end times (HH:MM).'
            );
        }

        if ($start >= $end) {
            throw new RuntimeException('The end time must be after the start time.');
        }

        $subjectId = $data['subject_id'] ?? null;
        $subjectId = $subjectId !== null && $subjectId !== ''
            ? (int) $subjectId
            : null;

        if ($subjectId !== null) {
            $subStmt = $this->pdo->prepare(
                'SELECT id FROM subjects WHERE id = :id LIMIT 1'
            );
            $subStmt->execute([':id' => $subjectId]);

            if (!$subStmt->fetch()) {
                throw new RuntimeException('The selected subject does not exist.');
            }
        }

        $teacherId = $data['teacher_id'] ?? null;
        $teacherId = $teacherId !== null && $teacherId !== ''
            ? (int) $teacherId
            : null;

        if ($teacherId !== null) {
            $teStmt = $this->pdo->prepare(
                'SELECT id FROM teachers WHERE id = :id LIMIT 1'
            );
            $teStmt->execute([':id' => $teacherId]);

            if (!$teStmt->fetch()) {
                throw new RuntimeException('The selected teacher does not exist.');
            }
        }

        $room = trim((string) ($data['room'] ?? ''));

        if (mb_strlen($room) > 50) {
            throw new RuntimeException('Room must not exceed 50 characters.');
        }

        $excludeSql = $excludeId !== null ? ' AND id <> :exclude_id' : '';
        $excludeParams = $excludeId !== null
            ? [':exclude_id' => $excludeId]
            : [];

        // --- 1. Duplicate slot (class + section + day + period) ---
        $dupStmt = $this->pdo->prepare("
            SELECT id
            FROM timetables
            WHERE class_id = :class_id
              AND COALESCE(section_id, 0) = COALESCE(:section_id, 0)
              AND day_of_week = :day
              AND period = :period
              {$excludeSql}
            LIMIT 1
        ");

        $dupStmt->execute(array_merge([
            ':class_id'   => $classId,
            ':section_id' => $sectionId,
            ':day'        => $day,
            ':period'     => $period,
        ], $excludeParams));

        if ($dupStmt->fetch()) {
            $where = $sectionId !== null
                ? 'this section of the class'
                : 'the whole class';

            throw new RuntimeException(
                'Period ' . $period . ' on '
                . (self::DAY_NAMES[$day] ?? 'that day')
                . ' is already taken for ' . $where . '.'
            );
        }

        // --- 2. Overlapping time for the same class/section ---
        // A whole-class slot conflicts with every slot of that class
        // at the same time; a section slot conflicts with whole-class
        // slots and its own section only.
        $overlapStmt = $this->pdo->prepare("
            SELECT id
            FROM timetables
            WHERE class_id = :class_id
              AND day_of_week = :day
              AND start_time < :end_time
              AND end_time > :start_time
              AND (
                    :section_id2 IS NULL
                    OR section_id IS NULL
                    OR section_id = :section_id3
              )
              {$excludeSql}
            LIMIT 1
        ");

        $overlapStmt->execute(array_merge([
            ':class_id'    => $classId,
            ':day'         => $day,
            ':end_time'    => $end,
            ':start_time'  => $start,
            ':section_id2' => $sectionId,
            ':section_id3' => $sectionId,
        ], $excludeParams));

        if ($overlapStmt->fetch()) {
            throw new RuntimeException(
                'This slot overlaps another lesson for that class on '
                . (self::DAY_NAMES[$day] ?? 'the selected day') . '.'
            );
        }

        // --- 3. Teacher double-booked ---
        if ($teacherId !== null) {
            $teConflict = $this->pdo->prepare("
                SELECT id
                FROM timetables
                WHERE teacher_id = :teacher_id
                  AND day_of_week = :day
                  AND start_time < :end_time
                  AND end_time > :start_time
                  {$excludeSql}
                LIMIT 1
            ");

            $teConflict->execute(array_merge([
                ':teacher_id' => $teacherId,
                ':day'        => $day,
                ':end_time'   => $end,
                ':start_time' => $start,
            ], $excludeParams));

            if ($teConflict->fetch()) {
                throw new RuntimeException(
                    'That teacher is already teaching at this time on '
                    . (self::DAY_NAMES[$day] ?? 'the selected day') . '.'
                );
            }
        }
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $value, $m)) {
            return null;
        }

        return $m[1] . ':' . $m[2];
    }

    // =====================================================
    // WRITE
    // =====================================================

    /**
     * Create a slot. Returns the new slot id.
     * Throws RuntimeException on validation/conflict failure.
     */
    public function create(array $data): int
    {
        $this->validate($data);

        $stmt = $this->pdo->prepare("
            INSERT INTO timetables
                (class_id, section_id, day_of_week, period,
                 subject_id, teacher_id, start_time, end_time, room)
            VALUES
                (:class_id, :section_id, :day_of_week, :period,
                 :subject_id, :teacher_id, :start_time, :end_time, :room)
        ");

        $sectionId = $data['section_id'] ?? null;
        $sectionId = $sectionId !== null && $sectionId !== ''
            ? (int) $sectionId
            : null;

        $subjectId = $data['subject_id'] ?? null;
        $subjectId = $subjectId !== null && $subjectId !== ''
            ? (int) $subjectId
            : null;

        $teacherId = $data['teacher_id'] ?? null;
        $teacherId = $teacherId !== null && $teacherId !== ''
            ? (int) $teacherId
            : null;

        $stmt->execute([
            ':class_id'    => (int) $data['class_id'],
            ':section_id'  => $sectionId,
            ':day_of_week' => (int) $data['day_of_week'],
            ':period'      => (int) $data['period'],
            ':subject_id'  => $subjectId,
            ':teacher_id'  => $teacherId,
            ':start_time'  => $this->normalizeTime((string) $data['start_time']),
            ':end_time'    => $this->normalizeTime((string) $data['end_time']),
            ':room'        => trim((string) ($data['room'] ?? '')) ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a slot. Throws RuntimeException on validation/conflict failure.
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new RuntimeException('Invalid timetable slot.');
        }

        $existing = $this->getById($id);

        if ($existing === null) {
            throw new RuntimeException('Timetable slot not found.');
        }

        $this->validate($data, $id);

        $sectionId = $data['section_id'] ?? null;
        $sectionId = $sectionId !== null && $sectionId !== ''
            ? (int) $sectionId
            : null;

        $subjectId = $data['subject_id'] ?? null;
        $subjectId = $subjectId !== null && $subjectId !== ''
            ? (int) $subjectId
            : null;

        $teacherId = $data['teacher_id'] ?? null;
        $teacherId = $teacherId !== null && $teacherId !== ''
            ? (int) $teacherId
            : null;

        $stmt = $this->pdo->prepare("
            UPDATE timetables SET
                class_id = :class_id,
                section_id = :section_id,
                day_of_week = :day_of_week,
                period = :period,
                subject_id = :subject_id,
                teacher_id = :teacher_id,
                start_time = :start_time,
                end_time = :end_time,
                room = :room
            WHERE id = :id
        ");

        $stmt->execute([
            ':class_id'    => (int) $data['class_id'],
            ':section_id'  => $sectionId,
            ':day_of_week' => (int) $data['day_of_week'],
            ':period'      => (int) $data['period'],
            ':subject_id'  => $subjectId,
            ':teacher_id'  => $teacherId,
            ':start_time'  => $this->normalizeTime((string) $data['start_time']),
            ':end_time'    => $this->normalizeTime((string) $data['end_time']),
            ':room'        => trim((string) ($data['room'] ?? '')) ?: null,
            ':id'          => $id,
        ]);

        return true;
    }

    /**
     * Delete a slot (leaf row — no academic history attached).
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM timetables WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
