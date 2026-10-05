<?php

declare(strict_types=1);

class Exam
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO exams (name, type, start_date, end_date, class_id, status)
            VALUES (:name, :type, :start_date, :end_date, :class_id, :status)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':start_date' => $data['start_date'] ?: null,
            ':end_date' => $data['end_date'] ?: null,
            ':class_id' => $data['class_id'],
            ':status' => $data['status'] ?? 'upcoming',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE exams
            SET name = :name, type = :type, start_date = :start_date,
                end_date = :end_date, class_id = :class_id, status = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':start_date' => $data['start_date'] ?: null,
            ':end_date' => $data['end_date'] ?: null,
            ':class_id' => $data['class_id'],
            ':status' => $data['status'],
            ':id' => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM exams WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function existsForClass(string $name, int $classId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM exams WHERE name = :name AND class_id = :class_id';
        $params = [':name' => $name, ':class_id' => $classId];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name AS class_name
            FROM exams e
            INNER JOIN classes c ON e.class_id = c.id
            WHERE e.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(string $search = '', string $status = '', string $classId = ''): array
    {
        $sql = "
            SELECT e.*, c.name AS class_name
            FROM exams e
            INNER JOIN classes c ON e.class_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND e.name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }
        if (in_array($status, ['upcoming', 'active', 'completed'], true)) {
            $sql .= ' AND e.status = :status';
            $params[':status'] = $status;
        }
        if ($classId !== '' && ctype_digit($classId)) {
            $sql .= ' AND e.class_id = :class_id';
            $params[':class_id'] = (int) $classId;
        }

        $sql .= ' ORDER BY e.start_date DESC, e.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function addSubject(int $examId, int $subjectId, float $totalMarks, float $passMarks): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO exam_subjects (exam_id, subject_id, total_marks, pass_marks)
            VALUES (:exam_id, :subject_id, :total_marks, :pass_marks)
        ");

        try {
            return $stmt->execute([
                ':exam_id' => $examId,
                ':subject_id' => $subjectId,
                ':total_marks' => $totalMarks,
                ':pass_marks' => $passMarks,
            ]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return false;
            }
            throw $e;
        }
    }

    public function removeSubject(int $examId, int $examSubjectId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM exam_subjects WHERE id = :id AND exam_id = :exam_id'
        );
        $stmt->execute([':id' => $examSubjectId, ':exam_id' => $examId]);

        return $stmt->rowCount() > 0;
    }

    public function getSubjects(int $examId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT es.*, s.name AS subject_name, s.code AS subject_code
            FROM exam_subjects es
            INNER JOIN subjects s ON es.subject_id = s.id
            WHERE es.exam_id = :exam_id
            ORDER BY s.name
        ");
        $stmt->execute([':exam_id' => $examId]);

        return $stmt->fetchAll();
    }
}
