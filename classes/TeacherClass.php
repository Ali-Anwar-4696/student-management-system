<?php

declare(strict_types=1);

class TeacherClass
{
    private PDO $pdo;

    // =====================================================
    // CONSTRUCTOR
    // =====================================================

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    // =====================================================
    // CHECK DUPLICATE ASSIGNMENT
    // =====================================================

    public function assignmentExists(
        int $teacherId,
        int $classId,
        ?int $sectionId,
        int $subjectId
    ): bool {

        if ($sectionId === null) {

            $sql = "
                SELECT id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND class_id = :class_id
                  AND section_id IS NULL
                  AND subject_id = :subject_id
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':teacher_id' => $teacherId,
                ':class_id'   => $classId,
                ':subject_id' => $subjectId
            ]);

        } else {

            $sql = "
                SELECT id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND class_id = :class_id
                  AND section_id = :section_id
                  AND subject_id = :subject_id
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':teacher_id' => $teacherId,
                ':class_id'   => $classId,
                ':section_id' => $sectionId,
                ':subject_id' => $subjectId
            ]);
        }

        return $stmt->fetch() !== false;
    }

    // =====================================================
    // ASSIGN TEACHER TO CLASS
    // =====================================================
    //
    // teacher_classes is the ONLY authoritative record of
    // what a teacher is allowed to teach (authorization).
    //
    // teacher_subjects is a derived projection that is kept
    // in sync here so display queries can never disagree
    // with the authorization data.
    // =====================================================

    public function assignClass(
        int $teacherId,
        int $classId,
        ?int $sectionId,
        int $subjectId
    ): bool {

        if (
            $this->assignmentExists(
                $teacherId,
                $classId,
                $sectionId,
                $subjectId
            )
        ) {
            return false;
        }

        $sql = "
            INSERT INTO teacher_classes
            (
                teacher_id,
                class_id,
                section_id,
                subject_id
            )
            VALUES
            (
                :teacher_id,
                :class_id,
                :section_id,
                :subject_id
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $created = $stmt->execute([
            ':teacher_id' => $teacherId,
            ':class_id'   => $classId,
            ':section_id' => $sectionId,
            ':subject_id' => $subjectId
        ]);

        if ($created) {
            $this->syncDerivedSubject(
                $teacherId,
                $subjectId,
                true
            );
        }

        return $created;
    }


    // =====================================================
    // KEEP teacher_subjects IN SYNC (DERIVED TABLE)
    // =====================================================

    private function syncDerivedSubject(
        int $teacherId,
        int $subjectId,
        bool $shouldExist
    ): void {

        if ($shouldExist) {

            // UNIQUE(teacher_id, subject_id) makes this idempotent.
            $sql = "
                INSERT IGNORE INTO teacher_subjects
                (
                    teacher_id,
                    subject_id
                )
                VALUES
                (
                    :teacher_id,
                    :subject_id
                )
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':teacher_id' => $teacherId,
                ':subject_id' => $subjectId
            ]);

            return;
        }

        // Only drop the derived row when the teacher has no
        // remaining class assignment for this subject.
        $check = $this->pdo->prepare(
            'SELECT id FROM teacher_classes
             WHERE teacher_id = :teacher_id
               AND subject_id = :subject_id
             LIMIT 1'
        );

        $check->execute([
            ':teacher_id' => $teacherId,
            ':subject_id' => $subjectId
        ]);

        if ($check->fetch()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM teacher_subjects
             WHERE teacher_id = :teacher_id
               AND subject_id = :subject_id'
        );

        $stmt->execute([
            ':teacher_id' => $teacherId,
            ':subject_id' => $subjectId
        ]);
    }

    // =====================================================
    // GET TEACHER ASSIGNED CLASSES
    // =====================================================

    public function getTeacherClasses(int $teacherId): array
    {
        $sql = "
            SELECT
                tc.id,

                tc.teacher_id,
                tc.class_id,
                tc.section_id,
                tc.subject_id,

                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code,
                sub.status AS subject_status,

                tc.created_at

            FROM teacher_classes AS tc

            INNER JOIN classes AS c
                ON c.id = tc.class_id

            LEFT JOIN sections AS sec
                ON sec.id = tc.section_id
               AND sec.class_id = tc.class_id

            INNER JOIN subjects AS sub
                ON sub.id = tc.subject_id

            WHERE tc.teacher_id = :teacher_id

            ORDER BY
                sub.name ASC,
                c.name ASC,
                sec.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':teacher_id' => $teacherId
        ]);

        return $stmt->fetchAll();
    }

    // =====================================================
    // GET ONE TEACHER CLASS ASSIGNMENT
    // =====================================================

    public function getAssignmentById(int $id): ?array
    {
        $sql = "
            SELECT
                tc.id,

                tc.teacher_id,
                tc.class_id,
                tc.section_id,
                tc.subject_id,

                t.name AS teacher_name,
                t.teacher_id AS teacher_code,

                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code,

                tc.created_at

            FROM teacher_classes AS tc

            INNER JOIN teachers AS t
                ON t.id = tc.teacher_id

            INNER JOIN classes AS c
                ON c.id = tc.class_id

            LEFT JOIN sections AS sec
                ON sec.id = tc.section_id
               AND sec.class_id = tc.class_id

            INNER JOIN subjects AS sub
                ON sub.id = tc.subject_id

            WHERE tc.id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $assignment = $stmt->fetch();

        return $assignment ?: null;
    }

    // =====================================================
    // REMOVE ASSIGNMENT
    // =====================================================
    //
    // Removes the authoritative teacher_classes row and,
    // when no other assignment covers the same subject,
    // the derived teacher_subjects row as well, so
    // authorization and display always agree.
    // =====================================================

    public function removeAssignment(int $id): bool
    {
        $lookup = $this->pdo->prepare(
            'SELECT teacher_id, subject_id
             FROM teacher_classes
             WHERE id = :id
             LIMIT 1'
        );
        $lookup->execute([':id' => $id]);

        $row = $lookup->fetch();

        if (!$row) {
            return false;
        }

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {
            $sql = "
                DELETE FROM teacher_classes
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([':id' => $id]);

            $removed = $stmt->rowCount() > 0;

            if ($removed) {
                $this->syncDerivedSubject(
                    (int) $row['teacher_id'],
                    (int) $row['subject_id'],
                    false
                );
            }

            if ($started) {
                $this->pdo->commit();
            }

            return $removed;

        } catch (Throwable $e) {

            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    // =====================================================
    // REMOVE A SUBJECT FROM A TEACHER (ALL CLASSES)
    // =====================================================
    //
    // Used by the "assigned subjects" screen. It deletes
    // the authoritative class assignments first, then the
    // derived subject row follows through the same sync.
    // =====================================================

    public function removeSubjectAssignments(
        int $teacherId,
        int $subjectId
    ): bool {

        $started = false;

        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM teacher_classes
                 WHERE teacher_id = :teacher_id
                   AND subject_id = :subject_id'
            );

            $stmt->execute([
                ':teacher_id' => $teacherId,
                ':subject_id' => $subjectId
            ]);

            $removed = $stmt->rowCount() > 0;

            $this->syncDerivedSubject(
                $teacherId,
                $subjectId,
                false
            );

            if ($started) {
                $this->pdo->commit();
            }

            return $removed;

        } catch (Throwable $e) {

            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    // =====================================================
    // GET ALL TEACHER CLASS ASSIGNMENTS
    // =====================================================

    public function getAllAssignments(): array
    {
        $sql = "
            SELECT
                tc.id,

                tc.teacher_id,
                tc.class_id,
                tc.section_id,
                tc.subject_id,

                t.teacher_id AS teacher_code,
                t.name AS teacher_name,

                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name,

                sub.name AS subject_name,
                sub.code AS subject_code,
                sub.status AS subject_status,

                tc.created_at

            FROM teacher_classes AS tc

            INNER JOIN teachers AS t
                ON t.id = tc.teacher_id

            INNER JOIN classes AS c
                ON c.id = tc.class_id

            LEFT JOIN sections AS sec
                ON sec.id = tc.section_id
               AND sec.class_id = tc.class_id

            INNER JOIN subjects AS sub
                ON sub.id = tc.subject_id

            ORDER BY
                t.name ASC,
                sub.name ASC,
                c.name ASC,
                sec.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }
}
