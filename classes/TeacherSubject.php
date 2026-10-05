<?php
declare(strict_types=1);

/**
 *=========================================================================
 * teacher_subjects — DERIVED / READ-ONLY VIEW MODEL
 *=========================================================================
 *
 * The single authoritative record of what a teacher may teach is
 * teacher_classes (class + section + subject) — see classes/TeacherClass.php.
 *
 * teacher_subjects is a synchronized projection of the distinct subjects
 * appearing in teacher_classes. Every write happens inside TeacherClass
 * (assign/remove), so this class is intentionally READ-ONLY: it must
 * never insert or delete rows on its own, otherwise the two tables could
 * disagree again.
 */
class TeacherSubject
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =====================================================
    // Get all subjects assigned to a teacher (derived list)
    // =====================================================
    public function getTeacherSubjects(
        int $teacherId
    ): array {

        $sql = "
            SELECT
                ts.id,
                ts.teacher_id,
                ts.subject_id,
                s.name AS subject_name,
                s.code AS subject_code,
                s.status AS subject_status,
                ts.created_at
            FROM teacher_subjects ts

            INNER JOIN subjects s
                ON ts.subject_id = s.id

            WHERE ts.teacher_id = :teacher_id

            ORDER BY s.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':teacher_id' => $teacherId
        ]);

        return $stmt->fetchAll();
    }


    // =====================================================
    // Get single teacher-subject assignment
    // =====================================================
    // Used by remove-subject.php to learn which teacher and
    // subject the removal refers to; the actual deletion is
    // performed by TeacherClass::removeSubjectAssignments().
    // =====================================================
    public function getAssignmentById(
        int $id
    ): ?array {

        $sql = "
            SELECT
                ts.id,
                ts.teacher_id,
                ts.subject_id,
                t.name AS teacher_name,
                t.teacher_id AS teacher_code,
                s.name AS subject_name,
                s.code AS subject_code,
                ts.created_at

            FROM teacher_subjects ts

            INNER JOIN teachers t
                ON ts.teacher_id = t.id

            INNER JOIN subjects s
                ON ts.subject_id = s.id

            WHERE ts.id = :id

            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $assignment = $stmt->fetch();

        return $assignment ?: null;
    }
}
