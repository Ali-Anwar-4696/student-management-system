<?php

declare(strict_types=1);

class Assignment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    // =====================================================
    // CREATE ASSIGNMENT
    // =====================================================

    public function createAssignment(array $data): bool
    {
        $sql = "
            INSERT INTO assignments (
                teacher_id,
                subject_id,
                class_id,
                section_id,
                title,
                description,
                due_date,
                status
            )
            VALUES (
                :teacher_id,
                :subject_id,
                :class_id,
                :section_id,
                :title,
                :description,
                :due_date,
                :status
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':teacher_id'  => (int) $data['teacher_id'],
            ':subject_id'  => (int) $data['subject_id'],
            ':class_id'    => (int) $data['class_id'],
            ':section_id'  => $data['section_id'] ?? null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':due_date'    => $data['due_date'],
            ':status'      => $data['status'] ?? 'active'
        ]);
    }


    // =====================================================
    // UPDATE ASSIGNMENT
    // =====================================================

    public function updateAssignment(int $id, array $data): bool
    {
        $sql = "
            UPDATE assignments
            SET
                subject_id = :subject_id,
                class_id = :class_id,
                section_id = :section_id,
                title = :title,
                description = :description,
                due_date = :due_date,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND teacher_id = :teacher_id
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id'          => $id,
            ':teacher_id'  => (int) $data['teacher_id'],
            ':subject_id'  => (int) $data['subject_id'],
            ':class_id'    => (int) $data['class_id'],
            ':section_id'  => $data['section_id'] ?? null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':due_date'    => $data['due_date'],
            ':status'      => $data['status'] ?? 'active'
        ]);
    }


    // =====================================================
    // CHECK DUPLICATE ASSIGNMENT
    // =====================================================

    public function assignmentExists(
        int $teacherId,
        int $subjectId,
        int $classId,
        ?int $sectionId,
        string $title,
        ?int $excludeAssignmentId = null
    ): bool {

        if ($sectionId === null) {

            $sql = "
                SELECT id
                FROM assignments
                WHERE teacher_id = :teacher_id
                  AND subject_id = :subject_id
                  AND class_id = :class_id
                  AND section_id IS NULL
                  AND title = :title
            ";

            $params = [
                ':teacher_id' => $teacherId,
                ':subject_id' => $subjectId,
                ':class_id'   => $classId,
                ':title'      => $title
            ];

        } else {

            $sql = "
                SELECT id
                FROM assignments
                WHERE teacher_id = :teacher_id
                  AND subject_id = :subject_id
                  AND class_id = :class_id
                  AND section_id = :section_id
                  AND title = :title
            ";

            $params = [
                ':teacher_id' => $teacherId,
                ':subject_id' => $subjectId,
                ':class_id'   => $classId,
                ':section_id' => $sectionId,
                ':title'      => $title
            ];
        }

        if ($excludeAssignmentId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeAssignmentId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }


    // =====================================================
    // GET ASSIGNMENT BY ID
    // =====================================================

    public function getAssignmentById(int $id): ?array
    {
        $sql = "
            SELECT
                a.id,
                a.teacher_id,
                a.subject_id,
                a.class_id,
                a.section_id,
                a.title,
                a.description,
                a.due_date,
                a.status,
                a.created_at,
                a.updated_at,

                t.name AS teacher_name,
                s.name AS subject_name,
                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name

            FROM assignments AS a

            INNER JOIN teachers AS t
                ON a.teacher_id = t.id

            INNER JOIN subjects AS s
                ON a.subject_id = s.id

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON sec.id = a.section_id
                AND sec.class_id = a.class_id

            WHERE a.id = :id

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
    // GET TEACHER ASSIGNMENTS
    // =====================================================

    public function getTeacherAssignments(int $teacherId): array
    {
        $sql = "
            SELECT
                a.id,
                a.teacher_id,
                a.subject_id,
                a.class_id,
                a.section_id,
                a.title,
                a.description,
                a.due_date,
                a.status,
                a.created_at,

                s.name AS subject_name,
                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name

            FROM assignments AS a

            INNER JOIN subjects AS s
                ON a.subject_id = s.id

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON sec.id = a.section_id
                AND sec.class_id = a.class_id

            WHERE a.teacher_id = :teacher_id

            ORDER BY
                a.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':teacher_id' => $teacherId
        ]);

        return $stmt->fetchAll();
    }


    // =====================================================
    // GET STUDENT ASSIGNMENTS
    // =====================================================

    public function getStudentAssignments(
        int $classId,
        int $sectionId,
        int $studentId
    ): array {

        $sql = "
            SELECT
                a.id,
                a.title,
                a.description,
                a.due_date,
                a.status,

                s.name AS subject_name,
                c.name AS class_name,

                COALESCE(
                    sec.name,
                    'Whole Class'
                ) AS section_name,

                sub.id AS submission_id,
                sub.submission_text,
                sub.file_path,
                sub.submitted_at,
                sub.marks,
                sub.feedback,
                sub.status AS submission_status

            FROM assignments AS a

            INNER JOIN subjects AS s
                ON a.subject_id = s.id

            INNER JOIN classes AS c
                ON a.class_id = c.id

            LEFT JOIN sections AS sec
                ON sec.id = a.section_id
                AND sec.class_id = a.class_id

            LEFT JOIN submissions AS sub
                ON a.id = sub.assignment_id
                AND sub.student_id = :student_id

            WHERE a.class_id = :class_id

              AND (
                    a.section_id = :section_id
                    OR a.section_id IS NULL
              )

              AND a.status = 'active'

            ORDER BY
                a.due_date ASC,
                a.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':student_id' => $studentId,
            ':class_id'   => $classId,
            ':section_id' => $sectionId
        ]);

        return $stmt->fetchAll();
    }


    // =====================================================
    // DELETE ASSIGNMENT
    // =====================================================

    public function deleteAssignment(
        int $id,
        int $teacherId
    ): bool {

        $stmt = $this->pdo->prepare("
            DELETE FROM assignments
            WHERE id = :id
              AND teacher_id = :teacher_id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':teacher_id' => $teacherId
        ]);
    }


    // =====================================================
    // GET SUBMISSIONS
    // =====================================================

    public function getSubmissions(
        int $assignmentId
    ): array {

        $stmt = $this->pdo->prepare("
            SELECT
                sub.id,
                sub.assignment_id,
                sub.student_id,
                sub.submission_text,
                sub.file_path,
                sub.submitted_at,
                sub.marks,
                sub.feedback,
                sub.status,

                st.name AS student_name,
                st.student_id AS student_code

            FROM submissions AS sub

            INNER JOIN students AS st
                ON sub.student_id = st.id

            WHERE sub.assignment_id = :assignment_id

            ORDER BY
                sub.submitted_at DESC,
                st.name ASC
        ");

        $stmt->execute([
            ':assignment_id' => $assignmentId
        ]);

        return $stmt->fetchAll();
    }


    // =====================================================
    // GET ONE SUBMISSION
    // =====================================================

    public function getSubmission(
        int $assignmentId,
        int $studentId
    ): ?array {

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM submissions
            WHERE assignment_id = :assignment_id
              AND student_id = :student_id
            LIMIT 1
        ");

        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':student_id'    => $studentId
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }


    // =====================================================
    // SUBMIT ASSIGNMENT
    // =====================================================

    public function submitAssignment(array $data): bool
    {
        $existing = $this->getSubmission(
            (int) $data['assignment_id'],
            (int) $data['student_id']
        );

        if (
            $existing &&
            $existing['status'] === 'graded'
        ) {
            return false;
        }

        if ($existing) {

            $stmt = $this->pdo->prepare("
                UPDATE submissions
                SET
                    submission_text = :text,
                    file_path = COALESCE(
                        :file_path,
                        file_path
                    ),
                    submitted_at = NOW(),
                    status = 'submitted'
                WHERE id = :id
                  AND status != 'graded'
            ");

            return $stmt->execute([
                ':text'      => $data['submission_text'] ?? null,
                ':file_path' => $data['file_path'] ?? null,
                ':id'        => $existing['id']
            ]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO submissions (
                assignment_id,
                student_id,
                submission_text,
                file_path,
                submitted_at,
                status
            )
            VALUES (
                :assignment_id,
                :student_id,
                :text,
                :file_path,
                NOW(),
                'submitted'
            )
        ");

        return $stmt->execute([
            ':assignment_id' => $data['assignment_id'],
            ':student_id'    => $data['student_id'],
            ':text'          => $data['submission_text'] ?? null,
            ':file_path'     => $data['file_path'] ?? null
        ]);
    }


    // =====================================================
    // GRADE SUBMISSION
    // =====================================================

    public function gradeSubmission(
        int $submissionId,
        int $teacherId,
        float $marks,
        ?string $feedback
    ): bool {

        $stmt = $this->pdo->prepare("
            UPDATE submissions AS sub

            INNER JOIN assignments AS a
                ON sub.assignment_id = a.id

            SET
                sub.marks = :marks,
                sub.feedback = :feedback,
                sub.status = 'graded'

            WHERE sub.id = :id
              AND a.teacher_id = :teacher_id
        ");

        return $stmt->execute([
            ':marks'      => $marks,
            ':feedback'   => $feedback,
            ':id'         => $submissionId,
            ':teacher_id' => $teacherId
        ]);
    }


    // =====================================================
    // SUBMISSION NOTIFICATION CONTEXT
    // =====================================================

    /**
     * Student id + assignment title for a submission, ownership-checked
     * through teacher_id. Used only to build the "graded" notification
     * after gradeSubmission() succeeded; returns null when the teacher
     * does not own the submission.
     */
    public function getSubmissionContext(
        int $submissionId,
        int $teacherId
    ): ?array {

        $stmt = $this->pdo->prepare("
            SELECT
                sub.id,
                sub.student_id,
                sub.marks,
                a.title
            FROM submissions AS sub
            INNER JOIN assignments AS a
                ON a.id = sub.assignment_id
            WHERE sub.id = :id
              AND a.teacher_id = :teacher_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id'         => $submissionId,
            ':teacher_id' => $teacherId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }


    // =====================================================
    // TEACHER OWNS ASSIGNMENT
    // =====================================================

    public function teacherOwnsAssignment(
        int $assignmentId,
        int $teacherId
    ): bool {

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM assignments
            WHERE id = :id
              AND teacher_id = :teacher_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id'         => $assignmentId,
            ':teacher_id' => $teacherId
        ]);

        return $stmt->fetch() !== false;
    }
}

