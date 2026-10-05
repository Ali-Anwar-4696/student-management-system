<?php

declare(strict_types=1);

class Mark
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Canonical marks writer.
     *
     * Admin and Teacher both go through this single method so that every mark
     * row is produced by exactly one pipeline with identical columns, totals
     * and audit fields.
     *
     * The unique key on (exam_id, student_id, subject_id) guarantees one row
     * per exam/subject/student: re-saving a mark UPDATES that row instead of
     * creating a duplicate. The write is atomic (no read-then-write race).
     */
    public function save(int $examId, int $studentId, int $subjectId, float $total, float $obtained, ?int $userId = null): bool
    {
        if ($total <= 0) {
            return false;
        }

        if ($obtained < 0 || $obtained > $total) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO marks (
                exam_id,
                student_id,
                subject_id,
                total_marks,
                obtained_marks,
                created_by,
                updated_by
            )
            VALUES (
                :exam_id,
                :student_id,
                :subject_id,
                :total_marks,
                :obtained_marks,
                :created_by,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                total_marks = VALUES(total_marks),
                obtained_marks = VALUES(obtained_marks),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            ':exam_id' => $examId,
            ':student_id' => $studentId,
            ':subject_id' => $subjectId,
            ':total_marks' => $total,
            ':obtained_marks' => $obtained,
            ':created_by' => $userId,
            ':updated_by' => $userId,
        ]);
    }

    /**
     * Authoritative maximum marks for an exam subject.
     * Returns null when the exam subject has not been configured.
     */
    public function getExamSubjectTotal(int $examId, int $subjectId): ?float
    {
        $stmt = $this->pdo->prepare("
            SELECT total_marks
            FROM exam_subjects
            WHERE exam_id = :exam_id AND subject_id = :subject_id
            LIMIT 1
        ");
        $stmt->execute([
            ':exam_id' => $examId,
            ':subject_id' => $subjectId,
        ]);

        $total = $stmt->fetchColumn();

        if ($total === false || $total === null) {
            return null;
        }

        return (float) $total;
    }

    public function getForExam(int $examId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, st.name AS student_name, st.student_id AS student_code, s.name AS subject_name
            FROM marks m
            INNER JOIN students st ON m.student_id = st.id
            INNER JOIN subjects s ON m.subject_id = s.id
            WHERE m.exam_id = :exam_id
            ORDER BY st.name, s.name
        ");
        $stmt->execute([':exam_id' => $examId]);

        return $stmt->fetchAll();
    }

    public function getForExamWithAudit(int $examId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, st.name AS student_name, st.student_id AS student_code, s.name AS subject_name,
                   m.created_by, m.updated_by
            FROM marks m
            INNER JOIN students st ON m.student_id = st.id
            INNER JOIN subjects s ON m.subject_id = s.id
            WHERE m.exam_id = :exam_id
            ORDER BY st.name, s.name
        ");
        $stmt->execute([':exam_id' => $examId]);

        return $stmt->fetchAll();
    }

    public function getStudentExamMarks(int $examId, int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, s.name AS subject_name, es.pass_marks
            FROM marks m
            INNER JOIN subjects s ON m.subject_id = s.id
            LEFT JOIN exam_subjects es ON es.exam_id = m.exam_id AND es.subject_id = m.subject_id
            WHERE m.exam_id = :exam_id AND m.student_id = :student_id
            ORDER BY s.name
        ");
        $stmt->execute([':exam_id' => $examId, ':student_id' => $studentId]);

        return $stmt->fetchAll();
    }

    public function calculateResult(int $examId, int $studentId): ?array
    {
        $marks = $this->getStudentExamMarks($examId, $studentId);
        if ($marks === []) {
            return null;
        }

        $total = 0.0;
        $obtained = 0.0;
        $passedAll = true;

        foreach ($marks as $row) {
            $total += (float) $row['total_marks'];
            $obtained += (float) $row['obtained_marks'];
            $pass = $row['pass_marks'] !== null ? (float) $row['pass_marks'] : ($row['total_marks'] * 0.4);
            if ((float) $row['obtained_marks'] < $pass) {
                $passedAll = false;
            }
        }

        $percent = $total > 0 ? round(($obtained / $total) * 100, 2) : 0.0;

        return [
            'total_marks' => $total,
            'obtained_marks' => $obtained,
            'percentage' => $percent,
            'grade' => $this->letterGrade($percent),
            'status' => $passedAll && $percent >= 40 ? 'Pass' : 'Fail',
            'subjects' => $marks,
        ];
    }

    private function letterGrade(float $percent): string
    {
        if ($percent >= 80) {
            return 'A';
        }
        if ($percent >= 70) {
            return 'B';
        }
        if ($percent >= 60) {
            return 'C';
        }
        if ($percent >= 50) {
            return 'D';
        }
        return 'F';
    }
}
